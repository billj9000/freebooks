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
//  Path: /modules/quickbooks_sync/ajax/preview.php
//
// The export page's preview of the documents in a date range, for htmx.
//
$security_level = validate_ajax_user(SECURITY_ID_QUICKBOOKS_SYNC);
gen_pull_language('quickbooks_sync');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_source.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/functions/quickbooks_sync.php');

$range = quickbooks_sync_date_range($_GET);
if (!$range) {
  echo '<div class="alert alert-danger py-2 mb-0">' . htmlspecialchars(QUICKBOOKS_SYNC_ERR_DATES) . '</div>';
  die;
}
$source = new quickbooks_source();
$documents = $source->documents('invoice', $range[0], $range[1]);
if (isset($_GET['credit_memos'])) $documents = array_merge($documents, $source->documents('credit_memo', $range[0], $range[1]));
echo quickbooks_sync_preview($documents, $source->builder(), quickbooks_sync_send_context((int)$security_level));
die;
