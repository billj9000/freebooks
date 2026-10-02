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
//  Path: /modules/shipping/methods/dhl_express/language/en_us/language.php
//

define('MODULE_SHIPPING_DHL_EXPRESS_TEXT_TITLE',       'DHL Express');
define('MODULE_SHIPPING_DHL_EXPRESS_TEXT_DESCRIPTION', 'DHL Express international courier');
define('MODULE_SHIPPING_DHL_EXPRESS_TITLE_SHORT',      'DHL Express');
// Admin panel field descriptions (one per key() entry)
define('MODULE_SHIPPING_DHL_EXPRESS_TITLE_DESC',          'Title shown in the shipping estimator.');
define('MODULE_SHIPPING_DHL_EXPRESS_API_KEY_DESC',        'DHL Express Commerce API key (username). Obtain from the DHL developer portal.');
define('MODULE_SHIPPING_DHL_EXPRESS_API_SECRET_DESC',     'DHL Express Commerce API secret (password).');
define('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_DESC',        'Your DHL Express account number for export (outbound) shipments.');
define('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_IMPORT_DESC', 'Your DHL Express account number for import (inbound) shipments. Leave blank to use the export account number.');
define('MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE_DESC',      'Test uses DHL sandbox credentials; Production uses live credentials.');
define('MODULE_SHIPPING_DHL_EXPRESS_SORT_ORDER_DESC',     'Sort order of display. Lowest is displayed first.');
// Error messages
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_RATES', 'DHL Express: Rate query failed - ');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL', 'DHL Express: Label creation failed - ');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY', 'DHL Express: API credentials not configured.');
// Label form
define('DHL_CUSTOMS_SECTION',     'Customs / Export Information');
define('DHL_TEXT_DESCRIPTION',    'Goods Description');
define('DHL_TEXT_DECLARED_VALUE', 'Declared Value');
define('DHL_TEXT_HS_CODE',        'HS / Commodity Code');
define('DHL_TEXT_ORIGIN_COUNTRY', 'Country of Origin');
define('DHL_TEXT_EXPORT_REASON',  'Reason for Export');
define('DHL_TEXT_IS_CUSTOMS',     'Customs Declarable');
// Line items table
define('DHL_LINE_ITEMS_HEADER',  'Shipment Contents (Customs Declaration)');
define('DHL_TEXT_UNIT_WEIGHT',   'Unit Weight');
define('DHL_TEXT_UNIT_VALUE',    'Unit Value');
define('DHL_TEXT_SAVE_TO_INV',   'Save descriptions to inventory');
define('DHL_ITEMS_SAVED',        'Item descriptions saved to inventory.');
define('DHL_TEXT_CUSTOM_PACKAGE', 'Custom');
?>
