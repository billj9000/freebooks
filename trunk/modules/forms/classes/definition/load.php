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
//  Path: /modules/forms/classes/definition/load.php
//
/**
 * Forms — loads the native definition format.
 *
 * Depends on the document seam (the value objects a Definition is made of) and
 * on the catalog (datasets, filters), both of which a caller must have loaded
 * — app/load.php does, and is what an application page requires.
 *
 * The legacy converters are required here rather than by app/load.php because
 * DefinitionBuilder is the last thing that will use them through the LOADER.
 * They do not go away with the loader's legacy branch, though: decision L keeps
 * them as the Import feature, so what step 6 removes here is the branch, not
 * the directory.
 */

require_once(__DIR__ . '/../legacy/legacy_reader.php');
require_once(__DIR__ . '/../legacy/template_converter.php');
require_once(__DIR__ . '/../legacy/dataset_resolver.php');
require_once(__DIR__ . '/../legacy/report_converter.php');
require_once(__DIR__ . '/definition.php');
require_once(__DIR__ . '/codec.php');
require_once(__DIR__ . '/legacy_import.php');
require_once(__DIR__ . '/loader.php');
require_once(__DIR__ . '/definition_form.php');
require_once(__DIR__ . '/template_form.php');
require_once(__DIR__ . '/serial_form.php');
