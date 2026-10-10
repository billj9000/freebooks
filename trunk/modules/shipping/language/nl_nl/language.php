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
// Generated: 2011-11-27 05:02:38
// Module/Method: shipping
// ISO Language: nl_nl
// Version: 3.3
// +-----------------------------------------------------------------+
// Path: /modules/shipping/language/nl_nl/language.php

define('HEADING_TITLE_MODULES_SHIPPING','Verzending Services');
define('SHIPPING_HEADING_SHIP_MGR','Verzending Module Manager');
define('TEXT_SHIPPING_MODULES_AVAILABLE','Beschikbare Verzend Methodes');
if (!defined('TEXT_PRODUCTION')) define('TEXT_PRODUCTION','Productie'); // also defined in payment/admin.php
if (!defined('TEXT_TEST')) define('TEXT_TEST','Test'); // phreedom's language.php also defines this, loaded first by every entry point that uses both
define('TEXT_PDF','PDF');
define('TEXT_GIF','GIF');
define('TEXT_THERMAL','Thermisch');
define('TEXT_PAGKAGE_DEFAULTS','Pakket Standaardwaarden');
define('TEXT_SHIPMENT_DEFAULTS','Verzend Standaardwaarden');
define('TEXT_SHIPMENTS_ON', 'Zendingen Op: ');
define('SHIPPING_TEXT_REMOVE_MESSAGE','Weet u zeker dat u deze module en alle geassocieerden data wil verwijderen?');
define('SHIPPING_BUTTON_CREATE_LOG_ENTRY','Maak een verzending aan');
define('SHIPPING_SET_BY_SYSTEM',' (Ingesteld door het systeem)');
define('SHIPPING_POPUP_WINDOW_TITLE','Verzending tarief calculator');
define('SHIPPING_POPUP_WINDOW_RATE_TITLE','Verzendingscalculator - tarieven');
define('SHIPPING_ESTIMATOR_OPTIONS','Verzendingscalculator - Verzendingsopties');
define('SHIPPING_TEXT_SHIPPER','Transporteur:');
define('SHIPPING_TEXT_SHIPMENT_DATE','Verzendingsdatum');
define('SHIPPING_TEXT_SHIP_FROM_CITY','Verzonden Van Stad: ');
define('SHIPPING_TEXT_SHIP_TO_CITY','Verzonden Naar Stad: ');
define('SHIPPING_RESIDENTIAL_ADDRESS','Woon adres');
define('SHIPPING_TEXT_SHIP_FROM_STATE','Verzonden Van Provincie: ');
define('SHIPPING_TEXT_SHIP_TO_STATE','Verzonden Naar Provincie: ');
define('SHIPPING_TEXT_SHIP_FROM_ZIP','Verzonden Van Postcode: ');
define('SHIPPING_TEXT_SHIP_TO_ZIP','Verzonden Naar Postcode: ');
define('SHIPPING_TEXT_SHIP_FROM_COUNTRY','Verzonden Van Land: ');
define('SHIPPING_TEXT_SHIP_TO_COUNTRY','Verzonden Naar Land: ');
define('SHIPPING_TEXT_SWAP_ADDRESSES', 'Wissel Van / Aan');
define('SHIPPING_TEXT_PACKAGE_INFORMATION','Pakket informatie');
define('SHIPPING_TEXT_PACKAGE_TYPE','Type Pakket ');
define('SHIPPING_TEXT_PICKUP_SERVICE','Ophaal Service ');
define('SHIPPING_TEXT_DIMENSIONS','Afmetingen: ');
define('SHIPPING_ADDITIONAL_HANDLING','Aanvullende Handling Geldt ( Oversize )');
define('SHIPPING_INSURANCE_AMOUNT','Verzekerd Bedrag ');
define('SHIPPING_SPLIT_LARGE_SHIPMENTS','Splits grote pakket voor de kleine pakket vervoerder');
define('SHIPPING_TEXT_PER_BOX',' per doos');
define('SHIPPING_TEXT_DELIVERY_CONFIRM','Leverings bevestiging ');
define('SHIPPING_SPECIAL_OPTIONS','Speciale opties');
define('SHIPPING_SERVICE_TYPE','Soort dienst');
define('SHIPPING_HANDLING_CHARGE','Administratiekosten: Bedrag ');
define('SHIPPING_COD_AMOUNT','Rembource Bedrag');
define('SHIPPING_SATURDAY_PICKUP','Zaterdag Ophalen');
define('SHIPPING_SATURDAY_DELIVERY','Zaterdag Afleveren');
define('SHIPPING_HAZARDOUS_MATERIALS','Gevaarlijk Materiaal');
define('SHIPPING_TEXT_DRY_ICE','Droog ijs');
define('SHIPPING_TEXT_RETURN_SERVICES','Retour Service ');
define('SHIPPING_TEXT_METHODS','Verzend Methode');
define('SHIPPING_TOTAL_WEIGHT','Totaal pakket gewicht');
define('SHIPPING_TOTAL_VALUE','Totaal pakket waarde');
define('SHIPPING_EMAIL_SENDER','E-mail Verzender');
define('SHIPPING_EMAIL_RECIPIENT','E-mail Ontvanger');
define('SHIPPING_EMAIL_SENDER_ADD','E-mail Adres Verzender');
define('SHIPPING_EMAIL_RECIPIENT_ADD','E-mail Adres Ontvanger');
define('SHIPPING_LABEL_TO_NAME',   'Aan: Naam');
define('SHIPPING_LABEL_TO_EMAIL',  'Aan: E-mail');
define('SHIPPING_LABEL_FROM_NAME', 'Van: Naam');
define('SHIPPING_LABEL_FROM_EMAIL','Van: E-mail');
define('SHIPPING_LABEL_CC_EMAIL',   'CC:');
define('SHIPPING_LABEL_BCC_EMAIL',  'BCC:');
define('SHIPPING_LABEL_EMAIL_SENT', 'Verzendlabel succesvol gemaild.');
define('SHIPPING_LABEL_EMAIL_ERROR','Verzenden mislukt. Controleer uw e-mailinstellingen.');
define('SHIPPING_LABEL_EMAIL_SUBJECT', 'Verzendlabel');
define('SHIPPING_LABEL_EMAIL_BODY', 'Bijgaand vindt u uw verzendlabel.');
define('SHIPPING_TEXT_EXCEPTION','Uitzondering');
define('SHIPPING_TEXT_DELIVER','Leverancier');
define('SHIPPING_PRINT_LABEL','Print Label');
define('SHIPPING_BILL_CHARGES_TO','Bereken kosten aan');
if (!defined('SHIPPING_THIRD_PARTY')) define('SHIPPING_THIRD_PARTY','Ontv/Derde Acct #');
define('SHIPPING_THIRD_PARTY_ZIP','Derde Partij Postcode');
define('SHIPPING_LTL_FREIGHT_CLASS','LTL Vracht klasse');
define('SHIPPING_NUM_PIECES','Aantal Prijzen');
define('SHIPPNIG_SUMMARY','Verzend Samenvatting');
define('SHIPPING_SHIPMENT_DETAILS','Verzending details');
define('SHIPPING_PACKAGE_DETAILS','Pakket Details');
define('SHIPPING_VOID_SHIPMENT','Niet geldige Verzending');
define('SHIPPING_TEXT_CARRIER','Koerier');
define('SHIPPING_TEXT_SERVICE','Service');
define('SHIPPING_TEXT_FREIGHT_QUOTE','Vracht Offerte');
define('SHIPPING_TEXT_BOOK_PRICE','Prijs boeken');
define('SHIPPING_TEXT_COST','Kosten');
define('SHIPPING_TEXT_NOTES','Aantekeningen');
define('SHIPPING_TEXT_PRINT_LABEL','Label afdrukken');
define('SHIPPING_TEXT_SAVE_PRINT_LABEL','Zending Aanmaken en Label Afdrukken');
define('SHIPPING_TEXT_CLOSE_DAY','Dagelijks Afsluiten');
define('SHIPPING_TEXT_DELETE_LABEL','Verwijder verzending');
define('SHIPPING_TEXT_SHIPMENT_ID','Verzendings ID');
define('SHIPPING_TEXT_REFERENCE_ID','Referentie ID');
define('SHIPPING_TEXT_TRACKING_NUM','Traceer Nummer');
define('SHIPPING_TRACK','Volgen');
define('SHIPPING_USE_COMPANY_ADDRESS', 'Bedrijfsadres gebruiken');
define('SHIPPING_FIND_CONTACT', 'Klant of leverancier zoeken');
define('SHIPPING_FIND_CONTACT_PROMPT', 'Naam, account, postcode of e-mail');
define('SHIPPING_FIND_CONTACT_SHORT', 'Typ ten minste twee tekens.');
define('SHIPPING_FIND_CONTACT_NONE', 'Geen klant of leverancier gevonden.');
define('SHIPPING_CONTACT_CUSTOMER', 'Klant');
define('SHIPPING_CONTACT_VENDOR', 'Leverancier');
define('SHIPPING_ADDRESS_MAIN', 'Hoofdadres');
define('SHIPPING_ADDRESS_DELIVERY', 'Afleveradres');
define('SHIPPING_FIND_ADDRESS', 'Adres zoeken');
define('SHIPPING_TEXT_EXPECTED_DATE','Verwachte Leveringsdatum');
define('SHIPPING_TEXT_ACTUAL_DATE','Daadwerkelijke Leveringsdatum');
define('SHIPPING_TEXT_DOWNLOAD','Download Thermisch label');
define('SHIPPING_THERMAL_INST', '<br />Het bestand is voorgeformatteerd voor thermische labelprinters. Om het label af te drukken:<br /><br />
		1. Klik op de knop Download om de download te starten.<br />
		2. Klik op \'Opslaan\' in het bevestigingsvenster om het bestand op uw lokale computer op te slaan.<br />
		3. Kopieer het bestand direct naar de printerpoort. (het bestand moet in raw formaat worden gekopieerd)');
define('SHIPPING_TEXT_NO_LABEL','Geen Label Gevonden!');
define('SHIPPING_NO_PACKAGES','Er waren geen pakketten om te verzenden, of de totale hoeveelheid of het gewicht was nul.');
define('SHIPPING_DELETE_ERROR','Fout - Kan niet verzending verwijderen, u heeft niet genoeg informatie heeft verstrekt.');
define('SHIPPING_CANNOT_DELETE','Fout - Kan verzending niet verwijderen waarvan het etiket werd voor gegenereerd voor vandaag.');
define('SHIPPING_LABEL_DELETED','Label Verwijderd');
define('SHIPPING_END_OF_DAY','Einde van de dag Gesloten - %s');
define('SHIPPING_ERROR_WEIGHT_ZERO','Pakket gewicht kan niet nul zijn.');
define('SHIPPING_ERROR_BAD_METHOD','De verzendmethode \'%s\' is niet geïnstalleerd, dus er zijn geen labels te beheren.');
define('SHIPPING_BOX_PACKER_NO_DIMS', 'Doosafmetingen zijn exclusief artikelen zonder ingestelde afmetingen (gewicht inbegrepen): ');
define('SHIPPING_BOX_PACKER_NOT_FOUND', 'Artikelen niet gevonden in voorraad (uitgesloten van schatting): ');
define('SHIPPING_TEXT_WEIGHT_FALLBACK', 'Gebruikt wanneer artikelafmetingen niet beschikbaar zijn');
define('SHIPPING_DELETE_CONFIRM','Weet u zeker dat u deze verzending wil verwijderen?');
define('SHIPPING_CANCEL_CONFIRM','Dit annuleert de verzending bij de vervoerder en verwijdert het record. Weet u het zeker?');
define('SHIPPING_NO_SHIPMENTS','Er zijn geen verzendingen van deze koerier vandaag!');
define('SHIPPING_ERROR_CONFIGURATION','<strong>Verzending Configuratie errors!</strong>');
define('SHIPPING_BAD_QUOTE_DATE','De datum van verzending kan niet voor vandaag zijn voor een offerte. Het te verzenden datum is veranderd naar vandaag voor offerte doeleinden.');
define('SHIPPING_LOG_LABEL_PRINTED','Label Gegenereerd');
define('SHIPPING_1DEAM','1 Dag vroeg a.m.');
define('SHIPPING_1DAM','1 Dag a.m.');
define('SHIPPING_1DPM','1 Dag p.m.');
define('SHIPPING_1DFRT','1 Dag vracht');
define('SHIPPING_2DAM','2 Dagen a.m.');
define('SHIPPING_2DPM','2 Dagen p.m.');
define('SHIPPING_2DFRT','2 Day Vracht');
define('SHIPPING_3DAM', '3 Dagen a.m.');
define('SHIPPING_3DPM','3 Dagen');
define('SHIPPING_3DFRT','3 Day Vracht');
define('SHIPPING_GND','Grond');
define('SHIPPING_GDR','Grond Woongebied');
define('SHIPPING_GNDFRT','Grond LTL Vracht');
define('SHIPPING_ECOFRT', 'Economy Vracht');
define('SHIPPING_I2DEAM','Wereldwijde Vroege Expres');
define('SHIPPING_I2DAM','Wereldwijde Expres');
define('SHIPPING_I3D','Wereldwijde Expeditie');
define('SHIPPING_IGND','Grond (Canada)');
define('SHIPPING_DHL_P', 'DHL Express Worldwide');
define('SHIPPING_DHL_D', 'DHL Express Worldwide (Dutiable)');
define('SHIPPING_DHL_I', 'DHL Express Worldwide (Doc)');
define('SHIPPING_DHL_N', 'DHL Domestic Express');
define('SHIPPING_DHL_G', 'DHL Domestic Economy Select');
define('SHIPPING_DHL_Y', 'DHL Express 9:00');
define('SHIPPING_DHL_E', 'DHL Express 9:00 (Dutiable)');
define('SHIPPING_DHL_K', 'DHL Express 9:00 (Regional)');
define('SHIPPING_DHL_T', 'DHL Express 12:00');
define('SHIPPING_DHL_L', 'DHL Express 10:30');
define('SHIPPING_DHL_M', 'DHL Express 10:30 (Dutiable)');
define('SHIPPING_DHL_W', 'DHL Express 12:00 (Dutiable)');
define('SHIPPING_DHL_Q', 'DHL Medical Express');
define('SHIPPING_DHL_C', 'DHL Medical Express (Domestic)');
define('SHIPPING_DHL_H', 'DHL Economy Select');
define('SHIPPING_DHL_8', 'DHL Express Easy');
define('SHIPPING_CRL24', 'Royal Mail 24');
define('SHIPPING_CRL48', 'Royal Mail 48');
define('SHIPPING_RM24', 'Tracked 24');
define('SHIPPING_RM48', 'Tracked 48');
define('SHIPPING_RMSD9', 'Special Delivery 9am');
define('SHIPPING_RMSD1', 'Special Delivery 1pm');
define('SHIPPING_RM1CL', 'Signed For 1st Class');
define('SHIPPING_RM2CL', 'Signed For 2nd Class');
define('SHIPPING_RMIT', 'International Business Mail Tracked');
define('SHIPPING_RMIS', 'International Business Mail Signed');
define('SHIPPING_RMITS', 'International Business Mail Tracked & Signed');
define('SHIPPING_RMIE', 'International Business Parcel Standard');
define('SHIPPING_RMIPT', 'International Business Parcel Tracked');
define('SHIPPING_PFX9', 'Parcelforce Express 9');
define('SHIPPING_PFX10', 'Parcelforce Express 10');
define('SHIPPING_PFXAM', 'Parcelforce Express AM');
define('SHIPPING_PFX24', 'Parcelforce Express 24');
define('SHIPPING_PFX48', 'Parcelforce Express 48');
define('SHIPPING_PFX4L', 'Parcelforce Express 48 Large');
define('SHIPPING_NDA', 'Parcelforce Express 24');
define('SHIPPING_FE0', 'Parcelforce Express 48');
define('SHIPPING_FEM', 'Parcelforce Express 48 Large');
define('SHIPPING_PFGX', 'Parcelforce Global Express');
define('SHIPPING_PFGP', 'Parcelforce Global Priority');
define('SHIPPING_PFGV', 'Parcelforce Global Value');
define('SHIPPING_PFGE', 'Parcelforce Global Economy');
define('SHIPPING_ER1', 'Parcelforce Europriority');
define('SHIPPING_ECA', 'Parcelforce GlobalPriority');
define('SHIPPING_SHIP_PACKAGE','Verzend een Pakketje');
define('SHIPPING_CREATE_ENTRY','Maak een nieuwe verzending aan');
define('SHIPPING_RECON_BILL','Verzoende Rekening');
define('SHIPPING_RECP_INFO','Ontvanger Informatie');
define('SHIPPING_EMAIL_NOTIFY','Email Notificatie');
define('SHIPPING_BILL_DETAIL','Factuur Details');
define('SHIPPING_LTL_FREIGHT','LTL Vracht');
define('SHIPPING_LTL_CLASS','Vracht Klasse');
define('TEXT_TRACK_CONFIRM','Levering Bevestigd');
define('SHIPPING_DAILY','Dagelijks ophalen');
define('SHIPPING_CARRIER','Vervoerder Klant Toonbank');
define('SHIPPING_ONE_TIME','Verzoek / eenmalig ophalen');
define('SHIPPING_ON_CALL','Wachten op Vlucht');
define('SHIPPING_RETAIL','Voorgestelde Verkoop Prijs');
define('SHIPPING_DROP_BOX','Drop Box/Centrum');
define('SHIPPING_AIR_SRV','Lucht service center');
// Packing list page
define('PKL_PAGE_TITLE',         'Paklijst');
define('PKL_PRINT_LINK',         'Paklijst afdrukken');
define('PKL_BTN_PRINT',          'Afdrukken');
define('PKL_BTN_CLOSE',          'Sluiten');
define('PKL_TEXT_SHIP_TO',       'Verzenden naar');
define('PKL_TEXT_SHIPMENT_DETAILS', 'Verzendgegevens');
define('PKL_TEXT_CARRIER',       'Vervoerder');
define('PKL_TEXT_SERVICE',       'Service');
define('PKL_TEXT_CUSTOMER_REF',  'Klant referentie');
define('PKL_TEXT_PACKAGES',      'Pakketten');
define('PKL_TEXT_ITEMS',         'Artikelen');
define('PKL_TEXT_BOX',           'Doos');
define('PKL_TEXT_SKU',           'SKU');
define('PKL_TEXT_DESCRIPTION',   'Omschrijving');
define('PKL_TEXT_QTY',           'Aantal');
define('PKL_NO_PACKAGES',        'Geen artikelen geregistreerd voor deze bestelling.');
define('PKL_BOX_NO_ITEMS',       'Geen artikelen geregistreerd voor deze doos.');
define('PKL_ERROR_NO_ORDER',     'Geen order ID opgegeven.');
define('PKL_ERROR_NOT_FOUND',    'Bestelling niet gevonden.');

define('SHIPPING_TEXT_LBS','lbs');
define('SHIPPING_TEXT_KGS','kgs');
define('SHIPPING_TEXT_IN','in');
define('SHIPPING_TEXT_CM','cm');
define('SHIPPING_ENVENLOPE','Envelop/Brief');
define('SHIPPING_CUST_SUPP','Klant geleverd');
define('SHIPPING_TUBE','Verzend Tube');
define('SHIPPING_PAK','Verzend Pak');
define('SHIPPING_BOX','Verzend Doos');
define('SHIPPING_25KG','25kg Doos');
define('SHIPPING_10KG','10kg Doos');
define('SHIPPING_CASH','Contant');
define('SHIPPING_CHECK','Check');
define('SHIPPING_CASHIERS','kassiers check');
define('SHIPPING_MO','Postwissel');
define('SHIPPING_ANY','Elk');
define('SHIPPING_NO_CONF','Geen Leveringsbevestiging');
define('SHIPPING_NO_SIG_RQD','Geen Handtekening Vereist');
define('SHIPPING_SIG_REQ','Handtekening Vereist');
define('SHIPPING_ADULT_SIG','Handtekening van Volwassene Vereist');
define('SHIPPING_RET_CARRIER','Koerier Retour Label');
define('SHIPPING_RET_LOCAL','Print Locale Retour Label');
define('SHIPPING_RET_MAILS','Koerier Print en mailt labels retour');
define('SHIPPING_SENDER','Zender');
define('SHIPPING_RECIPIENT', 'Ontvanger');
define('SHIPPING_LABEL_DOWNLOAD', 'Download');
define('SHIPPING_LABEL_EMAIL', 'E-mail');
define('SHIPPING_RECEIPIENT','Ontvanger');
define('SHIPPING_COLLECT','Verzamel');

// Shipping Packages page
define('SHPKG_HEADING_TITLE',       'Verzendverpakkingen');
define('SHPKG_TEXT_CODE',           'Code');
define('SHPKG_TEXT_NAME',           'Naam');
define('SHPKG_TEXT_DESCRIPTION',    'Omschrijving');
define('SHPKG_TEXT_WEIGHT',         'Tarra Gewicht');
define('SHPKG_TEXT_MAX_WEIGHT',     'Max. Gewicht');
define('SHPKG_TEXT_CARRIER',        'Vervoerder');
define('SHPKG_TEXT_ALL_CARRIERS',   'Alle vervoerders');
define('SHPKG_TEXT_EXTERNAL',       'Buiten');
define('SHPKG_TEXT_INTERNAL',       'Binnen');
define('SHPKG_TEXT_EXT_DIMS',       'Buitenafmetingen (L&times;B&times;H)');
define('SHPKG_TEXT_INT_DIMS',       'Binnenafmetingen (L&times;B&times;H)');
define('SHPKG_TEXT_EXT_LENGTH',     'Buiten lengte');
define('SHPKG_TEXT_EXT_WIDTH',      'Buiten breedte');
define('SHPKG_TEXT_EXT_HEIGHT',     'Buiten hoogte');
define('SHPKG_TEXT_INT_LENGTH',     'Binnen lengte');
define('SHPKG_TEXT_INT_WIDTH',      'Binnen breedte');
define('SHPKG_TEXT_INT_HEIGHT',     'Binnen hoogte');
define('SHPKG_TEXT_VOL_WEIGHT',     'Vol. Gewicht');
define('SHPKG_TEXT_COST',           'Kosten');
define('SHPKG_TEXT_INACTIVE',       'Inactief');
define('SHPKG_TEXT_SORT_ORDER',     'Sorteervolgorde');
define('SHPKG_ERROR_DUP_CODE',      'Er bestaat al een verpakking met die code.');
define('SHPKG_ERROR_MISSING_CODE',  'Verpakkingscode is verplicht.');
define('SHPKG_ERROR_MISSING_NAME',  'Verpakkingsnaam is verplicht.');
// Shipment contents on a label
define('SHIPPING_LINE_OF', 'van %s');
define('SHIPPING_LINE_REMOVE', 'Verwijderen');
define('SHIPPING_LINE_RESTORE', 'Herstellen');
define('SHIPPING_ERROR_LINE_QTY', 'Artikel %s: de hoeveelheid moet tussen 0 en %s liggen.');
define('SHIPPING_ERROR_NOTHING_TO_SHIP', 'Alle artikelen zijn verwijderd, er valt niets te verzenden.');
define('SHIPPING_SHORT_TITLE', 'De rest van dit artikel');
define('SHIPPING_SHORT_LATER', 'Rest volgt later');
define('SHIPPING_SHORT_CANCEL', 'Rest wordt niet verzonden');
define('SHIPPING_SHORT_CHOOSE', 'Kies…');
define('SHIPPING_ERROR_SHORT_CHOICE', 'Artikel %s wordt niet volledig verzonden: kies of de rest later volgt of niet wordt verzonden.');
define('SHIPPING_TEXT_PART_NUMBER', 'Onderdeelnr. fabrikant');
define('SHIPPING_TEXT_BARCODE', 'Barcode');
define('SHIPPING_LINE_SHIPPED', '%s al verzonden');
define('SHIPPING_LINE_CANCELLED', '%s wordt niet verzonden');
define('SHIPPING_LINE_NOTHING_LEFT', 'Niets meer te verzenden');
define('SHIPPING_ORDER_SHIPMENTS', 'Zendingen voor deze order');
define('SHIPPING_TEXT_CONTENTS', 'Inhoud');
define('SHIPPING_TEXT_CANCELLED', 'Wordt niet verzonden');
define('SHIPPING_RESTORE_CONFIRM', 'Dit artikel weer openstaand maken, zodat een latere zending het kan verzenden?');
define('SHIPPING_CANCELLED_RESTORED', '%s van %s staat weer open.');
define('SHIPPING_CANCELLED_RESTORED_LOG', 'Zendingsregel hersteld');

?>
