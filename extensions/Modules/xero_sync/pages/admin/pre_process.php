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
require_once(DIR_FS_WORKING . 'classes/xero_api.php');

$post_text = fn($name) => trim((string)($_POST[$name] ?? ''));

$connection = new xero_connection();

// Saves the whole form. Returns false, with a message, when a value is refused.
$save_settings = function () use ($post_text, $connection, $messageStack): bool {
  $due_days  = $post_text('due_days');
  $tolerance = $post_text('rounding_tolerance');
  if (!ctype_digit($due_days) || (int)$due_days > 365) {
    $messageStack->add(XERO_SYNC_ERR_DUE_DAYS, 'error');
    return false;
  }
  if (!is_numeric($tolerance) || (float)$tolerance < 0 || (float)$tolerance > 100) {
    $messageStack->add(XERO_SYNC_ERR_TOLERANCE, 'error');
    return false;
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

  // A blank secret field keeps the saved secret, which the page never shows.
  $secret = $post_text('client_secret');
  $connection->save_client($post_text('client_id'), isset($_POST['clear_secret']) ? '' : ($secret !== '' ? $secret : null));
  return true;
};

$settings_url = html_href_link(FILENAME_DEFAULT, 'module=xero_sync&page=admin', 'SSL');
switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    if (!$save_settings()) break;
    $messageStack->add(XERO_SYNC_SAVED, 'success');
    gen_redirect($settings_url);
    break;
  case 'connect':
    validate_security($security_level, 3);
    if (!$save_settings()) break;
    try {
      gen_redirect($connection->start());
    } catch (xero_auth_error $e) {
      $messageStack->add(htmlspecialchars($e->getMessage()), 'error');
    }
    break;
  case 'disconnect':
    validate_security($security_level, 3);
    $connection->disconnect();
    unset($_SESSION['xero_sync_lists']);
    $messageStack->add(XERO_SYNC_DISCONNECTED, 'success');
    gen_redirect($settings_url);
    break;
}

// Xero's own accounts and tax rates, offered as suggestions in the mapping fields once connected.
// Read at most every ten minutes.
$link       = $connection->load();
$connected  = $link['refresh_token'] !== '' && $link['tenant_id'] !== '';
$xero_lists = ['accounts' => [], 'tax_rates' => []];
$lists_error = '';
if ($connected) {
  $cached = $_SESSION['xero_sync_lists'] ?? null;
  if ($cached && $cached['tenant_id'] === $link['tenant_id'] && $cached['at'] > time() - 600) {
    $xero_lists = $cached['lists'];
  } else {
    try {
      $api = new xero_api($connection);
      foreach ($api->accounts() as $account) {
        if (($account['Code'] ?? '') !== '') $xero_lists['accounts'][] = ['value' => $account['Code'], 'label' => $account['Name'] ?? ''];
      }
      foreach ($api->tax_rates() as $rate) {
        if (($rate['Status'] ?? '') === 'ACTIVE') $xero_lists['tax_rates'][] = ['value' => $rate['Name'], 'label' => $rate['TaxType'] ?? ''];
      }
      $_SESSION['xero_sync_lists'] = ['tenant_id' => $link['tenant_id'], 'at' => time(), 'lists' => $xero_lists];
    } catch (Throwable $e) {
      $lists_error = sprintf(XERO_SYNC_XERO_LISTS_FAILED, $e->getMessage());
      $connected = $connection->is_connected();
    }
  }
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
