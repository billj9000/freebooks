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
//  Path: /modules/payment/functions/request_dialog.php
//
// The Request payment dialog, for a page that offers it on an invoice. The page
// calls payment_request_dialog() once, and each invoice's button or icon calls
// payRequestOpen(<journal_main id>) in the browser. What the dialog shows comes
// from modules/payment/ajax/request_payment.php.

// Whether the user may ask for payment: the Customer Receipts permission at the
// level that can enter one.
function payment_request_allowed() {
  return defined('SECURITY_ID_CUSTOMER_RECEIPTS')
      && (int)($_SESSION['admin_security'][SECURITY_ID_CUSTOMER_RECEIPTS] ?? 0) >= 2;
}

// The installed methods that can ask for payment, as code => object; with
// $currency, only those that can ask in it. Only a method that implements
// create_request() is constructed: the others have nothing to offer here, and
// some cannot be built outside the pages they were written for.
function payment_request_methods($currency = null) {
  require_once(DIR_FS_MODULES . 'payment/classes/payment.php');
  $dir = DIR_FS_MODULES . 'payment/methods/';
  $methods = [];
  foreach (scandir($dir) as $m) {
    if ($m[0] === '.' || !file_exists($dir . $m . '/' . $m . '.php')) continue;
    if (!defined('MODULE_PAYMENT_' . strtoupper($m) . '_STATUS')) continue;
    load_specific_method('payment', $m);
    if (!class_exists($m)) continue;
    if ((new ReflectionMethod($m, 'create_request'))->getDeclaringClass()->getName() === 'payment') continue;
    $obj = new $m();
    if (empty($obj->request_types)) continue;
    if ($currency !== null && $currency !== DEFAULT_CURRENCY && empty($obj->multi_currency)) continue;
    $methods[$m] = $obj;
  }
  return $methods;
}

// The newest request for each of the invoices $invoice_ids, as id => row.
function payment_latest_requests($invoice_ids) {
  global $db;
  $ids = array_filter(array_map('intval', (array)$invoice_ids));
  if (!$ids || !defined('TABLE_PAYMENT_REQUESTS') || !db_table_exists(TABLE_PAYMENT_REQUESTS)) return [];
  $out = [];
  $r = $db->Execute("SELECT * FROM " . TABLE_PAYMENT_REQUESTS . " WHERE ref_id IN (" . implode(',', $ids) . ")"
    . " ORDER BY created_at, id");
  while (!$r->EOF) { $out[(int)$r->fields['ref_id']] = $r->fields; $r->MoveNext(); }
  return $out;
}

// What a request is called: its method's label for the kind of request.
function payment_request_label($request, $methods) {
  return $methods[$request['method']]->request_types[$request['request_type']]
      ?? ($request['method'] . ' ' . $request['request_type']);
}

// A request's status, as a word.
function payment_request_status_text($request) {
  $status = payment::request_display_status($request);
  return defined('PAY_REQUEST_STATUS_' . $status) ? constant('PAY_REQUEST_STATUS_' . $status) : $status;
}

// A small badge saying what was last asked of an invoice and where it stands.
function payment_request_badge($request, $methods) {
  $status = payment::request_display_status($request);
  $class  = ['requested' => 'text-bg-info', 'paid' => 'text-bg-success', 'expired' => 'text-bg-warning'][$status] ?? 'text-bg-secondary';
  $text   = payment_request_label($request, $methods) . ': ' . payment_request_status_text($request)
          . ($status === 'requested' ? ' ' . gen_locale_date(substr($request['created_at'], 0, 10)) : '');
  return '<span class="badge ' . $class . ' fw-normal">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}

// For an invoice whose newest request can still be paid, the sentence that
// tells whoever is about to record a payment for it; otherwise ''.
function payment_request_open_note($request, $methods) {
  if (!$request || payment::request_display_status($request) !== 'requested') return '';
  return sprintf(PAY_REQUEST_OPEN_NOTE, payment_request_label($request, $methods),
                 gen_locale_date(substr($request['created_at'], 0, 10)));
}

// The dialog's markup and opener, printed once per page. With $reload, closing
// the dialog after a request was made reloads the page, so that a list showing
// each invoice's requests is current; a page that may hold unsaved edits leaves
// it off.
function payment_request_dialog($reload = false) {
  if (!payment_request_allowed()) return '';
  $loading = '<div class="modal-body text-center text-muted py-4">&hellip;</div>';
  return '<div class="modal fade" id="payRequestModal" tabindex="-1" aria-hidden="true">'
       . '<div class="modal-dialog modal-lg"><div class="modal-content" id="pay-request-content">' . $loading . '</div></div></div>'
       . '<script>'
       . ($reload ? 'document.getElementById("payRequestModal").addEventListener("hidden.bs.modal", function() {'
                  . '  if (document.querySelector("#pay-request-content [data-pay-request-made]")) location.reload();'
                  . '});' : '')
       . 'function payRequestOpen(oID) {'
       . '  var content = document.getElementById("pay-request-content");'
       . '  content.innerHTML = ' . json_encode($loading) . ';'
       . '  bootstrap.Modal.getOrCreateInstance(document.getElementById("payRequestModal")).show();'
       . '  htmx.ajax("GET", "index.php?module=payment&page=ajax&op=request_payment&oID=" + encodeURIComponent(oID),'
       . '            {target: "#pay-request-content", swap: "innerHTML"});'
       . '}'
       . '</script>';
}
