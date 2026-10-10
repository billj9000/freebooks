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
//  Path: /modules/phreebooks/pages/popup_bills_accts/pre_process.php
//
$security_level = validate_user(0, true);
/**************  include page specific files    *********************/
gen_pull_language('contacts');
require_once(DIR_FS_WORKING . 'defaults.php');
require(DIR_FS_WORKING . 'functions/phreebooks.php');
/**************   page specific initialization  *************************/
define('JOURNAL_ID',   (int)($_GET['jID'] ?? 0));
define('ACCOUNT_TYPE', $_GET['type'] ?? '');
switch (JOURNAL_ID) {
	case 18:
		$terms_type = 'AR';
		$default_purchase_invoice_id = 'DP' . date('Ymd', time());
		break;
	case 20: 
		$terms_type = 'AP';
		$result = $db->Execute("select next_check_num from " . TABLE_CURRENT_STATUS);
		$default_purchase_invoice_id = $result->fields['next_check_num'];
		break;
	default:
    // A journal id this page does not serve. A die() here reaches the user as a
    // blank page with no way back.
    $messageStack->add(sprintf(ORD_ERROR_BAD_JOURNAL_ID, (int)JOURNAL_ID), 'error');
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=phreedom&page=main', 'SSL'));
}
history_filter('pb_pop_contacts');
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/popup_bills_accts/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
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
// build the list header
$heading_array = array(
  'm.bill_primary_name'                     => GEN_PRIMARY_NAME,
  'm.bill_city_town, m.bill_state_province' => GEN_CITY_TOWN,
  'm.bill_state_province, m.bill_city_town' => GEN_STATE_PROVINCE,
  'm.postal_code'                           => GEN_POSTAL_CODE,
  'total_amount'                            => TEXT_BALANCE . (ENABLE_MULTI_CURRENCY ? ' (' . DEFAULT_CURRENCY . ')' : ''));
$result      = html_heading_bar($heading_array, array());
$list_header = $result['html_code'];
$disp_order  = $result['disp_order'];
// build the list for the page selected
if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
  $search_fields = array('c.short_name', 'm.bill_primary_name', 'm.bill_contact', 'm.bill_address1', 
    'm.bill_address2', 'm.bill_city_town', 'm.bill_postal_code', 'm.purchase_invoice_id');
  // hook for inserting new search fields to the query criteria.
  if (isset($extra_search_fields) && is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
  $search = ' and ' . db_search_criteria($search_fields, $_REQUEST['search_text']);
} else {
  $search = '';
}
$field_list = array('m.bill_acct_id', 'm.bill_primary_name', 'm.bill_city_town', 
	'm.bill_state_province', 'm.bill_postal_code', 'sum(m.total_amount) as ztotal_amount');
		// hook to add new fields to the query return results
if (isset($extra_query_list_fields) && is_array($extra_query_list_fields)) $field_list = array_merge($field_list, $extra_query_list_fields);

$query_raw = "select " . implode(', ', $field_list) . " 
	from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_CONTACTS . " c on m.bill_acct_id = c.id
	where c.type = '" . (ACCOUNT_TYPE == 'v' ? 'v' : 'c') . "' 
	and m.journal_id in " . (ACCOUNT_TYPE == 'v' ? '(6, 7)' : '(12, 13)') . " and m.closed = '0'" . $search . " 
	group by m.bill_acct_id order by $disp_order";

$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
if ($query_split->current_page_number <> $_REQUEST['list']) { // if here, go last was selected, now we know # pages, requery to get results
   	$_REQUEST['list'] = $query_split->current_page_number;
	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
	$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
   }
history_save('pb_pop_contacts');
$include_header   = false;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', constant('ORD_TEXT_' . JOURNAL_ID . '_' . strtoupper(ACCOUNT_TYPE) . '_WINDOW_TITLE'));

?>