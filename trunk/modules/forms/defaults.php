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
 * Deliberately few: renderer defaults travel on the document as PageSetup and
 * ReportStyle, so they are per-report rather than per-installation. What is left
 * here is what genuinely belongs to the installation.
 */

// The three renderer defaults, as FALLBACKS.
//
// Each is really a pb_configuration row that forms/classes/install.php seeds
// and application_top turns into a constant, so on any installed company these
// defines never run. They exist for the one case that is otherwise a fatal: a
// company missing the rows, or a CLI caller that builds its own constant list.
// `defined()` rather than an unconditional define, because a row and a define
// of the same name is a redeclaration warning on every page.
//
// The values match what install.php ships, deliberately. Two places holding the
// same number is a smell; the alternative is a page that dies when a row is
// missing, and a missing row is exactly when a default is wanted.
if (!defined('FORMS_DEFAULT_COLUMN_WIDTH')) define('FORMS_DEFAULT_COLUMN_WIDTH', 25);

// Rows drawn before the engine gives up on a runaway report. A report with no
// filter on a large journal is a memory exhaustion, not a useful document.
define('FORMS_MAX_ROWS', 50000);

// Where a company's definitions and their images live.
//
// The path is `pf_<id>` under `my_files/<company>/phreeform/`, and
// deliberately so: it is wired into the backup, the restore, and every
// definition file on disk, and moving it would be a migration with nothing to
// gain, one an older backup would silently undo.
//
// The constant is Forms' own, so no Forms page depends on another module to
// find its files (design §13.7 step 6).
define('FORMS_DIR_DEFINITIONS', DIR_FS_MY_FILES . ($_SESSION['company'] ?? '') . '/phreeform/');

// Images a form may draw. A subdirectory rather than a sibling, so that a
// company's whole Forms state is one folder to copy.
define('FORMS_DIR_IMAGES', FORMS_DIR_DEFINITIONS . 'images');

// The face a document is drawn in when it names none.
//
// One face, not a setting: Forms' renderers extend TCPDF unconditionally
// (classes/render/pdf_canvas.php), so there is no PDF_APP choice to make.
define('FORMS_DEFAULT_FONT', 'freeserif');

// Space between the heading rows of a table, in points.
if (!defined('FORMS_DEFAULT_ROWSPACE')) define('FORMS_DEFAULT_ROWSPACE', 2);

// The paper a seeded document gets when its definition names none. Stored as
// the NAME; forms_seed_native() takes either spelling and keeps the name.
if (!defined('FORMS_DEFAULT_PAPER')) define('FORMS_DEFAULT_PAPER', 'Letter');
