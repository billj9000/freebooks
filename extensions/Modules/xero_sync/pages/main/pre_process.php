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
//  Path: /modules/xero_sync/pages/main/pre_process.php
//
$security_level = validate_user(SECURITY_ID_XERO_SYNC);
gen_pull_language($module);
require_once(DIR_FS_WORKING . 'classes/xero_source.php');
require_once(DIR_FS_WORKING . 'functions/xero_sync.php');

$date_from    = $_GET['date_from'] ?? date('Y-m-01');
$date_to      = $_GET['date_to']   ?? date('Y-m-d');
$credit_memos = !isset($_GET['date_from']) || isset($_GET['credit_memos']);

switch ($_REQUEST['action'] ?? '') {
  case 'csv':
    $range = xero_sync_date_range($_GET);
    if (!$range) {
      $messageStack->add(XERO_SYNC_ERR_DATES, 'error');
      break;
    }
    $source = new xero_source();
    $documents = array_column($source->documents($range[0], $range[1], $credit_memos), 'doc');
    $csv = $source->builder()->csv_text($documents);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="xero_' . $range[0] . '_' . $range[1] . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

$mapping  = xero_settings::mapping();
$set_up   = $mapping['default_account'] !== '' && $mapping['rounding_account'] !== '';

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', XERO_SYNC_EXPORT_TITLE);
