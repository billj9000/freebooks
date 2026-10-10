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
// |                                                                 |
// | The license that is bundled with this package is located in the |
// | file: /doc/manual/ch01-Introduction/license.html.               |
// | If not, see http://www.gnu.org/licenses/                        |
// +-----------------------------------------------------------------+
//  Path: /modules/import_bank/pages/admin/pre_process.php
//

/**************   Check user security   *****************************/
$security_level = validate_user(SECURITY_ID_CONFIGURATION);

/**************  include page specific files    *********************/
gen_pull_language($module);
gen_pull_language($module, 'admin');
require_once(DIR_FS_WORKING . 'classes/install.php');
require_once(DIR_FS_WORKING . 'classes/known_transactions.php');
/**************   page specific initialization  *************************/
$error   = false; 
$install = new import_bank_admin();
$kt      = new known_transactions();
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
	validate_security($security_level, 4);
  	// save general tab
	foreach ($install->keys as $key => $default) {
	  $field = strtolower($key);
      if (isset($_POST[$field])) write_configure($key, $_POST[$field]);
    }
	$messageStack->add(IMPORT_BANK_CONFIG_SAVED, 'success');
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
    break;
  case 'delete':
	validate_security($security_level, 4); // security check
    // Only this page's own subjects; the delete link sends the class code.
    $subjects = array('kt' => $kt, 'known_transactions' => $kt);
    $subject = (string)$_POST['subject'];
    $id      = (int)$_POST['rowSeq'];
	if (!isset($subjects[$subject]) || !$id) break;
    $subjects[$subject]->btn_delete($id);
	break;
  default:
}

/*****************   prepare to display templates  *************************/
// build some general pull down arrays
$all_chart = gen_coa_pull_down(2, false, true, false);    
$use_bootstrap_nav = true;
$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', BOX_BANK_IMPORT_ADMIN);

?>