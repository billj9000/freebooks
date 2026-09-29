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
//  Arquivo Tradução Idioma  Phreedom
//  Generated: 2013-10-22 05:28:45
//  Module/Method: payment
//  ISO Language: pt_br
//  Version: 3.6
// Path: /modules/payment/language/pt_br/language.php

define('MODULE_PAYMENT_CC_NO_DUPS','O cartão de crédito não foi processado porque já tinha sido processado. Para recarregar o cartão de crédito, o cartão de crédito deve ser válido e não conter caracter *.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_CVV','* Os 3 ou 4 dígitos do número de CVV estão geralmente no verso do cartão.');
define('MODULE_PAYMENT_CC_TEXT_DECLINED_MESSAGE','A transação de cartão de crédito não processou corretamente. Se nenhuma razão foi especificada, o cartão foi recusado pelo banco.');
define('MODULE_PAYMENT_CC_TEXT_CVV','CVV Número');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_OWNER','* O nome do propprietário do cartão de crédito deve ter pelo menos %s caracteres.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_NUMBER','* O número do cartão de crédito deve ter pelo menos %s caracteres.');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_EXPIRES','Cartão Crédito Data Validade:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_NUMBER','Cartão Crédito Número:');
define('MODULE_PAYMENT_CC_TEXT_CATALOG_TITLE','Cartão Crédito');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_OWNER','Cartão Crédito Proprietário:');
define('TEXT_MORE_INFO','Mais Informação');
define('MODULE_PAYMENT_CC_TEXT_ERROR','Erro Cartão de Crédito!');

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
