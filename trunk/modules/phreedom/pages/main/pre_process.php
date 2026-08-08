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
//  Path: /modules/phreedom/pages/main/pre_process.php
//
/**************   Check user security   *****************************/
// Not here because may not be logged in
/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/phreedom.php');
require_once(DIR_FS_WORKING . 'functions/webauthn.php');
/**************   page specific initialization  *************************/
$error        = false; 
$menu_id      = isset($_GET['mID']) ? $_GET['mID'] : 'index'; // default to index unless heading is passed
if (isset($_GET['req']) && $_GET['req'] == 'pw_lost_req') $_REQUEST['action'] = 'pw_lost_req';
if (isset($_GET['req']) && $_GET['req'] == 'pw_lost_sub') $_REQUEST['action'] = 'pw_lost_sub';
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'validate':
    $admin_name     = db_prepare_input($_POST['admin_name']);
    $admin_pass     = db_prepare_input($_POST['admin_pass']);
    $admin_company  = $_SESSION['companies'][$_POST['company']];
    $admin_language = db_prepare_input($_POST['language']);
    $sql = "select admin_id, admin_name, inactive, display_name, admin_email, admin_pass, account_id, admin_prefs, admin_security, failed_attempts, locked_until,
        (locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP()) AS is_locked
		from " . TABLE_USERS . " where admin_name = '" . db_input($admin_name) . "'";
	$result = false;
    if ($db->db_connected) $result = $db->Execute($sql);
	if (!$result || $admin_name <> $result->fields['admin_name'] || $result->fields['inactive']) {
      $error = true;
      $messageStack->add(ERROR_WRONG_LOGIN, 'error');
      pw_validate_password($admin_pass, '$2y$12$invalidhashusedtoequaliseresponsetime000000000000000000'); // timing equaliser — prevents username enumeration via response time
    }
    if (!$error && $result->fields['is_locked']) {
      $error = true;
      $messageStack->add(ERROR_ACCOUNT_LOCKED, 'error');
    }
    if (!$error && !pw_validate_password($admin_pass, $result->fields['admin_pass'])) {
      $error = true;
      $messageStack->add(ERROR_WRONG_LOGIN, 'error');
      $new_attempts = (int)$result->fields['failed_attempts'] + 1;
      $lock_clause  = $new_attempts >= LOGIN_MAX_ATTEMPTS ? ', locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . LOGIN_LOCKOUT_MINUTES . ' MINUTE)' : '';
      $db->Execute("UPDATE " . TABLE_USERS . " SET failed_attempts = $new_attempts$lock_clause WHERE admin_id = " . (int)$result->fields['admin_id']);
    }
    if (!$error && pw_needs_rehash($result->fields['admin_pass'])) {
      $db->Execute("update " . TABLE_USERS . " set admin_pass = '" . db_input(pw_encrypt_password($admin_pass)) . "' where admin_id = " . (int)$result->fields['admin_id']);
    }
    if (!$error) {
	  pb_establish_session($result->fields, $admin_company, $admin_language);
	  gen_add_audit_log(GEN_LOG_LOGIN . $admin_name);
	  // check for session timeout to reload to requested page
	  $get_params = '';
	  if (isset($_SESSION['pb_cat']) && $_SESSION['pb_cat']) {
	    $get_params  = 'module='    . $_SESSION['pb_cat'];
	    $get_params .= '&amp;page=' . $_SESSION['pb_module'];
	    if (isset($_SESSION['pb_jID']) && $_SESSION['pb_jID'])  $get_params .= '&amp;jID='  . $_SESSION['pb_jID'];
	    if (isset($_SESSION['pb_type']) && $_SESSION['pb_type']) $get_params .= '&amp;type=' . $_SESSION['pb_type'];
	  }
	  // check safe mode
	  if (get_cfg_var('safe_mode')) $messageStack->add_session(SAFE_MODE_ERROR, 'error');
      gen_redirect(html_href_link(FILENAME_DEFAULT, $get_params, 'SSL'));
    } else {
	  // Note: This is assigned to admin id = 1 since the user is not logged in.
	  gen_add_audit_log(GEN_LOG_LOGIN_FAILED . $admin_name);
	}
	$_REQUEST['action'] = 'login';
	break;
  case 'pk_login_begin':
    // step 1 of a passkey login: hand the browser a challenge to sign.
    // No credential ids are offered (discoverable/resident credentials only),
    // so the authenticator itself decides which passkey (if any) it has for this site.
    header('Content-Type: application/json');
    if (!wa_enabled()) { echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_DISABLED)); exit; }
    try {
      $webAuthn = wa_new_instance();
    } catch (\Throwable $e) {
      echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_SERVER));
      exit;
    }
    $pk_args = $webAuthn->getGetArgs(array(), 30, true, true, true, true, true, 'preferred');
    wa_store_challenge($webAuthn->getChallenge()->getBinaryString(), 'login');
    echo json_encode(array('ok' => true, 'publicKey' => $pk_args->publicKey));
    exit;
  case 'pk_login_finish':
    // step 2: verify the signed assertion, identify the account by the
    // credential id (never by a posted username), and log in exactly the
    // way a password login does.
    header('Content-Type: application/json');
    $pk_challenge   = wa_consume_challenge('login');
    $pk_cred_id     = db_prepare_input($_POST['cred_id'] ?? '');
    $pk_client_data = $_POST['client_data'] ?? '';
    $pk_auth_data   = $_POST['authenticator_data'] ?? '';
    $pk_signature   = $_POST['signature'] ?? '';
    $pk_user_handle = $_POST['user_handle'] ?? '';
    if (!wa_enabled() || !$pk_challenge || !$pk_cred_id || !$pk_client_data || !$pk_auth_data || !$pk_signature || !$pk_user_handle) {
      gen_add_audit_log(GEN_LOG_LOGIN_FAILED . '(passkey)');
      echo json_encode(array('ok' => false, 'message' => ERROR_WRONG_LOGIN));
      exit;
    }
    $pk_cred = wa_get_credential_by_cred_id($pk_cred_id);
    if (!$pk_cred || bin2hex(wa_b64url_decode($pk_user_handle)) !== $pk_cred['user_handle']) {
      gen_add_audit_log(GEN_LOG_LOGIN_FAILED . '(passkey: unknown credential)');
      echo json_encode(array('ok' => false, 'message' => ERROR_WRONG_LOGIN));
      exit;
    }
    $pk_user = $db->Execute("select admin_id, admin_name, inactive, display_name, admin_email, account_id, admin_prefs, admin_security, locked_until,
        (locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP()) AS is_locked
        from " . TABLE_USERS . " where admin_id = " . (int)$pk_cred['admin_id']);
    if (!$pk_user || $pk_user->EOF || $pk_user->fields['inactive']) {
      gen_add_audit_log(GEN_LOG_LOGIN_FAILED . '(passkey: inactive user)');
      echo json_encode(array('ok' => false, 'message' => ERROR_WRONG_LOGIN));
      exit;
    }
    if ($pk_user->fields['is_locked']) {
      echo json_encode(array('ok' => false, 'message' => ERROR_ACCOUNT_LOCKED));
      exit;
    }
    try {
      $webAuthn = wa_new_instance();
      $webAuthn->processGet(
        wa_b64url_decode($pk_client_data),
        wa_b64url_decode($pk_auth_data),
        wa_b64url_decode($pk_signature),
        $pk_cred['public_key'],
        $pk_challenge,
        (int)$pk_cred['sign_count'],
        false, // requireUserVerification - login was requested with 'preferred', not 'required'
        true   // requireUserPresent
      );
    } catch (\Throwable $e) {
      gen_add_audit_log(GEN_LOG_LOGIN_FAILED . '(passkey: ' . $pk_user->fields['admin_name'] . ')');
      echo json_encode(array('ok' => false, 'message' => ERROR_WRONG_LOGIN));
      exit;
    }
    $pk_new_count = $webAuthn->getSignatureCounter();
    wa_update_sign_count($pk_cred['id'], $pk_new_count !== null ? $pk_new_count : $pk_cred['sign_count']);
    $admin_company  = $_SESSION['companies'][$_POST['company']] ?? ($_SESSION['company'] ?? '');
    $admin_language = db_prepare_input($_POST['language'] ?? DEFAULT_LANGUAGE);
    pb_establish_session($pk_user->fields, $admin_company, $admin_language);
    gen_add_audit_log(GEN_LOG_LOGIN . $pk_user->fields['admin_name'] . ' (passkey)');
    $get_params = '';
    if (isset($_SESSION['pb_cat']) && $_SESSION['pb_cat']) {
      $get_params  = 'module='    . $_SESSION['pb_cat'];
      $get_params .= '&amp;page=' . $_SESSION['pb_module'];
      if (isset($_SESSION['pb_jID']) && $_SESSION['pb_jID'])  $get_params .= '&amp;jID='  . $_SESSION['pb_jID'];
      if (isset($_SESSION['pb_type']) && $_SESSION['pb_type']) $get_params .= '&amp;type=' . $_SESSION['pb_type'];
    }
    echo json_encode(array('ok' => true, 'redirect' => html_href_link(FILENAME_DEFAULT, $get_params, 'SSL')));
    exit;
  case 'pw_lost_sub':
    $admin_email = db_prepare_input($_POST['admin_email']);
    $result = $db->Execute("select admin_id, admin_name, admin_email 
	  from " . TABLE_USERS . " where admin_email = '" . db_input($admin_email) . "'");
    if (!$admin_email || $admin_email <> $result->fields['admin_email']) {
      $error = true;
      $messageStack->add(ERROR_WRONG_EMAIL, 'error');
    }
    $_SESSION['company'] = $_SESSION['companies'][$_POST['company']];
    if (!$error) {
      $new_password = pw_create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
      $admin_pass   = pw_encrypt_password($new_password);
      $db->Execute("update " . TABLE_USERS . " set admin_pass = '" . db_input($admin_pass) . "' where admin_id = " . $result->fields['admin_id']);
      $html_msg['EMAIL_CUSTOMERS_NAME'] = $result->fields['admin_name'];
      $html_msg['EMAIL_MESSAGE_HTML']   = sprintf(TEXT_EMAIL_MESSAGE, COMPANY_NAME, $new_password);
      validate_send_mail($result->fields['admin_name'], $result->fields['admin_email'], TEXT_EMAIL_SUBJECT, $html_msg['EMAIL_MESSAGE_HTML'], COMPANY_NAME, EMAIL_FROM, $html_msg);
      $messageStack->add(SUCCESS_PASSWORD_SENT, 'success');
	  gen_add_audit_log(GEN_LOG_RESEND_PW . $admin_email);
    }
    break;
  case 'logout':
	$result = $db->Execute("select admin_name from " . TABLE_USERS . " where admin_id = " . $_SESSION['admin_id']);
	gen_add_audit_log(GEN_LOG_LOGOFF . $result->fields['admin_name']);
	session_destroy();
	gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL'));
	break;
  case 'save':
	$dashboard_id = db_prepare_input($_POST['dashboard_id']);
	// since we don't know where the module is, go find it.
	if (!isset($dirs)) $dirs = scandir(DIR_FS_MODULES);
	$module_id = '';
	foreach ($dirs as $dir) {
	  if (defined('MODULE_'.strtoupper($dir).'_STATUS') && file_exists(DIR_FS_MODULES . "$dir/dashboards/")) {
		$choices = scandir(DIR_FS_MODULES . "$dir/dashboards/");
		foreach ($choices as $name) if ($name == $dashboard_id) { $module_id = $dir; break; }
	  }
	  if ($module_id <> '') break;
	}
	// $dashboard_id comes from $_POST and is used below both as a require_once path
	// segment and as a class name in "new $dashboard_id". Only proceed if it matched
	// a real dashboard directory above; otherwise a crafted value could traverse the
	// filesystem or instantiate an arbitrary class.
	if ($module_id === '') break;
    load_method_language(DIR_FS_MODULES . "$module_id/dashboards/$dashboard_id");
	require_once (DIR_FS_MODULES . "$module_id/dashboards/$dashboard_id/$dashboard_id.php");
    $new_box 				= new $dashboard_id;
	$new_box->dashboard_id 	= $dashboard_id;
	$new_box->menu_id      	= $menu_id;
	$new_box->params        = array();
	$new_box->Update();
	break;
  case 'delete':
	$dashboard_id = db_prepare_input($_POST['dashboard_id']);
	$result = $db->Execute("delete from " . TABLE_USERS_PROFILES . " 
	  where user_id=".(int)$_SESSION['admin_id']." and menu_id='".db_input($menu_id)."' and dashboard_id='".db_input($dashboard_id)."'");
	break;
  case 'move_up': 
  case 'move_down':
	$dashboard_id = db_prepare_input($_POST['dashboard_id']);
	$sql = "select column_id, row_id from " . TABLE_USERS_PROFILES . " 
		where user_id=".(int)$_SESSION['admin_id']." and menu_id='".db_input($menu_id)."' and dashboard_id='".db_input($dashboard_id)."'";
	$result         = $db->Execute($sql);
	$current_row    = $result->fields['row_id'];
	$current_column = $result->fields['column_id'];
	$new_row        = ($_REQUEST['action'] == 'move_up') ? ($current_row - 1) : ($current_row + 1);
	$sql = "select max(row_id) as max_row from " . TABLE_USERS_PROFILES . " 
		where user_id=".(int)$_SESSION['admin_id']." and menu_id='".db_input($menu_id)."' and column_id='".db_input($current_column)."'";
	$result         = $db->Execute($sql);
	$max_row        = $result->fields['max_row'];
	if (($new_row >= 1 && $_REQUEST['action'] == 'move_up') || ($new_row <= $max_row && $_REQUEST['action'] == 'move_down')) {
	  $sql = "update " . TABLE_USERS_PROFILES . " set row_id = 0 
		where user_id=".$_SESSION['admin_id']." and menu_id='".db_input($menu_id)."' and column_id=$current_column and row_id='$current_row'";
	  $db->Execute($sql);
	  $sql = "update " . TABLE_USERS_PROFILES . " set row_id=$current_row 
		where user_id=".$_SESSION['admin_id']." and menu_id='".db_input($menu_id)."' and column_id=$current_column and row_id='$new_row'";
	  $db->Execute($sql);
	  $sql = "update " . TABLE_USERS_PROFILES . " set row_id=$new_row   
		where user_id=".$_SESSION['admin_id']." and menu_id='".db_input($menu_id)."' and column_id=$current_column and row_id=0";
	  $db->Execute($sql);
	}
	break;
  case 'move_left':
  case 'move_right':
	$dashboard_id = db_prepare_input($_POST['dashboard_id']);
	$sql = "select column_id, row_id from " . TABLE_USERS_PROFILES . " 
		where user_id = " . $_SESSION['admin_id'] . " and menu_id = '" . $menu_id . "' and dashboard_id = '" . $dashboard_id . "'";
	$result         = $db->Execute($sql);
	$current_row    = $result->fields['row_id'];
	$current_column = $result->fields['column_id'];
	$new_col = ($_REQUEST['action'] == 'move_left') ? ($current_column - 1) : ($current_column + 1);
	if (($new_col >= 1 && $_REQUEST['action'] == 'move_left') || ($new_col <= MAX_CP_COLUMNS && $_REQUEST['action'] == 'move_right')) {
	  $sql = "select max(row_id) as max_row from " . TABLE_USERS_PROFILES . " 
		where user_id = " . $_SESSION['admin_id'] . " and menu_id = '" . $menu_id . "' and column_id = '" . $new_col . "'";
	  $result = $db->Execute($sql);
	  $new_max_row = $result->fields['max_row'] + 1;
	  $sql = "update  " . TABLE_USERS_PROFILES . " set column_id = " . $new_col . ", row_id = " . $new_max_row . " 
		where user_id = " . $_SESSION['admin_id'] . " and menu_id = '" . $menu_id . "' and dashboard_id = '" . $dashboard_id . "'";
	  $db->Execute($sql);
	  $sql = "update  " . TABLE_USERS_PROFILES . " set row_id = row_id - 1 
		where user_id = " . $_SESSION['admin_id'] . " and menu_id = '" . $menu_id . "' 
		and column_id = " . $current_column . " and row_id >= '" . $current_row . "'";
	  $db->Execute($sql);
	}
	break;
  case 'debug':
	$file_name = 'trace.txt';
	if (!$handle = fopen(DIR_FS_MY_FILES . $file_name, "r")) {
	  $messageStack->add(DEBUG_TRACE_MISSING, 'error');
	  gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL'));
	}
	$contents = fread($handle, filesize(DIR_FS_MY_FILES . $file_name));
	fclose($handle);
	$file_size = strlen($contents);
	header('Content-type: text/html; charset=utf-8');
	header("Content-disposition: attachment; filename=" . $file_name . "; size=" . $file_size);
	header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Connection: close');
	header('Expires: ' . date('r', time() + 60 * 60));
	header('Last-Modified: ' . date('r', time()));
	print $contents;
	exit();
  case 'php_info':
  	if ($_SESSION['admin_id'] == 1) die(phpinfo());
  	break;
  default:
}

/*****************   prepare to display templates  *************************/
// prepare to display form
$include_header = true;
$include_footer = true;

switch ($_REQUEST['action']) {
  case 'login':
  case 'pw_lost_sub':
  case 'pw_lost_req':
  	$companies       = load_company_dropdown();
  	$single_company  = count(is_array($companies) ? $companies : [])==1 ? true : false;
  	$languages       = load_language_dropdown();
	$single_language = count(is_array($languages) ? $languages : [])==1 ? true : false;
	if (isset($_POST['company'])) { // find default company
	  $company_index = $_POST['company'];
	} else {
	  $default_company = defined('DEFAULT_COMPANY') ? DEFAULT_COMPANY : '';
	  if (isset($_COOKIE['pb_company'])) $default_company = $_COOKIE['pb_company'];
	  foreach ($_SESSION['companies'] as $key => $value) {
		if ($value == $default_company) $company_index = $key;
	  }
	}
	if (isset($_POST['language'])) { // find default language
	  $language_index = $_POST['language'];
	} else {
	  $default_language = defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en_us';
	  if (isset($_COOKIE['pb_language'])) $default_language = $_COOKIE['pb_language'];
	  foreach ($languages as $value) {
		if ($value['id'] == $default_language) $language_index = $value['id'];
	  }
	}
	$include_template  = $_REQUEST['action'] == 'pw_lost_req' ? 'template_pw_lost.php' : 'template_login.php';
  	$include_header    = false;
	$include_footer    = false;
	define('PAGE_TITLE', TITLE_LOGIN);
    break;
  case 'crash':
	$include_template  = 'template_crash.php';
	define('PAGE_TITLE', TITLE);
	break;
  default: // prepare to display templates
	if (!class_exists('queryFactory')) { // Errors will happen here if there was a problem logging in, logout and restart
	  session_destroy();
	  gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;action=login', 'SSL'));	
	}
	$cp_boxes = $db->Execute("select * from ".TABLE_USERS_PROFILES." 
		where user_id = '".$_SESSION['admin_id']."' and menu_id = '$menu_id' order by column_id, row_id");
	$include_template  = 'template_main.php';
	define('PAGE_TITLE', COMPANY_NAME);
	break;
}

?>