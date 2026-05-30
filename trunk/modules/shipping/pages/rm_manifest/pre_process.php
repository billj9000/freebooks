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
//  Path: /modules/shipping/pages/rm_manifest/pre_process.php
//
$security_level = validate_user(SECURITY_ID_SHIPPING_MANAGER);
gen_pull_language('contacts');
load_method_language(DIR_FS_MODULES . 'shipping/methods/', 'royal_mail');
load_specific_method('shipping', 'royal_mail');
require_once(DIR_FS_WORKING . 'defaults.php');

$error         = false;
$manifest_done = false;
$manifest_id   = '';
$today         = date('Y-m-d');

switch ($_REQUEST['action']) {
  case 'manifest':
    $rm     = new royal_mail();
    $result = $rm->manifestOrders();
    if ($result === false) {
      $error = true;
    } else {
      $manifest_done = true;
      $manifest_id   = is_string($result) ? $result : '';
      gen_add_audit_log(RM_MANIFEST_TITLE, $manifest_id ?: 'ok');
    }
    break;
}

// Today's Royal Mail shipments from local shipping log
$shipments = array();
$sql_result = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_LOG
  . " WHERE carrier = 'royal_mail' AND DATE(ship_date) = '" . db_input($today) . "'"
  . " ORDER BY ship_date DESC");
while (!$sql_result->EOF) {
  $shipments[] = $sql_result->fields;
  $sql_result->MoveNext();
}

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', RM_MANIFEST_TITLE);
?>
