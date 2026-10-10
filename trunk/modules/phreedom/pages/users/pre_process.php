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
//  Path: /modules/phreedom/pages/users/pre_process.php
//
if($_SESSION['admin_id'] == 1) $security_level = 4;
else $security_level = validate_user(SECURITY_ID_USERS);
/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
gen_pull_language('contacts');
require_once(DIR_FS_WORKING . 'functions/phreedom.php');
require_once(DIR_FS_WORKING . 'functions/webauthn.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
/**************   page specific initialization  *************************/
$error  = false;
history_filter('users');
if (!isset($_REQUEST['action'])) $_REQUEST['action'] = ''; // a plain page open
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/users/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
  case 'fill_all':
  case 'fill_role':
	validate_security($security_level, 2);
	$admin_id  = (int)$_POST['rowSeq'];
	$fill_all  = db_prepare_input($_POST['fill_all']);
	$fill_role = (int)$_POST['fill_role'];
	if ($security_level < 3 && $admin_id) $error = $messageStack->add(GEN_ADMIN_CANNOT_CHANGE_ROLES, 'error');
	$existing_security = array();
	$existing_email    = '';
	if ($admin_id) {
	  $result = $db->Execute("select admin_security, admin_email from " . TABLE_USERS . " where admin_id = " . $admin_id . " and is_role = '0'");
	  $existing_security = gen_parse_permissions($result->fields['admin_security'] ?? '');
	  $existing_email    = $result->fields['admin_email'] ?? '';
	}
	if ($_REQUEST['action'] == 'fill_role' ) {
	  $result = $db->Execute("select admin_prefs, admin_security from " . TABLE_USERS . " where admin_id = " . $fill_role);
	  $requested = gen_parse_permissions($result->fields['admin_security'] ?? '');
	  $temp = unserialize($result->fields['admin_prefs'] ?? '', ['allowed_classes' => false]);  // fake the input to look like role
	  if (is_array($temp)) foreach ($temp as $key => $value) $_POST[$key] = $value;
	} else {
	  $requested = array();
	  foreach (array_keys($_POST) as $key) if (strpos($key, 'sID_') === 0) { // it's a security setting post
		$requested[substr($key, 4)] = $fill_all == '-1' ? substr($_POST[$key], 0, 1) : $fill_all;
	  }
	}
	// Only the unrestricted admin (admin_id 1) may change their own security levels.
	if ($admin_id && $admin_id == $_SESSION['admin_id'] && (int)$_SESSION['admin_id'] !== 1) {
	  foreach ($requested as $key => $value) {
		if (max(0, min(4, (int)$value)) != (int)($existing_security[$key] ?? 0)) {
		  $error = $messageStack->add(GEN_ADMIN_CANNOT_CHANGE_ROLES, 'error');
		  break;
		}
	  }
	  $levels = $existing_security;
	} else {
	  $levels = phreedom_clamp_security($requested, $existing_security);
	}
	if ($admin_id == 1) $levels[SECURITY_ID_USERS] = 4; // make sure first admin_id (installer) will not lock self out of system
	$admin_security = phreedom_implode_security($levels);
	$outranks = $admin_id && phreedom_user_outranks_caller($admin_id, $existing_security);
	if ($outranks && $_POST['password_new']) $error = $messageStack->add(ERROR_NO_PERMISSION, 'error');
	if ($outranks && isset($_POST['admin_email']) && db_prepare_input($_POST['admin_email']) != $existing_email) {
	  $error = $messageStack->add(ERROR_NO_PERMISSION, 'error');
	}
	$prefs = array(
	  'role'            => $fill_role,
	  'def_store_id'    => db_prepare_input($_POST['def_store_id']),
	  'def_cash_acct'   => db_prepare_input($_POST['def_cash_acct']),
	  'def_ar_acct'     => db_prepare_input($_POST['def_ar_acct']),
	  'def_ap_acct'     => db_prepare_input($_POST['def_ap_acct']),
	  'restrict_store'  => isset($_POST['restrict_store'])  ? $_POST['restrict_store']  : '0',
	  'restrict_period' => isset($_POST['restrict_period']) ? $_POST['restrict_period'] : '0',
	);
	$admin_prefs = serialize($prefs);
	// not the most elegent but look for a colon in the second character position
	$sql_data_array = array(
	  'admin_name'     => db_prepare_input($_POST['admin_name']),
	  'is_role'        => '0',
	  'inactive'       => isset($_POST['inactive']) ? '1' : '0',
	  'display_name'   => db_prepare_input($_POST['display_name']),
	  'admin_email'    => db_prepare_input($_POST['admin_email']),
	  'account_id'     => $_POST['account_id'] ? db_prepare_input($_POST['account_id']) : 0,
	  'admin_prefs'    => $admin_prefs,
	  'admin_security' => $admin_security,
	);
	if ($_POST['password_new']) {
	  $password_new  = db_prepare_input($_POST['password_new']);
	  $password_conf = db_prepare_input($_POST['password_conf']);
	  if (strlen($password_new) < ENTRY_PASSWORD_MIN_LENGTH) {
		$error = $messageStack->add(sprintf(ENTRY_PASSWORD_NEW_ERROR, ENTRY_PASSWORD_MIN_LENGTH), 'error');
	  } else if ($password_new != $password_conf) {
		$error = $messageStack->add(ENTRY_PASSWORD_NEW_ERROR_NOT_MATCHING, 'error');
	  }
	  $sql_data_array['admin_pass'] = pw_encrypt_password($password_new);
	}
	if (!$admin_id) { // check for duplicate user name
	  $result = $db->Execute("select admin_id from " . TABLE_USERS . " where admin_name = '" . db_input(db_prepare_input($_POST['admin_name'])) . "'");
	  if ($result->RecordCount() > 0) {
		$error = $messageStack->add(ENTRY_DUP_USER_NEW_ERROR, 'error');
	  }
	}
	if (!$error) {
	  if ($admin_id) {
		db_perform(TABLE_USERS, $sql_data_array, 'update', 'admin_id = ' . (int)$admin_id . " and is_role = '0'");
		gen_add_audit_log(sprintf(GEN_LOG_USER, TEXT_UPDATE), db_prepare_input($_POST['admin_name']));
	  } else {
		// admin_pass is NOT NULL with no default, so an INSERT must supply it: omit
		// it and a server at its defaults refuses the whole row. A role has no
		// password, and a new user given none starts without one -
		// pw_validate_password() refuses an empty stored hash outright, so neither
		// can be logged in to.
		if (!isset($sql_data_array['admin_pass'])) $sql_data_array['admin_pass'] = '';
		db_perform(TABLE_USERS, $sql_data_array);
		$admin_id = db_insert_id();
		gen_add_audit_log(sprintf(GEN_LOG_USER, TEXT_ADD), db_prepare_input($_POST['admin_name']));
	  }
	  if ($admin_id == $_SESSION['admin_id']) $_SESSION['admin_security'] = gen_parse_permissions($admin_security); // update if user is current user
	} elseif ($error) {
	  $_REQUEST['action'] = 'edit';
	}
	$uInfo = new objectInfo($_POST);
	$uInfo->admin_security = $admin_security;
	break;

  case 'copy':
	validate_security($security_level, 3);
	$admin_id = (int)$_GET['cID'];
	$new_name = db_prepare_input($_GET['name']);
	// check for duplicate user names
	$result = $db->Execute("select admin_name from " . TABLE_USERS . " where admin_name = '" . db_input($new_name) . "'");
	if ($result->Recordcount() > 0) {	// error and reload
	  $messageStack->add(GEN_ERROR_DUPLICATE_ID, 'error');
	  break;
	}
	$result   = $db->Execute("select * from " . TABLE_USERS . " where admin_id = " . $admin_id);
	$old_name = $result->fields['admin_name'];
	// clean up the fields (especially the system fields, retain the custom fields)
	$output_array = array();
	foreach ($result->fields as $key => $value) {
	  switch ($key) {
		case 'admin_id':	// Remove from write list fields
		case 'display_name':
		case 'admin_email':
		case 'admin_pass':
		case 'account_id':
		  break;
		case 'admin_name': // set the new user name
		  $output_array[$key] = $new_name;
		  break;
		default:
		  $output_array[$key] = $value;
	  }
	}
	// The copy deliberately leaves the password behind, but the column is NOT NULL
	// with no default, so it has to be said rather than left out.
	$output_array['admin_pass'] = '';
	db_perform(TABLE_USERS, $output_array, 'insert');
	$new_id = db_insert_id();
	$messageStack->add(GEN_MSG_COPY_SUCCESS, 'success');
	// now continue with newly copied item by editing it
	gen_add_audit_log(sprintf(GEN_LOG_USER, TEXT_COPY), $old_name . ' => ' . $new_name);
	$_POST['rowSeq'] = $new_id;	// set item pointer to new record
	$_REQUEST['action'] = 'edit'; // fall through to edit case

  case 'edit':
	if (isset($_POST['rowSeq'])) $admin_id = db_prepare_input($_POST['rowSeq']);
	$result = $db->Execute("select * from " . TABLE_USERS . " where admin_id = " . (int)$admin_id);
	$temp = unserialize($result->fields['admin_prefs'] ?? '', ['allowed_classes' => false]);
	unset($result->fields['admin_prefs']);
	$uInfo = new objectInfo($result->fields);
	if (is_array($temp)) foreach ($temp as $key => $value) $uInfo->$key = $value;
	break;

  case 'delete':
	validate_security($security_level, 4);
	$admin_id = (int)db_prepare_input($_POST['rowSeq']);
	// fetch the name for the audit log
	$result = $db->Execute("select admin_name, admin_security from " . TABLE_USERS . " where admin_id = " . $admin_id . " and is_role = '0'");
	// A user holding more than the caller (or admin_id 1) is not the caller's to delete.
	if (phreedom_user_outranks_caller($admin_id, gen_parse_permissions($result->fields['admin_security'] ?? ''))) {
	  $messageStack->add(ERROR_NO_PERMISSION, 'error');
	  break;
	}
	$db->Execute("delete from " . TABLE_USERS . " where admin_id = " . $admin_id . " and is_role = '0'");
	gen_add_audit_log(sprintf(GEN_LOG_USER, TEXT_DELETE), $result->fields['admin_name']);
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	break;

  case 'go_first':    $_REQUEST['list'] = 1;       break;
  case 'go_previous': $_REQUEST['list'] = max($_REQUEST['list']-1, 1); break;
  case 'go_next':     $_REQUEST['list']++;         break;
  case 'go_last':     $_REQUEST['list'] = 99999;   break;
  case 'search':
  case 'search_reset':
  case 'go_page':
  default:
}

/*****************   prepare to display templates  *************************/
$include_header    = true;
$include_footer    = true;

switch ($_REQUEST['action']) {
  case 'new':
  case 'edit':
  case 'fill_all':
  case 'fill_role':
	if (!isset($uInfo)) $uInfo = new objectInfo([]);
	$fill_all_values = array(
	  array('id' => '-1', 'text' => GEN_HEADING_PLEASE_SELECT),
	  array('id' => '0',  'text' => TEXT_NONE),
	  array('id' => '1',  'text' => TEXT_READ_ONLY),
	  array('id' => '2',  'text' => TEXT_ADD),
	  array('id' => '3',  'text' => TEXT_EDIT),
	  array('id' => '4',  'text' => TEXT_FULL),
	);
	$fill_all_roles = array(array('id' => '0', 'text' => TEXT_NONE));
	$result = $db->Execute("select admin_id, admin_name from " . TABLE_USERS . " where is_role = '1'");
	while (!$result->EOF) {
	  $fill_all_roles[] = array('id' => $result->fields['admin_id'], 'text' => $result->fields['admin_name']);
	  $result->MoveNext();
	}
    $include_template = 'template_detail.php';
	$role_name = isset($uInfo->admin_name) ? (' - ' . $uInfo->admin_name) : '';
    define('PAGE_TITLE', HEADING_TITLE_USER_INFORMATION . $role_name);
	break;
  default:
	// build the list header
	$heading_array = array(
	  'admin_name'   => GEN_USERNAME,
	  'inactive'     => TEXT_INACTIVE,
	  'display_name' => GEN_DISPLAY_NAME,
	  'admin_email'  => GEN_EMAIL,
	);
	$result      = html_heading_bar($heading_array);
	$list_header = $result['html_code'];
	$disp_order  = $result['disp_order'];
	// build the list for the page selected
	if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] != '') {
	  $search_fields = array('admin_name', 'admin_email', 'display_name');
	  // hook for inserting new search fields to the query criteria.
	  if (isset($extra_search_fields) && is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
	  $search = ' and (' . implode(' like \'%' . db_input($_REQUEST['search_text']) . '%\' or ', $search_fields) . ' like \'%' . db_input($_REQUEST['search_text']) . '%\')';
	} else {
	  $search = '';
	}
	$field_list = array('admin_id', 'inactive', 'display_name', 'admin_name', 'admin_email');
	// hook to add new fields to the query return results
	if (isset($extra_query_list_fields) && is_array($extra_query_list_fields)) $field_list = array_merge($field_list, $extra_query_list_fields);
	$query_raw    = "select " . implode(', ', $field_list) . " from " . TABLE_USERS . " where is_role = '0'" . $search . " order by $disp_order";
	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    $query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
    if ($query_split->current_page_number != $_REQUEST['list']) {
    	$_REQUEST['list'] = $query_split->current_page_number;
    	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    	$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
    }
    history_save('users');

	$include_template = 'template_main.php';
	define('PAGE_TITLE', BOX_HEADING_USERS);
}

?>
