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
