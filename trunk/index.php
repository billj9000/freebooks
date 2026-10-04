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
// Path: /index.php
//
ob_start();
// display_errors is decided once DEBUG (seeded from pb_configuration) is known — see
// includes/application_top.php. Default to off here so an uncaught error/deprecation
// before that point doesn't leak stack traces/paths/query text to visitors.
ini_set('display_errors', '0');
ini_set('max_input_vars', '3000');
error_reporting(E_ALL ^ E_NOTICE);
if (isset($_POST['module']))    $module = $_POST['module'];
elseif (isset($_GET['module'])) $module = $_GET['module'];
else                            $module = 'phreedom';
if (isset($_POST['page']))      $page = $_POST['page'];
elseif (isset($_GET['page']))   $page = $_GET['page'];
else                     		$page = 'main';
// Both name a directory on the server, as does an ajax op a file: a plain name,
// nothing else, so no request can reach outside modules/<module>/pages|ajax.
if (!is_string($module) || !preg_match('/^[A-Za-z0-9_]+$/', $module)) $module = 'phreedom';
if (!is_string($page)   || !preg_match('/^[A-Za-z0-9_]+$/', $page))   $page   = 'main';
$user_validated = false;
require_once('includes/application_top.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf_ok = isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])
          && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
  if (!$csrf_ok) {
    if ($page == 'ajax') { echo createXmlHeader() . xmlEntry('error', 'Invalid request token.') . createXmlFooter(); }
    else { http_response_code(403); die('Invalid request token.'); }
    die;
  }
}
if (!$user_validated) {
  if ($page == 'ajax'){
	echo createXmlHeader() . xmlEntry('error', SORRY_YOU_ARE_LOGGED_OUT) . createXmlFooter();
	die;
  }
  $_SESSION['pb_cat']    = isset($_GET['module']) 	? $_GET['module']	: '';
  $_SESSION['pb_module'] = isset($_GET['page']) 	? $_GET['page']		: '';
  $_SESSION['pb_jID']    = isset($_GET['jID']) 		? $_GET['jID'] 		: '';
  $_SESSION['pb_type']   = isset($_GET['type'])		? $_GET['type']		: '';
  $module = 'phreedom';
  $page   = 'main';
  // 'validate' is the password login post; the pk_login_* actions are the
  // two steps of a passkey login ceremony (see phreedom/pages/main/pre_process.php);
  // pw_reset_form/pw_reset_save are the two steps of a lost-password reset reached from an
  // e-mailed token link - all are legitimately performed while logged out. Everything else
  // forces the login screen. (The older ?req=pw_lost_* form is handled inside pre_process.)
  $pre_auth_actions = array('validate', 'pk_login_begin', 'pk_login_finish', 'pw_reset_form', 'pw_reset_save');
  if (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], $pre_auth_actions, true)) $_REQUEST['action'] = 'login';
} else {
  unset($_SESSION['pb_cat']);
  unset($_SESSION['pb_module']);
  unset($_SESSION['pb_jID']);
  unset($_SESSION['pb_type']);
}
// A module can sit on disk without being installed. The optional ones are copied
// in from extensions/ and only become real when their STATUS is written, and
// until then application_top never loads their config.php - so their constants,
// the security ids and table names, do not exist. Routing to one anyway reaches
// its first constant and dies there: a blank page, or an ajax reply carrying an
// uncaught Error. Dispatch only to what is installed.
//
// $loaded_modules is built by application_top for an authenticated request; if
// it is absent this is the login path, which routes nowhere but phreedom.
if (!empty($loaded_modules) && $module <> 'phreedom' && !in_array($module, $loaded_modules, true)) {
  $messageStack->add(sprintf(GEN_ERROR_MODULE_NOT_INSTALLED, htmlspecialchars($module)), 'error');
  gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=phreedom&page=main', 'SSL'));
}
if ($page == 'ajax') {
  $op = isset($_GET['op']) && is_string($_GET['op']) && preg_match('/^[A-Za-z0-9_]+$/', $_GET['op']) ? $_GET['op'] : '';
  if ($op === '') die;
  $pre_process_path = DIR_FS_MODULES . $module . '/custom/ajax/' . $op . '.php';
  if (file_exists($pre_process_path)) { require($pre_process_path); die; }
  $pre_process_path = DIR_FS_MODULES . $module . '/ajax/' . $op . '.php';
  if (file_exists($pre_process_path)) { require($pre_process_path); die; }
  die; // go no further
}
$custom_html      = false;
$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
$pre_process_path = DIR_FS_MODULES . $module . '/pages/' . $page . '/pre_process.php';
if (file_exists($pre_process_path)) { define('DIR_FS_WORKING', DIR_FS_MODULES . $module . '/'); }
  else throw new RuntimeException('No pre_process file, looking for the file: ' . $pre_process_path);
require($pre_process_path); 
if (file_exists(DIR_FS_WORKING . 'custom/pages/' . $page . '/' . $include_template)) {
  $template_path = DIR_FS_WORKING . 'custom/pages/' . $page . '/' . $include_template;
} else {
  $template_path = DIR_FS_WORKING . 'pages/' . $page . '/' . $include_template;
}
require('includes/template_index.php');
require('includes/application_bottom.php');
ob_end_flush();
session_write_close();
?>