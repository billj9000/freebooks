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
//  Path: /modules/payment/methods/paypal/paypal.php

define('MODULE_PAYMENT_PAYPAL_VERSION', '1.0');
require_once(DIR_FS_MODULES . 'payment/classes/payment.php');

class paypal extends payment {
  public $code        = 'paypal';
  public $title       = MODULE_PAYMENT_PAYPAL_TEXT_TITLE;
  public $description = MODULE_PAYMENT_PAYPAL_TEXT_DESCRIPTION;
  public $sort_order  = 5;
  private $last_error = '';
  public function get_last_error() { return $this->last_error; }

  public function __construct() {
    parent::__construct();
    // field_0 = PayPal capture/transaction ID (blank for pending out-of-band payments)
    $this->payment_fields = $this->field_0; // single field; no card masking needed
    $this->key[] = ['key' => 'MODULE_PAYMENT_PAYPAL_CLIENT_ID',     'default' => '', 'text' => MODULE_PAYMENT_PAYPAL_CLIENT_ID_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_PAYPAL_CLIENT_SECRET', 'default' => '', 'text' => MODULE_PAYMENT_PAYPAL_CLIENT_SECRET_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_PAYPAL_TESTMODE',      'default' => 'Sandbox', 'text' => MODULE_PAYMENT_PAYPAL_TESTMODE_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL',  'default' => '', 'text' => MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL_DESC];
    $this->key[] = ['key' => 'MODULE_PAYMENT_PAYPAL_WEBHOOK_ID',    'default' => '', 'text' => MODULE_PAYMENT_PAYPAL_WEBHOOK_ID_DESC];
  }

  function configure($key) {
    switch ($key) {
      case 'MODULE_PAYMENT_PAYPAL_TESTMODE':
        $opts = [['id' => 'Sandbox', 'text' => TEXT_TEST], ['id' => 'Production', 'text' => TEXT_PRODUCTION]];
        return html_pull_down_menu(strtolower($key), $opts, defined($key) ? constant($key) : 'Sandbox');
      default:
        return parent::configure($key);
    }
  }

  // J18: no mandatory field validation (capture ID can be blank for pending payments).
  function javascript_validation() { return false; }

  // J18: show payment link / invoice creation UI.
  // J20: show refund status message only.
  function selection() {
    global $order;

    if (defined('JOURNAL_ID') && JOURNAL_ID == 20) {
      $poi = defined('purch_order_id') ? '' : '';
      $html = '<div id="paypal_refund_status" style="font-style:italic">'
            . MODULE_PAYMENT_PAYPAL_TEXT_PENDING . '</div>' . "\n"
            . '<script>' . "\n"
            . 'document.addEventListener("DOMContentLoaded", function() {' . "\n"
            . '  var poiEl = document.getElementById("purch_order_id");' . "\n"
            . '  var stEl  = document.getElementById("paypal_refund_status");' . "\n"
            . '  function ppRefundStatus() {' . "\n"
            . '    if (!stEl) return;' . "\n"
            . '    var v = poiEl ? poiEl.value : "";' . "\n"
            . '    stEl.textContent = v ? ' . json_encode(MODULE_PAYMENT_PAYPAL_TEXT_INVOICE_SENT) . ' + " " + v : ' . json_encode(MODULE_PAYMENT_PAYPAL_TEXT_PENDING) . ';' . "\n"
            . '  }' . "\n"
            . '  ppRefundStatus();' . "\n"
            . '  if (poiEl) { var _d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,"value"); Object.defineProperty(poiEl,"value",{get:function(){return _d.get.call(this);},set:function(v){_d.set.call(this,v);ppRefundStatus();}});' . "\n"
            . '  }' . "\n"
            . '});' . "\n"
            . '</script>' . "\n";
      return ['id' => $this->code, 'page' => $this->title, 'fields' => [['title' => '', 'field' => $html]]];
    }

    $cust_email = isset($order) && isset($order->bill_email) ? htmlspecialchars($order->bill_email) : '';
    $txn_val    = htmlspecialchars($this->field_0);

    $working    = htmlspecialchars(MODULE_PAYMENT_PAYPAL_TEXT_WORKING);
    $lbl_link   = htmlspecialchars(MODULE_PAYMENT_PAYPAL_TEXT_CREATE_LINK);
    $lbl_inv    = htmlspecialchars(MODULE_PAYMENT_PAYPAL_TEXT_CREATE_INVOICE);
    $lbl_send   = htmlspecialchars(MODULE_PAYMENT_PAYPAL_TEXT_SEND_INVOICE);
    $lbl_copy   = htmlspecialchars(MODULE_PAYMENT_PAYPAL_TEXT_COPY_LINK);
    $lbl_email  = htmlspecialchars(MODULE_PAYMENT_PAYPAL_TEXT_EMAIL_CUSTOMER);
    $err_create = addslashes(MODULE_PAYMENT_PAYPAL_ERROR_CREATE_ORDER);

    $html = '<div style="font-size:11px">' . "\n"

      // Transaction ID row
      . '<div class="row g-1 align-items-center mb-1">'
      . '<label class="col-5 text-end text-nowrap" style="font-size:11px">' . MODULE_PAYMENT_PAYPAL_TEXT_TRANSACTION_ID . '</label>'
      . '<div class="col-7">' . html_input_field('paypal_field_0', $txn_val, 'id="paypal_field_0" class="form-control form-control-sm"') . '</div>'
      . '</div>' . "\n"

      // Customer email for emailing links/invoices
      . '<div class="row g-1 align-items-center mb-1">'
      . '<label class="col-5 text-end text-nowrap" style="font-size:11px">' . GEN_EMAIL . '</label>'
      . '<div class="col-7"><input type="email" id="paypal_cust_email" class="form-control form-control-sm" value="' . $cust_email . '" placeholder="customer@example.com"></div>'
      . '</div>' . "\n"

      // Payment link
      . '<div class="row g-1 align-items-center mb-1">'
      . '<div class="col-5"></div>'
      . '<div class="col-7">'
      . '<button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="ppCreateLink()">' . $lbl_link . '</button>'
      . '<button type="button" class="btn btn-sm btn-outline-secondary d-none" id="paypal_email_link_btn" onclick="ppEmailLink()">' . $lbl_email . '</button>'
      . '</div>'
      . '</div>' . "\n"
      . '<div id="paypal_link_result" class="small text-break mb-1" style="display:none"></div>' . "\n"

      // PayPal invoice
      . '<div class="row g-1 align-items-center mb-1">'
      . '<div class="col-5"></div>'
      . '<div class="col-7">'
      . '<button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="ppCreateInvoice()">' . $lbl_inv . '</button>'
      . '<button type="button" class="btn btn-sm btn-outline-secondary d-none" id="paypal_send_inv_btn" onclick="ppSendInvoice()">' . $lbl_send . '</button>'
      . '</div>'
      . '</div>' . "\n"
      . '<div id="paypal_inv_result" class="small text-break mb-1" style="display:none"></div>' . "\n"

      . '</div>' . "\n" // end outer div

      . '<script>' . "\n"
      . 'var ppCurrentLinkUrl      = "";' . "\n"
      . 'var ppCurrentLinkDesc     = "";' . "\n"
      . 'var ppCurrentLinkInvoice  = 0;' . "\n"
      . 'var ppCurrentInvId        = "";' . "\n"
      . 'var ppCsrf = (function(){ var m = document.querySelector("meta[name=\'csrf-token\']"); return m ? m.getAttribute("content") : ""; })();' . "\n"
      . "\n"
      . 'function ppCollectInvoiceIds() {' . "\n"
      . '  var ids = [];' . "\n"
      . '  for (var i = 1; ; i++) {' . "\n"
      . '    var els = document.getElementsByName("id_"+i);' . "\n"
      . '    if (!els.length) break;' . "\n"
      . '    if (els[0].value) ids.push(els[0].value);' . "\n"
      . '  }' . "\n"
      . '  return ids;' . "\n"
      . '}' . "\n"
      . "\n"
      . 'function ppCreateLink() {' . "\n"
      . '  var total  = (document.getElementById("total") || {}).value || "";' . "\n"
      . '  var ids    = ppCollectInvoiceIds();' . "\n"
      . '  var email  = (document.getElementById("paypal_cust_email") || {}).value || "";' . "\n"
      . '  var res    = document.getElementById("paypal_link_result");' . "\n"
      . '  res.style.display = "block";' . "\n"
      . '  res.textContent   = ' . json_encode($working) . ';' . "\n"
      . '  document.getElementById("paypal_email_link_btn").classList.add("d-none");' . "\n"
      . '  fetch("index.php?module=payment&page=ajax&op=paypal_create_link", {' . "\n"
      . '    method: "POST",' . "\n"
      . '    headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n"
      . '    body: "csrf_token=" + encodeURIComponent(ppCsrf)' . "\n"
      . '        + "&amount=" + encodeURIComponent(total)' . "\n"
      . '        + "&invoice_ids=" + encodeURIComponent(JSON.stringify(ids))' . "\n"
      . '        + "&customer_email=" + encodeURIComponent(email)' . "\n"
      . '  }).then(function(r){return r.json();}).then(function(d) {' . "\n"
      . '    if (d.success) {' . "\n"
      . '      ppCurrentLinkUrl     = d.url;' . "\n"
      . '      ppCurrentLinkDesc    = d.description || "";' . "\n"
      . '      ppCurrentLinkInvoice = d.invoice_id  || 0;' . "\n"
      . '      res.innerHTML = ' . json_encode(MODULE_PAYMENT_PAYPAL_TEXT_LINK_CREATED) . ' + "<br>"' . "\n"
      . '        + "<a href=\\"" + d.url + "\\" target=\\"_blank\\">" + d.url + "</a>"' . "\n"
      . '        + " <button type=\\"button\\" class=\\"btn btn-xs btn-outline-secondary\\" onclick=\\"ppCopyLink()\\">Copy</button>"' . "\n"
      . '        + "<br><span class=\\"text-muted\\">' . addslashes(MODULE_PAYMENT_PAYPAL_TEXT_LINK_EXPIRES) . '</span>";' . "\n"
      . '      document.getElementById("paypal_email_link_btn").classList.remove("d-none");' . "\n"
      . '    } else {' . "\n"
      . '      res.textContent = ' . json_encode(rtrim($err_create, ' ') . ': ') . ' + (d.error || "Unknown error");' . "\n"
      . '    }' . "\n"
      . '  }).catch(function(e) { res.textContent = "Request failed: " + e; });' . "\n"
      . '}' . "\n"
      . "\n"
      . 'function ppCopyLink() {' . "\n"
      . '  if (navigator.clipboard && ppCurrentLinkUrl) navigator.clipboard.writeText(ppCurrentLinkUrl);' . "\n"
      . '}' . "\n"
      . "\n"
      . 'function ppEmailLink() {' . "\n"
      . '  var email = (document.getElementById("paypal_cust_email") || {}).value || "";' . "\n"
      . '  if (!email || !ppCurrentLinkUrl) return;' . "\n"
      . '  var res = document.getElementById("paypal_link_result");' . "\n"
      . '  fetch("index.php?module=payment&page=ajax&op=paypal_email_link", {' . "\n"
      . '    method: "POST",' . "\n"
      . '    headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n"
      . '    body: "csrf_token=" + encodeURIComponent(ppCsrf) + "&email=" + encodeURIComponent(email) + "&url=" + encodeURIComponent(ppCurrentLinkUrl) + "&description=" + encodeURIComponent(ppCurrentLinkDesc) + "&invoice_id=" + encodeURIComponent(ppCurrentLinkInvoice)' . "\n"
      . '  }).then(function(r){return r.json();}).then(function(d) {' . "\n"
      . '    res.innerHTML += d.success ? " <span class=\\"text-success\\">&#10003; Emailed</span>" : " <span class=\\"text-danger\\">Email failed</span>";' . "\n"
      . '  });' . "\n"
      . '}' . "\n"
      . "\n"
      . 'function ppCreateInvoice() {' . "\n"
      . '  var total  = (document.getElementById("total") || {}).value || "";' . "\n"
      . '  var ids    = ppCollectInvoiceIds();' . "\n"
      . '  var email  = (document.getElementById("paypal_cust_email") || {}).value || "";' . "\n"
      . '  var res    = document.getElementById("paypal_inv_result");' . "\n"
      . '  res.style.display = "block";' . "\n"
      . '  res.textContent   = ' . json_encode($working) . ';' . "\n"
      . '  document.getElementById("paypal_send_inv_btn").classList.add("d-none");' . "\n"
      . '  fetch("index.php?module=payment&page=ajax&op=paypal_create_invoice", {' . "\n"
      . '    method: "POST",' . "\n"
      . '    headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n"
      . '    body: "csrf_token=" + encodeURIComponent(ppCsrf)' . "\n"
      . '        + "&amount=" + encodeURIComponent(total)' . "\n"
      . '        + "&invoice_ids=" + encodeURIComponent(JSON.stringify(ids))' . "\n"
      . '        + "&customer_email=" + encodeURIComponent(email)' . "\n"
      . '  }).then(function(r){return r.json();}).then(function(d) {' . "\n"
      . '    if (d.success) {' . "\n"
      . '      ppCurrentInvId = d.invoice_id;' . "\n"
      . '      var idDisplay = d.invoicer_url' . "\n"
      . '        ? "<a href=\\"" + d.invoicer_url + "\\" target=\\"_blank\\">" + d.invoice_id + "</a>"' . "\n"
      . '        : d.invoice_id;' . "\n"
      . '      res.innerHTML = ' . json_encode(sprintf(MODULE_PAYMENT_PAYPAL_TEXT_INVOICE_CREATED, '%s')) . '.replace("%s", idDisplay);' . "\n"
      . '      document.getElementById("paypal_send_inv_btn").classList.remove("d-none");' . "\n"
      . '    } else {' . "\n"
      . '      res.textContent = ' . json_encode(rtrim(MODULE_PAYMENT_PAYPAL_ERROR_CREATE_INVOICE, ' ') . ': ') . ' + (d.error || "Unknown error");' . "\n"
      . '    }' . "\n"
      . '  }).catch(function(e) { res.textContent = "Request failed: " + e; });' . "\n"
      . '}' . "\n"
      . "\n"
      . 'function ppSendInvoice() {' . "\n"
      . '  if (!ppCurrentInvId) return;' . "\n"
      . '  var res = document.getElementById("paypal_inv_result");' . "\n"
      . '  fetch("index.php?module=payment&page=ajax&op=paypal_send_invoice", {' . "\n"
      . '    method: "POST",' . "\n"
      . '    headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n"
      . '    body: "csrf_token=" + encodeURIComponent(ppCsrf) + "&invoice_id=" + encodeURIComponent(ppCurrentInvId)' . "\n"
      . '  }).then(function(r){return r.json();}).then(function(d) {' . "\n"
      . '    res.textContent += d.success ? " — ' . addslashes(MODULE_PAYMENT_PAYPAL_TEXT_INVOICE_SENT) . '" : " — ' . addslashes(MODULE_PAYMENT_PAYPAL_ERROR_SEND_INVOICE) . '" + (d.error || "");' . "\n"
      . '    document.getElementById("paypal_send_inv_btn").classList.add("d-none");' . "\n"
      . '  });' . "\n"
      . '}' . "\n"
      . '</script>' . "\n";

    return ['id' => $this->code, 'page' => $this->title, 'fields' => [['title' => '', 'field' => $html]]];
  }

  // Called inside the DB transaction after the journal entry is written.
  // If field_0 is empty, save is allowed (pending payment). If set, verify with PayPal.
  function before_process() {
    global $messageStack;
    $capture_id = trim($this->field_0);
    if ($capture_id === '') {
      $this->payment_fields = '';
      return false; // pending — allow save
    }
    $token = $this->get_access_token();
    if (!$token) {
      $messageStack->add(MODULE_PAYMENT_PAYPAL_ERROR_TOKEN, 'error');
      return true;
    }
    $data = $this->paypal_request('v2/payments/captures/' . urlencode($capture_id), null, 'GET');
    if (!$data || ($data['status'] ?? '') !== 'COMPLETED') {
      $messageStack->add(MODULE_PAYMENT_PAYPAL_ERROR_VERIFY, 'error');
      return true;
    }
    $this->payment_fields = $capture_id;
    return false;
  }

  // Refund an existing PayPal capture. Called by banking.php for J20 customer refunds.
  function refund($capture_id, $amount_minor, $currency) {
    global $currencies;
    if (!$capture_id || $amount_minor <= 0) return false;
    $token = $this->get_access_token();
    if (!$token) { $this->refund_error = MODULE_PAYMENT_PAYPAL_ERROR_TOKEN; return false; }
    $dp  = isset($currencies->currencies[$currency]['decimal_places']) ? (int)$currencies->currencies[$currency]['decimal_places'] : 2;
    $val = number_format($amount_minor / pow(10, $dp), $dp, '.', '');
    $payload = [
      'amount' => ['value' => $val, 'currency_code' => $currency],
    ];
    $data = $this->paypal_request('v2/payments/captures/' . urlencode($capture_id) . '/refund', $payload);
    if (!$data) { $this->refund_error = 'No response from PayPal'; return false; }
    if (isset($data['details'][0]['issue'])) {
      $this->refund_error = $data['details'][0]['description'] ?? $data['details'][0]['issue'];
      return false;
    }
    return $data['id'] ?? true;
  }

  // Create a PayPal checkout order and return the payer-action approval URL.
  function create_payment_link($fb_invoice_id, $amount, $currency, $description, $return_url, $cancel_url) {
    global $db;
    $token = $this->get_access_token();
    if (!$token) return false;
    $payload = [
      'intent' => 'CAPTURE',
      'purchase_units' => [[
        'custom_id'   => 'fb:' . (int)$fb_invoice_id,
        'description' => $description,
        'amount'      => ['currency_code' => $currency, 'value' => number_format((float)$amount, 2, '.', '')],
      ]],
      'application_context' => [
        'return_url'   => $return_url,
        'cancel_url'   => $cancel_url,
        'user_action'  => 'PAY_NOW',
        'landing_page' => 'BILLING',
      ],
    ];
    // Pre-populate buyer details from the invoice so the customer doesn't have to re-enter them.
    if ((int)$fb_invoice_id > 0) {
      $addr = $this->build_payer_payload((int)$fb_invoice_id);
      if (!empty($addr['payer']))    $payload['payer'] = $addr['payer'];
      if (!empty($addr['shipping'])) $payload['purchase_units'][0]['shipping'] = $addr['shipping'];
    }
    $data = $this->paypal_request('v2/checkout/orders', $payload);
    if (!$data || !isset($data['id'])) return false;
    $paypal_order_id = $data['id'];
    $approve_url = '';
    foreach (($data['links'] ?? []) as $link) {
      if ($link['rel'] === 'payer-action' || $link['rel'] === 'approve') { $approve_url = $link['href']; break; }
    }
    if (!$approve_url) return false;
    // Store mapping so the webhook can auto-create the J18 receipt.
    $this->ensure_paypal_orders_table();
    $db->Execute("INSERT IGNORE INTO pb_paypal_orders (paypal_order_id, fb_invoice_id, status)"
      . " VALUES ('" . db_input($paypal_order_id) . "', " . (int)$fb_invoice_id . ", 'PENDING')");
    return $approve_url;
  }

  // Create a PayPal invoice, optionally send it. Returns invoice ID or false.
  // $fb_invoice_id: FreeBooks journal_main.id, stored so the webhook can auto-create the J18 receipt.
  function create_paypal_invoice($fb_invoice_id, $customer, $line_items, $send = false, $discount = 0.0) {
    global $db;
    $token = $this->get_access_token();
    if (!$token) return false;
    // invoicer.email_address must exactly match a verified email on the PayPal account — omit it
    // and let PayPal default to the account's primary email.
    $invoicer = [];
    if (defined('STORE_NAME') && STORE_NAME) $invoicer['business_name'] = STORE_NAME;

    // Build recipient billing_info, omitting fields that are empty.
    $billing_info = [];
    $cust_name = trim($customer['name'] ?? '');
    if ($cust_name) {
      $sp = strrpos($cust_name, ' ');
      $billing_info['name'] = $sp !== false
        ? ['given_name' => substr($cust_name, 0, $sp), 'surname' => substr($cust_name, $sp + 1)]
        : ['given_name' => $cust_name, 'surname' => $cust_name];
    }
    if (!empty($customer['email'])) $billing_info['email_address'] = $customer['email'];

    $currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';
    $detail = [
      'currency_code' => $currency,
      'invoice_date'  => date('Y-m-d'),
      'payment_term'  => ['term_type' => 'DUE_ON_RECEIPT'],
    ];
    if ($discount > 0.0) {
      $detail['discount'] = ['amount' => ['currency_code' => $currency, 'value' => number_format($discount, 2, '.', '')]];
    }

    $payload = [
      'detail'             => $detail,
      'primary_recipients' => [['billing_info' => $billing_info]],
      'items'              => $line_items,
    ];
    if ($invoicer) $payload['invoicer'] = $invoicer;
    $data = $this->paypal_request('v2/invoicing/invoices', $payload);
    if (!$data || !isset($data['id'])) return false;
    $invoice_id   = $data['id'];
    $invoicer_url = $data['detail']['metadata']['invoicer_view_url']  ?? '';
    $recipient_url = $data['detail']['metadata']['recipient_view_url'] ?? '';
    // Store mapping so the INVOICING.INVOICE.PAID webhook can auto-create the J18 receipt.
    if ((int)$fb_invoice_id > 0) {
      $this->ensure_paypal_orders_table();
      $db->Execute("INSERT IGNORE INTO pb_paypal_orders (paypal_order_id, fb_invoice_id, source_type, status)"
        . " VALUES ('" . db_input($invoice_id) . "', " . (int)$fb_invoice_id . ", 'invoice', 'PENDING')");
    }
    return ['id' => $invoice_id, 'invoicer_url' => $invoicer_url, 'recipient_url' => $recipient_url];
  }

  // Returns a URL to view a transaction on PayPal's website.
  function get_transaction_url($transaction_id) {
    $sandbox = defined('MODULE_PAYMENT_PAYPAL_TESTMODE') && MODULE_PAYMENT_PAYPAL_TESTMODE === 'Sandbox';
    $base    = $sandbox ? 'https://www.sandbox.paypal.com/activity/payment/' : 'https://www.paypal.com/activity/payment/';
    return $base . urlencode($transaction_id);
  }

  // --- Private helpers ---

  // Build payer (billing) and shipping blocks from journal_main for order pre-population.
  // FreeBooks stores 3-letter ISO alpha-3 country codes (e.g. GBR, USA); PayPal requires alpha-2.
  private function build_payer_payload($fb_invoice_id) {
    global $db;
    $inv = $db->Execute(
      "SELECT bill_primary_name, bill_email, bill_address1, bill_address2,"
      . " bill_city_town, bill_state_province, bill_postal_code, bill_country_code,"
      . " ship_primary_name, ship_address1, ship_address2, ship_city_town,"
      . " ship_state_province, ship_postal_code, ship_country_code"
      . " FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)$fb_invoice_id . " LIMIT 1"
    );
    if ($inv->EOF) return [];
    $r = $inv->fields;

    $result = [];

    // --- Payer (billing) ---
    $payer = [];
    if (!empty($r['bill_email'])) {
      $payer['email_address'] = $r['bill_email'];
    }
    $full = trim($r['bill_primary_name'] ?? '');
    if ($full) {
      $sp = strrpos($full, ' ');
      $payer['name'] = [
        'given_name' => $sp !== false ? substr($full, 0, $sp) : $full,
        'surname'    => $sp !== false ? substr($full, $sp + 1) : $full,
      ];
    }
    $bc = strtoupper(trim(gen_get_country_iso_2_from_3($r['bill_country_code'] ?? '')));
    if (strlen($bc) === 2 && !empty($r['bill_address1'])) {
      $a = ['country_code' => $bc, 'address_line_1' => $r['bill_address1']];
      if (!empty($r['bill_address2']))       $a['address_line_2'] = $r['bill_address2'];
      if (!empty($r['bill_city_town']))      $a['admin_area_2']   = $r['bill_city_town'];
      if (!empty($r['bill_state_province'])) $a['admin_area_1']   = $r['bill_state_province'];
      if (!empty($r['bill_postal_code']))    $a['postal_code']    = $r['bill_postal_code'];
      $payer['address'] = $a;
    }
    if ($payer) $result['payer'] = $payer;

    // --- Shipping ---
    $sc = strtoupper(trim(gen_get_country_iso_2_from_3($r['ship_country_code'] ?? '')));
    if (strlen($sc) === 2 && !empty($r['ship_address1'])) {
      $ship = [];
      $sname = trim($r['ship_primary_name'] ?? '');
      if ($sname) $ship['name'] = ['full_name' => $sname];
      $a = ['country_code' => $sc, 'address_line_1' => $r['ship_address1']];
      if (!empty($r['ship_address2']))       $a['address_line_2'] = $r['ship_address2'];
      if (!empty($r['ship_city_town']))      $a['admin_area_2']   = $r['ship_city_town'];
      if (!empty($r['ship_state_province'])) $a['admin_area_1']   = $r['ship_state_province'];
      if (!empty($r['ship_postal_code']))    $a['postal_code']    = $r['ship_postal_code'];
      $ship['address'] = $a;
      $result['shipping'] = $ship;
    }

    return $result;
  }

  // Get an OAuth2 access token, caching it in pb_configuration.
  public function get_access_token() {
    global $db;
    $client_id = defined('MODULE_PAYMENT_PAYPAL_CLIENT_ID')     ? MODULE_PAYMENT_PAYPAL_CLIENT_ID     : '';
    $secret    = defined('MODULE_PAYMENT_PAYPAL_CLIENT_SECRET') ? MODULE_PAYMENT_PAYPAL_CLIENT_SECRET : '';
    if (!$client_id || !$secret) return false;
    // Check cache
    $r = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = 'PAYPAL_ACCESS_TOKEN_CACHE' LIMIT 1");
    if (!$r->EOF) {
      $parts = explode(':', $r->fields['configuration_value'], 2);
      if (count($parts) === 2 && (int)$parts[0] > time() + 60) return $parts[1];
    }
    $ch = curl_init($this->paypal_base_url() . 'v1/oauth2/token');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
      CURLOPT_USERPWD        => $client_id . ':' . $secret,
      CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
      CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    if (!$body) return false;
    $data = json_decode($body, true);
    if (!isset($data['access_token'])) return false;
    $expires_at = time() + (int)($data['expires_in'] ?? 3600);
    $cache_val  = $expires_at . ':' . $data['access_token'];
    $exists = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = 'PAYPAL_ACCESS_TOKEN_CACHE' LIMIT 1");
    if ($exists->EOF) {
      $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value)"
        . " VALUES ('PAYPAL_ACCESS_TOKEN_CACHE', '" . db_input($cache_val) . "')");
    } else {
      $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . db_input($cache_val) . "'"
        . " WHERE configuration_key = 'PAYPAL_ACCESS_TOKEN_CACHE'");
    }
    return $data['access_token'];
  }

  // CURL wrapper for PayPal REST API calls.
  public function paypal_request($path, $body = null, $method = 'POST') {
    $token = $this->get_access_token();
    if (!$token) return false;
    $url = $this->paypal_base_url() . ltrim($path, '/');
    $ch  = curl_init($url);
    $headers = [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
      'Prefer: return=representation',
    ];
    if ($method === 'POST' || $method === 'PATCH') {
      $headers[] = 'PayPal-Request-Id: fb-' . md5($path . microtime());
    }
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_TIMEOUT        => 20,
    ]);
    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) return false; // curl-level failure
    if ($http_code >= 400) {
      $data = $resp !== '' ? json_decode($resp, true) : null;
      $this->last_error = isset($data['message']) ? $data['message'] : ('HTTP ' . $http_code);
      error_log('PayPal API error: ' . $method . ' ' . $path . ' HTTP ' . $http_code . ': ' . substr($resp, 0, 1000));
      return false;
    }
    if ($resp === '') return true; // 2xx with empty body
    $data = json_decode($resp, true);
    return $data ?: false;
  }

  public function paypal_base_url() {
    $sandbox = defined('MODULE_PAYMENT_PAYPAL_TESTMODE') && MODULE_PAYMENT_PAYPAL_TESTMODE === 'Sandbox';
    return $sandbox ? 'https://api-m.sandbox.paypal.com/' : 'https://api-m.paypal.com/';
  }

  public function ensure_paypal_orders_table() {
    global $db;
    $db->Execute("CREATE TABLE IF NOT EXISTS pb_paypal_orders ("
      . "id INT AUTO_INCREMENT PRIMARY KEY,"
      . "paypal_order_id VARCHAR(64) NOT NULL,"
      . "fb_invoice_id INT NOT NULL,"
      . "capture_id VARCHAR(64),"
      . "source_type VARCHAR(16) NOT NULL DEFAULT 'order',"
      . "status VARCHAR(32) DEFAULT 'PENDING',"
      . "created_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
      . "UNIQUE KEY uk_order (paypal_order_id)"
      . ") ENGINE=InnoDB DEFAULT CHARSET=utf8");
  }
}
?>
