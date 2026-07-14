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
//  Path: /modules/api/classes/resources/invoices.php
//
// Sales Invoices resource (journal_id = 12).
//
// 'lines' is a virtual field resolved by post_process(): it batch-loads
// all product lines for the current page of invoices in a single query
// (no N+1).  Only gl_type IN ('sos','por') rows are returned — the
// tax/freight/discount/total summary rows are excluded.
//
// Note on 'modified': journal_main has no row-modified timestamp, so
// 'modified' maps to post_date (the transaction/invoice date).  This
// is documented in the README.  A date-range filter on 'date' (which
// also maps to post_date) achieves the same effect for incremental sync.
//

class api_resource_invoices {

  public string $primary_key = 'id';

  // -----------------------------------------------------------------------
  // Field map
  // -----------------------------------------------------------------------
  public function field_map(): array {
    return [
      // ── journal_main columns ────────────────────────────────────────────
      'id'              => ['col' => 'm.id',                   'type' => 'int',    'filterable' => true,  'sortable' => true],
      'invoice_number'  => ['col' => 'm.purchase_invoice_id',  'type' => 'string', 'filterable' => true,  'sortable' => true],
      'reference'       => ['col' => 'm.purch_order_id',       'type' => 'string', 'filterable' => true,  'sortable' => false],
      'date'            => ['col' => 'm.post_date',            'type' => 'date',   'filterable' => true,  'sortable' => true],
      'customer_id'     => ['col' => 'm.bill_acct_id',         'type' => 'int',    'filterable' => true,  'sortable' => false],
      'customer_name'   => ['col' => 'm.bill_primary_name',    'type' => 'string', 'filterable' => true,  'sortable' => true],
      'customer_contact'=> ['col' => 'm.bill_contact',         'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_address1'   => ['col' => 'm.bill_address1',        'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_address2'   => ['col' => 'm.bill_address2',        'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_city'       => ['col' => 'm.bill_city_town',       'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_state'      => ['col' => 'm.bill_state_province',  'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_postal_code'=> ['col' => 'm.bill_postal_code',     'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_country'    => ['col' => 'm.bill_country_code',    'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_email'      => ['col' => 'm.bill_email',           'type' => 'string', 'filterable' => false, 'sortable' => false],
      'bill_telephone'  => ['col' => 'm.bill_telephone1',      'type' => 'string', 'filterable' => false, 'sortable' => false],
      'total'           => ['col' => 'm.total_amount',         'type' => 'float',  'filterable' => true,  'sortable' => true],
      'tax'             => ['col' => 'm.sales_tax',            'type' => 'float',  'filterable' => true,  'sortable' => false],
      'freight'         => ['col' => 'm.freight',              'type' => 'float',  'filterable' => false, 'sortable' => false],
      'discount'        => ['col' => 'm.discount',             'type' => 'float',  'filterable' => false, 'sortable' => false],
      'currency'        => ['col' => 'm.currencies_code',      'type' => 'string', 'filterable' => true,  'sortable' => false],
      'currency_rate'   => ['col' => 'm.currencies_value',     'type' => 'float',  'filterable' => false, 'sortable' => false],
      'closed'          => ['col' => 'm.closed',               'type' => 'string', 'filterable' => true,  'sortable' => false],
      'terms'           => ['col' => 'm.terms',                'type' => 'string', 'filterable' => false, 'sortable' => false],
      'shipper_code'    => ['col' => 'm.shipper_code',         'type' => 'string', 'filterable' => false, 'sortable' => false],
      'rep_id'          => ['col' => 'm.rep_id',               'type' => 'int',    'filterable' => false, 'sortable' => false],
      'notes'           => ['col' => 'm.notes',                'type' => 'string', 'filterable' => false, 'sortable' => false],
      'modified'        => ['col' => 'm.post_date',            'type' => 'date',   'filterable' => true,  'sortable' => true],
      // ── virtual: line items loaded by post_process() ────────────────────
      'lines'           => ['col' => '',  'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
    ];
  }

  // -----------------------------------------------------------------------
  // FROM clause — journal_main only (items loaded separately in post_process)
  // -----------------------------------------------------------------------
  public function base_from(): string {
    return TABLE_JOURNAL_MAIN . ' m';
  }

  // -----------------------------------------------------------------------
  // Base filter: only Sales Invoices (journal_id = 12)
  // -----------------------------------------------------------------------
  public function base_where(): string {
    return 'm.journal_id = 12';
  }

  // -----------------------------------------------------------------------
  // Default fields for an invoice dump
  // -----------------------------------------------------------------------
  public function default_fields(): array {
    return [
      'id', 'invoice_number', 'reference', 'date', 'customer_id',
      'customer_name', 'total', 'tax', 'freight', 'currency', 'closed',
    ];
  }

  // -----------------------------------------------------------------------
  // Post-process: batch-load invoice lines for all rows in the current page.
  // One query regardless of page size — no N+1.
  // -----------------------------------------------------------------------
  public function post_process(array $rows, array $virtual_fields): array {
    global $db;

    if (!in_array('lines', $virtual_fields) || empty($rows)) return $rows;

    // Collect invoice ids present on this page
    $ids = array_map('intval', array_column($rows, 'id'));
    $id_list = implode(',', $ids);

    // Load product lines only (gl_type sos = sales order line, por = purchase order line)
    $sql = "SELECT ref_id, sku, description, qty, credit_amount, debit_amount,
                   gl_account, taxable, full_price
            FROM " . TABLE_JOURNAL_ITEM . "
            WHERE ref_id IN ($id_list)
              AND gl_type IN ('sos','por')
            ORDER BY ref_id, item_cnt";

    $result = $db->Execute($sql);
    $lines_by_id = [];
    while (!$result->EOF) {
      $f   = $result->fields;
      $rid = (int)$f['ref_id'];
      $qty = (float)$f['qty'];
      // Extended amount (one of debit/credit is zero; sum gives the non-zero value)
      $amount     = (float)$f['credit_amount'] + (float)$f['debit_amount'];
      $unit_price = ($qty != 0) ? round($amount / $qty, 6) : 0;
      $lines_by_id[$rid][] = [
        'sku'         => $f['sku'],
        'description' => $f['description'],
        'qty'         => $qty,
        'unit_price'  => $unit_price,
        'amount'      => $amount,
        'gl_account'  => $f['gl_account'],
        'taxable'     => (int)$f['taxable'],
      ];
      $result->MoveNext();
    }

    // Attach lines to each invoice row
    foreach ($rows as &$row) {
      $row['lines'] = $lines_by_id[(int)$row['id']] ?? [];
    }
    unset($row);

    return $rows;
  }
}
