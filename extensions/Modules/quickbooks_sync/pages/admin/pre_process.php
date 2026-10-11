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
//  Path: /modules/quickbooks_sync/pages/admin/pre_process.php
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
gen_pull_language($module);
gen_pull_language($module, 'admin');
require_once(DIR_FS_WORKING . 'classes/quickbooks_settings.php');

$post_text = fn($name) => trim((string)($_POST[$name] ?? ''));

switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    $due_days  = $post_text('due_days');
    $tolerance = $post_text('rounding_tolerance');
    if (!ctype_digit($due_days) || (int)$due_days > 365) {
      $messageStack->add(QUICKBOOKS_SYNC_ERR_DUE_DAYS, 'error');
      break;
    }
    if (!is_numeric($tolerance) || (float)$tolerance < 0 || (float)$tolerance > 100) {
      $messageStack->add(QUICKBOOKS_SYNC_ERR_TOLERANCE, 'error');
      break;
    }
    $country = $post_text('home_country');
    write_configure('QUICKBOOKS_SYNC_HOME_COUNTRY',       preg_match('/^[A-Z]{3}$/', $country) ? $country : '');
    write_configure('QUICKBOOKS_SYNC_COUNTRY_SOURCE',     in_array($_POST['country_source'] ?? '', quickbooks_settings::COUNTRY_SOURCES, true) ? $_POST['country_source'] : 'shipping');
    write_configure('QUICKBOOKS_SYNC_DUE_DAYS',           (string)(int)$due_days);
    write_configure('QUICKBOOKS_SYNC_ROUNDING_TOLERANCE', (string)(float)$tolerance);
    write_configure('QUICKBOOKS_SYNC_USE_PAYMENT_REF',    isset($_POST['use_payment_ref']) ? '1' : '0');
    write_configure('QUICKBOOKS_SYNC_DEFAULT_ITEM',       mb_substr($post_text('default_item'), 0, 100));
    write_configure('QUICKBOOKS_SYNC_ROUNDING_ITEM',      mb_substr($post_text('rounding_item'), 0, 100));
    write_configure('QUICKBOOKS_SYNC_DISCOUNT_ACCOUNT',   mb_substr($post_text('discount_account'), 0, 100));
    write_configure('QUICKBOOKS_SYNC_NO_VAT_CODE',        mb_substr($post_text('no_vat_code'), 0, 100));

    $items = [];
    foreach ((array)($_POST['item'] ?? []) as $gl => $names) {
      if (is_array($names)) $items[$gl] = [(string)($names['d'] ?? ''), (string)($names['f'] ?? '')];
    }
    quickbooks_settings::save_rows('item', $items);

    $tax = [];
    foreach ((array)($_POST['tax'] ?? []) as $id => $name) {
      if (is_string($name)) $tax[$id] = [$name, ''];
    }
    quickbooks_settings::save_rows('tax', $tax);

    $customers = [];
    $names = (array)($_POST['customer_name'] ?? []);
    $qb_names = (array)($_POST['customer_qb'] ?? []);
    foreach ($names as $i => $name) {
      if (is_string($name) && is_string($qb_names[$i] ?? null)) $customers[$name] = [$qb_names[$i], ''];
    }
    quickbooks_settings::save_rows('customer', $customers);

    $messageStack->add(QUICKBOOKS_SYNC_SAVED, 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['action']), 'SSL'));
    break;
}

$options      = quickbooks_settings::options();
$mapping      = quickbooks_settings::mapping();
$accounts     = quickbooks_settings::sales_accounts();
$tax_rates    = quickbooks_settings::sales_tax_rates();
$customers    = quickbooks_settings::rows('customer');
$countries    = array_merge([['id' => '', 'text' => TEXT_NONE]], gen_get_countries());
$can_save     = $security_level > 2;

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', QUICKBOOKS_SYNC_PAGE_TITLE);
