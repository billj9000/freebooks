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
//  Path: /modules/quickbooks_sync/config.php
//

define('MODULE_QUICKBOOKS_SYNC_VERSION', '1.0.0');

// Who can export and send documents to QuickBooks. The settings page uses SECURITY_ID_CONFIGURATION.
define('SECURITY_ID_QUICKBOOKS_SYNC', 240);

define('TABLE_QUICKBOOKS_SYNC_MAP', DB_PREFIX . 'quickbooks_sync_map');

if (defined('MODULE_QUICKBOOKS_SYNC_STATUS')) {
  if (isset($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION]) && $_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] > 0) {
    gen_pull_language('quickbooks_sync', 'admin');
    $mainmenu["company"]['submenu']["configuration"]['submenu']["quickbooks_sync"] = array(
      'order'                  => MODULE_QUICKBOOKS_SYNC_TITLE,
      'text'                   => MODULE_QUICKBOOKS_SYNC_TITLE,
      'security_id'            => SECURITY_ID_CONFIGURATION,
      'link'                   => html_href_link(FILENAME_DEFAULT, 'module=quickbooks_sync&amp;page=admin', 'SSL'),
      'show_in_users_settings' => false,
      'params'                 => '',
    );
  }
}
