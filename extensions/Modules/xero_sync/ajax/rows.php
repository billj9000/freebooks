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
//  Path: /modules/xero_sync/ajax/rows.php
//
// Redraws part of the settings page for htmx: kind=accounts returns the account mapping rows
// (all=1 for every account), kind=contact returns one empty contact name row.
//
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
gen_pull_language('xero_sync');
require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_settings.php');
require_once(DIR_FS_MODULES . 'xero_sync/functions/xero_sync.php');

$can_save = $security_level > 2;
switch ($_GET['kind'] ?? '') {
  case 'accounts': echo xero_sync_account_rows(xero_settings::sales_accounts(($_GET['all'] ?? '') === '1'), $can_save); break;
  case 'contact':  if ($can_save) echo xero_sync_contact_row('', '', true); break;
}
die;
