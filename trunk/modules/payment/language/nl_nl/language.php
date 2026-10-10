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
define('MODULE_PAYMENT_CC_TEXT_CATALOG_TITLE','Creditcard');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_OWNER','Credit Card Eigenaar:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_NUMBER','Credit Card Nummer:');
define('MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_EXPIRES','Credit Card Expiratie Datum:');
define('MODULE_PAYMENT_CC_TEXT_CVV','CVV Nummer');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_OWNER','* De eigenaars naam moet minstens %s tekens lang zijn.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_NUMBER','* Het credit card nummer moet minstens %s tekens lang zijn.');
define('MODULE_PAYMENT_CC_TEXT_JS_CC_CVV','* Het 3 of 4 cijferige CVV nummer aan de achterkant van de kaart moet ingevoerd worden.');
define('MODULE_PAYMENT_CC_TEXT_DECLINED_MESSAGE','De credit card transactie is niet correct verwerkt. Als er geen reden wordt gegeven dan is de kaart geweigerd door de bank.');
define('MODULE_PAYMENT_CC_NO_DUPS','De credit card is niet verwerkt omdat hij al eerder verwerkt was. Om de credit card opnieuw te belasten moet het credit card nummer gecomplementeerd worden het mag dan geen * meer bevatten.');
define('MODULE_PAYMENT_CC_TEXT_ERROR','Creditcardfout!');
define('OPEN_POS_DRAWER_DESC','Toe staan dat de kassala open gaat bij het selecteren van deze betaal methode');
define('POS_GL_ACCT_DESC','Kassa betaalwijze rekening');
define('SHOW_IN_POS_DESC','Deze Betaalwijze in PhreePOS laten zien');

// ─── The Request payment dialog ───────────────────────────────────────────────
define('PAY_REQUEST_CUSTOMER',       'Customer');
define('PAY_REQUEST_BALANCE',        'Openstaand saldo');
define('PAY_REQUEST_AMOUNT',         'Aan te vragen bedrag');
define('PAY_REQUEST_EMAIL',          'E-mailadres van de klant');
define('PAY_REQUEST_HOW',            'Aanvraagmethode');
define('PAY_REQUEST_CREATE',         'Aanvraag maken');
define('PAY_REQUEST_NO_METHODS',     'Geen enkele geïnstalleerde betaalmethode kan om betaling vragen. PayPal kan dat, zodra het is geïnstalleerd onder Company > Configuration > Payment Module.');
define('PAY_REQUEST_NO_FOREIGN',     'Geen enkele geïnstalleerde betaalmethode die om betaling kan vragen, ondersteunt %s.');
define('PAY_REQUEST_NOTHING_DUE',    'Op deze factuur staat niets meer open.');
define('PAY_REQUEST_EARLIER',        'Aanvragen voor deze factuur');
define('PAY_REQUEST_COPY',           'Copy');
define('PAY_REQUEST_EMAIL_LINK',     'E-mailen naar klant');
define('PAY_REQUEST_EMAILED',        'Gemaild naar %s.');
define('PAY_REQUEST_EMAIL_FAILED',   'De e-mail kon niet worden verzonden.');
define('PAY_REQUEST_LINK_READY',     'Betaallink aangemaakt. Stuur deze naar de klant:');
define('PAY_REQUEST_SENT',           'Aangemaakt en door de betaaldienst gemaild naar %s.');
define('PAY_REQUEST_AUTOMATIC',      'De ontvangst wordt automatisch vastgelegd wanneer de klant betaalt.');
define('PAY_REQUEST_ERROR_AMOUNT',   'Voer een bedrag in dat groter is dan nul en niet hoger dan het openstaande saldo.');
define('PAY_REQUEST_ERROR_EMAIL',    'Voer een geldig e-mailadres in.');
define('PAY_REQUEST_ERROR_METHOD',   'Kies hoe om betaling moet worden gevraagd.');
define('PAY_REQUEST_ERROR_INVOICE',  'Alleen een geboekte klantfactuur kan op deze manier worden betaald.');
define('PAY_REQUEST_FAILED',         'De aanvraag kon niet worden gedaan: %s');
define('PAY_REQUEST_OPEN_NOTE',      'Betaling aangevraagd door %s op %s. De ontvangst wordt automatisch vastgelegd wanneer er is betaald.');
define('PAY_REQUEST_STATUS_requested', 'Requested');
define('PAY_REQUEST_STATUS_paid',      'Paid');
define('PAY_REQUEST_STATUS_expired',   'Expired');
define('PAY_REQUEST_STATUS_cancelled', 'Cancelled');
define('PAY_REQUEST_STATUS_failed',    'Failed');
?>
