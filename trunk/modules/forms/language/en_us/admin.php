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

define('MODULE_FORMS_PRINT_BUTTONS_TEXT', 'Print buttons open Forms');
define('MODULE_FORMS_PRINT_BUTTONS_DESC', 'The Print button on an order, bill, invoice, receipt or POS ticket opens PhreeForm until you turn this on. Both engines read the same stored form definitions, so nothing is migrated and nothing is lost by turning it off again.');
define('FORMS_ADMIN_PRINT_CAUTION',       'Printing through Forms needs the <b>Forms - Run and print documents</b> permission. Anyone whose role does not grant it keeps printing through PhreeForm, so check the roles in Company &rarr; User Security before relying on this.');
define('FORMS_ADMIN_YES_NO_ON',           'Yes - open Forms');
define('FORMS_ADMIN_YES_NO_OFF',          'No - open PhreeForm');
