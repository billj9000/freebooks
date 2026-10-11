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
require_once(DIR_FS_WORKING . 'classes/quickbooks_api.php');

$post_text = fn($name) => trim((string)($_POST[$name] ?? ''));

$connection = new quickbooks_connection();

// Saves the whole form. Returns false, with a message, when a value is refused.
$save_settings = function () use ($post_text, $connection, $messageStack): bool {
  $due_days  = $post_text('due_days');
  $tolerance = $post_text('rounding_tolerance');
  if (!ctype_digit($due_days) || (int)$due_days > 365) {
    $messageStack->add(QUICKBOOKS_SYNC_ERR_DUE_DAYS, 'error');
    return false;
  }
  if (!is_numeric($tolerance) || (float)$tolerance < 0 || (float)$tolerance > 100) {
    $messageStack->add(QUICKBOOKS_SYNC_ERR_TOLERANCE, 'error');
    return false;
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

  // A blank secret field keeps the saved secret, which the page never shows.
  $secret = $post_text('client_secret');
  $connection->save_client($post_text('environment'), $post_text('client_id'),
                           isset($_POST['clear_secret']) ? '' : ($secret !== '' ? $secret : null));
  return true;
};

$settings_url = html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&page=admin', 'SSL');
switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    if (!$save_settings()) break;
    $messageStack->add(QUICKBOOKS_SYNC_SAVED, 'success');
    gen_redirect($settings_url);
    break;
  case 'connect':
    validate_security($security_level, 3);
    if (!$save_settings()) break;
    try {
      gen_redirect($connection->start());
    } catch (quickbooks_auth_error $e) {
      $messageStack->add(htmlspecialchars($e->getMessage()), 'error');
    }
    break;
  case 'disconnect':
    validate_security($security_level, 3);
    $connection->disconnect();
    unset($_SESSION['quickbooks_sync_lists']);
    $messageStack->add(QUICKBOOKS_SYNC_DISCONNECTED, 'success');
    gen_redirect($settings_url);
    break;
}

// QuickBooks' own items, accounts and VAT codes, offered as suggestions in the mapping fields once
// connected. Read at most every ten minutes.
$link       = $connection->load();
$connected  = $link['refresh_token'] !== '' && $link['realm_id'] !== '';
$qb_lists   = ['items' => [], 'accounts' => [], 'tax_codes' => []];
$lists_error = '';
if ($connected) {
  $cached = $_SESSION['quickbooks_sync_lists'] ?? null;
  if ($cached && $cached['realm_id'] === $link['realm_id'] && $cached['at'] > time() - 600) {
    $qb_lists = $cached['lists'];
  } else {
    try {
      $api = new quickbooks_api($connection);
      foreach ($api->items() as $item)         $qb_lists['items'][]     = ['value' => (string)$item['Name'], 'label' => (string)($item['IncomeAccountRef']['name'] ?? '')];
      foreach ($api->accounts() as $account)   $qb_lists['accounts'][]  = ['value' => (string)$account['Name'], 'label' => (string)($account['AccountType'] ?? '')];
      foreach ($api->tax_codes() as $tax_code) $qb_lists['tax_codes'][] = ['value' => (string)$tax_code['Name'], 'label' => (string)($tax_code['Description'] ?? '')];
      $_SESSION['quickbooks_sync_lists'] = ['realm_id' => $link['realm_id'], 'at' => time(), 'lists' => $qb_lists];
    } catch (Throwable $e) {
      $lists_error = sprintf(QUICKBOOKS_SYNC_QB_LISTS_FAILED, $e->getMessage());
      $connected = $connection->is_connected();
    }
  }
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
