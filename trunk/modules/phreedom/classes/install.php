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
//  Path: /modules/phreedom/classes/install.php
//

class phreedom_admin {
	public $notes 			= array();// placeholder for any operational notes
	public $prerequisites 	= array();// modules required and rev level for this module to work properly
	public $keys			= array();// Load configuration constants for this module, must match entries in admin tabs
	public $dirlist			= array();// add new directories to store images and data
	public $tables			= array();// Load tables
	public $default_currency = 'USD';// the currency the books are kept in; set from the chosen chart of accounts

  // Every utf8mb4 table here names its collation. Left out, MariaDB 11.4 chooses
  // utf8mb4_uca1400_ai_ci, which MySQL has never heard of, and a dump of the
  // table then stops a restore dead with "Unknown collation".
  function __construct() {
	// Load configuration constants for this module, must match entries in admin tabs
    $this->keys = array(
	  'COMPANY_ID'                 => 'HQ',
	  'COMPANY_NAME'               => 'My Company',
	  'AR_CONTACT_NAME'            => 'AR Contact',
	  'AP_CONTACT_NAME'            => 'AP Contact',
	  'COMPANY_ADDRESS1'           => '100 Main St.',
	  'COMPANY_ADDRESS2'           => '',
	  'COMPANY_CITY_TOWN'          => 'Anytown',
	  'COMPANY_ZONE'               => 'CA',
	  'COMPANY_POSTAL_CODE'        => '90001',
	  'COMPANY_COUNTRY'            => 'USA',
	  'COMPANY_TELEPHONE1'         => '',
	  'COMPANY_TELEPHONE2'         => '',
	  'COMPANY_FAX'                => '',
	  'COMPANY_EMAIL'              => 'webmaster@mycompany.com',
	  'COMPANY_WEBSITE'            => '',
	  'TAX_ID'                     => '',
	  'ENABLE_MULTI_BRANCH'        => '0',
	  'ENABLE_MULTI_CURRENCY'      => '0',
	  'ENABLE_ENCRYPTION'          => '0',
	  'ENTRY_PASSWORD_MIN_LENGTH'  => '5',
	  'MAX_DISPLAY_SEARCH_RESULTS' => '20',
	  'CFG_AUTO_UPDATE_CHECK'      => '0',
	  'HIDE_SUCCESS_MESSAGES'      => '0',
	  'AUTO_UPDATE_CURRENCY'       => '1',
	  'LIMIT_HISTORY_RESULTS'      => '20',
	  'SESSION_TIMEOUT_ADMIN'      => '3600',
	  'SESSION_AUTO_REFRESH'       => '0',
	  'DEBUG'                      => '0',
	  'IE_RW_EXPORT_PREFERENCE'    => 'Download',
	  'EMAIL_TRANSPORT'            => 'smtp',
	  'EMAIL_LINEFEED'             => 'LF',
	  'EMAIL_USE_HTML'             => '0',
	  'STORE_OWNER_EMAIL_ADDRESS'  => '',
	  'EMAIL_FROM'                 => '',
	  'ADMIN_EXTRA_EMAIL_FORMAT'   => 'TEXT',
	  'EMAIL_SMTPAUTH_MAILBOX'     => '',
	  'EMAIL_SMTPAUTH_PASSWORD'    => '',
	  'EMAIL_SMTPAUTH_MAIL_SERVER' => '',
	  'EMAIL_SMTPAUTH_MAIL_SERVER_PORT' => '25',
      'EMAIL_SMTPAUTH_TLS'            => '0',
      'EMAIL_SMTP_ALLOW_SELF_SIGNED'  => '0',
      'EMAIL_OAUTH2_PROVIDER'        => 'google',
      'EMAIL_OAUTH2_CLIENT_ID'       => '',
      'EMAIL_OAUTH2_CLIENT_SECRET'   => '',
      'EMAIL_OAUTH2_TENANT'          => 'common',
      'EMAIL_OAUTH2_EMAIL'           => '',
      'EMAIL_OAUTH2_REFRESH_TOKEN'   => '',
	  'CURRENCIES_TRANSLATIONS'    => '&pound;,?:&euro;,?',
      'DATE_FORMAT'                => 'm/d/Y', // this is used for date(), use only values: Y, m and d (case sensitive)
      'DATE_DELIMITER'             => '/', // must match delimiter used in DATE_FORMAT
      'DATE_TIME_FORMAT'           => 'm/d/Y h:i:s a',
	  'SERVER_ADDRESS'			   => '',
	  'WEBAUTHN_ENABLED'           => '1',
	  'WEBAUTHN_RP_ID'             => '', // blank = derive from HTTPS_SERVER
	);
	// add new directories to store images and data
	$this->dirlist = array(
	  '../backups', // goes in root my_files directory
	  'images',
	  'temp',
	);
	// Load tables
	$this->tables = array(
	  TABLE_AUDIT_LOG => "CREATE TABLE " . TABLE_AUDIT_LOG . " (
		  id int(15) NOT NULL auto_increment,
		  action_date timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		  user_id int(11) NOT NULL default '0',
		  ip_address varchar(15) NOT NULL default '0.0.0.0',
		  stats varchar(32) NOT NULL,
		  reference_id varchar(32) NOT NULL default '',
		  action varchar(64) default NULL,
		  amount DOUBLE NOT NULL DEFAULT '0',
		  PRIMARY KEY (id),
		  KEY idx_page_accessed_zen (reference_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ;",
	  TABLE_CONFIGURATION => "CREATE TABLE " . TABLE_CONFIGURATION . " (
		  configuration_key varchar(64) NOT NULL default '',
		  configuration_value mediumtext,
		  PRIMARY KEY (configuration_key)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_CURRENCIES => "CREATE TABLE " . TABLE_CURRENCIES . " (
		  currencies_id int(11) NOT NULL auto_increment,
		  title varchar(32) NOT NULL default '',
		  code char(3) NOT NULL default '',
		  symbol_left varchar(24) default NULL,
		  symbol_right varchar(24) default NULL,
		  decimal_point char(1) default NULL,
		  thousands_point char(1) default NULL,
		  decimal_places char(1) default NULL,
		  decimal_precise char(1) NOT NULL default '2',
		  value DOUBLE NOT NULL DEFAULT '0',
		  last_updated datetime default NULL,
		  PRIMARY KEY  (currencies_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_CURRENT_STATUS => "CREATE TABLE " . TABLE_CURRENT_STATUS . " (
		  id int(11) NOT NULL auto_increment,
		  PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_DATA_SECURITY => "CREATE TABLE " . TABLE_DATA_SECURITY . " (
		  id int(11) NOT NULL auto_increment,
		  module varchar(32) NOT NULL DEFAULT '',
		  ref_1 int(11) NOT NULL DEFAULT '0',
		  ref_2 int(11) NOT NULL DEFAULT '0',
		  hint varchar(255) NOT NULL DEFAULT '',
		  enc_value varchar(255) NOT NULL DEFAULT '',
		  exp_date date NOT NULL DEFAULT '2049-12-31',
		  PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_EXTRA_FIELDS => "CREATE TABLE " . TABLE_EXTRA_FIELDS . " (
		  id int(10) NOT NULL auto_increment,
		  module_id varchar(32) NOT NULL default '',
		  tab_id int(11) NOT NULL default '0',
		  entry_type varchar(20) NOT NULL default '',
		  field_name varchar(32) NOT NULL default '',
		  description varchar(64) NOT NULL default '',
		  sort_order varchar(64) NOT NULL default '',
		  group_by varchar(64) NOT NULL default '',
		  use_in_inventory_filter enum('0','1') NOT NULL DEFAULT '0',
		  params mediumtext,
		  PRIMARY KEY (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_EXTRA_TABS => "CREATE TABLE " . TABLE_EXTRA_TABS . " (
		  id int(3) NOT NULL auto_increment,
		  module_id varchar(32) NOT NULL default '',
		  tab_name varchar(32) NOT NULL default '',
		  description varchar(80) NOT NULL default '',
		  sort_order int(2) NOT NULL default '0',
		  PRIMARY KEY (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_USERS => "CREATE TABLE " . TABLE_USERS . " (
		  admin_id int(11) NOT NULL auto_increment,
		  is_role enum('0','1') NOT NULL default '0',
		  admin_name varchar(32) NOT NULL default '',
		  inactive enum('0','1') NOT NULL default '0',
		  display_name varchar(32) NOT NULL default '',
		  admin_email varchar(96) NOT NULL default '',
		  admin_pass varchar(255) NOT NULL default '',
		  account_id int(11) NOT NULL default '0',
		  admin_store_id int(11) NOT NULL default '0',
		  admin_prefs mediumtext,
		  admin_security mediumtext,
		  failed_attempts int(11) NOT NULL default '0',
		  locked_until datetime NULL default NULL,
		  pw_reset_token char(64) NULL default NULL,
		  pw_reset_expires datetime NULL default NULL,
		  PRIMARY KEY (admin_id),
		  KEY idx_admin_name_zen (admin_name)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ;",
	  TABLE_WEBAUTHN_CREDS => "CREATE TABLE " . TABLE_WEBAUTHN_CREDS . " (
		  id int(11) NOT NULL auto_increment,
		  admin_id int(11) NOT NULL default '0',
		  cred_id varchar(500) CHARACTER SET ascii NOT NULL default '' COMMENT 'base64url-encoded credential id',
		  public_key text NOT NULL,
		  user_handle char(32) NOT NULL default '' COMMENT 'hex-encoded, stable per admin_id',
		  sign_count int(11) NOT NULL default '0',
		  aaguid char(32) NOT NULL default '' COMMENT 'hex-encoded authenticator model id',
		  label varchar(64) NOT NULL default '',
		  transports varchar(64) NOT NULL default '',
		  created datetime NOT NULL,
		  last_used datetime default NULL,
		  PRIMARY KEY (id),
		  UNIQUE KEY idx_cred_id (cred_id),
		  KEY idx_admin_id (admin_id),
		  KEY idx_user_handle (user_handle)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
	  TABLE_USERS_PROFILES => "CREATE TABLE " . TABLE_USERS_PROFILES . " (
		  id int(11) NOT NULL auto_increment,
		  user_id int(11) NOT NULL default '0',
		  menu_id varchar(32) NOT NULL default '',
		  module_id varchar(32) NOT NULL default '',
		  dashboard_id varchar(32) NOT NULL default '',
		  column_id int(3) NOT NULL default '0',
		  row_id int(3) NOT NULL default '0',
		  params mediumtext,
		  PRIMARY KEY (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    );
  }

  function install($module) {
    global $db, $messageStack;
	$error = false;
	// Load some default currency values.
	//
	// `value` is what one unit of the currency is worth in the DEFAULT one, so
	// the default's own row must be 1 and the rest must be quoted against it -
	// that is the convention currency::btn_update() writes and format() reads,
	// and it is why the rates below are held per US dollar and then divided
	// through by the default's. Seeding a GBP company from a table quoted in
	// dollars would leave every foreign amount out by the dollar rate.
	//
	// Indicative only, and only until the rates are next updated; the exchange
	// server does the real work whenever multi-currency is switched on.
	$db->Execute("TRUNCATE TABLE " . TABLE_CURRENCIES);
	$per_usd = array(
	  'USD' => array('title' => 'US Dollar',      'symbol_left' => '$', 'symbol_right' => '', 'rate' => 1.00),
	  'EUR' => array('title' => 'Euro',           'symbol_left' => '€', 'symbol_right' => '', 'rate' => 0.92),
	  'GBP' => array('title' => 'Pound Sterling', 'symbol_left' => '£', 'symbol_right' => '', 'rate' => 0.79),
	);
	$default = isset($per_usd[$this->default_currency]) ? $this->default_currency : 'USD';
	foreach ($per_usd as $code => $entry) {
	  db_perform(TABLE_CURRENCIES, array(
	    'title'           => $entry['title'],
	    'code'            => $code,
	    'symbol_left'     => $entry['symbol_left'],
	    'symbol_right'    => $entry['symbol_right'],
	    'decimal_point'   => '.',
	    'thousands_point' => ',',
	    'decimal_places'  => '2',
	    'decimal_precise' => '2',
	    'value'           => round($entry['rate'] / $per_usd[$default]['rate'], 8),
	    'last_updated'    => date('Y-m-d H:i:s'),
	  ), 'insert');
	}
	write_configure('DEFAULT_CURRENCY', $default);
	// Enter some data into table current status
	$db->Execute("TRUNCATE TABLE " . TABLE_CURRENT_STATUS);
	$db->Execute("insert into " . TABLE_CURRENT_STATUS . " set id = 1");
    return $error;
  }

  function initialize($loaded_modules) {
    global $db, $messageStack, $currencies;
    // load the latest currency exchange rates
    if (web_connected(false) && AUTO_UPDATE_CURRENCY && ENABLE_MULTI_CURRENCY) {
	  gen_pull_language('phreedom', 'admin');
	  require_once(DIR_FS_MODULES . 'phreedom/classes/currency.php');
	  $currency = new currency();
	  $currency->btn_update();
	}
	// Fix for change to audit log for upgrade to R3.6 causes perpertual crashing when writing audit log
	if (!db_field_exists(TABLE_AUDIT_LOG, 'stats')) $db->Execute("ALTER TABLE ".TABLE_AUDIT_LOG." ADD stats VARCHAR(32) NOT NULL AFTER ip_address");
	// load installed modules and initialize them
	if (is_array($loaded_modules)) foreach ($loaded_modules as $module) {
	  if ($module == 'phreedom') continue; // skip this module
	  require_once(DIR_FS_MODULES . $module . '/classes/install.php');
	  $install_class = $module . '_admin';
	  $mod_init = new $install_class;
	  if (constant('MODULE_' . strtoupper($module) . '_STATUS') <> constant('MODULE_' . strtoupper($module) . '_VERSION')) {
		// add any new constants
		if (!empty($mod_init->keys)) foreach ($mod_init->keys as $key => $value) {
		  if (!defined($key)) write_configure($key, $value);
		}
		admin_install_dirs($mod_init->dirlist, DIR_FS_MY_FILES.$_SESSION['company'].'/');
	    // Stamp here rather than trusting each module to remember. Sixteen of
	    // them did not, and the cost of forgetting is invisible: the cascade only
	    // knows to stop when STATUS reaches VERSION, so an update that does not
	    // record itself runs again on every login, for ever. A module that does
	    // stamp simply writes the same value twice.
	    //
	    // The return value is no use as a signal: several of these end with
	    // `return $error;` where $error is false, so false means SUCCESS - and
	    // vin_lookup returns false meaning "nothing to do". A thrown exception is
	    // the only thing that reliably says the update did not happen.
	    if (method_exists($mod_init, 'update')) $mod_init->update($module);
	    write_configure('MODULE_' . strtoupper($module) . '_STATUS',
	                    constant('MODULE_' . strtoupper($module) . '_VERSION'));
	  }
	  if (method_exists($mod_init, 'initialize')) $mod_init->initialize($module);
	}
    if (web_connected(false) && CFG_AUTO_UPDATE_CHECK && (SECURITY_ID_CONFIGURATION > 3)) { // check for software updates
	  $revisions = @file_get_contents(VERSION_CHECK_URL);
	  if ($revisions) {
	    $versions = xml_to_object($revisions);
		$latest  = $versions->Revisions->Phreedom->Current;
		$current = MODULE_PHREEDOM_VERSION;
		if ($latest > $current) $messageStack->add_session(sprintf(TEXT_VERSION_CHECK_NEW_VER, $current, $latest), 'caution');
		foreach ($loaded_modules as $mod) { // check rest of modules
		  if ($mod == 'phreedom') continue; // skip this module
		  $latest  = $versions->Revisions->Modules->$mod->Current;
		  $current = constant('MODULE_' . strtoupper($mod) . '_VERSION');
		  if ($latest > $current) $messageStack->add_session(sprintf(TEXT_VERSION_CHECK_NEW_MOD_VER, $mod, $current, $latest), 'caution');
		}
	  }
    }
	// Make sure the install directory has been moved/removed
	if (is_dir(DIR_FS_ADMIN . 'install')) $messageStack->add_session(TEXT_INSTALL_DIR_PRESENT, 'caution');
  }

  function update($module) {
    global $db, $messageStack;
	$error = false;
	$db_version = defined('MODULE_PHREEDOM_STATUS') ? MODULE_PHREEDOM_STATUS : false;
	foreach ($this->keys as $key => $value) if (!defined($key)) write_configure($key, $value);
	if ($db_version < MODULE_PHREEDOM_STATUS) {
 	  $db_version = $this->release_update($module, 3.0, DIR_FS_MODULES . 'phreedom/updates/PBtoR30.php');
	  if (!$db_version) return true;
	}
  	if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.1')) {
  	  foreach ($this->tables as $table => $create_table_sql) {
	    if (!db_table_exists($table)) if (!$db->Execute($create_table_sql)) $error = true;
	  }
	  // The NAME, quoted — see phreehelp/classes/install.php for why the bare
	  // constant wrote nothing.
	  write_configure('PHREEHELP_FORCE_RELOAD', '1');
	}
    if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.2')) {
	  if (!db_field_exists(TABLE_USERS, 'is_role')) $db->Execute("ALTER TABLE ".TABLE_USERS." ADD is_role ENUM('0','1') NOT NULL DEFAULT '0' AFTER admin_id");
	}
    if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.4')) {
	  if (!db_field_exists(TABLE_DATA_SECURITY, 'exp_date')) $db->Execute("ALTER TABLE ".TABLE_DATA_SECURITY." ADD exp_date DATE NOT NULL DEFAULT '2049-12-31' AFTER enc_value");
	  if (!db_field_exists(TABLE_AUDIT_LOG, 'ip_address'))   $db->Execute("ALTER TABLE ".TABLE_AUDIT_LOG    ." ADD ip_address VARCHAR(15) NOT NULL AFTER user_id");
    }
  	if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.5')) {
	  if (!db_field_exists(TABLE_EXTRA_FIELDS, 'group_by'))  $db->Execute("ALTER TABLE ".TABLE_EXTRA_FIELDS." ADD group_by varchar(64) NOT NULL default ''");
	  if (!db_field_exists(TABLE_EXTRA_FIELDS, 'sort_order'))$db->Execute("ALTER TABLE ".TABLE_EXTRA_FIELDS." ADD sort_order varchar(64) NOT NULL default ''");
	  if (!db_field_exists(TABLE_AUDIT_LOG, 'stats'))        $db->Execute("ALTER TABLE ".TABLE_AUDIT_LOG." ADD stats VARCHAR(32) NOT NULL AFTER ip_address");
  	}
  	if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.7')) {
  		$db->Execute("ALTER TABLE ".TABLE_AUDIT_LOG." CHANGE amount amount DOUBLE NOT NULL DEFAULT '0'");
  		$db->Execute("ALTER TABLE ".TABLE_CURRENCIES." CHANGE value value DOUBLE NOT NULL DEFAULT '0'");
  	}
  	if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.8')) {
  	  if (!db_table_exists(TABLE_WEBAUTHN_CREDS)) $db->Execute($this->tables[TABLE_WEBAUTHN_CREDS]);
  	}
  	if (gen_version_is_legacy(MODULE_PHREEDOM_STATUS, '3.9')) {
  	  // The Company Links / Notes / To-Do dashboards had PhreeForm's security
  	  // id (3) as their own key — a constant they borrowed, which went with
  	  // that module. They are SECURITY_ID_DASHBOARDS (12) now.
  	  //
  	  // A rename would not have been enough. A dashboard's security id decides
  	  // WHO CAN SEE IT, so pointing three of them at a key nobody holds makes
  	  // all three disappear from every dashboard nobody has re-permissioned —
  	  // silently, because an absent key reads exactly like a denied one. So
  	  // each role's level for 3 is copied across.
  	  //
  	  // Copied, not moved: PhreeForm still uses 3 until it is deleted, and a
  	  // role that already carries 12 is left alone, so running this twice is
  	  // harmless.
  	  $result = $db->Execute("select admin_id, admin_security from " . TABLE_USERS
  	      . " where admin_security <> ''");
  	  while ($result && !$result->EOF) {
  	    $id       = (int)$result->fields['admin_id'];
  	    $stored   = (string)$result->fields['admin_security'];
  	    $result->MoveNext();
  	    $levels   = gen_parse_permissions($stored);
  	    // 3 as a NUMBER, because SECURITY_ID_PHREEFORM no longer exists: the
  	    // module that defined it is deleted, and a migration whose job is to
  	    // carry an old value forward cannot depend on the old name surviving.
  	    if (!isset($levels[3]) || isset($levels[SECURITY_ID_DASHBOARDS])) continue;
  	    $levels[SECURITY_ID_DASHBOARDS] = $levels[3];
  	    $pairs = array();
  	    foreach ($levels as $key => $level) $pairs[] = $key . ':' . $level;
  	    $db->Execute("update " . TABLE_USERS . " set admin_security = '"
  	        . $db->prepare_input(implode(',', $pairs)) . "' where admin_id = " . $id);
  	  }
  	}

    if (!$error) {
	  write_configure('MODULE_' . strtoupper($module) . '_STATUS', constant('MODULE_' . strtoupper($module) . '_VERSION'));
   	  $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, constant('MODULE_' . strtoupper($module) . '_VERSION')), 'success');
	}
	return $error;
  }

  function remove($module) {
  }

  function release_update($module, $version, $path = '') {
    global $db, $messageStack;
	$error = false;
	if (file_exists($path)) { include_once($path); }
	write_configure('MODULE_' . strtoupper($module) . '_STATUS', $version);
	return $error ? false : $version;
  }

  function load_reports($module) {
  }

  function load_demo() {
  }

}
?>