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
//  Path: /modules/forms/classes/document/load.php
//
/**
 * Forms — loads the document seam.
 *
 * FreeBooks has no PSR-4 autoloader for application code (composer autoload
 * covers vendor packages only), so the seam is loaded explicitly, matching the
 * require_once convention used throughout the app:
 *
 *     require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
 *     use FreeBooks\Forms\Document\{LayoutDocument, Record, TypedValue};
 *
 * Order matters: values define the primitives that elements and documents
 * reference in their signatures.
 */

require_once(__DIR__ . '/values.php');
require_once(__DIR__ . '/elements.php');
require_once(__DIR__ . '/documents.php');
require_once(__DIR__ . '/serial.php');
require_once(__DIR__ . '/renderer.php');
