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
//  Path: /modules/phreebooks/pages/bills/pre_process.php
//

/**************   Check user security   *****************************/
$jID  = (int)$_GET['jID'];
$type = $_GET['type'] ?? $_POST['type'] ?? '';

switch ($jID) {
  case 18:	// Cash Receipts Journal
	define('JOURNAL_ID',18);
	$security_token = ($type == 'v') ? SECURITY_ID_VENDOR_RECEIPTS : SECURITY_ID_CUSTOMER_RECEIPTS;
	break;
  case 20:	// Cash Disbursements Journal
	define('JOURNAL_ID',20);
	$security_token = ($type == 'c') ? SECURITY_ID_CUSTOMER_PAYMENTS : SECURITY_ID_PAY_BILLS;
	break;
}
$security_level = validate_user($security_token);
/**************  include page specific files    *********************/
gen_pull_language('contacts');
gen_pull_language('payment');
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_MODULES . 'payment/defaults.php');
require_once(DIR_FS_WORKING . 'functions/phreebooks.php');
require_once(DIR_FS_WORKING . 'classes/gen_ledger.php');
require_once(DIR_FS_WORKING . 'classes/banking.php');
/**************   page specific initialization  *************************/
// check to see if we need to make a payment for a specific order
$oID               = isset($_GET['oID']) ? (int)$_GET['oID'] : false;
$post_date         = !empty($_POST['post_date']) ? gen_db_date($_POST['post_date']) : date('Y-m-d', time());
$period            = gen_calculate_period($post_date);
if (!$period) { // bad post_date was submitted
  $_REQUEST['action']    = '';
  $post_date = date('Y-m-d');
  $period    = 0;
}
$gl_acct_id        = !empty($_POST['gl_acct_id']) ? db_prepare_input($_POST['gl_acct_id']) : AP_PURCHASE_INVOICE_ACCOUNT;
$post_success      = false;
$error             = false;
$payment_modules   = array();

switch (JOURNAL_ID) {
  case 18:	// Cash Receipts Journal
	define('GL_TYPE','pmt');
	define('POPUP_FORM_TYPE','bnk:rcpt');
	define('AUDIT_LOG_DESC',ORD_TEXT_18_WINDOW_TITLE);
	define('AUDIT_LOG_DEL_DESC',ORD_TEXT_18_WINDOW_TITLE . '-' . TEXT_DELETE);
	$account_type = ($type == 'v') ? 'v' : 'c';
	$payment_modules = load_all_methods('payment');
	foreach ($payment_modules as $pmt_class) {
	  $class  = $pmt_class['id'];
	  $$class = new $class;
	}
	break;
  case 20:	// Cash Disbursements Journal
	define('GL_TYPE','chk');
	define('POPUP_FORM_TYPE','bnk:chk');
	define('AUDIT_LOG_DESC',ORD_TEXT_20_WINDOW_TITLE);
	define('AUDIT_LOG_DEL_DESC',ORD_TEXT_20_WINDOW_TITLE . '-' . TEXT_DELETE);
	$account_type = ($type == 'c') ? 'c' : 'v';
	if ($account_type == 'c') {
	  $payment_modules = load_all_methods('payment');
	  foreach ($payment_modules as $pmt_class) {
	    $class  = $pmt_class['id'];
	    $$class = new $class;
	  }
	}
	break;
  default: // this should never happen
	$messageStack->add('No valid journal id found (module bills), Journal ID needs to be passed to this script to identify the action', 'error');
	gen_redirect(html_href_link(FILENAME_DEFAULT, '', 'SSL'));
}

$order  = new banking();
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/bills/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }

/***************   Act on the action request   *************************/
if (!isset($_REQUEST['action'])) $_REQUEST['action'] = ''; // a plain page open; js_include reads it too
switch ($_REQUEST['action']) {
  case 'save':
  case 'print':
	// Did anyone actually change this receipt? Printing one is a look at it, not a
	// rewrite of it, so an unchanged record is left alone and read-only may print.
	// doc_modified is set by submitToDo() from fbDocModified(); it is '1' unless
	// the browser established that nothing changed, so a client that never ran the
	// javascript saves as usual rather than dropping an edit.
	$doc_unchanged = (($_POST['id'] ?? '') <> '') && isset($_POST['doc_modified']) && $_POST['doc_modified'] == '0';
	validate_security($security_level, ($doc_unchanged && $_REQUEST['action'] == 'print') ? 1 : 2);
  	// create and retrieve customer account (defaults also)
	$order->bill_short_name     = db_prepare_input($_POST['search']);
	$order->bill_acct_id        = ctype_digit((string)db_prepare_input($_POST['bill_acct_id'])) ? db_prepare_input($_POST['bill_acct_id']) : '';
	$order->bill_address_id     = db_prepare_input($_POST['bill_address_id']);
	$order->bill_primary_name   = $_POST['bill_primary_name'] <> GEN_PRIMARY_NAME ? db_prepare_input($_POST['bill_primary_name']) : '';
	$order->bill_contact        = $_POST['bill_contact'] <> GEN_CONTACT ? db_prepare_input($_POST['bill_contact']) : '';
	$order->bill_address1       = $_POST['bill_address1'] <> GEN_ADDRESS1 ? db_prepare_input($_POST['bill_address1']) : '';
	$order->bill_address2       = $_POST['bill_address2'] <> GEN_ADDRESS2 ? db_prepare_input($_POST['bill_address2']) : '';
	$order->bill_city_town      = $_POST['bill_city_town'] <> GEN_CITY_TOWN ? db_prepare_input($_POST['bill_city_town']) : '';
	$order->bill_state_province = $_POST['bill_state_province'] <> GEN_STATE_PROVINCE ? db_prepare_input($_POST['bill_state_province']) : '';
	$order->bill_postal_code    = $_POST['bill_postal_code'] <> GEN_POSTAL_CODE ? db_prepare_input($_POST['bill_postal_code']) : '';
	$order->bill_country_code   = db_prepare_input($_POST['bill_country_code']);
	$order->bill_email          = db_prepare_input($_POST['bill_email']);

	// load journal main data
	$order->id                  = ctype_digit((string)$_POST['id']) ? $_POST['id'] : ''; // will be null unless opening an existing purchase/receive
	$order->admin_id            = $_SESSION['admin_id'];
	$order->rep_id              = db_prepare_input($_POST['rep_id']);
	$order->journal_id          = JOURNAL_ID;
	$order->post_date           = $post_date;
	$order->period              = $period;
	if (!$order->period) break;	// bad post_date was submitted
	$order->store_id            = (int)db_prepare_input($_POST['store_id']);
	if ($order->store_id == '') $order->store_id = 0;
	$order->purchase_invoice_id = db_prepare_input($_POST['purchase_invoice_id']);	// PhreeBooks order/invoice ID
	$order->shipper_code        = db_prepare_input($_POST['shipper_code']);  // store payment method in shipper_code field
	// It names a class to instantiate and a file to include: an installed payment
	// method or nothing. Installed, not only active: a refund goes back through
	// the method the payment was taken with, even one switched off since.
	if (!preg_match('/^[a-z0-9_]+$/', $order->shipper_code)
	    || !file_exists(DIR_FS_MODULES . 'payment/methods/' . $order->shipper_code . '/' . $order->shipper_code . '.php')) $order->shipper_code = '';
	$order->purch_order_id      = db_prepare_input($_POST['purch_order_id']);  // customer PO/Ref number
	$order->description         = sprintf(TEXT_JID_ENTRY, constant('ORD_TEXT_' . JOURNAL_ID . '_' . strtoupper($type) . '_WINDOW_TITLE'));

	// The currency this entry settles in, and the rate it settles at.
	//
	// Every journal amount is STORED in the home currency - that is the design,
	// and orders/pre_process.php divides by the rate for the same reason - so the
	// figures typed on the page are divided back here (issue #63).
	$order->currencies_code     = db_prepare_input($_POST['currencies_code'] ?? '') ?: DEFAULT_CURRENCY;
	$order->currencies_value    = (float)db_prepare_input($_POST['currencies_value'] ?? '') ?: 1;
	$cur_code                   = $order->currencies_code;
	$cur_rate                   = $order->currencies_value;
	$order->total_amount        = $currencies->clean_value(db_prepare_input($_POST['total']), $cur_code) / $cur_rate;
	$order->gl_acct_id          = $gl_acct_id;
	$order->gl_disc_acct_id     = db_prepare_input($_POST['gl_disc_acct_id']);
	$order->payment_id          = db_prepare_input($_POST['payment_id']);
	$order->save_payment        = isset($_POST['save_payment']) ? true : false;
	$order->refund_payment_id   = db_prepare_input($_POST['refund_payment_id'] ?? '');
	// if the user switched away from the payment method the refund_payment_id was looked up
	// against, this is a manual refund (not an automatic gateway refund) - clear it so neither
	// the validation below nor banking.php's automatic-refund logic treat it as one
	$refund_orig_shipper_code  = db_prepare_input($_POST['refund_orig_shipper_code'] ?? '');
	if ($order->refund_payment_id !== '' && $order->shipper_code !== $refund_orig_shipper_code) {
	  $order->refund_payment_id = '';
	}

	// load item row data
	$x = 1;
	while (isset($_POST['id_' . $x])) { // while there are invoice rows to read in
	  if (isset($_POST['pay_' . $x])) {
		$order->item_rows[] = array(
		  'id'      => db_prepare_input($_POST['id_' . $x]),
		  'gl_type' => GL_TYPE,
		  'amt'     => $currencies->clean_value(db_prepare_input($_POST['amt_' . $x]),   $cur_code) / $cur_rate,
		  'desc'    => db_prepare_input($_POST['desc_' . $x]),
		  'dscnt'   => $currencies->clean_value(db_prepare_input($_POST['dscnt_' . $x]), $cur_code) / $cur_rate,
		  'total'   => $currencies->clean_value(db_prepare_input($_POST['total_' . $x]), $cur_code) / $cur_rate,
		  'inv'     => db_prepare_input($_POST['inv_' . $x]),
		  'prcnt'   => db_prepare_input($_POST['prcnt_' . $x]),
		  'early'   => db_prepare_input($_POST['early_' . $x]),
		  'due'     => db_prepare_input($_POST['due_' . $x]),
		  'pay'     => isset($_POST['pay_' . $x]) ? true : false,
		  'acct'    => db_prepare_input($_POST['acct_' . $x]),
		);
	  }
	  $x++;
	}

	// error check input
	if (!$order->bill_acct_id) { // no account was selected, error
	  $contact_type = $type=='c' ? TEXT_LC_CUSTOMER : TEXT_LC_VENDOR;
	  $error = $messageStack->add(sprintf(ERROR_NO_CONTACT_SELECTED, $contact_type, $contact_type, ORD_ADD_UPDATE), 'error');
	}
	if (!$order->item_rows) $error = $messageStack->add(GL_ERROR_NO_ITEMS, 'error');
	// One entry, one currency. The page will not let two be ticked together, but
	// the page is not what decides: a post that arrived any other way would
	// otherwise divide a euro figure by a dollar rate and store a number that is
	// in no currency at all. Checked against what was TICKED rather than against
	// the header, because the header is only the page's account of the rows.
	$ticked_currencies = array();
	$x = 1;
	while (isset($_POST['id_' . $x])) {
	  if (isset($_POST['pay_' . $x])) {
	    $row_code = db_prepare_input($_POST['curr_' . $x] ?? '') ?: DEFAULT_CURRENCY;
	    $ticked_currencies[$row_code] = true;
	  }
	  $x++;
	}
	if (count($ticked_currencies) > 1) {
	  $error = $messageStack->add(sprintf(BNK_ERROR_MIXED_CURRENCY,
	    implode(', ', array_keys($ticked_currencies))), 'error');
	}
	// A foreign-currency entry must not be handed to a payment method that will
	// transmit the number and name the company's currency beside it. That is not
	// a display fault: it is a real charge or refund for the wrong money, and
	// nothing on the page or the gateway would show it as one. Refused here
	// rather than corrected, because correcting it means teaching the method to
	// send a code and a rate, which is the method's own change to make.
	if ($order->currencies_code != DEFAULT_CURRENCY && !empty($order->shipper_code)) {
	  $pm_class = load_specific_method('payment', $order->shipper_code);
	  if (class_exists($pm_class)) {
	    $pm_probe = new $pm_class;
	    if (empty($pm_probe->multi_currency)) {
	      $error = $messageStack->add(sprintf(BNK_ERROR_PAYMENT_CURRENCY,
	        $pm_probe->title ?: $order->shipper_code, $order->currencies_code, DEFAULT_CURRENCY), 'error');
	    }
	  }
	}
	// check to make sure the payment method is valid
	if (JOURNAL_ID == 18) {
	  $payment_module = $order->shipper_code;
	  if ($payment_module === '' || !class_exists($payment_module)) {
	    $error = $messageStack->add(GL_ERROR_NO_POST, 'error');
	  } else {
	    $processor = new $payment_module;
	    if ($processor->pre_confirmation_check()) $error = true;
	  }
	}
	// for J20 customer refunds with a linked payment ID, confirm the selected module supports refunds
	if (JOURNAL_ID == 20 && $account_type == 'c' && !empty($order->refund_payment_id) && !empty($order->shipper_code)) {
	  $r_method = load_specific_method('payment', $order->shipper_code);
	  if (class_exists($r_method)) {
	    $r_check = new $r_method;
	    $base_refund = (new ReflectionMethod($r_check, 'refund'))->getDeclaringClass()->getName();
	    if ($base_refund === 'payment') $error = $messageStack->add(GL_ERROR_REFUND_NOT_SUPPORTED, 'error');
	  }
	}

/* This has been commented out to allow customer refunds (negative invoices)
	if ($order->total_amount < 0) $error = $messageStack->add(TEXT_TOTAL_LESS_THAN_ZERO,'error');
*/
	// post the receipt/payment
	if (!$error && $doc_unchanged) $post_success = true; // nothing to write
	elseif (!$error)               $post_success = $order->post_ordr($_REQUEST['action']);
	if (!$error && $post_success) {
	  if ($_REQUEST['action'] == 'save') {
		if (!$doc_unchanged) gen_add_audit_log(AUDIT_LOG_DESC, $order->purchase_invoice_id, $order->total_amount);
		if (DEBUG) $messageStack->write_debug();
		gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	  }
	  // Reopen the record that was printed rather than clearing the form: Print is
	  // a look at this receipt, not a move away from it. js_include.php opens the
	  // form popup when it sees print=1.
	  gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'oID', 'print')) . '&amp;action=edit&amp;oID=' . $order->id . '&amp;print=1', 'SSL'));
	} else { // else there was a post error, display and re-display form
	  $error = $messageStack->add(GL_ERROR_NO_POST, 'error');
	  if (DEBUG) $messageStack->write_debug();
	  $order = new objectInfo($_POST);
	  $order->post_date = gen_db_date($_POST['post_date']); // fix the date to original format
	  $order->id = ($_POST['id'] <> '') ? $_POST['id'] : ''; // will be null unless opening an existing purchase/receive
	}
	break;

  case 'delete':
	validate_security($security_level, 4);
  	$id = ($_POST['id'] <> '') ? $_POST['id'] : ''; // will be null unless opening an existing purchase/receive
	if ($id) {
		$delOrd = new banking();
		$delOrd->journal($id); // load the posted record based on the id submitted
		if ($delOrd->delete_payment()) {
			gen_add_audit_log(AUDIT_LOG_DEL_DESC, $order->purchase_invoice_id, $order->total_amount);
			if (DEBUG) $messageStack->write_debug();
			gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
		}
	} else {
		$messageStack->add(GL_ERROR_NEVER_POSTED, 'error');
	}
	$messageStack->add(GL_ERROR_NO_DELETE, 'error');
	// if we are here, there was an error, reload page
	$order = new objectInfo($_POST);
	$order->post_date = gen_db_date($_POST['post_date']); // fix the date to original format
	break;

  case 'pmt': // for opening a sales/invoice directly from payment (POS like)
    // fetch the journal_main information
    $sql = "select id, shipper_code, bill_acct_id, bill_address_id, bill_primary_name, bill_contact, bill_address1,
		bill_address2, bill_city_town, bill_state_province, bill_postal_code, bill_country_code, bill_email,
		post_date, terms, gl_acct_id, purchase_invoice_id, purch_order_id, total_amount,
		currencies_code, currencies_value from " . TABLE_JOURNAL_MAIN . "
		where id = " . $oID;
	$result = $db->Execute($sql);
	$account_id = $db->Execute("select short_name from " . TABLE_CONTACTS . " where id = " . $result->fields['bill_acct_id']);
	$due_dates = calculate_terms_due_dates($result->fields['post_date'], $result->fields['terms'], 'AR');
	$pre_paid  = fetch_partially_paid($oID);

	$order->bill_acct_id        = $result->fields['bill_acct_id'];
	$order->bill_primary_name   = $result->fields['bill_primary_name'];
	$order->bill_contact        = $result->fields['bill_contact'];
	$order->bill_address1       = $result->fields['bill_address1'];
	$order->bill_address2       = $result->fields['bill_address2'];
	$order->bill_city_town      = $result->fields['bill_city_town'];
	$order->bill_state_province = $result->fields['bill_state_province'];
	$order->bill_postal_code    = $result->fields['bill_postal_code'];
	$order->bill_country_code   = $result->fields['bill_country_code'];
	$order->bill_email          = $result->fields['bill_email'];
    $order->id_1                = $result->fields['id'];
    $order->inv_1               = $result->fields['purchase_invoice_id'];
    $order->acct_1              = $result->fields['gl_acct_id'];
    $order->early_1             = $due_dates['early_date'];
    $order->due_1               = $due_dates['net_date'];
    $order->prcnt_1             = $due_dates['discount'];
    $order->pay_1               = true;
    // The entry takes the currency of the document it was raised from, at THAT
    // document's rate rather than today's. A credit memo for $100 is a promise of
    // $100, and settling it at the rate it was raised at closes it to exactly
    // zero; today's rate would leave a few pence outstanding on a memo nobody
    // owes anything more on. Paying at a different rate is a real thing, and a
    // real thing this program has nowhere to post the difference to - see the
    // manual topic, and issue #63.
    $order->currencies_code     = $result->fields['currencies_code']  ?: DEFAULT_CURRENCY;
    $order->currencies_value    = $result->fields['currencies_value'] ?: 1;
    $order->curr_1              = $order->currencies_code;
    $order->amt_1               = $currencies->format($result->fields['total_amount'] - $pre_paid, true, $order->currencies_code, $order->currencies_value);
    $order->total_1             = $currencies->format($result->fields['total_amount'] - $pre_paid, true, $order->currencies_code, $order->currencies_value);
    $order->desc_1              = '';
	$order->purch_order_id      = $result->fields['purch_order_id'];
	// reset some particular values
	$order->search = $account_id->fields['short_name']; // set the customer id in the search box
	// show the form
	$payment = $db->Execute("select description from " . TABLE_JOURNAL_ITEM . " 
		where ref_id = " . $oID . " and gl_type = 'ttl'");
	$temp = $payment->fields['description'];
	$temp = strpos($temp, ':') ? substr($temp, strpos($temp, ':') + 1) : '';
	$payment_fields = explode(':', $temp);
	for ($i = 0; $i < count(is_array($payment_fields) ? $payment_fields : []); $i++) {
	  $temp = $result->fields['shipper_code'] . '_field_' . $i;
	  $order->$temp = $payment_fields[$i];
	}
	// For J20 customer refunds: look up original payment from J13 → J12 → J18.
	if (JOURNAL_ID == 20 && $account_type == 'c' && $oID) {
	  $j13r = $db->Execute("SELECT journal_id, so_po_ref_id FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)$oID);
	  if (!$j13r->EOF && $j13r->fields['journal_id'] == 13 && $j13r->fields['so_po_ref_id']) {
	    $j12_id = (int)$j13r->fields['so_po_ref_id'];
	    // J18 links to J12 via journal_item.so_po_item_ref_id = journal_main.id (not so_po_ref_id)
	    $j18r = $db->Execute("SELECT DISTINCT jm.id, jm.shipper_code FROM " . TABLE_JOURNAL_MAIN . " jm"
	      . " JOIN " . TABLE_JOURNAL_ITEM . " ji ON ji.ref_id = jm.id"
	      . " WHERE jm.journal_id = 18 AND ji.so_po_item_ref_id = " . $j12_id
	      . " ORDER BY jm.id DESC LIMIT 1");
	    if (!$j18r->EOF && $j18r->fields['shipper_code']) {
	      $order->shipper_code = $j18r->fields['shipper_code'];
	      $ttlr = $db->Execute("SELECT description FROM " . TABLE_JOURNAL_ITEM
	        . " WHERE ref_id = " . (int)$j18r->fields['id'] . " AND gl_type = 'ttl'");
	      if (!$ttlr->EOF) {
	        $tdesc  = $ttlr->fields['description'];
	        $tdesc  = strpos($tdesc, ':') !== false ? substr($tdesc, strpos($tdesc, ':') + 1) : $tdesc;
	        $tparts = explode(':', $tdesc);
	        // New format: receipt_no:payment_id (payment_id starts with receipt_no)
	        // Old format: payment_id:masked_card:... (no short receipt prefix)
	        if (isset($tparts[1]) && $tparts[1] !== '' && $tparts[1] !== '********'
	            && strpos($tparts[1], $tparts[0]) === 0) {
	          $order->refund_payment_id = $tparts[1]; // new format
	        } else {
	          $order->refund_payment_id = $tparts[0]; // old format: parts[0] is the full payment_id
	        }
	      }
	    }
	  }
	}
	break;
  case 'edit': // handled in ajax
	break;
  default:
}

/*****************   prepare to display templates  *************************/
// load the gl account beginning balance
$acct_balance = load_cash_acct_balance($post_date, $gl_acct_id, $period);
// load gl accounts
$gl_array_list = gen_coa_pull_down();
// generate address arrays for javascript
$js_arrays = gen_build_company_arrays();

$cal_bills = array(
  'name'      => 'dateOrdered',
  'form'      => 'bills_form',
  'fieldname' => 'post_date',
  'imagename' => 'btn_date_1',
  'default'   => isset($order->post_date) ? gen_locale_date($order->post_date) : date(DATE_FORMAT),
  'params'    => array('align' => 'left', 'onchange' => 'loadNewBalance();'),
);

// see if current user points to a employee for sales rep default
$result = $db->Execute("select account_id from " . TABLE_USERS . " where admin_id = " . $_SESSION['admin_id']);
$default_sales_rep = $result->fields['account_id'] ? $result->fields['account_id'] : '0';

$include_header      = true;
$include_footer      = true;
$include_template    = 'template_main.php';
$_pt_key = 'ORD_TEXT_' . JOURNAL_ID . '_' . strtoupper($type) . '_WINDOW_TITLE';
if (!defined($_pt_key)) $_pt_key = 'ORD_TEXT_' . JOURNAL_ID . '_WINDOW_TITLE';
define('PAGE_TITLE', constant($_pt_key));

?>