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
//  Path: /modules/api/pages/admin/pre_process.php
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);

gen_pull_language($module);
require_once(DIR_FS_WORKING . 'defaults.php');

$error   = false;
$api_keys = [];
$company  = db_input($_SESSION['company']);

// ── Actions ───────────────────────────────────────────────────────────────
switch ($_REQUEST['action']) {
  case 'delete':
    if ($security_level < 3) {
      $messageStack->add(ERROR_NO_PERMISSION, 'error');
      break;
    }
    if (!empty($_POST['key_id'])) {
      $kid = (int)$_POST['key_id'];
      $db->Execute("DELETE FROM " . TABLE_API_KEYS . " WHERE id = $kid AND company = '$company'");
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['action']), 'SSL'));
    break;
}

// ── Load key list for this company ────────────────────────────────────────
$result = $db->Execute(
  "SELECT id, key_prefix, label, scopes, enabled, created, last_used
   FROM " . TABLE_API_KEYS . " WHERE company = '$company' ORDER BY created DESC"
);
while (!$result->EOF) {
  $api_keys[] = $result->fields;
  $result->MoveNext();
}

// ── Page setup ────────────────────────────────────────────────────────────
$use_bootstrap_nav = true;
$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', API_PAGE_TITLE);
