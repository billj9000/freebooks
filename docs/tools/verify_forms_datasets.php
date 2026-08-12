<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /docs/tools/verify_forms_datasets.php
//
/**
 * Check every shipped dataset against the real schema (design §8.4).
 *
 * `verify_forms_catalog.php` attacks the catalog's SAFETY guarantees and needs
 * no database. This is the other half: are the datasets TRUE? Identifier::check
 * proves a name is syntactically an identifier, never that the column exists —
 * so a typo, a column that moved, or a plausible-looking guess passes every
 * existing check and fails only when some report finally selects it.
 *
 * That is not hypothetical. `journal_main.dept_rep_id` was added to the
 * Transactions dataset because a shipped PhreeForm report filters on it; the
 * column is on `contacts`, not `journal_main`, and the legacy definition only
 * ever worked because that filter defaulted to ALL and was never emitted.
 *
 * Every field, every join condition and every table a computed expression
 * mentions is checked against SHOW COLUMNS. A dataset is also asked to answer a
 * real SELECT, which catches the things a column census cannot — a join whose
 * condition compares incompatible sides, or an expression that does not parse.
 *
 * NOT part of the application. A dev aid, run inside the container.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /tmp/fbtools/verify_forms_datasets.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');

fb_bootstrap($argv[1] ?? null, false);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');

use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets, QueryBuilder};

$failures = 0;
function fail(string $what): void { global $failures; $failures++; echo "  [FAIL] $what\n"; }

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

/** @var array<string,array<string,true>> $schema table => columns */
$schema = [];
$columnsOf = function (string $table) use (&$schema): array {
    global $db;
    if (isset($schema[$table])) return $schema[$table];
    $cols = [];
    try {
        $r = $db->Execute("SHOW COLUMNS FROM " . DB_PREFIX . $table);
        while (!$r->EOF) { $cols[$r->fields['Field']] = true; $r->MoveNext(); }
    } catch (\Throwable) { /* missing table reported by the caller */ }
    return $schema[$table] = $cols;
};

$totalFields = 0;
foreach ($catalog->all() as $dataset) {
    $tables = $dataset->tables();
    echo "\n=== {$dataset->id} (" . count($dataset->fields) . " fields, "
       . count($dataset->joins) . " joins) ===\n";

    // Every table the dataset names must exist.
    foreach ($tables as $table) {
        if ($columnsOf($table) === []) fail("{$dataset->id}: table '$table' does not exist");
    }

    // Every plain field must name a real column of a table the dataset joins.
    foreach ($dataset->fields as $key => $field) {
        $totalFields++;
        if ($field->table !== null && !in_array($field->table, $tables, true)) {
            fail("{$dataset->id}.$key: table '{$field->table}' is not in this dataset");
            continue;
        }
        if ($field->column === null) continue;                  // computed
        $cols = $columnsOf((string)$field->table);
        if ($cols !== [] && !isset($cols[$field->column])) {
            fail("{$dataset->id}.$key: {$field->table}.{$field->column} does not exist");
        }
    }

    // Join conditions name columns too, and a wrong one here is worse than a
    // wrong field: it silently returns no matches rather than erroring.
    foreach ($dataset->joins as $join) {
        foreach ($join->on as $c) {
            foreach ([[$c->leftTable, $c->leftColumn], [$c->rightTable, $c->rightColumn]] as [$t, $col]) {
                $cols = $columnsOf($t);
                if ($cols !== [] && !isset($cols[$col])) {
                    fail("{$dataset->id}: join onto '{$join->table}' references $t.$col, which does not exist");
                }
            }
        }
    }

    // Ask the database to parse it. Selecting every field at once exercises each
    // expression and every join in one statement; LIMIT 1 keeps it cheap.
    $query = (new QueryBuilder())->build($dataset, [], [], [], 1);
    try {
        $db->ExecutePrepared($query->sql, $query->params);
        echo "  [ok] selects all " . count($dataset->fields) . " fields\n";
    } catch (\Throwable $e) {
        fail("{$dataset->id}: full SELECT failed — " . $e->getMessage());
        echo "        " . substr($query->sql, 0, 400) . "\n";
    }

    // And that a join actually matches something. An ON clause comparing the
    // wrong pair of columns is syntactically perfect and quietly matches no
    // rows, which looks like "there is no data" rather than a defect.
    foreach ($dataset->joins as $join) {
        // Only joins hanging directly off the base table can be tested in
        // isolation; one further down a chain needs its parent in the FROM.
        // That is a limit of this probe, not a fault in the dataset.
        $reachable = true;
        $on = [];
        foreach ($join->on as $c) {
            $on[] = $c->toSql();
            foreach ([$c->leftTable, $c->rightTable] as $t) {
                if ($t !== $dataset->baseTable && $t !== $join->table) $reachable = false;
            }
        }
        if (!$reachable) {
            printf("  [--] join %-28s not directly testable from %s\n", $join->table, $dataset->baseTable);
            continue;
        }
        // ExecutePrepared, not Execute: queryFactory's Execute() reports a bad
        // query by terminating the process, which ends the run mid-dataset and
        // looks like the tool passing.
        $sql = "SELECT COUNT(*) n FROM " . DB_PREFIX . $dataset->baseTable
             . " JOIN " . DB_PREFIX . $join->table . " ON " . implode(' AND ', $on);
        try {
            $r = $db->ExecutePrepared($sql);
            $n = (int)($r->fields['n'] ?? 0);
            if ($n > 0) { printf("  [ok] join %-28s matches %d row(s)\n", $join->table, $n); continue; }

            // Zero matches only means a wrong ON clause if there was something
            // to match. This company has no work orders at all, so the
            // work_orders joins legitimately match nothing — failing on that
            // would make the tool useless on any install missing a feature.
            $rows = fn(string $t) => (int)($db->ExecutePrepared(
                "SELECT COUNT(*) n FROM " . DB_PREFIX . $t)->fields['n'] ?? 0);
            $left = $rows($dataset->baseTable);
            $right = $rows($join->table);
            if ($left === 0 || $right === 0) {
                printf("  [--] join %-28s untested (%s is empty)\n", $join->table,
                    $left === 0 ? $dataset->baseTable : $join->table);
            } else {
                printf("  [FAIL] join %-28s matches nothing across %d x %d rows\n", $join->table, $left, $right);
                fail("{$dataset->id}: join onto '{$join->table}' matches no rows despite both tables having data");
            }
        } catch (\Throwable $e) {
            fail("{$dataset->id}: join onto '{$join->table}' failed — " . $e->getMessage());
        }
    }
}

echo "\n" . ($failures === 0
    ? "PASS - " . count($catalog->all()) . " datasets, $totalFields fields, all resolve against the schema.\n"
    : "FAIL - $failures problem(s).\n");
exit($failures === 0 ? 0 : 1);
