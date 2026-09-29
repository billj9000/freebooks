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
//  Path: /modules/payment/methods/paypal/language/en_us/language.php

// Admin configuration labels
define('MODULE_PAYMENT_PAYPAL_TEXT_TITLE',              'PayPal');
define('MODULE_PAYMENT_PAYPAL_TEXT_DESCRIPTION',        'PayPal REST API (payment links, invoices, refunds)');
define('MODULE_PAYMENT_PAYPAL_STATUS_DESC',             'Enable this payment method?');
define('MODULE_PAYMENT_PAYPAL_CLIENT_ID_DESC',          'PayPal REST API Client ID');
define('MODULE_PAYMENT_PAYPAL_CLIENT_SECRET_DESC',      'PayPal REST API Client Secret');
define('MODULE_PAYMENT_PAYPAL_TESTMODE_DESC',           'Environment (Sandbox for testing, Production for live)');
define('MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL_DESC',       'Email address to notify when a webhook auto-creates a receipt (leave blank to disable)');
define('MODULE_PAYMENT_PAYPAL_WEBHOOK_ID_DESC',         'Webhook ID from the PayPal developer dashboard (required for automatic receipt creation). Register the notification URL as paypal_webhook.php?company=NAME, where NAME is this company; it may be left out only when the installation has a single company');
define('MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET_DESC',     'Shared secret required on the webhook URL as whsecret=... Append it to the notification URL you register with PayPal, after the company: paypal_webhook.php?company=NAME&whsecret=... When set it is required on every webhook call (Sandbox and Live) before processing — set this to close the Sandbox-mode signature-verification bypass.');

// Requests for payment, as the Request payment dialog lists them
define('MODULE_PAYMENT_PAYPAL_REQUEST_LINK',            'PayPal payment link');
define('MODULE_PAYMENT_PAYPAL_REQUEST_INVOICE',         'PayPal invoice');
define('MODULE_PAYMENT_PAYPAL_PART_PAYMENT',            'Part payment for %s');
define('MODULE_PAYMENT_PAYPAL_ERROR_NO_INVOICE',        'The invoice was not found.');
define('MODULE_PAYMENT_PAYPAL_ERROR_BAD_LINK',          'This request has no PayPal link to send.');

// Bills UI
define('MODULE_PAYMENT_PAYPAL_TEXT_TRANSACTION_ID',     'PayPal Transaction ID');
define('MODULE_PAYMENT_PAYPAL_TEXT_INVOICE_SENT',       'PayPal invoice sent to customer.');
define('MODULE_PAYMENT_PAYPAL_TEXT_PENDING',            'Pending PayPal payment');

// Errors
define('MODULE_PAYMENT_PAYPAL_ERROR_NO_CREDENTIALS',    'PayPal Client ID and Secret are required.');
define('MODULE_PAYMENT_PAYPAL_ERROR_TOKEN',             'Could not obtain PayPal access token. Check your Client ID and Secret.');
define('MODULE_PAYMENT_PAYPAL_ERROR_NO_TXN',        'Enter the PayPal Transaction ID of the payment. To ask the customer to pay, use Request payment on the invoice.');
define('MODULE_PAYMENT_PAYPAL_ERROR_VERIFY',            'Could not verify PayPal capture. Check the Transaction ID.');
define('MODULE_PAYMENT_PAYPAL_ERROR_REFUND',            'PayPal refund failed: ');

// Payment link email to customer
define('MODULE_PAYMENT_PAYPAL_EMAIL_SUBJECT',           'Payment link from %s');
define('MODULE_PAYMENT_PAYPAL_EMAIL_BTN',               'Pay Now');
define('MODULE_PAYMENT_PAYPAL_EMAIL_INTRO',             'Please use the link below to pay securely by card or digital wallet — no PayPal account required.');
define('MODULE_PAYMENT_PAYPAL_EMAIL_EXPIRES',           'Please pay within 3 hours — this link will expire after that. If it has expired, please contact us for a new one.');
define('MODULE_PAYMENT_PAYPAL_EMAIL_ALT_LINK',          'Or copy this link into your browser:');
define('MODULE_PAYMENT_PAYPAL_EMAIL_CONTACT',           'If you have any questions, please contact us.');

// Webhook notification email
define('MODULE_PAYMENT_PAYPAL_NOTIFY_SUBJECT',          'Invoice #%s paid via PayPal');
define('MODULE_PAYMENT_PAYPAL_NOTIFY_BODY',
    "A PayPal payment has been received and a receipt has been automatically recorded.\n\n"
  . "Customer:     %s\n"
  . "Amount:       %s %s\n"
  . "Capture ID:   %s\n"
  . "Receipt:      %s\n"
);
define('MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_SUBJECT', 'Invoice #%s: PayPal payment larger than the balance due');
define('MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_BODY',
    "A PayPal payment has been received for an invoice that was already paid, in whole or in part, by another receipt.\n"
  . "Only the balance that was still due has been recorded. Check the other receipt: if it was saved for this same\n"
  . "PayPal payment, enter the Capture ID below as its PayPal Transaction ID; if it was a separate payment, the\n"
  . "customer has paid twice and the amount not recorded is due back to them.\n\n"
  . "Customer:     %s\n"
  . "Paid:         %s %s\n"
  . "Capture ID:   %s\n"
  . "Recorded:     %s %s\n"
  . "Not recorded: %s %s\n"
  . "Receipts:     %s\n"
);
