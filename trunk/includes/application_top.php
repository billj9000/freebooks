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
//  Path: /includes/application_top.php
//
define('PAGE_EXECUTION_START_TIME', microtime(true));
if (ini_get('max_execution_time') < 60) set_time_limit(60);
$currencies        = ''; 
$force_reset_cache = false;
// set php_self in the local scope
if (!isset($PHP_SELF)) $PHP_SELF = $_SERVER['PHP_SELF'];
// Check for application configuration parameters
if     (file_exists('includes/configure.php')) { require('includes/configure.php'); } 
elseif (file_exists('install/index.php')) { header('Location: install/index.php'); exit(); }
else   die('Phreedom cannot find the configuration file. Aborting!');
// Load some path constants
$path = (ENABLE_SSL_ADMIN == 'true' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN;
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES','my_files/');
define('DIR_WS_FULL_PATH', $path);	// full http path (or https if secure)
define('DIR_WS_MODULES',   'modules/');
define('DIR_WS_MY_FILES',  PATH_TO_MY_FILES);
// load some file system constants
define('DIR_FS_INCLUDES',  DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MODULES',   DIR_FS_ADMIN . 'modules/');
define('DIR_FS_MY_FILES',  DIR_FS_ADMIN . PATH_TO_MY_FILES);
define('DIR_FS_THEMES',    DIR_FS_ADMIN . 'themes/');
define('FILENAME_DEFAULT', 'index');
if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) require_once(DIR_FS_ADMIN . 'vendor/autoload.php');
// set the type of request (secure or not). Behind a reverse proxy that
// terminates TLS itself (Fly, Traefik, nginx, most CDNs/load balancers), the
// app never sees $_SERVER['HTTPS'] — the original protocol is signaled via
// the standard X-Forwarded-Proto header instead. The checks below read it too:
// without it every request is redirected between HTTP and HTTPS forever.
$request_type = (
  (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')) ||
  strstr(strtoupper($_SERVER['HTTP_X_FORWARDED_BY'] ?? ''), 'SSL') ||
  strstr(strtoupper($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''), 'SSL') ||
  strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == 'https'
) ? 'SSL' : 'NONSSL';
// define the inventory types that are tracked in cost of goods sold
define('COG_ITEM_TYPES','si,sr,ms,mi,ma,sa');
$_session_timeout = defined('SESSION_TIMEOUT_ADMIN') ? (int)SESSION_TIMEOUT_ADMIN : 360;
@ini_set('session.gc_maxlifetime', ($_session_timeout < 360 ? 360 : $_session_timeout));
$_REQUEST = array_merge($_GET, $_POST);
// The line below breaks the $_REQUEST string as array_map expects 2 parameters and the second array is null thus erasing all the values in $_REQUEST while keeping the keys.
//$_REQUEST = array_map('mysql_real_escape_string', $_REQUEST); // this should pas all variables in the $_REQUEST pas the db_prepare_input function
// SameSite=Lax (not Strict): OAuth2 provider redirects (Google/Microsoft) land back on
// oauth2_callback.php via a cross-site top-level GET navigation — Strict withholds the
// session cookie on that request entirely, breaking $_SESSION['company'] and surfacing as
// "Session expired" in the callback. Lax still blocks cross-site POST/embed CSRF, just not
// top-level GET redirects, which this flow depends on.
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => ENABLE_SSL_ADMIN === 'true']);
session_start();
$session_started = true;
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// Which page of a list is being looked at. Pages compute their SQL offset as
// MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1) before splitPageResults
// applies its own max(1, ...) guard, so without this an absent parameter asks
// for LIMIT -20,20 - a syntax error. Nothing distinguishes an absent list from
// page 1: no page tests isset($_REQUEST['list']).
$_REQUEST['list'] = max(1, (int)($_REQUEST['list'] ?? 1));
// set the language
if   (isset($_GET['language'])) { $_SESSION['language'] = $_GET['language']; } 
elseif (empty($_SESSION['language'])) { $_SESSION['language'] = defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en_us'; }
// It names a directory that language files are included from, and can arrive
// before login: a locale name such as en_us, nothing else.
if (!is_string($_SESSION['language']) || !preg_match('/^[a-z]{2}_[a-z]{2}$/', $_SESSION['language'])) $_SESSION['language'] = 'en_us';
// see if the user is logged in
$user_validated = isset($_SESSION['admin_id']) ? true : false;
// load general language translation, Check for global define overrides first
$path = DIR_FS_MODULES . 'phreedom/custom/language/' . $_SESSION['language'] . '/language.php';
if (file_exists($path)) { require_once($path); }
$path = DIR_FS_MODULES . 'phreedom/language/' . $_SESSION['language'] . '/language.php';
if (file_exists($path)) { require_once($path); } 
else { require_once(DIR_FS_MODULES . 'phreedom/language/en_us/language.php'); }
// define general functions and classes used application-wide
require_once(DIR_FS_MODULES  . 'phreedom/defaults.php');
require_once(DIR_FS_INCLUDES . 'common_functions.php');
require_once(DIR_FS_INCLUDES . 'common_classes.php');
gen_apply_timezone(); // the installation's default until a company's configuration is loaded
set_error_handler("PhreebooksErrorHandler");
set_exception_handler('PhreebooksExceptionHandler');
// pull in the custom language over-rides for this module/page
$custom_path = DIR_FS_MODULES . $module . '/custom/pages/' . $page . '/extra_defines.php';
if (file_exists($custom_path)) { include($custom_path); }
gen_pull_language($module);
// The theme names a directory template_index.php require_once()s config.php and
// menu_bs.php from, and the colour names one a stylesheet is linked from. Both
// arrive from the user's own profile form, so both are checked against what is
// installed rather than trusted, and fall back to the default. Checked here as
// well as on save, because a row saved by an earlier release is still read.
$_theme = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['admin_prefs']['theme'] ?? ''));
if ($_theme === '' || !is_dir(DIR_FS_THEMES . $_theme)) $_theme = DEFAULT_THEME;
define('DIR_WS_THEMES', 'themes/' . $_theme . '/');
$_colors = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['admin_prefs']['colors'] ?? ''));
if ($_colors === '' || !is_dir(DIR_FS_THEMES . $_theme . '/css/' . $_colors)) $_colors = DEFAULT_COLORS;
define('MY_COLORS', $_colors);
define('MY_MENU',        isset($_SESSION['admin_prefs']['menu'])       ?$_SESSION['admin_prefs']['menu']       :DEFAULT_MENU);
define('MY_MENU_HOVER',  isset($_SESSION['admin_prefs']['menu_hover']) ?$_SESSION['admin_prefs']['menu_hover'] :0);
define('DIR_WS_IMAGES', DIR_WS_THEMES . 'images/');
if (file_exists(DIR_WS_THEMES . 'icons/')) { define('DIR_WS_ICONS',  DIR_WS_THEMES . 'icons/'); }
else { define('DIR_WS_ICONS', 'themes/default/icons/'); } // use default
$messageStack = new messageStack;
$toolbar      = new toolbar;
// determine what company to connect to
$db_company = (isset($_SESSION['company'])) ? $_SESSION['company'] : ($_SESSION['companies'][$_POST['company'] ?? ''] ?? '');
// A password-reset link is clicked cold — often on a different device with a fresh session that has
// no company list yet — so the reset flow names its company directly in the URL. basename() blocks
// path traversal and the file_exists() check below still gates it to a real installed company.
if (!$db_company && in_array($_REQUEST['action'] ?? '', array('pw_reset_form', 'pw_reset_save'), true) && isset($_REQUEST['company'])) {
  $db_company = basename((string)$_REQUEST['company']);
}
if ($db_company && file_exists(DIR_FS_MY_FILES . $db_company . '/config.php')) {
  define('DB_DATABASE', $db_company);
  require_once(DIR_FS_MY_FILES . $db_company . '/config.php');
  if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER); // for old PhreeBooks installs
  // Load queryFactory db classes
  require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
  $db = new queryFactory();
  $db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
  // set application wide parameters for phreebooks module
  $result = $db->Execute_return_error("select configuration_key, configuration_value from " . DB_PREFIX . "configuration");
  if ($db->error_number != '' || $result->RecordCount() == 0) throw new RuntimeException(LOAD_CONFIG_ERROR);
  while (!$result->EOF) {
	define($result->fields['configuration_key'], $result->fields['configuration_value']);
	$result->MoveNext();
  }
  gen_apply_timezone(); // now the company's own
  // DEBUG is now known — only show PHP errors to the browser when it's explicitly on.
  ini_set('display_errors', (defined('DEBUG') && DEBUG) ? '1' : '0');
  if (!defined('DATE_FORMAT_CALENDAR')) {
    $cal_format = defined('DATE_FORMAT') ? preg_replace(array('/m/','/d/','/Y/'), array('mm','dd','yy'), DATE_FORMAT) : 'mm-dd-yy';
    define('DATE_FORMAT_CALENDAR', $cal_format);
  }
  // search the list modules and load configuration files and language files
  gen_pull_language('phreedom', 'menu');
  gen_pull_language('phreebooks', 'menu');
  require_once(DIR_FS_MODULES . 'phreedom/config.php');
  $messageStack->debug_header();
  $loaded_modules = array();
  $dirs = scandir(DIR_FS_MODULES);
  foreach ($dirs as $dir) { // first pull all module language files, loaded or not
    if ($dir == '.' || $dir == '..') continue;
	if (is_dir(DIR_FS_MODULES . $dir)) gen_pull_language($dir, 'menu');
  	if (defined('MODULE_' . strtoupper($dir) . '_STATUS')) { // module is loaded
	  $loaded_modules[] = $dir;
      require_once(DIR_FS_MODULES . $dir . '/config.php');
    } 
  }
  // pull in the custom language over-rides for this module (to pre-define the standard language)
  $path = DIR_FS_MODULES . $module . '/custom/pages/' . $page . '/extra_menus.php';
  if (file_exists($path)) { include($path); }
  $currencies = new currencies();
  // The users table, brought to shape before anyone can log in.
  //
  // Deliberately duplicates a section of the 1.0.0 migration. That migration runs
  // from the module cascade, which runs only after a successful login, and the
  // login query selects failed_attempts and locked_until: a database missing them
  // could never log in to reach the migration that adds them.
  //
  // One catalogue read per request; the ALTERs fire once in a database's life.
  $cols = array();
  $have = $db->Execute("SHOW COLUMNS FROM " . TABLE_USERS);
  while (!$have->EOF) { $cols[$have->fields['Field']] = $have->fields['Type']; $have->MoveNext(); }
  // A bcrypt hash needs 255 characters; a sha1 digest needed 40.
  if (isset($cols['admin_pass']) && stripos($cols['admin_pass'], 'varchar(40)') !== false) {
    $db->Execute("ALTER TABLE " . TABLE_USERS . " MODIFY admin_pass VARCHAR(255) NOT NULL DEFAULT ''");
  }
  // One guard per column: a pair guarded on the first alone breaks on a database
  // that has the second and not the first. pw_reset_token holds a SHA-256 hash
  // of the token e-mailed to the user, never the token itself.
  foreach (array(
      'failed_attempts'  => "INT(11) NOT NULL DEFAULT '0'",
      'locked_until'     => "DATETIME NULL DEFAULT NULL",
      'pw_reset_token'   => "CHAR(64) NULL DEFAULT NULL",
      'pw_reset_expires' => "DATETIME NULL DEFAULT NULL",
  ) as $column => $declaration) {
    if (!isset($cols[$column])) {
      $db->Execute("ALTER TABLE " . TABLE_USERS . " ADD COLUMN $column $declaration");
    }
  }
}
$_session_timeout = defined('SESSION_TIMEOUT_ADMIN') ? (int)SESSION_TIMEOUT_ADMIN : $_session_timeout;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > ($_session_timeout < 360 ? 360 : $_session_timeout))) {
	$_SESSION = array('language'=>$_SESSION['language'] ?? null, 'companies'=>$_SESSION['companies'] ?? null);
	gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL'));
}
$_SESSION['LAST_ACTIVITY'] = time();
$prefered_type = ENABLE_SSL_ADMIN == 'true' ? 'SSL' : 'NONSSL';
if ($request_type <> $prefered_type) gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL')); // re-direct if SSL request not matching actual request
if ($user_validated && !defined('DEFAULT_CURRENCY')) $messageStack->add(ERROR_NO_DEFAULT_CURRENCY_DEFINED, 'error'); // check for default currency defined

?>