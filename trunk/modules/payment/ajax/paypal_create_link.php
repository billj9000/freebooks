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
//  Path: /modules/payment/ajax/paypal_create_link.php
//
// Creates a PayPal checkout order and returns the payer-action approval URL.
// Also stores the paypal_order_id <-> fb_invoice_id mapping for webhook auto-receipt.

header('Content-Type: application/json');

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();

/**************   Load PayPal module   ******************************/
gen_pull_language('payment', 'paypal');
$method = load_specific_method('payment', 'paypal');
if (!class_exists($method)) {
  echo json_encode(['success' => false, 'error' => 'PayPal module not available.']);
  die;
}
$pp = new paypal();

/**************   Read and validate inputs   ************************/
$raw_amount  = isset($_POST['amount'])        ? trim($_POST['amount'])                        : '0';
$invoice_ids = isset($_POST['invoice_ids'])   ? json_decode($_POST['invoice_ids'], true)      : [];
$cust_email  = isset($_POST['customer_email'])? trim(db_prepare_input($_POST['customer_email'])) : '';

$amount = (float)preg_replace('/[^0-9.]/', '', $raw_amount);
if ($amount <= 0) {
  echo json_encode(['success' => false, 'error' => 'Amount must be greater than zero.']);
  die;
}

// Use the first invoice ID for webhook-to-receipt linking.
$fb_invoice_id = 0;
$description   = 'Payment';
$cust_name     = '';
// The currency the DOCUMENT was raised in, read here rather than posted.
//
// The page settles one currency at a time, so the first ticked document decides
// it for all of them - and reading it from journal_main instead of from the
// request means a forged post cannot name a currency the invoice was never in.
// The amount arrives already in it, because that is what the page displays.
$currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP';
if (!empty($invoice_ids)) {
  $fb_invoice_id = (int)$invoice_ids[0];
  $inv = $db->Execute("SELECT purchase_invoice_id, bill_primary_name, bill_email,"
    . " currencies_code FROM " . TABLE_JOURNAL_MAIN
    . " WHERE id = " . $fb_invoice_id . " LIMIT 1");
  if (!$inv->EOF) {
    $description = 'Invoice ' . $inv->fields['purchase_invoice_id'];
    $cust_name   = $inv->fields['bill_primary_name'];
    if ($cust_email === '') $cust_email = $inv->fields['bill_email'];
    if (!empty($inv->fields['currencies_code'])
        && isset($currencies->currencies[$inv->fields['currencies_code']])) {
      $currency = $inv->fields['currencies_code'];
    }
  }
}

/**************   Build return / cancel URLs   **********************/
$base_url   = DIR_WS_FULL_PATH;
// The return page captures the payment, so it must know which company's order
// this is; see paypal_return.php.
$return_url = $base_url . 'paypal_return.php?invoice_id=' . $fb_invoice_id
            . '&company=' . rawurlencode($_SESSION['company']);
$cancel_url = $base_url . 'paypal_cancel.php';

/**************   Create PayPal Order   *****************************/
$url = $pp->create_payment_link($fb_invoice_id, $amount, $currency, $description, $return_url, $cancel_url);

if (!$url) {
  $detail = $pp->get_last_error() ?: 'Request failed — check server log.';
  echo json_encode(['success' => false, 'error' => $detail]);
  die;
}

echo json_encode(['success' => true, 'url' => $url, 'description' => $description, 'invoice_id' => $fb_invoice_id]);
die;
?>
