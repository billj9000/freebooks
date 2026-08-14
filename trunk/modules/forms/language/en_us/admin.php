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
