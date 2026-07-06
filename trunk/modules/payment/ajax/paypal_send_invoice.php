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
//  Path: /modules/payment/ajax/paypal_send_invoice.php
//
// Asks PayPal to send the invoice email to the customer.

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
$invoice_id = isset($_POST['invoice_id']) ? trim(db_prepare_input($_POST['invoice_id'])) : '';
if ($invoice_id === '') {
  echo json_encode(['success' => false, 'error' => 'Invoice ID is required.']);
  die;
}

/**************   Send via PayPal   *********************************/
$result = $pp->paypal_request(
  'v2/invoicing/invoices/' . urlencode($invoice_id) . '/send',
  ['send_to_invoicer' => true, 'send_to_recipient' => true]
);

if ($result === false) {
  $msg = $pp->get_last_error() ?: 'Send request failed — check server log.';
  echo json_encode(['success' => false, 'error' => $msg]);
  die;
}

echo json_encode(['success' => true]);
die;
?>
