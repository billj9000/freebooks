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
//  Path: /modules/quickbooks_sync/ajax/rows.php
//
// Redraws part of the settings page for htmx: kind=items returns the item mapping rows (all=1 for
// every account), kind=customer returns one empty customer name row.
//
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
gen_pull_language('quickbooks_sync');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_settings.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/functions/quickbooks_sync.php');

$can_save = $security_level > 2;
switch ($_GET['kind'] ?? '') {
  case 'items':    echo quickbooks_sync_item_rows(quickbooks_settings::sales_accounts(($_GET['all'] ?? '') === '1'), $can_save); break;
  case 'customer': if ($can_save) echo quickbooks_sync_customer_row('', '', true); break;
}
die;
