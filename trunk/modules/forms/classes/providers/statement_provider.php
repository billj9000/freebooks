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
//  Path: /modules/forms/classes/providers/statement_provider.php
//
/**
 * Forms — customer / vendor statement provider.
 *
 * Port of phreebooks/classes/statement_builder.php, the special_class behind
 * CustomerStatement and VendorStatement. See docs/forms-design.md §5 and §7.
 *
 * A statement is per CONTACT, not per journal entry: build() takes contact ids,
 * which is what PhreeForm passed as the form break value. Everything else is a
 * faithful transcription — aging buckets, the prior-balance carry-forward, the
 * credit-memo and discount sign flips and the vendor inversion all put money on
 * a customer-facing document, so behaviour is held constant and proven by
 * diffing against a PhreeForm baseline (docs/tools/diff_forms_provider.php).
 *
 * Two things change shape:
 *
 *   - The reporting period arrives as a DateRange rather than being read from
 *     the report definition through gen_build_sql_date(). The legacy passed the
 *     '0000-00-00' sentinel into the prior-balance cutoff when the range was
 *     open; an open range here simply skips that query, which is the same
 *     answer (no row predates the beginning of time) without the sentinel.
 *   - Values keep their raw type, so the renderer decides whether a balance
 *     prints as £1,234.50 or 1234.50.
 *
 * A provider sits ABOVE the seam, so database access here is correct and
 * expected; it is the returned Document that must be free of it.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Catalog\DateRange;
use FreeBooks\Forms\Document\{
    Context, Currency, Document, DocumentProvider,
    LayoutDocument, PageSetup, Record, Template, TypedValue
};

final class StatementDocumentProvider implements DocumentProvider
{
    /** Journals that make up a statement: bills, vendor credits, invoices, customer credits. */
    private const JOURNALS_CHARGE  = [6, 7, 12, 13];
    /** Payment journals, which reduce a partially-paid prior balance. */
    private const JOURNALS_PAYMENT = [18, 20];
    /** Charges and payments together — the statement body. */
    private const JOURNALS_BODY    = [6, 7, 12, 13, 18, 20];
    /** Credit memos: their debit and credit swap places. */
    private const JOURNALS_CREDIT  = [7, 13];

    public function __construct(
        private readonly \queryFactory $db,
        private readonly Template      $template,
        private readonly DateRange     $range,
        private readonly Context       $context = new Context(),
        private readonly string        $title = 'Statement',
        private readonly PageSetup     $page = new PageSetup(),
        /**
         * Column the date range applies to, unqualified. The definitions ship
         * 'post_date'; it is qualified to the journal_main alias here because
         * both joined tables carry one.
         */
        private readonly string        $dateField = 'post_date',
    ) {}

    /** @param array{record_ids?:list<int|string>} $params contact ids */
    public function build(array $params): Document
    {
        $records = [];
        foreach ($params['record_ids'] ?? [] as $id) {
            $record = $this->buildRecord((int)$id);
            if ($record !== null) $records[] = $record;
        }
        return new LayoutDocument(
            title: $this->title,
            template: $this->template,
            records: $records,
            context: $this->context,
            page: $this->page,
        );
    }

    /** One statement. Mirrors statement_builder::load_query_results(). */
    private function buildRecord(int $contactId): ?Record
    {
        if (!$contactId) return null;

        // The main ('%m') address is the one a statement is sent to.
        $row = $this->db->Execute("select c.id, c.type, c.short_name, c.special_terms,
            a.primary_name, a.contact, a.address1, a.address2, a.city_town, a.state_province,
            a.postal_code, a.country_code, a.telephone1, a.telephone2, a.telephone3,
            a.telephone4, a.email, a.website
          from " . TABLE_CONTACTS . " c inner join " . TABLE_ADDRESS_BOOK . " a on c.id = a.ref_id
          where c.id = " . $contactId . " and a.type like '%m'")->fields;
        if (!$row) return null;

        $f = [];
        foreach ($row as $k => $v) {
            $f[$k] = function_exists('db_prepare_input') ? db_prepare_input($v) : $v;
        }

        $f += $this->loadAging($contactId, (string)($row['type'] ?? ''), (string)($row['special_terms'] ?? ''));

        $priorBalance = $this->loadPriorBalance($contactId);
        $lines        = $this->loadLines($contactId, (string)($row['special_terms'] ?? ''));

        $statementTotal = 0.0;
        foreach ($lines as $line) {
            if (!$line['_ref_only']) $statementTotal += $line['_net'];
        }

        $balanceDue = $priorBalance + $statementTotal;
        // Vendor balances are held with the opposite sign to customer balances;
        // a statement shows what is owed, so both read positive.
        if (($row['type'] ?? '') === 'v') {
            $priorBalance = -$priorBalance;
            $balanceDue   = -$balanceDue;
        }

        $f['prior_balance']   = $priorBalance;
        $f['statement_total'] = $statementTotal;
        $f['balance_due']     = $balanceDue;
        // The statement's own date is the day it is produced, not a stored one.
        // It stays a date so the renderer applies the locale.
        $f['post_date']       = date('Y-m-d');

        $rows = [];
        foreach ($lines as $line) {
            unset($line['_ref_only'], $line['_net']);
            $rows[] = $this->typeLine($line);
        }

        return new Record(
            values: $this->typeValues($f),
            tables: ['items' => $rows],
            currency: null,   // statements aggregate journals; there is no single posted currency
            key: (string)$contactId,
        );
    }

    /** Aging buckets, straight from the phreebooks helper the legacy used. */
    private function loadAging(int $contactId, string $type, string $terms): array
    {
        $out = ['balance_0' => 0, 'balance_30' => 0, 'balance_60' => 0, 'balance_90' => 0];
        if (!function_exists('calculate_aging')) return $out;
        $aging = calculate_aging($contactId, $type, $terms);
        foreach (array_keys($out) as $k) $out[$k] = $aging[$k] ?? 0;
        return $out;
    }

    /**
     * Everything owed before the statement period opened.
     *
     * Two passes, as the legacy did: unclosed (or later-closed) charges predating
     * the period, then whatever was already paid against them. An open-ended
     * range has no "before", so both are skipped.
     */
    private function loadPriorBalance(int $contactId): float
    {
        if ($this->range->start === null) return 0.0;
        $start = $this->db->prepare_input($this->range->start);

        $balance = 0.0;
        $ids     = [];
        $res = $this->db->Execute("select m.id, (i.debit_amount - i.credit_amount) as balance
            from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id
            where m.bill_acct_id = " . $contactId . "
            and m.post_date < '" . $start . "'
            and (m.closed_date >= '" . $start . "' or m.closed = '0')
            and m.journal_id in (" . implode(',', self::JOURNALS_CHARGE) . ")
            and i.gl_type in ('ttl', 'dsc')");
        while (!$res->EOF) {
            $balance += (float)$res->fields['balance'];
            $ids[]    = (int)$res->fields['id'];
            $res->MoveNext();
        }
        if (!$ids) return $balance;

        $paid = $this->db->Execute("select sum(i.debit_amount - i.credit_amount) as payment
            from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id
            where i.so_po_item_ref_id in (" . implode(',', $ids) . ")
            and m.post_date < '" . $start . "'
            and m.journal_id in (" . implode(',', self::JOURNALS_PAYMENT) . ")
            and i.gl_type = 'pmt'")->fields;
        return $balance + (float)($paid['payment'] ?? 0);
    }

    /**
     * The statement body: one line per journal total or discount in the period.
     *
     * Each line carries two private keys the caller strips — '_ref_only' and
     * '_net' — so the running total is computed from the same rows the reader
     * sees rather than from a second pass over the database.
     *
     * @return list<array<string,mixed>>
     */
    private function loadLines(int $contactId, string $terms): array
    {
        $sql = "select m.post_date, m.journal_id, m.terms, i.debit_amount, i.credit_amount,
            m.purchase_invoice_id, m.purch_order_id, i.gl_type
          from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id
          where m.bill_acct_id = " . $contactId;
        if ($this->range->start !== null) {
            $sql .= " and m." . $this->dateField . " >= '" . $this->db->prepare_input($this->range->start) . "'";
        }
        if ($this->range->endExclusive !== null) {
            $sql .= " and m." . $this->dateField . " < '" . $this->db->prepare_input($this->range->endExclusive) . "'";
        }
        $sql .= " and m.journal_id in (" . implode(',', self::JOURNALS_BODY) . ")
            and i.gl_type in ('ttl', 'dsc') order by m.post_date";

        $lines = [];
        $res   = $this->db->Execute($sql);
        while (!$res->EOF) {
            $r        = $res->fields;
            $journal  = (int)$r['journal_id'];
            $isCharge = in_array($journal, self::JOURNALS_CHARGE, true);
            $isDsc    = $r['gl_type'] === 'dsc';

            // A charge posts to the customer's account the opposite way round
            // from a payment, so the two columns swap before display.
            $credit = $isCharge ? (float)$r['debit_amount']  : (float)$r['credit_amount'];
            $debit  = $isCharge ? (float)$r['credit_amount'] : (float)$r['debit_amount'];

            // An order discount on a charge is shown for information only: it is
            // already inside the invoice total, so counting it again would
            // double-discount the statement.
            $refOnly = $isDsc && $isCharge;

            $label = $this->journalLabel($journal);
            if (in_array($journal, self::JOURNALS_CREDIT, true) || $isDsc) {
                [$debit, $credit] = [-$credit, -$debit];
                if ($isDsc) $label = defined('TEXT_DISCOUNT') ? TEXT_DISCOUNT : 'Discount';
            }

            $due = ['net_date' => ''];
            if (function_exists('calculate_terms_due_dates')) {
                $due = calculate_terms_due_dates($r['post_date'], $terms);
            }

            $lines[] = [
                'journal_desc'        => $refOnly ? self::REF_ONLY_LABEL : $label,
                'purchase_invoice_id' => $r['purchase_invoice_id'],
                'purch_order_id'      => $r['purch_order_id'],
                'post_date'           => $r['post_date'],
                'due_date'            => $due['net_date'] ?? '',
                'credit_amount'       => $refOnly ? -$credit : $credit,
                'debit_amount'        => $refOnly ? -$debit  : $debit,
                '_ref_only'           => $refOnly,
                '_net'                => $credit - $debit,
            ];
            $res->MoveNext();
        }
        return $lines;
    }

    /**
     * Carried over verbatim from statement_builder, hardcoded English and all.
     * Every other label on the statement comes from a language constant; this
     * one has no constant to come from, and inventing one would change what a
     * shipped form prints. Left as a deviation to close deliberately.
     */
    private const REF_ONLY_LABEL = 'Order Discount (Ref Only)';

    /** Journal number to its admin-tools label, falling back to the number. */
    private function journalLabel(int $journal): string
    {
        $const = 'GEN_ADM_TOOLS_J' . str_pad((string)$journal, 2, '0', STR_PAD_LEFT);
        return defined($const) ? (string)constant($const) : (string)$journal;
    }

    // ── typing ───────────────────────────────────────────────────────────────

    private const MONEY = [
        'prior_balance', 'statement_total', 'balance_due',
        'balance_0', 'balance_30', 'balance_60', 'balance_90',
    ];
    private const DATES      = ['post_date'];
    private const MONEY_LINE = ['credit_amount', 'debit_amount'];
    private const DATE_LINE  = ['post_date', 'due_date'];

    /** @param array<string,mixed> $f @return array<string,TypedValue> */
    private function typeValues(array $f): array
    {
        $out = [];
        foreach ($f as $k => $v) {
            $out[$k] = match (true) {
                in_array($k, self::MONEY, true) => TypedValue::money($v),
                in_array($k, self::DATES, true) => TypedValue::date($v === null ? null : (string)$v),
                default                         => new TypedValue($v),
            };
        }
        return $out;
    }

    /** @param array<string,mixed> $line @return array<string,TypedValue> */
    private function typeLine(array $line): array
    {
        $out = [];
        foreach ($line as $k => $v) {
            $out[$k] = match (true) {
                in_array($k, self::MONEY_LINE, true) => TypedValue::money($v),
                in_array($k, self::DATE_LINE, true)  => TypedValue::date($v === null ? null : (string)$v),
                default                              => new TypedValue($v),
            };
        }
        return $out;
    }
}
