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
//  Path: /modules/payment/classes/install.php
//

class payment_admin {
	public $notes 			= array();// placeholder for any operational notes
	public $prerequisites 	= array();// modules required and rev level for this module to work properly
	public $keys			= array();// Load configuration constants for this module, must match entries in admin tabs
	public $dirlist			= array();// add new directories to store images and data
	// Requests for payment: one row per payment link or payment-method invoice
	// sent to a customer, against the FreeBooks invoice it asks to be paid.
	// external_id is the method's own reference for the request, unique within
	// the method. status is requested, paid, cancelled or failed; a request whose
	// expires_at has passed unpaid is shown as expired rather than stored so.
	// receipt_id is the J18 that recorded the payment, when one was recorded.
	public $tables = array(
	  TABLE_PAYMENT_REQUESTS => "CREATE TABLE " . TABLE_PAYMENT_REQUESTS . " (
		  id int(11) NOT NULL AUTO_INCREMENT,
		  method varchar(32) NOT NULL,
		  request_type varchar(16) NOT NULL,
		  external_id varchar(64) NOT NULL,
		  external_ref varchar(64) NULL DEFAULT NULL,
		  ref_id int(11) NOT NULL,
		  amount double NOT NULL DEFAULT 0,
		  currency_code char(3) NOT NULL DEFAULT '',
		  url varchar(512) NULL DEFAULT NULL,
		  status varchar(16) NOT NULL DEFAULT 'requested',
		  receipt_id int(11) NULL DEFAULT NULL,
		  capture_id varchar(64) NULL DEFAULT NULL,
		  admin_id int(11) NOT NULL DEFAULT 0,
		  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  expires_at datetime NULL DEFAULT NULL,
		  completed_at datetime NULL DEFAULT NULL,
		  PRIMARY KEY (id),
		  UNIQUE KEY method_external (method, external_id),
		  KEY ref_id (ref_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
	);
	
  function __construct() {
	$this->prerequisites = array( // modules required and rev level for this module to work properly
	  'contacts'   => '1.0.0',
	  'phreedom'   => '1.0.0',
	  'phreebooks' => '1.0.0',
	);
  }

  function install($module) {
	$error = false;
	$methods = array('cod','moneyorder'); // pick a couple of modules to install
	foreach ($methods as $method) {
	  // Each method's class properties reference its own language constants
	  // (e.g. MODULE_PAYMENT_COD_TEXT_TITLE), evaluated at class-declaration
	  // time, so its language file must be loaded before the require below.
	  $method_lang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
	  $method_lang_file = DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/language/' . $method_lang . '/language.php';
	  if (!file_exists($method_lang_file)) $method_lang_file = DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/language/en_us/language.php';
	  if (file_exists($method_lang_file)) require_once($method_lang_file);
	  require_once(DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/' . $method . '.php');
	  $properties = new $method();
	  write_configure('MODULE_' . strtoupper($module) . '_' . strtoupper($method) . '_STATUS', '1');
	  foreach ($properties->key as $key) write_configure($key['key'], $key['default']);
	  if (method_exists($properties, 'install')) $properties->install();
	}
    return $error;
  }

  function initialize($module) {
  }

  /**
   * What every method's constructor reads before it reads its own language file:
   * the base class's key descriptions, from this module's language and admin
   * files, and the card limits in defaults.php, which some methods' language
   * files use. update() and remove() run from the login cascade and from module
   * removal, where none of these is loaded.
   */
  private function load_method_prerequisites($module) {
	gen_pull_language($module);
	gen_pull_language($module, 'admin');
	require_once(DIR_FS_MODULES . $module . '/defaults.php');
  }

  function update($module) {
    global $db, $messageStack;
	$error = false;
  	// load all modules
	$method_dir = DIR_FS_ADMIN . 'modules/' . $module . '/methods/';
	$methods = array();
	if ($dir = @dir($method_dir)) {
	  while ($choice = $dir->read()) {
		if (file_exists($method_dir . $choice . '/' . $choice . '.php') && $choice <> '.' && $choice <> '..') {
		  $methods[] = $choice;
		}
	  }
	  $dir->close();//update keys
	  $this->load_method_prerequisites($module);
	  foreach ($methods as $method) {
	    require_once(DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/' . $method . '.php');
	    // A method's constructor reads its own title constant, which lives in
	    // the method's language file. install() loads it; these two run from
	    // the module cascade and from module removal, where nothing else does.
	    load_method_language($method_dir, $method);
	    $properties = new $method();
	    foreach ($properties->keys() as $key) {
	    	if(!defined($key['key'])) write_configure($key['key'], $key['default']);
	    }
	  }
	}
	if (!gen_version_is_freebooks(MODULE_PAYMENT_STATUS)) {
	  include(DIR_FS_MODULES . 'payment/updates/PhreeBooks371toFreeBooks100.php');
	}
	if (!$error) {
	  $ver_const = 'MODULE_' . strtoupper($module) . '_VERSION';
	  $ver = defined($ver_const) ? constant($ver_const) : '';
	  write_configure('MODULE_' . strtoupper($module) . '_STATUS', $ver);
   	  $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, $ver), 'success');
	}
	return $error;
  }

  function remove($module) {
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
	  $this->load_method_prerequisites($module);
	  foreach ($methods as $method) {
	    require_once(DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/' . $method . '.php');
	    // A method's constructor reads its own title constant, which lives in
	    // the method's language file. install() loads it; these two run from
	    // the module cascade and from module removal, where nothing else does.
	    load_method_language($method_dir, $method);
	    $properties = new $method();
	    remove_configure('MODULE_' . strtoupper($module) . '_' . strtoupper($method) . '_STATUS');
	    foreach ($properties->keys() as $key) remove_configure($key['key']);
	    if (method_exists($properties, 'remove')) $properties->remove();
	  }
	}
	return $error;
  }

  function load_reports($module) {
  }

  function load_demo() {
  }

}
?>