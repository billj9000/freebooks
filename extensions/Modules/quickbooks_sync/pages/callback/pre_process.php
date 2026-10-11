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
//  Path: /modules/quickbooks_sync/pages/callback/pre_process.php
//
// Where Intuit returns the browser after the user grants or refuses access. The code Intuit sends
// is exchanged for tokens for the company it names (realmId), whose name is then read so the
// settings page can show it. The page always returns to the settings page; it draws nothing of
// its own.
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
validate_security($security_level, 3);
gen_pull_language($module);
require_once(DIR_FS_WORKING . 'classes/quickbooks_api.php');

$settings_url = html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&page=admin', 'SSL');
$connection   = new quickbooks_connection();

if (isset($_GET['error'])) {
  unset($_SESSION['quickbooks_sync_oauth']);
  $messageStack->add(htmlspecialchars(sprintf(QUICKBOOKS_SYNC_ERR_QB_DENIED, $_GET['error_description'] ?? $_GET['error'])), 'error');
  gen_redirect($settings_url);
}
try {
  $connection->complete((string)($_GET['code'] ?? ''), (string)($_GET['state'] ?? ''), (string)($_GET['realmId'] ?? ''));
} catch (Throwable $e) {
  $messageStack->add(htmlspecialchars($e->getMessage()), 'error');
  gen_redirect($settings_url);
}
unset($_SESSION['quickbooks_sync_lists']);
// The connection stands even when the name cannot be read; the settings page then shows the
// company's id in its place.
try {
  $name = (new quickbooks_api($connection))->company_name();
  $connection->set_company_name($name);
  $messageStack->add(htmlspecialchars(sprintf(QUICKBOOKS_SYNC_CONNECTED_TO, $name)), 'success');
} catch (Throwable $e) {
  $messageStack->add(htmlspecialchars(sprintf(QUICKBOOKS_SYNC_CONNECTED_NO_NAME, $e->getMessage())), 'caution');
}
gen_redirect($settings_url);
