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
//  Path: /modules/phreebooks/ajax/load_bill.php
//
$xml = NULL;
$security_level = validate_ajax_user();
/**************  include page specific files    *********************/
gen_pull_language('contacts');
require_once(DIR_FS_MODULES . 'phreebooks/defaults.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');

/**************   page specific initialization  *************************/
$error = false;
$debug = NULL;
$cID   = (int)db_prepare_input($_GET['cID']); // contact record ID
$bID   = (int)db_prepare_input($_GET['bID']); // journal record ID
$jID   = db_prepare_input($_GET['jID']); // journal ID

define('JOURNAL_ID', $jID);

if ($bID) {
  $bill = $db->Execute("select * from " . TABLE_JOURNAL_MAIN . " where id = '$bID'");
  if ($bill->fields['bill_acct_id']) $cID = $bill->fields['bill_acct_id']; // replace bID with ID from payment
} else {
  $bill = new objectInfo();
}
// select the customer and build the contact record
$contact = $db->Execute("select * from " . TABLE_CONTACTS . " where id = '$cID'");
$type = $contact->fields['type'];
define('ACCOUNT_TYPE', $type);
$bill_add  = $db->Execute("select * from " . TABLE_ADDRESS_BOOK . " 
  where ref_id = '" . $cID . "' and type in ('" . $type . "m', '" . $type . "b')");

//$debug .= 'Processing main_id = ' . $bID . ' and contact ID = ' . $cID . ' and type = ' . $type . chr(10);
// fetch the line items
//
// A STORED record prices every row in its own currency, because the page's
// arithmetic is in that currency and a row priced in another would not add up.
// A new one has no currency yet, so each document comes back in its own and the
// first row ticked is what the receipt adopts (issue #63).
$display_code  = $bID && !empty($bill->fields['currencies_code'])  ? $bill->fields['currencies_code']  : null;
$display_value = $bID && !empty($bill->fields['currencies_value']) ? $bill->fields['currencies_value'] : null;
$invoices  = fill_paid_invoice_array($bID, $cID, $type, $display_code, $display_value);
$item_list = $invoices['invoices'];

// An invoice whose payment has been asked for through a payment method is
// recorded when that method reports the payment. Its note tells whoever is about
// to record a payment for it here.
if (defined('MODULE_PAYMENT_STATUS') && $item_list) {
  require_once(DIR_FS_MODULES . 'payment/functions/request_dialog.php');
  $open = payment_latest_requests(array_column($item_list, 'id'));
  if ($open) {
    $pay_methods = payment_request_methods();
    foreach ($item_list as $k => $item) {
      $note = payment_request_open_note($open[(int)$item['id']] ?? null, $pay_methods);
      if ($note !== '') $item_list[$k]['pay_request'] = $note;
    }
  }
}

// some adjustments based on what we are doing
$bill->fields['payment_fields'] = $invoices['payment_fields'];
$bill->fields['post_date']      = $bill->fields['post_date'] ?: date('Y-m-d'); // stays ISO for <input type="date">

// build the form data
if (!empty($contact->fields)) {
  $xml .= "<BillContact>\n";
  foreach ($contact->fields as $key => $value) $xml .= "\t" . xmlEntry($key, $value);
  if ($bill_add->fields) while (!$bill_add->EOF) {
    $xml .= "\t<Address>\n";
    foreach ($bill_add->fields as $key => $value) $xml .= "\t\t" . xmlEntry($key, $value);
    $xml .= "\t</Address>\n";
    $bill_add->MoveNext();
  }
  $xml .= "</BillContact>\n";
}
if (!empty($bill->fields)) { // there was an bill to open
  $xml .= "<BillData>\n";
  foreach ($bill->fields as $key => $value) $xml .= "\t\t" . xmlEntry($key, $value);
  foreach ($item_list as $item) { // there should always be invoices to pull
    $xml .= "\t<Item>\n";
    foreach ($item as $key => $value) $xml .= "\t\t" . xmlEntry($key, $value);
    $xml .= "\t</Item>\n";
  }
  $xml .= "</BillData>\n";
}

if ($debug) $xml .= xmlEntry('debug', $debug);
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>