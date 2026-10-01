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
//  Path: /modules/vin_lookup/pages/main/pre_process.php
//
$security_level = validate_user(SECURITY_ID_VIN_LOOKUP);

gen_pull_language('vin_lookup');
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'classes/vin_lookup.php');

switch ($_REQUEST['action'] ?? '') {
  case 'delete':
    validate_security($security_level, 4);
    // A change: POST only, where index.php checks the csrf_token.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'id')), 'SSL'));
      break;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $db->Execute("DELETE FROM " . TABLE_VIN_LOOKUP_CACHE . " WHERE id = " . $id);
      $messageStack->add(VIN_TEXT_DELETED, 'success');
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'id')), 'SSL'));
    break;
}

// Recent cached lookups for the history card
$recent = VinLookup::recent();

$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', VIN_PAGE_TITLE);
