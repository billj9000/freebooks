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
//  Path: /modules/api/classes/resources/credit_memos.php
//
// Customer Credit Memos resource (journal_id = 13).
//
// Field layout mirrors api_resource_invoices — same journal_main /
// journal_item schema, same 'sos'/'por' line gl_types. The one schema
// difference is that credit memo line item qty is stored positive
// (invoices negate qty for inventory draw-down); this does not affect
// amount fields, which use debit_amount/credit_amount exactly as
// invoices do.
//
// 'lines' is a virtual field resolved by post_process(): it batch-loads
// all product lines for the current page of credit memos in a single
// query (no N+1). Only gl_type IN ('sos','por') rows are returned — the
// tax/freight/discount/total summary rows are excluded.
//
// Each line's 'taxable' is a tax_rate_id (0 = none), not a percentage —
// 'tax_rate' resolves it to the actual percentage FreeBooks would use to
// calculate tax (tax_rates.rate_accounts summed against tax_authorities.tax_rate,
// mirroring gen_calculate_tax_rate() in modules/phreebooks/functions/phreebooks.php),
// so callers don't need to duplicate that lookup or hardcode rates that could
// drift out of sync.
//
// 'freight_gl_account' and 'discount_gl_account' are likewise resolved by
// post_process(): freight and discount are each posted as their own
// journal_item row (gl_type 'frt' / 'dsc', set per-order from
// ship_gl_acct_id / disc_gl_acct_id in orders.php) excluded from 'lines'
// above, so their GL accounts need their own lookup rather than being a
// plain journal_main column.
//
// 'freight_taxable' and 'freight_tax_rate' are resolved from that same 'frt'
// row's own 'taxable' column, NOT from the journal_main.freight_tax_id column
// exposed above. freight_tax_id is the raw per-order override typed on the
// order form, which is commonly left at 0 ("use the company default,"
// AR_ADD_SALES_TAX_TO_SHIPPING); the *effective* tax_rate_id FreeBooks
// actually applied — mirroring a line item's 'taxable' — is only recorded on
// the posted 'frt' journal_item row itself (orders.php
// add_freight_journal_row()). Using freight_tax_id directly would silently
// under- or over-state freight's tax whenever it falls back to the company
// default instead of an explicit override.
//
// Note on 'modified': journal_main has no row-modified timestamp, so
// 'modified' maps to post_date (the transaction/credit memo date).
// A date-range filter on 'date' (which also maps to post_date) achieves
// the same effect for incremental sync.
//

class api_resource_credit_memos {

  public string $primary_key = 'id';

  // -----------------------------------------------------------------------
  // Field map
  // -----------------------------------------------------------------------
  public function field_map(): array {
    return [
      // ── journal_main columns ────────────────────────────────────────────
      'id'              => ['col' => 'm.id',                   'type' => 'int',    'filterable' => true,  'sortable' => true],
      'credit_memo_number' => ['col' => 'm.purchase_invoice_id', 'type' => 'string', 'filterable' => true,  'sortable' => true],
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
      'ship_name'       => ['col' => 'm.ship_primary_name',    'type' => 'string', 'filterable' => false, 'sortable' => false],
      'ship_address1'   => ['col' => 'm.ship_address1',        'type' => 'string', 'filterable' => false, 'sortable' => false],
      'ship_address2'   => ['col' => 'm.ship_address2',        'type' => 'string', 'filterable' => false, 'sortable' => false],
      'ship_city'       => ['col' => 'm.ship_city_town',       'type' => 'string', 'filterable' => false, 'sortable' => false],
      'ship_state'      => ['col' => 'm.ship_state_province',  'type' => 'string', 'filterable' => false, 'sortable' => false],
      'ship_postal_code'=> ['col' => 'm.ship_postal_code',     'type' => 'string', 'filterable' => false, 'sortable' => false],
      'ship_country'    => ['col' => 'm.ship_country_code',    'type' => 'string', 'filterable' => false, 'sortable' => false],
      'total'           => ['col' => 'm.total_amount',         'type' => 'float',  'filterable' => true,  'sortable' => true],
      'tax'             => ['col' => 'm.sales_tax',            'type' => 'float',  'filterable' => true,  'sortable' => false],
      'freight'         => ['col' => 'm.freight',              'type' => 'float',  'filterable' => false, 'sortable' => false],
      'freight_tax_id'  => ['col' => 'm.freight_tax_id',       'type' => 'int',    'filterable' => false, 'sortable' => false],
      'discount'        => ['col' => 'm.discount',             'type' => 'float',  'filterable' => false, 'sortable' => false],
      'currency'        => ['col' => 'm.currencies_code',      'type' => 'string', 'filterable' => true,  'sortable' => false],
      'currency_rate'   => ['col' => 'm.currencies_value',     'type' => 'float',  'filterable' => false, 'sortable' => false],
      'closed'          => ['col' => 'm.closed',               'type' => 'string', 'filterable' => true,  'sortable' => false],
      'terms'           => ['col' => 'm.terms',                'type' => 'string', 'filterable' => false, 'sortable' => false],
      'shipper_code'    => ['col' => 'm.shipper_code',         'type' => 'string', 'filterable' => false, 'sortable' => false],
      'rep_id'          => ['col' => 'm.rep_id',               'type' => 'int',    'filterable' => false, 'sortable' => false],
      'notes'           => ['col' => 'm.notes',                'type' => 'string', 'filterable' => false, 'sortable' => false],
      'modified'        => ['col' => 'm.post_date',            'type' => 'date',   'filterable' => true,  'sortable' => true],
      // ── virtual: resolved by post_process() ──────────────────────────────
      'lines'               => ['col' => '',  'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
      'freight_taxable'     => ['col' => '',  'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
      'freight_tax_rate'    => ['col' => '',  'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
      'freight_gl_account'  => ['col' => '',  'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
      'discount_gl_account' => ['col' => '',  'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
    ];
  }

  // -----------------------------------------------------------------------
  // FROM clause — journal_main only (items loaded separately in post_process)
  // -----------------------------------------------------------------------
  public function base_from(): string {
    return TABLE_JOURNAL_MAIN . ' m';
  }

  // -----------------------------------------------------------------------
  // Base filter: only Customer Credit Memos (journal_id = 13)
  // -----------------------------------------------------------------------
  public function base_where(): string {
    return 'm.journal_id = 13';
  }

  // -----------------------------------------------------------------------
  // Default fields for a credit memo dump
  // -----------------------------------------------------------------------
  public function default_fields(): array {
    return [
      'id', 'credit_memo_number', 'reference', 'date', 'customer_id',
      'customer_name', 'total', 'tax', 'freight', 'currency', 'closed',
    ];
  }

  // -----------------------------------------------------------------------
  // Post-process: batch-load credit memo lines and/or resolve tax percentages
  // for all rows in the current page. One query per concern regardless of
  // page size — no N+1.
  // -----------------------------------------------------------------------
  public function post_process(array $rows, array $virtual_fields): array {
    global $db;

    if (empty($rows)) return $rows;

    $needs_lines               = in_array('lines', $virtual_fields);
    $needs_freight_taxable     = in_array('freight_taxable', $virtual_fields);
    $needs_freight_tax_rate    = in_array('freight_tax_rate', $virtual_fields);
    $needs_freight_gl_account  = in_array('freight_gl_account', $virtual_fields);
    $needs_discount_gl_account = in_array('discount_gl_account', $virtual_fields);
    $needs_frt_row = $needs_freight_taxable || $needs_freight_tax_rate || $needs_freight_gl_account;
    if (!$needs_lines && !$needs_frt_row && !$needs_discount_gl_account) {
      return $rows;
    }

    $tax_rate_lookup = $this->load_tax_rate_lookup($db);

    if ($needs_lines || $needs_frt_row || $needs_discount_gl_account) {
      // Collect credit memo ids present on this page
      $ids = array_map('intval', array_column($rows, 'id'));
      $id_list = implode(',', $ids);
    }

    if ($needs_lines) {
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
        $taxable    = (int)$f['taxable'];
        $lines_by_id[$rid][] = [
          'sku'         => $f['sku'],
          'description' => $f['description'],
          'qty'         => $qty,
          'unit_price'  => $unit_price,
          'amount'      => $amount,
          'gl_account'  => $f['gl_account'],
          'taxable'     => $taxable,
          'tax_rate'    => $tax_rate_lookup[$taxable] ?? 0.0,
        ];
        $result->MoveNext();
      }

      // Attach lines to each credit memo row
      foreach ($rows as &$row) {
        $row['lines'] = $lines_by_id[(int)$row['id']] ?? [];
      }
      unset($row);
    }

    if ($needs_frt_row || $needs_discount_gl_account) {
      // Freight and discount are each a single row per credit memo (gl_type
      // 'frt' / 'dsc'), same ids as above — one query covers both, including
      // the 'frt' row's own 'taxable' for freight_taxable/freight_tax_rate
      // (see class-level comment on why journal_main.freight_tax_id isn't
      // used for this).
      $gl_types = [];
      if ($needs_frt_row)              $gl_types[] = "'frt'";
      if ($needs_discount_gl_account)  $gl_types[] = "'dsc'";
      $sql = "SELECT ref_id, gl_type, gl_account, taxable
              FROM " . TABLE_JOURNAL_ITEM . "
              WHERE ref_id IN ($id_list)
                AND gl_type IN (" . implode(',', $gl_types) . ")";
      $result = $db->Execute($sql);
      $freight_gl_by_id      = [];
      $freight_taxable_by_id = [];
      $discount_gl_by_id     = [];
      while (!$result->EOF) {
        $rid = (int)$result->fields['ref_id'];
        if ($result->fields['gl_type'] === 'frt') {
          $freight_gl_by_id[$rid]      = $result->fields['gl_account'];
          $freight_taxable_by_id[$rid] = (int)$result->fields['taxable'];
        } else {
          $discount_gl_by_id[$rid] = $result->fields['gl_account'];
        }
        $result->MoveNext();
      }
      foreach ($rows as &$row) {
        if ($needs_freight_gl_account) $row['freight_gl_account'] = $freight_gl_by_id[(int)$row['id']] ?? '';
        if ($needs_freight_taxable || $needs_freight_tax_rate) {
          $freight_taxable = $freight_taxable_by_id[(int)$row['id']] ?? 0;
          if ($needs_freight_taxable)  $row['freight_taxable']  = $freight_taxable;
          if ($needs_freight_tax_rate) $row['freight_tax_rate'] = $tax_rate_lookup[$freight_taxable] ?? 0.0;
        }
        if ($needs_discount_gl_account) $row['discount_gl_account'] = $discount_gl_by_id[(int)$row['id']] ?? '';
      }
      unset($row);
    }

    return $rows;
  }

  // -----------------------------------------------------------------------
  // Builds a tax_rate_id -> percentage lookup, mirroring gen_calculate_tax_rate()
  // in modules/phreebooks/functions/phreebooks.php: each tax_rates row's
  // rate_accounts is a colon-separated list of tax_authorities.tax_auth_id,
  // summed to get the effective rate. Both tables are tiny, so loading them
  // in full is simpler and cheap compared to per-id lookups.
  // -----------------------------------------------------------------------
  private function load_tax_rate_lookup($db): array {
    $auth_rates = [];
    $auth_result = $db->Execute("SELECT tax_auth_id, tax_rate FROM " . TABLE_TAX_AUTH);
    while (!$auth_result->EOF) {
      $auth_rates[(int)$auth_result->fields['tax_auth_id']] = (float)$auth_result->fields['tax_rate'];
      $auth_result->MoveNext();
    }

    $lookup = [0 => 0.0];
    $rate_result = $db->Execute("SELECT tax_rate_id, rate_accounts FROM " . TABLE_TAX_RATES);
    while (!$rate_result->EOF) {
      $id   = (int)$rate_result->fields['tax_rate_id'];
      $rate = 0.0;
      foreach (explode(':', (string)$rate_result->fields['rate_accounts']) as $auth_id) {
        $rate += $auth_rates[(int)$auth_id] ?? 0.0;
      }
      $lookup[$id] = $rate;
      $rate_result->MoveNext();
    }
    return $lookup;
  }
}
