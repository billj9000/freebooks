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
//  Path: /square_webhook.php
//
// Square webhook endpoint - no session auth; verified by Square's signature.
// Handles payment.updated: a completed payment of a Square payment link or a
// Square invoice that FreeBooks requested records the J18 Cash Receipt. Both
// kinds of request are recorded under their Square order's id, which the
// payment carries.

// ─── Read raw body before any output ─────────────────────────────────────────
$raw_body = file_get_contents('php://input');

// ─── FreeBooks ────────────────────────────────────────────────────────────────
if (!(include __DIR__ . '/modules/payment/standalone.php')) {
    http_response_code($pay_standalone_status);
    error_log('square_webhook: ' . $pay_standalone_error);
    exit;
}
load_specific_method('payment', 'square_terminal');
$sq = new square_terminal();

// ─── Signature ────────────────────────────────────────────────────────────────
// Square signs the notification URL followed by the body with the
// subscription's signature key, so the URL must be the one registered with
// Square exactly - which is why it is a setting and not worked out from this
// request, whose address a proxy may have rewritten. Without both there is
// nothing to check against, and nothing is accepted. The same check works in
// Square's Sandbox, so there is no unverified mode.
$key = defined('MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_KEY') ? (string)MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_KEY : '';
$url = defined('MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_URL') ? (string)MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_URL : '';
if ($key === '' || $url === '') {
    http_response_code(404);
    error_log('square_webhook: the signature key or notification URL is not set');
    exit;
}
$signature = (string)($_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '');
$expected  = base64_encode(hash_hmac('sha256', $url . $raw_body, $key, true));
if ($signature === '' || !hash_equals($expected, $signature)) {
    http_response_code(403);
    error_log('square_webhook: signature check failed');
    exit;
}

$event = json_decode($raw_body, true);
if (!is_array($event) || !isset($event['type'])) {
    http_response_code(400);
    error_log('square_webhook: invalid JSON');
    exit;
}
http_response_code(200);
if ($event['type'] !== 'payment.updated' && $event['type'] !== 'payment.created') exit;

// ─── The payment, as Square has it now ───────────────────────────────────────
// Square may deliver events out of order, and a signed message is only as
// good as the key, so the payment is read back from Square rather than taken
// from the message: only a payment Square itself reports COMPLETED is recorded.
$payment_id = (string)($event['data']['object']['payment']['id'] ?? $event['data']['id'] ?? '');
if ($payment_id === '') exit;
$sq->ensure_requests_table();
$read = $sq->square_request('payments/' . rawurlencode($payment_id), null, 'GET');
if ($read === false && $sq->last_status === 404) {
    // Square has no such payment - its dashboard's test event names one that
    // does not exist. Nothing a retry could change, so it is not asked for.
    error_log('square_webhook: Square has no payment ' . $payment_id . '; ignored');
    exit;
}
if ($read === false) {
    http_response_code(500); // Square sends it again
    error_log('square_webhook: could not read payment ' . $payment_id . ': ' . $sq->get_last_error());
    exit;
}
$payment = $read['payment'] ?? [];
if (($payment['status'] ?? '') !== 'COMPLETED' || empty($payment['order_id'])) exit;

// ─── The request this pays ───────────────────────────────────────────────────
// A payment FreeBooks did not ask for - a sale on the terminal, or one taken by
// another application on the same Square account - has no request and is left
// alone.
$request = $sq->find_request($payment['order_id']);
if (!$request) exit;

// Square counts money in the currency's smallest unit.
$currency = (string)($payment['amount_money']['currency'] ?? '');
$dp       = (int)($currencies->currencies[$currency]['decimal_places'] ?? 2);
$amount   = (int)($payment['amount_money']['amount'] ?? 0) / pow(10, $dp);

payment_record_request_payment($request, [
    'txn_id'       => $payment_id,
    'reference'    => (string)($payment['receipt_number'] ?? $payment_id),
    // The Customer Refund page takes the payment id from here and refunds it
    // through this method, as it does a sale on the terminal.
    'ttl'          => 'Square-Payment:' . $payment_id,
    'amount'       => $amount,
    'currency'     => $currency,
    'shipper_code' => 'square_terminal',
    'description'  => 'Square payment for invoice %s',
    'log'          => 'square_webhook',
    'notify_email' => defined('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_EMAIL') ? MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_EMAIL : '',
    'notify'       => [
        'subject'          => MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_SUBJECT,
        'body'             => MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_BODY,
        'unposted_subject' => MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_UNPOSTED_SUBJECT,
        'unposted_body'    => MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_UNPOSTED_BODY,
    ],
]);
exit;
