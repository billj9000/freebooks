<?php
//  Path: /modules/shipping/pages/packages/pre_process.php

$security_level = validate_user(SECURITY_ID_SHIPPING_PACKAGES);

gen_pull_language('shipping');
require_once(DIR_FS_WORKING . 'functions/shipping.php');

$pkgID          = (int)db_prepare_input($_REQUEST['pkgID'] ?? 0);
$carrier_filter = db_prepare_input($_GET['carrier_filter'] ?? '');

// Build installed-carrier list for dropdowns
$method_dir   = DIR_FS_WORKING . 'methods/';
$carrier_list = [['id' => '', 'text' => SHPKG_TEXT_ALL_CARRIERS]];
$carrier_map  = ['' => SHPKG_TEXT_ALL_CARRIERS];
if (is_dir($method_dir)) foreach (scandir($method_dir) as $method) {
  if ($method === '.' || $method === '..') continue;
  load_method_language($method_dir, $method);
  $title_const = 'MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_TITLE';
  $title = defined($title_const) ? constant($title_const) : strtoupper($method);
  $carrier_list[] = ['id' => $method, 'text' => $title];
  $carrier_map[$method] = $title;
}

switch ($_REQUEST['action']) {
  case 'save':
  case 'update':
    validate_security($security_level, 2);
    $package_code = db_prepare_input($_POST['package_code'] ?? '');
    $name         = db_prepare_input($_POST['name']         ?? '');
    if ($package_code === '') { $messageStack->add(SHPKG_ERROR_MISSING_CODE, 'error'); break; }
    if ($name         === '') { $messageStack->add(SHPKG_ERROR_MISSING_NAME, 'error'); break; }

    $sql = [
      'package_code' => $package_code,
      'name'         => $name,
      'description'  => db_prepare_input($_POST['description']  ?? ''),
      'weight'       => (float)str_replace(',', '.', $_POST['weight']     ?? 0),
      'max_weight'   => (float)str_replace(',', '.', $_POST['max_weight'] ?? 0),
      'ext_length'   => (float)str_replace(',', '.', $_POST['ext_length'] ?? 0),
      'ext_width'    => (float)str_replace(',', '.', $_POST['ext_width']  ?? 0),
      'ext_height'   => (float)str_replace(',', '.', $_POST['ext_height'] ?? 0),
      'int_length'   => (float)str_replace(',', '.', $_POST['int_length'] ?? 0),
      'int_width'    => (float)str_replace(',', '.', $_POST['int_width']  ?? 0),
      'int_height'   => (float)str_replace(',', '.', $_POST['int_height'] ?? 0),
      'cost'         => (float)str_replace(',', '.', $_POST['cost']       ?? 0),
      'sort_order'   => (int)($_POST['sort_order'] ?? 0),
      'inactive'     => isset($_POST['inactive']) ? '1' : '0',
      'carrier'      => db_prepare_input($_POST['carrier'] ?? ''),
    ];

    if ($_REQUEST['action'] === 'save') {
      $dup = $db->Execute("SELECT id FROM " . TABLE_SHIPPING_PACKAGES
        . " WHERE package_code = '" . db_input($package_code) . "' LIMIT 1");
      if (!$dup->EOF) { $messageStack->add(SHPKG_ERROR_DUP_CODE, 'error'); break; }
      db_perform(TABLE_SHIPPING_PACKAGES, $sql, 'insert');
    } else {
      $dup = $db->Execute("SELECT id FROM " . TABLE_SHIPPING_PACKAGES
        . " WHERE package_code = '" . db_input($package_code) . "' AND id <> $pkgID LIMIT 1");
      if (!$dup->EOF) { $messageStack->add(SHPKG_ERROR_DUP_CODE, 'error'); break; }
      db_perform(TABLE_SHIPPING_PACKAGES, $sql, 'update', "id = $pkgID");
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT,
      gen_get_all_get_params(['pkgID', 'action']), 'SSL'));
    break;

  case 'delete':
    validate_security($security_level, 4);
    $db->Execute("DELETE FROM " . TABLE_SHIPPING_PACKAGES . " WHERE id = $pkgID");
    gen_redirect(html_href_link(FILENAME_DEFAULT,
      gen_get_all_get_params(['pkgID', 'action']), 'SSL'));
    break;

  default:
}

$where     = $carrier_filter !== '' ? " WHERE carrier = '" . db_input($carrier_filter) . "'" : '';
$query_raw = "SELECT SQL_CALC_FOUND_ROWS * FROM " . TABLE_SHIPPING_PACKAGES . $where
           . " ORDER BY sort_order ASC, package_code ASC";
$query_result = $db->Execute($query_raw,
  (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)) . ',' . MAX_DISPLAY_SEARCH_RESULTS);
$query_split  = new splitPageResults($_REQUEST['list'], '');
if ($query_split->current_page_number <> $_REQUEST['list']) {
  $_REQUEST['list'] = $query_split->current_page_number;
  $query_result = $db->Execute($query_raw,
    (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)) . ',' . MAX_DISPLAY_SEARCH_RESULTS);
  $query_split  = new splitPageResults($_REQUEST['list'], '');
}

$selected_pkg = null;
if ($pkgID) {
  $r = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_PACKAGES . " WHERE id = $pkgID LIMIT 1");
  if (!$r->EOF) $selected_pkg = $r->fields;
}

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', SHPKG_HEADING_TITLE);
