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
//  Path: /modules/xero_sync/pages/admin/pre_process.php
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
gen_pull_language($module);
gen_pull_language($module, 'admin');
require_once(DIR_FS_WORKING . 'classes/xero_settings.php');

$post_text = fn($name) => trim((string)($_POST[$name] ?? ''));

switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    $due_days  = $post_text('due_days');
    $tolerance = $post_text('rounding_tolerance');
    if (!ctype_digit($due_days) || (int)$due_days > 365) {
      $messageStack->add(XERO_SYNC_ERR_DUE_DAYS, 'error');
      break;
    }
    if (!is_numeric($tolerance) || (float)$tolerance < 0 || (float)$tolerance > 100) {
      $messageStack->add(XERO_SYNC_ERR_TOLERANCE, 'error');
      break;
    }
    $country = $post_text('home_country');
    write_configure('XERO_SYNC_HOME_COUNTRY',       preg_match('/^[A-Z]{3}$/', $country) ? $country : '');
    write_configure('XERO_SYNC_COUNTRY_SOURCE',     in_array($_POST['country_source'] ?? '', xero_settings::COUNTRY_SOURCES, true) ? $_POST['country_source'] : 'shipping');
    write_configure('XERO_SYNC_DUE_DAYS',           (string)(int)$due_days);
    write_configure('XERO_SYNC_ROUNDING_TOLERANCE', (string)(float)$tolerance);
    write_configure('XERO_SYNC_USE_PAYMENT_REF',    isset($_POST['use_payment_ref']) ? '1' : '0');
    write_configure('XERO_SYNC_DEFAULT_ACCOUNT',    mb_substr($post_text('default_account'), 0, 32));
    write_configure('XERO_SYNC_ROUNDING_ACCOUNT',   mb_substr($post_text('rounding_account'), 0, 32));
    write_configure('XERO_SYNC_NO_VAT_TYPE',        mb_substr($post_text('no_vat_type'), 0, 255));

    $accounts = [];
    foreach ((array)($_POST['account'] ?? []) as $gl => $codes) {
      if (is_array($codes)) $accounts[$gl] = [(string)($codes['d'] ?? ''), (string)($codes['f'] ?? '')];
    }
    xero_settings::save_rows('account', $accounts);

    $tax = [];
    foreach ((array)($_POST['tax'] ?? []) as $id => $name) {
      if (is_string($name)) $tax[$id] = [$name, ''];
    }
    xero_settings::save_rows('tax', $tax);

    $contacts = [];
    $names = (array)($_POST['contact_name'] ?? []);
    $xero_names = (array)($_POST['contact_xero'] ?? []);
    foreach ($names as $i => $name) {
      if (is_string($name) && is_string($xero_names[$i] ?? null)) $contacts[$name] = [$xero_names[$i], ''];
    }
    xero_settings::save_rows('contact', $contacts);

    $messageStack->add(XERO_SYNC_SAVED, 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['action']), 'SSL'));
    break;
}

$options      = xero_settings::options();
$mapping      = xero_settings::mapping();
$accounts     = xero_settings::sales_accounts();
$tax_rates    = xero_settings::sales_tax_rates();
$contacts     = xero_settings::rows('contact');
$countries    = array_merge([['id' => '', 'text' => TEXT_NONE]], gen_get_countries());
$can_save     = $security_level > 2;

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', XERO_SYNC_PAGE_TITLE);
