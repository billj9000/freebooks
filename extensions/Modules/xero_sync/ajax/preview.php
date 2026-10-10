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
//  Path: /modules/xero_sync/ajax/preview.php
//
// The export page's preview of the documents in a date range, for htmx.
//
validate_ajax_user(SECURITY_ID_XERO_SYNC);
gen_pull_language('xero_sync');
require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_source.php');
require_once(DIR_FS_MODULES . 'xero_sync/functions/xero_sync.php');

$range = xero_sync_date_range($_GET);
if (!$range) {
  echo '<div class="alert alert-danger py-2 mb-0">' . htmlspecialchars(XERO_SYNC_ERR_DATES) . '</div>';
  die;
}
$source = new xero_source();
echo xero_sync_preview($source->documents($range[0], $range[1], isset($_GET['credit_memos'])));
die;
