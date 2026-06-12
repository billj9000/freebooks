<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /modules/doc_ctl/ajax/doc_operation.php
//
$security_level = validate_ajax_user(SECURITY_ID_DOC_CONTROL);
/**************  include page specific files    *********************/
require_once(DIR_FS_MODULES . 'doc_ctl/defaults.php');
require_once(DIR_FS_MODULES . 'doc_ctl/functions/doc_ctl.php');
/**************   page specific initialization  *************************/
$xml       = '';
$ajax_text = '';
$id        = (int)$_GET['id'];

if (!isset($_GET['id'])) die;
$doc_details = $db->Execute("select * from " . TABLE_DC_DOCUMENT . " where id = " . $id);
switch ($_REQUEST['action']) {
  case 'bookmark':
	$output = array();
	$bookmarks = explode(":", $doc_details->fields['bookmarks']);
	if (is_array($bookmarks)) foreach ($bookmarks as $value) if ($value) $output[$value] = true;
	$output[$_SESSION['admin_id']] = true;
	$db->Execute("update " . TABLE_DC_DOCUMENT . " set bookmarks = ':" . implode(":",$output) . ":' where  id = " . $id);
    break;
  case 'del_bookmark':
	$output = array();
	$bookmarks = explode(":", $doc_details->fields['bookmarks']);
	if (is_array($bookmarks)) foreach ($bookmarks as $value) if ($value) $output[$value] = true;
	if (isset($output[$_SESSION['admin_id']])) unset($output[$_SESSION['admin_id']]);
	$result = empty($output) ? '' : (':' . implode(":",$output) . ':');
	$db->Execute("update " . TABLE_DC_DOCUMENT . " set bookmarks = '" . $result. "' where  id = " . $id);
    break;
  case 'lock':
	$db->Execute("update " . TABLE_DC_DOCUMENT . " set lock_id = " . $_SESSION['admin_id'] . " where  id = " . $id);
    break;
  case 'del_lock':
	$db->Execute("update " . TABLE_DC_DOCUMENT . " set lock_id = 0 where  id = " . $id);
    break;
  case 'delete':
	$file_name = str_pad($id, 8, '0', STR_PAD_LEFT) . '_*.dc'; // delete all revisions
	$files = glob(DOC_CTL_DIR_MY_DOCS . $file_name);
	if (is_array($files)) foreach ($files as $filename) unlink($filename);
	dc_remove_node($id);
	$id = $doc_details->fields['parent_id'];
	$xml .= "\t" . xmlEntry("action", 'reload_tree');
	break;
  case 'delete_dir':
	// check for directory not empty
	$result = $db->Execute("select id from " . TABLE_DC_DOCUMENT . " where parent_id = " . $id);
	if ($result->RecordCount() > 0) {
	  $ajax_text = DOC_CTL_JS_DIR_DELETED_ERROR;
	  break;
	}
	dc_remove_node($id);
	$id = $doc_details->fields['parent_id'];
	$ajax_text = '';
	$xml .= "\t" . xmlEntry("action", 'reload_tree');
	break;
  default: die;
}
// put the output together
$xml .= "\t" . xmlEntry("docID", $id);
$xml .= "\t" . xmlEntry("msg",   $ajax_text);
ob_clean();
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>