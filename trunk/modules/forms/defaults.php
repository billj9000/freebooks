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
//  Path: /modules/forms/defaults.php
//
/**
 * Module-level constants for Forms.
 *
 * Deliberately few. PhreeForm carried a dozen PF_* settings that were really
 * renderer defaults; those now travel on the document as PageSetup and
 * ReportStyle, so they are per-report rather than per-installation. What is left
 * here is what genuinely belongs to the installation.
 */

// Fallback width for a column whose definition declares none. PhreeForm's admin
// setting is reused where present so a converted report keeps its layout.
define('FORMS_DEFAULT_COLUMN_WIDTH', defined('PF_DEFAULT_COLUMN_WIDTH') ? PF_DEFAULT_COLUMN_WIDTH : 25);

// Rows drawn before the engine gives up on a runaway report. A report with no
// filter on a large journal is a memory exhaustion, not a useful document.
define('FORMS_MAX_ROWS', 50000);

// Where a company's definitions and their images live.
//
// The same path PhreeForm used, and deliberately so: `pf_<id>` under
// `my_files/<company>/phreeform/` is wired into the backup, the restore, and
// every definition file already on disk. Moving 57 files to make a folder name
// read better would be a migration with nothing to gain from it, and one that
// an older backup would silently undo.
//
// What changes is who owns the constant. Forms read PF_DIR_MY_REPORTS, which
// phreeform/defaults.php defines, so every Forms page had to bootstrap the
// module it replaces before it could find its own files — and deleting
// PhreeForm would have taken the loader, the canvas, the print page and the
// migration tool with it (design §13.7 step 6).
define('FORMS_DIR_DEFINITIONS', DIR_FS_MY_FILES . ($_SESSION['company'] ?? '') . '/phreeform/');

// Images a form may draw. A subdirectory rather than a sibling, so that a
// company's whole Forms state is one folder to copy.
define('FORMS_DIR_IMAGES', FORMS_DIR_DEFINITIONS . 'images');
