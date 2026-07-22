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
//  Path: /modules/phreebooks/ajax/unprinted_invoices.php
//
// Returns every unprinted Sales Invoice id (journal_id 12), regardless of
// which page of the Sales Invoice Manager list is currently displayed, so
// "Select All Unprinted" can select across pages in one call.

$security_level = validate_ajax_user(SECURITY_ID_SALES_INVOICE);

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

$sql = "select id from " . TABLE_JOURNAL_MAIN . " where journal_id = 12 and printed = 0";
if ($store_id) $sql .= " and store_id = " . $store_id;
$sql .= " order by id";

$result = $db->Execute($sql);
$ids    = array();
while (!$result->EOF) {
  $ids[] = (int)$result->fields['id'];
  $result->MoveNext();
}

echo json_encode(array('ids' => $ids));
die;
