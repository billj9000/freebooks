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
//  Path: /modules/payment/methods/square_terminal/language/nl_nl/language.php
//

define('MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_TITLE',       'Square');
define('MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_DESCRIPTION', 'Accepteer kaartbetalingen op een Square Terminal en vraag klanten te betalen via een Square-betaallink of een Square-factuur.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN_DESC', 'Square API-toegangstoken (uit het Square Developer Dashboard).');
define('MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID_DESC',  'Square Location ID waar de terminal is geregistreerd.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID_DESC',    'Square Terminal Device ID.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX_DESC',      'Gebruik de Square-sandboxomgeving om te testen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_EMAIL_DESC', 'E-mailadres dat een melding krijgt wanneer een Square-betaling een ontvangst boekt (leeg laten om uit te schakelen).');
define('MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_KEY_DESC',  'Handtekeningsleutel van het webhookabonnement, uit het Square Developer Dashboard. Square-betaalverzoeken boeken hun ontvangsten alleen als deze sleutel en de notificatie-URL zijn ingesteld.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_WEBHOOK_URL_DESC',  'Notificatie-URL van het webhookabonnement, precies zoals ingevoerd in het Square Developer Dashboard: https://your-freebooks-host/square_webhook.php?company=NAME. Square ondertekent deze URL samen met elk bericht, dus hij moet teken voor teken overeenkomen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_CURRENCY_WARNING',  'Square accepteert alleen betalingen in de valuta van zijn locatie, %s, en de valuta van dit bedrijf is %s. Square weigert elke betaling en elk verzoek totdat deze twee overeenkomen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_CURRENCY_UNKNOWN',  'Square heeft niet aangegeven welke valuta zijn locatie accepteert, dus kon deze niet worden vergeleken met die van dit bedrijf: %s');

define('MODULE_PAYMENT_SQUARE_TERMINAL_REQUEST_LINK',      'Square-betaallink');
define('MODULE_PAYMENT_SQUARE_TERMINAL_REQUEST_INVOICE',   'Square-factuur');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PART_PAYMENT',      'Deelbetaling voor %s');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_INVOICE',  'De factuur is niet gevonden.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_BAD_LINK',    'Dit verzoek heeft geen Square-betaallink om te e-mailen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_CUSTOMER', 'Square kon de klant niet vinden of toevoegen: %s');

define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_SUBJECT',     'Betaallink van %s');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_BTN',         'Nu betalen');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_INTRO',       'Gebruik de onderstaande link om veilig met een kaart te betalen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_ALT_LINK',    'Of kopieer deze link naar uw browser:');
define('MODULE_PAYMENT_SQUARE_TERMINAL_EMAIL_CONTACT',     'Hebt u vragen, neem dan contact met ons op.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_SUBJECT',    'Factuur #%s betaald via Square');
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_BODY',
    "Er is een Square-betaling ontvangen en er is automatisch een ontvangst geboekt.\n\n"
  . "Klant:          %s\n"
  . "Bedrag:         %s %s\n"
  . "Betalings-ID:   %s\n"
  . "Ontvangst:      %s\n"
);
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_UNPOSTED_SUBJECT', 'Factuur #%s: Square-betaling hoger dan het openstaande saldo');
define('MODULE_PAYMENT_SQUARE_TERMINAL_NOTIFY_UNPOSTED_BODY',
    "Er is een Square-betaling ontvangen voor een factuur die al geheel of gedeeltelijk met een andere ontvangst was betaald.\n"
  . "Alleen het nog openstaande saldo is geboekt. Was de andere ontvangst een aparte betaling, dan heeft de\n"
  . "klant twee keer betaald en moet het niet-geboekte bedrag worden terugbetaald.\n\n"
  . "Klant:          %s\n"
  . "Betaald:        %s %s\n"
  . "Betalings-ID:   %s\n"
  . "Geboekt:        %s %s\n"
  . "Niet geboekt:   %s %s\n"
  . "Ontvangsten:    %s\n"
);

define('MODULE_PAYMENT_SQUARE_TERMINAL_BTN_SEND',          'Naar terminal sturen');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_IDLE',       'Gereed — klik op Naar terminal sturen om te beginnen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_SENDING',    'Bedrag wordt naar de terminal gestuurd...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_WAITING',    'Wachten op betaling op de terminal...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_COMPLETED',  'Betaling bevestigd!');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_CANCELED',   'Betaling geannuleerd of mislukt — klik op Naar terminal sturen om het opnieuw te proberen.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND_DESC',         'Bij openen via de knop Betaling op de factuur: Off = niets doen (de gebruiker klikt zelf op Naar terminal sturen); On = direct versturen; Countdown = 5 seconden aftellen en dan versturen, met de optie Annuleren.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_DEFAULT_PMT_TYPE_DESC',  'Standaard betalingstype op het betaalscherm. De kassamedewerker kan dit per transactie wijzigen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_PRINT_DESC',        'Open automatisch de factuur-PDF nadat een betaling is bevestigd.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_CARD_PRESENT',  'Kaart aanwezig (tikken / halen / insteken)');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PMT_TYPE_MANUAL',        'Handmatige invoer (kaartnummer intypen)');

define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_BTN',            'Nieuwe terminal koppelen');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_BTN',          'Apparaten zoeken');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_LOADING',      'Apparaten worden geladen van Square...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_NONE',         'Geen Terminal API-apparaten gevonden in dit Square-account.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_CREATING',       'Koppelcode wordt aangemaakt...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_INSTRUCTIONS',   'Op de terminal: <strong>Settings &rarr; Device Management &rarr; Enter pairing code</strong><br>Wachten tot de terminal is gekoppeld...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_SUCCESS',        'Terminal gekoppeld — Device ID is hierboven ingevuld. Klik op Opslaan om te bevestigen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_TIMEOUT',        'Koppelen is verlopen (code verlopen). Probeer het opnieuw.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_ERROR_CONFIG',   'Toegangstoken en Location ID moeten zijn ingesteld en opgeslagen voordat u kunt koppelen.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_COUNTDOWN_SENDING',   'Versturen over %ss &mdash; <a href="#" onclick="sqTermCancelCountdown();return false;">Annuleren</a>');

define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_PAID',         'Betaling al verwerkt — Naar terminal sturen is uitgeschakeld.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_REFUND_PENDING',      'Terugbetaling wordt automatisch verwerkt bij opslaan.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_REFUND_DONE',         'Terugbetaling al verwerkt:');

define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NOT_CONFIRMED', 'Square Terminal: de betaling moet op de terminal worden bevestigd voordat u opslaat.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_VERIFY',        'Square Terminal: kan de voltooide betaling niet bij Square verifiëren. Controleer het toegangstoken en probeer het opnieuw.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_TOKEN',      'Square Terminal: toegangstoken is niet ingesteld.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_DEVICE',     'Square Terminal: Device ID is niet ingesteld.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_LOCATION',   'Square Terminal: Location ID is niet ingesteld.');

// Fallback for SORT_ORDER_DESC which is only loaded on the payment admin page
if (!defined('SORT_ORDER_DESC')) define('SORT_ORDER_DESC', 'Volgorde van tonen. De laagste wordt als eerste getoond.');
?>
