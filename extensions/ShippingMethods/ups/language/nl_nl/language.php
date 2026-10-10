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
//  Path: /modules/shipping/methods/ups/language/nl_nl/language.php
//
define('MODULE_SHIPPING_UPS_TEXT_TITLE',        'United Parcel Service');
define('MODULE_SHIPPING_UPS_TITLE_SHORT',       'UPS');
define('MODULE_SHIPPING_UPS_TEXT_DESCRIPTION',  'United Parcel Service - Deze methode ondersteunt momenteel alleen labels voor binnenlandse zendingen in de VS. LTL-vrachtoffertes en -labels worden niet ondersteund. <b>(UPS ondersteunt geen PHP, stuur ze een e-mail uit protest!)</b>');
define('MODULE_SHIPPING_UPS_TEXT_INTRODUCTION', 'Stap 1: Maak een account aan op www.ups.com.<br />Stap 2: Vraag een toegangssleutel aan.<br />Stap 3: Vraag productietoegang aan<br />(Let op: er zijn meer stappen; help deze procedure te voltooien door op het phreesoft-forum te posten of de projectbeheerder te e-mailen.)');

// key descriptions
define('MODULE_SHIPPING_UPS_TITLE_DESC','Titel voor weergave in de verzendkostencalculator');
define('MODULE_SHIPPING_UPS_SHIPPER_NUMBER_DESC','Voer het UPS-verzendernummer in dat voor tariefberekeningen wordt gebruikt');
define('MODULE_SHIPPING_UPS_USER_ID_DESC','Voer het bij UPS geregistreerde UPS-account-ID in voor toegang tot de tariefcalculator');
define('MODULE_SHIPPING_UPS_PASSWORD_DESC','Voer het wachtwoord in voor toegang tot uw UPS-account');
define('MODULE_SHIPPING_UPS_ACCESS_KEY_DESC','Voer de XML Access Key in die u van UPS hebt ontvangen.');
define('MODULE_SHIPPING_UPS_TEST_MODE_DESC','Testmodus voor het testen van verzendlabels');
define('MODULE_SHIPPING_UPS_PRINTER_TYPE_DESC','Type printer voor het afdrukken van labels. GIF voor gewoon papier, Thermisch voor de UPS 2442 thermische labelprinter (zie het Help-bestand voordat u de thermische printer kiest)');
define('MODULE_SHIPPING_UPS_PRINTER_NAME_DESC','Stelt de naam in van de printer voor het afdrukken van labels, zoals gedefinieerd in de printervoorkeuren van het lokale werkstation.');
define('MODULE_SHIPPING_UPS_LABEL_SIZE_DESC', 'Labelformaat voor thermisch afdrukken, geldige waarden zijn 6 of 8 inch');
define('MODULE_SHIPPING_UPS_TYPES_DESC','Selecteer de UPS-diensten die standaard worden aangeboden.');
define('MODULE_SHIPPING_UPS_SORT_ORDER_DESC','Volgorde van tonen. De laagste wordt als eerste getoond.');

define('MODULE_SHIPPING_UPS_GND', 'Ground');
define('MODULE_SHIPPING_UPS_GDF', 'Ground Freight');
define('MODULE_SHIPPING_UPS_1DM', 'Next Day Air Early AM');
define('MODULE_SHIPPING_UPS_1DA', 'Next Day Air');
define('MODULE_SHIPPING_UPS_1DP', 'Next Day Air Saver');
define('MODULE_SHIPPING_UPS_2DM', '2nd Day Air Early AM');
define('MODULE_SHIPPING_UPS_2DP', '2nd Day Air');
define('MODULE_SHIPPING_UPS_3DS', '3 Day Select');
define('MODULE_SHIPPING_UPS_XDM', 'Worldwide Express Plus');
define('MODULE_SHIPPING_UPS_XPR', 'Worldwide Express');
define('MODULE_SHIPPING_UPS_XPD', 'Worldwide Expedited');
define('MODULE_SHIPPING_UPS_STD', 'Standard (Canada)');

define('SHIPPING_UPS_VIEW_REPORTS','Rapporten bekijken voor ');
define('SHIPPING_UPS_CLOSE_REPORTS','Afsluitrapport');
define('SHIPPING_UPS_MULTIWGHT_REPORTS','Rapport meerdere gewichten');
define('SHIPPING_UPS_HAZMAT_REPORTS','Rapport gevaarlijke stoffen');
define('SHIPPING_UPS_SHIPMENTS_ON','UPS-zendingen op ');
define('TEXT_PRINTABLE_IMAGE','Afdrukbare afbeelding');

define('SHIPPING_UPS_RATE_ERROR','Fout in UPS-tariefantwoord: ');
define('SHIPPING_UPS_RATE_CITY_MATCH','Plaats komt niet overeen met postcode.');
define('SHIPPING_UPS_RATE_TRANSIT',' dag(en) onderweg, komt aan op ');
define('SHIPPING_UPS_TNT_ERROR',' UPS Time in Transit fout nr. ');
// Ship Manager Defines
define('SRV_SHIP_UPS','Een pakket verzenden');
define('SHIPPING_UPS_CURL_ERROR','cURL-fout: ');
define('SHIPPING_UPS_PACKAGE_ERROR','Gestopt door problemen bij het opsplitsen van de zending in pakketten. Het gewicht van de zending was: ');
define('SHIPPING_UPS_ERROR_WEIGHT_150','Het gewicht van een enkele zending mag niet meer dan 150 lbs zijn om de UPS-module te gebruiken.');
define('SHIPPING_UPS_ERROR_POSTAL_CODE','Postcode is verplicht om de UPS-module te gebruiken');

?>