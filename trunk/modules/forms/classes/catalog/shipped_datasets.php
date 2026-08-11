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
 * The shape comes from §6.1, which found the whole shipped library forms only
 * five connected components and that no report spans more than one. The largest
 * (44 of 56 documents) is split into Transactions and General Ledger, because a
 * dataset is a curated view chosen for usability rather than the maximal graph.
 *
 * The field lists are not exhaustive schema dumps. They cover what the shipped
 * definitions actually reference (§6.2 counted 117 distinct table.column uses)
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
        $catalog->register(self::generalLedger());
        $catalog->register(self::inventory());
        $catalog->register(self::auditTrail());
    }

    /**
     * Orders, invoices, receipts and their line items, with the contact and
     * address they belong to — the dataset behind most of the library.
     *
     * `journal_main` -> `journal_item` is one-to-many, so a query over this
     * dataset returns one row per LINE, and a form groups them by its break
     * field. That is the same shape PhreeForm produced.
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
                // and pulling it into the dataset behind 44 documents would
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
                new FieldDefinition('item_date_1',      'Item Date',        'journal_item', 'date_1',        ValueType::Date),

                // A computed field: the per-unit price the line worked out to.
                // PhreeForm wrote `journal_item.debit_amount /journal_item.qty`
                // straight into a report's field list. Here the arithmetic is
                // named, typed and defined once, in code — which is the whole
                // point of the shipped tier.
                new FieldDefinition(
                    key:        'item_unit_price',
                    label:      'Unit Price',
                    table:      'journal_item',      // grain: one per line
                    type:       ValueType::Money,
                    expression: '(CASE WHEN journal_item.qty = 0 THEN 0 ELSE journal_item.debit_amount / journal_item.qty END)',
                    sortable:   true,
                    // What the shipped definitions wrote inline. The guard
                    // against division by zero is new: PhreeForm's raw
                    // expression divides regardless.
                    legacyNames: ['journal_item.debit_amount / journal_item.qty'],
                ),

                // Net movement on the line. The sales reports total this, and
                // wrote the subtraction inline; naming it once means the reports
                // agree on what "net" means.
                new FieldDefinition(
                    key:        'item_net',
                    label:      'Net Amount',
                    table:      'journal_item',
                    type:       ValueType::Money,
                    expression: '(journal_item.credit_amount - journal_item.debit_amount)',
                    legacyNames: ['journal_item.credit_amount - journal_item.debit_amount'],
                ),

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
            ],
        );
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

                new FieldDefinition('posting_id',      'Posting ID',    'journal_item', 'id',            ValueType::Integer),
                new FieldDefinition('posting_debit',   'Debit',         'journal_item', 'debit_amount',  ValueType::Money),
                new FieldDefinition('posting_credit',  'Credit',        'journal_item', 'credit_amount', ValueType::Money),
                new FieldDefinition('posting_desc',    'Posting Text',  'journal_item', 'description'),
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
                new FieldDefinition('item_cost',        'Item Cost',        'inventory', 'item_cost',        ValueType::Money),
                new FieldDefinition('full_price',       'Full Price',       'inventory', 'full_price',       ValueType::Money),
                new FieldDefinition('inactive',         'Inactive',         'inventory', 'inactive',         ValueType::Boolean),

                new FieldDefinition('history_id',       'History ID',       'inventory_history', 'id',        ValueType::Integer),
                new FieldDefinition('history_date',     'Movement Date',    'inventory_history', 'post_date', ValueType::Date),
                new FieldDefinition('history_qty',      'Movement Qty',     'inventory_history', 'qty',       ValueType::Decimal),
                new FieldDefinition('history_remaining','Remaining',        'inventory_history', 'remaining', ValueType::Decimal),
                new FieldDefinition('history_unit_cost','Unit Cost',        'inventory_history', 'unit_cost', ValueType::Money),

                // §6.2 counted nine computed expressions across the library,
                // all simple arithmetic; this is the one the stock valuation
                // reports use.
                new FieldDefinition(
                    key:        'history_value',
                    label:      'Remaining Value',
                    table:      'inventory_history', // grain: one per movement
                    type:       ValueType::Money,
                    expression: '(inventory_history.unit_cost * inventory_history.remaining)',
                    legacyNames: ['inventory_history.unit_cost * inventory_history.remaining'],
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
}
