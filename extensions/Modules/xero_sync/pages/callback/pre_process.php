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
//  Path: /modules/xero_sync/pages/callback/pre_process.php
//
// Where Xero returns the browser after the user grants or refuses access. The code Xero sends is
// exchanged for tokens, then the organisation is chosen: straight away when the connection
// reaches one, or on this page when it reaches several.
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
validate_security($security_level, 3);
gen_pull_language($module);
require_once(DIR_FS_WORKING . 'classes/xero_connection.php');

$settings_url = html_href_link(FILENAME_DEFAULT, 'module=xero_sync&page=admin', 'SSL');
$connection   = new xero_connection();
$use_tenant   = function (array $tenant) use ($connection, $messageStack, $settings_url) {
  $connection->set_tenant((string)$tenant['tenantId'], (string)($tenant['tenantName'] ?? ''));
  unset($_SESSION['xero_sync_tenants'], $_SESSION['xero_sync_lists']);
  $messageStack->add(htmlspecialchars(sprintf(XERO_SYNC_CONNECTED_TO, $tenant['tenantName'] ?? '')), 'success');
  gen_redirect($settings_url);
};

if (($_REQUEST['action'] ?? '') === 'tenant') {
  foreach ($_SESSION['xero_sync_tenants'] ?? [] as $tenant) {
    if (hash_equals((string)$tenant['tenantId'], (string)($_POST['tenant_id'] ?? ''))) $use_tenant($tenant);
  }
  $messageStack->add(XERO_SYNC_ERR_NO_TENANT, 'error');
  gen_redirect($settings_url);
}

if (isset($_GET['error'])) {
  unset($_SESSION['xero_sync_oauth']);
  $messageStack->add(htmlspecialchars(sprintf(XERO_SYNC_ERR_XERO_DENIED, $_GET['error_description'] ?? $_GET['error'])), 'error');
  gen_redirect($settings_url);
}
try {
  $tenants = $connection->complete((string)($_GET['code'] ?? ''), (string)($_GET['state'] ?? ''));
} catch (Throwable $e) {
  $messageStack->add(htmlspecialchars($e->getMessage()), 'error');
  gen_redirect($settings_url);
}
if (!$tenants) {
  $messageStack->add(XERO_SYNC_ERR_NO_TENANT, 'error');
  gen_redirect($settings_url);
}
if (count($tenants) === 1) $use_tenant($tenants[0]);
$_SESSION['xero_sync_tenants'] = $tenants;

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', XERO_SYNC_HEAD_CONNECTION);
