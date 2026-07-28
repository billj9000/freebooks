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
//  Path: /modules/vin_lookup/config.php
//

// Release History
// 1.00 => 2026-07-28 - Initial release: VIN lookup by registration via Vehicle Data Global, local caching

define('MODULE_VIN_LOOKUP_VERSION', 1.00);

// Security ID (unique across all modules; 220 sits between doc_ctl's 210 and linkpoint's 310)
define('SECURITY_ID_VIN_LOOKUP', 220);

// Database table
define('TABLE_VIN_LOOKUP_CACHE', DB_PREFIX . 'vin_lookup_cache');

if (defined('MODULE_VIN_LOOKUP_STATUS')) {
  gen_pull_language('vin_lookup');

  $mainmenu['tools']['submenu']['vin_lookup'] = array(
    'order'                  => 60,
    'text'                   => BOX_VIN_LOOKUP_TITLE,
    'security_id'            => SECURITY_ID_VIN_LOOKUP,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=vin_lookup&amp;page=main', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );

  // Register the settings page under Company > Configuration when the module is active
  if (isset($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION])
      && $_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] > 0) {
    gen_pull_language('vin_lookup', 'admin'); // loads MODULE_VIN_LOOKUP_TITLE
    $mainmenu['company']['submenu']['configuration']['submenu']['vin_lookup'] = array(
      'order'                  => MODULE_VIN_LOOKUP_TITLE,
      'text'                   => MODULE_VIN_LOOKUP_TITLE,
      'security_id'            => SECURITY_ID_CONFIGURATION,
      'link'                   => html_href_link(FILENAME_DEFAULT, 'module=vin_lookup&amp;page=admin', 'SSL'),
      'show_in_users_settings' => false,
      'params'                 => '',
    );
  }
}
