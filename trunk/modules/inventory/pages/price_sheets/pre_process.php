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
//  Path: /modules/inventory/pages/price_sheets/pre_process.php
//
$security_level = validate_user(SECURITY_ID_PRICE_SHEET_MANAGER);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
/**************   page specific initialization  *************************/
$type        = (isset($_GET['type']) && $_GET['type'] === 'v') ? 'v' : 'c';
history_filter('inv_prices');
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_MODULES . 'inventory/pages/price_sheets/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
  case 'update':
	validate_security($security_level, 2);
  	$id             = (int)$_POST['id'];
	$sheet_name     = db_prepare_input($_POST['sheet_name']);
	$revision       = db_prepare_input($_POST['revision']);
	// A blank date means effective now. Not NULL: the price-sheet readers (Zen
	// Cart, bulk inventory, Amazon) select `today >= effective_date`, which a NULL
	// never satisfies, so the sheet would silently apply nowhere.
	$effective_date = gen_db_date($_POST['effective_date']) ?: date('Y-m-d');
	$default_sheet  = isset($_POST['default_sheet']) ? '1' : '0';
	$inactive       = isset($_POST['inactive']) ? '1' : '0';
	$encoded_prices = array();
	for ($i=0, $j=1; $i < MAX_NUM_PRICE_LEVELS; $i++, $j++) {
	  $price   = $currencies->clean_value(db_prepare_input($_POST['price_'   . $j]));
	  $adj     = db_prepare_input($_POST['adj_' . $j]);
	  $adj_val = $currencies->clean_value(db_prepare_input($_POST['adj_val_' . $j]));
	  $rnd     = db_prepare_input($_POST['rnd_' . $j]);
	  $rnd_val = $currencies->clean_value(db_prepare_input($_POST['rnd_val_' . $j]));
	  $level_data = ($_POST['price_' . $j]) ? $price : '0';
	  $level_data .= ':' . db_prepare_input($_POST['qty_' . $j]);
	  $level_data .= ':' . db_prepare_input($_POST['src_' . $j]);
	  $level_data .= ':' . ($_POST['adj_' . $j]     ? $adj     : '0');
	  $level_data .= ':' . ($_POST['adj_val_' . $j] ? $adj_val : '0');
	  $level_data .= ':' . ($_POST['rnd_' . $j]     ? $rnd     : '0');
	  $level_data .= ':' . ($_POST['rnd_val_' . $j] ? $rnd_val : '0');
	  $encoded_prices[] = $level_data;
	}
	$default_levels = implode(';', $encoded_prices);
	// Check for duplicate price sheet names
	if ($_REQUEST['action'] == 'save') {
	  $result = $db->Execute("SELECT id FROM " . TABLE_PRICE_SHEETS . " WHERE sheet_name='".addslashes($sheet_name)."'");
	  if ($result->RecordCount() > 0) {
		$messageStack->add(SRVCS_DUPLICATE_SHEET_NAME,'error');
		$effective_date = gen_locale_date($effective_date);
		$_REQUEST['action'] = 'new';
		break;
	  }
	}
	$sql_data_array = array(
	  'sheet_name' 		=> $sheet_name, 
	  'type' 			=> $type, 
	  'inactive' 		=> $inactive, 
	  // price_sheets.revision is `float NOT NULL`, and the field may be left
	  // blank or not posted at all; neither is a number, and a strict server
	  // refuses the whole save.
	  'revision' 		=> is_numeric($revision) ? $revision : 0, 
	  'effective_date' 	=> $effective_date, 
	  'default_sheet' 	=> $default_sheet, 
	  'default_levels' 	=> $default_levels);
	if ($_REQUEST['action'] == 'save'){
		db_perform(TABLE_PRICE_SHEETS, $sql_data_array, 'insert');
	}else{
		db_perform(TABLE_PRICE_SHEETS, $sql_data_array, 'update', " id = $id");
	}
	if ($default_sheet) {
		// Reset all other price sheet default flags if set to this price sheet
		$db->Execute("update " . TABLE_PRICE_SHEETS . " set default_sheet = '0' where sheet_name <> '".addslashes($sheet_name)."' and type = '$type'");
		// Set all price sheets with this name to default
	  	$db->Execute("update " . TABLE_PRICE_SHEETS . " set default_sheet = '1' where sheet_name = '".addslashes($sheet_name)."' and type = '$type'");
	}
	// set expiration date of previous rev if there is a older rev of this price sheet
	if ($id != '') $db->Execute("update " . TABLE_PRICE_SHEETS . " set expiration_date = '" . gen_specific_date($effective_date, -1) . "' 
	  where sheet_name = '".addslashes($sheet_name)."' and type = '$type' and ( expiration_date IS NULL or expiration_date = '0000-00-00' or expiration_date >= '$effective_date' ) and id < $id");
	gen_add_audit_log(PRICE_SHEETS_LOG . ($_REQUEST['action'] == 'save') ? TEXT_SAVE : TEXT_UPDATE, $sheet_name);
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('psID', 'action')), 'SSL'));
	break;

  case 'delete':
	validate_security($security_level, 4);
	// Delete and revise only as a POST, which index.php has checked for its
	// csrf_token: a GET could be a link someone was sent.
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
  	$id = (int)$_POST['psID'];
	$result = $db->Execute("select sheet_name, type, default_sheet from " . TABLE_PRICE_SHEETS . " where id = " . $id);
	$sheet_name = $result->fields['sheet_name'];
	$type       = $result->fields['type'];
	if ($result->fields['default_sheet'] == '1') $messageStack->add(PRICE_SHEET_DEFAULT_DELETED, 'caution');
	$db->Execute("delete from " . TABLE_PRICE_SHEETS . " where id = '$id'");
	$db->Execute("delete from " . TABLE_INVENTORY_SPECIAL_PRICES . " where price_sheet_id = '$id'");
	gen_add_audit_log(PRICE_SHEETS_LOG . TEXT_DELETE, $sheet_name);
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('psID', 'action')).'&type='.$type, 'SSL'));
	break;

  case 'revise':
	validate_security($security_level, 2);
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
  	$old_id  = (int)$_POST['psID'];
	$result  = $db->Execute("select * from " . TABLE_PRICE_SHEETS . " where id = $old_id");
	$old_rev = $result->fields['revision'];
	$output_array = array(
	  'sheet_name'     => $result->fields['sheet_name'],
	  'type'           => $type,
	  'revision'       => $result->fields['revision'] + 1,
	  'effective_date' => gen_specific_date($result->fields['effective_date'], 1), // default today
	  'default_sheet'  => $result->fields['default_sheet'],
	  'default_levels' => $result->fields['default_levels'],
	);
	db_perform(TABLE_PRICE_SHEETS, $output_array, 'insert');
	$id = db_insert_id(); // this is used by the edit function later on.
	// expire the old sheet
	$db->Execute("UPDATE ".TABLE_PRICE_SHEETS." SET expiration_date='".gen_specific_date($result->fields['effective_date'], 1)."' WHERE id=$old_id");
	// Copy special pricing information to new sheet
	$levels = $db->Execute("select inventory_id, price_levels from " . TABLE_INVENTORY_SPECIAL_PRICES . " where price_sheet_id = $old_id");
	while (!$levels->EOF){
		$db->Execute("insert into " . TABLE_INVENTORY_SPECIAL_PRICES . " set inventory_id = " . (int)$levels->fields['inventory_id'] . ",
		  price_sheet_id = " . (int)$id . ", price_levels = '" . db_input($levels->fields['price_levels']) . "'");
		$levels->MoveNext();
	}
	gen_add_audit_log(PRICE_SHEETS_LOG . TEXT_REVISE, $result->fields['sheet_name'] . ' Rev. ' . $old_rev . ' => ' . ($old_rev + 1));
	$_REQUEST['action'] = 'edit'; // continue with edit.
  case 'edit':
	if(!isset($id)) $id = (int)$_POST['rowSeq'];
	$result         = $db->Execute("select * from " . TABLE_PRICE_SHEETS . " where id = $id");
	$sheet_name     = $result->fields['sheet_name'];
	$revision       = $result->fields['revision'];
	$effective_date = gen_locale_date($result->fields['effective_date']);
	$default_sheet  = ($result->fields['default_sheet']) ? '1' : '0';
	$default_levels = $result->fields['default_levels'];
	break;

  case 'go_first':    $_REQUEST['list'] = 1;       break;
  case 'go_previous': $_REQUEST['list'] = max($_REQUEST['list']-1, 1); break;
  case 'go_next':     $_REQUEST['list']++;         break;
  case 'go_last':     $_REQUEST['list'] = 99999;   break;
  case 'search':
  case 'search_reset':
  case 'go_page':
  case 'new':
  default:
}

/*****************   prepare to display templates  *************************/
$cal_ps = array(
  'name'      => 'datePost',
  'form'      => 'pricesheet',
  'fieldname' => 'effective_date',
  'imagename' => 'btn_date_1',
  'default'   => $effective_date,
);

$include_header    = true;
$include_footer    = true;

switch ($_REQUEST['action']) {
  case 'new':
  case 'edit':
    $include_template = 'template_detail.php';
    define('PAGE_TITLE', ($_REQUEST['action'] == 'new') ? PRICE_SHEET_NEW_TITLE : PRICE_SHEET_EDIT_TITLE);
	break;
  default:
	$heading_array = array(
	  'sheet_name'      => TEXT_SHEET_NAME,
	  'inactive'        => TEXT_INACTIVE,
	  'revision'        => TEXT_REVISION,
	  'default_sheet'   => TEXT_DEFAULT,
	  'effective_date'  => TEXT_EFFECTIVE_DATE,
	  'expiration_date' => TEXT_EXPIRATION_DATE,
	);
	$result      = html_heading_bar($heading_array, array(TEXT_SPECIAL_PRICING, TEXT_ACTION));
	$list_header = $result['html_code'];
	$disp_order  = $result['disp_order'];
	// find the highest rev level by sheet name
	$result = $db->Execute("select distinct sheet_name, max(revision) as rev from " . TABLE_PRICE_SHEETS . " 
	  where type = '$type' group by sheet_name");
	$rev_levels = array();
	while(!$result->EOF) {
	  $rev_levels[$result->fields['sheet_name']] = $result->fields['rev'];
	  $result->MoveNext();
	}
	// build the list for the page selected
	$search = '';
	if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
	  $search_fields = array('sheet_name', 'revision');
	  // hook for inserting new search fields to the query criteria.
	  if (isset($extra_search_fields) && is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
	  $search = ' AND (' . implode(" LIKE '%" . db_input($_REQUEST['search_text']) . "%' OR ", $search_fields) . " LIKE '%" . db_input($_REQUEST['search_text']) . "%')";
	}
	$field_list = array('id', 'inactive', 'sheet_name', 'revision', 'effective_date', 'expiration_date', 'default_sheet');
	// hook to add new fields to the query return results
	if (is_array($extra_query_list_fields) > 0) $field_list = array_merge($field_list, $extra_query_list_fields);
	$query_raw    = "SELECT ".implode(', ', $field_list)." FROM ".TABLE_PRICE_SHEETS." WHERE type='$type' $search ORDER BY $disp_order";
	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".MAX_DISPLAY_SEARCH_RESULTS);
    $query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
if ($query_split->current_page_number <> $_REQUEST['list']) { // if here, go last was selected, now we know # pages, requery to get results
    	$_REQUEST['list'] = $query_split->current_page_number;
    	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    	$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
    }
    history_save('inv_prices');
    
    $include_template = 'template_main.php';
    define('PAGE_TITLE', $type == 'v' ? BOX_PURCHASE_PRICE_SHEETS : BOX_SALES_PRICE_SHEETS);
}

?>