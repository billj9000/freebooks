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
define('MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER_DESC', 'Volgorde van tonen. De laagste wordt als eerste getoond.');
// Error messages
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL',  'Royal Mail: aanmaken van label mislukt - ');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NOKEY',  'Royal Mail: Click & Drop API-sleutel niet ingesteld.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_CANCEL', 'Royal Mail: Click & Drop heeft order %s niet geannuleerd (%s). De zending blijft hier bewaard; annuleer deze in Click & Drop als hij niet moet worden verzonden.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NO_ORDER', 'Royal Mail: voor deze zending is geen Click & Drop-ordernummer opgeslagen, dus kan deze hier niet worden geannuleerd. Annuleer hem in Click & Drop.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_BAD_FORMAT', 'Royal Mail: pakketformaat \'%s\' is niet geldig voor dienst \'%s\'. Geldige formaten: %s.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_DUTY', 'Royal Mail: de vooruitbetaalde invoerrechten moeten een bedrag zijn van 0 tot 99,999.99.');
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
?>
