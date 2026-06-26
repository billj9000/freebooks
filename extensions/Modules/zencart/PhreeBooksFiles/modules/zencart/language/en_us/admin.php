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
//  Path: /modules/zencart/language/en_us/admin.php

// Module information
define('MODULE_ZENCART_TITLE','ZenCart Module');
define('MODULE_ZENCART_DESCRIPTION','The ZenCart module interfaces PhreeBooks with a ZenCart e-store. Functons include upload products, download orders and synchronizing product databases.');
define('ZENCART_ADMIN_URL','ZenCart store URL (no trailing slash, e.g. https://shop.example.com)');
define('ZENCART_ADMIN_API_KEY','API key (must match FREEBOOKS_API_KEY in ZenCart configuration table)');
define('MODULE_ZENCART_CONFIG_INFO','Please set the configuration values to your ZenCart e-store.');
define('ZENCART_TAX_CLASS','Enter the ZenCart Tax Class Text field (Must match exactly to the entry in ZenCart if tax is charged)');
define('ZENCART_USE_PRICES','Do you want to use price sheets?');
define('ZENCART_TEXT_PRICE_SHEET','ZenCart Price Sheet to use');
define('ZENCART_SHIP_ID','ZenCart numeric status code for Shipped Orders');
define('ZENCART_PARTIAL_ID','ZenCart numeric status code for Partially Shipped Orders');
define('ZENCART_LBL_NOTIFY_SHIPMENTS','Send shipping notifications to ZenCart in the daily cron (emails customers when their order is shipped, using the status codes above)');
define('ZENCART_LBL_NOTIFY_MESSAGE','Shipping notification message — use {date}, {carrier}, {service}, {tracking} in any order');
define('ZENCART_LBL_UPDATED_BY','Name shown in the ZenCart order status "Updated By" column');
define('ZENCART_CONFIG_SAVED','Zencart configuration values updated/saved.');
define('ZENCART_CATALOG_ADD','Allow upload to ZenCart Catalog');
define('ZENCART_CATALOG_CATEGORY_ID','ZenCart - category id. Needs to match Zen Cart category where product will be located.');
define('ZENCART_CATALOG_MANUFACTURER','ZenCart - product manufacturer. Needs to match with the manufacturer name as defined in ZenCart.');
define('ZENCART_LBL_CATCH_ALL_ACCT','Catch-all customer contact ID for orders where email cannot be matched or a new customer cannot be created (0 = skip such orders)');
define('ZENCART_LBL_DEFAULT_TAX','Fallback tax rate ID when ZenCart\'s item tax % cannot be matched in the Tax Rates table (0 = untaxed)');
define('ZENCART_LBL_CREATE_INVOICE','Also create a Sales Invoice (journal 12) for each imported order');
define('ZENCART_LBL_DEFAULT_REP','Default sales rep for imported orders (0 = none)');
define('ZENCART_LBL_LAST_ORDER','Last imported ZenCart order ID — edit to re-import orders from a given point');
define('ZENCART_LBL_PAYMENT_MAP','Payment module map — comma-separated entries of the form zcmodule=fbmodule:glacct (e.g. paypalexpress=paypal_nvp:1020,stripe=freecharger:1030). fbmodule and glacct are each optional.');
define('ZENCART_LBL_SHIPPING_MAP','Shipping method map — pipe-separated entries of the form zc_module:keyword=fb_module:fb_service. keyword is matched as a case-insensitive substring of the ZenCart shipping description (may contain colons); longest match wins. Leave keyword blank for a catch-all entry for that module. Example: royalmail:RM Tracked 24=royal_mail:RM24|royalmail:=royal_mail:RM48|dhl:DHL Express: Domestic=dhl_express:DHL_D');
define('ZENCART_LBL_NON_SHIP_SKUS','Non-shipping SKUs — comma-separated list of ZenCart item codes that are fee charges (IOSS, duty, etc.) and should be excluded from the customs/shipping line items and declared goods value. These items are still imported into the order for accounting purposes.');
// Validation warnings for config maps
define('ZENCART_WARN_PAYMENT_MAP_FMT',      'Payment map entry #%d ("%s"): missing "=" separator — expected format zcmodule=fbmodule:glacct.');
define('ZENCART_WARN_PAYMENT_MAP_EMPTY',    'Payment map entry #%d: ZenCart module name is empty.');
define('ZENCART_WARN_SHIPPING_MAP_FMT',     'Shipping map entry #%d ("%s"): missing "=" separator — expected format zc_module:keyword=fb_module:fb_service.');
define('ZENCART_WARN_SHIPPING_MAP_EMPTY',   'Shipping map entry #%d: ZenCart module name is empty.');
define('ZENCART_WARN_SHIPPING_MAP_MODULE',  'Shipping map entry #%d: FreeBooks shipping module "%s" not found under modules/shipping/methods/.');
// audit log messages
define('ZENCART_LOG_TABS','Zencart Inventory Add Tab');
define('ZENCART_LOG_FIELDS','Zencart Inventory Add Field');

?>