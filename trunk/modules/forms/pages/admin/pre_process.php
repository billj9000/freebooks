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
//  Path: /modules/forms/pages/admin/pre_process.php
//
/**
 * Forms module settings (design §11.16).
 *
 * Reached from Company -> Module Administration, which links every installed
 * module's `page=admin` from the properties icon. One setting so far: whether
 * the Print buttons open Forms or PhreeForm.
 *
 * It is a page rather than a line in the installer because the answer changes
 * during the transition and has to be reversible without a deploy — that is the
 * whole reason the switch exists rather than the buttons simply being repointed.
 */

$security_level = validate_user(SECURITY_ID_CONFIGURATION);

/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_WORKING . 'classes/install.php');

/**************   page specific initialization  *************************/
$install = new forms_admin();

/***************   Act on the action request   *************************/
switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    // Same shape as every other module's settings tab: the installer's key list
    // is the list of settings, so a key cannot be added in one place and
    // forgotten in the other.
    foreach ($install->keys as $key => $default) {
      $field = strtolower($key);
      if (isset($_POST[$field])) write_configure($key, db_prepare_input($_POST[$field]));
    }
    $messageStack->add(GENERAL_CONFIG_SAVED, 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
    break;
  default:
}

/*****************   prepare to display templates  *************************/
$forms_print_choices = array(
  array('id' => '0', 'text' => FORMS_ADMIN_YES_NO_OFF),
  array('id' => '1', 'text' => FORMS_ADMIN_YES_NO_ON),
);

// An installation enabled before this key existed has no row for it, so the
// constant is undefined and the pull-down would select nothing. Off is both the
// shipped default and what such an installation is actually doing.
$forms_print_buttons = isset($_POST['module_forms_print_buttons'])
                     ? (string)$_POST['module_forms_print_buttons']
                     : (defined('MODULE_FORMS_PRINT_BUTTONS') ? (string)MODULE_FORMS_PRINT_BUTTONS : '0');

$include_header    = true;
$include_footer    = true;
$use_bootstrap_nav = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', FORMS_ADMIN_TITLE);
