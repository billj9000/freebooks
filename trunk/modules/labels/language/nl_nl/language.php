<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/language/nl_nl/language.php

define('TEXT_LABEL_TEMPLATES',     'Labelsjablonen');
define('TEXT_ADD_TEMPLATE',        'Sjabloon toevoegen');
define('TEXT_EDIT_TEMPLATE',       'Sjabloon bewerken');
define('TEXT_LABEL_NAME',          'Sjabloonnaam');
define('TEXT_WIDTH_MM',            'Breedte (mm)');
define('TEXT_HEIGHT_MM',           'Hoogte (mm)');
define('TEXT_PRINTER_TYPE',        'Printertype');
define('TEXT_PRINTER_TYPE_HTML',   'HTML (browser / QZ Tray)');
define('TEXT_PRINTER_TYPE_ZPL',    'ZPL (thermisch/Zebra)');
define('TEXT_HTML_TEMPLATE',       'HTML-sjabloon');
define('TEXT_ZPL_TEMPLATE',        'ZPL-sjabloon');
define('TEXT_PREVIEW',             'Voorbeeld');
define('TEXT_PRINT_LABELS',        'Labels afdrukken');
define('TEXT_PRINT_LABEL',         'Label afdrukken');
define('TEXT_SELECT_TEMPLATE',     'Sjabloon selecteren');
define('TEXT_QTY_PER_ITEM',        'Verpakkingsaantal');
define('TEXT_NUM_LABELS',          'Aantal af te drukken labels');
define('TEXT_VARIABLE_REFERENCE',  'Beschikbare variabelen');
define('TEXT_NO_TEMPLATES_DEFINED','Er zijn nog geen labelsjablonen gedefinieerd.');
define('TEXT_LABEL_DELETED',       'Labelsjabloon verwijderd.');
define('TEXT_LABEL_SAVED',         'Labelsjabloon opgeslagen.');
define('TEXT_LABEL_ERROR_NAME',    'Sjabloonnaam is verplicht.');
define('TEXT_LABEL_ERROR_SIZE',    'Breedte en hoogte moeten groter zijn dan nul.');
define('TEXT_LABEL_DATE_MODIFIED', 'Laatst gewijzigd');
define('TEXT_LABEL_PRINT_QTY',     'Aantal');
define('TEXT_LABEL_NO_ITEMS',      'Geen artikelen geselecteerd om af te drukken.');
define('TEXT_LABEL_NO_TEMPLATE',   'Selecteer een labelsjabloon.');
define('TEXT_SKU_SEARCH',          'Zoeken op SKU of artikelnummer leverancier');
define('TEXT_SKU_SEARCH_PLACEHOLDER', 'Typ een SKU of artikelnummer van de leverancier...');
define('TEXT_SKU_NO_MATCHES',      'Geen overeenkomende artikelen gevonden.');
define('TEXT_LABEL_PRINT_NO_PERMISSION', 'U hebt geen rechten om labels af te drukken. Neem contact op met uw beheerder.');

// Variable reference descriptions
define('TEXT_VAR_SKU',             '{{ sku }} — SKU / artikelnummer');
define('TEXT_VAR_DESCRIPTION',     '{{ description }} — Volledige artikelomschrijving');
define('TEXT_VAR_SHORT_DESC',      '{{ short_description }} — Korte omschrijving');
define('TEXT_VAR_PRICE',           '{{ price }} — Opgemaakte verkoopprijs');
define('TEXT_VAR_COST',            '{{ cost }} — Opgemaakte kostprijs');
define('TEXT_VAR_UPC',             '{{ upc }} — UPC-code');
define('TEXT_VAR_WEIGHT',          '{{ weight }} — Artikelgewicht');
define('TEXT_VAR_BARCODE_SVG',     '{{ barcode_svg | raw }} — CODE128-barcode als SVG met de SKU (alleen HTML-sjablonen)');
define('TEXT_VAR_QRCODE_SVG',      '{{ qrcode_svg | raw }} — QR-code als SVG met de SKU (alleen HTML-sjablonen)');
define('TEXT_VAR_QTY_PER_ITEM',    '{{ pack_qty }} — de waarde "Verpakkingsaantal" die is ingevoerd in het pop-upvenster/de pagina Labels afdrukken');

// Conditional layout reference
define('TEXT_CONDITIONAL_LAYOUT',  'Voorwaardelijke opmaak');
define('TEXT_COND_IF',             '{% if upc %}...{% endif %} — neem de inhoud alleen op als een variabele een waarde heeft');
define('TEXT_COND_IF_ELSE',        '{% if upc %}...{% else %}...{% endif %} — kies tussen twee blokken');
define('TEXT_COND_NOTE',           'Voorwaarden kunnen variabelen testen en waarden vergelijken (bijv. {% if price %} of {% if weight > 1 %}). Lussen zijn niet beschikbaar.');
