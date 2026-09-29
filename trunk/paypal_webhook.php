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
if (!(include __DIR__ . '/modules/payment/methods/paypal/standalone.php')) {
    http_response_code($pp_standalone_status);
    error_log('paypal_webhook: ' . $pp_standalone_error);
    exit;
}

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

// ─── J18 banking subclass (mirrors zc_banking in zencart_sync.php) ────────────
// Bypasses the JOURNAL_ID constant check in banking::__construct() and provides
// a simplified post_ordr() that records a pre-captured payment without calling
// the payment-processor machinery.
class wh_banking extends banking {
    function __construct() {
        $this->journal_id          = 18;
        $this->save_payment        = false;
        $this->currencies_code     = DEFAULT_CURRENCY;
        $this->currencies_value    = '1.0';
        $this->gl_acct_id          = defined('AR_SALES_RECEIPTS_ACCOUNT') ? AR_SALES_RECEIPTS_ACCOUNT : AR_DEFAULT_GL_ACCT;
        $this->gl_disc_acct_id     = defined('AR_DISCOUNT_SALES_ACCOUNT') ? AR_DISCOUNT_SALES_ACCOUNT : AR_DEFAULT_GL_ACCT;
        $this->purchase_invoice_id = '';
        $this->bill_primary_name   = '';
        $this->bill_contact        = '';
        $this->bill_address1       = '';
        $this->bill_address2       = '';
        $this->bill_city_town      = '';
        $this->bill_state_province = '';
        $this->bill_postal_code    = '';
        $this->bill_country_code   = defined('COMPANY_COUNTRY') ? COMPANY_COUNTRY : 'GBR';
        $this->bill_email          = '';
        $this->search              = '';
    }

    // Simplified post_ordr(): records a payment already captured by PayPal.
    // Stores the capture_id in the 'ttl' description so the J20 refund path can find it.
    function post_ordr($action) {
        global $db, $currencies, $messageStack;
        $this->journal_main_array = $this->build_journal_main_array();
        $this->journal_rows = [];

        $total = 0.0;
        foreach ($this->item_rows as $row) {
            $amount = round((float)$row['total'] + (float)$row['dscnt'], 8);
            $this->journal_rows[] = [
                'so_po_item_ref_id' => $row['id'],
                'gl_type'           => $row['gl_type'],
                'description'       => $row['desc'],
                'credit_amount'     => $amount,
                'gl_account'        => $row['acct'],
                'serialize_number'  => $row['inv'] ?? '',
                'post_date'         => $this->post_date,
            ];
            $total += $amount;
        }
        // Debit: bank/cash account. Store capture_id in description for refund lookup.
        $this->journal_rows[] = [
            'gl_type'      => 'ttl',
            'debit_amount' => round($total, 8),
            'description'  => 'PayPal-Payment:' . ($this->capture_id ?? ''),
            'gl_account'   => $this->gl_acct_id,
            'post_date'    => $this->post_date,
        ];

        // Runs inside the caller's transaction, which also marks the PayPal order
        // done, so the receipt and that mark are committed or rolled back together.
        if (!$this->validate_purchase_invoice_id()) return false;
        if (!$this->Post('insert'))                 return false;
        // Store capture_id in purch_order_id for display and clickable link.
        if (!empty($this->capture_id)) {
            $db->Execute("UPDATE " . TABLE_JOURNAL_MAIN . " SET purch_order_id = '" . db_input($this->capture_id) . "' WHERE id = " . (int)$this->id);
        }
        if (!$this->increment_purchase_invoice_id()) return false;
        return true;
    }
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
if ($request['status'] === 'paid') {
    exit; // a repeated delivery
}

$request_id    = (int)$request['id'];
$fb_invoice_id = (int)$request['ref_id'];

// ─── Load invoice details ─────────────────────────────────────────────────────
$inv = $db->Execute("SELECT id, journal_id, purchase_invoice_id, bill_acct_id, bill_address_id,"
    . " bill_primary_name, bill_email, gl_acct_id, post_date, total_amount, currencies_code, currencies_value"
    . " FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . $fb_invoice_id . " LIMIT 1");

if ($inv->EOF) {
    error_log('paypal_webhook: fb_invoice_id ' . $fb_invoice_id . ' not found in journal_main');
    exit;
}

// ─── Amount, in the invoice's currency and at its rate ────────────────────────
// PayPal is asked for the invoice's own currency, and journal amounts are stored
// in the home currency. The receipt settles at the invoice's stored rate, as a
// payment entered on the Bills page does, so a payment of the whole invoice
// closes it exactly and needs no exchange gain or loss.
$inv_currency = $inv->fields['currencies_code'] ?: DEFAULT_CURRENCY;
$inv_rate     = (float)$inv->fields['currencies_value'] > 0 ? (float)$inv->fields['currencies_value'] : 1.0;
$inv_home     = (float)$inv->fields['total_amount'];
if ($capture_amount > 0 && strtoupper($capture_currency) !== strtoupper($inv_currency)) {
    // Not something a retry can change, so PayPal is not asked to send it again.
    error_log('paypal_webhook: capture ' . $capture_id . ' is in ' . $capture_currency . ' but invoice '
        . $fb_invoice_id . ' is in ' . $inv_currency . '; no receipt posted');
    exit;
}
if ($capture_amount <= 0) {
    $home_amount = $inv_home;
} elseif ($capture_amount == round($inv_home * $inv_rate, 2)) {
    $home_amount = $inv_home;
} else {
    $home_amount = round($capture_amount / $inv_rate, 8);
}

// ─── Auto-create J18 Cash Receipt ────────────────────────────────────────────
// Define constants required by the journal classes.
if (!defined('JOURNAL_ID')) define('JOURNAL_ID', 18);
if (!defined('GL_TYPE'))    define('GL_TYPE',    'pmt');
if (!defined('DEF_GL_ACCT')) define('DEF_GL_ACCT', AR_DEFAULT_GL_ACCT);
if (!defined('POPUP_FORM_TYPE')) define('POPUP_FORM_TYPE', 'bnk:rcpt');

$pay = new wh_banking();
$pay->bill_acct_id      = $inv->fields['bill_acct_id'];
$pay->bill_address_id   = $inv->fields['bill_address_id'];
$pay->bill_primary_name = $inv->fields['bill_primary_name'];
$pay->bill_email        = $inv->fields['bill_email'];
$pay->post_date         = date('Y-m-d');
// The ledger posts each row to the period its date falls in.
$pay->period            = gen_calculate_period($pay->post_date, true);
if (!$pay->period) {
    // An administrator can add the missing periods, so PayPal is asked to retry.
    http_response_code(500);
    error_log('paypal_webhook: ' . $pay->post_date . ' is outside the accounting periods; no receipt for invoice ' . $fb_invoice_id);
    exit;
}
$pay->currencies_code   = $inv_currency;
$pay->currencies_value  = $inv_rate;
$pay->shipper_code      = 'paypal';
$pay->description       = 'PayPal payment for invoice ' . $inv->fields['purchase_invoice_id'];
$pay->capture_id        = $capture_id;
$pay->admin_id          = 1;

// ─── Post the receipt and mark the request paid, as one transaction ──────────
// The request row is locked first, so a delivery PayPal repeats while this one
// is still posting waits here and then finds it paid. If the post fails,
// nothing is kept: the request stays requested and PayPal, told the delivery
// failed, sends it again. The response says the delivery failed until the commit, because a
// failed statement ends the request where it stands.
http_response_code(500);
$db->transStart();
$lock = $db->Execute("SELECT status FROM " . TABLE_PAYMENT_REQUESTS . " WHERE id = " . $request_id . " FOR UPDATE");
if ($lock->EOF || $lock->fields['status'] === 'paid') {
    $db->transRollback();
    http_response_code(200);
    exit;
}

// A receipt never takes more than the invoice still owes. Another receipt may
// already hold some or all of the same money - one recorded by hand, say - so
// payment::invoice_balance() measures what is left. What PayPal took beyond
// that is left unposted and reported, because a payment recorded twice would
// hide in the ledger where one missing from it shows at bank reconciliation.
$half_unit = 0.5 / pow(10, (int)$currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
$outstanding = payment::invoice_balance($fb_invoice_id);
$post_amount = min($home_amount, max(0.0, $outstanding));
if ($post_amount < $half_unit) $post_amount = 0.0;
$unposted    = ($home_amount - $post_amount >= $half_unit) ? round($home_amount - $post_amount, 8) : 0.0;

if ($post_amount > 0) {
    $pay->total_amount = $post_amount;
    $pay->item_rows[]  = [
        'id'      => $fb_invoice_id,
        'gl_type' => 'pmt',
        'amt'     => $post_amount,
        'desc'    => 'Invoice ' . $inv->fields['purchase_invoice_id'],
        'dscnt'   => 0.0,
        'total'   => $post_amount,
        'inv'     => $inv->fields['purchase_invoice_id'],
        'prcnt'   => 0,
        'early'   => date('Y-m-d'),
        'due'     => date('Y-m-d'),
        'pay'     => true,
        'acct'    => AR_DEFAULT_GL_ACCT,
    ];
    if (!$pay->post_ordr('insert')) {
        $db->transRollback();
        $errs = '';
        foreach (($_SESSION['messageToStack'] ?? []) as $m) {
            $errs .= strip_tags($m['text'] ?? '') . ' ';
        }
        $messageStack->reset();
        http_response_code(500);
        error_log('paypal_webhook: J18 post failed for invoice ' . $fb_invoice_id . ': ' . trim($errs));
        exit;
    }
}
$db->Execute("UPDATE " . TABLE_PAYMENT_REQUESTS . " SET status = 'paid', capture_id = '" . db_input($capture_id) . "',"
    . " receipt_id = " . ($post_amount > 0 ? (int)$pay->id : "NULL") . ", completed_at = NOW()"
    . " WHERE id = " . $request_id);
$db->transCommit();
http_response_code(200);

// Amounts for people are in the invoice's currency.
$in_inv_currency = function ($home) use ($inv_rate) { return number_format(round($home * $inv_rate, 2), 2); };
if ($post_amount > 0) {
    error_log('paypal_webhook: created J18 receipt ' . $pay->purchase_invoice_id . ' for invoice ' . $fb_invoice_id . ' capture ' . $capture_id);
}
if ($unposted > 0) {
    error_log('paypal_webhook: capture ' . $capture_id . ' for invoice ' . $fb_invoice_id . ' exceeded its balance; '
        . $in_inv_currency($unposted) . ' ' . $inv_currency . ' of it was not recorded');
}

// ─── Notify staff ─────────────────────────────────────────────────────────────
$notify_email = defined('MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL') ? MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL : '';
if ($notify_email) {
    $from_name    = defined('STORE_NAME') ? STORE_NAME : 'FreeBooks';
    $from_address = defined('EMAIL_FROM') ? EMAIL_FROM : $notify_email;
    $base_url     = (defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : ''))
                  . (defined('DIR_WS_ADMIN') ? DIR_WS_ADMIN : '/');
    $j18_url      = $base_url . 'index.php?module=phreebooks&page=bills&jID=18';
    if ($unposted > 0) {
        $subject = sprintf(MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_SUBJECT, $inv->fields['purchase_invoice_id']);
        $body    = sprintf(
            MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_BODY,
            $inv->fields['bill_primary_name'],
            $in_inv_currency($home_amount), $inv_currency,
            $capture_id,
            $in_inv_currency($post_amount), $inv_currency,
            $in_inv_currency($unposted), $inv_currency,
            $j18_url
        );
    } else {
        $subject = sprintf(MODULE_PAYMENT_PAYPAL_NOTIFY_SUBJECT, $inv->fields['purchase_invoice_id']);
        $body    = sprintf(
            MODULE_PAYMENT_PAYPAL_NOTIFY_BODY,
            $inv->fields['bill_primary_name'],
            $in_inv_currency($home_amount),
            $inv_currency,
            $capture_id,
            $j18_url
        );
    }
    validate_send_mail('', $notify_email, $subject, $body, $from_name, $from_address);
}

exit;
?>
