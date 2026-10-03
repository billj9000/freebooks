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
//  Path: /modules/phreebooks/pages/status/pre_process.php
//
/**************   Check user security   *****************************/
define('JOURNAL_ID', (int)($_GET['jID'] ?? 0));
// journals 18 (Cash Receipts) and 20 (Cash Disbursements) each serve two account
// types via the same journal_id (customer receipt/vendor refund and vendor
// payment/customer refund, respectively); default preserves the historical
// meaning of the existing un-typed manager links.
if (JOURNAL_ID == 18 || JOURNAL_ID == 20) {
  $account_type = (($_GET['type'] ?? '') == 'c' || ($_GET['type'] ?? '') == 'v') ? $_GET['type'] : (JOURNAL_ID == 18 ? 'c' : 'v');
}
switch (JOURNAL_ID) {
  case  2: $security_token = SECURITY_ID_JOURNAL_ENTRY;      break;
  case  3: $security_token = SECURITY_ID_PURCHASE_QUOTE;     break;
  case  4: $security_token = SECURITY_ID_PURCHASE_ORDER;     break;
  case  6: $security_token = SECURITY_ID_PURCHASE_INVENTORY; break;
  case  7: $security_token = SECURITY_ID_PURCHASE_CREDIT;    break;
  case  9: $security_token = SECURITY_ID_SALES_QUOTE;        break;
  case 10: $security_token = SECURITY_ID_SALES_ORDER;        break;
  case 12: $security_token = SECURITY_ID_SALES_INVOICE;      break;
  case 13: $security_token = SECURITY_ID_SALES_CREDIT;       break;
  case 18: $security_token = ($account_type == 'v') ? SECURITY_ID_VENDOR_RECEIPTS : SECURITY_ID_CUSTOMER_RECEIPTS; break;
  case 20: $security_token = ($account_type == 'c') ? SECURITY_ID_CUSTOMER_PAYMENTS : SECURITY_ID_PAY_BILLS;       break;
  default:
    // A journal id this page does not serve. A die() here reaches the user as a
    // blank page with no way back.
    $messageStack->add(sprintf(ORD_ERROR_BAD_JOURNAL_ID, (int)JOURNAL_ID), 'error');
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=phreedom&page=main', 'SSL'));
}
$security_level = validate_user($security_token);
/**************  include page specific files    *********************/
require(DIR_FS_WORKING . 'defaults.php');
require(DIR_FS_WORKING . 'functions/phreebooks.php');
/**************   page specific initialization  *************************/
history_filter('pb'.JOURNAL_ID, $defaults = array('sf'=>TEXT_DATE, 'so'=>'desc', 'search_period'=>CURRENT_ACCOUNTING_PERIOD));
$date_today = date('Y-m-d');
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/status/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action'] ?? '') {
  case 'toggle':
    $id     = db_prepare_input($_POST['rowSeq']);
	$result = $db->Execute("select waiting from " . TABLE_JOURNAL_MAIN . " where id = '" . db_input($id) . "'");
	$toggle = $result->fields['waiting'] ? '0' : '1';
	$db->Execute("update " . TABLE_JOURNAL_MAIN . " set waiting = '" . $toggle . "' where id = '" . db_input($id) . "'");
    break;
  case 'dn_attach':
	$oID = db_prepare_input($_POST['rowSeq']);
	if (file_exists(PHREEBOOKS_DIR_MY_ORDERS . 'order_' . $oID . '.zip')) {
	  require_once(DIR_FS_MODULES . 'phreedom/classes/backup.php');
	  $backup = new backup();
	  $backup->download(PHREEBOOKS_DIR_MY_ORDERS, 'order_' . $oID . '.zip', true);
	}
	die;
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
// Set up some default lists
$heading_array = array(
  'post_date'           => TEXT_DATE,
  'purchase_invoice_id' => constant('ORD_HEADING_NUMBER_' . JOURNAL_ID),
  'bill_primary_name'   => (in_array(JOURNAL_ID, array(9,10,12,13,19)) || (in_array(JOURNAL_ID, array(18,20)) && $account_type == 'c')) ? ORD_CUSTOMER_NAME : ORD_VENDOR_NAME,
  'purch_order_id'      => TEXT_REFERENCE,
  'closed'              => TEXT_CLOSED,
  'total_amount'        => TEXT_AMOUNT,
);
$extras     = array(TEXT_ACTION);
$field_list = array('id', 'journal_id', 'post_date', 'purchase_invoice_id', 'purch_order_id', 'store_id', 'closed', 'waiting', 
  'bill_primary_name', 'total_amount', 'currencies_code', 'currencies_value', 'shipper_code');
$search_fields = array('bill_primary_name', 'purchase_invoice_id', 'purch_order_id', 'store_id', 'bill_postal_code', 'ship_primary_name', 'total_amount');

switch (JOURNAL_ID) {
  case  2:	// Purchase Quote Journal
	define('POPUP_FORM_TYPE','');
	$heading_array['bill_primary_name'] = TEXT_DESCRIPTION;
	$page_title = ORD_TEXT_2_WINDOW_TITLE;
	break;
  case  3:	// Purchase Quote Journal
	define('POPUP_FORM_TYPE','vend:quot');
	$page_title = ORD_TEXT_3_WINDOW_TITLE;
	break;
  case  4:	// Purchase Order Journal
	if (defined('MODULE_SHIPPING_STATUS')) {
	  $shipping_modules = load_all_methods('shipping');
	  $heading_array['shipper_code'] = TEXT_CARRIER;
	}
	define('POPUP_FORM_TYPE','vend:po');
	$page_title = ORD_TEXT_4_WINDOW_TITLE;
	break;
  case  6:	// Purchase Journal
	define('POPUP_FORM_TYPE','');
	$heading_array['closed'] = TEXT_PAID;
	$page_title = ORD_TEXT_6_WINDOW_TITLE;
	break;
  case  7:	// Vendor Credit Memo Journal
	define('POPUP_FORM_TYPE','vend:cm');
	$heading_array['closed'] = TEXT_PAID;
	$page_title = ORD_TEXT_7_WINDOW_TITLE;
	break;
  case  9:	// Sales Quote Journal
	define('POPUP_FORM_TYPE','cust:quot');
	$page_title = ORD_TEXT_9_WINDOW_TITLE;
	break;
  case 10:	// Sales Order Journal
	define('POPUP_FORM_TYPE','cust:so');
	$page_title = ORD_TEXT_10_WINDOW_TITLE;
	break;
  case 12:	// Invoice Journal
	if (defined('MODULE_SHIPPING_STATUS')) {
	  $shipping_modules = load_all_methods('shipping');
	  $heading_array['shipper_code'] = TEXT_CARRIER;
	}
	define('POPUP_FORM_TYPE','cust:inv');
	$heading_array['closed']  = TEXT_PAID;
	$heading_array['printed'] = STATUS_TEXT_PRINTED;
	$field_list[]              = 'printed';
	$page_title = ORD_TEXT_12_WINDOW_TITLE;
	break;
  case 13:	// Customer Credit Memo Journal
	define('POPUP_FORM_TYPE','cust:cm');
	$heading_array['closed'] = TEXT_PAID;
	$page_title = ORD_TEXT_13_WINDOW_TITLE;
	break;
  case 18:	// Cash Receipts Journal
	define('POPUP_FORM_TYPE','bnk:rcpt');
	$page_title = constant('ORD_TEXT_18_' . strtoupper($account_type) . '_WINDOW_TITLE');
	break;
  case 20:	// Cash Distribution Journal
	define('POPUP_FORM_TYPE','bnk:chk');
	$page_title = constant('ORD_TEXT_20_' . strtoupper($account_type) . '_WINDOW_TITLE');
	break;
  default:
}

$result      = html_heading_bar($heading_array, $extras);
$list_header = $result['html_code'];
$disp_order  = $result['disp_order'];

// build the list for the page selected
$period_filter = gen_period_sql_filter($_REQUEST['search_period']);
if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
  // hook for inserting new search fields to the query criteria.
  if (is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
  $search = ' and (' . implode(' like \'%' . db_input($_REQUEST['search_text']) . '%\' or ', $search_fields) . ' like \'%' . db_input($_REQUEST['search_text']) . '%\')';
} else {
  $search = '';
}

if (!empty($_REQUEST['store_id'])) $search .= " and store_id = '" . (int)$_REQUEST['store_id'] . "' ";
// J18/J20 share a journal_id across two account types; restrict the list to the
// requested type via the contact it's billed to
if (in_array(JOURNAL_ID, array(18, 20))) {
  $search .= " and bill_acct_id in (select id from " . TABLE_CONTACTS . " where type = '" . db_input($account_type) . "')";
}
// hook to add new fields to the query return results
if (isset($extra_query_list_fields) && is_array($extra_query_list_fields)) $field_list = array_merge($field_list, $extra_query_list_fields);

$query_raw    = "select " . implode(', ', $field_list) . " from " . TABLE_JOURNAL_MAIN . " 
	where journal_id = " . JOURNAL_ID . $period_filter . $search . " order by $disp_order, purchase_invoice_id DESC";
$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
if ($query_split->current_page_number <> $_REQUEST['list']) { // if here, go last was selected, now we know # pages, requery to get results
	$_REQUEST['list'] = $query_split->current_page_number;
	$query_result = $db->Execute($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
	$query_split  = new splitPageResults($_REQUEST['list'], $query_raw);
}
history_save('pb'.JOURNAL_ID);

$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', sprintf(BOX_STATUS_MGR, $page_title));

?>