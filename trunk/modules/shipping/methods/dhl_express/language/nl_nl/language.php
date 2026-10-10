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
//  Path: /modules/shipping/methods/dhl_express/language/nl_nl/language.php
//

define('MODULE_SHIPPING_DHL_EXPRESS_TEXT_TITLE',       'DHL Express');
define('MODULE_SHIPPING_DHL_EXPRESS_TEXT_DESCRIPTION', 'DHL Express internationale koerier');
define('MODULE_SHIPPING_DHL_EXPRESS_TITLE_SHORT',      'DHL Express');
// Admin panel field descriptions (one per key() entry)
define('MODULE_SHIPPING_DHL_EXPRESS_TITLE_DESC',          'Titel die in de verzendcalculator wordt getoond.');
define('MODULE_SHIPPING_DHL_EXPRESS_API_KEY_DESC',        'DHL Express Commerce API-sleutel (gebruikersnaam). Verkrijgbaar via het DHL-ontwikkelaarsportaal.');
define('MODULE_SHIPPING_DHL_EXPRESS_API_SECRET_DESC',     'DHL Express Commerce API-geheim (wachtwoord).');
define('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_DESC',        'Uw DHL Express-accountnummer voor exportzendingen (uitgaand).');
define('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_IMPORT_DESC', 'Uw DHL Express-accountnummer voor importzendingen (inkomend). Leeg laten om het exportaccountnummer te gebruiken.');
define('MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE_DESC',      'Test gebruikt de DHL-sandboxgegevens; Production gebruikt de live-gegevens.');
define('MODULE_SHIPPING_DHL_EXPRESS_SORT_ORDER_DESC',     'Volgorde van tonen. De laagste wordt als eerste getoond.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_READY_DESC',    'Ophaalopdrachten: het tijdstip waarop pakketten meestal klaarstaan (HH:MM), voorgesteld bij het boeken van een ophaalopdracht.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_CLOSE_DESC',    'Ophaalopdrachten: het laatste tijdstip waarop de koerier kan ophalen (HH:MM), voorgesteld bij het boeken van een ophaalopdracht.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_LOCATION_DESC', 'Ophaalopdrachten: waar de koerier ophaalt, bijv. Receptie (maximaal 80 tekens).');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_CONTACT_DESC',  'Ophaalopdrachten: de contactnaam die DHL voor een ophaalopdracht krijgt. Leeg gebruikt de contactnaam voor debiteuren op het tabblad Mijn Bedrijf.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_PHONE_DESC',    'Ophaalopdrachten: het telefoonnummer dat DHL belt over een ophaalopdracht. Leeg gebruikt het telefoonnummer van het bedrijf.');
// Error messages
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_RATES', 'DHL Express: tariefopvraag mislukt - ');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL', 'DHL Express: aanmaken van label mislukt - ');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY', 'DHL Express: API-gegevens niet ingesteld.');
define('MODULE_SHIPPING_DHL_EXPRESS_DELETE_CONFIRM', 'DHL kan een label niet annuleren nadat het is afgedrukt. Verwijderen haalt deze zending alleen uit FreeBooks weg, dus geef het pakket niet aan DHL mee. Verwijderen?');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_PICKUP', 'DHL Express: aanvraag ophaalopdracht mislukt - ');
// Label form
define('DHL_CUSTOMS_SECTION',     'Douane- / exportgegevens');
define('DHL_TEXT_DESCRIPTION',    'Omschrijving goederen');
define('DHL_TEXT_DECLARED_VALUE', 'Aangegeven waarde');
define('DHL_TEXT_HS_CODE',        'HS- / goederencode');
define('DHL_TEXT_ORIGIN_COUNTRY', 'Land van oorsprong');
define('DHL_TEXT_EXPORT_REASON',  'Reden van export');
define('DHL_TEXT_DELIVERY_TERMS', 'Leveringsvoorwaarden');
define('DHL_TERMS_DAP',           'Rechten onbetaald (DAP) - de ontvanger betaalt');
define('DHL_TERMS_DDP',           'Rechten betaald (DDP) - gefactureerd aan ons DHL-account');
define('DHL_TEXT_IS_CUSTOMS',     'Douaneaangifte vereist');
// Line items table
define('DHL_LINE_ITEMS_HEADER',  'Inhoud zending');
define('DHL_LINE_ITEMS_CUSTOMS', '(Douaneaangifte)');
define('DHL_TEXT_UNIT_WEIGHT',   'Gewicht per stuk');
define('DHL_TEXT_UNIT_VALUE',    'Waarde per stuk');
define('DHL_TEXT_SAVE_TO_INV',   'Omschrijvingen opslaan in voorraad');
define('DHL_ITEMS_SAVED',        'Artikelomschrijvingen opgeslagen in voorraad.');
define('DHL_TEXT_CUSTOM_PACKAGE', 'Aangepast');
// Collections
define('DHL_PICKUP_TITLE',          'DHL-ophaalopdracht');
define('DHL_PICKUP_BOOK_BTN',       'Ophaalopdracht boeken');
define('DHL_PICKUP_BOOKED_HEADING', 'Geboekte ophaalopdrachten');
define('DHL_PICKUP_NEW_HEADING',    'Een ophaalopdracht boeken');
define('DHL_PICKUP_NONE',           'Geen ophaalopdracht geboekt voor deze dag.');
define('DHL_PICKUP_ADDRESS',        'Ophaaladres');
define('DHL_PICKUP_ADDRESS_NOTE',   'Ophaalopdrachten worden geboekt op het bedrijfsadres, dat u wijzigt op het tabblad Mijn Bedrijf van de bedrijfsinstellingen.');
define('DHL_PICKUP_DATE',           'Ophaaldatum');
define('DHL_PICKUP_READY',          'Gereed vanaf');
define('DHL_PICKUP_CLOSE',          'Uiterlijk ophalen');
define('DHL_PICKUP_LOCATION',       'Ophalen bij');
define('DHL_PICKUP_LOCATION_TYPE',  'Type locatie');
define('DHL_PICKUP_BUSINESS',       'Bedrijf');
define('DHL_PICKUP_RESIDENCE',      'Woning');
define('DHL_PICKUP_CONTACT',        'Contactnaam');
define('DHL_PICKUP_PHONE',          'Telefoon');
define('DHL_PICKUP_INSTRUCTIONS',   'Opmerkingen voor de koerier (maximaal 4 regels)');
define('DHL_PICKUP_SHIPMENTS',      'Op te halen zendingen');
define('DHL_PICKUP_NO_SHIPMENTS',   'Er zijn voor deze dag geen DHL-labels afgedrukt.');
define('DHL_PICKUP_PARCELS',        'Pakketten');
define('DHL_PICKUP_WEIGHT_KG',      'Gewicht (kg)');
define('DHL_PICKUP_SIZE_CM',        'L x B x H (cm)');
define('DHL_PICKUP_ENTER_FIGURES',  'Er zijn bij dit label geen pakketgegevens opgeslagen; voer het aantal pakketten, het gewicht van elk pakket en de afmetingen in.');
define('DHL_PICKUP_IN_BOOKING',     'Al in ophaalopdracht %s');
define('DHL_PICKUP_ELSEWHERE',      'Verzonden vanaf postcode %s, niet vanaf hier');
define('DHL_PICKUP_CONFIRMATION',   'Bevestiging');
define('DHL_PICKUP_STATUS_BOOKED',  'Geboekt');
define('DHL_PICKUP_STATUS_CANCELLED', 'Geannuleerd');
define('DHL_PICKUP_CANCEL_BTN',     'Ophaalopdracht annuleren');
define('DHL_PICKUP_CANCEL_REASON',  'Reden');
define('DHL_PICKUP_REASON_NOT_NEEDED', 'Ophaalopdracht niet meer nodig');
define('DHL_PICKUP_REASON_ERROR',   'Per abuis geboekt');
define('DHL_PICKUP_REASON_REBOOK',  'Opnieuw boeken met wijzigingen');
define('DHL_PICKUP_CANCEL_CONFIRM', 'Deze ophaalopdracht bij DHL annuleren? Als DHL deze heeft samengevoegd met een andere ophaalopdracht vanaf dit adres, wordt die ophaalopdracht ook geannuleerd.');
define('DHL_PICKUP_BOOKED',         'Ophaalopdracht geboekt. DHL-bevestiging: %s');
define('DHL_PICKUP_WARNING',        'DHL: %s');
define('DHL_PICKUP_CANCELLED',      'Ophaalopdracht %s geannuleerd.');
define('DHL_PICKUP_LOG_BOOKED',     'DHL-ophaalopdracht geboekt');
define('DHL_PICKUP_LOG_CANCELLED',  'DHL-ophaalopdracht geannuleerd');
define('DHL_PICKUP_ERR_DATE',       'De ophaaldatum moet vandaag zijn of binnen de komende 10 dagen vallen.');
define('DHL_PICKUP_ERR_TIME',       'Voer de tijden in als HH:MM, met het uiterste ophaaltijdstip na het tijdstip waarop de zending gereedstaat.');
define('DHL_PICKUP_ERR_CONTACT',    'Een contactnaam en telefoonnummer zijn verplicht.');
define('DHL_PICKUP_ERR_TEXT',       'Het ophaalpunt en elke opmerking voor de koerier mogen maximaal 80 tekens lang zijn.');
define('DHL_PICKUP_ERR_NONE',       'Vink ten minste één op te halen zending aan.');
define('DHL_PICKUP_ERR_PARCELS',    'Zending %s: elk pakket heeft een gewicht en een lengte, breedte en hoogte nodig.');
?>
