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
//  Path: /modules/shipping/classes/install.php
//

class shipping_admin {
	public $notes 			= array();// placeholder for any operational notes
	public $prerequisites 	= array();// modules required and rev level for this module to work properly
	public $keys			= array();// Load configuration constants for this module, must match entries in admin tabs
	public $dirlist			= array();// add new directories to store images and data
	public $tables			= array();// Load tables

  function __construct() {
	$this->prerequisites = array( // modules required and rev level for this module to work properly
	  'phreedom'   => '1.0.0',
	  'phreebooks' => '1.0.0',
	);
	// Load configuration constants for this module, must match entries in admin tabs
    $this->keys = array(
	  'ADDRESS_BOOK_SHIP_CONTACT_REQ'                  => '0',
	  'ADDRESS_BOOK_SHIP_ADD1_REQ'                     => '1',
	  'ADDRESS_BOOK_SHIP_ADD2_REQ'                     => '0',
	  'ADDRESS_BOOK_SHIP_CITY_REQ'                     => '1',
	  'ADDRESS_BOOK_SHIP_STATE_REQ'                    => '1',
	  'ADDRESS_BOOK_SHIP_POSTAL_CODE_REQ'              => '1',
	  'SHIPPING_DEFAULT_WEIGHT_UNIT'                   => 'LBS',
	  'SHIPPING_DEFAULT_CURRENCY'                      => 'USD',
	  'SHIPPING_DEFAULT_PKG_DIM_UNIT'                  => 'IN',
	  'SHIPPING_DEFAULT_RESIDENTIAL'                   => '1',
	  'SHIPPING_DEFAULT_PACKAGE_TYPE'                  => '02',
	  'SHIPPING_DEFAULT_PICKUP_SERVICE'                => '01',
	  'SHIPPING_DEFAULT_LENGTH'                        => '8',
	  'SHIPPING_DEFAULT_WIDTH'                         => '6',
	  'SHIPPING_DEFAULT_HEIGHT'                        => '4',
	  'SHIPPING_DEFAULT_ADDITIONAL_HANDLING_SHOW'      => '1',
	  'SHIPPING_DEFAULT_ADDITIONAL_HANDLING_CHECKED'   => '0',
	  'SHIPPING_DEFAULT_INSURANCE_SHOW'                => '1',
	  'SHIPPING_DEFAULT_INSURANCE_CHECKED'             => '0',
	  'SHIPPING_DEFAULT_INSURANCE_VALUE'               => '100.00',
	  'SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_SHOW'    => '1',
	  'SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_CHECKED' => '1',
	  'SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_VALUE'   => '75',
	  'SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_SHOW'    => '1',
	  'SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_CHECKED' => '0',
	  'SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_TYPE'    => '2',
	  'SHIPPING_DEFAULT_HANDLING_CHARGE_SHOW'          => '1',
	  'SHIPPING_DEFAULT_HANDLING_CHARGE_CHECKED'       => '0',
	  'SHIPPING_DEFAULT_HANDLING_CHARGE_VALUE'         => '0.00',
	  'SHIPPING_DEFAULT_COD_SHOW'                      => '1',
	  'SHIPPING_DEFAULT_COD_CHECKED'                   => '0',
	  'SHIPPING_DEFAULT_PAYMENT_TYPE'                  => '1',
	  'SHIPPING_DEFAULT_SATURDAY_PICKUP_SHOW'          => '1',
	  'SHIPPING_DEFAULT_SATURDAY_PICKUP_CHECKED'       => '0',
	  'SHIPPING_DEFAULT_SATURDAY_DELIVERY_SHOW'        => '1',
	  'SHIPPING_DEFAULT_SATURDAY_DELIVERY_CHECKED'     => '0',
	  'SHIPPING_DEFAULT_HAZARDOUS_SHOW'                => '0',
	  'SHIPPING_DEFAULT_HAZARDOUS_CHECKED'             => '0',
	  'SHIPPING_DEFAULT_DRY_ICE_SHOW'                  => '0',
	  'SHIPPING_DEFAULT_DRY_ICE_CHECKED'               => '0',
	  'SHIPPING_DEFAULT_RETURN_SERVICE_SHOW'           => '1',
	  'SHIPPING_DEFAULT_RETURN_SERVICE_CHECKED'        => '0',
	  'SHIPPING_DEFAULT_RETURN_SERVICE'                => '2',
	  'SHIPPING_VOL_WEIGHT_DIVISOR'                    => '5000',
	  'SHIPPING_SHIPPER_EORI'                          => '',
	  'SHIPPING_SHIPPER_VAT'                           => '',
	  'SHIPPING_SHIPPER_IOSS'                          => '',
	);
	// add new directories to store images and data
	$this->dirlist = array(
	  'shipping',
	);
	// Load tables
	$this->tables = array(
	  TABLE_SHIPPING_PACKAGES => "CREATE TABLE " . TABLE_SHIPPING_PACKAGES . " (
		  id int(11) NOT NULL auto_increment,
		  package_code varchar(20) NOT NULL default '',
		  name varchar(64) NOT NULL default '',
		  description varchar(255) NOT NULL default '',
		  weight decimal(10,4) NOT NULL default '0.0000',
		  max_weight decimal(10,4) NOT NULL default '0.0000',
		  ext_length decimal(10,4) NOT NULL default '0.0000',
		  ext_width decimal(10,4) NOT NULL default '0.0000',
		  ext_height decimal(10,4) NOT NULL default '0.0000',
		  int_length decimal(10,4) NOT NULL default '0.0000',
		  int_width decimal(10,4) NOT NULL default '0.0000',
		  int_height decimal(10,4) NOT NULL default '0.0000',
		  cost decimal(10,4) NOT NULL default '0.0000',
		  sort_order int(11) NOT NULL default '0',
		  inactive enum('0','1') NOT NULL default '0',
		  carrier varchar(32) NOT NULL default '',
		  PRIMARY KEY (id),
		  UNIQUE KEY package_code (package_code)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_SHIPPING_LOG => "CREATE TABLE " . TABLE_SHIPPING_LOG . " (
		  id int(11) NOT NULL auto_increment,
		  shipment_id int(11) NOT NULL default '0',
		  ref_id varchar(16) NOT NULL default '0',
		  reconciled smallint(4) NOT NULL default '0',
		  carrier varchar(16) NOT NULL default '',
		  method varchar(8) NOT NULL default '',
		  ship_date datetime NULL default NULL,
		  deliver_date datetime NULL default NULL,
		  actual_date datetime NULL default NULL,
		  deliver_late enum('0','1') NOT NULL default '0',
		  tracking_id varchar(32) NOT NULL default '',
		  cost double NOT NULL default '0',
		  notes varchar(255) NOT NULL default '',
		  recipient_name varchar(64) NOT NULL default '',
		  recipient_email varchar(100) NOT NULL default '',
		  carrier_order_id varchar(64) NOT NULL default '',
		  package_data mediumtext NULL,
		  order_id int(11) NOT NULL default '0',
		  PRIMARY KEY  (id),
		  KEY ref_id (ref_id),
		  KEY order_id (order_id)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_SHIPPING_LOG_ITEMS => "CREATE TABLE " . TABLE_SHIPPING_LOG_ITEMS . " (
		  id int(11) NOT NULL auto_increment,
		  shipment_id int(11) NOT NULL default '0',
		  carrier varchar(16) NOT NULL default '',
		  order_id int(11) NOT NULL default '0',
		  item_ref_id int(11) NOT NULL default '0',
		  sku varchar(64) NOT NULL default '',
		  qty double NOT NULL default '0',
		  status enum('shipped','cancelled') NOT NULL default 'shipped',
		  created_at datetime NULL default NULL,
		  PRIMARY KEY (id),
		  KEY order_id (order_id),
		  KEY shipment_id (shipment_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_SHIPPING_PICKUPS => "CREATE TABLE " . TABLE_SHIPPING_PICKUPS . " (
		  id int(11) NOT NULL auto_increment,
		  carrier varchar(16) NOT NULL default '',
		  confirmation varchar(255) NOT NULL default '',
		  pickup_date date NULL default NULL,
		  ready_time varchar(5) NOT NULL default '',
		  close_time varchar(5) NOT NULL default '',
		  location varchar(80) NOT NULL default '',
		  shipment_ids varchar(255) NOT NULL default '',
		  status enum('booked','cancelled') NOT NULL default 'booked',
		  booked_by varchar(64) NOT NULL default '',
		  booked_at datetime NULL default NULL,
		  cancelled_at datetime NULL default NULL,
		  PRIMARY KEY (id),
		  KEY carrier_date (carrier, pickup_date)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    );
  }

  function install($module) {
    global $db, $messageStack;
	$error = false;
	$methods = array('freeshipper','flat'); // pick a couple of modules to install
	foreach ($methods as $method) {
	  // Each method's keys() references its own language constants (e.g.
	  // MODULE_SHIPPING_FREESHIPPER_TITLE_SHORT), so its language file must be
	  // loaded before the class is used.
	  $method_lang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
	  $method_lang_file = DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/language/' . $method_lang . '/language.php';
	  if (!file_exists($method_lang_file)) $method_lang_file = DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/language/en_us/language.php';
	  if (file_exists($method_lang_file)) require_once($method_lang_file);
	  require_once(DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/' . $method . '.php');
	  $properties = new $method();
	  write_configure('MODULE_' . strtoupper($module) . '_' . strtoupper($method) . '_STATUS', '1');
	  foreach ($properties->keys() as $key) write_configure($key['key'], $key['default']);
	  if (method_exists($properties, 'install')) $properties->install();
	}
	if (!db_field_exists(TABLE_CURRENT_STATUS, 'next_shipment_num')) $db->Execute("ALTER TABLE ".TABLE_CURRENT_STATUS." ADD next_shipment_num VARCHAR(16) NOT NULL DEFAULT '1'");
    return $error;
  }

  function initialize($module) {
  }

  function update($module) {
    global $db, $messageStack;
	$error = false;
	if (!defined('MODULE_'.strtoupper($module).'_VERSION')) return $error; // shippig module not installed,
	if (gen_version_is_legacy(MODULE_SHIPPING_STATUS, '3.2')) {
	  if (!db_field_exists(TABLE_CURRENT_STATUS, 'next_shipment_num')) $db->Execute("ALTER TABLE " . TABLE_CURRENT_STATUS . " ADD next_shipment_num VARCHAR(16) NOT NULL DEFAULT '1'");
	  if (db_field_exists(TABLE_CURRENT_STATUS, 'next_shipment_desc')) $db->Execute("ALTER TABLE " . TABLE_CURRENT_STATUS . " DROP next_shipment_desc");
	}
	if (gen_version_is_legacy(MODULE_SHIPPING_STATUS, '3.7')) {
		$db->Execute("ALTER TABLE ".TABLE_SHIPPING_LOG." CHANGE cost cost DOUBLE NOT NULL DEFAULT '0'");
	}
	if (!gen_version_is_freebooks(MODULE_SHIPPING_STATUS)) {
		include(DIR_FS_MODULES . 'shipping/updates/PhreeBooks371toFreeBooks100.php');
	}
	if (!$error) {
	  write_configure('MODULE_' . strtoupper($module) . '_STATUS', constant('MODULE_' . strtoupper($module) . '_VERSION'));
   	  $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, constant('MODULE_' . strtoupper($module) . '_VERSION')), 'success');
	}
	return $error;
  }

  function remove($module) {
    global $db;
	$error = false;
	// load and remove all modules
	$method_dir = DIR_FS_ADMIN . 'modules/' . $module . '/methods/';
	$methods = array();
	if ($dir = @dir($method_dir)) {
	  while ($choice = $dir->read()) {
		if (file_exists($method_dir . $choice . '/' . $choice . '.php') && $choice <> '.' && $choice <> '..') {
		  $methods[] = $choice;
		}
	  }
	  $dir->close();
	  foreach ($methods as $method) {
	    require_once(DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/' . $method . '.php');
	    $properties = new $method();
	    remove_configure('MODULE_' . strtoupper($module) . '_' . strtoupper($method) . '_STATUS');
	    foreach ($properties->keys() as $key) remove_configure($key['key']);
	    if (method_exists($properties, 'remove')) $properties->remove();
	  }
	}
    if (db_field_exists(TABLE_CURRENT_STATUS, 'next_shipment_num'))  $db->Execute("ALTER TABLE " . TABLE_CURRENT_STATUS . " DROP next_shipment_num");
	if (db_field_exists(TABLE_CURRENT_STATUS, 'next_shipment_desc')) $db->Execute("ALTER TABLE " . TABLE_CURRENT_STATUS . " DROP next_shipment_desc");
	return $error;
  }

  function load_reports($module) {
  }

  function load_demo() {
  }

}
?>