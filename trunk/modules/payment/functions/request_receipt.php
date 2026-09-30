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
//  Path: /modules/payment/functions/request_receipt.php
//
// Records the Cash Receipt for a payment a customer made against a payment
// request, for a payment method's webhook. The method's webhook verifies the
// call, finds the request and says what was paid; everything from there - the
// receipt, the balance cap, marking the request paid, telling staff - is the
// same for every method and lives here. Needs FreeBooks started by
// modules/payment/standalone.php.

// A J18 receipt for a payment the payment service has already taken. Bypasses
// the JOURNAL_ID check in banking::__construct() and posts without the payment
// processor machinery, since there is nothing left to charge.
class request_banking extends banking {
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

    // $this->ttl_description goes on the total row, where the Customer Refund
    // page reads the payment's id back to refund it through the same method.
    // $this->reference is shown as the receipt's reference.
    function post_ordr($action) {
        global $db;
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
        $this->journal_rows[] = [
            'gl_type'      => 'ttl',
            'debit_amount' => round($total, 8),
            'description'  => $this->ttl_description ?? '',
            'gl_account'   => $this->gl_acct_id,
            'post_date'    => $this->post_date,
        ];

        // Runs inside the caller's transaction, which also marks the request
        // paid, so the receipt and that mark are committed or rolled back together.
        if (!$this->validate_purchase_invoice_id()) return false;
        if (!$this->Post('insert'))                 return false;
        if (!empty($this->reference)) {
            $db->Execute("UPDATE " . TABLE_JOURNAL_MAIN . " SET purch_order_id = '" . db_input($this->reference) . "' WHERE id = " . (int)$this->id);
        }
        if (!$this->increment_purchase_invoice_id()) return false;
        return true;
    }
}

/**
 * Record what a customer paid against $request (a payment_requests row), and
 * set the HTTP status to answer the payment service with: 200 when it is done
 * or nothing a retry could change, 500 when the service should send it again.
 * Returns that status too. $payment:
 *
 *   txn_id        the payment service's id for the payment, kept on the request
 *   reference     the receipt's reference, as the Banking pages show it
 *   ttl           the receipt's total-row description (see request_banking)
 *   amount        what was paid, in the invoice's currency; 0 when not known,
 *                 which takes it as the whole invoice
 *   currency      the currency it was paid in
 *   shipper_code  the payment method's code, recorded on the receipt
 *   description   the receipt's description, with %s for the invoice number
 *   log           the prefix for lines in the PHP error log
 *   notify_email  who to tell, or '' for nobody
 *   notify        sprintf formats: subject and body for a payment recorded in
 *                 full, and unposted_subject and unposted_body for one that paid
 *                 more than the invoice still owed
 */
function payment_record_request_payment(array $request, array $payment) {
    global $db, $currencies, $messageStack;
    $log = $payment['log'];
    if ($request['status'] === 'paid') { http_response_code(200); return 200; } // a repeated delivery

    $request_id    = (int)$request['id'];
    $fb_invoice_id = (int)$request['ref_id'];
    $txn_id        = (string)$payment['txn_id'];

    $inv = $db->Execute("SELECT id, journal_id, purchase_invoice_id, bill_acct_id, bill_address_id,"
        . " bill_primary_name, bill_email, gl_acct_id, post_date, total_amount, currencies_code, currencies_value"
        . " FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . $fb_invoice_id . " LIMIT 1");
    if ($inv->EOF) {
        error_log($log . ': fb_invoice_id ' . $fb_invoice_id . ' not found in journal_main');
        http_response_code(200); return 200;
    }

    // ─── Amount, in the invoice's currency and at its rate ────────────────────
    // The payment service is asked for the invoice's own currency, and journal
    // amounts are stored in the home currency. The receipt settles at the
    // invoice's stored rate, as a payment entered on the Bills page does, so a
    // payment of the whole invoice closes it exactly and needs no exchange gain
    // or loss.
    $inv_currency = $inv->fields['currencies_code'] ?: DEFAULT_CURRENCY;
    $inv_rate     = (float)$inv->fields['currencies_value'] > 0 ? (float)$inv->fields['currencies_value'] : 1.0;
    $inv_home     = (float)$inv->fields['total_amount'];
    $paid         = (float)$payment['amount'];
    if ($paid > 0 && strtoupper($payment['currency']) !== strtoupper($inv_currency)) {
        // Not something a retry can change, so the service is not asked to send it again.
        error_log($log . ': payment ' . $txn_id . ' is in ' . $payment['currency'] . ' but invoice '
            . $fb_invoice_id . ' is in ' . $inv_currency . '; no receipt posted');
        http_response_code(200); return 200;
    }
    if ($paid <= 0) {
        $home_amount = $inv_home;
    } elseif ($paid == round($inv_home * $inv_rate, 2)) {
        $home_amount = $inv_home;
    } else {
        $home_amount = round($paid / $inv_rate, 8);
    }

    // ─── The receipt ──────────────────────────────────────────────────────────
    if (!defined('JOURNAL_ID'))      define('JOURNAL_ID', 18);
    if (!defined('GL_TYPE'))         define('GL_TYPE',    'pmt');
    if (!defined('DEF_GL_ACCT'))     define('DEF_GL_ACCT', AR_DEFAULT_GL_ACCT);
    if (!defined('POPUP_FORM_TYPE')) define('POPUP_FORM_TYPE', 'bnk:rcpt');

    $pay = new request_banking();
    $pay->bill_acct_id      = $inv->fields['bill_acct_id'];
    $pay->bill_address_id   = $inv->fields['bill_address_id'];
    $pay->bill_primary_name = $inv->fields['bill_primary_name'];
    $pay->bill_email        = $inv->fields['bill_email'];
    $pay->post_date         = date('Y-m-d');
    // The ledger posts each row to the period its date falls in.
    $pay->period            = gen_calculate_period($pay->post_date, true);
    if (!$pay->period) {
        // An administrator can add the missing periods, so the service is asked to retry.
        error_log($log . ': ' . $pay->post_date . ' is outside the accounting periods; no receipt for invoice ' . $fb_invoice_id);
        http_response_code(500); return 500;
    }
    // Numbered as every other receipt is, by its deposit ticket: DP and the
    // date. Left blank, the ledger would take next_deposit_num instead.
    $pay->purchase_invoice_id = 'DP' . str_replace('-', '', $pay->post_date);
    $pay->currencies_code   = $inv_currency;
    $pay->currencies_value  = $inv_rate;
    $pay->shipper_code      = $payment['shipper_code'];
    $pay->description       = sprintf($payment['description'], $inv->fields['purchase_invoice_id']);
    $pay->reference         = $payment['reference'];
    $pay->ttl_description   = $payment['ttl'];
    $pay->admin_id          = 1;

    // ─── Post the receipt and mark the request paid, as one transaction ──────
    // The request row is locked first, so a delivery the service repeats while
    // this one is still posting waits here and then finds it paid. If the post
    // fails, nothing is kept: the request stays requested and the service, told
    // the delivery failed, sends it again. The response says the delivery failed
    // until the commit, because a failed statement ends the request where it stands.
    http_response_code(500);
    $db->transStart();
    $lock = $db->Execute("SELECT status FROM " . TABLE_PAYMENT_REQUESTS . " WHERE id = " . $request_id . " FOR UPDATE");
    if ($lock->EOF || $lock->fields['status'] === 'paid') {
        $db->transRollback();
        http_response_code(200); return 200;
    }

    // A receipt never takes more than the invoice still owes. Another receipt may
    // already hold some or all of the same money - one recorded by hand, say - so
    // payment::invoice_balance() measures what is left. What was paid beyond that
    // is left unposted and reported, because a payment recorded twice would hide
    // in the ledger where one missing from it shows at bank reconciliation.
    $half_unit   = 0.5 / pow(10, (int)$currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
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
            foreach (($_SESSION['messageToStack'] ?? []) as $m) $errs .= strip_tags($m['text'] ?? '') . ' ';
            $messageStack->reset();
            error_log($log . ': J18 post failed for invoice ' . $fb_invoice_id . ': ' . trim($errs));
            http_response_code(500); return 500;
        }
    }
    $db->Execute("UPDATE " . TABLE_PAYMENT_REQUESTS . " SET status = 'paid', capture_id = '" . db_input($txn_id) . "',"
        . " receipt_id = " . ($post_amount > 0 ? (int)$pay->id : "NULL") . ", completed_at = '" . date('Y-m-d H:i:s') . "'"
        . " WHERE id = " . $request_id);
    $db->transCommit();
    http_response_code(200);

    // Amounts for people are in the invoice's currency.
    $in_inv_currency = function ($home) use ($inv_rate) { return number_format(round($home * $inv_rate, 2), 2); };
    if ($post_amount > 0) {
        error_log($log . ': created J18 receipt ' . $pay->purchase_invoice_id . ' for invoice ' . $fb_invoice_id . ' payment ' . $txn_id);
    }
    if ($unposted > 0) {
        error_log($log . ': payment ' . $txn_id . ' for invoice ' . $fb_invoice_id . ' exceeded its balance; '
            . $in_inv_currency($unposted) . ' ' . $inv_currency . ' of it was not recorded');
    }

    // Once the invoice is paid in full, nothing else should still be asking for it.
    if (payment::invoice_balance($fb_invoice_id) < $half_unit) payment_withdraw_open_requests($fb_invoice_id, $request_id, $log);

    // ─── Tell staff ───────────────────────────────────────────────────────────
    $notify_email = (string)($payment['notify_email'] ?? '');
    if ($notify_email) {
        $from_name    = defined('STORE_NAME') ? STORE_NAME : 'FreeBooks';
        $from_address = defined('EMAIL_FROM') ? EMAIL_FROM : $notify_email;
        $base_url     = (defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : ''))
                      . (defined('DIR_WS_ADMIN') ? DIR_WS_ADMIN : '/');
        $j18_url      = $base_url . 'index.php?module=phreebooks&page=bills&jID=18';
        $fmt          = $payment['notify'];
        if ($unposted > 0) {
            $subject = sprintf($fmt['unposted_subject'], $inv->fields['purchase_invoice_id']);
            $body    = sprintf($fmt['unposted_body'],
                $inv->fields['bill_primary_name'],
                $in_inv_currency($home_amount), $inv_currency,
                $txn_id,
                $in_inv_currency($post_amount), $inv_currency,
                $in_inv_currency($unposted), $inv_currency,
                $j18_url);
        } else {
            $subject = sprintf($fmt['subject'], $inv->fields['purchase_invoice_id']);
            $body    = sprintf($fmt['body'],
                $inv->fields['bill_primary_name'],
                $in_inv_currency($home_amount), $inv_currency,
                $txn_id,
                $j18_url);
        }
        validate_send_mail('', $notify_email, $subject, $body, $from_name, $from_address);
    }
    return 200;
}

// Withdraw every request still open for the invoice $invoice_id other than
// $except_id - a link sent before the customer paid another way, say - so that
// the customer cannot pay it a second time. Each is withdrawn by its own
// method; one whose method cannot withdraw it is left open, and a payment made
// against it anyway is capped at the balance and reported.
function payment_withdraw_open_requests($invoice_id, $except_id, $log) {
    global $db;
    $open = $db->Execute("SELECT * FROM " . TABLE_PAYMENT_REQUESTS . " WHERE ref_id = " . (int)$invoice_id
        . " AND status = 'requested' AND id <> " . (int)$except_id);
    $methods = [];
    while (!$open->EOF) {
        $row = $open->fields;
        $open->MoveNext();
        if (payment::request_display_status($row) !== 'requested') continue; // expired: nothing to withdraw
        $code = $row['method'];
        if (!array_key_exists($code, $methods)) {
            $methods[$code] = null;
            if (defined('MODULE_PAYMENT_' . strtoupper($code) . '_STATUS')) {
                load_specific_method('payment', $code);
                if (class_exists($code)) $methods[$code] = new $code();
            }
        }
        if ($methods[$code] && $methods[$code]->cancel_request($row)) {
            $db->Execute("UPDATE " . TABLE_PAYMENT_REQUESTS . " SET status = 'cancelled', completed_at = '" . date('Y-m-d H:i:s') . "'"
                . " WHERE id = " . (int)$row['id']);
            error_log($log . ': withdrew request ' . $row['id'] . ' (' . $code . ' ' . $row['request_type'] . '): invoice ' . $invoice_id . ' is paid');
        }
    }
}
