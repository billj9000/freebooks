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

// Settings page
define('QUICKBOOKS_SYNC_PAGE_TITLE',            'QuickBooks Sync Settings');
define('QUICKBOOKS_SYNC_HEAD_GENERAL',          'Documents');
define('QUICKBOOKS_SYNC_HEAD_QUICKBOOKS',       'QuickBooks Defaults');
define('QUICKBOOKS_SYNC_HEAD_ITEMS',            'Item Mapping');
define('QUICKBOOKS_SYNC_HEAD_TAX',              'VAT Code Mapping');
define('QUICKBOOKS_SYNC_HEAD_CUSTOMERS',        'Customer Names');
define('QUICKBOOKS_SYNC_LBL_HOME_COUNTRY',      'Home country');
define('QUICKBOOKS_SYNC_HELP_HOME_COUNTRY',     'A document to another country uses the foreign items below. Leave blank to use the domestic items for every document.');
define('QUICKBOOKS_SYNC_LBL_COUNTRY_SOURCE',    'Country taken from');
define('QUICKBOOKS_SYNC_OPT_SHIPPING',          'Shipping address');
define('QUICKBOOKS_SYNC_OPT_BILLING',           'Billing address');
define('QUICKBOOKS_SYNC_HELP_COUNTRY_SOURCE',   'When that address has no country, the other address is used.');
define('QUICKBOOKS_SYNC_LBL_DUE_DAYS',          'Days until due');
define('QUICKBOOKS_SYNC_LBL_TOLERANCE',         'Rounding tolerance');
define('QUICKBOOKS_SYNC_HELP_TOLERANCE',        'A penny difference between the VAT worked out per code and the VAT FreeBooks saved is taken into the largest VAT line; a difference in the total goes on a rounding line. A difference larger than this is flagged for review.');
define('QUICKBOOKS_SYNC_LBL_PAYMENT_REF',       'Use the payment reference as an invoice\'s reference');
define('QUICKBOOKS_SYNC_HELP_PAYMENT_REF',      'Sends the reference of the receipt that paid the invoice, such as a transaction id, in place of its PO number. An unpaid invoice sends its PO number.');
define('QUICKBOOKS_SYNC_LBL_DEFAULT_ITEM',      'Default item');
define('QUICKBOOKS_SYNC_HELP_DEFAULT_ITEM',     'The QuickBooks product or service used for any FreeBooks account not mapped below. Such documents are flagged.');
define('QUICKBOOKS_SYNC_LBL_ROUNDING_ITEM',     'Rounding item');
define('QUICKBOOKS_SYNC_LBL_DISCOUNT_ACCOUNT',  'Discount account');
define('QUICKBOOKS_SYNC_HELP_DISCOUNT_ACCOUNT', 'The QuickBooks account a discount is posted to. Leave blank to use QuickBooks\' own discount account.');
define('QUICKBOOKS_SYNC_LBL_NO_VAT_CODE',       'VAT code for untaxed lines');
define('QUICKBOOKS_SYNC_HELP_NO_VAT_CODE',      'The name of the QuickBooks VAT code written on a line with no VAT, exactly as QuickBooks shows it.');
define('QUICKBOOKS_SYNC_HELP_ITEMS',            'The accounts your sales invoices and credit memos post products and freight to. Enter the name of the QuickBooks product or service each is sold as; that item decides the QuickBooks income account. Leave the foreign item blank to use the same item for every country.');
define('QUICKBOOKS_SYNC_LBL_SHOW_ALL',          'Show every account');
define('QUICKBOOKS_SYNC_COL_ACCOUNT',           'FreeBooks account');
define('QUICKBOOKS_SYNC_COL_DOMESTIC',          'QuickBooks item');
define('QUICKBOOKS_SYNC_COL_FOREIGN',           'QuickBooks item, foreign');
define('QUICKBOOKS_SYNC_HELP_TAX',              'Enter the name of the matching QuickBooks VAT code exactly as QuickBooks shows it, such as 20.0% S. The VAT FreeBooks charged is sent with the document, so keep the two rates the same.');
define('QUICKBOOKS_SYNC_COL_TAX_RATE',          'FreeBooks tax rate');
define('QUICKBOOKS_SYNC_COL_QB_TAX',            'QuickBooks VAT code');
define('QUICKBOOKS_SYNC_HELP_CUSTOMERS',        'Only needed where a customer has a different name in QuickBooks.');
define('QUICKBOOKS_SYNC_COL_CUSTOMER',          'FreeBooks customer name');
define('QUICKBOOKS_SYNC_COL_QB_CUSTOMER',       'QuickBooks customer name');
define('QUICKBOOKS_SYNC_BTN_ADD_CUSTOMER',      'Add a name');
define('QUICKBOOKS_SYNC_NOT_IN_CHART',          '(no longer in the chart)');
define('QUICKBOOKS_SYNC_NOT_A_RATE',            '(no longer a tax rate)');
define('QUICKBOOKS_SYNC_NONE_USED',             'No sales have been posted yet. Tick Show every account to map accounts in advance.');
define('QUICKBOOKS_SYNC_NO_TAX_RATES',          'There are no customer tax rates.');
define('QUICKBOOKS_SYNC_SAVED',                 'The QuickBooks settings were saved.');
define('QUICKBOOKS_SYNC_ERR_DUE_DAYS',          'Days until due must be a whole number from 0 to 365.');
define('QUICKBOOKS_SYNC_ERR_TOLERANCE',         'The rounding tolerance must be a number from 0 to 100.');

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
