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
//  Path: /modules/phreebooks/pages/export_profiles/pre_process.php
//
// Manages purchase_export_profile rows - the line-format templates a
// supplier's ordering system expects. Assigned to a vendor from the
// contacts "General" tab (export_profile_id); consumed by
// classes/purchase_export.php when a Purchase Order is exported.
$use_bootstrap_nav = true;
$security_level = validate_user(SECURITY_ID_PURCHASE_EXPORT);
/**************  include page specific files    *********************/
require_once(DIR_FS_MODULES . 'phreebooks/defaults.php');
/**************   page specific initialization  *************************/
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
  case 'update':
	validate_security($security_level, 2);
	$id               = (int)$_POST['id'];
	$profile_name     = db_prepare_input($_POST['profile_name']);
	$file_extension   = db_prepare_input($_POST['file_extension']) ?: 'txt';
	$header_template  = db_prepare_input($_POST['header_template']);
	$line_template    = db_prepare_input($_POST['line_template']);
	$footer_template  = db_prepare_input($_POST['footer_template']);
	$line_ending      = ($_POST['line_ending'] == 'lf') ? 'lf' : 'crlf';
	$inactive         = isset($_POST['inactive']) ? '1' : '0';
	if ($profile_name == '') {
	  $messageStack->add(PO_EXPORT_ERROR_NO_NAME, 'error');
	  $_REQUEST['action'] = $id ? 'edit' : 'new';
	  break;
	}
	$sql_data_array = array(
	  'profile_name'    => $profile_name,
	  'file_extension'  => $file_extension,
	  'header_template' => $header_template,
	  'line_template'   => $line_template,
	  'footer_template' => $footer_template,
	  'line_ending'     => $line_ending,
	  'inactive'        => $inactive,
	);
	if ($_REQUEST['action'] == 'save') {
	  db_perform(TABLE_PURCHASE_EXPORT_PROFILE, $sql_data_array, 'insert');
	  gen_add_audit_log(PO_EXPORT_PROFILES_LOG . '-' . TEXT_ADD, $profile_name);
	} else {
	  db_perform(TABLE_PURCHASE_EXPORT_PROFILE, $sql_data_array, 'update', "id = " . $id);
	  gen_add_audit_log(PO_EXPORT_PROFILES_LOG . '-' . TEXT_UPDATE, $profile_name);
	}
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'peID')), 'SSL'));
	break;

  case 'delete':
	validate_security($security_level, 4);
	$id = (int)db_prepare_input($_GET['peID']);
	if ($id) {
	  $in_use = $db->Execute("select count(*) as cnt from " . TABLE_CONTACTS . " where export_profile_id = " . $id);
	  if ($in_use->fields['cnt'] > 0) {
		$messageStack->add(PO_EXPORT_ERROR_IN_USE, 'error');
		break;
	  }
	  $result = $db->Execute("select profile_name from " . TABLE_PURCHASE_EXPORT_PROFILE . " where id = " . $id);
	  $db->Execute("delete from " . TABLE_PURCHASE_EXPORT_PROFILE . " where id = " . $id);
	  gen_add_audit_log(PO_EXPORT_PROFILES_LOG . '-' . TEXT_DELETE, $result->fields['profile_name']);
	}
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'peID')), 'SSL'));
	break;

  case 'edit':
	if (!isset($id)) $id = (int)$_POST['rowSeq'];
	$result           = $db->Execute("select * from " . TABLE_PURCHASE_EXPORT_PROFILE . " where id = " . $id);
	$profile_name     = $result->fields['profile_name'];
	$file_extension   = $result->fields['file_extension'];
	$header_template  = $result->fields['header_template'];
	$line_template    = $result->fields['line_template'];
	$footer_template  = $result->fields['footer_template'];
	$line_ending      = $result->fields['line_ending'];
	$inactive         = $result->fields['inactive'];
	break;

  case 'new':
  default:
}

/*****************   prepare to display templates  *************************/
$include_header = true;
$include_footer = true;

switch ($_REQUEST['action']) {
  case 'new':
  case 'edit':
	$include_template = 'template_detail.php';
	define('PAGE_TITLE', ($_REQUEST['action'] == 'new') ? PO_EXPORT_PROFILE_NEW_TITLE : PO_EXPORT_PROFILE_EDIT_TITLE);
	break;
  default:
	$query_result = $db->Execute("select * from " . TABLE_PURCHASE_EXPORT_PROFILE . " order by profile_name");
	$include_template = 'template_main.php';
	define('PAGE_TITLE', PO_EXPORT_PROFILES_TITLE);
}
?>
