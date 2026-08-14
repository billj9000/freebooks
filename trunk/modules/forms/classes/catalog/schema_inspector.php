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
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/forms/classes/catalog/schema_inspector.php
//
/**
 * Forms — what the live database actually contains (design §3.3).
 *
 * This is the class that makes the rule "a dataset author picks identifiers;
 * they never supply SQL syntax" true rather than aspirational. Every name the
 * admin UI offers comes from here, and every name it accepts is checked against
 * here before it is stored. A name that is not in this inspector's answer is not
 * a name the author may use — so there is no path by which typed text becomes
 * part of a statement.
 *
 * PhreeForm's equivalent was a free-form `<relationship>` textbox pasted into
 * the WHERE clause, which is the whole of §1.1. There is deliberately no method
 * on this class that accepts a fragment.
 *
 * Two responsibilities beyond listing:
 *
 *  - **The prefix.** Datasets store unprefixed names, because the query builder
 *    applies `DB_PREFIX` when it introduces a table. So this strips the prefix
 *    on the way out and adds it on the way in; a prefixed install and a plain
 *    one present the author with exactly the same list.
 *  - **The sensitive set.** `users`, `configuration` and the rest are filtered
 *    out of every listing, so they cannot be picked. That is a convenience, not
 *    the guarantee: `Dataset` refuses them at construction whatever this class
 *    says, which is what protects a definition edited straight into the table.
 */

namespace FreeBooks\Forms\Catalog;

final class SchemaInspector
{
    /** @var array<string,list<string>>|null tables => columns, unprefixed; loaded once */
    private ?array $schema = null;

    public function __construct(
        private readonly \queryFactory $db,
        /** null takes DB_PREFIX; pass explicitly to test a prefixed install. */
        private readonly ?string $tablePrefix = null,
    ) {}

    /**
     * Tables an author may build a dataset on, unprefixed and sorted.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return array_keys($this->schema());
    }

    /**
     * Columns of one table, unprefixed and sorted, or [] if the table is not
     * one an author may use — which includes it not existing at all. The two
     * cases are deliberately indistinguishable to a caller offering choices;
     * `reject()` gives the reason when one is needed.
     *
     * @return list<string>
     */
    public function columns(string $table): array
    {
        return $this->schema()[$table] ?? [];
    }

    public function hasTable(string $table): bool
    {
        return isset($this->schema()[$table]);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    /**
     * Why this table may not be used, or null if it may.
     *
     * Separated from hasTable() so the UI can say "that is a sensitive table"
     * rather than "no such table", which would be both untrue and confusing.
     */
    public function reject(string $table): ?string
    {
        if (in_array(strtolower($table), Dataset::SENSITIVE_TABLES, true)) {
            return "'$table' holds credentials or configuration and is reserved to"
                 . " datasets shipped with FreeBooks.";
        }
        if (!$this->hasTable($table)) {
            return "There is no table '$table' in this database.";
        }
        return null;
    }

    /**
     * Columns that plausibly join two tables: names present in both.
     *
     * Only a convenience for the join builder — it narrows a pair of long lists
     * to the handful worth looking at. The author still chooses, and both sides
     * are validated as ordinary columns, so a correct join this misses is still
     * expressible.
     *
     * @return list<string>
     */
    public function sharedColumns(string $left, string $right): array
    {
        return array_values(array_intersect($this->columns($left), $this->columns($right)));
    }

    /**
     * A best guess at what kind of value a column holds, from its SQL type.
     *
     * The author can override it. Guessing matters because the alternative is a
     * form that asks a bookkeeper to classify forty columns by hand, which ends
     * with everything left on Text and every money column exporting as a string
     * — the defect Forms exists to remove (§1.2).
     */
    public function valueTypeOf(string $table, string $column): \FreeBooks\Forms\Document\ValueType
    {
        $type = strtolower($this->columnTypes()[$table][$column] ?? '');
        return match (true) {
            str_contains($type, 'decimal'), str_contains($type, 'numeric'),
            str_contains($type, 'float'), str_contains($type, 'double')
                => \FreeBooks\Forms\Document\ValueType::Decimal,
            str_contains($type, 'date'), str_contains($type, 'timestamp')
                => \FreeBooks\Forms\Document\ValueType::Date,
            str_contains($type, 'tinyint(1)')
                => \FreeBooks\Forms\Document\ValueType::Boolean,
            str_contains($type, 'int')
                => \FreeBooks\Forms\Document\ValueType::Integer,
            default
                => \FreeBooks\Forms\Document\ValueType::Text,
        };
    }

    private function prefix(): string
    {
        $prefix = $this->tablePrefix ?? (defined('DB_PREFIX') ? (string)DB_PREFIX : '');
        // Same check the query builder makes: the prefix reaches a statement
        // unquoted, so it has to be a bare identifier prefix wherever it is read.
        if ($prefix !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new CatalogException("DB_PREFIX '$prefix' is not a bare identifier prefix.");
        }
        return $prefix;
    }

    /** @return array<string,list<string>> */
    private function schema(): array
    {
        if ($this->schema !== null) return $this->schema;

        $types = $this->columnTypes();
        $out   = [];
        foreach ($types as $table => $columns) {
            $out[$table] = array_keys($columns);
        }
        return $this->schema = $out;
    }

    /** @var array<string,array<string,string>>|null table => column => sql type */
    private ?array $types = null;

    /**
     * The whole schema in one query.
     *
     * `information_schema` rather than SHOW TABLES followed by a SHOW COLUMNS
     * per table: the latter is one round trip per table — over a hundred on a
     * FreeBooks install — to build a form. The values are bound; the only thing
     * interpolated anywhere in this class is the prefix, checked above.
     *
     * @return array<string,array<string,string>>
     */
    private function columnTypes(): array
    {
        if ($this->types !== null) return $this->types;

        $prefix = $this->prefix();
        $result = $this->db->ExecutePrepared(
            "select table_name, column_name, column_type
               from information_schema.columns
              where table_schema = database()
              order by table_name, ordinal_position", []);

        $types = [];
        while (!$result->EOF) {
            $table  = (string)($result->fields['table_name']  ?? $result->fields['TABLE_NAME']  ?? '');
            $column = (string)($result->fields['column_name'] ?? $result->fields['COLUMN_NAME'] ?? '');
            $sql    = (string)($result->fields['column_type'] ?? $result->fields['COLUMN_TYPE'] ?? '');
            $result->MoveNext();

            if ($prefix !== '') {
                if (!str_starts_with($table, $prefix)) continue;   // not ours
                $table = substr($table, strlen($prefix));
            }
            if ($table === '' || $column === '') continue;
            // Filtered here so no listing can offer them. The guarantee is in
            // Dataset's constructor; this only keeps them off the screen.
            if (in_array(strtolower($table), Dataset::SENSITIVE_TABLES, true)) continue;
            // Anything whose name is not a bare identifier could not be used
            // safely even if an author picked it, so it is never offered.
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) continue;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) continue;

            $types[$table][$column] = $sql;
        }
        ksort($types);
        return $this->types = $types;
    }
}
