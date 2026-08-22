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
//  Path: /modules/payment/ajax/paypal_create_invoice.php
//
// Creates a PayPal-hosted invoice. The admin can then click "Send Invoice" to email it.

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
$raw_amount  = isset($_POST['amount'])        ? trim($_POST['amount'])                           : '0';
$invoice_ids = isset($_POST['invoice_ids'])   ? json_decode($_POST['invoice_ids'], true)         : [];
$cust_email  = isset($_POST['customer_email'])? trim(db_prepare_input($_POST['customer_email'])) : '';

$amount = (float)preg_replace('/[^0-9.]/', '', $raw_amount);
if ($amount <= 0) {
  echo json_encode(['success' => false, 'error' => 'Amount must be greater than zero.']);
  die;
}

// Look up customer and invoice details from the first invoice ID.
$cust_name     = '';
$description   = 'Invoice';
$fb_inv_num    = '';
$fb_invoice_id = 0;
// The currency and rate the DOCUMENT was raised in, read here rather than
// posted, for the reason paypal_create_link.php gives: a forged request must not
// get to name a currency the invoice was never in.
//
// The rate matters here in a way it does not there. The amount arrives from the
// page already in the entry's currency, but the LINE ITEMS below are read
// straight out of journal_item, and every journal amount is stored in the HOME
// currency. Left alone, the items would be pounds under a dollar code and the
// rounding line at the end would try to close a gap of a third of the invoice
// (issue #63).
$currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP';
$rate     = 1.0;
if (!empty($invoice_ids)) {
  $fb_invoice_id = (int)$invoice_ids[0];
  $inv = $db->Execute("SELECT purchase_invoice_id, bill_primary_name, bill_email,"
    . " currencies_code, currencies_value FROM " . TABLE_JOURNAL_MAIN
    . " WHERE id = " . $fb_invoice_id . " LIMIT 1");
  if (!$inv->EOF) {
    $cust_name   = $inv->fields['bill_primary_name'];
    $fb_inv_num  = $inv->fields['purchase_invoice_id'];
    $description = 'Invoice ' . $fb_inv_num;
    if ($cust_email === '') $cust_email = $inv->fields['bill_email'];
    if (!empty($inv->fields['currencies_code'])
        && isset($currencies->currencies[$inv->fields['currencies_code']])) {
      $currency = $inv->fields['currencies_code'];
      $rate     = (float)$inv->fields['currencies_value'] ?: 1.0;
    }
  }
}

$customer = ['name' => $cust_name, 'email' => $cust_email];

/**************   Build itemised line items from journal rows   *****/
// Built by the module, not here, so that it can be checked without creating a
// real invoice on PayPal - see paypal::build_invoice_items(). It was inline, and
// what that cost was a freight line converted at the wrong rate that nothing in
// the suite could have seen (issue #63).
$built          = $pp->build_invoice_items($fb_invoice_id, $currency, $rate, $amount, $description);
$items          = $built['items'];
$discount_total = $built['discount'];

/**************   Create PayPal Invoice   ***************************/
$result = $pp->create_paypal_invoice($fb_invoice_id, $customer, $items, false, $discount_total, $currency);

if (!$result) {
  $detail = $pp->get_last_error() ?: 'Request failed — check server log.';
  echo json_encode(['success' => false, 'error' => $detail]);
  die;
}

echo json_encode([
  'success'       => true,
  'invoice_id'    => $result['id'],
  'invoicer_url'  => $result['invoicer_url'],
  'recipient_url' => $result['recipient_url'],
  'description'   => $description,
  'fb_invoice_id' => $fb_invoice_id,
]);
die;
?>
