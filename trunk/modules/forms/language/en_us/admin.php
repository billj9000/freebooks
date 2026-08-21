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
define('MODULE_FORMS_DESCRIPTION', 'Reports and forms engine. Runs the document catalog with typed values, so the same definition prints a readable PDF and exports a machine-readable CSV. Defining datasets is a separate privilege from writing or running reports.');

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

/***** the tools card: two actions that came over with the engine *****/
define('FORMS_ADMIN_TOOLS_INFO',    'Tools');

define('FORMS_TOOLS_RESTAMP_TITLE',   'Re-stamp Paper Size');
define('FORMS_TOOLS_RESTAMP_DESC',    'Re-target every stored report and form onto the current default paper size (%s). Element positions are not moved, so a form drawn for a wider page will sit closer to the right edge on a narrower one. Any file whose page setting cannot be located is left untouched and counted.');
define('FORMS_TOOLS_RESTAMP_SUBMIT',  'Re-stamp all reports and forms');
define('FORMS_TOOLS_RESTAMP_CONFIRM', 'This rewrites the page size of every stored report and form. Continue?');
define('FORMS_RESTAMP_DONE',          'Re-stamped %d report(s) and form(s) onto %s.');
define('FORMS_RESTAMP_SKIPPED',       '%d file(s) were left untouched because their page setting could not be located.');

define('FORMS_TOOLS_REBUILD_TITLE',   'Document List Verification / Rebuild');
define('FORMS_TOOLS_REBUILD_DESC',    'Rebuilds the document list from the definition files on disk. It reloads the folder structure, re-files any document whose folder has gone missing, and clears list entries that no longer have a file. Use it after a restore has left the list wrong.');
define('FORMS_TOOLS_REBUILD_SUBMIT',  'Start verify / rebuild');
define('FORMS_TOOLS_REBUILD_CONFIRM', 'This rebuilds the whole document list from the files on disk. Continue?');
define('FORMS_TOOLS_REBUILD_SUCCESS', 'Rebuilt the document list: %s document(s), of which %s had lost their folder and were filed under Miscellaneous.');
define('FORMS_TOOLS_REBUILD_SKIPPED', '%s file(s) could not be read as a document and were left where they are. They are not in the rebuilt list.');
