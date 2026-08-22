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
//  Path: /modules/phreebooks/pages/admin/pre_process.php
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_WORKING . 'functions/phreebooks.php');
require_once(DIR_FS_WORKING . 'classes/install.php');
require_once(DIR_FS_WORKING . 'classes/chart_of_accounts.php');
require_once(DIR_FS_WORKING . 'classes/tax_auths.php');
require_once(DIR_FS_WORKING . 'classes/tax_auths_vend.php');
require_once(DIR_FS_WORKING . 'classes/tax_rates.php');
require_once(DIR_FS_WORKING . 'classes/tax_rates_vend.php');

/**************   page specific initialization  *************************/
$error  = false; 
$install           = new phreebooks_admin();
$chart_of_accounts = new chart_of_accounts();
$tax_auths         = new tax_auths();
$tax_auths_vend    = new tax_auths_vend();
$tax_rates         = new tax_rates();
$tax_rates_vend    = new tax_rates_vend();

/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'import':
	validate_security($security_level, 3);
  	$delete_chart = !empty($_POST['delete_chart']);
	// basename(): the value names a file this page then reads off disk, and a
	// level 3 user is not the same thing as a user who may name any path on the
	// server. phreebooks_chart_read() checks it against the shipped list too.
	$std_chart    = basename(db_prepare_input($_POST['std_chart'] ?? ''));
	// first verify the file was uploaded ok
	if (!$std_chart) if (!validate_upload('file_name', 'text', 'txt')) break;

	// The new chart is read BEFORE anything is emptied. Replacing truncates two
	// tables, and doing that first meant a file that turned out not to be a
	// chart left the company with no chart of accounts at all and nothing to
	// put back.
	//
	// A shipped chart is read through phreebooks_chart_read(), which knows where
	// the charts for this locale actually are - this page used to list them out
	// of en_us when the locale had no charts directory of its own but then read
	// each one back from the locale's path, so the drop down offered names that
	// could not be opened. An uploaded file is parsed as it always was.
	if ($std_chart) {
	  $chart_info = phreebooks_chart_read($std_chart);
	  if (!$chart_info) { $messageStack->add(sprintf(GL_CHART_NOT_READ, $std_chart), 'error'); break; }
	  $chart_accounts = $chart_info['accounts'];
	} else {
	  $chart_info = false;
	  $accounts   = xml_to_object(file_get_contents($_FILES['file_name']['tmp_name']));
	  if (is_object($accounts) && isset($accounts->ChartofAccounts)) $accounts = $accounts->ChartofAccounts; // just pull the first one
	  // A file that is not a chart parses to a string or to false, and reading
	  // ->account off either is how an unhelpful upload became an unhelpful
	  // warning rather than a message saying what was wrong.
	  if (!is_object($accounts)) { $messageStack->add(sprintf(GL_CHART_NOT_READ, $_FILES['file_name']['name']), 'error'); break; }
	  if (isset($accounts->account) && is_object($accounts->account)) $accounts->account = array($accounts->account); // in case of only one chart entry
	  $chart_accounts = (isset($accounts->account) && is_array($accounts->account)) ? $accounts->account : array();
	}
	if (empty($chart_accounts)) { $messageStack->add(sprintf(GL_CHART_NOT_READ, $std_chart ? $std_chart : $_FILES['file_name']['name']), 'error'); break; }

	if ($delete_chart) {
	  $result = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . " limit 1");
	  if ($result->RecordCount() > 0) {
	    $messageStack->add(GL_JOURNAL_NOT_EMTPY,'error');
	    break;
	  }
	  $db->Execute("TRUNCATE TABLE " . TABLE_CHART_OF_ACCOUNTS);
	  $db->Execute("TRUNCATE TABLE " . TABLE_CHART_OF_ACCOUNTS_HISTORY);
	}
	foreach (phreebooks_chart_insert($chart_accounts) as $duplicate) {
	  $messageStack->add(sprintf(GL_ACCOUNT_DUPLICATE, $duplicate),'error');
	}
	build_and_check_account_history_records();
	// A chart REPLACED is a chart whose numbering has just changed underneath
	// every receivables and payables default, so the chart's own answers are
	// taken with it. Merging into an existing chart leaves them alone: those
	// settings are about the accounts already in use, not the ones just added.
	//
	// Redirected afterwards because write_configure() only define()s a constant
	// on the branch that INSERTS the row. These rows exist, so it updates them
	// and the constants this request already holds stay as they were - the
	// settings tabs alongside this one read those constants, and would show the
	// old accounts while the database held the new. Messages survive the
	// redirect; they are kept in the session.
	if ($delete_chart && $chart_info && !empty($chart_info['defaults'])) {
	  $applied = false;
	  foreach ($chart_info['defaults'] as $chart_key => $chart_acct) {
	    if (!array_key_exists($chart_key, $install->keys)) continue;
	    write_configure($chart_key, $chart_acct);
	    $applied = true;
	  }
	  if ($applied) {
	    $messageStack->add(sprintf(GL_CHART_DEFAULTS_SET, $chart_info['description']), 'success');
	    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	  }
	}
	break;
  case 'save':
	validate_security($security_level, 3);
  	// some special values for checkboxes
	$_POST['ar_use_credit_limit'] = isset($_POST['ar_use_credit_limit']) ? '1' : '0';
	$_POST['ap_use_credit_limit'] = isset($_POST['ap_use_credit_limit']) ? '1' : '0';
	// save general tab
	foreach ($install->keys as $key => $default) {
	  $field = strtolower($key);
      if (isset($_POST[$field])) write_configure($key, $_POST[$field]);
    }
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	$messageStack->add(PHREEBOOKS_CONFIG_SAVED,'success');
    break;
  case 'delete':
	validate_security($security_level, 4);
    $subject = $_POST['subject'];
    $id      = $_POST['rowSeq'];
	if (!$subject || !$id) break;
    $$subject->btn_delete($id);
    switch($subject) {
      case 'chart_of_accounts': $default_tab_id = 'chart_of_accounts'; break;
      case 'tax_auths':         $default_tab_id = 'tax_auths';         break;
      case 'tax_auths_vend':    $default_tab_id = 'tax_auths_vend';    break;
      case 'tax_rates':         $default_tab_id = 'tax_rates';         break;
      case 'tax_rates_vend':    $default_tab_id = 'tax_rates_vend';    break;
    }
	break;
  default:
}

/*****************   prepare to display templates  *************************/
// build some general pull down arrays
$sel_yes_no = array(
 array('id' => '0', 'text' => TEXT_NO),
 array('id' => '1', 'text' => TEXT_YES),
);

$sel_gl_desc = array(
 array('id' => '0', 'text' => TEXT_NUMBER),
 array('id' => '1', 'text' => TEXT_DESCRIPTION),
 array('id' => '2', 'text' => TEXT_BOTH),
);

$sel_order_lines = array(
  array('id' => '0', 'text' => TEXT_DOUBLE_MODE),
  array('id' => '1', 'text' => TEXT_SINGLE_MODE),
);

$sel_inv_due = array( // invoice date versus due date for aging
 array('id' => '0', 'text' => BNK_INVOICE_DATE),
 array('id' => '1', 'text' => BNK_DUE_DATE),
);

// the charts shipped for this locale, listed and read out of the same directory
$sel_chart = array_merge(array(array('id' => '0', 'text' => TEXT_SELECT)), gen_build_pull_down(phreebooks_charts()));

// some pre-defined gl accounts
$cash_chart = gen_coa_pull_down(2, false, true, false, $restrict_types = array(0));    // cash types only
$ar_chart   = gen_coa_pull_down(2, false, true, false, $restrict_types = array(2));    // ar types only
$ap_chart   = gen_coa_pull_down(2, false, true, false, $restrict_types = array(20));   // ap types only
$ocl_chart  = gen_coa_pull_down(2, false, true, false, $restrict_types = array(22));   // other current liability types only
$inc_chart  = gen_coa_pull_down(2, false, true, false, $restrict_types = array(30));   // income types only
$inv_chart  = gen_coa_pull_down(2, false, true, false, $restrict_types = array(4,34)); // inv, expenses types only

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', BOX_PHREEBOOKS_MODULE_ADM);

?>