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
//  Path: /modules/payment/methods/paypal/language/nl_nl/language.php

// Admin configuration labels
define('MODULE_PAYMENT_PAYPAL_TEXT_TITLE',              'PayPal');
define('MODULE_PAYMENT_PAYPAL_TEXT_DESCRIPTION',        'PayPal REST API (betaallinks, facturen, terugbetalingen)');
define('MODULE_PAYMENT_PAYPAL_STATUS_DESC',             'Deze betaalmethode inschakelen?');
define('MODULE_PAYMENT_PAYPAL_CLIENT_ID_DESC',          'PayPal REST API Client ID');
define('MODULE_PAYMENT_PAYPAL_CLIENT_SECRET_DESC',      'PayPal REST API Client Secret');
define('MODULE_PAYMENT_PAYPAL_TESTMODE_DESC',           'Omgeving (Sandbox om te testen, Production voor live)');
define('MODULE_PAYMENT_PAYPAL_NOTIFY_EMAIL_DESC',       'E-mailadres dat een melding krijgt wanneer een webhook automatisch een ontvangst aanmaakt (leeg laten om uit te schakelen)');
define('MODULE_PAYMENT_PAYPAL_WEBHOOK_ID_DESC',         'Webhook ID uit het PayPal developer dashboard (vereist voor het automatisch aanmaken van ontvangsten). Registreer de notificatie-URL als paypal_webhook.php?company=NAME, waarbij NAME dit bedrijf is; company mag alleen worden weggelaten als de installatie één bedrijf heeft');
define('MODULE_PAYMENT_PAYPAL_WEBHOOK_SECRET_DESC',     'Gedeeld geheim dat verplicht is in de webhook-URL als whsecret=... Voeg het toe aan de notificatie-URL die u bij PayPal registreert, na het bedrijf: paypal_webhook.php?company=NAME&whsecret=... Indien ingesteld, is het bij elke webhook-aanroep (Sandbox en Live) vereist vóór verwerking — stel dit in om het omzeilen van de handtekeningverificatie in Sandbox-modus te voorkomen.');

// Requests for payment, as the Request payment dialog lists them
define('MODULE_PAYMENT_PAYPAL_REQUEST_LINK',            'PayPal-betaallink');
define('MODULE_PAYMENT_PAYPAL_REQUEST_INVOICE',         'PayPal-factuur');
define('MODULE_PAYMENT_PAYPAL_PART_PAYMENT',            'Deelbetaling voor %s');
define('MODULE_PAYMENT_PAYPAL_ERROR_NO_INVOICE',        'De factuur is niet gevonden.');
define('MODULE_PAYMENT_PAYPAL_ERROR_BAD_LINK',          'Dit verzoek heeft geen PayPal-link om te versturen.');

// Bills UI
define('MODULE_PAYMENT_PAYPAL_TEXT_TRANSACTION_ID',     'PayPal-transactie-ID');
define('MODULE_PAYMENT_PAYPAL_TEXT_INVOICE_SENT',       'PayPal-factuur naar klant verzonden.');
define('MODULE_PAYMENT_PAYPAL_TEXT_PENDING',            'PayPal-betaling in behandeling');

// Errors
define('MODULE_PAYMENT_PAYPAL_ERROR_NO_CREDENTIALS',    'PayPal Client ID en Secret zijn verplicht.');
define('MODULE_PAYMENT_PAYPAL_ERROR_TOKEN',             'Kan geen PayPal-toegangstoken verkrijgen. Controleer uw Client ID en Secret.');
define('MODULE_PAYMENT_PAYPAL_ERROR_NO_TXN',        'Voer de PayPal-transactie-ID van de betaling in. Gebruik Betaling aanvragen op de factuur om de klant te vragen te betalen.');
define('MODULE_PAYMENT_PAYPAL_ERROR_VERIFY',            'Kan de PayPal-capture niet verifiëren. Controleer de transactie-ID.');
define('MODULE_PAYMENT_PAYPAL_ERROR_REFUND',            'PayPal-terugbetaling mislukt: ');

// Payment link email to customer
define('MODULE_PAYMENT_PAYPAL_EMAIL_SUBJECT',           'Betaallink van %s');
define('MODULE_PAYMENT_PAYPAL_EMAIL_BTN',               'Nu betalen');
define('MODULE_PAYMENT_PAYPAL_EMAIL_INTRO',             'Gebruik de onderstaande link om veilig te betalen met een kaart of digitale wallet — een PayPal-account is niet nodig.');
define('MODULE_PAYMENT_PAYPAL_EMAIL_EXPIRES',           'Betaal binnen 3 uur — daarna verloopt deze link. Is de link verlopen, neem dan contact met ons op voor een nieuwe.');
define('MODULE_PAYMENT_PAYPAL_EMAIL_ALT_LINK',          'Of kopieer deze link naar uw browser:');
define('MODULE_PAYMENT_PAYPAL_EMAIL_CONTACT',           'Hebt u vragen, neem dan contact met ons op.');

// Webhook notification email
define('MODULE_PAYMENT_PAYPAL_NOTIFY_SUBJECT',          'Factuur #%s betaald via PayPal');
define('MODULE_PAYMENT_PAYPAL_NOTIFY_BODY',
    "Er is een PayPal-betaling ontvangen en er is automatisch een ontvangst geboekt.\n\n"
  . "Klant:        %s\n"
  . "Bedrag:       %s %s\n"
  . "Capture-ID:   %s\n"
  . "Ontvangst:    %s\n"
);
define('MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_SUBJECT', 'Factuur #%s: PayPal-betaling hoger dan het openstaande saldo');
define('MODULE_PAYMENT_PAYPAL_NOTIFY_UNPOSTED_BODY',
    "Er is een PayPal-betaling ontvangen voor een factuur die al geheel of gedeeltelijk met een andere ontvangst was betaald.\n"
  . "Alleen het nog openstaande saldo is geboekt. Controleer de andere ontvangst: als die voor dezelfde\n"
  . "PayPal-betaling is opgeslagen, voer dan de onderstaande Capture-ID in als de PayPal-transactie-ID ervan; was het een\n"
  . "aparte betaling, dan heeft de klant twee keer betaald en moet het niet-geboekte bedrag worden terugbetaald.\n\n"
  . "Klant:          %s\n"
  . "Betaald:        %s %s\n"
  . "Capture-ID:     %s\n"
  . "Geboekt:        %s %s\n"
  . "Niet geboekt:   %s %s\n"
  . "Ontvangsten:    %s\n"
);
