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
//  Path: /modules/shipping/methods/royal_mail/language/nl_nl/language.php
//

define('MODULE_SHIPPING_ROYAL_MAIL_TEXT_TITLE',       'Royal Mail Click & Drop');
define('MODULE_SHIPPING_ROYAL_MAIL_TEXT_DESCRIPTION', 'Labels afdrukken via Royal Mail Click & Drop (OBA)');
define('MODULE_SHIPPING_ROYAL_MAIL_TITLE_SHORT',      'Royal Mail');
// Admin field descriptions
define('MODULE_SHIPPING_ROYAL_MAIL_TITLE_DESC',      'Titel die voor deze vervoerder wordt getoond.');
define('MODULE_SHIPPING_ROYAL_MAIL_API_KEY_DESC',    'Click & Drop API-sleutel. Verkrijgbaar in uw Click & Drop-account onder Settings &rarr; API.');
define('MODULE_SHIPPING_ROYAL_MAIL_HANDLING_DESC',      'Behandelingskosten die bij elke offerte van Royal Mail en Parcelforce worden opgeteld, in de standaardvaluta.');
define('MODULE_SHIPPING_ROYAL_MAIL_SURCHARGE_DESC',     'Toeslag (%) op de accountprijzen van Royal Mail, zoals de brandstof- en energietoeslag, plus een eventuele piektoeslag. Onlineprijzen bevatten die al.');
define('MODULE_SHIPPING_ROYAL_MAIL_INTL_SURCHARGE_DESC','Extra toeslag (%) op de internationale accountprijzen van Royal Mail: de internationale toeslag.');
define('MODULE_SHIPPING_ROYAL_MAIL_PF_SURCHARGE_DESC',  'Toeslag (%) op de account- en contractprijzen van Parcelforce, zoals de brandstof- en energietoeslag, plus een eventuele piektoeslag.');
define('MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER_DESC', 'Volgorde van tonen. De laagste wordt als eerste getoond.');
// Error messages
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL',  'Royal Mail: aanmaken van label mislukt - ');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NOKEY',  'Royal Mail: Click & Drop API-sleutel niet ingesteld.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_CANCEL', 'Royal Mail: Click & Drop heeft order %s niet geannuleerd (%s). De zending blijft hier bewaard; annuleer deze in Click & Drop als hij niet moet worden verzonden.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NO_ORDER', 'Royal Mail: voor deze zending is geen Click & Drop-ordernummer opgeslagen, dus kan deze hier niet worden geannuleerd. Annuleer hem in Click & Drop.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_BAD_FORMAT', 'Royal Mail: pakketformaat \'%s\' is niet geldig voor dienst \'%s\'. Geldige formaten: %s.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_DUTY', 'Royal Mail: de vooruitbetaalde invoerrechten moeten een bedrag zijn van 0 tot 99,999.99.');
define('MODULE_SHIPPING_ROYAL_MAIL_WARN_INVOICE', 'Royal Mail: het label is geboekt, maar Click & Drop leverde geen handelsfactuur (%s). Druk deze af vanuit de order op de website van Click & Drop.');
// Label form — customs section
define('RM_CUSTOMS_SECTION',           'Douane- / exportgegevens');
define('RM_TEXT_DECLARATION_CATEGORY', 'Aangiftecategorie');
define('RM_TEXT_DECLARED_VALUE',       'Aangegeven waarde');
define('RM_TEXT_HS_CODE',              'HS- / goederencode');
define('RM_TEXT_ORIGIN_COUNTRY',       'Land van oorsprong');
define('RM_TEXT_RETURNS_LABEL',        'Retourlabel meesturen');
define('RM_TEXT_DELIVERY_INSTRUCTIONS', 'Bezorginstructies');
define('RM_TEXT_ORDER_NOTES',           'Ordernotities (ter referentie, niet verzonden):');
define('RM_TEXT_DELIVERY_TERMS',       'Leveringsvoorwaarden');
define('RM_TERMS_DDU',                 'Rechten onbetaald (DDU/DAP) - de ontvanger betaalt');
define('RM_TERMS_DDP',                 'Rechten betaald (DDP) - wij betalen');
define('RM_TEXT_DUTY_PAID',            'Vooruitbetaalde invoerrechten');
define('RM_TEXT_RECIPIENT_BUSINESS',   'Ontvanger is een bedrijf');
// Label form — line items table
define('RM_LINE_ITEMS_HEADER',   'Inhoud zending');
define('RM_LINE_ITEMS_CUSTOMS',  '(Douaneaangifte)');
define('RM_TEXT_UNIT_WEIGHT',    'Gewicht per stuk');
define('RM_TEXT_UNIT_VALUE',     'Waarde per stuk');
define('RM_TEXT_SAVE_TO_INV',    'Omschrijvingen opslaan in voorraad');
define('RM_ITEMS_SAVED',         'Artikelomschrijvingen opgeslagen in voorraad.');
// Label form — package formats
define('RM_TEXT_PACKAGE_FORMAT',   'Formaat');
define('RM_PKG_LETTER',            'Brief');
define('RM_PKG_LARGE_LETTER',      'Grote brief');
define('RM_PKG_SMALL_PARCEL',      'Klein pakket');
define('RM_PKG_MEDIUM_PARCEL',     'Middelgroot pakket');
define('RM_PKG_LARGE_PARCEL',      'Groot pakket');
define('RM_PKG_PARCEL',            'Pakket');
define('RM_PKG_DOCUMENTS',         'Documenten');
// Declaration category options
define('RM_DECL_SALE_OF_GOODS',     'Commerciële verkoop');
define('RM_DECL_GIFT',              'Geschenk');
define('RM_DECL_DOCUMENTS',         'Documenten');
define('RM_DECL_COMMERCIAL_SAMPLE', 'Handelsmonster');
define('RM_DECL_RETURNED_GOODS',    'Geretourneerde goederen');
define('RM_DECL_OTHER',             'Overig');
// Manifest page
define('RM_MANIFEST_TITLE',         'Royal Mail-manifest');
define('RM_MANIFEST_HEADING',       'Royal Mail-eindedagmanifest');
define('RM_MANIFEST_DESC',          'Verstuurt alle labels die nog niet in een manifest staan naar Royal Mail voor de ophaalopdracht van vandaag. Voer dit eenmaal uit aan het einde van elke verzenddag.');
define('RM_MANIFEST_BTN',           'Manifest nu versturen');
define('RM_MANIFEST_SUCCESS',       'Manifest succesvol verstuurd.');
define('RM_MANIFEST_ID',            'Manifest-ID');
define('RM_MANIFEST_CONFIRM',       'Alle Royal Mail-orders die nog niet in een manifest staan, versturen voor ophalen?');
define('RM_MANIFEST_ERROR',         'Manifestaanvraag mislukt.');
define('RM_MANIFEST_NONE_TODAY',    'Vandaag geen Royal Mail-zendingen.');
define('RM_MANIFEST_SHIPMENTS',     'Royal Mail-zendingen van vandaag');
define('RM_MANIFEST_OPEN_BTN',      'RM-manifest');
define('RM_TEXT_RECIPIENT',         'Ontvanger');
// Prijslijstpagina
define('RM_RATES_TITLE',           'Royal Mail-prijslijst');
define('RM_RATES_HEADING',         'Prijslijst Royal Mail en Parcelforce');
define('RM_RATES_OPEN_BTN',        'Prijslijst');
define('RM_RATES_UPLOAD',          'Tariefbestand importeren');
define('RM_RATES_UPLOAD_DESC',     'Kies een tariefbestand (CSV, één regel per prijsschijf, prijzen in GBP). Het wordt gecontroleerd en samengevat voordat er iets wordt opgeslagen. Een bestand vervangt een prijslijst die al voor dezelfde ingangsdatum is opgeslagen; lijsten voor andere data blijven bewaard.');
define('RM_RATES_CHECK',           'Bestand controleren');
define('RM_RATES_IMPORT',          'Importeren');
define('RM_RATES_DISCARD',         'Verwerpen');
define('RM_RATES_PENDING',         'Klaar om te importeren: %s');
define('RM_RATES_PENDING_DESC',    'Het bestand bevat %d prijsschijven, geldig vanaf %s.');
define('RM_RATES_PENDING_REPLACES','Het vervangt de %d schijven die al voor die datum zijn opgeslagen.');
define('RM_RATES_CHECK_FAILED',    '%s kan niet worden geïmporteerd: %d problemen');
define('RM_RATES_MORE_ERRORS',     'en nog %d.');
define('RM_RATES_STORED',          'Opgeslagen prijslijsten');
define('RM_RATES_NONE',            'Er is nog geen prijslijst geïmporteerd.');
define('RM_RATES_IMPORTED',        '%d prijsschijven geïmporteerd, geldig vanaf %s.');
define('RM_RATES_DELETED',         'De prijslijst geldig vanaf %s is verwijderd.');
define('RM_RATES_DELETE_CONFIRM',  'De prijslijst geldig vanaf %s verwijderen?');
define('RM_RATES_AUDIT_IMPORT',    'Royal Mail-prijslijst - import');
define('RM_RATES_AUDIT_DELETE',    'Royal Mail-prijslijst - verwijderen');
define('RM_RATES_TEXT_CARRIER',    'Vervoerder');
define('RM_RATES_TEXT_SERVICE',    'Dienst');
define('RM_RATES_TEXT_CHANNEL',    'Prijskanaal');
define('RM_RATES_TEXT_ROWS',       'Schijven');
define('RM_RATES_TEXT_CONFIRM',    'Te bevestigen');
define('RM_RATES_TEXT_EFFECTIVE',  'Geldig vanaf');
define('RM_RATES_TEXT_STATUS',     'Status');
define('RM_RATES_STATUS_CURRENT',  'In gebruik');
define('RM_RATES_STATUS_FUTURE',   'Nog niet van kracht');
define('RM_RATES_STATUS_PAST',     'Vervangen');
define('RM_RATES_ERR_READ',        'Het tariefbestand kon niet worden gelezen.');
define('RM_RATES_ERR_EMPTY',       'Het tariefbestand bevat geen prijsschijven.');
define('RM_RATES_ERR_NO_PENDING',  'Er is geen gecontroleerd tariefbestand om te importeren. Kies het bestand opnieuw.');
define('RM_RATES_ERR_COLUMNS',     'In het tariefbestand ontbreken deze kolommen: %s.');
define('RM_RATES_ERR_DATES',       'Een tariefbestand moet één effective_from-datum hebben; dit bestand heeft %s.');
define('RM_RATES_ERR_LONG',        'Regel %d: %s is langer dan %d tekens.');
define('RM_RATES_ERR_BLANK',       'Regel %d: %s is leeg.');
define('RM_RATES_ERR_VALUE',       'Regel %d: %s kan niet "%s" zijn.');
define('RM_RATES_ERR_NUMBER',      'Regel %d: %s "%s" is geen geldig getal.');
define('RM_RATES_ERR_WEIGHTS',     'Regel %d: weight_from_g moet kleiner zijn dan weight_to_g.');
define('RM_RATES_ERR_PRICE',       'Regel %d: de prijs moet hoger zijn dan 0.');
define('RM_RATES_ERR_INCREMENT',   'Regel %d: een increment-regel heeft increment_g nodig.');
define('RM_RATES_ERR_VAT',         'Regel %d: %s is %s, maar de prijs en de btw-behandeling geven %s.');
define('RM_RATES_ERR_OVERLAP',     'Overlappende schijven voor %s (%s, %s, %s): %d-%d g en %d-%d g.');
// Prijslijstpagina - landzones
define('RM_ZONES_HEADING',       'Landzones');
define('RM_ZONES_DESC',          'Prijzen gelden per zone, dus elke bestemming wordt geprijsd via haar Royal Mail- en Parcelforce-zone. %d landen hebben zones; %d worden door de gepubliceerde zonelijsten niet vastgelegd en moeten worden gecontroleerd op royalmail.com en parcelforce.com.');
define('RM_ZONES_EDIT_DESC',     'Om een zone te corrigeren: download de lijst, wijzig hem in een spreadsheet en importeer hem opnieuw. Importeren vervangt de hele lijst.');
define('RM_ZONES_DOWNLOAD',      'Zones downloaden');
define('RM_ZONES_IMPORT',        'Zones importeren');
define('RM_ZONES_RESET',         'Meegeleverde zones herstellen');
define('RM_ZONES_RESET_CONFIRM', 'De opgeslagen zones vervangen door de lijst die met FreeBooks wordt meegeleverd?');
define('RM_ZONES_IMPORTED',      'Zones opgeslagen voor %d landen.');
define('RM_ZONES_CHECK_FAILED',  'Het zonebestand is niet geïmporteerd: %d problemen');
define('RM_ZONES_AUDIT_IMPORT',  'Royal Mail-zones - import');
define('RM_ZONES_AUDIT_RESET',   'Royal Mail-zones - herstellen');
define('RM_ZONES_TEXT_COUNTRY',  'Land');
define('RM_ZONES_TEXT_RM',       'Royal Mail-zone');
define('RM_ZONES_TEXT_PF',       'Parcelforce-zone');
define('RM_ZONES_TEXT_NOTES',    'Opmerkingen');
define('RM_ZONES_ERR_READ',      'Het zonebestand kon niet worden gelezen.');
define('RM_ZONES_ERR_EMPTY',     'Het zonebestand bevat geen landen.');
define('RM_ZONES_ERR_COLUMNS',   'In het zonebestand ontbreken deze kolommen: %s.');
define('RM_ZONES_ERR_COUNTRY',   'Regel %d: "%s" is geen landcode die FreeBooks kent.');
define('RM_ZONES_ERR_DUPLICATE', 'Regel %d: %s komt meer dan eens voor.');
define('RM_ZONES_ERR_ZONE',      'Regel %d: %s kan niet "%s" zijn.');
// Verzendcalculator
define('RM_QUOTE_NO_PRICE_LIST', 'Royal Mail: er is geen prijslijst van kracht, dus er kunnen geen prijzen van Royal Mail of Parcelforce worden gegeven. Importeer er een via de eigenschappen van Royal Mail (Prijslijst).');
define('RM_QUOTE_NO_ZONE',       'Royal Mail: het land van bestemming (%s) heeft geen Royal Mail-zone, dus er kunnen geen prijzen worden gegeven.');
define('RM_QUOTE_NO_GBP',        'Royal Mail: de prijslijst is in GBP en GBP is niet als valuta ingesteld, dus er kunnen geen prijzen worden gegeven.');
define('RM_QUOTE_TO_CONFIRM',    'prijs te bevestigen');
?>
