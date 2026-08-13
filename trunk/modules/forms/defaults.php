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
