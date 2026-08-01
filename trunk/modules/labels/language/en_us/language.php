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
//  Path: /modules/labels/language/en_us/language.php

define('TEXT_LABEL_TEMPLATES',     'Label Templates');
define('TEXT_ADD_TEMPLATE',        'Add Template');
define('TEXT_EDIT_TEMPLATE',       'Edit Template');
define('TEXT_LABEL_NAME',          'Template Name');
define('TEXT_WIDTH_MM',            'Width (mm)');
define('TEXT_HEIGHT_MM',           'Height (mm)');
define('TEXT_PRINTER_TYPE',        'Printer Type');
define('TEXT_PRINTER_TYPE_HTML',   'HTML (browser / QZ Tray)');
define('TEXT_PRINTER_TYPE_ZPL',    'ZPL (thermal/Zebra)');
define('TEXT_HTML_TEMPLATE',       'HTML Template');
define('TEXT_ZPL_TEMPLATE',        'ZPL Template');
define('TEXT_PREVIEW',             'Preview');
define('TEXT_PRINT_LABELS',        'Print Labels');
define('TEXT_PRINT_LABEL',         'Print Label');
define('TEXT_SELECT_TEMPLATE',     'Select Template');
define('TEXT_QTY_PER_ITEM',        'Pack Qty');
define('TEXT_NUM_LABELS',          'Number of Labels to Print');
define('TEXT_VARIABLE_REFERENCE',  'Available Variables');
define('TEXT_NO_TEMPLATES_DEFINED','No label templates have been defined yet.');
define('TEXT_LABEL_DELETED',       'Label template deleted.');
define('TEXT_LABEL_SAVED',         'Label template saved.');
define('TEXT_LABEL_ERROR_NAME',    'Template name is required.');
define('TEXT_LABEL_ERROR_SIZE',    'Width and height must be greater than zero.');
define('TEXT_LABEL_DATE_MODIFIED', 'Last Modified');
define('TEXT_LABEL_PRINT_QTY',     'Quantity');
define('TEXT_LABEL_NO_ITEMS',      'No items selected for printing.');
define('TEXT_LABEL_NO_TEMPLATE',   'Please select a label template.');
define('TEXT_SKU_SEARCH',          'Search by SKU or Vendor Part #');
define('TEXT_SKU_SEARCH_PLACEHOLDER', 'Start typing a SKU or vendor part number...');
define('TEXT_SKU_NO_MATCHES',      'No matching items found.');
define('TEXT_LABEL_PRINT_NO_PERMISSION', 'You do not have permission to print labels. Please contact your administrator.');

// Variable reference descriptions
define('TEXT_VAR_SKU',             '{{ sku }} — Item SKU / part number');
define('TEXT_VAR_DESCRIPTION',     '{{ description }} — Full item description');
define('TEXT_VAR_SHORT_DESC',      '{{ short_description }} — Short description');
define('TEXT_VAR_PRICE',           '{{ price }} — Formatted retail price');
define('TEXT_VAR_COST',            '{{ cost }} — Formatted item cost');
define('TEXT_VAR_UPC',             '{{ upc }} — UPC code');
define('TEXT_VAR_WEIGHT',          '{{ weight }} — Item weight');
define('TEXT_VAR_BARCODE_SVG',     '{{ barcode_svg | raw }} — CODE128 barcode SVG encoding the SKU (HTML templates only)');
define('TEXT_VAR_QRCODE_SVG',      '{{ qrcode_svg | raw }} — QR code SVG encoding the SKU (HTML templates only)');
define('TEXT_VAR_QTY_PER_ITEM',    '{{ pack_qty }} — the "Pack Qty" value entered on the Print Labels popup/page');
