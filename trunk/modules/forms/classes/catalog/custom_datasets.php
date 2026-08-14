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
//  Path: /modules/forms/classes/catalog/custom_datasets.php
//
/**
 * Forms — datasets defined through the admin UI (design §3.2, §3.3).
 *
 * The counterpart to ShippedDatasets: same `Dataset` objects, same query
 * builder, different tier and a narrower set of powers. A custom dataset may
 * declare structured joins and plain columns; it may not write a SQL expression
 * and may not touch the sensitive tables. Those limits are enforced in
 * `Dataset`'s constructor, not here — so every path that produces one, including
 * this store, is bound by them.
 *
 * ## Why a stored definition is treated as untrusted input
 *
 * It would be easy to validate on save and trust what comes back. This does not,
 * and the reason is worth stating: the row is reachable by anything with
 * database access, by a restore of an older backup, and by a future version of
 * this code with a different idea of what is safe. Loading is therefore the same
 * checked construction as saving, and a row that fails is SKIPPED with a
 * message rather than thrown — one malformed dataset must not take the reports
 * page down with it.
 *
 * The security guarantee does not depend on the JSON being well-formed, because
 * every identifier in it passes `Identifier::check()` and every table passes the
 * sensitive-set test before any of it reaches a statement.
 */

namespace FreeBooks\Forms\Catalog;

use FreeBooks\Forms\Document\ValueType;

/**
 * Loading, saving and validating the DB-stored datasets.
 *
 * TABLE_FORMS_DATASETS holds one row per dataset: its id, its label, and the
 * whole definition as JSON. JSON rather than a normalised set of tables because
 * a definition is read and written whole, never queried into — and because a
 * schema change to the definition format should not be a database migration.
 */
final class CustomDatasetStore
{
    /** @var list<string> */
    private array $problems = [];

    public function __construct(
        private readonly \queryFactory   $db,
        private readonly SchemaInspector $schema,
        private readonly string          $table,
    ) {}

    /**
     * Every stored dataset that is currently valid.
     *
     * @return list<Dataset>
     */
    public function all(): array
    {
        $this->problems = [];
        $datasets = [];

        // An installation enabled before custom datasets existed has no table
        // until the module is updated. Reports must keep working meanwhile:
        // "there are no custom datasets" is the right answer, and taking the
        // page down over it would not be.
        if (function_exists('db_table_exists') && !db_table_exists($this->table)) {
            return [];
        }

        $result = $this->db->ExecutePrepared(
            "select id, definition from " . $this->table . " order by id", []);
        while (!$result->EOF) {
            $id         = (string)$result->fields['id'];
            $definition = (string)$result->fields['definition'];
            $result->MoveNext();

            try {
                $datasets[] = $this->hydrate($id, $this->decode($definition));
            } catch (\Throwable $e) {
                // Named, not swallowed, and not fatal: a broken custom dataset
                // must cost its own reports, not everybody's.
                $this->problems[] = "Custom dataset '$id' was skipped: " . $e->getMessage();
            }
        }
        return $datasets;
    }

    /** Register every valid stored dataset onto a catalog, shipped ones winning. */
    public function registerAll(Catalog $catalog): void
    {
        foreach ($this->all() as $dataset) {
            if ($catalog->has($dataset->id)) {
                // A shipped dataset is version-controlled and may use
                // expressions; letting a stored row shadow one would be a way
                // to redefine a trusted dataset from the database.
                $this->problems[] = "Custom dataset '{$dataset->id}' was ignored: a dataset shipped"
                                  . " with FreeBooks already uses that name.";
                continue;
            }
            $catalog->register($dataset);
        }
    }

    /** @return list<string> anything skipped by the last all()/registerAll(), and why */
    public function problems(): array
    {
        return $this->problems;
    }

    /** The raw stored form, for the editor. @return array<string,mixed>|null */
    public function load(string $id): ?array
    {
        $fields = $this->db->ExecutePrepared(
            "select id, label, description, definition from " . $this->table . " where id = ?",
            [$id])->fields;
        if (!$fields) return null;

        $definition = $this->decode((string)$fields['definition']);
        return [
            'id'          => (string)$fields['id'],
            'label'       => (string)$fields['label'],
            'description' => (string)($fields['description'] ?? ''),
            'baseTable'   => (string)($definition['baseTable'] ?? ''),
            'joins'       => $definition['joins']  ?? [],
            'fields'      => $definition['fields'] ?? [],
        ];
    }

    /**
     * Validate and store.
     *
     * Construction happens FIRST and its result is discarded: if the definition
     * cannot become a Dataset, nothing is written. Saving a definition that
     * cannot be loaded would leave an admin with a row they can neither run nor
     * see the problem with.
     *
     * @param array<string,mixed> $input
     * @throws CatalogException
     */
    public function save(array $input, bool $isNew): string
    {
        $id = strtolower(trim((string)($input['id'] ?? '')));
        Identifier::check($id, 'dataset name');

        $exists = (bool)$this->db->ExecutePrepared(
            "select id from " . $this->table . " where id = ?", [$id])->fields;
        if ($isNew && $exists) {
            throw new CatalogException("A custom dataset named '$id' already exists.");
        }
        if (!$isNew && !$exists) {
            throw new CatalogException("There is no custom dataset named '$id'.");
        }

        $definition = $this->normalise($input);
        $this->hydrate($id, $definition + ['label' => (string)($input['label'] ?? $id)]);

        // Bound rather than db_perform(): its update takes a raw WHERE clause,
        // and although this id has already been through Identifier::check(),
        // a stored definition is not the place to depend on a check made
        // somewhere else in the file.
        $label       = trim((string)($input['label'] ?? $id));
        $description = trim((string)($input['description'] ?? ''));
        $json        = json_encode($definition, JSON_THROW_ON_ERROR);
        $now         = date('Y-m-d H:i:s');

        if ($isNew) {
            $this->db->ExecutePrepared("insert into " . $this->table . "
                (id, label, description, definition, create_date, last_update)
                values (?, ?, ?, ?, ?, ?)", [$id, $label, $description, $json, $now, $now]);
        } else {
            $this->db->ExecutePrepared("update " . $this->table . "
                set label = ?, description = ?, definition = ?, last_update = ?
                where id = ?", [$label, $description, $json, $now, $id]);
        }
        return $id;
    }

    public function delete(string $id): void
    {
        $this->db->ExecutePrepared("delete from " . $this->table . " where id = ?", [$id]);
    }

    /**
     * Reduce submitted form input to a stored definition, keeping only what the
     * live schema confirms.
     *
     * Every table and column is checked against SchemaInspector, so a name that
     * was not offered — because it was typed, replayed, or forged — does not
     * survive into storage. The join type is looked up in an enum rather than
     * taken as text: `JoinType::from()` on an unknown value throws, which is the
     * behaviour wanted. There is no branch anywhere here that accepts a
     * fragment of SQL.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function normalise(array $input): array
    {
        $baseTable = trim((string)($input['baseTable'] ?? ''));
        if ($reason = $this->schema->reject($baseTable)) {
            throw new CatalogException($reason);
        }

        $joins     = [];
        $available = [$baseTable];
        foreach ((array)($input['joins'] ?? []) as $join) {
            $table = trim((string)($join['table'] ?? ''));
            if ($table === '') continue;
            if ($reason = $this->schema->reject($table)) {
                throw new CatalogException($reason);
            }

            $conditions = [];
            foreach ((array)($join['on'] ?? []) as $condition) {
                $leftTable   = trim((string)($condition['leftTable']   ?? ''));
                $leftColumn  = trim((string)($condition['leftColumn']  ?? ''));
                $rightColumn = trim((string)($condition['rightColumn'] ?? ''));
                if ($leftTable === '' || $leftColumn === '' || $rightColumn === '') continue;

                // The left side must be a table already in the dataset. Without
                // this a join can name any table at all as its left-hand side
                // and quietly widen the FROM clause — the structured equivalent
                // of the free-form condition box this design removed.
                if (!in_array($leftTable, $available, true)) {
                    throw new CatalogException(
                        "Join onto '$table' refers to '$leftTable', which is not part of this dataset yet."
                        . " Add it first, or join onto a table above it.");
                }
                if (!$this->schema->hasColumn($leftTable, $leftColumn)) {
                    throw new CatalogException("'$leftTable' has no column '$leftColumn'.");
                }
                if (!$this->schema->hasColumn($table, $rightColumn)) {
                    throw new CatalogException("'$table' has no column '$rightColumn'.");
                }
                $conditions[] = [
                    'leftTable'   => $leftTable,
                    'leftColumn'  => $leftColumn,
                    'rightTable'  => $table,
                    'rightColumn' => $rightColumn,
                ];
            }
            if (!$conditions) {
                throw new CatalogException("Join onto '$table' has no conditions, so it cannot be built.");
            }

            $joins[] = [
                'table' => $table,
                'type'  => JoinType::from((string)($join['type'] ?? JoinType::Inner->value))->value,
                'on'    => $conditions,
            ];
            $available[] = $table;
        }

        $fields = [];
        foreach ((array)($input['fields'] ?? []) as $field) {
            $table  = trim((string)($field['table']  ?? ''));
            $column = trim((string)($field['column'] ?? ''));
            if ($table === '' || $column === '') continue;
            if (!in_array($table, $available, true)) {
                throw new CatalogException("Field on '$table' refers to a table this dataset does not include.");
            }
            if (!$this->schema->hasColumn($table, $column)) {
                throw new CatalogException("'$table' has no column '$column'.");
            }

            $key = trim((string)($field['key'] ?? '')) ?: $table . '_' . $column;
            $fields[] = [
                'key'    => strtolower($key),
                'label'  => trim((string)($field['label'] ?? '')) ?: $column,
                'table'  => $table,
                'column' => $column,
                'type'   => $this->typeName($field['type'] ?? null, $table, $column),
            ];
        }
        if (!$fields) {
            throw new CatalogException('A dataset needs at least one field.');
        }

        $keys = array_column($fields, 'key');
        if (count($keys) !== count(array_unique($keys))) {
            throw new CatalogException('Two fields share the same name; each needs its own.');
        }

        return ['baseTable' => $baseTable, 'joins' => $joins, 'fields' => $fields];
    }

    /**
     * Build the Dataset. This is where the tier's limits are applied, because
     * the constructor applies them.
     *
     * @param array<string,mixed> $definition
     */
    private function hydrate(string $id, array $definition): Dataset
    {
        $joins = [];
        foreach ((array)($definition['joins'] ?? []) as $join) {
            $conditions = [];
            foreach ((array)($join['on'] ?? []) as $condition) {
                $conditions[] = new JoinCondition(
                    (string)$condition['leftTable'],  (string)$condition['leftColumn'],
                    (string)$condition['rightTable'], (string)$condition['rightColumn']);
            }
            $joins[] = new Join(
                table: (string)$join['table'],
                on:    $conditions,
                type:  JoinType::from((string)($join['type'] ?? JoinType::Inner->value)),
            );
        }

        $fields = [];
        foreach ((array)($definition['fields'] ?? []) as $field) {
            $fields[] = new FieldDefinition(
                key:    (string)$field['key'],
                label:  (string)($field['label'] ?? $field['key']),
                table:  (string)$field['table'],
                column: (string)$field['column'],
                type:   $this->valueType((string)($field['type'] ?? 'Text')),
            );
        }

        return new Dataset(
            id:          $id,
            label:       (string)($definition['label'] ?? $id),
            baseTable:   (string)($definition['baseTable'] ?? ''),
            fields:      $fields,
            joins:       $joins,
            tier:        DatasetTier::Custom,
            description: (string)($definition['description'] ?? ''),
        );
    }

    /** @param array<string,mixed> $definition */
    private function decode(string $json): array
    {
        $definition = json_decode($json, true);
        if (!is_array($definition)) {
            throw new CatalogException('its stored definition is not readable.');
        }
        return $definition;
    }

    private function typeName(mixed $requested, string $table, string $column): string
    {
        $name = is_string($requested) ? trim($requested) : '';
        foreach (ValueType::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) return $case->name;
        }
        // Not offered, or not chosen: take the column's own SQL type.
        return $this->schema->valueTypeOf($table, $column)->name;
    }

    private function valueType(string $name): ValueType
    {
        foreach (ValueType::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) return $case;
        }
        return ValueType::Text;
    }
}
