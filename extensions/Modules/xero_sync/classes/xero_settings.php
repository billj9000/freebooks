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
//  Path: /modules/xero_sync/classes/xero_settings.php
//
// Reads and writes the module's settings and its FreeBooks-to-Xero mapping, and gives them to
// xero_builder in the shape it takes.
//

class xero_settings {

  const COUNTRY_SOURCES = ['shipping', 'billing'];

  // The options build_document() takes, from the configuration.
  public static function options(): array {
    return [
      'due_days'              => (int)(defined('XERO_SYNC_DUE_DAYS') ? XERO_SYNC_DUE_DAYS : 30),
      'rounding_tolerance'    => (float)(defined('XERO_SYNC_ROUNDING_TOLERANCE') ? XERO_SYNC_ROUNDING_TOLERANCE : 0.01),
      'home_country'          => defined('XERO_SYNC_HOME_COUNTRY') ? XERO_SYNC_HOME_COUNTRY : '',
      'country_source'        => defined('XERO_SYNC_COUNTRY_SOURCE') && XERO_SYNC_COUNTRY_SOURCE === 'billing' ? 'billing' : 'shipping',
      'use_payment_reference' => defined('XERO_SYNC_USE_PAYMENT_REF') && XERO_SYNC_USE_PAYMENT_REF === '1',
    ];
  }

  // The mapping array xero_builder takes. An account with no foreign code is a plain entry, used
  // for domestic and foreign documents alike.
  public static function mapping(): array {
    $mapping = [
      'accounts'         => [],
      'default_account'  => defined('XERO_SYNC_DEFAULT_ACCOUNT')  ? XERO_SYNC_DEFAULT_ACCOUNT  : '',
      'rounding_account' => defined('XERO_SYNC_ROUNDING_ACCOUNT') ? XERO_SYNC_ROUNDING_ACCOUNT : '',
      'contacts'         => [],
      'tax_types'        => [],
      'no_vat_type'      => defined('XERO_SYNC_NO_VAT_TYPE') ? XERO_SYNC_NO_VAT_TYPE : '',
    ];
    foreach (self::rows() as $row) {
      switch ($row['map_type']) {
        case 'account':
          $mapping['accounts'][$row['fb_key']] = $row['xero_foreign'] === ''
            ? $row['xero_value']
            : ['domestic' => $row['xero_value'], 'foreign' => $row['xero_foreign']];
          break;
        case 'tax':     $mapping['tax_types'][$row['fb_key']] = $row['xero_value']; break;
        case 'contact': $mapping['contacts'][$row['fb_key']]  = $row['xero_value']; break;
      }
    }
    return $mapping;
  }

  // Every mapping row, or those of one type, keyed by FreeBooks value when a type is given.
  public static function rows(string $map_type = ''): array {
    global $db;
    $sql = "SELECT map_type, fb_key, xero_value, xero_foreign FROM " . TABLE_XERO_SYNC_MAP;
    if ($map_type !== '') $sql .= " WHERE map_type = '" . db_input($map_type) . "'";
    $result = $db->Execute($sql . " ORDER BY map_type, fb_key");
    $rows = [];
    while (!$result->EOF) {
      if ($map_type !== '') $rows[$result->fields['fb_key']] = $result->fields;
      else $rows[] = $result->fields;
      $result->MoveNext();
    }
    return $rows;
  }

  // Replaces every row of one type. $entries is FreeBooks value => [xero_value, xero_foreign]; an
  // entry with both values blank is left out, so clearing a row removes its mapping.
  public static function save_rows(string $map_type, array $entries): void {
    global $db;
    $db->transStart();
    $db->Execute("DELETE FROM " . TABLE_XERO_SYNC_MAP . " WHERE map_type = '" . db_input($map_type) . "'");
    foreach ($entries as $fb_key => [$xero_value, $xero_foreign]) {
      $fb_key = trim((string)$fb_key);
      $xero_value = trim((string)$xero_value);
      $xero_foreign = trim((string)$xero_foreign);
      if ($fb_key === '' || ($xero_value === '' && $xero_foreign === '')) continue;
      db_perform(TABLE_XERO_SYNC_MAP, [
        'map_type'     => $map_type,
        'fb_key'       => mb_substr($fb_key, 0, 255),
        'xero_value'   => mb_substr($xero_value, 0, 255),
        'xero_foreign' => mb_substr($xero_foreign, 0, 255),
      ]);
    }
    $db->transCommit();
  }

  // The GL accounts the mapping table lists: those that sales invoices and credit memos have posted
  // product, freight or discount lines to, plus any already mapped. With $all, every active account.
  // A mapped account is always listed, even when inactive, because saving the page replaces every
  // account row with the ones it shows.
  public static function sales_accounts(bool $all = false): array {
    global $db;
    $mapped = self::rows('account');
    $where = "heading_only = '0' AND account_inactive = '0'";
    if (!$all) {
      $where .= " AND id IN (SELECT DISTINCT i.gl_account FROM " . TABLE_JOURNAL_ITEM . " i
                 JOIN " . TABLE_JOURNAL_MAIN . " m ON m.id = i.ref_id
                 WHERE m.journal_id IN (12, 13) AND i.gl_type IN ('sos', 'frt', 'dsc'))";
    }
    $keys = array_map(fn($k) => "'" . db_input($k) . "'", array_keys($mapped));
    if ($keys) $where = "($where) OR id IN (" . implode(',', $keys) . ")";
    $result = $db->Execute("SELECT id, description FROM " . TABLE_CHART_OF_ACCOUNTS . " WHERE $where ORDER BY id");
    $accounts = [];
    while (!$result->EOF) {
      $id = $result->fields['id'];
      $accounts[] = [
        'id'           => $id,
        'description'  => $result->fields['description'],
        'xero_value'   => $mapped[$id]['xero_value']   ?? '',
        'xero_foreign' => $mapped[$id]['xero_foreign'] ?? '',
      ];
      unset($mapped[$id]);
      $result->MoveNext();
    }
    // A mapped account no longer in the chart can still appear on older documents.
    foreach ($mapped as $id => $row) {
      $accounts[] = ['id' => (string)$id, 'description' => '', 'xero_value' => $row['xero_value'], 'xero_foreign' => $row['xero_foreign']];
    }
    return $accounts;
  }

  // The customer tax rates, each with its percentage (the sum of its authorities' rates) and its
  // mapped Xero tax rate name.
  public static function sales_tax_rates(): array {
    global $db;
    $mapped = self::rows('tax');
    $authorities = [];
    $result = $db->Execute("SELECT tax_auth_id, tax_rate FROM " . TABLE_TAX_AUTH);
    while (!$result->EOF) {
      $authorities[(int)$result->fields['tax_auth_id']] = (float)$result->fields['tax_rate'];
      $result->MoveNext();
    }
    $rates = [];
    $result = $db->Execute("SELECT tax_rate_id, description_short, rate_accounts FROM " . TABLE_TAX_RATES . " WHERE type = 'c' ORDER BY description_short");
    while (!$result->EOF) {
      $rate = 0.0;
      foreach (explode(':', (string)$result->fields['rate_accounts']) as $auth_id) $rate += $authorities[(int)$auth_id] ?? 0.0;
      $id = (string)$result->fields['tax_rate_id'];
      $rates[] = [
        'id'          => $id,
        'description' => $result->fields['description_short'],
        'rate'        => $rate,
        'xero_value'  => $mapped[$id]['xero_value'] ?? '',
      ];
      unset($mapped[$id]);
      $result->MoveNext();
    }
    // A mapped tax rate since deleted can still appear on older documents.
    foreach ($mapped as $id => $row) {
      $rates[] = ['id' => (string)$id, 'description' => '', 'rate' => null, 'xero_value' => $row['xero_value']];
    }
    return $rates;
  }
}
