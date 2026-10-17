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
	// Post() reads these from the entry itself - the balance update chooses its work by journal_id -
	// so they are set here as well as in the main record.
	$order->journal_id = JOURNAL_ID;
	$order->post_date  = date('Y-m-d');
	$order->period     = CURRENT_ACCOUNTING_PERIOD;
	$order->admin_id   = $_SESSION['admin_id'];
	$order->store_id   = $tills->store_id;
	// load journal main data	
	$order->journal_main_array = array(
		'period'              => CURRENT_ACCOUNTING_PERIOD,
		'journal_id'          => JOURNAL_ID,
		'post_date'           => date('Y-m-d'),
		'description'         => GL_ENTRY_TITLE,
		'bill_primary_name'   => $transaction->description,
		'purchase_invoice_id' => '',
		'admin_id'            => $_SESSION['admin_id'],
		'store_id'            => $tills->store_id,
		'total_amount'		  => $amount,
	);
	// The side a transaction does not use is zero: the amount columns are numbers.
	switch($transaction->type){
		case 'cash_in':
			$credit_amount = 0;
			$debit_amount  = $amount;
			break;
		default:
			$debit_amount  = 0;
			$credit_amount = $amount;
	}
	$ot_desc = db_prepare_input($_POST['ot_desc'] ?? '');
	$order->journal_rows[] = array(
			'id'            => '',
			'qty'           => '1',
			'gl_type'		=> 'ttl',
			'gl_account'    => $tills->gl_acct_id,
			'description'   => ($ot_desc == '') ? $transaction->description : $ot_desc,
			'debit_amount'  => $debit_amount,
			'credit_amount' => $credit_amount,
			'post_date'     => date('Y-m-d'));
	
	if($transaction->type == 'expenses'){
		$tax = (float)$currencies->clean_value(db_prepare_input($_POST['ot_tax'] ?? ''), $order->currencies_code) / $order->currencies_value;
		$tax_auths      = gen_build_tax_auth_array();
		$order->journal_rows[] = array(
			'id'            => '',
			'qty'           => '1',
		    'gl_type'		=> 'tax',
			'gl_account'    => $tax_auths[$_POST['ot_rate']]['account_id'],
			'description'   => $transaction->description,
			'taxable'       => db_prepare_input($_POST['ot_rate']),
			'debit_amount'  => $tax,
			'credit_amount' => $debit_amount,
			'post_date'     => date('Y-m-d'));
		$order->journal_rows[] = array(
			'id'            => '',
			'qty'           => '1',
			'gl_type'		=> 'sos',
			'gl_account'    => $transaction->gl_acct_id,
			'description'   => $transaction->description,
			'debit_amount'  => $credit_amount - $tax,
			'credit_amount' => $debit_amount,
			'post_date'     => date('Y-m-d'));
	}else{
		$order->journal_rows[] = array(
			'id'            => '',
			'qty'           => '1',
			'gl_type'		=> 'sos',
			'gl_account'    => $transaction->gl_acct_id,
			'description'   => $transaction->description,
			'debit_amount'  => $credit_amount,
			'credit_amount' => $debit_amount,
			'post_date'     => date('Y-m-d'));
	}
	
	// The till balance and the journal entry are one change: both are kept or neither is.
	// Post() answers true when it succeeded and rolls back itself on its own failures.
	$db->transStart();
	$tills->adjust_balance($transaction->type == 'cash_in' ? $amount : -$amount);
	// Balances are carried forward to later periods and the next fiscal year, as
	// every other page posts (issue #108).
	if ($order->Post('insert')) {
	  $db->transCommit();
	} else {
	  $db->transRollback();
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