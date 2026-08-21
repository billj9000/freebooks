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
//  Path: /modules/forms/classes/app/record_selector.php
//
/**
 * Forms — which records a form is being asked to print.
 *
 * Every Print button in FreeBooks opens PhreeForm with the same four
 * parameters, and they are the contract this has to honour if Forms is to be
 * reachable from the documents that own the forms:
 *
 *     &xfld=journal_main.id&xcr=EQUAL&xmin=1234
 *     &xfld=journal_main.id&xcr=IN_LIST&xmin=12,13,14
 *     &xfld=journal_main.purchase_invoice_id&xcr=RANGE&xmin=1000&xmax=1099
 *
 * PhreeForm turned those straight into SQL and let the query do the selecting.
 * Forms' providers take a list of ids instead, so the translation happens here
 * — once, in one place, above the seam — and the providers stay ignorant of
 * where their ids came from.
 *
 * ── Why this is a query and not string manipulation ──────────────────────────
 * EQUAL on the record table's own key is just the value, and that is the common
 * case. But RANGE on `purchase_invoice_id` names a DIFFERENT column from the
 * one the provider breaks on, so the ids have to be looked up; the bulk-bills
 * page sends exactly that. Doing it here means the caller never has to know
 * which of the two it is holding.
 *
 * ── Why the column is checked against the schema ─────────────────────────────
 * `xfld` arrives from a URL. It reaches a FROM and a WHERE clause, so it is
 * checked against the columns the table actually has — the same Identifier
 * chokepoint the rest of the module uses — rather than escaped. A name the
 * schema does not have is refused, not quoted.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Catalog\Identifier;

/** A record selection, resolved to the ids a provider takes. */
final readonly class RecordSelection
{
    /**
     * @param list<int|string> $ids
     * @param list<string>     $notes why a selection came back empty, for the user
     */
    public function __construct(
        public array  $ids,
        public array  $notes = [],
        public string $description = '',
        /**
         * What each selected record is CALLED, for a person reading the print
         * page — "SO-10023, Acme Ltd" rather than "id = 993".
         *
         * Empty when the record table has no column anybody would recognise.
         * A caller must fall back to a count rather than to $description, which
         * is the filter expression and is developer-facing.
         *
         * @var list<string>
         */
        public array $labels = [],
    ) {}

    public function isEmpty(): bool { return $this->ids === []; }
}

final class RecordSelector
{
    /** Comparisons the Print buttons actually send. */
    private const EQUAL   = 'EQUAL';
    private const IN_LIST = 'IN_LIST';
    private const RANGE   = 'RANGE';

    /** How many records the print page will name before it stops listing. */
    private const LABEL_LIMIT = 5;

    public function __construct(private readonly \queryFactory $db) {}

    /**
     * Resolve the URL vocabulary into ids on $recordTable's primary key.
     *
     * $field may be qualified (`journal_main.id`) or bare (`id`); the table
     * part is discarded, because the record table is already known from the
     * form's plan and letting a URL choose the table would be an injection of a
     * different kind — printing an invoice from whatever table was named.
     *
     * @param string $recordTable unprefixed table name the ids belong to
     */
    public function resolve(
        string $recordTable,
        string $field,
        string $comparison,
        string $min,
        string $max = '',
        string $keyColumn = 'id'
    ): RecordSelection {
        if ($recordTable === '') {
            return new RecordSelection([], ['This form does not say which table its records come from.']);
        }

        // Drop any table qualifier: the table is not the URL's to choose.
        $column = str_contains($field, '.') ? substr($field, strrpos($field, '.') + 1) : $field;
        $column = trim($column);
        if ($column === '') $column = $keyColumn;

        $comparison = strtoupper(trim($comparison)) ?: self::EQUAL;

        // The fast path, and by far the commonest: the selector already names
        // the key the provider breaks on, so no query is needed at all.
        if ($column === $keyColumn) {
            $ids = match ($comparison) {
                self::IN_LIST => $this->splitList($min),
                self::RANGE   => [],           // a key range still needs the query below
                default       => ($min === '' ? [] : [$min]),
            };
            if ($comparison !== self::RANGE) {
                return new RecordSelection($ids, $ids === []
                    ? ['No record was named to print.'] : [],
                    $this->describe($column, $comparison, $min, $max),
                    $this->labelsFor($recordTable, $ids, $keyColumn));
            }
        }

        // Anything else is a lookup. Both names are checked against the schema
        // before either reaches the SQL.
        try {
            Identifier::check($recordTable, 'table name');
            Identifier::check($column,      'column name');
            Identifier::check($keyColumn,   'column name');
        } catch (\Throwable $e) {
            return new RecordSelection([], ['Refused to select records: ' . $e->getMessage()]);
        }

        $table = (defined('DB_PREFIX') ? DB_PREFIX : '') . $recordTable;
        if (!$this->hasColumn($table, $column)) {
            return new RecordSelection([], ["'$column' is not a column of $recordTable."]);
        }

        [$where, $ok] = $this->whereFor($column, $comparison, $min, $max);
        if (!$ok) {
            return new RecordSelection([], ["Cannot select records with comparison '$comparison'."]);
        }

        $ids    = [];
        $result = $this->db->Execute("select `$keyColumn` from `$table` where $where order by `$keyColumn`");
        while ($result && !$result->EOF) {
            $ids[] = $result->fields[$keyColumn];
            $result->MoveNext();
        }

        return new RecordSelection($ids, $ids === []
            ? ['Nothing matched the records requested.'] : [],
            $this->describe($column, $comparison, $min, $max),
            $this->labelsFor($recordTable, $ids, $keyColumn));
    }

    /**
     * The columns a person would recognise a record by, best first.
     *
     * Explicit rather than guessed. A heuristic ("any column called *_num")
     * finds nothing on journal_main — which has no such column — and would find
     * the wrong thing elsewhere; there are three record tables in the shipped
     * set and naming them costs less than being clever about it.
     *
     * The FIRST non-empty one wins, because a journal row carries the reference
     * for its own kind: a sales invoice fills purchase_invoice_id, a purchase
     * order fills purch_order_id, and a quote may carry only the customer's own
     * so_po_ref_id.
     */
    private const REFERENCE = [
        'journal_main'    => ['purchase_invoice_id', 'purch_order_id', 'so_po_ref_id'],
        'wo_journal_main' => ['id'],
        'contacts'        => ['short_name'],
    ];

    /** And who it is for, where the table says. */
    private const PARTY = [
        'journal_main'    => 'bill_primary_name',
        'wo_journal_main' => 'primary_name',
    ];

    /**
     * What the selected records are called.
     *
     * One query, capped: this is a label on a page, and reading four hundred
     * rows to print three of them would make a document slower to describe than
     * to render.
     *
     * @param list<string|int> $ids
     * @return list<string>
     */
    private function labelsFor(string $recordTable, array $ids, string $keyColumn): array
    {
        $columns = self::REFERENCE[$recordTable] ?? [];
        if ($columns === [] || $ids === []) return [];

        $table = (defined('DB_PREFIX') ? DB_PREFIX : '') . $recordTable;
        $party = self::PARTY[$recordTable] ?? '';
        $wanted = $columns;
        if ($party !== '') $wanted[] = $party;
        foreach ($wanted as $column) {
            if (!$this->hasColumn($table, $column)) return [];   // an older schema
        }

        $keys = array_map(fn($id) => "'" . $this->db->prepare_input((string)$id) . "'",
                          array_slice($ids, 0, self::LABEL_LIMIT));
        $select = implode(', ', array_map(fn($c) => "`$c`", array_unique($wanted)));
        $result = $this->db->Execute("select `$keyColumn`, $select from `$table`
            where `$keyColumn` in (" . implode(',', $keys) . ") order by `$keyColumn`");

        $labels = [];
        while ($result && !$result->EOF) {
            $reference = '';
            foreach ($columns as $column) {
                $value = trim((string)($result->fields[$column] ?? ''));
                if ($value !== '' && $value !== '0') { $reference = $value; break; }
            }
            $who = $party === '' ? '' : trim((string)($result->fields[$party] ?? ''));
            if ($reference === '' && $who === '') { $result->MoveNext(); continue; }
            $labels[] = $reference !== '' && $who !== '' ? "$reference — $who" : ($reference ?: $who);
            $result->MoveNext();
        }
        return $labels;
    }

    /**
     * @return array{0:string,1:bool} the WHERE fragment, and whether it is usable
     */
    private function whereFor(string $column, string $comparison, string $min, string $max): array
    {
        switch ($comparison) {
            case self::IN_LIST:
                $values = $this->splitList($min);
                if ($values === []) return ['', false];
                $quoted = array_map(fn($v) => "'" . $this->db->prepare_input($v) . "'", $values);
                return ["`$column` in (" . implode(',', $quoted) . ")", true];

            case self::RANGE:
                if ($min === '' && $max === '') return ['', false];
                $parts = [];
                if ($min !== '') $parts[] = "`$column` >= '" . $this->db->prepare_input($min) . "'";
                if ($max !== '') $parts[] = "`$column` <= '" . $this->db->prepare_input($max) . "'";
                return [implode(' and ', $parts), true];

            case self::EQUAL:
                if ($min === '') return ['', false];
                return ["`$column` = '" . $this->db->prepare_input($min) . "'", true];
        }
        return ['', false];
    }

    /** @return list<string> */
    private function splitList(string $value): array
    {
        $out = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part !== '') $out[] = $part;
        }
        return $out;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $result = $this->db->Execute("select column_name from information_schema.columns
            where table_schema = database()
              and table_name   = '" . $this->db->prepare_input($table) . "'
              and column_name  = '" . $this->db->prepare_input($column) . "'");
        return $result && !$result->EOF;
    }

    private function describe(string $column, string $comparison, string $min, string $max): string
    {
        return match ($comparison) {
            self::IN_LIST => "$column in ($min)",
            self::RANGE   => trim("$column from $min to $max"),
            default       => "$column = $min",
        };
    }
}
