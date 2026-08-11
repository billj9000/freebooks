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
// +-----------------------------------------------------------------+
//  Path: /modules/forms/classes/catalog/load.php
//
/**
 * Forms — loads the catalog layer.
 *
 *     require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
 *     require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
 *
 * The document seam must be loaded first: field definitions are typed with
 * ValueType.
 */

require_once(__DIR__ . '/catalog.php');
require_once(__DIR__ . '/filters.php');
require_once(__DIR__ . '/query_builder.php');
require_once(__DIR__ . '/shipped_datasets.php');
