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

define('MODULE_SHIPPING_ROYAL_MAIL_TEXT_TITLE',        'Royal Mail / Parcelforce');
define('MODULE_SHIPPING_ROYAL_MAIL_TITLE',             'Royal Mail Title');
define('MODULE_SHIPPING_ROYAL_MAIL_CLICKDROP_API_KEY', 'Click &amp; Drop API Key');
define('MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_ID',      'RM Shipping API Client ID');
define('MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_SECRET',  'RM Shipping API Client Secret');
define('MODULE_SHIPPING_ROYAL_MAIL_OBA_ACCOUNT',       'OBA Account Number');
define('MODULE_SHIPPING_ROYAL_MAIL_TEST_MODE',         'Mode');
define('MODULE_SHIPPING_ROYAL_MAIL_TYPES',             'Enabled Services');
define('MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER',        'Sort Order');
// Parcelforce configured rates (contract rates, not from API)
define('MODULE_SHIPPING_ROYAL_MAIL_PFX9_RATE',         'Parcelforce Express 9 Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFX10_RATE',        'Parcelforce Express 10 Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFXAM_RATE',        'Parcelforce Express AM Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFX24_RATE',        'Parcelforce Express 24 Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFX48_RATE',        'Parcelforce Express 48 Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFX4L_RATE',        'Parcelforce Express 48L Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFGX_RATE',         'Parcelforce Global Express Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFGP_RATE',         'Parcelforce Global Priority Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFGV_RATE',         'Parcelforce Global Value Rate (£)');
define('MODULE_SHIPPING_ROYAL_MAIL_PFGE_RATE',         'Parcelforce Global Economy Rate (£)');
// Service display names (used in type checkboxes)
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM24',  'RM Tracked 24');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM48',  'RM Tracked 48');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD9', 'RM Special Delivery 9am');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD1', 'RM Special Delivery 1pm');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM1CL', 'RM 1st Class');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM2CL', 'RM 2nd Class');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIT',  'RM International Tracked');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIS',  'RM International Signed');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMITS', 'RM International Tracked &amp; Signed');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIE',  'RM International Economy');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX9',  'Parcelforce Express 9');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX10', 'Parcelforce Express 10');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFXAM', 'Parcelforce Express AM');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX24', 'Parcelforce Express 24');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX48', 'Parcelforce Express 48');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX4L', 'Parcelforce Express 48L');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGX',  'Parcelforce Global Express');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGP',  'Parcelforce Global Priority');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGV',  'Parcelforce Global Value');
define('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGE',  'Parcelforce Global Economy');
// Error messages
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_TOKEN',  'Royal Mail: Failed to obtain API access token.');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_RATES',  'Royal Mail: Rate query failed - ');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL',  'Royal Mail: Label creation failed - ');
define('MODULE_SHIPPING_ROYAL_MAIL_ERROR_NO_KEY', 'Royal Mail: Click &amp; Drop API key not configured.');
// Label form labels
define('RM_CUSTOMS_SECTION',      'Customs Information (international)');
define('RM_TEXT_DESCRIPTION',     'Goods Description');
define('RM_TEXT_DECLARED_VALUE',  'Declared Value');
define('RM_TEXT_HS_CODE',         'HS / Commodity Code');
define('RM_TEXT_ORIGIN_COUNTRY',  'Country of Origin');
define('RM_TEXT_EXPORT_REASON',   'Reason for Export');
?>
