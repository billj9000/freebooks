<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |

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
//  Path: /modules/shipping/language/en_us/language.php
//

// Headings 
define('HEADING_TITLE_MODULES_SHIPPING','Shipping Services');
define('SHIPPING_HEADING_SHIP_MGR','Shipping Module Manager');
define('TEXT_SHIPPING_MODULES_AVAILABLE','Shipping Methods Available');
// General Defines
define('TEXT_PRODUCTION','Production');
if (!defined('TEXT_TEST')) define('TEXT_TEST','Test'); // phreedom's language.php also defines this, loaded first by every entry point that uses both
define('TEXT_PDF','PDF');
define('TEXT_GIF','GIF');
define('TEXT_THERMAL','Thermal');
define('TEXT_PAGKAGE_DEFAULTS','Package Defaults');
define('TEXT_SHIPMENT_DEFAULTS','Shipment Defaults');
define('TEXT_SHIPMENTS_ON','Shipments On: ');
define('TEXT_REMOVE_MESSAGE','Are you sure you want to remove this shipping method?');
define('SHIPPING_BUTTON_CREATE_LOG_ENTRY','Create a Shipment Entry');
define('SHIPPING_SET_BY_SYSTEM',' (Set by the system)');
define('SHIPPING_POPUP_WINDOW_TITLE','Shipping Rate Estimator');
define('SHIPPING_POPUP_WINDOW_RATE_TITLE','Shipping Estimator - Rates');
define('SHIPPING_ESTIMATOR_OPTIONS','Shipping Estimator - Shipment Options');
define('SHIPPING_TEXT_SHIPPER','Shipper:');
define('SHIPPING_TEXT_SHIPMENT_DATE','Shipment Date');
define('SHIPPING_TEXT_SHIP_FROM_CITY','Ship From City: ');
define('SHIPPING_TEXT_SHIP_TO_CITY','Ship To City: ');
define('SHIPPING_RESIDENTIAL_ADDRESS','Residential Address');
define('SHIPPING_TEXT_SHIP_FROM_STATE','Ship From State: ');
define('SHIPPING_TEXT_SHIP_TO_STATE','Ship To State: ');
define('SHIPPING_TEXT_SHIP_FROM_ZIP','Ship From Postal Code: ');
define('SHIPPING_TEXT_SHIP_TO_ZIP','Ship To Postal Code: ');
define('SHIPPING_TEXT_SHIP_FROM_COUNTRY','Ship From Country: ');
define('SHIPPING_TEXT_SHIP_TO_COUNTRY','Ship To Country: ');
define('SHIPPING_TEXT_SWAP_ADDRESSES','Swap From / To');
define('SHIPPING_TEXT_PACKAGE_INFORMATION','Package Information');
define('SHIPPING_TEXT_PACKAGE_TYPE','Type of Packaging ');
define('SHIPPING_TEXT_PICKUP_SERVICE','Pickup Service ');
define('SHIPPING_TEXT_DIMENSIONS','Dimensions: ');
define('SHIPPING_ADDITIONAL_HANDLING','Additional Handling Applies (Oversize)');
define('SHIPPING_INSURANCE_AMOUNT','Insurance: Amount ');
define('SHIPPING_SPLIT_LARGE_SHIPMENTS','Split large shipments for small pkg carrier ');
define('SHIPPING_TEXT_PER_BOX',' per box');
define('SHIPPING_TEXT_DELIVERY_CONFIRM','Delivery Confirmation ');
define('SHIPPING_SPECIAL_OPTIONS','Special Options');
define('SHIPPING_SERVICE_TYPE','Service Type');
define('SHIPPING_HANDLING_CHARGE','Handling Charge: Amount ');
define('SHIPPING_COD_AMOUNT','COD: Collect ');
define('SHIPPING_SATURDAY_PICKUP','Saturday Pickup');
define('SHIPPING_SATURDAY_DELIVERY','Saturday Delivery');
define('SHIPPING_HAZARDOUS_MATERIALS','Hazardous Material');
define('SHIPPING_TEXT_DRY_ICE','Dry Ice');
define('SHIPPING_TEXT_RETURN_SERVICES','Return Services ');
define('SHIPPING_TEXT_METHODS','Shipping Methods');
define('SHIPPING_TOTAL_WEIGHT','Total Shipment Weight');
define('SHIPPING_TOTAL_VALUE','Total Shipment Value');
define('SHIPPING_EMAIL_SENDER','E-mail Sender');
define('SHIPPING_EMAIL_RECIPIENT','E-mail Recipient');
define('SHIPPING_EMAIL_SENDER_ADD','Sender E-mail Address');
define('SHIPPING_EMAIL_RECIPIENT_ADD','Recipient E-mail Address');
define('SHIPPING_LABEL_TO_NAME',   'To: Name');
define('SHIPPING_LABEL_TO_EMAIL',  'To: E-mail');
define('SHIPPING_LABEL_FROM_NAME', 'From: Name');
define('SHIPPING_LABEL_FROM_EMAIL','From: E-mail');
define('SHIPPING_LABEL_CC_EMAIL',  'CC:');
define('SHIPPING_LABEL_BCC_EMAIL', 'BCC:');
define('SHIPPING_TEXT_EXCEPTION','Exception');
define('SHIPPING_TEXT_DELIVER','Deliver');
define('SHIPPING_BILL_CHARGES_TO','Bill charges to');
define('SHIPPING_THIRD_PARTY','Recpt/Third Party Acct #');
define('SHIPPING_THIRD_PARTY_ZIP','Third Party Postal Code');
define('SHIPPING_LTL_FREIGHT_CLASS','LTL Freight Class');
define('SHIPPING_NUM_PIECES','Number of Pieces');
define('SHIPPNIG_SUMMARY','Shipment Summary');
define('SHIPPING_SHIPMENT_DETAILS','Shipment Details');
define('SHIPPING_PACKAGE_DETAILS','Package Details');
define('SHIPPING_VOID_SHIPMENT','Void Shipment');

// Shipping Packages page
define('SHPKG_HEADING_TITLE',       'Shipping Packages');
define('SHPKG_TEXT_CODE',           'Code');
define('SHPKG_TEXT_NAME',           'Name');
define('SHPKG_TEXT_DESCRIPTION',    'Description');
define('SHPKG_TEXT_WEIGHT',         'Tare Weight');
define('SHPKG_TEXT_MAX_WEIGHT',     'Max Weight');
define('SHPKG_TEXT_CARRIER',        'Carrier');
define('SHPKG_TEXT_ALL_CARRIERS',   'All Carriers');
define('SHPKG_TEXT_EXTERNAL',       'External');
define('SHPKG_TEXT_INTERNAL',       'Internal');
define('SHPKG_TEXT_EXT_DIMS',       'External Dims (L&times;W&times;H)');
define('SHPKG_TEXT_INT_DIMS',       'Internal Dims (L&times;W&times;H)');
define('SHPKG_TEXT_EXT_LENGTH',     'Ext. Length');
define('SHPKG_TEXT_EXT_WIDTH',      'Ext. Width');
define('SHPKG_TEXT_EXT_HEIGHT',     'Ext. Height');
define('SHPKG_TEXT_INT_LENGTH',     'Int. Length');
define('SHPKG_TEXT_INT_WIDTH',      'Int. Width');
define('SHPKG_TEXT_INT_HEIGHT',     'Int. Height');
define('SHPKG_TEXT_VOL_WEIGHT',     'Vol. Weight');
define('SHPKG_TEXT_COST',           'Cost');
define('SHPKG_TEXT_INACTIVE',       'Inactive');
define('SHPKG_TEXT_SORT_ORDER',     'Sort Order');
define('SHPKG_ERROR_DUP_CODE',      'A package with that code already exists.');
define('SHPKG_ERROR_MISSING_CODE',  'Package code is required.');
define('SHPKG_ERROR_MISSING_NAME',  'Package name is required.');

define('SHIPPING_TEXT_CARRIER','Carrier');
define('SHIPPING_TEXT_SERVICE','Service');
define('SHIPPING_TEXT_FREIGHT_QUOTE','Freight Quote');
define('SHIPPING_TEXT_BOOK_PRICE','Book Price');
define('SHIPPING_TEXT_COST','Cost');
define('SHIPPING_TEXT_NOTES','Notes');
define('SHIPPING_TEXT_PRINT_LABEL','Print Label');
define('SHIPPING_TEXT_CLOSE_DAY','Daily Close');
define('SHIPPING_TEXT_DELETE_LABEL','Delete Shipment');
define('SHIPPING_TEXT_SHIPMENT_ID','Shipment ID');
define('SHIPPING_TEXT_REFERENCE_ID','Reference ID');
define('SHIPPING_TEXT_TRACKING_NUM','Tracking Number');
define('SHIPPING_TEXT_EXPECTED_DATE','Expected Delivery Date');
define('SHIPPING_TEXT_ACTUAL_DATE','Actual Delivery Date');
define('SHIPPING_TEXT_DOWNLOAD','Download Thermal Label');
define('SHIPPING_THERMAL_INST','<br />The file is pre-formatted for thermal label printers. To print the label:<br /><br />
		1. Click the Download button to start the download.<br />
		2. Click on \'Save\' on the confirmation popup to save the file to you local machine.<br />
		3. Copy the file directly to the printer port. (the file must be copied in raw format)');
define('SHIPPING_TEXT_NO_LABEL','No Label Found!');
define('SHIPPING_NO_PACKAGES','There were no packages to ship, either the total quantity or weight was zero.');
define('SHIPPING_DELETE_ERROR','Error - Cannot delete the shipment, not enough information provided.');
define('SHIPPING_CANNOT_DELETE','Error - Cannot delete a shipment whose label was generated prior to today.');
define('SHIPPING_LABEL_DELETED','Label Deleted');
define('SHIPPING_END_OF_DAY','End of Day Close - %s');

define('SHIPPING_ERROR_WEIGHT_ZERO','Shipment weight cannot be zero.');
define('SHIPPING_BOX_PACKER_NO_DIMS','Box dimensions exclude items with no dimensions set (weight included): ');
define('SHIPPING_BOX_PACKER_NOT_FOUND','Items not found in inventory (excluded from estimate): ');
define('SHIPPING_TEXT_WEIGHT_FALLBACK','Used when item dimensions are unavailable');
define('SHIPPING_DELETE_CONFIRM', 'Are you sure you want to delete this shipment?');
define('SHIPPING_CANCEL_CONFIRM', 'This will cancel the shipment at the carrier and delete the record. Are you sure?');
define('SHIPPING_NO_SHIPMENTS', 'There are no shipments from this carrier today!');
define('SHIPPING_ERROR_CONFIGURATION', '<strong>Shipping Configuration errors!</strong>');
define('SHIPPING_BAD_QUOTE_DATE','The Shipment date cannot be before today for a quote. The ship date has been changed to today for quoting purposes.');
// Audit log messages
define('SHIPPING_LOG_LABEL_PRINTED','Label Generated');
// shipping options
define('SHIPPING_1DEAM','1 Day Early a.m.');
define('SHIPPING_1DAM','1 Day a.m.');
define('SHIPPING_1DPM','1 Day p.m.');
define('SHIPPING_1DFRT','1 Day Freight');
define('SHIPPING_2DAM','2 Day a.m.');
define('SHIPPING_2DPM','2 Day p.m.');
define('SHIPPING_2DFRT','2 Day Freight');
define('SHIPPING_3DAM','3 Day a.m.');
define('SHIPPING_3DPM','3 Day');
define('SHIPPING_3DFRT','3 Day Freight');
define('SHIPPING_GND','Ground');
define('SHIPPING_GDR','Ground Residential');
define('SHIPPING_GNDFRT','Ground LTL Freight');
define('SHIPPING_ECOFRT','Economy Freight');
define('SHIPPING_I2DEAM','Worldwide Early Express');
define('SHIPPING_I2DAM','Worldwide Express');
define('SHIPPING_I3D','Worldwide Expedited');
define('SHIPPING_IGND','Ground (Canada)');

// DHL Express product codes (keyed as DHL_{productCode})
define('SHIPPING_DHL_P', 'DHL Express Worldwide');
define('SHIPPING_DHL_D', 'DHL Express Worldwide (Dutiable)');
define('SHIPPING_DHL_I', 'DHL Express Worldwide (Doc)');
define('SHIPPING_DHL_N', 'DHL Domestic Express');
define('SHIPPING_DHL_G', 'DHL Domestic Economy Select');
define('SHIPPING_DHL_Y', 'DHL Express 9:00');
define('SHIPPING_DHL_E', 'DHL Express 9:00 (Dutiable)');
define('SHIPPING_DHL_K', 'DHL Express 9:00 (Regional)');
define('SHIPPING_DHL_T', 'DHL Express 12:00');
define('SHIPPING_DHL_L', 'DHL Express 10:30');
define('SHIPPING_DHL_M', 'DHL Express 10:30 (Dutiable)');
define('SHIPPING_DHL_W', 'DHL Express 12:00 (Dutiable)');
define('SHIPPING_DHL_Q', 'DHL Medical Express');
define('SHIPPING_DHL_C', 'DHL Medical Express (Domestic)');
define('SHIPPING_DHL_H', 'DHL Economy Select');
define('SHIPPING_DHL_8', 'DHL Express Easy');
// Royal Mail domestic — untracked (Click & Drop)
define('SHIPPING_CRL24', 'Royal Mail 24');
define('SHIPPING_CRL48', 'Royal Mail 48');
// Royal Mail domestic — tracked
define('SHIPPING_RM24',  'Tracked 24');
define('SHIPPING_RM48',  'Tracked 48');
define('SHIPPING_RMSD9', 'Special Delivery 9am');
define('SHIPPING_RMSD1', 'Special Delivery 1pm');
define('SHIPPING_RM1CL', 'Signed For 1st Class');
define('SHIPPING_RM2CL', 'Signed For 2nd Class');
// Royal Mail international
define('SHIPPING_RMIT',  'International Business Mail Tracked');
define('SHIPPING_RMIS',  'International Business Mail Signed');
define('SHIPPING_RMITS', 'International Business Mail Tracked & Signed');
define('SHIPPING_RMIE',  'International Business Parcel Standard');
// Parcelforce domestic (legacy module codes)
define('SHIPPING_PFX9',  'Parcelforce Express 9');
define('SHIPPING_PFX10', 'Parcelforce Express 10');
define('SHIPPING_PFXAM', 'Parcelforce Express AM');
define('SHIPPING_PFX24', 'Parcelforce Express 24');
define('SHIPPING_PFX48', 'Parcelforce Express 48');
define('SHIPPING_PFX4L', 'Parcelforce Express 48 Large');
// Parcelforce domestic (Click & Drop)
define('SHIPPING_NDA',   'Parcelforce Express 24');
define('SHIPPING_FE0',   'Parcelforce Express 48');
define('SHIPPING_FEM',   'Parcelforce Express 48 Large');
// Parcelforce international (legacy module codes)
define('SHIPPING_PFGX',  'Parcelforce Global Express');
define('SHIPPING_PFGP',  'Parcelforce Global Priority');
define('SHIPPING_PFGV',  'Parcelforce Global Value');
define('SHIPPING_PFGE',  'Parcelforce Global Economy');
// Parcelforce international (Click & Drop)
define('SHIPPING_ER1',   'Parcelforce Europriority');
define('SHIPPING_ECA',   'Parcelforce GlobalPriority');
define('SHIPPING_SHIP_PACKAGE','Ship a Package');
define('SHIPPING_CREATE_ENTRY','Create a Shipment Entry');
define('SHIPPING_RECON_BILL','Reconcile Bill');
define('SHIPPING_RECP_INFO','Recepient Information');
define('SHIPPING_EMAIL_NOTIFY','Email Notifications');
define('SHIPPING_BILL_DETAIL','Billing Details');
define('SHIPPING_LTL_FREIGHT','LTL Freight');
define('SHIPPING_LTL_CLASS','Freight Class');
define('TEXT_TRACK_CONFIRM','Confirm Delivery');

define('SHIPPING_DAILY','Daily Pickup');
define('SHIPPING_CARRIER','Carrier Customer Counter');
define('SHIPPING_ONE_TIME','Request/One Time Pickup');
define('SHIPPING_ON_CALL','On Call Air');
define('SHIPPING_RETAIL','Suggested Retail Rates');
define('SHIPPING_DROP_BOX','Drop Box/Center');
define('SHIPPING_AIR_SRV','Air Service Center');

// Packing list page
define('PKL_PAGE_TITLE',         'Packing List');
define('PKL_PRINT_LINK',         'Print Packing List');
define('PKL_BTN_PRINT',          'Print');
define('PKL_BTN_CLOSE',          'Close');
define('PKL_TEXT_SHIP_TO',       'Ship To');
define('PKL_TEXT_SHIPMENT_DETAILS', 'Shipment Details');
define('PKL_TEXT_CARRIER',       'Carrier');
define('PKL_TEXT_SERVICE',       'Service');
define('PKL_TEXT_CUSTOMER_REF',  'Customer Ref');
define('PKL_TEXT_PACKAGES',      'Packages');
define('PKL_TEXT_ITEMS',         'Items');
define('PKL_TEXT_BOX',           'Box');
define('PKL_TEXT_SKU',           'SKU');
define('PKL_TEXT_DESCRIPTION',   'Description');
define('PKL_TEXT_QTY',           'Qty');
define('PKL_NO_PACKAGES',        'No items recorded for this order.');
define('PKL_BOX_NO_ITEMS',       'No items recorded for this box.');
define('PKL_ERROR_NO_ORDER',     'No order ID provided.');
define('PKL_ERROR_NOT_FOUND',    'Order not found.');

define('SHIPPING_TEXT_LBS','lbs');
define('SHIPPING_TEXT_KGS','kgs');

define('SHIPPING_TEXT_IN','in');
define('SHIPPING_TEXT_CM','cm');

define('SHIPPING_ENVENLOPE','Envelope/Letter');
define('SHIPPING_CUST_SUPP','Customer Supplied');
define('SHIPPING_TUBE','Carrier Tube');
define('SHIPPING_PAK','Carrier Pak');
define('SHIPPING_BOX','Carrier  Box');
define('SHIPPING_25KG','25kg Box');
define('SHIPPING_10KG','10kg Box');

define('SHIPPING_CASH','Cash');
define('SHIPPING_CHECK','Check');
define('SHIPPING_CASHIERS','Cashier\'s Check');
define('SHIPPING_MO','Money Order');
define('SHIPPING_ANY','Any');

define('SHIPPING_NO_CONF','No delivery confirmation');
define('SHIPPING_NO_SIG_RQD','No Signature Required');
define('SHIPPING_SIG_REQ','Signature Required');
define('SHIPPING_ADULT_SIG','Adult Signature Required');

define('SHIPPING_RET_CARRIER','Carrier Return Label');
define('SHIPPING_RET_LOCAL','Print Local Return Label');
define('SHIPPING_RET_MAILS','Carrier Prints and Mails Return Label');

define('SHIPPING_SENDER','Sender');
define('SHIPPING_RECIPIENT','Recipient');
// Label viewer
define('SHIPPING_LABEL_DOWNLOAD',      'Download');
define('SHIPPING_LABEL_EMAIL',         'Email');
define('SHIPPING_LABEL_EMAIL_SENT',    'Shipping label emailed successfully.');
define('SHIPPING_LABEL_EMAIL_ERROR',   'Failed to send email. Please check your email settings.');
define('SHIPPING_LABEL_EMAIL_SUBJECT', 'Shipping Label');
define('SHIPPING_LABEL_EMAIL_BODY',    'Please find your shipping label attached.');
define('SHIPPING_RECEIPIENT','Receipient');
if (!defined('SHIPPING_THIRD_PARTY')) define('SHIPPING_THIRD_PARTY','Third Party'); // already defined above ('Recpt/Third Party Acct #', which wins) — this file defines it twice
define('SHIPPING_COLLECT','Collect');
?>