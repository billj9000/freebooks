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
//  Path: /modules/shipping/methods/endicia/language/nl_nl/language.php
//
define('MODULE_SHIPPING_ENDICIA_TEXT_TITLE', 'US Postal Service');
define('MODULE_SHIPPING_ENDICIA_TITLE_SHORT', 'USPS');
define('MODULE_SHIPPING_ENDICIA_TEXT_DESCRIPTION', 'US Postal Service (via Endicia)');
define('MODULE_SHIPPING_ENDICIA_TEXT_INTRODUCTION', '<h3>Stap 1. Registreer een account</h3>
<p>Open het registratieformulier van Endicia en volg de instructies om een account aan te maken. Meestal is de basisdienst voldoende (ongeveer $15.95 per maand). <a href="http://www.endicia.com/labelserver/login.cfm?partid=lpst" target="_blank"><b>Klik HIER om u aan te melden</b></a></p>
<h3>Stap 2. Wacht op bevestiging</h3><p>Controleer uw e-mail op een bevestiging van Endicia met uw accountnummer. Voer dit in het onderstaande veld in en sla de wijzigingen op. Voer ook de tijdelijke wachtzin in die u bij de registratie hebt gebruikt.</p>
<h3>Stap 3. Wijzig de wachtzin om het account te activeren</h3><p>Ga naar Extra -> Verzendbeheer -> tabblad US Postal Service. Klik op de knop <b>Wachtzin wijzigen</b> om de tijdelijke wachtzin waarmee u zich hebt aangemeld te vervangen door een nieuwe wachtzin. De nieuwe wachtzin wordt automatisch in het onderstaande veld bijgewerkt.</p>
<h3>Stap 4. Koop porto</h3><p>Ga naar Extra -> Verzendbeheer -> tabblad US Postal Service. Selecteer een bedrag en klik op de knop <b>Porto kopen</b> om saldo aan uw portoaccount toe te voegen.</p>
<h3>Stap 5. Stel de opties in</h3><p>Zorg dat de modus op Production staat voor tariefberekening en het afdrukken van labels. Neem contact op met PhreeSoft voor het Dial-A-Zip-wachtwoord, omdat dit een gedeeld account met PhreeSoft is en wij graag willen weten wie deze dienst gebruikt. U zou nu tarieven moeten kunnen opvragen en labels kunnen afdrukken met deze dienst. LET OP: PhreeSoft heeft alle labeltests uitgevoerd op een Zebra ZP 505-printer. Oudere Eltron/Zebra 2442- en 2844-printers ondersteunen de barcodetypen die nodig zijn voor het afdrukken van USPS-porto niet.</p>');
// key descriptions
define('MODULE_SHIPPING_ENDICIA_TITLE_DESC','Titel voor weergave bij verzending met USPS');
define('MODULE_SHIPPING_ENDICIA_ACCOUNT_NUMBER_DESC','Voer het te gebruiken Endicia-accountnummer in');
define('MODULE_SHIPPING_ENDICIA_PASS_PHRASE_DESC','Voer de wachtzin van uw Endicia-account in');
define('MODULE_SHIPPING_ENDICIA_TEST_MODE_DESC','Test-/productiemodus voor het testen van verzendlabels');
define('MODULE_SHIPPING_ENDICIA_PRINTER_TYPE_DESC','Type printer voor het afdrukken van labels. PDF voor gewoon papier, Thermal voor een Eltron/Zebra-labelprinter');
define('MODULE_SHIPPING_ENDICIA_PRINTER_NAME_DESC', 'Stelt de naam in van de printer voor het afdrukken van labels, zoals gedefinieerd in de printervoorkeuren van het lokale werkstation');
define('MODULE_SHIPPING_ENDICIA_TYPES_DESC','Selecteer de USPS-diensten die standaard worden aangeboden');
define('MODULE_SHIPPING_ENDICIA_SORT_ORDER_DESC','Volgorde van tonen. De laagste wordt als eerste getoond');
// Delivery Methods
define('MODULE_SHIPPING_ENDICIA_GND','Parcel Post');
define('MODULE_SHIPPING_ENDICIA_GDR','Parcel Select');
define('MODULE_SHIPPING_ENDICIA_1DM','Priority');
define('MODULE_SHIPPING_ENDICIA_1DA','Express');
define('MODULE_SHIPPING_ENDICIA_1DP','First Class');
define('MODULE_SHIPPING_ENDICIA_2DA','Critical Mail');
define('MODULE_SHIPPING_ENDICIA_2DP','Library Mail');
define('MODULE_SHIPPING_ENDICIA_3DA','Standard Mail');
define('MODULE_SHIPPING_ENDICIA_3DS','Media Mail');
//define('MODULE_SHIPPING_ENDICIA_XDM','Express Mail Int');
//define('MODULE_SHIPPING_ENDICIA_XPR','Priority Mail Int');
//define('MODULE_SHIPPING_ENDICIA_X3D','First Class Mail Int');
// General defines
define('TEXT_PASSPHRASE_NOW','Huidige wachtzin invoeren');
define('TEXT_PASSPHRASE','Nieuwe wachtzin invoeren');
define('TEXT_PASSPHRASE_DUP','Wachtzin opnieuw invoeren');
define('TEXT_PARTNER_ID','Partner-ID');
define('SHIPPING_ENDICIA_LABEL_STATUS','USPS-label succesvol opgehaald, trackingnummer %s. Uw portosaldo is: %s');
define('SHIPPING_ENDICIA_ERROR_TOO_HEAVY','Het pakketgewicht overschrijdt het maximum dat deze vervoerder ondersteunt!');
define('SHIPPING_ENDICIA_PURCHASE_SUCCESS_MSG','Uw aankoop is geslaagd, uw saldo is nu %s (transactiereferentie %s)');
define('SHIPPING_ENDICIA_PASSPHRASE_CHANGE_DESC','De wachtzin moet minimaal 5 en maximaal 64 tekens lang zijn. Voor extra veiligheid moet de wachtzin minimaal 10 tekens lang zijn en uit meer dan één woord bestaan, met ten minste één hoofdletter en één kleine letter, één cijfer en één niet-tekstteken (bijvoorbeeld een leesteken). Een wachtzin die al eerder is gebruikt, wordt geweigerd.');
define('SHIPPING_ENDICIA_PASSPHRASE_OLD_NOT_MATCH','Uw huidige wachtzin komt niet overeen met wat in het systeem is opgeslagen!');
define('SHIPPING_ENDICIA_PASSPHRASE_NEW_NOT_MATCH','Uw nieuwe wachtzin komt niet overeen met de bevestigde wachtzin!');
define('SHIPPING_ENDICIA_PASSPHRASE_SUCCESS_MSG','Uw wachtzin is succesvol gewijzigd!');
define('SHIPPING_ENDICIA_REFUND_MSG','Terugbetaling voor Endicia-trackingnummer %s goedgekeurd: %s - %s');
define('SHIPPING_ENDICIA_ERROR_POSTAL_CODE','Postcode is verplicht om de Endicia-module te gebruiken!');
define('SHIPPING_ENDICIA_TRACK_STATUS','Trackingresultaten van USPS voor zending-ID %s, trackingnummer %s: %s');
define('SHIPPING_ENDICIA_SIGNUP_STATUS','Aanmeldingsbevestiging van de Endicia-servers: %s. U ontvangt binnenkort een e-mail om uw activering te voltooien.');
define('SHIPPING_ENDICIA_ADD_VAL_ERROR','Dial-A-Zip-fout (%s) %s. Het adres moet worden gecorrigeerd voordat een label kan worden aangemaakt.');
// Ship Manager
define('ENDICIA_CHANGE_PASSPHRASE','Wachtzin wijzigen');
define('ENDICIA_BUY_POSTAGE','Porto kopen');
// Buy Postage Amounts
define('TEXT_0010_DOLLARS','$10.00');
define('TEXT_0025_DOLLARS','$25.00');
define('TEXT_0100_DOLLARS','$100.00');
define('TEXT_0250_DOLLARS','$250.00');
define('TEXT_0500_DOLLARS','$500.00');
define('TEXT_1000_DOLLARS','$1000.00');
// Mail Piece Shapes
define('MPS_01','Kaart');
define('MPS_02','Brief');
define('MPS_03','Grote envelop');
define('MPS_04','Pakket');
define('MPS_05','Groot pakket');
define('MPS_06','Onregelmatig pakket');
define('MPS_07','Pakket met overmaat');
define('MPS_08','Flat Rate-envelop');
define('MPS_09','Flat Rate Legal-envelop');
define('MPS_10','Gewatteerde Flat Rate-envelop');
define('MPS_11','Flat Rate-envelop voor cadeaukaarten');
define('MPS_12','Flat Rate-vensterenvelop');
define('MPS_13','Kartonnen Flat Rate-envelop');
define('MPS_14','Kleine Flat Rate-envelop');
define('MPS_15','Kleine Flat Rate-doos');
define('MPS_16','Middelgrote Flat Rate-doos');
define('MPS_17','Grote Flat Rate-doos');
define('MPS_18','DVD Flat Rate-doos');
define('MPS_19','Grote video Flat Rate-doos');
define('MPS_20','Regional Rate-doos A');
define('MPS_21','Regional Rate-doos B');

?>