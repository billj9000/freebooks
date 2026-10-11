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

// Connection to QuickBooks, on the settings page
define('QUICKBOOKS_SYNC_HEAD_CONNECTION',       'QuickBooks Connection');
define('QUICKBOOKS_SYNC_LBL_ENVIRONMENT',       'Environment');
define('QUICKBOOKS_SYNC_OPT_SANDBOX',           'Sandbox');
define('QUICKBOOKS_SYNC_OPT_PRODUCTION',        'Production');
define('QUICKBOOKS_SYNC_LBL_CLIENT_ID',         'Client id');
define('QUICKBOOKS_SYNC_LBL_CLIENT_SECRET',     'Client secret');
define('QUICKBOOKS_SYNC_HELP_CLIENT',           'From the app registered for FreeBooks in Intuit\'s developer portal, under Keys and credentials. Sandbox keys connect only to sandbox companies, and production keys only to real ones.');
define('QUICKBOOKS_SYNC_SECRET_KEPT',           'Saved; leave blank to keep it');
define('QUICKBOOKS_SYNC_LBL_CLEAR_SECRET',      'Remove the saved client secret');
define('QUICKBOOKS_SYNC_LBL_REDIRECT',          'Redirect URI');
define('QUICKBOOKS_SYNC_HELP_REDIRECT',         'Add this address to the app\'s redirect URIs in Intuit\'s developer portal, exactly as shown.');
define('QUICKBOOKS_SYNC_CONNECTED',             'Connected to %s (%s) since %s.');
define('QUICKBOOKS_SYNC_LAPSES',                'If QuickBooks Sync is not used before %s, the connection lapses and must be made again.');
define('QUICKBOOKS_SYNC_NOT_CONNECTED',         'Not connected.');
define('QUICKBOOKS_SYNC_BTN_CONNECT',           'Connect to QuickBooks');
define('QUICKBOOKS_SYNC_BTN_RECONNECT',         'Connect again');
define('QUICKBOOKS_SYNC_BTN_DISCONNECT',        'Disconnect');
define('QUICKBOOKS_SYNC_CONFIRM_DISCONNECT',    'Disconnect from QuickBooks? Nothing can be sent until it is connected again.');
define('QUICKBOOKS_SYNC_DISCONNECTED',          'Disconnected from QuickBooks.');
define('QUICKBOOKS_SYNC_CONNECTED_TO',          'Connected to the QuickBooks company %s.');
define('QUICKBOOKS_SYNC_CONNECTED_NO_NAME',     'Connected to QuickBooks, but the company\'s name could not be read: %s');
define('QUICKBOOKS_SYNC_QB_LISTS_FAILED',       'QuickBooks\' products, accounts and VAT codes could not be read: %s');

// Connection errors
define('QUICKBOOKS_SYNC_ERR_NO_CLIENT',         'Enter the Intuit app\'s client id and client secret first.');
define('QUICKBOOKS_SYNC_ERR_STATE',             'The answer from QuickBooks did not match a connection started here, so it was ignored. Press Connect to QuickBooks again.');
define('QUICKBOOKS_SYNC_ERR_NO_COMPANY',        'The answer from QuickBooks did not name a company.');
define('QUICKBOOKS_SYNC_ERR_TOKEN',             'Intuit refused the token request (HTTP %d: %s).');
define('QUICKBOOKS_SYNC_ERR_QB_HTTP',           'QuickBooks answered with HTTP status %d.');
define('QUICKBOOKS_SYNC_ERR_QB_DENIED',         'QuickBooks did not grant access: %s');
define('QUICKBOOKS_SYNC_ERR_NOT_CONNECTED',     'QuickBooks Sync is not connected to QuickBooks. Connect it from the QuickBooks Sync settings.');
define('QUICKBOOKS_SYNC_ERR_EXPIRED',           'The connection to QuickBooks has lapsed or was revoked. Connect again from the QuickBooks Sync settings.');

// Export page
define('QUICKBOOKS_SYNC_EXPORT_TITLE',          'QuickBooks Sync');
define('QUICKBOOKS_SYNC_LBL_FROM',              'From');
define('QUICKBOOKS_SYNC_LBL_TO',                'To');
define('QUICKBOOKS_SYNC_LBL_CREDIT_MEMOS',      'Include credit memos');
define('QUICKBOOKS_SYNC_BTN_PREVIEW',           'Preview');
define('QUICKBOOKS_SYNC_BTN_INVOICES',          'Download invoices');
define('QUICKBOOKS_SYNC_BTN_CREDITS',           'Download credit memos');
define('QUICKBOOKS_SYNC_COL_TYPE',              'Type');
define('QUICKBOOKS_SYNC_COL_NUMBER',            'Number');
define('QUICKBOOKS_SYNC_COL_DATE',              'Date');
define('QUICKBOOKS_SYNC_COL_DOC_CUSTOMER',     'Customer');
define('QUICKBOOKS_SYNC_COL_COUNTRY',           'Country');
define('QUICKBOOKS_SYNC_COL_FB_TOTAL',          'FreeBooks total');
define('QUICKBOOKS_SYNC_COL_VAT',               'VAT');
define('QUICKBOOKS_SYNC_COL_QB_TOTAL',          'QuickBooks total');
define('QUICKBOOKS_SYNC_COL_ROUNDING',          'Rounding');
define('QUICKBOOKS_SYNC_COL_STATUS',            'Status');
define('QUICKBOOKS_SYNC_TYPE_INVOICE',          'Invoice');
define('QUICKBOOKS_SYNC_TYPE_CREDIT',           'Credit memo');
define('QUICKBOOKS_SYNC_DOMESTIC',              '%s (domestic)');
define('QUICKBOOKS_SYNC_FOREIGN',               '%s (foreign)');
define('QUICKBOOKS_SYNC_STATUS_OK',             'OK');
define('QUICKBOOKS_SYNC_STATUS_ACCOUNTS',       'Unmapped accounts: %s');
define('QUICKBOOKS_SYNC_STATUS_TAX',            'Unmapped tax rates: %s');
define('QUICKBOOKS_SYNC_STATUS_ROUNDED',        'Rounded %s');
define('QUICKBOOKS_SYNC_SUMMARY',               '%d documents, %d to check.');
define('QUICKBOOKS_SYNC_PARTS_INVOICES',        'The invoices download as %d files in a ZIP, because QuickBooks imports at most 100 documents at a time.');
define('QUICKBOOKS_SYNC_PARTS_CREDITS',         'The credit memos download as %d files in a ZIP, because QuickBooks imports at most 100 documents at a time.');
define('QUICKBOOKS_SYNC_NO_DOCUMENTS',          'No invoices or credit memos are dated in that range.');
define('QUICKBOOKS_SYNC_NO_INVOICES',           'No invoices that can be imported are dated in that range.');
define('QUICKBOOKS_SYNC_NO_CREDITS',            'No credit memos that can be imported are dated in that range.');
define('QUICKBOOKS_SYNC_ERR_DATES',             'Enter a From and a To date, with From no later than To.');
define('QUICKBOOKS_SYNC_NOT_SET_UP',            'QuickBooks Sync is not set up yet: choose a default item and a rounding item in its settings.');
define('QUICKBOOKS_SYNC_SETTINGS_LINK',         'Open the settings');

// Sending to QuickBooks, on the export page
define('QUICKBOOKS_SYNC_COL_QB',                'In QuickBooks');
define('QUICKBOOKS_SYNC_BTN_SEND',              'Send ticked documents to QuickBooks');
define('QUICKBOOKS_SYNC_SEND_NOT_CONNECTED',    'To send documents to QuickBooks, connect to QuickBooks in the QuickBooks Sync settings.');
define('QUICKBOOKS_SYNC_SEND_NEEDS_EDIT',       'Sending documents to QuickBooks needs edit permission on QuickBooks Sync.');
define('QUICKBOOKS_SYNC_SEND_NONE',             'Tick at least one document to send.');
define('QUICKBOOKS_SYNC_SEND_PROGRESS',         'Sending %d of %d...');
define('QUICKBOOKS_SYNC_SEND_DONE',             'Finished: %d sent, %d not sent.');
define('QUICKBOOKS_SYNC_SEND_WAITING',          'QuickBooks allows only so many requests a minute; waiting %d seconds before going on (%d of %d done)...');
define('QUICKBOOKS_SYNC_X_NOT_SENT',            'Not sent');
define('QUICKBOOKS_SYNC_X_SENDING',             'Being sent');
define('QUICKBOOKS_SYNC_X_SENT',                'Sent %s');
define('QUICKBOOKS_SYNC_X_CHANGED',             'Changed since it was sent');
define('QUICKBOOKS_SYNC_X_RELEASED',            'Released');
define('QUICKBOOKS_SYNC_X_FAILED',              'Not sent: %s');
define('QUICKBOOKS_SYNC_X_HELD',                'QuickBooks holds an earlier copy, which the next send updates.');
define('QUICKBOOKS_SYNC_BTN_RELEASE',           'Release');
define('QUICKBOOKS_SYNC_BTN_FORGET',            'Unlink from deleted QuickBooks copy');
define('QUICKBOOKS_SYNC_CONFIRM_RELEASE',       'Release this document? It can then be changed in FreeBooks, and sending it again updates the copy in QuickBooks.');
define('QUICKBOOKS_SYNC_CONFIRM_FORGET',        'Use this only after deleting the old copy in QuickBooks yourself: FreeBooks deletes nothing there. QuickBooks refuses a document number already in use, even by a voided document, so the next send makes a new document only once the old one is deleted. Unlink now?');
define('QUICKBOOKS_SYNC_ACTION_STALE',          'Nothing changed: the document is no longer in a state that allows this.');

// Errors when a document is sent
define('QUICKBOOKS_SYNC_ERR_NOT_A_DOCUMENT',    'This is not a sales invoice or credit memo.');
define('QUICKBOOKS_SYNC_ERR_REJECTED',          'QuickBooks refused the document without saying why.');
define('QUICKBOOKS_SYNC_ERR_PAID',              'QuickBooks has a payment or credit applied to its copy of this document, so it is not changed. Correct the sale with a credit memo, or remove the payment in QuickBooks first.');

// The document lock, on the invoice and credit memo page
define('QUICKBOOKS_SYNC_BADGE',                 'In QuickBooks');
define('QUICKBOOKS_SYNC_LOCK_SAVE',             'This document was sent to QuickBooks on %s, and this change would alter what QuickBooks holds or the tax it rests on: the customer, an address, the number, the date, the reference, the currency or the lines. Release it on the QuickBooks Sync page to change it.');
define('QUICKBOOKS_SYNC_LOCK_DELETE',           'This document was sent to QuickBooks on %s, so it cannot be deleted. Release it on the QuickBooks Sync page first.');

// Line descriptions written to QuickBooks
define('QUICKBOOKS_SYNC_LINE_FREIGHT',        'Freight');
define('QUICKBOOKS_SYNC_LINE_ROUNDING',       'Rounding Adjustment');
define('QUICKBOOKS_SYNC_LINE_NO_DESCRIPTION', '(no description)');
define('QUICKBOOKS_SYNC_LINE_AFTER_DISCOUNT', '%s (after discount)');
define('QUICKBOOKS_SYNC_LINE_ROUNDED',        '%s (rounded)');
define('QUICKBOOKS_SYNC_LINE_DISCOUNT_ROUNDED', '%s (after discount, rounded)');

// Warnings shown against a document before it is exported or sent
define('QUICKBOOKS_SYNC_WARN_NO_COUNTRY',     'Neither address has a country, so the domestic items are used.');
define('QUICKBOOKS_SYNC_WARN_CODE_RATES',     'Two FreeBooks tax rates with different percentages map to the QuickBooks tax code \'%s\'.');
define('QUICKBOOKS_SYNC_WARN_TAX_MISMATCH',   'The VAT worked out from the lines differs from the VAT FreeBooks saved by %s, more than the tolerance of %s. Check this document.');
define('QUICKBOOKS_SYNC_WARN_TOTAL_MISMATCH', 'The total differs from the FreeBooks total by %s, more than the tolerance of %s. A rounding line is added; check this document.');
define('QUICKBOOKS_SYNC_WARN_NO_PAYMENT_REF', 'No payment reference has been recorded for this invoice yet, so its PO number is sent instead.');
define('QUICKBOOKS_SYNC_WARN_NUMBER_LONG',    'The document number is longer than QuickBooks allows (%d characters).');
define('QUICKBOOKS_SYNC_FILE_NEGATIVE',      'Not in the import file: QuickBooks imports take no negative amounts. Send it through the connection instead.');
define('QUICKBOOKS_SYNC_FILE_NUMBER_LONG',   'Not in the import file: the document number is longer than QuickBooks allows (%d characters).');
define('QUICKBOOKS_SYNC_WARN_BAD_DATE',       'The date \'%s\' could not be read, so the due date is the document date.');

// Errors in building a document's API body
define('QUICKBOOKS_SYNC_ERR_NUMBER_LONG',        'The document number \'%s\' is longer than QuickBooks allows (%d characters).');
define('QUICKBOOKS_SYNC_ERR_ITEM_NOT_FOUND',     'QuickBooks has no product or service named \'%s\'. Check the item mapping.');
define('QUICKBOOKS_SYNC_ERR_ACCOUNT_NOT_FOUND',  'QuickBooks has no account named \'%s\'. Check the discount account.');
define('QUICKBOOKS_SYNC_ERR_TAX_CODE_NOT_FOUND', 'QuickBooks has no VAT code named \'%s\'. Check the tax code mapping and the code for untaxed lines.');
define('QUICKBOOKS_SYNC_ERR_TAX_CODE_NO_RATE',   'The QuickBooks VAT code \'%s\' has no sales rate, so it cannot carry VAT.');
