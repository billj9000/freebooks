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
//  Path: /modules/forms/language/en_us/admin.php
//
// Read by Company -> Module Administration, which builds its list by scanning
// modules/*/config.php and reading MODULE_<NAME>_TITLE and _DESCRIPTION for
// each. Both are required: a module without them is a fatal on that page, not a
// missing row.

define('MODULE_FORMS_TITLE',       'Forms Module');
define('MODULE_FORMS_DESCRIPTION', 'Reports and forms engine. Runs the existing report catalog with typed values, so the same definition prints a readable PDF and exports a machine-readable CSV. Replaces PhreeForm, and runs alongside it during the transition. Defining datasets is a separate privilege from writing or running reports.');

// The module's own settings page, reached from Company -> Module Administration.
define('FORMS_ADMIN_TITLE',        'Forms Module Settings');
define('FORMS_ADMIN_CONFIG_INFO',  'Settings');

// The renderer defaults. Each was a PhreeForm setting, on a tab that goes when
// that module does — so they live here or they become unreachable.
define('FORMS_DEFAULT_PAPER_TEXT',        'Paper size');
define('FORMS_DEFAULT_PAPER_DESC',        'The paper a document sits on when its own layout does not say. A document that names its own size keeps it.');
define('FORMS_DEFAULT_ROWSPACE_TEXT',     'Row spacing');
define('FORMS_DEFAULT_ROWSPACE_DESC',     'Extra space between the rows of a report table, in points. 2 is the shipped value.');
define('FORMS_DEFAULT_COLUMN_WIDTH_TEXT', 'Default column width');
define('FORMS_DEFAULT_COLUMN_WIDTH_DESC', 'Width in millimetres for a report column whose definition declares none. 25 is the shipped value.');
define('FORMS_ADMIN_PRINT_CAUTION',       'Printing needs the <b>Forms - Run and print documents</b> permission. Anyone whose role does not grant it cannot open a document from a Print button, so check the roles in Company &rarr; User Security.');
