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

        foreach ($dataset->joins as $join) {
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

    /** Neutralise LIKE metacharacters inside a user's search term. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
