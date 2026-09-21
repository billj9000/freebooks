<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/phreedom/pages/profile/pre_process.php
//
$security_level = validate_user(SECURITY_ID_MY_PROFILE);
gen_pull_language($module, 'admin');
require_once(DIR_FS_WORKING . 'functions/phreedom.php');
require_once(DIR_FS_WORKING . 'functions/webauthn.php');

$error = false;
$result = $db->Execute("SELECT admin_prefs FROM " . TABLE_USERS . " WHERE admin_id = " . (int)$_SESSION['admin_id']);
$prefs  = unserialize($result->fields['admin_prefs']);
$pk_credentials = wa_enabled() ? wa_list_credentials((int)$_SESSION['admin_id']) : array();

$custom_path = DIR_FS_WORKING . 'custom/pages/profile/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }

switch ($_REQUEST['action']) {
  case 'save':
    validate_security($security_level, 4);
    // Both name directories: the theme one that template_index.php require_once()s
    // config.php and menu_bs.php from, the colour one a stylesheet is linked
    // from. Only an installed pair is stored.
    $prefs['theme']      = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['theme'] ?? ''));
    $prefs['colors']     = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['colors'] ?? ''));
    $prefs['menu_hover'] = (($_POST['menu_hover'] ?? '') === '1') ? 1 : 0;
    if (!$prefs['colors'] || !$prefs['theme']
        || !is_dir(DIR_FS_THEMES . $prefs['theme'])
        || !is_dir(DIR_FS_THEMES . $prefs['theme'] . '/css/' . $prefs['colors'])) {
      $error = $messageStack->add(GEN_ERROR_NO_THEME_COLORS, 'error');
      break;
    }
    db_perform(TABLE_USERS, array('admin_prefs' => serialize($prefs)), 'update', 'admin_id = ' . (int)$_SESSION['admin_id']);
    $_SESSION['admin_prefs']['theme']      = $prefs['theme'];
    $_SESSION['admin_prefs']['colors']     = $prefs['colors'];
    $_SESSION['admin_prefs']['menu_hover'] = $prefs['menu_hover'];
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(), 'SSL'));
    break;
  default:
}

$include_header    = true;
$include_footer    = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', BOX_HEADING_PROFILE);
?>
