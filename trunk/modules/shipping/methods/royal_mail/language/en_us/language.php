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
//  Path: /modules/shipping/methods/royal_mail/language/en_us/language.php
//

define('MODULE_SHIPPING_ROYAL_MAIL_TEXT_TITLE',       'Royal Mail Click & Drop');
define('MODULE_SHIPPING_ROYAL_MAIL_TEXT_DESCRIPTION', 'Royal Mail Click & Drop label printing (OBA)');
define('MODULE_SHIPPING_ROYAL_MAIL_TITLE_SHORT',      'Royal Mail');
// Admin field descriptions
define('MODULE_SHIPPING_ROYAL_MAIL_TITLE_DESC',      'Title shown for this carrier.');
define('MODULE_SHIPPING_ROYAL_MAIL_API_KEY_DESC',    'Click & Drop API key. Obtain from your Click & Drop account under Settings &rarr; API.');
define('MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER_DESC', 'Sort order of display. Lowest is displayed first.');
// Error messages
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL',  'Royal Mail: Label creation failed - ');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NOKEY',  'Royal Mail: Click & Drop API key not configured.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_CANCEL', 'Royal Mail: Click & Drop did not cancel order %s (%s). The shipment is kept here; cancel it in Click & Drop if it should not go.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NO_ORDER', 'Royal Mail: this shipment has no Click & Drop order number saved, so it cannot be cancelled from here. Cancel it in Click & Drop.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_BAD_FORMAT', 'Royal Mail: Package format \'%s\' is not valid for service \'%s\'. Valid formats: %s.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_DUTY', 'Royal Mail: the prepaid duty must be an amount from 0 to 99,999.99.');
// Label form — customs section
define('RM_CUSTOMS_SECTION',           'Customs / Export Information');
define('RM_TEXT_DECLARATION_CATEGORY', 'Declaration Category');
define('RM_TEXT_DECLARED_VALUE',       'Declared Value');
define('RM_TEXT_HS_CODE',              'HS / Commodity Code');
define('RM_TEXT_ORIGIN_COUNTRY',       'Country of Origin');
define('RM_TEXT_RETURNS_LABEL',        'Include Returns Label');
define('RM_TEXT_DELIVERY_INSTRUCTIONS', 'Delivery instructions');
define('RM_TEXT_ORDER_NOTES',           'Order notes (for reference, not sent):');
define('RM_TEXT_DELIVERY_TERMS',       'Delivery Terms');
define('RM_TERMS_DDU',                 'Duty unpaid (DDU/DAP) - the recipient pays');
define('RM_TERMS_DDP',                 'Duty paid (DDP) - we pay');
define('RM_TEXT_DUTY_PAID',            'Prepaid Duty');
define('RM_TEXT_RECIPIENT_BUSINESS',   'Recipient is a business');
// Label form — line items table
define('RM_LINE_ITEMS_HEADER',   'Shipment Contents');
define('RM_LINE_ITEMS_CUSTOMS',  '(Customs Declaration)');
define('RM_TEXT_UNIT_WEIGHT',    'Unit Weight');
define('RM_TEXT_UNIT_VALUE',     'Unit Value');
define('RM_TEXT_SAVE_TO_INV',    'Save descriptions to inventory');
define('RM_ITEMS_SAVED',         'Item descriptions saved to inventory.');
// Label form — package formats
define('RM_TEXT_PACKAGE_FORMAT',   'Format');
define('RM_PKG_LETTER',            'Letter');
define('RM_PKG_LARGE_LETTER',      'Large Letter');
define('RM_PKG_SMALL_PARCEL',      'Small Parcel');
define('RM_PKG_MEDIUM_PARCEL',     'Medium Parcel');
define('RM_PKG_LARGE_PARCEL',      'Large Parcel');
define('RM_PKG_PARCEL',            'Parcel');
define('RM_PKG_DOCUMENTS',         'Documents');
// Declaration category options
define('RM_DECL_SALE_OF_GOODS',     'Commercial Sale');
define('RM_DECL_GIFT',              'Gift');
define('RM_DECL_DOCUMENTS',         'Documents');
define('RM_DECL_COMMERCIAL_SAMPLE', 'Commercial Sample');
define('RM_DECL_RETURNED_GOODS',    'Returned Goods');
define('RM_DECL_OTHER',             'Other');
// Manifest page
define('RM_MANIFEST_TITLE',         'Royal Mail Manifest');
define('RM_MANIFEST_HEADING',       'Royal Mail End-of-Day Manifest');
define('RM_MANIFEST_DESC',          'Submits all unmanifested labels to Royal Mail for today\'s collection. Run this once at the end of each shipping day.');
define('RM_MANIFEST_BTN',           'Manifest Now');
define('RM_MANIFEST_SUCCESS',       'Manifest submitted successfully.');
define('RM_MANIFEST_ID',            'Manifest ID');
define('RM_MANIFEST_CONFIRM',       'Submit all unmanifested Royal Mail orders for collection?');
define('RM_MANIFEST_ERROR',         'Manifest request failed.');
define('RM_MANIFEST_NONE_TODAY',    'No Royal Mail shipments today.');
define('RM_MANIFEST_SHIPMENTS',     'Today\'s Royal Mail Shipments');
define('RM_MANIFEST_OPEN_BTN',      'RM Manifest');
define('RM_TEXT_RECIPIENT',         'Recipient');
?>
