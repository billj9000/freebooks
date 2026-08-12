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
//  Path: /modules/forms/classes/catalog/query_builder.php
//
/**
 * Forms — assembles a dataset, fields, filters and sorts into a SELECT.
 *
 * The entire safety argument of the catalog rests on this file, so it is worth
 * being explicit about what it may emit. Only four things ever reach the SQL
 * string:
 *
 *   1. identifiers that passed Identifier::check() when their definition was
 *      constructed (table names, column names, field keys);
 *   2. expressions from SHIPPED datasets, which are developer-authored PHP and
 *      therefore no more privileged than the rest of the codebase;
 *   3. fixed keywords chosen by enum (JOIN type, comparison operator, sort
 *      direction);
 *   4. `?` placeholders.
 *
 * No caller-supplied value is ever concatenated. Filter values, date bounds and
 * record ids all leave as bound parameters, which is what makes the difference
 * between this and PhreeForm's build_criteria().
 *
 * The result is a Query — SQL plus its parameters — rather than a string, so a
 * caller cannot accidentally execute the one without the other.
 */

namespace FreeBooks\Forms\Catalog;

/** A statement and the parameters it must be executed with. */
final readonly class Query
{
    /** @param list<scalar|null> $params */
    public function __construct(
        public string $sql,
        public array  $params = [],
    ) {}
}

final class QueryBuilder
{
    /**
     * @param list<string> $fieldKeys fields to select; empty selects them all
     * @param list<Filter> $filters
     * @param list<Sort>   $sorts
     */
    public function build(
        Dataset $dataset,
        array   $fieldKeys = [],
        array   $filters   = [],
        array   $sorts     = [],
        ?int    $limit     = null,
    ): Query {
        $fields = $fieldKeys === []
            ? array_values($dataset->fields)
            : $dataset->fieldsFor($fieldKeys);

        $select = [];
        foreach ($fields as $field) $select[] = $field->toSelect();

        $sql    = 'SELECT ' . implode(', ', $select) . ' FROM ' . $dataset->baseTable;
        $params = [];

        $needed = $this->requiredTables($dataset, $fields, $filters, $sorts);
        foreach ($dataset->joins as $join) {
            if (!isset($needed[$join->table])) continue;
            $on = [];
            foreach ($join->on as $condition) $on[] = $condition->toSql();
            $sql .= ' ' . $join->type->value . ' ' . $join->table . ' ON ' . implode(' AND ', $on);
        }

        $where = [];
        foreach ($filters as $filter) {
            $where[] = $this->condition($dataset, $filter, $params);
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

        if ($sorts) {
            $order = [];
            foreach ($sorts as $sort) {
                $field = $dataset->field($sort->fieldKey);
                if ($field === null) {
                    throw new CatalogException("Cannot sort by unknown field '{$sort->fieldKey}'.");
                }
                if (!$field->sortable) {
                    throw new CatalogException("Field '{$sort->fieldKey}' is not sortable.");
                }
                $order[] = $field->toReference() . ' ' . $sort->direction->value;
            }
            $sql .= ' ORDER BY ' . implode(', ', $order);
        }

        // Cast, never interpolate a caller's string: LIMIT takes no placeholder
        // in MySQL's text protocol, so it is the one value that must be proven
        // safe by type rather than by binding.
        if ($limit !== null) {
            if ($limit < 1) throw new CatalogException('LIMIT must be a positive integer.');
            $sql .= ' LIMIT ' . (int)$limit;
        }

        return new Query($sql, $params);
    }

    /**
     * Which of a dataset's joins this query actually needs.
     *
     * A dataset is a curated view over a join graph, so it is deliberately wider
     * than any one report — Transactions reaches contacts and address_book
     * because some documents need them. Joining all of it unconditionally is not
     * merely wasteful, it changes the ANSWER: address_book is one-to-many, so a
     * report over journal_main + journal_item alone returns twice as many rows
     * once an unused address_book join is bolted on. PhreeForm avoided this by
     * making each report name its own tables; pruning is how a shared dataset
     * gets the same result.
     *
     * Pruning is decided by REFERENCE, not by join type. Keeping every INNER
     * join looks like the cautious choice — an INNER join can exclude rows, so
     * removing one changes which records appear — but on real reports it is the
     * more damaging one. `transactions` joins journal_item INNER because most
     * documents are line-grain; Open Purchase Orders selects only header
     * columns, and carrying the unreferenced line join turned its 55 orders
     * into 955 rows, one per line. Legacy had no such problem because each
     * report named its own tables, so a table it did not use was simply absent.
     *
     * An unreferenced join contributes exactly two things: row multiplication
     * when it is one-to-many, and silent exclusion of parents with no children.
     * A report that names no column from that table asked for neither. Where
     * the join genuinely is part of the dataset's meaning, it says so with
     * `required: true` and is kept regardless.
     *
     * Tables are kept transitively: dropping a link in the middle of a chain
     * would leave the far end unreachable.
     *
     * @param list<FieldDefinition> $fields
     * @param list<Filter>          $filters
     * @param list<Sort>            $sorts
     * @return array<string,true>   tables that must be joined
     */
    private function requiredTables(Dataset $dataset, array $fields, array $filters, array $sorts): array
    {
        $needed = [$dataset->baseTable => true];

        $note = function (?FieldDefinition $field) use (&$needed, $dataset): void {
            if ($field === null) return;
            if ($field->table !== null) $needed[$field->table] = true;
            // A computed field's expression may reference tables its own `table`
            // does not name, so every known table it mentions is required.
            if ($field->expression !== null) {
                foreach ($dataset->tables() as $table) {
                    if (preg_match('/\b' . preg_quote($table, '/') . '\./', $field->expression)) {
                        $needed[$table] = true;
                    }
                }
            }
        };

        foreach ($fields as $field)   $note($field);
        foreach ($sorts as $sort)     $note($dataset->field($sort->fieldKey));
        foreach ($filters as $filter) {
            $note($dataset->field($filter->fieldKey));
            // A field expression references fields too, and a join pruned out
            // from under one produces "Unknown column" rather than a wrong
            // answer — but only at run time, on whichever report uses it.
            if ($filter->expression !== null) {
                foreach ($filter->expression->fieldKeys() as $key) $note($dataset->field($key));
            }
        }

        // Joins the dataset declares as load-bearing stay regardless.
        foreach ($dataset->joins as $join) {
            if ($join->required) $needed[$join->table] = true;
        }

        // Keep whatever is needed to reach a required table, walking the
        // declared join order backwards so chains resolve in one pass.
        foreach (array_reverse($dataset->joins) as $join) {
            if (!isset($needed[$join->table])) continue;
            foreach ($join->on as $condition) {
                $needed[$condition->leftTable]  = true;
                $needed[$condition->rightTable] = true;
            }
        }

        return $needed;
    }

    /**
     * Render one filter, appending its values to $params.
     *
     * @param list<scalar|null> $params
     */
    private function condition(Dataset $dataset, Filter $filter, array &$params): string
    {
        $field = $dataset->field($filter->fieldKey);
        if ($field === null) {
            throw new CatalogException("Cannot filter on unknown field '{$filter->fieldKey}'.");
        }
        if (!$field->filterable) {
            throw new CatalogException("Field '{$filter->fieldKey}' is not filterable.");
        }

        $ref = $field->toReference();

        // A comparison against other fields emits no parameter at all: every
        // token is either a key resolved through the dataset or one of six
        // fixed symbols, so nothing a caller wrote reaches the statement.
        if ($filter->expression !== null) {
            $sql = '';
            foreach ($filter->expression->tokens as $token) {
                if ($token['kind'] === 'op') { $sql .= ' ' . $token['text']; continue; }
                $other = $dataset->field($token['text']);
                if ($other === null) {
                    throw new CatalogException(
                        "Filter on '{$filter->fieldKey}' compares against unknown field '{$token['text']}'.");
                }
                if (!$other->filterable) {
                    throw new CatalogException("Field '{$token['text']}' is not filterable.");
                }
                $sql .= ' ' . $other->toReference();
            }
            return "$ref " . $this->comparison($filter->operator) . ' (' . trim($sql) . ')';
        }

        switch ($filter->operator) {
            case FilterOperator::Equal:        $op = '=';  break;
            case FilterOperator::NotEqual:     $op = '<>'; break;
            case FilterOperator::GreaterThan:  $op = '>';  break;
            case FilterOperator::GreaterEqual: $op = '>='; break;
            case FilterOperator::LessThan:     $op = '<';  break;
            case FilterOperator::LessEqual:    $op = '<='; break;

            case FilterOperator::Between:
                $params[] = $filter->values[0];
                $params[] = $filter->values[1];
                return "$ref BETWEEN ? AND ?";

            case FilterOperator::In:
                foreach ($filter->values as $value) $params[] = $value;
                return "$ref IN (" . implode(', ', array_fill(0, count($filter->values), '?')) . ')';

            case FilterOperator::Contains:
                // The wildcards are ours; the value stays a parameter, so a
                // value containing % or _ widens only its own match.
                $params[] = '%' . $this->escapeLike((string)$filter->values[0]) . '%';
                return "$ref LIKE ?";

            case FilterOperator::StartsWith:
                $params[] = $this->escapeLike((string)$filter->values[0]) . '%';
                return "$ref LIKE ?";

            case FilterOperator::IsEmpty:
                return "($ref IS NULL OR $ref = '')";

            case FilterOperator::IsNotEmpty:
                return "($ref IS NOT NULL AND $ref <> '')";
        }

        $params[] = $filter->values[0];
        return "$ref $op ?";
    }

    /** The SQL symbol for a scalar comparison. */
    private function comparison(FilterOperator $operator): string
    {
        return match ($operator) {
            FilterOperator::Equal        => '=',
            FilterOperator::NotEqual     => '<>',
            FilterOperator::GreaterThan  => '>',
            FilterOperator::GreaterEqual => '>=',
            FilterOperator::LessThan     => '<',
            FilterOperator::LessEqual    => '<=',
            default => throw new CatalogException("Operator '{$operator->value}' is not a scalar comparison."),
        };
    }

    /** Neutralise LIKE metacharacters inside a user's search term. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
