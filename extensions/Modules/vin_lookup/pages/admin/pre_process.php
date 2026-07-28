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
//  Path: /modules/vin_lookup/pages/admin/pre_process.php
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);

gen_pull_language('vin_lookup', 'admin');
require_once(DIR_FS_WORKING . 'classes/install.php');
$install = new vin_lookup_admin();

switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    foreach ($install->keys as $key => $default) {
      $field = strtolower($key);
      if (isset($_POST[$field])) write_configure($key, db_prepare_input($_POST[$field]));
    }
    $messageStack->add(VIN_TEXT_SETTINGS_SAVED, 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
    break;
}

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', MODULE_VIN_LOOKUP_TITLE);
