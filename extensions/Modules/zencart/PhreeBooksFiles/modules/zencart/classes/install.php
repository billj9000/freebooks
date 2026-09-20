<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/zencart/classes/install.php

class zencart_admin {
	public $notes 		  = array(); // placeholder for any operational notes
	public $prerequisites = array(); // modules required and rev level for this module to work properly
	public $keys		  = array(); // Load configuration constants for this module, must match entries in admin tabs
	public $dirlist		  = array(); // add new directories to store images and data
	public $tables		  = array(); // Load tables

  function __construct() {
	$this->prerequisites = array( // modules required and rev level for this module to work properly
	  'phreedom'   => '3.6',
	  'contacts'   => '3.6',
	  'inventory'  => '3.6',
	  'payment'    => '3.6',
	  'phreebooks' => '3.6',
	  'shipping'   => '3.6',
	);
	// Load configuration constants for this module, must match entries in admin tabs
    $this->keys = array(
	  'ZENCART_ENABLED'           => '0',
	  'ZENCART_URL'               => 'https://',
	  'ZENCART_API_KEY'           => '',
	  'ZENCART_PRODUCT_TAX_CLASS' => '',
	  'ZENCART_USE_PRICE_SHEETS'  => '0',
	  'ZENCART_PRICE_SHEET'       => '',
	  'ZENCART_STATUS_CONFIRM_ID'     => '',
	  'ZENCART_STATUS_PARTIAL_ID'     => '',
	  'ZENCART_NOTIFY_SHIPMENTS'      => '0',
	  'ZENCART_NOTIFY_MESSAGE'        => 'Your order shipped {date} via {carrier} {service}, tracking number: {tracking}',
	  'ZENCART_UPDATED_BY'            => 'FreeBooks',
	  'ZENCART_LAST_ORDER'            => '0',
	  'ZENCART_CUSTOMER_ACCOUNT_ID'   => '0',
	  'ZENCART_DEFAULT_TAX_RATE_ID'   => '0',
	  'ZENCART_CREATE_INVOICE'        => '0',
	  'ZENCART_DEFAULT_REP_ID'        => '0',
	  'ZENCART_PAYMENT_MAP'           => '',
	  'ZENCART_SHIPPING_MAP'          => '',
	  'ZENCART_NON_SHIP_SKUS'         => '',
	  'ZENCART_VAT_INVOICE'           => '0',
	  'ZENCART_VAT_INVOICE_FORM'      => '',
	  'ZENCART_VAT_INVOICE_SUBJECT'   => 'VAT Invoice {invoice}',
	  'ZENCART_VAT_INVOICE_MESSAGE'   => 'Please find attached the VAT invoice {invoice} for your order, {name}. Thank you for your business.',
	  'ZENCART_VAT_INVOICE_BCC'       => '',
	  'ZENCART_LIST_ICON_ENABLED'     => '0',
	);
  }

  function install($module) {
    global $db, $messageStack;
	$error = false;
	// zc_ship_notify was created in update() only, so an installation that had
	// never been upgraded simply did not have it - the same fault the whole
	// baseline exercise is about, one module further out. Created here too;
	// IF NOT EXISTS makes it harmless where update() got there first.
	$db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_ZC_SHIP_NOTIFY . " ("
	    . "shipping_log_id INT NOT NULL PRIMARY KEY,"
	    . "notified_at DATETIME NOT NULL"
	    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8");
	if (!db_field_exists(TABLE_INVENTORY, 'catalog')) { // setup new tab in table inventory
	  $result = $db->Execute("select id FROM ".TABLE_EXTRA_TABS." WHERE tab_name='ZenCart'");
	  if ($result->RecordCount() == 0) {
	  	$sql_data_array = array(
	      'module_id'   => 'inventory',
	      'tab_name'    => 'ZenCart',
	      'description' => 'ZenCart Catalog',
	      'sort_order'  => '48', // keep below the Amazon tab (49) so ZenCart sorts first
	    );
	    db_perform(TABLE_EXTRA_TABS, $sql_data_array);
	    $tab_id = db_insert_id();
	  } else $tab_id = $result->fields['id'];
	  gen_add_audit_log(ZENCART_LOG_TABS . TEXT_ADD, 'zencart');
	  // setup extra fields for inventory
	  $sql_data_array = array(
	    'module_id'   => 'inventory',
	    'tab_id'      => $tab_id,
	    'entry_type'  => 'check_box',
	    'field_name'  => 'catalog',
	    'description' => ZENCART_CATALOG_ADD,
	  	'sort_order'  => 10,
	  	'use_in_inventory_filter'=>'1',
	    'params'      => serialize(array('type'=>'check_box', 'select'=>'0', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
	  );
	  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column catalog enum('0','1') default '0'");
	  $sql_data_array = array(
	    'module_id'   => 'inventory',
	    'tab_id'      => $tab_id,
	    'entry_type'  => 'text',
	    'field_name'  => 'category_id',
	    'description' => ZENCART_CATALOG_CATEGORY_ID,
	  	'sort_order'  => 20,
	  	'use_in_inventory_filter'=>'1',
	  	'params'      => serialize(array('type'=>'text', 'length'=>'64', 'default'=>'', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
	  );
	  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column category_id varchar(64) default ''");

	  $sql_data_array = array(
	    'module_id'   => 'inventory',
	    'tab_id'      => $tab_id,
	    'entry_type'  => 'text',
	    'field_name'  => 'manufacturer',
	    'description' => ZENCART_CATALOG_MANUFACTURER,
	  	'sort_order'  => 30,
	  	'use_in_inventory_filter'=>'1',
	  	'params'      => serialize(array('type'=>'text', 'length'=>'64', 'default'=>'', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
	  );
	  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column manufacturer varchar(64) default ''");

	  $sql_data_array = array(
	    'module_id'   => 'inventory',
	    'tab_id'      => $tab_id,
	    'entry_type'  => 'text',
	    'field_name'  => 'ProductModel',
	    'description' => ZENCART_CATALOG_MODEL,
	  	'sort_order'  => 40,
	  	'use_in_inventory_filter'=>'1',
	  	'params'      => serialize(array('type'=>'text', 'length'=>'64', 'default'=>'', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
	  );
	  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column ProductModel varchar(64) default ''");

	  $sql_data_array = array(
	  	'module_id'   => 'inventory',
	  	'tab_id'      => $tab_id,
	  	'entry_type'  => 'text',
	  	'field_name'  => 'ProductURL',
	  	'description' => ZENCART_CATALOG_URL,
	  	'sort_order'  => 50,
	  	'use_in_inventory_filter'=>'1',
	  	'params'      => serialize(array('type'=>'text', 'length'=>'64', 'default'=>'', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
	  );
	  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column ProductURL varchar(64) default ''");

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
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column zencart_description text");

	  gen_add_audit_log(ZENCART_LOG_FIELDS . TEXT_NEW, 'zencart - catalog');
	}
    return $error;
  }

  function initialize($module) {
  	global $db, $messageStack;
  	// Skip the bulk upload to ZenCart when the integration is disabled.
  	if (!defined('ZENCART_ENABLED') || ZENCART_ENABLED !== '1') {
  	  $messageStack->add(defined('ZENCART_DISABLED_NOTICE') ? ZENCART_DISABLED_NOTICE : 'The ZenCart integration is disabled.', 'caution');
  	  return true;
  	}
  	gen_pull_language('inventory');
  	require_once(DIR_FS_MODULES . 'zencart/classes/zencart.php');
  	require_once(DIR_FS_MODULES . 'zencart/functions/zencart.php');
	require_once(DIR_FS_MODULES . 'zencart/language/'.$_SESSION['language'].'/language.php');
    require_once(DIR_FS_MODULES . 'inventory/defaults.php');
  	require_once(DIR_FS_MODULES . 'inventory/functions/inventory.php');

	$error  = false;
	if(defined('MODULE_ZENCART_LAST_UPDATE') && MODULE_ZENCART_LAST_UPDATE <> '') $where = " and ( last_update >'" . MODULE_ZENCART_LAST_UPDATE . "' or last_journal_date >'" . MODULE_ZENCART_LAST_UPDATE . "')";
	$result = $db->Execute("select id from " . TABLE_INVENTORY . " where catalog = '1' " . $where);
	$cnt    = 0;
	if($result->RecordCount() == 0)	return true;
	while(!$result->EOF) {
	  $prodXML = new zencart();
	  if (!$prodXML->uploadProduct((int)$result->fields['id'], true)) {
		$error = true;
		break;
	  }
	  $cnt++;
	  $result->MoveNext();
	}
	if ($error) return false;
	$messageStack->add(sprintf(ZENCART_BULK_UPLOAD_SUCCESS, $cnt), 'success');
	gen_add_audit_log(ZENCART_BULK_UPLOAD);
	write_configure('MODULE_ZENCART_LAST_UPDATE', date('Y-m-d H:i:s'));
  }

  function update($module) {
    global $db, $messageStack;
	$error = false;
	if (MODULE_ZENCART_STATUS < 3.4) {
		write_configure('MODULE_ZENCART_LAST_UPDATE', date('0000-00-00 00:00:00'));
	}
	$result = $db->Execute("select tab_id from " . TABLE_EXTRA_FIELDS . " where field_name = 'category_id'");
	if ($result->RecordCount() == 0) $error = $messageStack->add('can not find tab_name ZenCart','error');
	else $tab_id = $result->fields['tab_id'];
	if ($error == false  && !db_field_exists(TABLE_INVENTORY, 'ProductURL')){
		 $sql_data_array = array(
		    'module_id'   => 'inventory',
		    'tab_id'      => $tab_id,
		    'entry_type'  => 'text',
		    'field_name'  => 'ProductURL',
		    'description' => ZENCART_CATALOG_URL,
		    'params'      => serialize(array('type'=>'text', 'length'=>'64', 'default'=>'', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
		  );
		  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
		  $db->Execute("alter table " . TABLE_INVENTORY . " add column ProductURL varchar(64) default ''");
	}
	if ($error == false  && !db_field_exists(TABLE_INVENTORY, 'ProductModel')){
		$sql_data_array = array(
		    'module_id'   => 'inventory',
		    'tab_id'      => $tab_id,
		    'entry_type'  => 'text',
		    'field_name'  => 'ProductModel',
		    'description' => ZENCART_CATALOG_MODEL,
		    'params'      => serialize(array('type'=>'text', 'length'=>'64', 'default'=>'', 'inventory_type'=>'ai:ci:ds:sf:ma:ia:lb:mb:ms:mi:ns:sa:sr:sv:si:')),
		  );
		  db_perform(TABLE_EXTRA_FIELDS, $sql_data_array);
		  $db->Execute("alter table " . TABLE_INVENTORY . " add column ProductModel varchar(64) default ''");
	}

	if (!db_field_exists(TABLE_JOURNAL_MAIN, 'freight_tax_id')) {
	  $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
	    . " ADD COLUMN freight_tax_id SMALLINT NOT NULL DEFAULT 0 AFTER freight");
	}
	$db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_ZC_SHIP_NOTIFY . " ("
	    . "shipping_log_id INT NOT NULL PRIMARY KEY,"
	    . "notified_at DATETIME NOT NULL"
	    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8");

	// Master enable switch (default off) — gates all ZenCart traffic; see functions/zencart.php + cron
	if (!defined('ZENCART_ENABLED')) write_configure('ZENCART_ENABLED', '0');

	// VAT invoice email — off by default; see functions/zencart.php::zencart_email_vat_invoice()
	if (!defined('ZENCART_VAT_INVOICE'))         write_configure('ZENCART_VAT_INVOICE', '0');
	if (!defined('ZENCART_VAT_INVOICE_SUBJECT')) write_configure('ZENCART_VAT_INVOICE_SUBJECT', 'VAT Invoice {invoice}');
	if (!defined('ZENCART_VAT_INVOICE_MESSAGE')) write_configure('ZENCART_VAT_INVOICE_MESSAGE',
	  'Please find attached the VAT invoice {invoice} for your order, {name}. Thank you for your business.');
	if (!defined('ZENCART_VAT_INVOICE_BCC'))     write_configure('ZENCART_VAT_INVOICE_BCC', '');
	// Pre-select the stock VAT invoice form so the setting isn't blank on first upgrade.
	// Only ever fills an empty value — never overwrites a form the admin has already chosen.
	if (!defined('ZENCART_VAT_INVOICE_FORM') || ZENCART_VAT_INVOICE_FORM === '') {
	  if (!defined('TABLE_PHREEFORM')) define('TABLE_PHREEFORM', DB_PREFIX . 'phreeform');
	  $frm = $db->Execute("SELECT id FROM " . TABLE_PHREEFORM
	    . " WHERE doc_group = 'cust:inv' AND doc_ext = 'frm' AND doc_title = 'Invoice-VAT' LIMIT 1");
	  // Left blank (not '0') when no matching form exists yet, so a later module update —
	  // after the admin adds an Invoice-VAT form — can still auto-select it.
	  if (!$frm->EOF) write_configure('ZENCART_VAT_INVOICE_FORM', (int)$frm->fields['id']);
	}

	// HTML long-description field on the ZenCart inventory tab (holds web long description)
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
	  $db->Execute("alter table " . TABLE_INVENTORY . " add column zencart_description text");
	}

	if (!$error) {
	  write_configure('MODULE_' . strtoupper($module) . '_STATUS', constant('MODULE_' . strtoupper($module) . '_VERSION'));
   	  $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, constant('MODULE_' . strtoupper($module) . '_VERSION')), 'success');
	}
	return $error;
  }

  function remove($module) {
  }

  function load_reports($module) {
  }

  function load_demo() {
  }

}
?>