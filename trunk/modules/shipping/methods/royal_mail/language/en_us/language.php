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
define('MODULE_SHIPPING_ROYAL_MAIL_HANDLING_DESC',      'Handling charge added to each Royal Mail and Parcelforce quote, in the default currency.');
define('MODULE_SHIPPING_ROYAL_MAIL_SURCHARGE_DESC',     'Surcharge (%) on Royal Mail account prices, such as the fuel and energy surcharge, plus any peak surcharge. Online prices already include it.');
define('MODULE_SHIPPING_ROYAL_MAIL_INTL_SURCHARGE_DESC','Further surcharge (%) on Royal Mail international account prices: the international surcharge.');
define('MODULE_SHIPPING_ROYAL_MAIL_PF_SURCHARGE_DESC',  'Surcharge (%) on Parcelforce account and contract prices, such as the fuel and energy surcharge, plus any peak surcharge.');
define('MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER_DESC', 'Sort order of display. Lowest is displayed first.');
// Error messages
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL',  'Royal Mail: Label creation failed - ');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NOKEY',  'Royal Mail: Click & Drop API key not configured.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_CANCEL', 'Royal Mail: Click & Drop did not cancel order %s (%s). The shipment is kept here; cancel it in Click & Drop if it should not go.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NO_ORDER', 'Royal Mail: this shipment has no Click & Drop order number saved, so it cannot be cancelled from here. Cancel it in Click & Drop.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_BAD_FORMAT', 'Royal Mail: Package format \'%s\' is not valid for service \'%s\'. Valid formats: %s.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_DUTY', 'Royal Mail: the prepaid duty must be an amount from 0 to 99,999.99.');
define('MODULE_SHIPPING_ROYAL_MAIL_WARN_INVOICE', 'Royal Mail: the label is booked, but Click & Drop did not supply a commercial invoice (%s). Print it from the order on the Click & Drop website.');
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
// Price list page
define('RM_RATES_TITLE',           'Royal Mail Price List');
define('RM_RATES_HEADING',         'Royal Mail and Parcelforce Price List');
define('RM_RATES_OPEN_BTN',        'Price List');
define('RM_RATES_UPLOAD',          'Import a Rate File');
define('RM_RATES_UPLOAD_DESC',     'Choose a rate file (CSV, one row per price band, prices in GBP). It is checked and summarised before anything is saved. A file replaces any price list already stored for the same effective date; lists for other dates are kept.');
define('RM_RATES_CHECK',           'Check File');
define('RM_RATES_IMPORT',          'Import');
define('RM_RATES_DISCARD',         'Discard');
define('RM_RATES_PENDING',         'Ready to import: %s');
define('RM_RATES_PENDING_DESC',    'The file holds %d price bands, effective from %s.');
define('RM_RATES_PENDING_REPLACES','It replaces the %d bands already stored for that date.');
define('RM_RATES_CHECK_FAILED',    '%s cannot be imported: %d problems');
define('RM_RATES_MORE_ERRORS',     'and %d more.');
define('RM_RATES_STORED',          'Stored Price Lists');
define('RM_RATES_NONE',            'No price list has been imported.');
define('RM_RATES_IMPORTED',        '%d price bands imported, effective from %s.');
define('RM_RATES_DELETED',         'The price list effective from %s was deleted.');
define('RM_RATES_DELETE_CONFIRM',  'Delete the price list effective from %s?');
define('RM_RATES_AUDIT_IMPORT',    'Royal Mail price list - import');
define('RM_RATES_AUDIT_DELETE',    'Royal Mail price list - delete');
define('RM_RATES_TEXT_CARRIER',    'Carrier');
define('RM_RATES_TEXT_SERVICE',    'Service');
define('RM_RATES_TEXT_CHANNEL',    'Price Channel');
define('RM_RATES_TEXT_ROWS',       'Bands');
define('RM_RATES_TEXT_CONFIRM',    'To Confirm');
define('RM_RATES_TEXT_EFFECTIVE',  'Effective From');
define('RM_RATES_TEXT_STATUS',     'Status');
define('RM_RATES_STATUS_CURRENT',  'In use');
define('RM_RATES_STATUS_FUTURE',   'Not yet in effect');
define('RM_RATES_STATUS_PAST',     'Superseded');
define('RM_RATES_ERR_READ',        'The rate file could not be read.');
define('RM_RATES_ERR_EMPTY',       'The rate file holds no price bands.');
define('RM_RATES_ERR_NO_PENDING',  'There is no checked rate file to import. Choose the file again.');
define('RM_RATES_ERR_COLUMNS',     'The rate file is missing these columns: %s.');
define('RM_RATES_ERR_DATES',       'A rate file must have one effective_from date; this one has %s.');
define('RM_RATES_ERR_LONG',        'Line %d: %s is longer than %d characters.');
define('RM_RATES_ERR_BLANK',       'Line %d: %s is blank.');
define('RM_RATES_ERR_VALUE',       'Line %d: %s cannot be "%s".');
define('RM_RATES_ERR_NUMBER',      'Line %d: %s "%s" is not a valid number.');
define('RM_RATES_ERR_WEIGHTS',     'Line %d: weight_from_g must be less than weight_to_g.');
define('RM_RATES_ERR_PRICE',       'Line %d: the price must be more than 0.');
define('RM_RATES_ERR_INCREMENT',   'Line %d: an increment row needs increment_g.');
define('RM_RATES_ERR_VAT',         'Line %d: %s is %s, but the price and VAT treatment give %s.');
define('RM_RATES_ERR_OVERLAP',     'Overlapping bands for %s (%s, %s, %s): %d-%d g and %d-%d g.');
// Price list page - country zones
define('RM_ZONES_HEADING',       'Country Zones');
define('RM_ZONES_DESC',          'Prices are set by zone, so each destination is priced through its Royal Mail and Parcelforce zone. %d countries have zones; %d are not settled by the published zone lists and should be checked against royalmail.com and parcelforce.com.');
define('RM_ZONES_EDIT_DESC',     'To correct a zone, download the list, change it in a spreadsheet and import it again. Importing replaces the whole list.');
define('RM_ZONES_DOWNLOAD',      'Download Zones');
define('RM_ZONES_IMPORT',        'Import Zones');
define('RM_ZONES_RESET',         'Restore Supplied Zones');
define('RM_ZONES_RESET_CONFIRM', 'Replace the zones held with the list supplied with FreeBooks?');
define('RM_ZONES_IMPORTED',      'Zones stored for %d countries.');
define('RM_ZONES_CHECK_FAILED',  'The zone file was not imported: %d problems');
define('RM_ZONES_AUDIT_IMPORT',  'Royal Mail zones - import');
define('RM_ZONES_AUDIT_RESET',   'Royal Mail zones - restore');
define('RM_ZONES_TEXT_COUNTRY',  'Country');
define('RM_ZONES_TEXT_RM',       'Royal Mail Zone');
define('RM_ZONES_TEXT_PF',       'Parcelforce Zone');
define('RM_ZONES_TEXT_NOTES',    'Notes');
define('RM_ZONES_ERR_READ',      'The zone file could not be read.');
define('RM_ZONES_ERR_EMPTY',     'The zone file holds no countries.');
define('RM_ZONES_ERR_COLUMNS',   'The zone file is missing these columns: %s.');
define('RM_ZONES_ERR_COUNTRY',   'Line %d: "%s" is not a country code FreeBooks knows.');
define('RM_ZONES_ERR_DUPLICATE', 'Line %d: %s appears more than once.');
define('RM_ZONES_ERR_ZONE',      'Line %d: %s cannot be "%s".');
// Shipping estimator
define('RM_QUOTE_NO_PRICE_LIST', 'Royal Mail: no price list is in effect, so no Royal Mail or Parcelforce prices can be given. Import one from the Royal Mail properties (Price List).');
define('RM_QUOTE_NO_ZONE',       'Royal Mail: the destination country (%s) has no Royal Mail zone, so no prices can be given.');
define('RM_QUOTE_NO_GBP',        'Royal Mail: the price list is in GBP, and GBP is not set up as a currency, so no prices can be given.');
define('RM_QUOTE_TO_CONFIRM',    'price to confirm');
?>
