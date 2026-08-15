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
//  Path: /modules/forms/language/en_us/language.php
//

define('BOX_FORMS_DOCUMENTS',      'Documents (Forms)');
define('FORMS_PAGE_TITLE',         'Documents');
define('FORMS_PAGE_INTRO',         'Reports and forms, rendered by the Forms engine. PhreeForm continues to run alongside it.');

define('FORMS_COL_TITLE',          'Document');
define('FORMS_COL_GROUP',          'Group');
define('FORMS_COL_SERVED_BY',      'Served by');
define('FORMS_COL_ACTIONS',        'Run');

define('FORMS_SERVED_DATASET',     'dataset: %s');
define('FORMS_SERVED_PROVIDER',    'built in code: %s');
define('FORMS_SERVED_NOT_YET',     'not yet served by Forms');
define('FORMS_SERVED_FORM',        'a form — printed from a document');
define('FORMS_BTN_PRINT',          'Print…');

define('FORMS_LEGEND_OPTIONS',     'Run options');
define('FORMS_DATE_RANGE',         'Date range');
define('FORMS_PERIOD',             'Accounting period');
define('FORMS_GL_ACCOUNT',         'GL account');
define('FORMS_BTN_PDF',            'PDF');
define('FORMS_BTN_CSV',            'CSV');
define('FORMS_BTN_VIEW',           'View');

define('FORMS_ERROR_NOT_FOUND',    'No document with id %s.');
define('FORMS_ERROR_CANNOT_RUN',   'Forms cannot run this document: %s');
define('FORMS_ERROR_NO_FORMAT',    'Unknown output format: %s');
define('FORMS_ERROR_DELIVERY',     'The document was built but could not be sent: %s');
define('FORMS_ERROR_GL_REQUIRED',  'Choose a GL account before running the reconciliation.');
define('FORMS_WARN_CONVERSION',    'This report converted with warnings: %s');
define('FORMS_NO_DOCUMENTS',       'No documents are defined.');

/***** printing a form from the document that owns it (?module=forms&page=print) *****/
define('FORMS_PRINT_TITLE',        'Print Document');
define('FORMS_PRINT_CHOOSE',       'Choose a form');
define('FORMS_PRINT_CHOOSE_BTN',   'Use this form');
define('FORMS_PRINT_WHAT',         'What will be printed');
define('FORMS_PRINT_RECORDS',      'Records selected');
define('FORMS_PRINT_DELIVER',      'Deliver');
define('FORMS_PRINT_BTN_VIEW',     'View PDF');
define('FORMS_PRINT_BTN_DOWNLOAD', 'Download PDF');
define('FORMS_PRINT_BTN_EMAIL',    'Send email');
define('FORMS_PRINT_EMAIL',        'Email it instead');
define('FORMS_PRINT_EMAIL_TO',     'To address');
define('FORMS_PRINT_EMAIL_NAME',   'To name');
define('FORMS_PRINT_EMAIL_SUBJECT','Subject');
define('FORMS_PRINT_EMAIL_MESSAGE','Message');
define('FORMS_PRINT_EMAIL_BODY',   'Please find attached your %s from %s.');
define('FORMS_PRINT_EMAIL_NO_ADDRESS', 'Enter an address to send it to.');
define('FORMS_PRINT_NO_FORMS',     'No forms are defined in group %s.');
define('FORMS_PRINT_NOTHING_ASKED','No form was named. This page is opened from the document being printed.');
define('FORMS_PRINT_NO_RECORDS',   'Nothing was selected to print.');
define('FORMS_PRINT_OPEN_FROM_DOCUMENT', 'A form prints one record at a time, so it is opened from the document it belongs to — open the order, invoice or statement and use its Print button.');

// ── Dataset authoring (design §3.2, §3.3) ────────────────────────────────────
define('BOX_FORMS_DATASETS',        'Report Datasets');
define('FORMS_DS_TITLE',            'Report Datasets');
define('FORMS_DS_INTRO',            'A dataset is the set of tables a report author may draw fields from. Defining one is a separate privilege from writing or running reports, because it decides what data becomes reachable.');
define('FORMS_DS_SHIPPED_NOTE',     'Datasets shipped with FreeBooks are defined in code and cannot be edited here.');
define('FORMS_DS_COL_NAME',         'Name');
define('FORMS_DS_COL_LABEL',        'Label');
define('FORMS_DS_COL_TABLES',       'Tables');
define('FORMS_DS_COL_FIELDS',       'Fields');
define('FORMS_DS_COL_TIER',         'Origin');
define('FORMS_DS_TIER_SHIPPED',     'Shipped');
define('FORMS_DS_TIER_CUSTOM',      'Custom');
define('FORMS_DS_NEW',              'New dataset');
define('FORMS_DS_EDIT',             'Edit dataset');
define('FORMS_DS_NONE',             'No custom datasets have been defined.');

define('FORMS_DS_LEGEND_ABOUT',     'Dataset');
define('FORMS_DS_LEGEND_JOINS',     'Tables');
define('FORMS_DS_LEGEND_FIELDS',    'Fields');
define('FORMS_DS_NAME',             'Name');
define('FORMS_DS_NAME_HELP',        'Letters, digits and underscores. Used in stored report definitions, so it cannot be changed later.');
define('FORMS_DS_LABEL',            'Label');
define('FORMS_DS_DESCRIPTION',      'Description');
define('FORMS_DS_BASE_TABLE',       'Starting table');
define('FORMS_DS_ADD_JOIN',         'Add a table');
define('FORMS_DS_ADD_FIELD',        'Add a field');
define('FORMS_DS_REMOVE',           'Remove');
define('FORMS_DS_JOIN_TYPE',        'Include rows');
define('FORMS_DS_JOIN_INNER',       'Only where both match');
define('FORMS_DS_JOIN_LEFT',        'All, matched or not');
define('FORMS_DS_JOIN_TABLE',       'Table');
define('FORMS_DS_JOIN_MATCH',       'Matched on');
define('FORMS_DS_FIELD_KEY',        'Field name');
define('FORMS_DS_FIELD_LABEL',      'Shown as');
define('FORMS_DS_FIELD_SOURCE',     'Column');
define('FORMS_DS_FIELD_TYPE',       'Type');
define('FORMS_DS_NO_SQL_NOTE',      'Tables and columns are chosen from this database. There is no place to type SQL, by design.');

define('FORMS_DS_SAVED',            "Dataset '%s' saved.");
define('FORMS_DS_DELETED',          "Dataset '%s' deleted.");
define('FORMS_DS_ERROR',            'Dataset not saved: %s');
define('FORMS_DS_NOT_FOUND',        "There is no custom dataset named '%s'.");
define('FORMS_DS_IN_USE',           "Dataset '%s' cannot be deleted: %d report(s) still use it.");
define('FORMS_DS_CONFIRM_DELETE',   'Delete this dataset? Reports that use it will stop working.');
define('FORMS_DS_PROBLEM',          '%s');
