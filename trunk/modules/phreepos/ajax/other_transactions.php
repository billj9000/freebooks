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
//  Path: /modules/phreepos/ajax/other_transactions.php
//
$security_level = validate_user(SECURITY_ID_PHREEPOS);
define('JOURNAL_ID',2);
/**************  include page specific files    *********************/
gen_pull_language('contacts');
gen_pull_language('phreebooks');
gen_pull_language('inventory');
require_once(DIR_FS_MODULES . 'inventory/defaults.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
require_once(DIR_FS_MODULES . 'phreebooks/classes/gen_ledger.php');
require_once(DIR_FS_MODULES . 'phreepos/classes/tills.php');
require_once(DIR_FS_MODULES . 'phreepos/classes/other_transactions.php');
/**************   page specific initialization  *************************/
define('ORD_ACCT_ID',GEN_CUSTOMER_ID);
define('GL_TYPE','sos');
define('DEF_INV_GL_ACCT',AR_DEF_GL_SALES_ACCT);
define('DEF_GL_ACCT',AR_DEFAULT_GL_ACCT);
define('DEF_GL_ACCT_TITLE',ORD_AR_ACCOUNT);
define('POPUP_FORM_TYPE','pos:rcpt');
$error           = false;
$account_type    = 'c';
$order           = new journal();
$transaction     = new other_transactions();
$tills           = new tills();
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_MODULES . 'phreepos/custom/ajax/other_transactions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
	if ($security_level < 2) {
	  $error .= ERROR_NO_PERMISSION;
	}
	// currency values (convert to DEFAULT_CURRENCY to store in db)
	$order->currencies_code     = db_prepare_input($_POST['ot_currencies_code'] ?? '');
	// An exchange rate of zero is not a rate. Absent or zero means the home
	// currency, and every amount below is divided by it.
	$order->currencies_value    = (float)db_prepare_input($_POST['ot_currencies_value'] ?? '') ?: 1;
	$amount = (float)$currencies->clean_value(db_prepare_input($_POST['ot_amount'] ?? ''), $order->currencies_code) / $order->currencies_value;
	// Nothing is posted without a known transaction type, a known till and an amount to move.
	if (!$error && (!$transaction->get_transaction_info($_POST['Other_trans_type'] ?? '') || empty($transaction->type)
	    || !$tills->get_till_info($_POST['ot_till_id'] ?? '') || empty($tills->till_id) || !($amount > 0))) {
	  $error .= POS_ERROR_OTHER_TRANS_INCOMPLETE;
	}
	if (!$error) {
	  // The tax in an expense is worked out by the ledger from the amount and the rate (issue #122);
	  // the screen shows the same figure and posts none of its own.
	  if (!$transaction->post($tills, $amount, (int)($_POST['ot_rate'] ?? 0), db_prepare_input($_POST['ot_desc'] ?? ''),
	                          date('Y-m-d'), CURRENT_ACCOUNTING_PERIOD)) {
	    $error .= POS_ERROR_POSTING;
	  }
	}
	if ( DEBUG )           $messageStack->write_debug();
$xml = '';
						$xml .= "\t" . xmlEntry("action",			$_REQUEST['action'] ?? '');
if ($error)  			$xml .= "\t" . xmlEntry("error", 			$error);
//if ($order->errormsg)	$xml .= "\t" . xmlEntry("error", 			$order->errormsg);
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>