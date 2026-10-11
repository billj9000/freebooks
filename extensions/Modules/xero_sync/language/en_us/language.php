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

// Settings page
define('XERO_SYNC_PAGE_TITLE',            'Xero Sync Settings');
define('XERO_SYNC_HEAD_GENERAL',          'Documents');
define('XERO_SYNC_HEAD_XERO',             'Xero Defaults');
define('XERO_SYNC_HEAD_ACCOUNTS',         'Account Mapping');
define('XERO_SYNC_HEAD_TAX',              'Tax Rate Mapping');
define('XERO_SYNC_HEAD_CONTACTS',         'Contact Names');
define('XERO_SYNC_LBL_HOME_COUNTRY',      'Home country');
define('XERO_SYNC_HELP_HOME_COUNTRY',     'A document to another country uses the foreign account codes below. Leave blank to use the domestic codes for every document.');
define('XERO_SYNC_LBL_COUNTRY_SOURCE',    'Country taken from');
define('XERO_SYNC_OPT_SHIPPING',          'Shipping address');
define('XERO_SYNC_OPT_BILLING',           'Billing address');
define('XERO_SYNC_HELP_COUNTRY_SOURCE',   'When that address has no country, the other address is used.');
define('XERO_SYNC_LBL_DUE_DAYS',          'Days until due');
define('XERO_SYNC_LBL_TOLERANCE',         'Rounding tolerance');
define('XERO_SYNC_HELP_TOLERANCE',        'A difference between the FreeBooks total and Xero\'s is posted to the rounding account. A difference larger than this is also flagged for review.');
define('XERO_SYNC_LBL_PAYMENT_REF',       'Use the payment reference as an invoice\'s reference');
define('XERO_SYNC_HELP_PAYMENT_REF',      'Sends the reference of the receipt that paid the invoice, such as a transaction id, in place of its PO number. An unpaid invoice sends its PO number.');
define('XERO_SYNC_LBL_DEFAULT_ACCOUNT',   'Default account code');
define('XERO_SYNC_HELP_DEFAULT_ACCOUNT',  'Used for any FreeBooks account not mapped below. Such documents are flagged.');
define('XERO_SYNC_LBL_ROUNDING_ACCOUNT',  'Rounding account code');
define('XERO_SYNC_LBL_NO_VAT_TYPE',       'Tax rate for untaxed lines');
define('XERO_SYNC_HELP_NO_VAT_TYPE',      'The name of the Xero tax rate written on a line with no tax, exactly as Xero shows it.');
define('XERO_SYNC_HELP_ACCOUNTS',         'The accounts your sales invoices and credit memos post to. Leave the foreign code blank to use the same account for every country.');
define('XERO_SYNC_LBL_SHOW_ALL',          'Show every account');
define('XERO_SYNC_COL_ACCOUNT',           'FreeBooks account');
define('XERO_SYNC_COL_DOMESTIC',          'Xero code');
define('XERO_SYNC_COL_FOREIGN',           'Xero code, foreign');
define('XERO_SYNC_HELP_TAX',              'Enter the name of the matching Xero tax rate exactly as Xero shows it, not its code. Xero charges its own rate for that name, so keep the two rates the same.');
define('XERO_SYNC_COL_TAX_RATE',          'FreeBooks tax rate');
define('XERO_SYNC_COL_XERO_TAX',          'Xero tax rate name');
define('XERO_SYNC_HELP_CONTACTS',         'Only needed where a customer has a different name in Xero.');
define('XERO_SYNC_COL_CUSTOMER',          'FreeBooks customer name');
define('XERO_SYNC_COL_XERO_CONTACT',      'Xero contact name');
define('XERO_SYNC_BTN_ADD_CONTACT',       'Add a name');
define('XERO_SYNC_NOT_IN_CHART',          '(no longer in the chart)');
define('XERO_SYNC_NOT_A_RATE',            '(no longer a tax rate)');
define('XERO_SYNC_NONE_USED',             'No sales have been posted yet. Tick Show every account to map accounts in advance.');
define('XERO_SYNC_NO_TAX_RATES',          'There are no customer tax rates.');
define('XERO_SYNC_SAVED',                 'The Xero settings were saved.');
define('XERO_SYNC_ERR_DUE_DAYS',          'Days until due must be a whole number from 0 to 365.');
define('XERO_SYNC_ERR_TOLERANCE',         'The rounding tolerance must be a number from 0 to 100.');

// Connection to Xero
define('XERO_SYNC_HEAD_CONNECTION',       'Xero Connection');
define('XERO_SYNC_LBL_CLIENT_ID',         'Client id');
define('XERO_SYNC_LBL_CLIENT_SECRET',     'Client secret');
define('XERO_SYNC_HELP_CLIENT',           'From the app registered for FreeBooks in Xero\'s developer portal. A PKCE app has no client secret; leave it blank.');
define('XERO_SYNC_SECRET_KEPT',           'Saved; leave blank to keep it');
define('XERO_SYNC_LBL_CLEAR_SECRET',      'Remove the saved client secret');
define('XERO_SYNC_LBL_REDIRECT',          'Redirect URI');
define('XERO_SYNC_HELP_REDIRECT',         'Add this address to the app\'s redirect URIs in Xero, exactly as shown.');
define('XERO_SYNC_CONNECTED',             'Connected to %s since %s.');
define('XERO_SYNC_NOT_CONNECTED',         'Not connected.');
define('XERO_SYNC_BTN_CONNECT',           'Connect to Xero');
define('XERO_SYNC_BTN_RECONNECT',         'Connect again');
define('XERO_SYNC_BTN_DISCONNECT',        'Disconnect');
define('XERO_SYNC_CONFIRM_DISCONNECT',    'Disconnect from Xero? Nothing can be sent until it is connected again.');
define('XERO_SYNC_DISCONNECTED',          'Disconnected from Xero.');
define('XERO_SYNC_CONNECTED_TO',          'Connected to the Xero organisation %s.');
define('XERO_SYNC_XERO_LISTS_FAILED',     'Xero\'s accounts and tax rates could not be read: %s');
define('XERO_SYNC_PICK_TENANT',           'Choose the Xero organisation to send documents to.');
define('XERO_SYNC_BTN_USE_TENANT',        'Use this organisation');
define('XERO_SYNC_ERR_NO_CLIENT',         'Enter the Xero app\'s client id first.');
define('XERO_SYNC_ERR_STATE',             'The answer from Xero did not match a connection started here, so it was ignored. Press Connect to Xero again.');
define('XERO_SYNC_ERR_TOKEN',             'Xero refused the token request (HTTP %d: %s).');
define('XERO_SYNC_ERR_XERO_HTTP',         'Xero answered with HTTP status %d.');
define('XERO_SYNC_ERR_XERO_DENIED',       'Xero did not grant access: %s');
define('XERO_SYNC_ERR_NO_TENANT',         'The connection does not reach any Xero organisation.');
define('XERO_SYNC_ERR_NOT_CONNECTED',     'Xero Sync is not connected to Xero. Connect it from the Xero Sync settings.');
define('XERO_SYNC_ERR_EXPIRED',           'The connection to Xero has lapsed or was revoked. Connect again from the Xero Sync settings.');
define('XERO_SYNC_ERR_REJECTED',          'Xero refused the document without saying why.');

// Export page
define('XERO_SYNC_EXPORT_TITLE',          'Xero Sync');
define('XERO_SYNC_LBL_FROM',              'From');
define('XERO_SYNC_LBL_TO',                'To');
define('XERO_SYNC_LBL_CREDIT_MEMOS',      'Include credit memos');
define('XERO_SYNC_BTN_PREVIEW',           'Preview');
define('XERO_SYNC_BTN_CSV',               'Download CSV');
define('XERO_SYNC_COL_TYPE',              'Type');
define('XERO_SYNC_COL_NUMBER',            'Number');
define('XERO_SYNC_COL_DATE',              'Date');
define('XERO_SYNC_COL_CONTACT',           'Contact');
define('XERO_SYNC_COL_COUNTRY',           'Country');
define('XERO_SYNC_COL_FB_TOTAL',          'FreeBooks total');
define('XERO_SYNC_COL_XERO_TOTAL',        'Xero total');
define('XERO_SYNC_COL_ROUNDING',          'Rounding');
define('XERO_SYNC_COL_STATUS',            'Status');
define('XERO_SYNC_COL_XERO',              'In Xero');
define('XERO_SYNC_TYPE_INVOICE',          'Invoice');
define('XERO_SYNC_TYPE_CREDIT',           'Credit memo');
define('XERO_SYNC_DOMESTIC',              '%s (domestic)');
define('XERO_SYNC_FOREIGN',               '%s (foreign)');
define('XERO_SYNC_STATUS_OK',             'OK');
define('XERO_SYNC_STATUS_ACCOUNTS',       'Unmapped accounts: %s');
define('XERO_SYNC_STATUS_TAX',            'Unmapped tax rates: %s');
define('XERO_SYNC_STATUS_ROUNDED',        'Rounded %s');
define('XERO_SYNC_SUMMARY',               '%d documents, %d to check.');
define('XERO_SYNC_NO_DOCUMENTS',          'No invoices or credit memos are dated in that range.');
define('XERO_SYNC_ERR_DATES',             'Enter a From and a To date, with From no later than To.');
define('XERO_SYNC_NOT_SET_UP',            'Xero Sync is not set up yet: choose a default account and a rounding account in its settings.');
define('XERO_SYNC_SETTINGS_LINK',         'Open the settings');

// Sending to Xero
define('XERO_SYNC_BTN_SEND',              'Send ticked documents to Xero');
define('XERO_SYNC_SEND_NOT_CONNECTED',    'To send documents to Xero, connect to Xero in the Xero Sync settings.');
define('XERO_SYNC_SEND_NEEDS_EDIT',       'Sending documents to Xero needs edit permission on Xero Sync.');
define('XERO_SYNC_SEND_NONE',             'Tick at least one document to send.');
define('XERO_SYNC_SEND_PROGRESS',         'Sending %d of %d...');
define('XERO_SYNC_SEND_DONE',             'Finished: %d sent, %d not sent.');
define('XERO_SYNC_SEND_WAITING',          'Xero allows only so many requests a minute; waiting %d seconds before going on (%d of %d done)...');
define('XERO_SYNC_SEND_DAY_LIMIT',        'Stopped: Xero\'s daily limit for this organisation is reached. %d sent, %d not sent, %d still to send; send them again tomorrow.');
define('XERO_SYNC_X_NOT_SENT',            'Not sent');
define('XERO_SYNC_X_SENDING',             'Being sent');
define('XERO_SYNC_X_SENT',                'Sent %s');
define('XERO_SYNC_X_CHANGED',             'Changed since it was sent');
define('XERO_SYNC_X_RELEASED',            'Released');
define('XERO_SYNC_X_FAILED',              'Not sent: %s');
define('XERO_SYNC_X_HELD',                'Xero holds an earlier copy, which the next send updates.');
define('XERO_SYNC_BTN_RELEASE',           'Release');
define('XERO_SYNC_BTN_FORGET',            'Unlink from deleted Xero copy');
define('XERO_SYNC_CONFIRM_RELEASE',       'Release this document? It can then be changed in FreeBooks, and sending it again updates the copy in Xero.');
define('XERO_SYNC_CONFIRM_FORGET',        'Use this only after deleting the old copy in Xero yourself: FreeBooks deletes nothing there. Xero matches the next send to any document with the same number, so it overwrites one still in draft and refuses one that is voided or paid. Unlink now?');
define('XERO_SYNC_ACTION_STALE',          'Nothing changed: the document is no longer in a state that allows this.');
define('XERO_SYNC_ERR_NOT_A_DOCUMENT',    'This is not a sales invoice or credit memo.');
define('XERO_SYNC_BADGE',                 'In Xero');
define('XERO_SYNC_LOCK_SAVE',             'This document was sent to Xero on %s, and this change would alter what Xero holds or the tax it rests on: the customer, an address, the number, the date, the reference, the currency or the lines. Release it on the Xero Sync page to change it.');
define('XERO_SYNC_LOCK_DELETE',           'This document was sent to Xero on %s, so it cannot be deleted. Release it on the Xero Sync page first.');

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
