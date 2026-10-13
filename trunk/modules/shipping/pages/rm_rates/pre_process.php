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
//  Path: /modules/shipping/pages/rm_rates/pre_process.php
//
// The Royal Mail price list: import a rate file, see what each stored set holds,
// and delete a set. An uploaded file is checked and summarised first, and kept
// as the pending file until it is imported or discarded. The page also holds the
// country zones the price list is read through.
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
gen_pull_language($module, 'admin');
load_method_language(DIR_FS_MODULES . 'shipping/methods/', 'royal_mail');
require_once(DIR_FS_WORKING . 'functions/shipping.php');
require_once(DIR_FS_WORKING . 'methods/royal_mail/rm_rates.php');
require_once(DIR_FS_WORKING . 'methods/royal_mail/rm_zones.php');

$pending_dir  = DIR_FS_MY_FILES . $_SESSION['company'] . '/shipping/';
$pending_file = $pending_dir . 'rm_rates_pending.csv';
$pending      = false; // the checked pending file: effective_from, rows, summary
$check_errors = array();
$error_count  = 0;
$reader       = new rm_rates();
$zone_errors  = array();
$zone_count   = 0;

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = '';
// Every action but the zone download changes stored data, so each comes from the
// posted (CSRF-checked) form.
if (!in_array($_REQUEST['action'], array('', 'zones_export'), true) && $_SERVER['REQUEST_METHOD'] !== 'POST') $_REQUEST['action'] = '';

switch ($_REQUEST['action']) {
  case 'check':
    validate_security($security_level, 3);
    if (!validate_upload('rate_file', 'text', 'csv')) break;
    if (!is_dir($pending_dir)) @mkdir($pending_dir, 0755, true);
    if (!move_uploaded_file($_FILES['rate_file']['tmp_name'], $pending_file)) {
      $messageStack->add(RM_RATES_ERR_READ, 'error');
      break;
    }
    $_SESSION['rm_rates_pending_name'] = basename((string)$_FILES['rate_file']['name']);
    break;
  case 'import':
    validate_security($security_level, 3);
    $file = is_file($pending_file) ? $reader->read_file($pending_file) : false;
    if ($file === false) {
      $messageStack->add(RM_RATES_ERR_NO_PENDING, 'error');
      break;
    }
    rm_rates::replace_version($file['effective_from'], $file['rows']);
    @unlink($pending_file);
    gen_add_audit_log(RM_RATES_AUDIT_IMPORT, $file['effective_from'], count($file['rows']));
    $messageStack->add(sprintf(RM_RATES_IMPORTED, count($file['rows']), gen_locale_date($file['effective_from'])), 'success');
    unset($_SESSION['rm_rates_pending_name']);
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'SSL'));
    break;
  case 'discard':
    validate_security($security_level, 3);
    @unlink($pending_file);
    unset($_SESSION['rm_rates_pending_name']);
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'SSL'));
    break;
  case 'delete':
    validate_security($security_level, 4);
    $date = (string)($_POST['effective_from'] ?? '');
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) {
      rm_rates::delete_version($date);
      gen_add_audit_log(RM_RATES_AUDIT_DELETE, $date);
      $messageStack->add(sprintf(RM_RATES_DELETED, gen_locale_date($date)), 'success');
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'SSL'));
    break;
  case 'zones_export':
    rm_zones::export();
    exit;
  case 'zones_import':
    validate_security($security_level, 3);
    if (!validate_upload('zone_file', 'text', 'csv')) break;
    $zone_reader = new rm_zones();
    $rows = $zone_reader->read_file($_FILES['zone_file']['tmp_name']);
    if ($rows === false) {
      $zone_errors = $zone_reader->errors;
      $zone_count  = $zone_reader->error_count;
      break;
    }
    rm_zones::replace_all($rows);
    gen_add_audit_log(RM_ZONES_AUDIT_IMPORT, '', count($rows));
    $messageStack->add(sprintf(RM_ZONES_IMPORTED, count($rows)), 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'SSL'));
    break;
  case 'zones_reset':
    validate_security($security_level, 3);
    $zone_reader = new rm_zones();
    $rows = $zone_reader->read_file(rm_zones::shipped_file());
    if ($rows !== false) {
      rm_zones::replace_all($rows);
      gen_add_audit_log(RM_ZONES_AUDIT_RESET, '', count($rows));
      $messageStack->add(sprintf(RM_ZONES_IMPORTED, count($rows)), 'success');
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'SSL'));
    break;
}

// A pending file is shown, checked, on every visit until it is imported or discarded.
if (is_file($pending_file)) {
  $file = $reader->read_file($pending_file);
  if ($file === false) {
    $check_errors = $reader->errors;
    $error_count  = $reader->error_count;
  } else {
    $pending = array(
      'effective_from' => $file['effective_from'],
      'rows'           => count($file['rows']),
      'summary'        => rm_rates::summarise($file['rows']),
      'replaces'       => false,
    );
  }
}
$zones       = rm_zones::all();
$zones_check = array_filter($zones, function ($z) { return $z['needs_confirmation'] === '1'; });
$versions = rm_rates::versions();
$current  = rm_rates::current_version();
if ($pending) foreach ($versions as $v) {
  if ($v['effective_from'] === $pending['effective_from']) $pending['replaces'] = (int)$v['num_rows'];
}

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', RM_RATES_TITLE);
?>
