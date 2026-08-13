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
     * Tables are introduced as `<prefix><table> AS <table>` and referenced by
     * the bare name everywhere else.
     *
     * FreeBooks supports a table-name prefix, set at install time and baked
     * into configure.php as DB_PREFIX; the shipped installs use an empty one,
     * which is why the catalog got away with emitting bare names. Prefixing
     * every REFERENCE instead — which is what PhreeForm's prefixTables() does,
     * by string-replacing "table." throughout — would mean rewriting field
     * references, join conditions, ORDER BY and the raw SQL inside every
     * computed expression, and getting any of those wrong fails only on an
     * install nobody here is running.
     *
     * Aliasing moves the whole problem to the two places a table is introduced.
     * A dataset then reads the same on every install, and an expression like
     * `(journal_item.credit_amount - journal_item.debit_amount)` needs no
     * rewriting at all. The one case that does is a subquery introducing a
     * FRESH instance of a table — see EXPRESSION_TABLE_PATTERN.
     *
     * @param ?string $tablePrefix null takes DB_PREFIX; pass explicitly to test
     *   a prefixed install from one that is not.
     */
    public function __construct(private readonly ?string $tablePrefix = null) {}

    /** The configured prefix, validated — it reaches the statement unquoted. */
    private function prefix(): string
    {
        $prefix = $this->tablePrefix ?? (defined('DB_PREFIX') ? (string)DB_PREFIX : '');
        if ($prefix !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new CatalogException("DB_PREFIX '$prefix' is not a bare identifier prefix.");
        }
        return $prefix;
    }

    /** `<prefix><table> AS <table>`, for a FROM or JOIN. */
    private function introduce(string $table): string
    {
        return $this->prefix() . $table . ' AS ' . $table;
    }

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

        // Parameters bind positionally, so they must be collected in the order
        // they appear in the finished SQL — SELECT, then WHERE, then ORDER BY.
        // That is the order the clauses are assembled below; keep it that way.
        $params = [];

        $select = [];
        foreach ($fields as $field) $select[] = $this->expand($field->toSelect(), $dataset, $params);

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . $this->introduce($dataset->baseTable);

        $needed = $this->requiredTables($dataset, $fields, $filters, $sorts);
        foreach ($dataset->joins as $join) {
            if (!isset($needed[$join->table])) continue;
            $on = [];
            foreach ($join->on as $condition) $on[] = $condition->toSql();
            $sql .= ' ' . $join->type->value . ' ' . $this->introduce($join->table)
                  . ' ON ' . implode(' AND ', $on);
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
                $order[] = $this->expand($field->toReference(), $dataset, $params) . ' ' . $sort->direction->value;
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

        $ref = $this->expand($field->toReference(), $dataset, $params);

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
                $sql .= ' ' . $this->expand($other->toReference(), $dataset, $params);
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

    /**
     * `{{table}}` inside a computed expression, for a FRESH table instance.
     *
     * Aliasing handles every reference to a table the dataset already joins,
     * because the alias IS the bare name. It cannot handle a subquery that
     * opens the table again — `(SELECT avg_cost FROM inventory_history h_avg
     * WHERE …)` names a second, independent instance, and that one needs the
     * real prefixed name. A dataset writes it as `FROM {{inventory_history}}
     * h_avg`.
     *
     * The braces are deliberately unlike anything SQL uses, so a missing
     * substitution is a syntax error rather than a query against the wrong
     * table. Only shipped datasets carry expressions at all (§3.2), so this is
     * developer-authored text — but the name is still checked, since it is the
     * one part of an expression that is rewritten rather than passed through.
     */
    private const EXPRESSION_TABLE_PATTERN = '/\{\{(param:)?([A-Za-z_][A-Za-z0-9_]*)\}\}/';

    /**
     * `{{param:name}}` resolves to a `?`, and the dataset's value for that name
     * is appended to $params.
     *
     * This is the one thing a computed expression cannot express for itself: a
     * value that is not known when the dataset is written. The aging buckets are
     * today's date less an admin setting, so they move with the clock and with
     * configuration. Substituting the date into the SQL text would work and
     * would be safe — nothing user-supplied is involved — but it would put a
     * literal where this file promises only identifiers, keywords and `?` ever
     * go, and that promise is easier to keep than to qualify.
     *
     * @param array<int,scalar|null> $params appended to, in SQL-text order
     */
    private function expand(string $sql, ?Dataset $dataset = null, array &$params = []): string
    {
        if (!str_contains($sql, '{{')) return $sql;
        $prefix = $this->prefix();
        $out = preg_replace_callback(
            self::EXPRESSION_TABLE_PATTERN,
            function (array $m) use ($prefix, $dataset, &$params): string {
                if (($m[1] ?? '') === '') {
                    Identifier::check($m[2], 'table name');
                    return $prefix . $m[2];
                }
                Identifier::check($m[2], 'expression parameter name');
                if ($dataset === null || !array_key_exists($m[2], $dataset->params)) {
                    throw new CatalogException(
                        "Expression references parameter '{$m[2]}', which the dataset does not supply.");
                }
                $params[] = $dataset->params[$m[2]];
                return '?';
            },
            $sql,
        );
        if ($out === null || str_contains($out, '{{')) {
            throw new CatalogException("Expression has an unresolved table placeholder: $sql");
        }
        return $out;
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
