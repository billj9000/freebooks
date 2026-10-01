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
//  Path: /paypal_webhook.php
//
// PayPal webhook endpoint — no session auth; verified by PayPal signature.
// Handles CHECKOUT.ORDER.APPROVED, by capturing a payment-link order FreeBooks
// created, and PAYMENT.CAPTURE.COMPLETED and INVOICING.INVOICE.PAID, by
// recording the J18 Cash Receipt.

// ─── Read raw body before any output ─────────────────────────────────────────
$raw_body = file_get_contents('php://input');

// ─── FreeBooks ────────────────────────────────────────────────────────────────
if (!(include __DIR__ . '/modules/payment/standalone.php')) {
    http_response_code($pay_standalone_status);
    error_log('paypal_webhook: ' . $pay_standalone_error);
    exit;
}
load_specific_method('payment', 'paypal');
$pp = new paypal();

// ─── Shared-secret gate ───────────────────────────────────────────────────────
// When MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET is set, require it on every call
// before doing any work. This is the primary guard for Sandbox mode, where
// PayPal's Simulator events cannot be signature-verified and the verification
// below is skipped — without a secret, a Sandbox-configured instance would
// accept forged webhooks. Register the notification URL with PayPal as
// .../paypal_webhook.php?whsecret=<secret> (an X-FreeBooks-Webhook-Secret
// header is also accepted for proxies that strip the query string).
if (defined('MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET') && MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET !== '') {
    $provided_secret = (string)($_GET['whsecret'] ?? $_SERVER['HTTP_X_FREEBOOKS_WEBHOOK_SECRET'] ?? '');
    if ($provided_secret === '' || !hash_equals((string)MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET, $provided_secret)) {
        http_response_code(403);
        error_log('paypal_webhook: shared-secret check failed');
        exit;
    }
}

// ─── Check webhook is configured ─────────────────────────────────────────────
if (!defined('MODULE_PAYMENT_PAYPAL_WEBHOOK_ID') || MODULE_PAYMENT_PAYPAL_WEBHOOK_ID === '') {
    http_response_code(404);
    exit;
}

// ─── Parse and verify webhook event ──────────────────────────────────────────
if (!$raw_body) {
    http_response_code(400);
    error_log('paypal_webhook: empty body');
    exit;
}

$event = json_decode($raw_body, true);
if (!$event || !isset($event['event_type'])) {
    http_response_code(400);
    error_log('paypal_webhook: invalid JSON');
    exit;
}

// Verify PayPal signature (skip in Sandbox mode to allow PayPal Simulator).
$sandbox = defined('MODULE_PAYMENT_PAYPAL_TESTMODE') && MODULE_PAYMENT_PAYPAL_TESTMODE === 'Sandbox';
// Sandbox skips the signature, so the shared secret above is then the only
// guard: without one configured, nothing proves the call came from PayPal.
if ($sandbox && (!defined('MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET') || MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET === '')) {
    http_response_code(403);
    error_log('paypal_webhook: Sandbox mode with no webhook secret configured, refused');
    exit;
}
if (!$sandbox) {
    $sig_payload = [
        'auth_algo'       => $_SERVER['HTTP_PAYPAL_AUTH_ALGO']       ?? '',
        'cert_url'        => $_SERVER['HTTP_PAYPAL_CERT_URL']        ?? '',
        'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
        'transmission_sig'=> $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG']?? '',
        'transmission_time'=> $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
        'webhook_id'      => MODULE_PAYMENT_PAYPAL_WEBHOOK_ID,
        'webhook_event'   => $event,
    ];
    $verify = $pp->paypal_request('v1/notifications/verify-webhook-signature', $sig_payload);
    if (!$verify || ($verify['verification_status'] ?? '') !== 'SUCCESS') {
        http_response_code(400);
        error_log('paypal_webhook: signature verification failed: ' . json_encode($verify));
        exit;
    }
}

// Acknowledge all events immediately; process known event types below.
http_response_code(200);

$pp->ensure_requests_table();

// ─── A buyer approved a payment link: take the payment ────────────────────────
// Approval moves no money. The capture this makes is reported by its own
// PAYMENT.CAPTURE.COMPLETED event, which records the receipt below. An order
// FreeBooks did not create is left alone, and a failed capture is answered 500
// so that PayPal sends the approval again.
if ($event['event_type'] === 'CHECKOUT.ORDER.APPROVED') {
    $order_id = (string)($event['resource']['id'] ?? '');
    if ($pp->capture_mapped_order($order_id) === false) {
        http_response_code(500);
        error_log('paypal_webhook: could not capture approved order ' . $order_id . ': ' . $pp->get_last_error());
    }
    exit;
}

// ─── Extract event-specific IDs and amounts ───────────────────────────────────
switch ($event['event_type']) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        $capture_id       = $event['resource']['id'] ?? '';
        $paypal_order_id  = $event['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
        $capture_amount   = (float)($event['resource']['amount']['value'] ?? 0);
        $capture_currency = $event['resource']['amount']['currency_code'] ?? DEFAULT_CURRENCY;
        break;

    case 'INVOICING.INVOICE.PAID':
        $paypal_order_id  = $event['resource']['id'] ?? '';
        $txns             = $event['resource']['payments']['transactions'] ?? [];
        $capture_id       = $txns[0]['transaction_id'] ?? '';
        $capture_amount   = (float)($txns[0]['amount']['value']
            ?? $event['resource']['payments']['paid_amount']['value'] ?? 0);
        $capture_currency = $txns[0]['amount']['currency_code']
            ?? $txns[0]['amount']['currency'] ?? DEFAULT_CURRENCY;
        break;

    default:
        exit; // ignore other event types
}

if (!$capture_id || !$paypal_order_id) {
    error_log('paypal_webhook: missing capture_id or paypal_order_id in ' . $event['event_type']
        . ': ' . json_encode($event['resource']));
    exit;
}

// ─── The request this pays, and its invoice ──────────────────────────────────
// A payment FreeBooks did not ask for - one taken by another application on the
// same PayPal account - has no request here and is left alone.
$request = $pp->find_request($paypal_order_id);
if (!$request) {
    error_log('paypal_webhook: not a payment FreeBooks requested; ignoring ' . $paypal_order_id);
    exit;
}
payment_record_request_payment($request, [
    'txn_id'       => $capture_id,
    'reference'    => $capture_id,
    'ttl'          => 'PayPal-Payment:' . $capture_id,
    'amount'       => $capture_amount,
    'currency'     => $capture_currency,
    'shipper_code' => 'paypal',
    'description'  => 'PayPal payment for invoice %s',
    'log'          => 'paypal_webhook',
    'notify_email' => defined('MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL') ? MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL : '',
    'notify'       => [
        'subject'          => MODULE_PAYMENT_PAYPAL_NOTIFY_SUBJECT,
        'body'             => MODULE_PAYMENT_PAYPAL_NOTIFY_BODY,
        'unposted_subject' => MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_SUBJECT,
        'unposted_body'    => MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_BODY,
    ],
]);
exit;
