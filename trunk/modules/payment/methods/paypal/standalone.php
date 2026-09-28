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
//  Path: /modules/payment/methods/paypal/standalone.php
//
// Starts FreeBooks for a PayPal page that PayPal or a paying customer calls
// directly, with no login: paypal_webhook.php and paypal_return.php. It chooses
// the company, connects to its database, loads its settings, the journal
// classes and the PayPal method, and leaves $db, $currencies, $messageStack and
// $pp (the PayPal method) ready.
//
// Include it from the top level of the calling script, so that what it defines
// is global. It does not end the request: it evaluates to true when FreeBooks is
// ready and false when it is not, with the reason in $pp_standalone_error, and
// the caller decides what to answer. $pp_standalone_status is the HTTP status that
// fits: 400 when the request named no usable company, 500 otherwise.

define('PAGE_EXECUTION_START_TIME', microtime(true));

$trunk = dirname(__DIR__, 4) . '/';
if (!file_exists($trunk . 'includes/configure.php')) {
    $pp_standalone_error  = 'configure.php not found';
    $pp_standalone_status = 500;
    return false;
}
require($trunk . 'includes/configure.php');

if (!defined('DIR_FS_ADMIN')) define('DIR_FS_ADMIN', $trunk);

// ─── Path constants ───────────────────────────────────────────────────────────
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');
define('DIR_WS_MODULES',   'modules/');
define('DIR_WS_MY_FILES',  PATH_TO_MY_FILES);
define('DIR_FS_INCLUDES',  DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MODULES',   DIR_FS_ADMIN . 'modules/');
define('DIR_FS_MY_FILES',  DIR_FS_ADMIN . PATH_TO_MY_FILES);
define('DIR_FS_THEMES',    DIR_FS_ADMIN . 'themes/');
define('FILENAME_DEFAULT', 'index');
define('COG_ITEM_TYPES',   'si,sr,ms,mi,ma,sa');
define('DIR_WS_ICONS',     '');

if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) {
    require_once(DIR_FS_ADMIN . 'vendor/autoload.php');
}

// ─── Minimal server context ───────────────────────────────────────────────────
if (!isset($_SERVER['HTTP_HOST']))   $_SERVER['HTTP_HOST']   = 'localhost';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (!isset($_REQUEST)) $_REQUEST = [];
if (!isset($_GET))     $_GET     = [];
if (!isset($_POST))    $_POST    = [];
$_SESSION = ['language' => 'en_us', 'admin_id' => 1];

// ─── Company ─────────────────────────────────────────────────────────────────
// The URL names the company, as ?company=<name>. An installation with a single
// company may leave it out. With several, an unnamed call is refused: guessing
// would put one company's payment into another's books. The name is matched
// exactly against the company directories, so it can only ever select one of
// them.
$companies = [];
$mf = DIR_FS_MY_FILES;
if (is_dir($mf)) {
    foreach (scandir($mf) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir($mf . $entry) && file_exists($mf . $entry . '/config.php')) $companies[] = $entry;
    }
}
$requested = (string)($_GET['company'] ?? '');
if ($requested !== '') {
    if (!in_array($requested, $companies, true)) {
        $pp_standalone_error  = 'no company named in ?company= exists';
        $pp_standalone_status = 400;
        return false;
    }
    $company = $requested;
} elseif (count($companies) === 1) {
    $company = $companies[0];
} else {
    $pp_standalone_error  = count($companies) . ' companies and none named; add ?company=<name> to the URL';
    $pp_standalone_status = 400;
    return false;
}
$_SESSION['company'] = $company;

// ─── Shared code ──────────────────────────────────────────────────────────────
$_prev_er = error_reporting(E_ALL & ~E_WARNING);
require_once(DIR_FS_MODULES . 'phreedom/language/en_us/language.php');
require_once(DIR_FS_MODULES . 'phreedom/defaults.php');
require_once(DIR_FS_INCLUDES . 'common_functions.php');
require_once(DIR_FS_INCLUDES . 'common_classes.php');
error_reporting($_prev_er); unset($_prev_er);

// ─── Database connection and settings ─────────────────────────────────────────
define('DB_DATABASE', $company);
require_once(DIR_FS_MY_FILES . $company . '/config.php');
if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
$db = new queryFactory();
$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

$_cfg = $db->Execute("SELECT configuration_key, configuration_value FROM " . DB_PREFIX . "configuration");
while (!$_cfg->EOF) {
    if (!defined($_cfg->fields['configuration_key'])) {
        define($_cfg->fields['configuration_key'], $_cfg->fields['configuration_value']);
    }
    $_cfg->MoveNext();
}
unset($_cfg);

// ─── TABLE_* constants ────────────────────────────────────────────────────────
define('TABLE_AUDIT_LOG',              DB_PREFIX . 'audit_log');
define('TABLE_CONFIGURATION',          DB_PREFIX . 'configuration');
define('TABLE_CURRENCIES',             DB_PREFIX . 'currencies');
define('TABLE_CURRENT_STATUS',         DB_PREFIX . 'current_status');
define('TABLE_DATA_SECURITY',          DB_PREFIX . 'data_security');
define('TABLE_EXTRA_FIELDS',           DB_PREFIX . 'xtra_fields');
define('TABLE_EXTRA_TABS',             DB_PREFIX . 'xtra_tabs');
define('TABLE_USERS',                  DB_PREFIX . 'users');
define('TABLE_ACCOUNTING_PERIODS',     DB_PREFIX . 'accounting_periods');
define('TABLE_ACCOUNTS_HISTORY',       DB_PREFIX . 'accounts_history');
define('TABLE_CHART_OF_ACCOUNTS',      DB_PREFIX . 'chart_of_accounts');
define('TABLE_CHART_OF_ACCOUNTS_HISTORY', DB_PREFIX . 'chart_of_accounts_history');
define('TABLE_INVENTORY_ASSY_LIST',    DB_PREFIX . 'inventory_assy_list');
define('TABLE_INVENTORY_COGS_OWED',    DB_PREFIX . 'inventory_cogs_owed');
define('TABLE_INVENTORY_COGS_USAGE',   DB_PREFIX . 'inventory_cogs_usage');
define('TABLE_INVENTORY_HISTORY',      DB_PREFIX . 'inventory_history');
define('TABLE_INVENTORY_MS_LIST',      DB_PREFIX . 'inventory_ms_list');
define('TABLE_INVENTORY_PURCHASE',     DB_PREFIX . 'inventory_purchase_details');
define('TABLE_JOURNAL_ITEM',           DB_PREFIX . 'journal_item');
define('TABLE_JOURNAL_MAIN',           DB_PREFIX . 'journal_main');
define('TABLE_RECONCILIATION',         DB_PREFIX . 'reconciliation');
define('TABLE_TAX_AUTH',               DB_PREFIX . 'tax_authorities');
define('TABLE_TAX_RATES',              DB_PREFIX . 'tax_rates');
define('TABLE_ADDRESS_BOOK',           DB_PREFIX . 'address_book');
define('TABLE_CONTACTS',               DB_PREFIX . 'contacts');
define('TABLE_CONTACTS_LOG',           DB_PREFIX . 'contacts_log');
define('TABLE_DEPARTMENTS',            DB_PREFIX . 'departments');
define('TABLE_PRICE_SHEETS',           DB_PREFIX . 'price_sheets');
define('TABLE_INVENTORY',              DB_PREFIX . 'inventory');
define('TABLE_INVENTORY_SPECIAL_PRICES', DB_PREFIX . 'inventory_special_prices');
define('TABLE_SHIPPING_LOG',           DB_PREFIX . 'shipping_log');

// ─── Core objects and language ────────────────────────────────────────────────
$messageStack = new messageStack;
$currencies   = new currencies();

$_prev_er = error_reporting(E_ALL & ~E_WARNING);
gen_pull_language('contacts');
gen_pull_language('phreebooks');
gen_pull_language('phreebooks', 'menu');
// The payment base class constructor reads its setting labels from the payment
// module's admin strings, so constructing the PayPal method needs them.
gen_pull_language('payment', 'admin');
error_reporting($_prev_er); unset($_prev_er);

// ─── Journal classes ──────────────────────────────────────────────────────────
require_once(DIR_FS_MODULES . 'phreebooks/classes/gen_ledger.php');
require_once(DIR_FS_MODULES . 'phreebooks/classes/orders.php');
require_once(DIR_FS_MODULES . 'phreebooks/classes/banking.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');

// ─── PayPal method ────────────────────────────────────────────────────────────
require_once(DIR_FS_MODULES . 'payment/classes/payment.php');
load_specific_method('payment', 'paypal');
$pp = new paypal();

return true;
