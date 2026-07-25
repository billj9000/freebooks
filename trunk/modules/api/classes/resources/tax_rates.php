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
//  Path: /modules/api/classes/resources/tax_rates.php
//
// Tax Rates resource — read-only lookup of the tax_rate_ids referenced by
// invoices/credit_memos' 'taxable' / 'freight_taxable' fields (see
// resources/invoices.php). FreeBooks' own admin UI (Company Setup > Tax
// Rates) never displays this numeric id to the user — it only appears
// inside a hidden onclick attribute on the edit icon — so external
// integrations have no other way to discover which id corresponds to which
// real-world tax without this resource.
//
// 'rate' is a virtual field: tax_rates.rate_accounts is a colon-separated
// list of tax_authorities.tax_auth_id, summed to get the effective
// percentage, mirroring gen_calculate_tax_rate() in
// modules/phreebooks/functions/phreebooks.php (same calculation used by
// invoices.php / credit_memos.php's 'tax_rate' / 'freight_tax_rate').
//
// 'type' is 'c' (customer/sales) or 'v' (vendor/purchase) — only 'c' rows
// are relevant to the invoices/credit_memos resources.
//

class api_resource_tax_rates {

  public string $primary_key = 'id';

  // -----------------------------------------------------------------------
  // Field map
  // -----------------------------------------------------------------------
  public function field_map(): array {
    return [
      'id'                => ['col' => 't.tax_rate_id',       'type' => 'int',    'filterable' => true,  'sortable' => true],
      'type'              => ['col' => 't.type',               'type' => 'string', 'filterable' => true,  'sortable' => false],
      'description_short' => ['col' => 't.description_short',  'type' => 'string', 'filterable' => false, 'sortable' => true],
      'description_long'  => ['col' => 't.description_long',   'type' => 'string', 'filterable' => false, 'sortable' => false],
      'freight_taxable'   => ['col' => 't.freight_taxable',     'type' => 'string', 'filterable' => false, 'sortable' => false],
      // Internal only — feeds the 'rate' virtual field, not meant to be
      // requested directly, but left in field_map so it can be selected as
      // a virtual_field_dependencies() column.
      'rate_accounts'     => ['col' => 't.rate_accounts',       'type' => 'string', 'filterable' => false, 'sortable' => false],
      // ── virtual: resolved by post_process() ──────────────────────────────
      'rate'              => ['col' => '', 'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
    ];
  }

  // -----------------------------------------------------------------------
  // Raw columns a virtual field needs that the caller might not have
  // requested (see api_query::run()).
  // -----------------------------------------------------------------------
  public function virtual_field_dependencies(): array {
    return [
      'rate' => ['rate_accounts'],
    ];
  }

  // -----------------------------------------------------------------------
  // FROM clause
  // -----------------------------------------------------------------------
  public function base_from(): string {
    return TABLE_TAX_RATES . ' t';
  }

  // -----------------------------------------------------------------------
  // No base filter — callers typically filter by type = 'c' themselves.
  // -----------------------------------------------------------------------
  public function base_where(): string {
    return '';
  }

  // -----------------------------------------------------------------------
  // Default fields
  // -----------------------------------------------------------------------
  public function default_fields(): array {
    return ['id', 'type', 'description_short', 'description_long', 'rate', 'freight_taxable'];
  }

  // -----------------------------------------------------------------------
  // Post-process: resolve each row's 'rate' from its own rate_accounts.
  // -----------------------------------------------------------------------
  public function post_process(array $rows, array $virtual_fields): array {
    global $db;

    if (!in_array('rate', $virtual_fields) || empty($rows)) return $rows;

    $auth_rates = [];
    $auth_result = $db->Execute("SELECT tax_auth_id, tax_rate FROM " . TABLE_TAX_AUTH);
    while (!$auth_result->EOF) {
      $auth_rates[(int)$auth_result->fields['tax_auth_id']] = (float)$auth_result->fields['tax_rate'];
      $auth_result->MoveNext();
    }

    foreach ($rows as &$row) {
      $rate = 0.0;
      foreach (explode(':', (string)($row['rate_accounts'] ?? '')) as $auth_id) {
        $rate += $auth_rates[(int)$auth_id] ?? 0.0;
      }
      $row['rate'] = $rate;
    }
    unset($row);

    return $rows;
  }
}
