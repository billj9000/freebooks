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
//  Path: /modules/payment/ajax/request_payment.php
//
// The Request payment dialog. It asks a customer to pay an invoice through any
// installed payment method that can make a request (payment::$request_types),
// and lists the invoice's earlier requests. Nothing here records money: the
// method's service reports the payment, and the receipt is recorded then.
//
// htmx fragments, swapped into the dialog's #pay-request-content:
//   GET  oID                                  the form
//   POST action=create, oID, amount, email, choice=<method>:<type>
//   POST action=email, request_id, email      email a request's link; answers
//                                             into #pay-request-email-status

// Asking for payment is part of receiving it, so it takes the Customer Receipts
// permission at the level that can enter one.
$security_level = (int)($_SESSION['admin_security'][SECURITY_ID_CUSTOMER_RECEIPTS] ?? 0);
if ($security_level < 2) {
  echo '<div class="modal-body"><div class="alert alert-danger mb-0">' . ERROR_NO_PERMISSION . '</div></div>';
  exit;
}
gen_pull_language('payment');
require_once(DIR_FS_MODULES . 'payment/classes/payment.php');
require_once(DIR_FS_MODULES . 'payment/functions/request_dialog.php');

// ─── Data ─────────────────────────────────────────────────────────────────────

// A posted customer invoice, or null.
function pr_invoice($id) {
  global $db;
  $r = $db->Execute("SELECT id, purchase_invoice_id, bill_primary_name, bill_email, closed, total_amount,"
    . " currencies_code, currencies_value FROM " . TABLE_JOURNAL_MAIN
    . " WHERE id = " . (int)$id . " AND journal_id = 12");
  if ($r->EOF) return null;
  $inv = $r->fields;
  $inv['currencies_code'] = $inv['currencies_code'] ?: DEFAULT_CURRENCY;
  $inv['rate']            = (float)$inv['currencies_value'] > 0 ? (float)$inv['currencies_value'] : 1.0;
  $inv['due']             = max(0.0, round(payment::invoice_balance($id) * $inv['rate'], 2));
  return $inv;
}

function pr_requests($invoice_id) {
  global $db;
  $out = [];
  if (!db_table_exists(TABLE_PAYMENT_REQUESTS)) return $out;
  $r = $db->Execute("SELECT * FROM " . TABLE_PAYMENT_REQUESTS . " WHERE ref_id = " . (int)$invoice_id
    . " ORDER BY created_at DESC, id DESC");
  while (!$r->EOF) { $out[] = $r->fields; $r->MoveNext(); }
  return $out;
}

// ─── Rendering ────────────────────────────────────────────────────────────────

function pr_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pr_money($amount, $code) {
  global $currencies;
  $dp = (int)($currencies->currencies[$code]['decimal_places'] ?? 2);
  return number_format((float)$amount, $dp, '.', ',') . ' ' . pr_h($code);
}

function pr_header($inv) {
  return '<div class="modal-header"><h5 class="modal-title">' . PAY_REQUEST_TITLE
       . ($inv ? ' &mdash; ' . TEXT_INVOICE . ' ' . pr_h($inv['purchase_invoice_id']) : '') . '</h5>'
       . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . TEXT_CLOSE . '"></button></div>';
}

// Help opens the manual's Requesting a Payment topic, as a toolbar's Help does.
function pr_footer() {
  return '<div class="modal-footer justify-content-between">'
       . '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.open(\'index.php?module=phreehelp&amp;page=main&amp;idx=14.02\',\'help\',\'width=800,height=600,resizable=1,scrollbars=1,top=100,left=100\')">'
       . TEXT_HELP . '</button>'
       . '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">' . TEXT_CLOSE . '</button></div>';
}

function pr_summary($inv) {
  return '<table class="table table-borderless table-sm mb-2">'
       . '<tr><th class="w-25">' . PAY_REQUEST_CUSTOMER . '</th><td>' . pr_h($inv['bill_primary_name']) . '</td></tr>'
       . '<tr><th>' . PAY_REQUEST_BALANCE . '</th><td>' . pr_money($inv['due'], $inv['currencies_code']) . '</td></tr>'
       . '</table>';
}

function pr_request_table($requests, $methods_all) {
  if (!$requests) return '';
  $html = '<h6 class="mt-3">' . PAY_REQUEST_EARLIER . '</h6>'
        . '<table class="table table-borderless table-hover table-sm mb-0"><thead class="table-dark"><tr>'
        . '<th>' . TEXT_DATE . '</th><th>' . TEXT_TYPE . '</th><th class="text-end">' . TEXT_AMOUNT . '</th><th>' . TEXT_STATUS . '</th>'
        . '</tr></thead><tbody>';
  foreach ($requests as $r) {
    $html .= '<tr><td>' . pr_h(gen_locale_date(substr($r['created_at'], 0, 10))) . '</td>'
           . '<td>' . pr_h(payment_request_label($r, $methods_all)) . '</td>'
           . '<td class="text-end">' . pr_money($r['amount'], $r['currency_code']) . '</td>'
           . '<td>' . pr_h(payment_request_status_text($r)) . '</td></tr>';
  }
  return $html . '</tbody></table>';
}

function pr_form($inv, $methods, $requests, $values = [], $error = '') {
  $html = pr_header($inv) . '<div class="modal-body">' . pr_summary($inv);
  if ($error) $html .= '<div class="alert alert-danger py-1">' . pr_h($error) . '</div>';
  if ($inv['due'] <= 0) {
    $html .= '<div class="alert alert-info py-1 mb-0">' . PAY_REQUEST_NOTHING_DUE . '</div>';
  } elseif (!$methods) {
    $html .= '<div class="alert alert-warning py-1 mb-0">' . ($inv['currencies_code'] !== DEFAULT_CURRENCY
          ? sprintf(PAY_REQUEST_NO_FOREIGN, pr_h($inv['currencies_code'])) : PAY_REQUEST_NO_METHODS) . '</div>';
  } else {
    $amount = $values['amount'] ?? number_format($inv['due'], 2, '.', '');
    $email  = $values['email']  ?? $inv['bill_email'];
    $choice = $values['choice'] ?? '';
    $html .= '<form hx-post="index.php?module=payment&amp;page=ajax&amp;op=request_payment"'
           . ' hx-target="#pay-request-content" hx-swap="innerHTML" hx-disabled-elt="find button[type=submit]">'
           . '<input type="hidden" name="action" value="create"><input type="hidden" name="oID" value="' . (int)$inv['id'] . '">'
           . '<div class="row g-2 mb-2">'
           . '<div class="col-sm-5"><label class="form-label mb-0" for="pr_amount">' . PAY_REQUEST_AMOUNT . ' (' . pr_h($inv['currencies_code']) . ')</label>'
           . '<input type="text" inputmode="decimal" class="form-control form-control-sm" id="pr_amount" name="amount" value="' . pr_h($amount) . '"></div>'
           . '<div class="col-sm-7"><label class="form-label mb-0" for="pr_email">' . PAY_REQUEST_EMAIL . '</label>'
           . '<input type="email" class="form-control form-control-sm" id="pr_email" name="email" value="' . pr_h($email) . '"></div>'
           . '</div><div class="mb-2"><div class="form-label mb-0">' . PAY_REQUEST_HOW . '</div>';
    $first = true;
    foreach ($methods as $code => $obj) {
      foreach ($obj->request_types as $type => $label) {
        $value   = $code . ':' . $type;
        $checked = ($choice === $value || ($choice === '' && $first)) ? ' checked' : '';
        $id      = 'pr_' . preg_replace('/[^a-z0-9_]/i', '_', $value);
        $html .= '<div class="form-check"><input class="form-check-input" type="radio" name="choice" id="' . $id . '" value="' . pr_h($value) . '"' . $checked . '>'
               . '<label class="form-check-label" for="' . $id . '">' . pr_h($label) . '</label></div>';
        $first = false;
      }
    }
    $html .= '</div><button type="submit" class="btn btn-primary btn-sm">' . PAY_REQUEST_CREATE . '</button></form>';
  }
  return $html . pr_request_table($requests, $methods) . '</div>' . pr_footer();
}

function pr_result($inv, $methods, $requests, $request, $result, $email) {
  $html = pr_header($inv) . '<div class="modal-body">' . pr_summary($inv);
  if (!empty($result['sent'])) {
    $html .= '<div class="alert alert-success py-1">' . sprintf(PAY_REQUEST_SENT, pr_h($email)) . '</div>';
  } elseif (!empty($result['url'])) {
    $html .= '<div class="alert alert-success py-1 mb-2">' . PAY_REQUEST_LINK_READY . '</div>'
           . '<div class="input-group input-group-sm mb-2">'
           . '<input type="text" class="form-control" id="pay-request-url" value="' . pr_h($result['url']) . '" readonly>'
           . '<button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById(\'pay-request-url\').value)">'
           . PAY_REQUEST_COPY . '</button></div>'
           . '<form class="d-flex gap-2 align-items-center mb-2" hx-post="index.php?module=payment&amp;page=ajax&amp;op=request_payment"'
           . ' hx-target="#pay-request-email-status" hx-swap="innerHTML" hx-disabled-elt="find button">'
           . '<input type="hidden" name="action" value="email"><input type="hidden" name="request_id" value="' . (int)$request['id'] . '">'
           . '<input type="email" class="form-control form-control-sm" name="email" value="' . pr_h($email) . '" aria-label="' . PAY_REQUEST_EMAIL . '">'
           . '<button type="submit" class="btn btn-primary btn-sm text-nowrap">' . PAY_REQUEST_EMAIL_LINK . '</button></form>'
           . '<div id="pay-request-email-status"></div>';
  }
  $html .= '<p class="text-muted small mb-0" data-pay-request-made>' . PAY_REQUEST_AUTOMATIC . '</p>';
  return $html . pr_request_table($requests, $methods) . '</div>' . pr_footer();
}

// ─── Actions ──────────────────────────────────────────────────────────────────

$action = $_POST['action'] ?? '';

if ($action === 'email') {
  $request_id = (int)($_POST['request_id'] ?? 0);
  $email      = trim((string)($_POST['email'] ?? ''));
  $row = $db->Execute("SELECT * FROM " . TABLE_PAYMENT_REQUESTS . " WHERE id = " . $request_id);
  if ($row->EOF || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<div class="alert alert-danger py-1 mb-0">' . PAY_REQUEST_ERROR_EMAIL . '</div>';
    exit;
  }
  $method = $row->fields['method'];
  load_specific_method('payment', $method);
  $obj = class_exists($method) ? new $method() : null;
  $ok  = $obj && $obj->email_request($row->fields, $email);
  echo $ok ? '<div class="alert alert-success py-1 mb-0">' . sprintf(PAY_REQUEST_EMAILED, pr_h($email)) . '</div>'
           : '<div class="alert alert-danger py-1 mb-0">' . PAY_REQUEST_EMAIL_FAILED . '</div>';
  exit;
}

$inv = pr_invoice((int)($_REQUEST['oID'] ?? 0));
if (!$inv) {
  echo pr_header(null) . '<div class="modal-body"><div class="alert alert-danger mb-0">' . PAY_REQUEST_ERROR_INVOICE . '</div></div>' . pr_footer();
  exit;
}
$methods = payment_request_methods($inv['currencies_code']);

if ($action !== 'create') {
  echo pr_form($inv, $methods, pr_requests($inv['id']));
  exit;
}

$values = [
  'amount' => trim((string)($_POST['amount'] ?? '')),
  'email'  => trim((string)($_POST['email']  ?? '')),
  'choice' => (string)($_POST['choice'] ?? ''),
];
$amount = is_numeric($values['amount']) ? round((float)$values['amount'], 2) : 0.0;
list($code, $type) = array_pad(explode(':', $values['choice'], 2), 2, '');
$error = '';
if ($amount <= 0 || $amount > $inv['due'] + 0.005)            $error = PAY_REQUEST_ERROR_AMOUNT;
elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $error = PAY_REQUEST_ERROR_EMAIL;
elseif (!isset($methods[$code]->request_types[$type]))       $error = PAY_REQUEST_ERROR_METHOD;
if ($error) {
  echo pr_form($inv, $methods, pr_requests($inv['id']), $values, $error);
  exit;
}

$obj    = $methods[$code];
$result = $obj->create_request($type, $inv['id'], $amount, $inv['currencies_code'], $values['email']);
if (!$result) {
  $reason = method_exists($obj, 'get_last_error') ? $obj->get_last_error() : '';
  echo pr_form($inv, $methods, pr_requests($inv['id']), $values, sprintf(PAY_REQUEST_FAILED, $reason ?: TEXT_ERROR));
  exit;
}
gen_add_audit_log(PAY_REQUEST_TITLE . ' - ' . $obj->request_types[$type], $inv['purchase_invoice_id'], $amount);
echo pr_result($inv, $methods, pr_requests($inv['id']), $obj->find_request($result['external_id']), $result, $values['email']);
exit;
