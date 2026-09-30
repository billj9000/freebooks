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
//  Path: /modules/payment/methods/square_terminal/language/en_us/language.php
//

define('MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_TITLE',       'Square');
define('MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_DESCRIPTION', 'Accept card payments on a Square Terminal, and ask customers to pay by a Square payment link or a Square invoice.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN_DESC', 'Square API access token (from the Square Developer Dashboard).');
define('MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID_DESC',  'Square Location ID where the terminal device is registered.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID_DESC',    'Square Terminal Device ID.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX_DESC',      'Use Square sandbox environment for testing.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_EMAIL_DESC', 'Email address to notify when a Square payment records a receipt (leave blank to disable).');
define('MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_KEY_DESC',  'Signature key of the webhook subscription, from the Square Developer Dashboard. Square payment requests record their receipts only when this and the notification URL are set.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_URL_DESC',  'Notification URL of the webhook subscription, exactly as entered in the Square Developer Dashboard: https://your-freebooks-host/square_webhook.php?company=NAME. Square signs it along with each message, so it must match character for character.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_CURRENCY_WARNING',  'Square takes payments only in its location\'s currency, %s, and this company\'s currency is %s. Square will refuse every payment and request until the two match.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_CURRENCY_UNKNOWN',  'Square did not say which currency its location takes, so it could not be checked against this company\'s: %s');

define('MODULE_PAYMENT_SQUARE_TERMINAL_REQUEST_LINK',      'Square payment link');
define('MODULE_PAYMENT_SQUARE_TERMINAL_REQUEST_INVOICE',   'Square invoice');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PART_PAYMENT',      'Part payment for %s');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_INVOICE',  'The invoice was not found.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_BAD_LINK',    'This request has no Square payment link to email.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_CUSTOMER', 'Square could not find or add the customer: %s');

define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_SUBJECT',     'Payment link from %s');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_BTN',         'Pay Now');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_INTRO',       'Please use the link below to pay securely by card.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_ALT_LINK',    'Or copy this link into your browser:');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_CONTACT',     'If you have any questions, please contact us.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_SUBJECT',    'Invoice #%s paid via Square');
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_BODY',
    "A Square payment has been received and a receipt has been automatically recorded.\n\n"
  . "Customer:     %s\n"
  . "Amount:       %s %s\n"
  . "Payment ID:   %s\n"
  . "Receipt:      %s\n"
);
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_UNPOSTED_SUBJECT', 'Invoice #%s: Square payment larger than the balance due');
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_UNPOSTED_BODY',
    "A Square payment has been received for an invoice that was already paid, in whole or in part, by another receipt.\n"
  . "Only the balance that was still due has been recorded. If the other receipt was a separate payment, the\n"
  . "customer has paid twice and the amount not recorded is due back to them.\n\n"
  . "Customer:     %s\n"
  . "Paid:         %s %s\n"
  . "Payment ID:   %s\n"
  . "Recorded:     %s %s\n"
  . "Not recorded: %s %s\n"
  . "Receipts:     %s\n"
);

define('MODULE_PAYMENT_SQUARE_TERMINAL_BTN_SEND',          'Send to Terminal');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_IDLE',       'Ready — click Send to Terminal to begin.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_SENDING',    'Sending amount to terminal...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_WAITING',    'Waiting for payment on terminal...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_COMPLETED',  'Payment confirmed!');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_CANCELED',   'Payment canceled or failed — click Send to Terminal to retry.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND_DESC',         'When arriving from the invoice Payment button: Off = do nothing (user clicks Send manually); On = send immediately; Countdown = count down 5 seconds then send, with a Cancel option.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE_DESC',  'Default payment type shown on the payment screen. The cashier can change it per transaction.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT_DESC',        'Automatically open the invoice PDF after a payment is confirmed.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_CARD_PRESENT',  'Card Present (tap / swipe / insert)');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_MANUAL',        'Manual Entry (key in card number)');

define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_BTN',            'Pair New Terminal');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_BTN',          'Browse Devices');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_LOADING',      'Loading devices from Square...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_NONE',         'No Terminal API devices found on this Square account.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_CREATING',       'Creating pairing code...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_INSTRUCTIONS',   'On the terminal: <strong>Settings &rarr; Device Management &rarr; Enter pairing code</strong><br>Waiting for terminal to pair...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_SUCCESS',        'Terminal paired — Device ID filled in above. Click Save to confirm.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_TIMEOUT',        'Pairing timed out (code expired). Please try again.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_ERROR_CONFIG',   'Access token and location ID must be configured and saved before pairing.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_COUNTDOWN_SENDING',   'Sending in %ss &mdash; <a href="#" onclick="sqTermCancelCountdown();return false;">Cancel</a>');

define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_PAID',         'Payment already processed — Send to Terminal is disabled.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_REFUND_PENDING',      'Refund will be processed automatically on save.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_REFUND_DONE',         'Refund already processed:');

define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NOT_CONFIRMED', 'Square Terminal: payment must be confirmed on the terminal before saving.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_VERIFY',        'Square Terminal: could not verify the completed payment with Square. Check the access token and try again.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_TOKEN',      'Square Terminal: access token is not configured.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_DEVICE',     'Square Terminal: device ID is not configured.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_LOCATION',   'Square Terminal: location ID is not configured.');

// Fallback for SORT_ORDER_DESC which is only loaded on the payment admin page
if (!defined('SORT_ORDER_DESC')) define('SORT_ORDER_DESC', 'Sort order of display. Lowest is displayed first.');
?>
