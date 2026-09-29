<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/payment/language/en_us/language.php
//

// Headings 

// General Defines
define('TEXT_MORE_INFO','More Info');
define('MODULE_PAYMENT_CC_TEXT_CATALOG_TITLE', 'Credit Card');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_OWNER', 'Credit Card Owner:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_NUMBER', 'Credit Card Number:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_EXPIRES', 'Credit Card Expiry Date:');
define('MODULE_PAYMENT_CC_TEXT_CVV', 'CVV Number');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_OWNER', '* The owner\'s name of the credit card must be at least %s characters.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_NUMBER', '* The credit card number must be at least %s characters.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_CVV', '* The 3 or 4 digit CVV number must be entered from the back of the credit card.');
define('MODULE_PAYMENT_CC_TEXT_DECLINED_MESSAGE', 'The credit card transaction did not process correctly. If no reason is given, the card was declined by the bank.');
define('MODULE_PAYMENT_CC_NO_DUPS','The credit card was not processed because it has already been processed. To recharge a credit card, the credit card must be valid and not contain any * characters.');
define('MODULE_PAYMENT_CC_TEXT_ERROR', 'Credit Card Error!');
define('OPEN_POS_DRAWER_DESC','Allow the pos drawer to open when payment is selected.');
define('POS_GL_ACCT_DESC','POS gl account payment type');
define('SHOW_IN_POS_DESC','Show this payment in PhreePOS');

// ─── The Request payment dialog ───────────────────────────────────────────────
define('PAY_REQUEST_CUSTOMER',       'Customer');
define('PAY_REQUEST_BALANCE',        'Balance due');
define('PAY_REQUEST_AMOUNT',         'Amount to request');
define('PAY_REQUEST_EMAIL',          'Customer email');
define('PAY_REQUEST_HOW',            'How to ask');
define('PAY_REQUEST_CREATE',         'Create request');
define('PAY_REQUEST_NO_METHODS',     'No installed payment method can ask for payment. PayPal can, once it is installed under Company > Configuration > Payment Module.');
define('PAY_REQUEST_NO_FOREIGN',     'No installed payment method that can ask for payment handles %s.');
define('PAY_REQUEST_NOTHING_DUE',    'This invoice has nothing left to pay.');
define('PAY_REQUEST_EARLIER',        'Requests for this invoice');
define('PAY_REQUEST_COPY',           'Copy');
define('PAY_REQUEST_EMAIL_LINK',     'Email to customer');
define('PAY_REQUEST_EMAILED',        'Emailed to %s.');
define('PAY_REQUEST_EMAIL_FAILED',   'The email could not be sent.');
define('PAY_REQUEST_LINK_READY',     'Payment link created. Send it to the customer:');
define('PAY_REQUEST_SENT',           'Created, and emailed to %s by the payment service.');
define('PAY_REQUEST_AUTOMATIC',      'The receipt is recorded automatically when the customer pays.');
define('PAY_REQUEST_ERROR_AMOUNT',   'Enter an amount greater than zero and no more than the balance due.');
define('PAY_REQUEST_ERROR_EMAIL',    'Enter a valid email address.');
define('PAY_REQUEST_ERROR_METHOD',   'Choose how to ask for payment.');
define('PAY_REQUEST_ERROR_INVOICE',  'Only a posted customer invoice can be paid this way.');
define('PAY_REQUEST_FAILED',         'The request could not be made: %s');
define('PAY_REQUEST_STATUS_requested', 'Requested');
define('PAY_REQUEST_STATUS_paid',      'Paid');
define('PAY_REQUEST_STATUS_expired',   'Expired');
define('PAY_REQUEST_STATUS_cancelled', 'Cancelled');
define('PAY_REQUEST_STATUS_failed',    'Failed');
?>
