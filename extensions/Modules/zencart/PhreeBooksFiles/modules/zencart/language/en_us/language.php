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
//  Path: /modules/zencart/language/en_us/language.php
//

// Headings 
define('BOX_ZENCART_ADMIN','Zencart Configuration');
// General Defines
define('ZENCART_CONFIRM_MESSAGE','Your order shipped {date} via {carrier} {service}, tracking number: {tracking}');
define('ZENCART_BULK_UPLOAD_SUCCESS','Successfully uploaded %s item(s) to the ZenCart e-store.');
define('ZENCART_TEXT_ERROR','Error # ');
define('ZENCART_IVENTORY_UPLOAD','ZenCart Upload');
define('ZENCART_BULK_UPLOAD_TITLE','Bulk Upload');
define('ZENCART_BULK_UPLOAD_INFO','Bulk upload all products selected to be displayed in the ZenCart e-commerce site. Images are not included unless the checkbox is checked.');
define('ZENCART_BULK_UPLOAD_TEXT','Bulk upload products to e-store');
define('ZENCART_INCLUDE_IMAGES','Include Images');
define('ZENCART_BULK_UPLOAD_BTN','Bulk Upload');
define('ZENCART_PRODUCT_SYNC_TITLE','Synchronize Products');
define('ZENCART_PRODUCT_SYNC_INFO','Push current stock quantities and retail prices from FreeBooks to ZenCart for all products marked for the catalog. Use this to manually force a quantity sync outside of the scheduled cron run.');
define('ZENCART_SYNC_QTY_SUCCESS','Successfully synced quantity/price for %s product(s) to ZenCart.');
define('ZENCART_SYNC_QTY_PARTIAL','Synced %s product(s); %s SKU(s) not found in ZenCart:');
define('ZENCART_PRODUCT_SYNC_TEXT','Synchronize products with e-store');
define('ZENCART_DELETE_ZENCART','Also delete products in ZenCart that are not falgged to be there.');
define('ZENCART_PRODUCT_SYNC_BTN','Synchronize');
define('ZENCART_SHIP_CONFIRM_TITLE','Confirm Shipments');
define('ZENCART_SHIP_CONFIRM_INFO','Confirms all shipments on the date selected from the Shipping Manager and sets the status in ZenCart. Completed orders and partially shipped orders are updated. Email notifications to the customer are not sent.');
define('ZENCART_SHIP_CONFIRM_TEXT','Send shipment confirmations');
define('ZENCART_TEXT_CONFIRM_ON','For orders shipped on');
define('ZENCART_SHIP_CONFIRM_BTN','Confirm Shipments');
// Error Messages
define('ZENCART_ERROR_NO_ITEMS','No inventory items were selected to upload to the ZenCart catalog. Looking for the checkbox field named catalog to identify items to be uploaded.');
define('ZENCART_ERROR_CONFRIM_NO_DATA','No records were found for this date to confirm with ZenCart.');
define('ZENCART_ERROR_NO_PRICE_SHEET','Couldn\'t find a default price level for price sheet: ');
define('ZENCART_INVALID_ACTION','Invalid action requested in ZenCart interface class. Aborting!');
define('ZENCART_INVALID_SKU','Error in inventory item id, could not find the record in the database');
// Javascrpt Defines
// Audit Log Messages
define('ZENCART_UPLOAD_PRODUCT','ZenCart Product Upload');
define('ZENCART_BULK_UPLOAD','ZenCart Bulk Upload');
define('ZENCART_PRODUCT_SYNC','ZenCart Product Sync');
define('ZENCART_SHIP_CONFIRM','ZenCart Ship Confirmation');
define('ZENCART_INVENTORY_CATALOG_IS_EMPTY','The field webshop menu / catalog is empty. this field is required when you want to upload a product.');
define('ZENCART_DISABLED_NOTICE','The ZenCart integration is disabled. Enable it under Company → Configuration → ZenCart before uploading or syncing.');
// VAT invoice email — fallback default body if ZENCART_VAT_INVOICE_MESSAGE is ever cleared
define('ZENCART_VAT_INVOICE_MESSAGE_DEFAULT','Please find attached the VAT invoice {invoice} for your order, {name}. Thank you for your business.');

define('ZENCART_CATALOG_URL','ZenCart - product URL.');
define('ZENCART_CATALOG_MODEL','ZenCart - product model.');

?>