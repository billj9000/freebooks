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
//  Path: /modules/quickbooks_sync/pages/main/pre_process.php
//
$security_level = validate_user(SECURITY_ID_QUICKBOOKS_SYNC);
gen_pull_language($module);
require_once(DIR_FS_WORKING . 'classes/quickbooks_source.php');
require_once(DIR_FS_WORKING . 'functions/quickbooks_sync.php');

$date_from    = $_GET['date_from'] ?? date('Y-m-01');
$date_to      = $_GET['date_to']   ?? date('Y-m-d');
$credit_memos = !isset($_GET['date_from']) || isset($_GET['credit_memos']);

$downloads = ['invoices' => 'invoice', 'credit_memos' => 'credit_memo'];
$action = $_REQUEST['action'] ?? '';
if (isset($downloads[$action])) {
  $doc_type = $downloads[$action];
  $range = quickbooks_sync_date_range($_GET);
  if (!$range) {
    $messageStack->add(QUICKBOOKS_SYNC_ERR_DATES, 'error');
  } else {
    $source = new quickbooks_source();
    $builder = $source->builder();
    $documents = array_values(array_filter(array_column($source->documents($doc_type, $range[0], $range[1]), 'doc'),
                                           fn($doc) => $builder->file_refusal($doc) === null));
    if (!$documents) {
      $messageStack->add($doc_type === 'invoice' ? QUICKBOOKS_SYNC_NO_INVOICES : QUICKBOOKS_SYNC_NO_CREDITS, 'error');
    } else {
      // More documents than one import takes go out as numbered files in a ZIP, each to be imported
      // in turn.
      $name = 'quickbooks_' . $action . '_' . $range[0] . '_' . $range[1];
      $parts = $builder->file_parts($documents);
      if (count($parts) === 1) {
        $content = $builder->file_text($doc_type, $parts[0]);
        $type = 'text/csv; charset=utf-8';
        $file = $name . '.csv';
      } else {
        $zip_path = tempnam(sys_get_temp_dir(), 'qbs');
        $zip = new ZipArchive();
        $zip->open($zip_path, ZipArchive::OVERWRITE);
        foreach ($parts as $n => $part) $zip->addFromString($name . '_' . ($n + 1) . '.csv', $builder->file_text($doc_type, $part));
        $zip->close();
        $content = file_get_contents($zip_path);
        unlink($zip_path);
        $type = 'application/zip';
        $file = $name . '.zip';
      }
      header('Content-Type: ' . $type);
      header('Content-Disposition: attachment; filename="' . $file . '"');
      header('Content-Length: ' . strlen($content));
      echo $content;
      exit;
    }
  }
}

$mapping = quickbooks_settings::mapping();
$set_up  = $mapping['default_item'] !== '' && $mapping['rounding_item'] !== '';

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', QUICKBOOKS_SYNC_EXPORT_TITLE);
