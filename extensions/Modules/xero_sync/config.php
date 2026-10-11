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
//  Path: /modules/xero_sync/config.php
//

define('MODULE_XERO_SYNC_VERSION', '1.0.0');

// Who can export and upload documents to Xero. The settings page uses SECURITY_ID_CONFIGURATION.
define('SECURITY_ID_XERO_SYNC', 230);

define('TABLE_XERO_SYNC_MAP',   DB_PREFIX . 'xero_sync_map');
define('TABLE_XERO_SYNC_TOKEN', DB_PREFIX . 'xero_sync_token');

if (defined('MODULE_XERO_SYNC_STATUS')) {
  gen_pull_language('xero_sync', 'admin');
  $mainmenu["customers"]['submenu']["xero_sync"] = array(
    'order'                  => 90,
    'text'                   => MODULE_XERO_SYNC_TITLE,
    'security_id'            => SECURITY_ID_XERO_SYNC,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=xero_sync&amp;page=main', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );
  if (isset($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION]) && $_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] > 0) {
    $mainmenu["company"]['submenu']["configuration"]['submenu']["xero_sync"] = array(
      'order'                  => MODULE_XERO_SYNC_TITLE,
      'text'                   => MODULE_XERO_SYNC_TITLE,
      'security_id'            => SECURITY_ID_CONFIGURATION,
      'link'                   => html_href_link(FILENAME_DEFAULT, 'module=xero_sync&amp;page=admin', 'SSL'),
      'show_in_users_settings' => false,
      'params'                 => '',
    );
  }
}
