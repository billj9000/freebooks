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
//  Path: /modules/api/config.php
//

define('MODULE_API_VERSION', '1.0');

// Security ID for access to API key management (unique across all modules; 201 follows ZenCart's 200)
define('SECURITY_ID_API', 201);

// The api_keys table lives in the company DB alongside all other tables.
define('TABLE_API_KEYS', DB_PREFIX . 'api_keys');

// Register the settings page under Company > Configuration when the module is active
if (defined('MODULE_API_STATUS')) {
  if (isset($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION]) && $_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] > 0) {
    gen_pull_language('api', 'admin'); // loads MODULE_API_TITLE
    $mainmenu["company"]['submenu']["configuration"]['submenu']["api"] = array(
      'order'                   => MODULE_API_TITLE,
      'text'                    => MODULE_API_TITLE,
      'security_id'             => SECURITY_ID_CONFIGURATION,
      'link'                    => html_href_link(FILENAME_DEFAULT, 'module=api&amp;page=admin', 'SSL'),
      'show_in_users_settings'  => false,
      'params'                  => '',
    );
  }
}
