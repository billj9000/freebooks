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
//  Path: /modules/zencart/updates/PhreeBooks371toFreeBooks100.php
//
// The Zen Cart module's tables and settings, from PhreeBooks 3.7.1 to FreeBooks
// 1.0.0.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.
//
// update() sets $tab_id, the Zen Cart tab of the inventory page, and $error
// before including this.

// ---- zc_ship_notify --------------------------------------------------------
// The shipments whose notification has been sent to Zen Cart, so none is sent
// twice.
$db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_ZC_SHIP_NOTIFY . " ("
    . "shipping_log_id INT NOT NULL PRIMARY KEY,"
    . "notified_at DATETIME NOT NULL"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ---- settings --------------------------------------------------------------
// The master switch for all Zen Cart traffic, off until an administrator turns
// it on; see functions/zencart.php and the cron job.
if (!defined('ZENCART_ENABLED')) write_configure('ZENCART_ENABLED', '0');

// The VAT invoice email, off by default; see zencart_email_vat_invoice().
if (!defined('ZENCART_VAT_INVOICE'))         write_configure('ZENCART_VAT_INVOICE', '0');
if (!defined('ZENCART_VAT_INVOICE_SUBJECT')) write_configure('ZENCART_VAT_INVOICE_SUBJECT', 'VAT Invoice {invoice}');
if (!defined('ZENCART_VAT_INVOICE_MESSAGE')) write_configure('ZENCART_VAT_INVOICE_MESSAGE',
  'Please find attached the VAT invoice {invoice} for your order, {name}. Thank you for your business.');
if (!defined('ZENCART_VAT_INVOICE_BCC'))     write_configure('ZENCART_VAT_INVOICE_BCC', '');

// The stock VAT invoice form is chosen for an empty setting, and a chosen one is
// never replaced. With no such form the setting stays empty rather than '0', so
// a later update can still pick one up.
if (!defined('ZENCART_VAT_INVOICE_FORM') || ZENCART_VAT_INVOICE_FORM === '') {
  if (!defined('TABLE_PHREEFORM')) define('TABLE_PHREEFORM', DB_PREFIX . 'phreeform');
  if (db_table_exists(TABLE_PHREEFORM)) {
    $frm = $db->Execute("SELECT id FROM " . TABLE_PHREEFORM
      . " WHERE doc_group = 'cust:inv' AND doc_ext = 'frm' AND doc_title = 'Invoice-VAT' LIMIT 1");
    if (!$frm->EOF) write_configure('ZENCART_VAT_INVOICE_FORM', (int)$frm->fields['id']);
  }
}

// ---- inventory: the web long description -----------------------------------
// An HTML field on the Zen Cart tab of the inventory page.
if ($error == false && !db_field_exists(TABLE_INVENTORY, 'zencart_description')) {
  $sql_data_array = array(
    'module_id'   => 'inventory',
    'tab_id'      => $tab_id,
    'entry_type'  => 'html',
    'field_name'  => 'zencart_description',
    'description' => ZENCART_CATALOG_DESCRIPTION,
    'sort_order'  => 60,
    'params'      => serialize(array('type'=>'html', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:pb')),
  );
  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
  $db->Execute("alter table " . TABLE_INVENTORY . " add column zencart_description mediumtext");
}
