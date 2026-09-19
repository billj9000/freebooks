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
     * The records the canvas may draw its page from, newest first (§13.53).
     *
     * The editor's question is not the print page's. A Print button arrives
     * naming exactly one record; an author laying out a form has no record in
     * mind and needs to be OFFERED some — and, once conditional content exists,
     * needs a particular one, because a euro bank block cannot be judged
     * against a sterling invoice.
     *
     * Whether any of these actually builds a document is settled by trying: the
     * form's own filters are what make a Purchase Order a purchase order, so a
     * list drawn from the record table alone will include rows this form
     * declines. That is the caller's business and it is cheap to discover.
     *
     * A row nothing can name keeps its own key as its label rather than being
     * dropped. Dropping it would read better and would be a REGRESSION: this
     * list is also the list of records the canvas tries, and a record table
     * with no reference columns — a custom dataset's, say — would go from
     * showing real data to showing invented data, silently.
     *
     * @return array<string,string> record key => what to call it
     */
    public function recent(string $recordTable, string $keyColumn, int $limit = 20,
                           string $group = ''): array
    {
        return $this->withLabels($recordTable, $keyColumn,
            $this->keysOf($recordTable, $keyColumn, '', $limit, $group), $limit);
    }

    /**
     * The records a person's own reference names — an invoice number, an order
     * number, whatever that table's REFERENCE columns hold (§13.53).
     *
     * A separate question from resolve(), which selects on a column the CALLER
     * names. Here nobody knows the column: an author types "20055" and means
     * whichever field their company's invoices are numbered in. So every
     * reference column for the table is tried, in the order that decides what a
     * record is called, and the first that matches wins — the same precedence
     * labelMap() uses, so a record found by its number is labelled by the same
     * number rather than by a different one.
     *
     * Matched as a PREFIX, because a reference is typed from memory and the
     * stored value may carry padding or a suffix an author does not think of as
     * part of it. Exact matches sort first all the same.
     *
     * @return array<string,string> record key => what to call it
     */
    public function findByReference(string $recordTable, string $keyColumn, string $text,
                                    int $limit = 20, string $group = ''): array
    {
        $text    = trim($text);
        $columns = self::REFERENCE[$recordTable] ?? [];
        if ($text === '' || $columns === []) return [];

        try {
            Identifier::check($recordTable, 'table name');
            Identifier::check($keyColumn,   'column name');
        } catch (\Throwable $e) {
            return [];
        }
        $table = (defined('DB_PREFIX') ? DB_PREFIX : '') . $recordTable;

        // The value is escaped and the COLUMNS come from the constant above,
        // never from the caller — which is the §3.3 rule in miniature: an
        // author supplies a value, never an identifier.
        $like  = $this->db->prepare_input($text);
        $where = [];
        foreach ($columns as $column) {
            if (!$this->hasColumn($table, $column)) continue;
            $where[] = "`$column` like '" . $like . "%'";
        }
        if ($where === []) return [];

        // The journal restriction applies to a typed reference too. Searching
        // for "1234" while laying out an invoice must not answer with the
        // purchase order that happens to carry that number.
        $only = $this->journalClause($table, $group);

        $ids    = [];
        $result = $this->db->Execute("select `$keyColumn` from `$table`
            where (" . implode(' or ', $where) . ") $only
            order by `$keyColumn` desc limit " . (int)$limit);
        while ($result && !$result->EOF) {
            $ids[] = (string)$result->fields[$keyColumn];
            $result->MoveNext();
        }
        return $this->withLabels($recordTable, $keyColumn, $ids, $limit);
    }

    /**
     * Ids in the order given, each against what it is called — or against
     * itself where nothing can name it.
     *
     * @param list<string> $ids
     * @return array<string,string>
     */
    private function withLabels(string $recordTable, string $keyColumn, array $ids, int $limit): array
    {
        if ($ids === []) return [];
        $named = $this->labelMap($recordTable, $ids, $keyColumn, $limit);

        $out = [];
        foreach ($ids as $id) $out[$id] = $named[$id] ?? $id;
        return $out;
    }

    /**
     * What one record is called, or null if the table has no such record.
     *
     * The membership test the canvas needs before it will draw a record an
     * author named: one query for one key, rather than reading a page of recent
     * ones and looking in it — which answers a different question and answers
     * it wrongly for exactly the record worth asking about, an old one.
     *
     * Null means "not a record of this table". It never means "not labelled":
     * an existing row nothing can name comes back as its own key, so a caller
     * can tell the two apart.
     */
    public function label(string $recordTable, string $keyColumn, string $id,
                          string $group = ''): ?string
    {
        // Not observable, and kept anyway: keysOf() with no value asks for every
        // recent key, and the lookup below would still answer null. What the
        // guard saves is that query — on a page that redraws as fast as an
        // author can drag.
        $id = trim($id);
        if ($id === '') return null;

        $found = $this->withLabels($recordTable, $keyColumn,
            $this->keysOf($recordTable, $keyColumn, $id, 1, $group), 1);
        return $found[$id] ?? null;
    }

    /**
     * Distinct key values of the record table, newest first — all of them, or
     * the one $only names.
     *
     * The filter is a VALUE, never a fragment. A caller handing in ready-made
     * SQL would be building it before this function has decided the column name
     * is safe, which is an ordering nobody would notice was wrong; the clause is
     * assembled here, after the checks, or not at all.
     *
     * The schema check is not decoration either. `recordKey` comes from a
     * form's plan, which comes from a stored definition, and a definition
     * naming a column the table has lost — an older schema, a hand-edited file
     * — would put that name straight into a FROM/WHERE. Refused rather than
     * quoted, as everywhere else in this class.
     *
     * @return list<string>
     */
    private function keysOf(string $recordTable, string $keyColumn, string $only, int $limit,
                            string $group = ''): array
    {
        if ($recordTable === '') return [];
        try {
            Identifier::check($recordTable, 'table name');
            Identifier::check($keyColumn,   'column name');
        } catch (\Throwable $e) {
            return [];
        }
        $table = (defined('DB_PREFIX') ? DB_PREFIX : '') . $recordTable;
        if (!$this->hasColumn($table, $keyColumn)) return [];

        $where = $only === '' ? '1 = 1'
            : "`$keyColumn` = '" . $this->db->prepare_input($only) . "'";
        $where .= $this->journalClause($table, $group);

        $ids    = [];
        $result = $this->db->Execute("select distinct `$keyColumn` from `$table`
            where $where
            order by `$keyColumn` desc limit " . (int)$limit);
        while ($result && !$result->EOF) {
            $value = (string)$result->fields[$keyColumn];
            if ($value !== '') $ids[] = $value;
            $result->MoveNext();
        }
        return $ids;
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

    /**
     * Which journals a document group covers — the restriction that makes the
     * canvas's record list a list of the right KIND of record (§13.54).
     *
     * `journal_main` holds every transaction there is. A form is made a Sales
     * Invoice by the journal its records sit in, and nothing in the definition
     * says so: a provider-backed form carries no filters at all, and its plan
     * names only the table. So the list offered while laying out an invoice was
     * every recent transaction — measured on a real company, six of the newest
     * twenty were invoices and the rest were receipts, sales orders and a
     * purchase order.
     *
     * Taken from `phreebooks/pages/status/pre_process.php`, which is where the
     * application itself decides which form type a journal prints through. That
     * is a `switch`, not a table, so this is a copy of it and copies go stale —
     * `verify_forms_print_buttons.php` holds the two together by harvesting
     * every POPUP_FORM_TYPE the source defines and requiring each to be
     * accounted for here.
     *
     * A group this does not know is NOT filtered. That is the safe direction:
     * an unknown group offers what it always did rather than offering nothing,
     * which is what a wrong guess here would look like — a picker that has gone
     * empty for a form nobody can see the reason for.
     */
    private const GROUP_JOURNALS = [
        'vend:quot' => [3],
        'vend:po'   => [4],
        'vend:cm'   => [7],
        'cust:quot' => [9],
        'cust:so'   => [10],
        'cust:inv'  => [12],
        'cust:cm'   => [13],
        'bnk:rcpt'  => [18],
        'bnk:chk'   => [20],
    ];

    /**
     * Groups the map deliberately does not restrict, and why — so that a check
     * comparing it against the application's own list can tell "not yet
     * thought about" from "thought about and left alone".
     */
    public const UNRESTRICTED = [
        // Not a journal table: a statement is per CONTACT, and contacts has no
        // journal_id to restrict by.
        'cust:stmt' => 'statements are selected by contact, not by journal',
        'vend:stmt' => 'statements are selected by contact, not by journal',
        // Its own table, with one kind of record in it.
        'inv:wo'    => 'work orders have a table to themselves',
        // A receipt has no page layout, so the canvas redirects it to the
        // receipt editor before any of this runs and there is no picker to
        // restrict. Left out rather than guessed at.
        'pos:rcpt'  => 'a receipt is edited elsewhere and has no record picker',
        // No Print button anywhere defines this one, so there is no authoritative
        // journal to copy and guessing would be the wrong kind of confident.
        'bnk:deps'  => 'no Print button names it, so nothing says which journal',
        // The application defines no form type for these two journals at all.
        ''          => 'no form type',
    ];

    /**
     * The document groups whose records are restricted by journal.
     *
     * The names only — the journal ids stay private, because nothing outside
     * this class has any business asserting which journal an invoice is. It
     * exists so a check can hold this list against the application's own
     * (§13.54), which is the whole defence against the copy going stale.
     *
     * @return list<string>
     */
    public static function restrictedGroups(): array
    {
        return array_keys(self::GROUP_JOURNALS);
    }

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
        return array_values($this->labelMap($recordTable, $ids, $keyColumn, self::LABEL_LIMIT));
    }

    /**
     * The same labels, still keyed by the record they belong to.
     *
     * Split out of labelsFor() rather than written a second time, because the
     * canvas's record picker (§13.53) needs to put a label against an id and
     * the print page needs a list of labels — and two implementations of "what
     * is this record called" would describe the same invoice two ways on two
     * pages of the same module.
     *
     * A row with neither a reference nor a party is skipped, not given a blank
     * entry: the caller wants records a person can recognise, and an entry
     * reading only its own internal id is one nobody can choose between.
     *
     * @param list<string|int> $ids
     * @return array<string,string> record key => what to call it
     */
    private function labelMap(string $recordTable, array $ids, string $keyColumn, int $limit): array
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
                          array_slice($ids, 0, $limit));
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
            $labels[(string)$result->fields[$keyColumn]] =
                $reference !== '' && $who !== '' ? "$reference — $who" : ($reference ?: $who);
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

    /**
     * The journal restriction for a document group, or nothing at all.
     *
     * Nothing in three cases, and each is deliberate: a group this does not
     * know, a table with no `journal_id` to restrict by, and no group given —
     * the print page calls every method here without one, and must keep seeing
     * what it always saw.
     *
     * The ids come from a constant and are cast, so nothing an author or a URL
     * supplies reaches this clause.
     */
    private function journalClause(string $table, string $group): string
    {
        $journals = self::GROUP_JOURNALS[trim($group)] ?? [];
        if ($journals === [] || !$this->hasColumn($table, 'journal_id')) return '';

        return ' and `journal_id` in (' . implode(',', array_map('intval', $journals)) . ')';
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
