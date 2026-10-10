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
// +-----------------------------------------------------------------+
// Phreedom Language Translation File
// Generated: 2011-11-27 05:02:37
// Module/Method: shipping-fedex_v7
// ISO Language: nl_nl
// Version: 3.2
// +-----------------------------------------------------------------+
// Path: /modules/shipping/methods/fedex_v7/language/nl_nl/language.php

define('MODULE_SHIPPING_FEDEX_V7_TEXT_TITLE','Federal Express');
define('MODULE_SHIPPING_FEDEX_V7_TITLE_SHORT','FedEx');
define('MODULE_SHIPPING_FEDEX_V7_TEXT_DESCRIPTION','Federal Express');
define('MODULE_SHIPPING_FEDEX_V7_TEXT_INTRODUCTION', '<h3>Stap 1. Ontwikkel en Test een Applicatie met Web Services</h3>
Deze stap is uitgevoerd door PhreeSoft. De overige stappen moeten worden gevolgd om het aanmaken van labels mogelijk te maken.
<h3>Stap 2. Registreer voor Overgang naar Productie</h3>
Start het certificeringsproces door een FedEx Production Meter Number aan te vragen bij het FedEx Developer Resource Center. <a href="http://www.fedex.com/developer">http://www.fedex.com/developer</a>
<h3>Stap 3. Verkrijg Productiegegevens</h3>
Verkrijg uw productiegegevens (production meter number, production authentication key en production security key) online tijdens het registratieproces. Uw wachtwoord is via een aparte e-mail verzonden.
Opmerking: Voor elk van uw locaties is een production meter number vereist. Het is toegestaan om
dezelfde authentication key voor meerdere locaties te gebruiken. Extra meter numbers kunnen worden verkregen via de Subscribe (Advanced) Web Service.
<br />Belangrijke Opmerking: Vanwege de gevoeligheid van deze informatie wordt de production authentication key niet vermeld in de bevestigingsmail (Stap 4). Bewaar deze informatie voor uw administratie.
<h3>Stap 4. Ontvang Bevestigingsmail</h3>
U ontvangt per e-mail een bevestiging van uw registratie.
<h3>Stap 5. Neem Contact op met het Regionale Web Integration Solutions (WIS) Support Team</h3>
Neem contact op met het Web Integration Solutions Team voor uw regio met uw applicatie-informatie en productiegegevens. Vraag om uw applicatie in te schakelen voor advanced Web Services met verzendlabels. (US &amp; Canada - 1.877.339.2774)
<h3>Stap 6. Regionaal WIS Team Geeft Instructies voor het Indienen van Labels</h3>
Het supportteam van uw regio stuurt een e-mail met instructies voor het certificeren van labels die door uw applicatie zijn aangemaakt.
<h3>Stap 7. Maak en Dien Testlabels in</h3>
Maak testlabels aan met de applicatie en dien deze in bij de FedEx label evaluation teams ter goedkeuring. De voorbeeldgegevens staan in /modules/shipping/methods/fedex_v7/sample_data.php. <a href="index.php?module=shipping&amp;page=fedex_v7_qualify" target="_blank">Download Voorbeeldlabels</a>
<br />Opmerking: De FedEx label evaluation teams hebben vijf werkdagen nodig voor de beoordeling van labels.
<h3>Stap 8. Beoordeling van Labels</h3>
De FedEx label evaluation teams beoordelen de ingediende testlabels en keuren deze goed of af. Het supportteamlid in uw regio neemt contact met u op over de goedkeuring of afkeuring van de ingediende labels. Als de labels zijn goedgekeurd, ga dan door naar de volgende stap. Als de labels zijn afgekeurd, corrigeer de labels volgens de instructies en dien ze opnieuw in ter goedkeuring.
<h3>Stap 9. Regionaal WIS Team Schakelt de Applicatie in</h3>
Zodra de testlabels door de label evaluation teams zijn goedgekeurd voor productie, autoriseert het supportteamlid voor uw regio uw profiel om de betreffende verzendlabeltransactie te verzenden en wordt u op de hoogte gesteld van de succesvolle certificering.
<h3>Stap 10. Vervang URL en Gegevens</h3>
Vervang de test-URL en testgegevens door de productie-URL en productiegegevens.');
define('MODULE_SHIPPING_FEDEX_V7_TITLE_DESC','De titel welke gebruikt moet worden in weergaven. ');
define('MODULE_SHIPPING_FEDEX_V7_ACCOUNT_NUMBER_DESC','Voer het FedEx-klantnummer te gebruiken voor tarief schattingen');
define('MODULE_SHIPPING_FEDEX_V7_LTL_ACCOUNT_NUMBER_DESC','Voer de FedEx LTL rekeningnummer te gebruiken voor tarief schattingen. Laat dit veld leeg als er geen LTL zal worden gebruikt.');
define('MODULE_SHIPPING_FEDEX_V7_NAT_ACCOUNT_NUMBER_DESC','Voer de FedEx Nationale LTL klantnummer in te gebruiken voor tarief schattingen. Laat dit veld leeg als er geen LTL Freight zal worden gebruikt.');
define('MODULE_SHIPPING_FEDEX_V7_AUTH_KEY_DESC','Voer de FedEx-ontwikkelaar sleutel in welke u geleverd is door FedEx.');
define('MODULE_SHIPPING_FEDEX_V7_AUTH_PWD_DESC','Voer de FedEx-ontwikkelaar wachtwoord in afgegeven door FedEx.');
define('MODULE_SHIPPING_FEDEX_V7_METER_NUMBER_DESC','Voer de meter nummer in welke aan u is geleverde door FedEx.');
define('MODULE_SHIPPING_FEDEX_V7_TEST_MODE_DESC','Test/Productie mode voor het testen van verzend labels');
define('MODULE_SHIPPING_FEDEX_V7_PRINTER_TYPE_DESC','Type printer te gebruiken voor het afdrukken van etiketten. PDF voor gewoon papier, Thermal voor FedEx 2844 Thermal Label Printer (Zie Help-bestand voor het selecteren van thermische printer)');
define('MODULE_SHIPPING_FEDEX_V7_PRINTER_NAME_DESC','Stel dan naam van de printer welke gebruikt moet worden voor het printen van labels zoals gedefinieerd in de printer instellingen op de computer.');
define('MODULE_SHIPPING_FEDEX_V7_TYPES_DESC','Selecteer de FedEx diensten die standaard aangeboden worden.');
define('MODULE_SHIPPING_FEDEX_V7_SORT_ORDER_DESC','Volgorde van tonen. Bepaalt in welke volgorde deze optie in lijsten voor moet komen.');
define('MODULE_SHIPPING_FEDEX_V7_GND','Grond');
define('MODULE_SHIPPING_FEDEX_V7_GDR','Thuis Bezorging');
define('MODULE_SHIPPING_FEDEX_V7_1DM','Eerste Klas Overnacht');
define('MODULE_SHIPPING_FEDEX_V7_1DA','Priority Overnacht');
define('MODULE_SHIPPING_FEDEX_V7_1DP','Standaard Overnacht');
define('MODULE_SHIPPING_FEDEX_V7_2DA', 'Express 2 Dagen A.M.');
define('MODULE_SHIPPING_FEDEX_V7_2DP','Express 2 Dagen');
define('MODULE_SHIPPING_FEDEX_V7_3DA', 'Smart Post');
define('MODULE_SHIPPING_FEDEX_V7_3DS','Express Goedkoop');
define('MODULE_SHIPPING_FEDEX_V7_XDM','Int. Eerste Klas');
define('MODULE_SHIPPING_FEDEX_V7_XPR','Int. Priority');
define('MODULE_SHIPPING_FEDEX_V7_XPD','Int. Economie');
define('MODULE_SHIPPING_FEDEX_V7_1DF','1 dagen Vracht');
define('MODULE_SHIPPING_FEDEX_V7_2DF','2 dagen Vracht');
define('MODULE_SHIPPING_FEDEX_V7_3DF','3 dagen Vracht');
define('MODULE_SHIPPING_FEDEX_V7_GDF','Grond LTL Vracht');
define('MODULE_SHIPPING_FEDEX_V7_ECF','Gnd Economy Vracht');
define('SHIPPING_FEDEX_V7_VIEW_REPORTS','Bekijk rapporten voor');
define('SHIPPING_FEDEX_V7_CLOSE_REPORTS','Eind Rapport');
define('SHIPPING_FEDEX_V7_MULTIWGHT_REPORTS','Multigewicht Rapport');
define('SHIPPING_FEDEX_V7_HAZMAT_REPORTS','Hazmat Rapport');
define('SHIPPING_FEDEX_V7_SHIPMENTS_ON','FedEx Verzendingen op');
define('SHIPPING_FEDEX_CURL_ERROR','Er is een fout in de communicatie met de FedEx-server:');
define('SHIPPING_FEDEX_V7_RATE_ERROR','FedEx tarief respons fout:');
define('SHIPPING_FEDEX_V7_RATE_CITY_MATCH','Stad komt niet overeen met de postcode.');
define('SHIPPING_FEDEX_V7_RATE_TRANSIT','Dag (en) onderweg, arriveert');
define('SHIPPING_FEDEX_V7_TNT_ERROR','FedEx Tijd onderweg Error # ');
define('SHIPPING_FEDEX_V7_DEL_ERROR','FedEx Verwijder Label Error: ');
define('SHIPPING_FEDEX_V7_DEL_SUCCESS','Succesvol verwijderd van de FedEx-label. Traceer #');
define('SHIPPING_FEDEX_V7_TRACK_ERROR','FedEx Pakket Traceer Error:');
define('SHIPPING_FEDEX_V7_TRACK_SUCCESS','Succesvol getraceerd Pakket Referentie #');
define('SHIPPING_FEDEX_V7_TRACK_STATUS','Het pakket referentie:%s is niet geleverd, de status is: (Code %s) %s.');
define('SHIPPING_FEDEX_V7_TRACK_FAIL','De volgende pakket referentienummer werd afgeleverd na de verwachte datum / tijd:');
define('SHIPPING_FEDEX_V7_CLOSE_SUCCESS','Succesvol de FedEx zendingen voor vandaag afgesloten.');
define('SRV_TRACK_FEDEX_V7','Traceer FedEx verzendingen van vandaag');
define('ONE_DAY','Een dag onderweg');
define('TWO_DAYS','Twee dagen onderweg');
define('THREE_DAYS','Drie dagen onderweg');
define('FOUR_DAYS','Vier dagen onderweg');
define('FIVE_DAYS','Vijf dagen onderweg');
define('SIX_DAYS','Zes dagen onderweg');
define('SEVEN_DAYS','Zeven dagen onderweg');
define('EIGHT_DAYS','Acht dagen onderweg');
define('NINE_DAYS','Negen dagen onderweg');
define('TEN_DAYS','Tien dagen onderweg');
define('ELEVEN_DAYS','Elf dagen onderweg');
define('TWELVE_DAYS','Twaalf dagen onderweg');
define('THIRTEEN_DAYS','Dertien dagen onderweg');
define('FOURTEEN_DAYS','Veertien dagen onderweg');
define('FIFTEEN_DAYS','Vijftien dagen onderweg');
define('SIXTEEN_DAYS','Zestien dagen onderweg');
define('SEVENTEEN_DAYS','Zeventien dagen onderweg');
define('EIGHTEEN_DAYS','Achttien dagen onderweg');
define('NINETEEN_DAYS','Negentien dagen onderweg');
define('TWENTY_DAYS','Twintig dagen onderweg');
define('UNKNOWN','Onbekend aantal dage onderweg');
define('TEXT_PRINT_PDF_DOCUMENTS','PDF-documenten afdrukken (nadat het afdrukken van labels is voltooid)');
define('TEXT_PRINT_PAPER','Papieren documenten afdrukken');
define('SHIPPING_FEDEX_ERROR_POSTAL_CODE','Postcode is vereist om de FedEx module te gebruiken');
define('SHIPPING_FEDEX_RECON_TITLE','FedEx Verzoenings-Rapport gegeneerd: ');
define('SHIPPING_FEDEX_RECON_INTRO','Factuur Nummer: %s gedateerd %s');
define('SHIPPING_FEDEX_RECON_NO_RECORDS','Verzenddatum :%s Referentie %s, traceer # %s - Er zijn geen records gevonden, verzending van %s tot %s');
define('SHIPPING_FEDEX_RECON_TOO_MANY','Verzenddatum :%s Referentie %s, traceer # %s - Er zijn te veel records gevonden, verzending van %s tot %s');
define('SHIPPING_FEDEX_RECON_COST_OVER','Verzenddatum :%s Referentie %s, traceer # %s - Gefactureerde kosten: (%s) geoffreerde kosten: (%s)');
define('SHIPPING_FEDEX_RECON_COST_OVER_INV', 'Verzenddatum: %s Referentie %s, tracking # %s - De gefactureerde kosten: (%s) zijn hoger dan de gefactureerde kosten op de factuur: (%s)');
define('SHIPPING_FEDEX_RECON_SUMMARY','Totaal aantal records verzoend: %s');

?>
