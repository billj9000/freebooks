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
//  Phreedom Language Translation File
//  Generated: 2011-11-27 05:02:32
//  Module/Method: payment
//  ISO Language: nl_nl
//  Version: 3.3
// Path: /modules/payment/language/nl_nl/language.php

define('TEXT_MORE_INFO','Meer Info');
define('MODULE_PAYMENT_CC_TEXT_CATALOG_TITLE','Credit Card');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_OWNER','Credit Card Eigenaar:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_NUMBER','Credit Card Nummer:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_EXPIRES','Credit Card Expiratie Datum:');
define('MODULE_PAYMENT_CC_TEXT_CVV','CVV Nummer');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_OWNER','* De eigenaars naam moet minstens %s tekens lang zijn.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_NUMBER','* Het credit card nummer moet minstens %s tekens lang zijn.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_CVV','* Het 3 of 4 cijferige CVV nummer aan de achterkant van de kaart moet ingevoerd worden.');
define('MODULE_PAYMENT_CC_TEXT_DECLINED_MESSAGE','De credit card transactie is niet correct verwerkt. Als er geen reden wordt gegeven dan is de kaart geweigerd door de bank.');
define('MODULE_PAYMENT_CC_NO_DUPS','De credit card is niet verwerkt omdat hij al eerder verwerkt was. Om de credit card opnieuw te belasten moet het credit card nummer gecomplementeerd worden het mag dan geen * meer bevatten.');
define('MODULE_PAYMENT_CC_TEXT_ERROR','Credit Card Error!');
define('OPEN_POS_DRAWER_DESC','Toe staan dat de kassala open gaat bij het selecteren van deze betaal methode');
define('POS_GL_ACCT_DESC','Kassa betaalwijze rekening');
define('SHOW_IN_POS_DESC','Deze Betaalwijze in PhreePOS laten zien');

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
define('PAY_REQUEST_OPEN_NOTE',      'Payment requested by %s on %s. The receipt is recorded automatically when it is paid.');
define('PAY_REQUEST_STATUS_requested', 'Requested');
define('PAY_REQUEST_STATUS_paid',      'Paid');
define('PAY_REQUEST_STATUS_expired',   'Expired');
define('PAY_REQUEST_STATUS_cancelled', 'Cancelled');
define('PAY_REQUEST_STATUS_failed',    'Failed');
?>
