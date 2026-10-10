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
//  Path: /modules/xero_sync/language/en_us/language.php
//

// Line descriptions written to Xero
define('XERO_SYNC_LINE_FREIGHT',        'Freight');
define('XERO_SYNC_LINE_DISCOUNT',       'Discount');
define('XERO_SYNC_LINE_ROUNDING',       'Rounding Adjustment');
define('XERO_SYNC_LINE_NO_DESCRIPTION', '(no description)');

// Warnings shown against a document before it is exported or uploaded
define('XERO_SYNC_WARN_NO_COUNTRY',       'Could not determine billing/shipping country for this record; domestic/foreign account mapping defaulted to domestic.');
define('XERO_SYNC_WARN_DISCOUNT_MIXED',   'Discount left untaxed: product lines use more than one tax rate, so it can\'t be apportioned correctly across them.');
define('XERO_SYNC_WARN_TOTAL_MISMATCH',   'Total mismatch of %s exceeds tolerance %s — rounding line added anyway; this record needs manual review.');
define('XERO_SYNC_WARN_NO_PAYMENT_REF',   'No payment reference found yet for this invoice (unpaid, or paid with no reference recorded) — Reference field falls back to PO Number.');
define('XERO_SYNC_WARN_BAD_DATE',         'Could not parse date \'%s\' to compute due date; using invoice date.');

// Upload errors
define('XERO_SYNC_ERR_TAX_RATE_NOT_FOUND', 'Xero tax rate \'%s\' was not found in this organisation\'s Tax Rates — check that the tax type mapping and the No VAT name match a Xero tax rate name exactly.');
