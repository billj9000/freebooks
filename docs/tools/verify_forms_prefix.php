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
//  Path: /docs/tools/verify_forms_prefix.php
//
/**
 * Prove the catalog works on a table-prefixed install (design §8.5).
 *
 * FreeBooks lets an installer set DB_PREFIX, and every database available here
 * uses an empty one — so prefix support is exactly the kind of thing that can
 * be quietly broken for years. Rather than reason about the generated SQL, this
 * builds a prefixed schema and runs against it.
 *
 * Each table the shipped datasets touch is copied into a session TEMPORARY
 * table named pb_<table>. Temporary tables are visible only to the connection
 * that made them and vanish when it closes, so no company database is created,
 * altered or dropped. Every dataset's full SELECT then runs twice — unprefixed
 * against the real tables, prefixed against the copies — and the row sets are
 * compared.
 *
 * The comparison deliberately uses no LIMIT. With no ORDER BY, an indexed table
 * and a heap copy return different windows of the same rows, so a LIMIT makes
 * identical result sets look like a mismatch. It compares multisets for the
 * same reason: neither query defines an order.
 *
 * NOT part of the application. A dev aid, run inside the container.
 *
 *   docker exec <c> php -d display_errors=1 -d memory_limit=1G \
 *       /tmp/fbtools/verify_forms_prefix.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');

fb_bootstrap($argv[1] ?? null, false);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');

use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets, QueryBuilder};

global $db;
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

$tables = [];
foreach ($catalog->all() as $ds) foreach ($ds->tables() as $t) $tables[$t] = true;
$tables = array_keys($tables);

// ── one point in time, as far as the storage engine allows ──────────────────
//
// The copies are taken one table at a time and the unprefixed query then reads
// the LIVE table, so anything written in between shows up as a mismatch. That
// is not hypothetical: this failed twice on companies that are being written
// to, both times on audit_log, both times by exactly one row.
//
// A consistent snapshot settles it for InnoDB — every read in this transaction,
// including the CREATE TEMPORARY ... SELECT and the later live SELECTs, sees the
// database as it was when the transaction opened.
//
// It settles NOTHING for MyISAM, which ignores MVCC entirely, and five of the
// tables these datasets touch are MyISAM — including audit_log, the one that
// actually moved. Those are handled below, at the only point where it matters.
$db->Execute("START TRANSACTION WITH CONSISTENT SNAPSHOT");

/** Tables the snapshot above does not cover, with their row counts as taken. */
$volatile = [];
$engines  = $db->Execute("select table_name, engine from information_schema.tables
    where table_schema = database() and table_name in ('" . implode("','", $tables) . "')");
while ($engines && !$engines->EOF) {
    if (strcasecmp((string)$engines->fields['engine'], 'InnoDB') !== 0) {
        $volatile[(string)$engines->fields['table_name']] = null;
    }
    $engines->MoveNext();
}

foreach ($tables as $t) {
    $db->ExecutePrepared("CREATE TEMPORARY TABLE pb_$t AS SELECT * FROM $t");
}
/** Counted AFTER the copies, so this is what the copies contain. */
function rowCount($db, string $table): ?int {
    $r = $db->Execute("select count(*) as n from $table");
    return $r && !$r->EOF ? (int)$r->fields['n'] : null;
}
foreach (array_keys($volatile) as $t) $volatile[$t] = rowCount($db, $t);

printf("copied %d tables into session TEMPORARY pb_* tables\n", count($tables));
printf("  %d under a consistent snapshot; %d not transactional and watched instead%s\n\n",
    count($tables) - count($volatile), count($volatile),
    $volatile ? ' (' . implode(', ', array_keys($volatile)) . ')' : '');

$failures  = 0;
$unstable  = [];
function fail(string $message): void { global $failures; $failures++; echo "  [FAIL] $message\n"; }

$total = 0;
foreach ($catalog->all() as $ds) {
    $plain    = (new QueryBuilder(''))->build($ds);
    $prefixed = (new QueryBuilder('pb_'))->build($ds);

    // Structural: a prefix belongs only where a table is INTRODUCED. On the
    // alias it would defeat the point; on a column reference it names a table
    // that is not in the FROM clause.
    if (preg_match('/AS pb_/', $prefixed->sql)) {
        fail("{$ds->id}: an alias carries the prefix");
    }
    if (preg_match('/\bpb_[A-Za-z0-9_]*\./', $prefixed->sql)) {
        fail("{$ds->id}: a column reference carries the prefix");
    }
    if (str_contains($prefixed->sql, '{{')) {
        fail("{$ds->id}: an expression table placeholder was not expanded");
    }
    $introduced = preg_match_all('/(?:FROM|JOIN)\s+pb_[A-Za-z0-9_]+/', $prefixed->sql);
    // One for the base table plus one per join actually emitted. A subquery in
    // a computed expression introduces its own instance, so more is fine.
    $expected = 1 + count(array_filter($ds->joins, fn($j) => str_contains($prefixed->sql, ' AS ' . $j->table)));
    if ($introduced < $expected) {
        fail("{$ds->id}: only $introduced of $expected table introductions prefixed");
    }

    $bag = function (string $sql, array $params) use ($db): array {
        $result = $db->ExecutePrepared($sql, $params);
        $rows = [];
        while (!$result->EOF) { $rows[] = json_encode($result->fields); $result->MoveNext(); }
        sort($rows);
        return $rows;
    };
    $a = $bag($plain->sql, $plain->params);
    $b = $bag($prefixed->sql, $prefixed->params);

    $same   = $a === $b;
    $total += count($a);

    // A difference is only a difference if the ground held still. Checked here
    // rather than up front, because it costs a COUNT(*) per table and is only
    // worth paying when there is something to explain.
    $moved = [];
    if (!$same) {
        foreach ($ds->tables() as $t) {
            if (!array_key_exists($t, $volatile)) continue;   // InnoDB: the snapshot covers it
            if (rowCount($db, $t) !== $volatile[$t]) $moved[] = $t;
        }
    }

    if ($same) {
        printf("  [ok] %-15s %d table(s) prefixed, %d rows identical\n", $ds->id, $introduced, count($a));
    } elseif ($moved !== []) {
        // Not a pass and not a failure: the question could not be asked. Said
        // out loud so a company under load does not quietly stop being checked.
        printf("  [--] %-15s not comparable — %s changed while the check ran\n",
            $ds->id, implode(', ', $moved));
        $unstable[] = $ds->id;
    } else {
        fail("{$ds->id}: " . count($a) . " unprefixed vs " . count($b) . " prefixed rows, or differing values");
    }
}

$db->Execute("COMMIT");

if ($unstable !== []) {
    printf("\n  [--] %d dataset(s) could not be compared because a non-transactional table\n"
         . "       was written to while this ran: %s\n"
         . "       Re-run on a quiet database to check them.\n",
        count($unstable), implode(', ', $unstable));
}
// And the floor under all of it: skipping is honest for one dataset and a
// vacuous pass for all of them. A tool that reports success while comparing
// nothing is the failure this whole suite is built to distrust.
if (count($unstable) === count($catalog->all())) {
    fail('nothing could be compared at all — every dataset moved while this ran');
}

echo "\n" . ($failures === 0
    ? "PASS - " . (count($catalog->all()) - count($unstable)) . " of " . count($catalog->all())
      . " datasets, $total rows, identical on a prefixed schema.\n"
    : "FAIL - $failures problem(s).\n");
exit($failures === 0 ? 0 : 1);
