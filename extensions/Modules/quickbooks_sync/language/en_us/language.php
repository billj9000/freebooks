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
//  Path: /modules/quickbooks_sync/language/en_us/language.php
//

// Line descriptions written to QuickBooks
define('QUICKBOOKS_SYNC_LINE_FREIGHT',        'Freight');
define('QUICKBOOKS_SYNC_LINE_ROUNDING',       'Rounding Adjustment');
define('QUICKBOOKS_SYNC_LINE_NO_DESCRIPTION', '(no description)');

// Warnings shown against a document before it is exported or sent
define('QUICKBOOKS_SYNC_WARN_NO_COUNTRY',     'Neither address has a country, so the domestic items are used.');
define('QUICKBOOKS_SYNC_WARN_CODE_RATES',     'Two FreeBooks tax rates with different percentages map to the QuickBooks tax code \'%s\'.');
define('QUICKBOOKS_SYNC_WARN_TAX_MISMATCH',   'The VAT worked out from the lines differs from the VAT FreeBooks saved by %s, more than the tolerance of %s. Check this document.');
define('QUICKBOOKS_SYNC_WARN_TOTAL_MISMATCH', 'The total differs from the FreeBooks total by %s, more than the tolerance of %s. A rounding line is added; check this document.');
define('QUICKBOOKS_SYNC_WARN_NO_PAYMENT_REF', 'No payment reference has been recorded for this invoice yet, so its PO number is sent instead.');
define('QUICKBOOKS_SYNC_WARN_NUMBER_LONG',    'The document number is longer than QuickBooks allows (%d characters).');
define('QUICKBOOKS_SYNC_WARN_BAD_DATE',       'The date \'%s\' could not be read, so the due date is the document date.');

// Errors when a document is sent
define('QUICKBOOKS_SYNC_ERR_NUMBER_LONG',        'The document number \'%s\' is longer than QuickBooks allows (%d characters).');
define('QUICKBOOKS_SYNC_ERR_ITEM_NOT_FOUND',     'QuickBooks has no product or service named \'%s\'. Check the item mapping.');
define('QUICKBOOKS_SYNC_ERR_ACCOUNT_NOT_FOUND',  'QuickBooks has no account named \'%s\'. Check the discount account.');
define('QUICKBOOKS_SYNC_ERR_TAX_CODE_NOT_FOUND', 'QuickBooks has no VAT code named \'%s\'. Check the tax code mapping and the code for untaxed lines.');
define('QUICKBOOKS_SYNC_ERR_TAX_CODE_NO_RATE',   'The QuickBooks VAT code \'%s\' has no sales rate, so it cannot carry VAT.');
