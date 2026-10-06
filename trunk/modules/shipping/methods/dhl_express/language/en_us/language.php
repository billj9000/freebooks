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
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_READY_DESC',    'Collections: the time parcels are usually ready (HH:MM), offered when booking a collection.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_CLOSE_DESC',    'Collections: the latest time the courier can collect (HH:MM), offered when booking a collection.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_LOCATION_DESC', 'Collections: where the courier collects from, e.g. Reception (up to 80 characters).');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_CONTACT_DESC',  'Collections: the contact name DHL is given for a collection. Blank uses the receivables contact name on the My Company tab.');
define('MODULE_SHIPPING_DHL_EXPRESS_PICKUP_PHONE_DESC',    'Collections: the telephone number DHL calls about a collection. Blank uses the company telephone number.');
// Error messages
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_RATES', 'DHL Express: Rate query failed - ');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL', 'DHL Express: Label creation failed - ');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY', 'DHL Express: API credentials not configured.');
define('MODULE_SHIPPING_DHL_EXPRESS_ERROR_PICKUP', 'DHL Express: Collection request failed - ');
// Label form
define('DHL_CUSTOMS_SECTION',     'Customs / Export Information');
define('DHL_TEXT_DESCRIPTION',    'Goods Description');
define('DHL_TEXT_DECLARED_VALUE', 'Declared Value');
define('DHL_TEXT_HS_CODE',        'HS / Commodity Code');
define('DHL_TEXT_ORIGIN_COUNTRY', 'Country of Origin');
define('DHL_TEXT_EXPORT_REASON',  'Reason for Export');
define('DHL_TEXT_DELIVERY_TERMS', 'Delivery Terms');
define('DHL_TERMS_DAP',           'Duty unpaid (DAP) - the recipient pays');
define('DHL_TERMS_DDP',           'Duty paid (DDP) - billed to our DHL account');
define('DHL_TEXT_IS_CUSTOMS',     'Customs Declarable');
// Line items table
define('DHL_LINE_ITEMS_HEADER',  'Shipment Contents (Customs Declaration)');
define('DHL_TEXT_UNIT_WEIGHT',   'Unit Weight');
define('DHL_TEXT_UNIT_VALUE',    'Unit Value');
define('DHL_TEXT_SAVE_TO_INV',   'Save descriptions to inventory');
define('DHL_ITEMS_SAVED',        'Item descriptions saved to inventory.');
define('DHL_TEXT_CUSTOM_PACKAGE', 'Custom');
// Collections
define('DHL_PICKUP_TITLE',          'DHL Collection');
define('DHL_PICKUP_BOOK_BTN',       'Book collection');
define('DHL_PICKUP_BOOKED_HEADING', 'Collections booked');
define('DHL_PICKUP_NEW_HEADING',    'Book a collection');
define('DHL_PICKUP_NONE',           'No collection booked for this day.');
define('DHL_PICKUP_ADDRESS',        'Collection address');
define('DHL_PICKUP_ADDRESS_NOTE',   'Collections are booked at the company address, which is changed on the My Company tab of the company settings.');
define('DHL_PICKUP_DATE',           'Collection date');
define('DHL_PICKUP_READY',          'Ready from');
define('DHL_PICKUP_CLOSE',          'Latest collection');
define('DHL_PICKUP_LOCATION',       'Collect from');
define('DHL_PICKUP_LOCATION_TYPE',  'Premises');
define('DHL_PICKUP_BUSINESS',       'Business');
define('DHL_PICKUP_RESIDENCE',      'Residence');
define('DHL_PICKUP_CONTACT',        'Contact name');
define('DHL_PICKUP_PHONE',          'Telephone');
define('DHL_PICKUP_INSTRUCTIONS',   'Notes to the courier (up to 4 lines)');
define('DHL_PICKUP_SHIPMENTS',      'Shipments to collect');
define('DHL_PICKUP_NO_SHIPMENTS',   'No DHL labels were printed for this day.');
define('DHL_PICKUP_PARCELS',        'Parcels');
define('DHL_PICKUP_WEIGHT_KG',      'Weight (kg)');
define('DHL_PICKUP_SIZE_CM',        'L x W x H (cm)');
define('DHL_PICKUP_ENTER_FIGURES',  'No parcel figures were stored with this label; enter the number of parcels, the weight of each and their size.');
define('DHL_PICKUP_IN_BOOKING',     'Already in collection %s');
define('DHL_PICKUP_ELSEWHERE',      'Sent from postcode %s, not from here');
define('DHL_PICKUP_CONFIRMATION',   'Confirmation');
define('DHL_PICKUP_STATUS_BOOKED',  'Booked');
define('DHL_PICKUP_STATUS_CANCELLED', 'Cancelled');
define('DHL_PICKUP_CANCEL_BTN',     'Cancel collection');
define('DHL_PICKUP_CANCEL_REASON',  'Reason');
define('DHL_PICKUP_REASON_NOT_NEEDED', 'Collection no longer needed');
define('DHL_PICKUP_REASON_ERROR',   'Booked in error');
define('DHL_PICKUP_REASON_REBOOK',  'Rebooking with changes');
define('DHL_PICKUP_CANCEL_CONFIRM', 'Cancel this collection with DHL? If DHL has merged it with another collection from this address, that collection is cancelled too.');
define('DHL_PICKUP_BOOKED',         'Collection booked. DHL confirmation: %s');
define('DHL_PICKUP_WARNING',        'DHL: %s');
define('DHL_PICKUP_CANCELLED',      'Collection %s cancelled.');
define('DHL_PICKUP_LOG_BOOKED',     'DHL collection booked');
define('DHL_PICKUP_LOG_CANCELLED',  'DHL collection cancelled');
define('DHL_PICKUP_ERR_DATE',       'The collection date must be today or within the next 10 days.');
define('DHL_PICKUP_ERR_TIME',       'Enter the times as HH:MM, with the latest collection after the ready time.');
define('DHL_PICKUP_ERR_CONTACT',    'A contact name and telephone number are required.');
define('DHL_PICKUP_ERR_TEXT',       'The collection point and each note to the courier can be up to 80 characters.');
define('DHL_PICKUP_ERR_NONE',       'Tick at least one shipment to collect.');
define('DHL_PICKUP_ERR_PARCELS',    'Shipment %s: every parcel needs a weight and a length, width and height.');
?>
