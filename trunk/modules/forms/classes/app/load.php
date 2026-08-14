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
//  Path: /modules/forms/classes/app/load.php
//
/**
 * Forms — loads everything the application needs to run a document.
 *
 * This is the one require a page makes. It pulls in all five layers in
 * dependency order, which is why nothing else in the module has to know that
 * order:
 *
 *     require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
 *
 * The layers below are loadable on their own — the seam has no dependencies at
 * all, and the verification tools require them individually — but an
 * application page wants the lot.
 */

require_once(__DIR__ . '/../document/load.php');
require_once(__DIR__ . '/../catalog/load.php');
require_once(__DIR__ . '/../render/load.php');
require_once(__DIR__ . '/../deliver/load.php');
require_once(__DIR__ . '/../legacy/template_converter.php');
require_once(__DIR__ . '/../legacy/dataset_resolver.php');
require_once(__DIR__ . '/../providers/report_provider.php');
require_once(__DIR__ . '/../legacy/report_converter.php');
require_once(__DIR__ . '/../providers/financial_provider.php');
require_once(__DIR__ . '/../providers/entry_provider.php');
require_once(__DIR__ . '/../providers/query_provider.php');
require_once(__DIR__ . '/../providers/statement_provider.php');
require_once(__DIR__ . '/../providers/pos_provider.php');
require_once(__DIR__ . '/../providers/work_order_provider.php');
require_once(__DIR__ . '/record_selector.php');
require_once(__DIR__ . '/form_runner.php');
require_once(__DIR__ . '/document_runner.php');
