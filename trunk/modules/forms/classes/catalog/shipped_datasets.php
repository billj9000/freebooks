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
//  Path: /modules/forms/classes/catalog/shipped_datasets.php
//
/**
 * Forms — the datasets that ship with FreeBooks.
 *
 * These are the "shipped" tier of §3.2: version-controlled PHP, editable only
 * with filesystem access, and therefore permitted to reference any table and to
 * carry raw SQL expressions. Everything a report author can do is bounded by
 * what is defined here.
 *
 * The shape comes from §6.1: no report spans more than one connected component
 * of the schema, and the largest is split into Transactions and General Ledger,
 * because a dataset is a curated view chosen for usability rather than the
 * maximal graph.
 *
 * The field lists are not exhaustive schema dumps. They cover what the shipped
 * definitions actually reference (§6.2)
 * plus the identifying columns a report author needs to filter on. Adding a
 * field is a one-line change here; that is the intended way to extend a dataset,
 * and it is why the admin UI never needs a table picker over the whole schema.
 */

namespace FreeBooks\Forms\Catalog;

use FreeBooks\Forms\Document\ValueType;

final class ShippedDatasets
{
    /** Register every shipped dataset onto a catalog. */
    public static function registerAll(Catalog $catalog): void
    {
        $catalog->register(self::transactions());
        $catalog->register(self::purchasing());
        $catalog->register(self::generalLedger());
        $catalog->register(self::inventory());
        $catalog->register(self::auditTrail());
        $catalog->register(self::contacts());
        $catalog->register(self::workOrders());
        $catalog->register(self::assets());
    }

    /**
     * Orders, invoices, receipts and their line items, with the contact and
     * address they belong to — the dataset behind most of the library.
     *
     * `journal_main` -> `journal_item` is one-to-many, so a query over this
     * dataset returns one row per LINE, and a form groups them by its break
     * field.
     */
    public static function transactions(): Dataset
    {
        return new Dataset(
            id:        'transactions',
            label:     'Transactions',
            baseTable: 'journal_main',
            description: 'Orders, invoices, purchases and receipts, with their line items and contact.',
            joins: [
                new Join('journal_item', [
                    new JoinCondition('journal_main', 'id', 'journal_item', 'ref_id'),
                ], JoinType::Inner),
                new Join('contacts', [
                    new JoinCondition('journal_main', 'bill_acct_id', 'contacts', 'id'),
                ], JoinType::Left),
                new Join('address_book', [
                    new JoinCondition('contacts', 'id', 'address_book', 'ref_id'),
                ], JoinType::Left),
                // The account a LINE posts to. Reports that select by account
                // type rather than by journal — the Daily Cash Report asks for
                // postings against cash accounts — need this to be reachable
                // from the transaction side. LEFT, so a line whose account has
                // no chart row still appears; any filter on account_type
                // excludes those anyway, as an INNER join would.
                new Join('chart_of_accounts', [
                    new JoinCondition('journal_item', 'gl_account', 'chart_of_accounts', 'id'),
                ], JoinType::Left),
            ],
            fields: [
                // ── the transaction ──────────────────────────────────────────
                new FieldDefinition('id',                  'Record ID',        'journal_main', 'id',                  ValueType::Integer),
                new FieldDefinition('journal_id',          'Journal',          'journal_main', 'journal_id',          ValueType::Integer),
                new FieldDefinition('post_date',           'Post Date',        'journal_main', 'post_date',           ValueType::Date),
                new FieldDefinition('period',              'Period',           'journal_main', 'period',              ValueType::Integer),
                new FieldDefinition('description',         'Description',      'journal_main', 'description'),
                new FieldDefinition('closed',              'Closed',           'journal_main', 'closed',              ValueType::Boolean),
                new FieldDefinition('closed_date',         'Closed Date',      'journal_main', 'closed_date',         ValueType::Date),
                new FieldDefinition('printed',             'Printed Count',    'journal_main', 'printed',             ValueType::Integer),
                new FieldDefinition('waiting',             'Waiting',          'journal_main', 'waiting',             ValueType::Boolean),
                new FieldDefinition('notes',               'Notes',            'journal_main', 'notes'),
                new FieldDefinition('terms',               'Terms',            'journal_main', 'terms'),
                new FieldDefinition('purchase_invoice_id', 'PO/Invoice Number','journal_main', 'purchase_invoice_id'),
                new FieldDefinition('purch_order_id',      'Purchase Order',   'journal_main', 'purch_order_id'),
                new FieldDefinition('so_po_ref_id',        'SO/PO Reference',  'journal_main', 'so_po_ref_id'),
                new FieldDefinition('drop_ship',           'Drop Ship',        'journal_main', 'drop_ship',           ValueType::Boolean),
                new FieldDefinition('store_id',            'Branch',           'journal_main', 'store_id',            ValueType::Integer),

                // ── money ────────────────────────────────────────────────────
                new FieldDefinition('total_amount',    'Total',            'journal_main', 'total_amount',    ValueType::Money),
                new FieldDefinition('sales_tax',       'Sales Tax',        'journal_main', 'sales_tax',       ValueType::Money),
                new FieldDefinition('freight',         'Freight',          'journal_main', 'freight',         ValueType::Money),
                new FieldDefinition('discount',        'Discount',         'journal_main', 'discount',        ValueType::Money),
                new FieldDefinition('currencies_code', 'Currency',         'journal_main', 'currencies_code'),
                new FieldDefinition('currencies_value','Exchange Rate',    'journal_main', 'currencies_value', ValueType::Decimal),

                // ── shipping ─────────────────────────────────────────────────
                new FieldDefinition('shipper_code',    'Carrier',          'journal_main', 'shipper_code'),
                new FieldDefinition('ship_packages',   'Packages',         'journal_main', 'ship_packages',   ValueType::Integer),
                new FieldDefinition('terminal_date',   'Ship Date',        'journal_main', 'terminal_date',   ValueType::Date),

                // ── who ──────────────────────────────────────────────────────
                // admin_id and rep_id are ids. A form wants the NAME, and
                // resolving one is a database lookup, which a renderer may not
                // do (§4.3.1) — so the join to `users` would have to happen
                // here. It deliberately does not: `users` is a sensitive table
                // and pulling it into the dataset behind most documents
                // would
                // hand every report author a route to it. The provider resolves
                // these instead.
                new FieldDefinition('admin_id', 'Entered By (id)', 'journal_main', 'admin_id', ValueType::Integer),
                new FieldDefinition('rep_id',   'Sales Rep (id)',  'journal_main', 'rep_id',   ValueType::Integer),

                // ── billing address ──────────────────────────────────────────
                new FieldDefinition('bill_acct_id',        'Bill Account ID',   'journal_main', 'bill_acct_id', ValueType::Integer),
                new FieldDefinition('bill_primary_name',   'Bill Name',         'journal_main', 'bill_primary_name'),
                new FieldDefinition('bill_contact',        'Bill Contact',      'journal_main', 'bill_contact'),
                new FieldDefinition('bill_address1',       'Bill Address 1',    'journal_main', 'bill_address1'),
                new FieldDefinition('bill_address2',       'Bill Address 2',    'journal_main', 'bill_address2'),
                new FieldDefinition('bill_city_town',      'Bill City',         'journal_main', 'bill_city_town'),
                new FieldDefinition('bill_state_province', 'Bill State',        'journal_main', 'bill_state_province'),
                new FieldDefinition('bill_postal_code',    'Bill Postcode',     'journal_main', 'bill_postal_code'),
                new FieldDefinition('bill_country_code',   'Bill Country',      'journal_main', 'bill_country_code'),
                new FieldDefinition('bill_telephone1',     'Bill Telephone',    'journal_main', 'bill_telephone1'),
                new FieldDefinition('bill_email',          'Bill Email',        'journal_main', 'bill_email'),

                // ── shipping address ─────────────────────────────────────────
                new FieldDefinition('ship_primary_name',   'Ship Name',         'journal_main', 'ship_primary_name'),
                new FieldDefinition('ship_contact',        'Ship Contact',      'journal_main', 'ship_contact'),
                new FieldDefinition('ship_address1',       'Ship Address 1',    'journal_main', 'ship_address1'),
                new FieldDefinition('ship_address2',       'Ship Address 2',    'journal_main', 'ship_address2'),
                new FieldDefinition('ship_city_town',      'Ship City',         'journal_main', 'ship_city_town'),
                new FieldDefinition('ship_state_province', 'Ship State',        'journal_main', 'ship_state_province'),
                new FieldDefinition('ship_postal_code',    'Ship Postcode',     'journal_main', 'ship_postal_code'),
                new FieldDefinition('ship_country_code',   'Ship Country',      'journal_main', 'ship_country_code'),
                new FieldDefinition('ship_telephone1',     'Ship Telephone',    'journal_main', 'ship_telephone1'),
                new FieldDefinition('ship_email',          'Ship Email',        'journal_main', 'ship_email'),

                // ── line items ───────────────────────────────────────────────
                new FieldDefinition('item_id',          'Line ID',          'journal_item', 'id',            ValueType::Integer),
                new FieldDefinition('item_sku',         'SKU',              'journal_item', 'sku'),
                new FieldDefinition('item_description', 'Item Description', 'journal_item', 'description'),
                new FieldDefinition('item_qty',         'Quantity',         'journal_item', 'qty',           ValueType::Decimal),
                new FieldDefinition('item_debit',       'Debit',            'journal_item', 'debit_amount',  ValueType::Money),
                new FieldDefinition('item_credit',      'Credit',           'journal_item', 'credit_amount', ValueType::Money),
                new FieldDefinition('item_gl_type',     'Entry Type',       'journal_item', 'gl_type'),
                new FieldDefinition('item_gl_account',  'GL Account',       'journal_item', 'gl_account'),
                new FieldDefinition('item_taxable',     'Taxable',          'journal_item', 'taxable',       ValueType::Integer),
                new FieldDefinition('item_serialize',   'Serialized',       'journal_item', 'serialize',     ValueType::Boolean),

                // The serial itself, not the flag beside it. `item_serialize`
                // says WHETHER a line is serialized; this is the number, and
                // the three-part cheque prints it on the stub.
                new FieldDefinition('item_serial_number', 'Serial Number',  'journal_item', 'serialize_number'),

                // What the line is worth, whichever side of the entry it sits
                // on. Distinct from item_net (credit MINUS debit, a movement
                // that can be negative) and from the two raw sides: a payment
                // stub wants the amount, and only one of the two columns is
                // ever populated on a given line.
                new FieldDefinition(
                    key:        'item_amount',
                    label:      'Line Amount',
                    table:      'journal_item',
                    type:       ValueType::Money,
                    expression: '(journal_item.credit_amount + journal_item.debit_amount)',
                    legacyNames: ['journal_item.credit_amount + journal_item.debit_amount'],
                ),
                new FieldDefinition('item_date_1',      'Item Date',        'journal_item', 'date_1',        ValueType::Date),

                // A computed field: the per-unit price the line worked out to,
                // named, typed and defined once, in code, rather than written
                // into a report's field list — which is the whole point of the
                // shipped tier.
                new FieldDefinition(
                    key:        'item_unit_price',
                    label:      'Unit Price',
                    table:      'journal_item',      // grain: one per line
                    type:       ValueType::Money,
                    expression: '(CASE WHEN journal_item.qty = 0 THEN 0 ELSE journal_item.debit_amount / journal_item.qty END)',
                    sortable:   true,
                    // The inline expression the shipped definitions carry,
                    // with a guard against division by zero that the raw
                    // expression lacks.
                    legacyNames: ['journal_item.debit_amount / journal_item.qty'],
                ),

                // The same price read off the OTHER side of the entry, and it
                // has to exist separately for the same reason item_net_debit
                // does: a journal line carries its amount on the side its
                // account takes. A sales order line is qty 8, debit 0, credit
                // 20.40 — so the debit-side expression above computes 0 for it,
                // and pointing the credit-side definitions at that field would
                // print 0.00 where a price belongs. A wrong number is worse
                // than a blank, because it looks right.
                //
                // Four shipped definitions write this inline — Sales Order,
                // Customer Quotes, Vendor Credit Memo and the three-part Bank
                // Check — and their unit-price column binds to it.
                //
                // Named with the side as a SUFFIX rather than matching the
                // net pair's convention (where the bare name is the credit
                // one): `item_unit_price` is already stored in converted
                // definitions and cannot be re-pointed without changing what
                // they print.
                new FieldDefinition(
                    key:        'item_unit_price_credit',
                    label:      'Unit Price (Credit Side)',
                    table:      'journal_item',
                    type:       ValueType::Money,
                    expression: '(CASE WHEN journal_item.qty = 0 THEN 0 ELSE journal_item.credit_amount / journal_item.qty END)',
                    sortable:   true,
                    legacyNames: ['journal_item.credit_amount / journal_item.qty'],
                ),

                // Net movement on the line. The sales reports total this; naming
                // it once means the reports agree on what "net" means.
                new FieldDefinition(
                    key:        'item_net',
                    label:      'Net Amount',
                    table:      'journal_item',
                    type:       ValueType::Money,
                    expression: '(journal_item.credit_amount - journal_item.debit_amount)',
                    legacyNames: ['journal_item.credit_amount - journal_item.debit_amount'],
                ),

                // The same movement with the sign the other way up. Both are
                // needed, and which one a report wants is not a style choice:
                // a journal line carries the amount on the side its account
                // takes, so Sales By SKU (credit - debit) and Sales By Rep
                // (debit - credit) are reading opposite sides of the same
                // entry and both expect a positive number. Shipped
                // definitions write whichever subtraction suits inline,
                // and reference this exact string.
                new FieldDefinition(
                    key:        'item_net_debit',
                    label:      'Net Amount (Debit Side)',
                    table:      'journal_item',
                    type:       ValueType::Money,
                    expression: '(journal_item.debit_amount - journal_item.credit_amount)',
                    legacyNames: ['journal_item.debit_amount - journal_item.credit_amount'],
                ),

                // ── the account a line posts to ──────────────────────────────
                new FieldDefinition('gl_account_title', 'Account Title', 'chart_of_accounts', 'description'),
                new FieldDefinition('gl_account_type',  'Account Type',  'chart_of_accounts', 'account_type', ValueType::Integer),

                // ── contact / address book ───────────────────────────────────
                new FieldDefinition('contact_account_number', 'Account Number', 'contacts', 'account_number'),
                new FieldDefinition('contact_short_name',     'Short Name',     'contacts', 'short_name'),
                new FieldDefinition('contact_type',           'Contact Type',   'contacts', 'type'),
                new FieldDefinition('contact_first_date',     'First Activity', 'contacts', 'first_date',   ValueType::Date),
                new FieldDefinition('contact_last_date',      'Last Activity',  'contacts', 'last_date_1',  ValueType::Date),
                new FieldDefinition('address_type',           'Address Type',   'address_book', 'type'),
                new FieldDefinition('address_telephone1',     'Telephone 1',    'address_book', 'telephone1'),
                new FieldDefinition('address_telephone3',     'Telephone 3',    'address_book', 'telephone3'),
                new FieldDefinition('address_email',          'Address Email',  'address_book', 'email'),

                // ── aging ────────────────────────────────────────────────────
                // See agingFields(): six computed columns that replace the
                // aged_receivables and aged_summary special classes outright.
                ...self::agingFields(),
            ],
            params: self::agingCutoffs(),
        );
    }

    /**
     * The aging buckets, as ordinary dataset fields.
     *
     * As fields they cost one query for any number of rows, group and total
     * through the normal report path, and can be put on any transactions report.
     *
     * **The sign rule follows the ledger.** A customer invoice and a vendor
     * credit memo both carry their ttl amount as a DEBIT, while a vendor bill and
     * a customer credit memo both carry it as a CREDIT. So the side a document
     * sits on follows the ledger, not the document type, and the rule that makes
     * every base document positive and every credit memo negative is to negate
     * the payables journals (6 and 7) and leave the receivables journals (12 and
     * 13) alone. Negating {6, 13} instead would read a vendor credit memo as a
     * positive payable, and {7, 13} a vendor BILL as negative.
     *
     * @return list<FieldDefinition>
     */
    private static function agingFields(): array
    {
        // Payables sit on the credit side of the ttl line, receivables on the
        // debit side; a payment reverses whichever it settles.
        $billed = fn(string $a, string $b) =>
            "(SELECT COALESCE(SUM(ttl.{$a}_amount - ttl.{$b}_amount), 0) FROM {{journal_item}} ttl"
          . " WHERE ttl.ref_id = journal_main.id AND ttl.gl_type = 'ttl')";
        $paid = fn(string $a, string $b) =>
            "(SELECT COALESCE(SUM(pmt.{$a}_amount - pmt.{$b}_amount), 0) FROM {{journal_item}} pmt"
          . " WHERE pmt.so_po_item_ref_id = journal_main.id AND pmt.gl_type IN ('pmt', 'chk'))";

        $isPayable = 'journal_main.journal_id IN (6, 7)';
        $balance =
            "(CASE WHEN $isPayable"
          . " THEN " . $billed('credit', 'debit') . ' - ' . $paid('debit', 'credit')
          . " ELSE " . $billed('debit', 'credit') . ' - ' . $paid('credit', 'debit')
          . ' END)';

        // Each bucket restates the whole if/elseif chain, because they are
        // separate columns rather than successive branches. A row lands in
        // exactly one; the rest report zero, which is what makes them summable.
        $cutoff = fn(string $days) => "(CASE WHEN $isPayable THEN {{param:ap_late_$days}} ELSE {{param:ar_late_$days}} END)";
        $before = fn(string $days) => 'journal_main.post_date < ' . $cutoff($days);
        $bucket = fn(string $when) => "(CASE WHEN $when THEN $balance ELSE 0 END)";

        $over90 = $before('90');
        $in60   = '(NOT ' . $before('90') . ' AND ' . $before('60') . ')';
        $in30   = '(NOT ' . $before('60') . ' AND ' . $before('30') . ')';
        $future = '(NOT ' . $before('30') . ' AND journal_main.post_date > {{param:today}})';
        $current= '(NOT ' . $before('30') . ' AND NOT journal_main.post_date > {{param:today}})';

        return [
            new FieldDefinition(
                key: 'balance_due', label: 'Balance Due', type: ValueType::Money,
                expression: $balance, legacyNames: ['balance_due'],
            ),
            new FieldDefinition(
                key: 'balance_future', label: 'Not Yet Due', type: ValueType::Money,
                expression: $bucket($future),
                // PhreeForm's own misspelling; it is the stored field name.
                legacyNames: ['balance_futre'],
            ),
            new FieldDefinition(
                key: 'balance_0',  label: 'Age Current', type: ValueType::Money,
                expression: $bucket($current), legacyNames: ['balance_0'],
            ),
            new FieldDefinition(
                key: 'balance_30', label: 'Age 30', type: ValueType::Money,
                expression: $bucket($in30), legacyNames: ['balance_30'],
            ),
            new FieldDefinition(
                key: 'balance_60', label: 'Age 60', type: ValueType::Money,
                expression: $bucket($in60), legacyNames: ['balance_60'],
            ),
            new FieldDefinition(
                key: 'balance_90', label: 'Age 90', type: ValueType::Money,
                expression: $bucket($over90), legacyNames: ['balance_90'],
            ),
        ];
    }

    /**
     * Today, and the three aging cutoffs for each side, as bound parameters.
     *
     * Receivables and payables age independently — AR_AGING_PERIOD_* against
     * AP_AGING_DATE_* — so a payables summary is bucketed by the payables
     * periods.
     *
     * @return array<string,string>
     */
    private static function agingCutoffs(): array
    {
        $today = date('Y-m-d');
        $back  = function (string $constant, int $fallback) use ($today): string {
            $days = defined($constant) ? (int)constant($constant) : $fallback;
            return date('Y-m-d', strtotime("$today -$days days"));
        };
        return [
            'today'      => $today,
            'ar_late_30' => $back('AR_AGING_PERIOD_1', 30),
            'ar_late_60' => $back('AR_AGING_PERIOD_2', 60),
            'ar_late_90' => $back('AR_AGING_PERIOD_3', 90),
            'ap_late_30' => $back('AP_AGING_DATE_1',   30),
            'ap_late_60' => $back('AP_AGING_DATE_2',   60),
            'ap_late_90' => $back('AP_AGING_DATE_3',   90),
        ];
    }

    /**
     * The chart of accounts and its balance history — the trial balance,
     * balance sheet and income statement family.
     *
     * Shares journal_item/journal_main with Transactions on purpose: datasets
     * are views, and may overlap.
     */
    public static function generalLedger(): Dataset
    {
        return new Dataset(
            id:        'general_ledger',
            label:     'General Ledger',
            baseTable: 'chart_of_accounts',
            description: 'Accounts, their balances by period, and the postings behind them.',
            joins: [
                new Join('chart_of_accounts_history', [
                    new JoinCondition('chart_of_accounts', 'id', 'chart_of_accounts_history', 'account_id'),
                ], JoinType::Left),
                new Join('journal_item', [
                    new JoinCondition('chart_of_accounts', 'id', 'journal_item', 'gl_account'),
                ], JoinType::Left),
                new Join('journal_main', [
                    new JoinCondition('journal_item', 'ref_id', 'journal_main', 'id'),
                ], JoinType::Left),
            ],
            fields: [
                new FieldDefinition('account_id',      'Account ID',    'chart_of_accounts', 'id',            ValueType::Integer),
                new FieldDefinition('account_number',  'Account',       'chart_of_accounts', 'id'),
                new FieldDefinition('account_title',   'Account Title', 'chart_of_accounts', 'description'),
                new FieldDefinition('account_type',    'Account Type',  'chart_of_accounts', 'account_type',  ValueType::Integer),
                new FieldDefinition('account_inactive','Inactive',      'chart_of_accounts', 'account_inactive', ValueType::Boolean),
                new FieldDefinition('account_heading', 'Heading Only',  'chart_of_accounts', 'heading_only',     ValueType::Boolean),

                new FieldDefinition('history_period',  'Period',        'chart_of_accounts_history', 'period',        ValueType::Integer),
                new FieldDefinition('beginning_balance','Opening Balance','chart_of_accounts_history','beginning_balance', ValueType::Money),
                new FieldDefinition('debit_amount',    'Period Debits', 'chart_of_accounts_history', 'debit_amount',  ValueType::Money),
                new FieldDefinition('credit_amount',   'Period Credits','chart_of_accounts_history', 'credit_amount', ValueType::Money),

                new FieldDefinition('history_budget',  'Period Budget', 'chart_of_accounts_history', 'budget',        ValueType::Money),

                // The trial balance's two columns. A period's closing balance is
                // opening + debits - credits, and the report shows it in
                // whichever column its sign calls for — so each side is the
                // balance clamped at zero, and exactly one of the pair is
                // non-zero for any account. The stored legacy definition writes
                // it inline with GREATEST() and no space after `debit_amount`,
                // which still matches because TemplateConverter::normalise()
                // strips whitespace before matching.
                new FieldDefinition(
                    key:        'trial_debit',
                    label:      'Trial Balance Debit',
                    table:      'chart_of_accounts_history',
                    type:       ValueType::Money,
                    expression: 'GREATEST(chart_of_accounts_history.debit_amount - chart_of_accounts_history.credit_amount'
                              . ' + chart_of_accounts_history.beginning_balance, 0)',
                    legacyNames: ['greatest(chart_of_accounts_history.debit_amount -chart_of_accounts_history.credit_amount + chart_of_accounts_history.beginning_balance, 0)'],
                ),
                new FieldDefinition(
                    key:        'trial_credit',
                    label:      'Trial Balance Credit',
                    table:      'chart_of_accounts_history',
                    type:       ValueType::Money,
                    expression: 'GREATEST(chart_of_accounts_history.credit_amount - chart_of_accounts_history.debit_amount'
                              . ' - chart_of_accounts_history.beginning_balance, 0)',
                    legacyNames: ['greatest(chart_of_accounts_history.credit_amount - chart_of_accounts_history.debit_amount - chart_of_accounts_history.beginning_balance, 0)'],
                ),

                new FieldDefinition('posting_id',      'Posting ID',    'journal_item', 'id',            ValueType::Integer),
                new FieldDefinition('posting_account', 'Posting Account','journal_item', 'gl_account'),
                new FieldDefinition('posting_debit',   'Debit',         'journal_item', 'debit_amount',  ValueType::Money),
                new FieldDefinition('posting_credit',  'Credit',        'journal_item', 'credit_amount', ValueType::Money),
                new FieldDefinition('posting_desc',    'Posting Text',  'journal_item', 'description'),
                new FieldDefinition('posting_sku',     'Posting SKU',   'journal_item', 'sku'),
                new FieldDefinition('journal_record_id','Journal Record','journal_main','id',            ValueType::Integer),
                new FieldDefinition('journal_name',    'Contact Name',  'journal_main', 'bill_primary_name'),
                new FieldDefinition('post_date',       'Post Date',     'journal_main', 'post_date',     ValueType::Date),
                new FieldDefinition('period',          'Journal Period','journal_main', 'period',        ValueType::Integer),
                new FieldDefinition('journal_id',      'Journal',       'journal_main', 'journal_id',    ValueType::Integer),
                new FieldDefinition('reference',       'Reference',     'journal_main', 'purchase_invoice_id'),
            ],
        );
    }

    /** Stock, its movement history, and work orders (§6.1 component D2). */
    public static function inventory(): Dataset
    {
        return new Dataset(
            id:        'inventory',
            label:     'Inventory',
            baseTable: 'inventory',
            description: 'Stock items, quantities, costs and movement history.',
            joins: [
                new Join('inventory_history', [
                    new JoinCondition('inventory', 'sku', 'inventory_history', 'sku'),
                ], JoinType::Left),
            ],
            fields: [
                new FieldDefinition('sku',              'SKU',              'inventory', 'sku'),
                new FieldDefinition('item_description', 'Description',      'inventory', 'description_short'),
                new FieldDefinition('long_description', 'Long Description', 'inventory', 'description_sales'),
                new FieldDefinition('item_type',        'Type',             'inventory', 'inventory_type'),
                new FieldDefinition('qty_stock',        'Qty On Hand',      'inventory', 'quantity_on_hand',         ValueType::Decimal),
                new FieldDefinition('qty_on_order',     'Qty On Order',     'inventory', 'quantity_on_order',        ValueType::Decimal),
                new FieldDefinition('qty_on_sales',     'Qty Committed',    'inventory', 'quantity_on_sales_order',  ValueType::Decimal),
                new FieldDefinition('qty_allocated',    'Qty Allocated',    'inventory', 'quantity_on_allocation',   ValueType::Decimal),
                new FieldDefinition('item_cost',        'Item Cost',        'inventory', 'item_cost',        ValueType::Money),
                new FieldDefinition('full_price',       'Full Price',       'inventory', 'full_price',       ValueType::Money),
                new FieldDefinition('inactive',         'Inactive',         'inventory', 'inactive',         ValueType::Boolean),
                new FieldDefinition('cost_method',      'Cost Method',      'inventory', 'cost_method'),
                new FieldDefinition('min_stock_level',  'Minimum Stock',    'inventory', 'minimum_stock_level', ValueType::Decimal),
                new FieldDefinition('reorder_quantity', 'Reorder Quantity', 'inventory', 'reorder_quantity',    ValueType::Decimal),
                new FieldDefinition('lead_time',        'Lead Time',        'inventory', 'lead_time',           ValueType::Integer),
                // The vendor is stored as an id. The name is a contacts lookup,
                // and joining contacts here would widen a dataset that exists to
                // be about stock — so the id is what a report gets, as the
                // Re-order Worksheet prints it.
                new FieldDefinition('vendor_id',        'Vendor (id)',      'inventory', 'vendor_id',           ValueType::Integer),
                // Despite the column name this is the inventory ASSET account;
                // `wage` is a leftover from PhreeBooks' service-item handling.
                new FieldDefinition('account_inventory','Inventory Account','inventory', 'account_inventory_wage'),
                new FieldDefinition('account_income',   'Income Account',   'inventory', 'account_sales_income'),
                new FieldDefinition('account_cogs',     'COGS Account',     'inventory', 'account_cost_of_sales'),

                new FieldDefinition('history_id',       'History ID',       'inventory_history', 'id',        ValueType::Integer),
                new FieldDefinition('history_date',     'Movement Date',    'inventory_history', 'post_date', ValueType::Date),
                new FieldDefinition('history_qty',      'Movement Qty',     'inventory_history', 'qty',       ValueType::Decimal),
                new FieldDefinition('history_remaining','Remaining',        'inventory_history', 'remaining', ValueType::Decimal),
                new FieldDefinition('history_unit_cost','Unit Cost',        'inventory_history', 'unit_cost', ValueType::Money),
                new FieldDefinition('history_avg_cost', 'Average Cost',     'inventory_history', 'avg_cost',  ValueType::Money),
                new FieldDefinition('history_store_id', 'Branch',           'inventory_history', 'store_id',  ValueType::Integer),
                new FieldDefinition('history_serial',   'Serial Number',    'inventory_history', 'serialize_number'),
                new FieldDefinition('history_journal',  'Journal',          'inventory_history', 'journal_id', ValueType::Integer),
                // inventory_history carries its own sku, and the valuation
                // reports select THAT one rather than inventory.sku. They are
                // equal by the join condition, but a report that names it must
                // still resolve — and it fixes the grain: selecting the history
                // copy is what tells the pruner this report is per-movement.
                new FieldDefinition('history_sku',      'SKU (Movement)',   'inventory_history', 'sku'),

                // The computed expression the stock valuation reports use
                // (§6.2).
                new FieldDefinition(
                    key:        'history_value',
                    label:      'Remaining Value',
                    table:      'inventory_history', // grain: one per movement
                    type:       ValueType::Money,
                    expression: '(inventory_history.unit_cost * inventory_history.remaining)',
                    legacyNames: ['inventory_history.unit_cost * inventory_history.remaining'],
                ),

                // The `avg_cost` and `avg_total` processing hints, as SQL.
                //
                // The key names look odd and are not arbitrary: ReportConverter
                // re-points a provider-resolved hint by appending
                // PROVIDER_RESOLVED[$hint] to the field the column already maps
                // to, so `avg_cost` on `inventory_history.sku` must be found at
                // `history_sku` + `_avg_cost`. Renaming either half breaks the
                // link silently.
                //
                // As SQL it is one statement rather than queries per row, and
                // the definition of "average cost" sits in the dataset where a
                // report author can see it.
                new FieldDefinition(
                    key:        'history_sku_avg_cost',
                    label:      'Average Cost',
                    table:      'inventory_history',
                    type:       ValueType::Money,
                    expression: '(SELECT h_avg.avg_cost FROM {{inventory_history}} h_avg'
                              . ' WHERE h_avg.sku = inventory_history.sku'
                              . ' ORDER BY h_avg.post_date DESC LIMIT 1)',
                ),
                new FieldDefinition(
                    key:        'history_id_avg_total',
                    label:      'Total Value At Average',
                    table:      'inventory_history',
                    type:       ValueType::Money,
                    expression: '(inventory_history.remaining * (SELECT h_tot.avg_cost'
                              . ' FROM {{inventory_history}} h_tot'
                              . ' WHERE h_tot.sku = inventory_history.sku'
                              . ' ORDER BY h_tot.post_date DESC LIMIT 1))',
                ),
            ],
        );
    }

    /**
     * The audit trail (§6.1 component D3).
     *
     * The one shipped dataset that joins a sensitive table. It is exactly the
     * case §3.3 anticipated: the stock report needs `users` to name who did
     * what, so it ships as code — while the admin UI can never produce an
     * equivalent, because Custom datasets refuse that table outright.
     */
    public static function auditTrail(): Dataset
    {
        return new Dataset(
            id:        'audit_trail',
            label:     'Audit Trail',
            baseTable: 'audit_log',
            description: 'Recorded system activity, with the user responsible.',
            joins: [
                new Join('users', [
                    new JoinCondition('audit_log', 'user_id', 'users', 'admin_id'),
                ], JoinType::Left),
            ],
            fields: [
                new FieldDefinition('log_id',      'Entry ID',   'audit_log', 'id',        ValueType::Integer),
                new FieldDefinition('log_date',    'Date',       'audit_log', 'action_date', ValueType::Date),
                new FieldDefinition('log_action',  'Action',     'audit_log', 'action'),
                new FieldDefinition('log_detail',  'Detail',     'audit_log', 'stats'),
                new FieldDefinition('log_ip',      'IP Address', 'audit_log', 'ip_address'),
                new FieldDefinition('log_amount',  'Amount',     'audit_log', 'amount',      ValueType::Money),
                new FieldDefinition('log_ref',     'Reference',  'audit_log', 'reference_id'),
                // Named columns only — never the whole `users` row, so password
                // hashes and tokens are not reachable even from here.
                new FieldDefinition('user_name',   'User',       'users', 'admin_name'),
                new FieldDefinition('user_display','Display Name','users', 'display_name'),
            ],
        );
    }

    /**
     * Customers, vendors and employees, with their addresses.
     *
     * One table serves all three in FreeBooks — `contacts.type` is 'c', 'v' or
     * 'e' — which is why the Customer, Vendor and Employee List reports differ
     * only in their filter. `address_book` is one-to-many (a contact has a
     * billing address, zero or more shipping addresses, and for a vendor a
     * remit-to), so a report that reaches it should filter `address_type` or it
     * will see a row per address. The shipped lists all filter on 'm' (main).
     */
    public static function contacts(): Dataset
    {
        return new Dataset(
            id:        'contacts',
            label:     'Contacts',
            baseTable: 'contacts',
            description: 'Customers, vendors and employees, with their address book entries.',
            joins: [
                new Join('address_book', [
                    new JoinCondition('contacts', 'id', 'address_book', 'ref_id'),
                ], JoinType::Left),
            ],
            fields: [
                new FieldDefinition('contact_id',      'Record ID',    'contacts', 'id',      ValueType::Integer),
                new FieldDefinition('short_name',      'Short Name',   'contacts', 'short_name'),
                new FieldDefinition('account_number',  'Account Number','contacts', 'account_number'),
                new FieldDefinition('contact_type',    'Type',         'contacts', 'type'),
                new FieldDefinition('contact_level',   'Level',        'contacts', 'contacts_level'),
                new FieldDefinition('inactive',        'Inactive',     'contacts', 'inactive',    ValueType::Boolean),
                new FieldDefinition('first_name',      'First Name',   'contacts', 'contact_first'),
                new FieldDefinition('middle_name',     'Middle Name',  'contacts', 'contact_middle'),
                new FieldDefinition('last_name',       'Last Name',    'contacts', 'contact_last'),
                new FieldDefinition('gov_id_number',   'Government ID','contacts', 'gov_id_number'),
                new FieldDefinition('vat_number',      'VAT Number',   'contacts', 'vat_number'),
                new FieldDefinition('eori_number',     'EORI Number',  'contacts', 'eori_number'),
                new FieldDefinition('store_id',        'Branch',       'contacts', 'store_id',    ValueType::Integer),
                new FieldDefinition('rep_id',          'Rep (id)',     'contacts', 'dept_rep_id', ValueType::Integer),
                new FieldDefinition('gl_account',      'GL Account',   'contacts', 'gl_type_account'),
                new FieldDefinition('special_terms',   'Terms',        'contacts', 'special_terms'),
                new FieldDefinition('price_sheet',     'Price Sheet',  'contacts', 'price_sheet'),
                new FieldDefinition('currency_code',   'Currency',     'contacts', 'currency_code'),
                new FieldDefinition('tax_id',          'Tax Rate (id)','contacts', 'tax_id',      ValueType::Integer),
                new FieldDefinition('first_date',      'First Activity','contacts','first_date',  ValueType::Date),
                new FieldDefinition('last_date',       'Last Activity','contacts', 'last_date_1', ValueType::Date),
                new FieldDefinition('last_update',     'Last Updated', 'contacts', 'last_update', ValueType::Date),

                new FieldDefinition('address_id',      'Address ID',   'address_book', 'address_id', ValueType::Integer),
                new FieldDefinition('address_type',    'Address Type', 'address_book', 'type'),
                new FieldDefinition('primary_name',    'Name',         'address_book', 'primary_name'),
                new FieldDefinition('contact_name',    'Contact',      'address_book', 'contact'),
                new FieldDefinition('address1',        'Address 1',    'address_book', 'address1'),
                new FieldDefinition('address2',        'Address 2',    'address_book', 'address2'),
                new FieldDefinition('city_town',       'City',         'address_book', 'city_town'),
                new FieldDefinition('state_province',  'State',        'address_book', 'state_province'),
                new FieldDefinition('postal_code',     'Postcode',     'address_book', 'postal_code'),
                new FieldDefinition('country_code',    'Country',      'address_book', 'country_code'),
                new FieldDefinition('telephone1',      'Telephone 1',  'address_book', 'telephone1'),
                new FieldDefinition('telephone2',      'Telephone 2',  'address_book', 'telephone2'),
                new FieldDefinition('telephone3',      'Telephone 3',  'address_book', 'telephone3'),
                new FieldDefinition('telephone4',      'Telephone 4',  'address_book', 'telephone4'),
                new FieldDefinition('email',           'Email',        'address_book', 'email'),
                new FieldDefinition('website',         'Website',      'address_book', 'website'),
            ],
        );
    }

    /**
     * Work orders, their steps, and the task catalog behind them.
     *
     * `wo_steps` joins `wo_task` the other way round from how it reads —
     * `wo_task.id = wo_steps.task_id` — because a step points AT a task, and
     * the same task is reused across many work orders.
     */
    public static function workOrders(): Dataset
    {
        return new Dataset(
            id:        'work_orders',
            label:     'Work Orders',
            baseTable: 'wo_main',
            description: 'Work orders, the steps they run, and the tasks those steps use.',
            joins: [
                new Join('inventory', [
                    new JoinCondition('wo_main', 'sku_id', 'inventory', 'id'),
                ], JoinType::Left),
                new Join('wo_steps', [
                    new JoinCondition('wo_main', 'id', 'wo_steps', 'ref_id'),
                ], JoinType::Left),
                new Join('wo_task', [
                    new JoinCondition('wo_task', 'id', 'wo_steps', 'task_id'),
                ], JoinType::Left),
            ],
            fields: [
                new FieldDefinition('wo_id',        'Work Order ID', 'wo_main', 'id',        ValueType::Integer),
                new FieldDefinition('wo_title',     'Work Order',    'wo_main', 'wo_title'),
                new FieldDefinition('wo_description','Description',  'wo_main', 'description'),
                new FieldDefinition('wo_revision',  'Revision',      'wo_main', 'revision'),
                new FieldDefinition('wo_revision_date','Revision Date','wo_main','revision_date', ValueType::Date),
                new FieldDefinition('wo_last_usage','Last Used',     'wo_main', 'last_usage',  ValueType::Date),
                new FieldDefinition('wo_inactive',  'Inactive',      'wo_main', 'inactive',    ValueType::Boolean),
                new FieldDefinition('wo_allocate',  'Allocate',      'wo_main', 'allocate',    ValueType::Boolean),
                new FieldDefinition('wo_ref_doc',   'Reference Doc', 'wo_main', 'ref_doc'),
                new FieldDefinition('wo_ref_spec',  'Reference Spec','wo_main', 'ref_spec'),

                new FieldDefinition('sku',          'SKU',           'inventory', 'sku'),
                new FieldDefinition('item_description','Item Description','inventory','description_short'),

                new FieldDefinition('step_id',      'Step ID',       'wo_steps', 'id',      ValueType::Integer),
                new FieldDefinition('step_number',  'Step',          'wo_steps', 'step',    ValueType::Integer),

                new FieldDefinition('task_id',      'Task ID',       'wo_task', 'id',       ValueType::Integer),
                new FieldDefinition('task_name',    'Task',          'wo_task', 'task_name'),
                new FieldDefinition('task_description','Task Description','wo_task','description'),
                new FieldDefinition('task_time',    'Job Time',      'wo_task', 'job_time', ValueType::Decimal),
                new FieldDefinition('task_unit',    'Job Unit',      'wo_task', 'job_unit'),
                new FieldDefinition('task_dept',    'Department',    'wo_task', 'dept_id',  ValueType::Integer),
            ],
        );
    }

    /** Fixed assets — a single table, and the smallest shipped dataset. */
    public static function assets(): Dataset
    {
        return new Dataset(
            id:        'assets',
            label:     'Assets',
            baseTable: 'assets',
            description: 'Fixed assets, their cost, acquisition and disposal.',
            fields: [
                new FieldDefinition('record_id',    'Record ID',    'assets', 'id',        ValueType::Integer),
                new FieldDefinition('asset_id',     'Asset ID',     'assets', 'asset_id'),
                new FieldDefinition('asset_type',   'Type',         'assets', 'asset_type'),
                new FieldDefinition('description',  'Description',  'assets', 'description_short'),
                new FieldDefinition('long_description','Detail',    'assets', 'description_long'),
                new FieldDefinition('purch_cond',   'Condition',    'assets', 'purch_cond'),
                new FieldDefinition('serial_number','Serial Number','assets', 'serial_number'),
                new FieldDefinition('inactive',     'Inactive',     'assets', 'inactive',  ValueType::Boolean),
                new FieldDefinition('asset_cost',   'Cost',         'assets', 'asset_cost',  ValueType::Money),
                new FieldDefinition('full_price',   'Full Price',   'assets', 'full_price',  ValueType::Money),
                new FieldDefinition('depreciation_method','Depreciation Method','assets','depreciation_method'),
                new FieldDefinition('account_asset','Asset Account','assets', 'account_asset'),
                new FieldDefinition('account_depreciation','Depreciation Account','assets','account_depreciation'),
                new FieldDefinition('account_maintenance','Maintenance Account','assets','account_maintenance'),
                new FieldDefinition('acquisition_date','Acquired',  'assets', 'acquisition_date', ValueType::Date),
                new FieldDefinition('maintenance_date','Maintenance Due','assets','maintenance_date', ValueType::Date),
                new FieldDefinition('terminal_date','Disposed',     'assets', 'terminal_date',    ValueType::Date),
            ],
        );
    }

    /**
     * Transactions, plus what a vendor calls the thing being bought.
     *
     * ── Why this exists as its own dataset ───────────────────────────────────
     * A purchase order often has to show the SUPPLIER's part number rather than
     * ours, and `inventory_purchase_details` is where that lives. It is not in
     * `transactions` and deliberately stays out: that dataset is behind most
     * documents, its join is one-to-many on (sku, vendor_id), and those columns are
     * not unique, so adding it there would put an extra row into unrelated reports
     * to serve one form.
     *
     * DatasetResolver picks the NARROWEST dataset that covers a definition's
     * tables, so every existing report keeps resolving to `transactions` and
     * only a definition that names `inventory_purchase_details` lands here.
     *
     * ── The customisation this replaces (§1.1, §8.2) ─────────────────────────
     * A customised Purchase Order may declare the extra table with a
     * hand-written `<relationship>` and print a column whose FIELD NAME is
     *
     *     IF(inventory_purchase_details.vendor_sku <> '',
     *        inventory_purchase_details.vendor_sku,
     *        SUBSTRING_INDEX(inventory_purchase_details.description_purchase, ' ', 1))
     *
     * — a report author writing SQL, which is the whole of §1.1 and the reason
     * Forms exists, and which Forms will not execute. Naming the expression here
     * as a shipped field, with the legacy string in `legacyNames`, converts the
     * customisation instead of running it: the same document prints, and the
     * arithmetic is the catalog's rather than the report's (§8.2).
     */
    public static function purchasing(): Dataset
    {
        $base = self::transactions();

        return new Dataset(
            id:        'purchasing',
            label:     'Purchasing',
            baseTable: $base->baseTable,
            description: 'Transactions and their line items, with the vendor\'s own part number '
                       . 'and purchase description for each line.',
            joins: [
                ...$base->joins,
                // Both conditions matter. On sku alone every vendor's record for
                // that part would match; on vendor alone every part that vendor
                // sells would. LEFT so a line with no purchase record still
                // prints — an INNER join here would silently drop lines from the
                // purchase order, which is the worst outcome available.
                new Join('inventory_purchase_details', [
                    new JoinCondition('journal_item', 'sku', 'inventory_purchase_details', 'sku'),
                    new JoinCondition('journal_main', 'bill_acct_id', 'inventory_purchase_details', 'vendor_id'),
                ], JoinType::Left),
            ],
            fields: [
                ...array_values($base->fields),

                // Grain, not provenance: `table` on a computed field states
                // whether the value varies per line or per record, and the
                // consumer folds rows on exactly that. These vary per LINE —
                // each line has its own sku — so they name journal_item, which
                // is the table QueryDocumentProvider treats as line-grain.
                // Naming inventory_purchase_details here would make them header
                // fields and every line would show the first line's vendor SKU.
                new FieldDefinition(
                    key: 'vendor_sku', label: 'Vendor SKU',
                    table: 'journal_item', type: ValueType::Text,
                    expression: "IF(inventory_purchase_details.vendor_sku <> '',"
                              . " inventory_purchase_details.vendor_sku,"
                              . " SUBSTRING_INDEX(inventory_purchase_details.description_purchase, ' ', 1))",
                    // The stored form names the whole expression as its field.
                    // Matching is whitespace-insensitive, so the two need not
                    // agree on spacing — only on the text.
                    legacyNames: [
                        "IF(inventory_purchase_details.vendor_sku <> '',"
                        . " inventory_purchase_details.vendor_sku,"
                        . " SUBSTRING_INDEX(inventory_purchase_details.description_purchase, ' ', 1))",
                    ],
                ),
                new FieldDefinition(
                    key: 'vendor_sku_only', label: 'Vendor SKU (blank if unset)',
                    table: 'journal_item', type: ValueType::Text,
                    expression: 'inventory_purchase_details.vendor_sku',
                    legacyNames: ['inventory_purchase_details.vendor_sku'],
                ),
                new FieldDefinition(
                    key: 'vendor_description', label: 'Vendor Description',
                    table: 'journal_item', type: ValueType::Text,
                    expression: 'inventory_purchase_details.description_purchase',
                    legacyNames: ['inventory_purchase_details.description_purchase'],
                ),
            ],
            // The aging fields come across with the transaction fields, and they
            // are the reason this cannot be a bare field list: their expressions
            // read {{param:...}} placeholders that only these values satisfy.
            params: $base->params,
        );
    }
}
