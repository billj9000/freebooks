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
if (!empty($invoice_ids)) {
  $fb_invoice_id = (int)$invoice_ids[0];
  $inv = $db->Execute("SELECT purchase_invoice_id, bill_primary_name, bill_email FROM " . TABLE_JOURNAL_MAIN
    . " WHERE id = " . $fb_invoice_id . " LIMIT 1");
  if (!$inv->EOF) {
    $cust_name   = $inv->fields['bill_primary_name'];
    $fb_inv_num  = $inv->fields['purchase_invoice_id'];
    $description = 'Invoice ' . $fb_inv_num;
    if ($cust_email === '') $cust_email = $inv->fields['bill_email'];
  }
}

$currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP';
$customer = ['name' => $cust_name, 'email' => $cust_email];

/**************   Build itemised line items from journal rows   *****/
$sos_items      = [];
$frt_items      = [];
$tax_items      = [];
$item_total     = 0.0;
$discount_total = 0.0;

if ($fb_invoice_id > 0 && defined('TABLE_JOURNAL_ITEM')) {
  $rows = $db->Execute(
    "SELECT gl_type, sku, qty, description, debit_amount, credit_amount, full_price"
    . " FROM " . TABLE_JOURNAL_ITEM
    . " WHERE ref_id = " . $fb_invoice_id
    . " AND gl_type IN ('sos', 'frt', 'tax', 'dsc')"
    . " ORDER BY item_cnt"
  );
  while (!$rows->EOF) {
    $row = $rows->fields;
    $gt  = $row['gl_type'];

    if ($gt === 'dsc') {
      $discount_total += (float)$row['debit_amount'];
    } else {
      $credit = (float)$row['credit_amount'];
      if ($credit == 0) { $rows->MoveNext(); continue; }

      $qty = (float)$row['qty'];
      if ($qty <= 0) $qty = 1;

      // Use full_price (unit price) when available; fall back to credit_amount/qty.
      // Set qty=1 in that case to avoid re-introducing division rounding.
      if ((float)$row['full_price'] != 0) {
        $unit = (float)$row['full_price'];
      } else {
        $unit = $credit;
        $qty  = 1;
      }

      if ($gt === 'sos') {
        $name = trim($row['description'] ?? '');
        if (!empty($row['sku'])) $name = $row['sku'] . ($name ? ' ' . $name : '');
        if (!$name) $name = 'Item';
      } elseif ($gt === 'frt') {
        $name = 'Shipping';
      } else { // tax
        $name = trim($row['description'] ?? '') ?: 'Tax';
      }

      // Format quantity: strip trailing decimal zeros (e.g. "4.00" → "4", "0.50" → "0.5").
      $qty_str = rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');

      $entry = [
        'name'        => mb_substr($name, 0, 200),
        'quantity'    => $qty_str,
        'unit_amount' => ['currency_code' => $currency, 'value' => number_format($unit, 2, '.', '')],
      ];
      if ($gt === 'sos')      { $sos_items[] = $entry; }
      elseif ($gt === 'frt')  { $frt_items[] = $entry; }
      else                    { $tax_items[] = $entry; }
      $item_total += round($unit * $qty, 2);
    }
    $rows->MoveNext();
  }
}

// Products → Shipping → Taxes
$items = array_merge($sos_items, $frt_items, $tax_items);

// Fallback to a single summary line if no rows were found.
if (empty($items)) {
  $items      = [['name' => $description, 'quantity' => '1',
                  'unit_amount' => ['currency_code' => $currency, 'value' => number_format($amount, 2, '.', '')]]];
  $item_total = $amount;
}

// Close any rounding gap between PayPal's item sum and the FreeBooks total.
$paypal_total = round($item_total - $discount_total, 2);
$diff = round($amount - $paypal_total, 2);
if ($diff != 0.0 && abs($diff) <= 0.05) {
  $items[] = [
    'name'        => 'Rounding adjustment',
    'quantity'    => '1',
    'unit_amount' => ['currency_code' => $currency, 'value' => number_format($diff, 2, '.', '')],
  ];
}

/**************   Create PayPal Invoice   ***************************/
$result = $pp->create_paypal_invoice($fb_invoice_id, $customer, $items, false, $discount_total);

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
