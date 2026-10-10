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
//  Path: /modules/assets/pages/admin/pre_process.php
//
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
require_once(DIR_FS_WORKING . 'classes/install.php');
require_once(DIR_FS_WORKING . 'classes/assets_tabs.php');
require_once(DIR_FS_WORKING . 'classes/assets_fields.php');
/**************   page specific initialization  *************************/
$error  = false; 
$install= new assets_admin();
$tabs   = new assets_tabs();
$fields = new assets_fields();
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'delete':
	if ($security_level < 4) {
	  $messageStack->add(ERROR_NO_PERMISSION,'error');
	  break;
	}
    $subject = $_POST['subject'];
    $id      = (int)$_POST['rowSeq']; // used in btn_delete()'s SQL
	// $$subject is a variable-variable method call — restrict it to the known
	// objects so a crafted subject can't reach an arbitrary variable.
	if (!in_array($subject, array('tabs', 'fields'), true) || !$id) break;
    $$subject->btn_delete($id);
	break;
  case 'go_first':    $_REQUEST['list'] = 1;       break;
  case 'go_previous': $_REQUEST['list'] = max($_REQUEST['list']-1, 1); break;
  case 'go_next':     $_REQUEST['list']++;         break;
  case 'go_last':     $_REQUEST['list'] = 99999;   break;
  case 'search':
  case 'search_reset':
  case 'go_page':
  default:
}

/*****************   prepare to display templates  *************************/
// build some general pull down arrays
$sel_yes_no = array(
 array('id' => '0', 'text' => TEXT_NO),
 array('id' => '1', 'text' => TEXT_YES),
);

$use_bootstrap_nav = true;
$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', BOX_ASSETS_ADMIN);

?>