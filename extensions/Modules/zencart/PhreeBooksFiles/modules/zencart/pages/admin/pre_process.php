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
//  Path: /modules/zencart/pages/admin/pre_process.php
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
require_once(DIR_FS_WORKING . 'functions/zencart.php');
require_once(DIR_FS_WORKING . 'classes/install.php');

/**************   page specific initialization  *************************/
$error   = false; 
$install = new zencart_admin();

/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/admin/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
	if ($security_level < 3) {
		$messageStack->add_session(ERROR_NO_PERMISSION,'error');
		gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL')); 
	}
	// Only empty (the company's zone) or a zone PHP knows; anything else would date orders wrongly.
	if (!empty($_POST['zencart_store_timezone']) && !in_array($_POST['zencart_store_timezone'], timezone_identifiers_list(), true)) unset($_POST['zencart_store_timezone']);
	// save general tab
	foreach ($install->keys as $key => $default) {
	  $field = strtolower($key);
      if (isset($_POST[$field])) write_configure($key, $_POST[$field]);
    }
	// Validate payment map: comma-separated, each entry must be zcmodule=fbmodule:glacct
	if (!empty($_POST['zencart_payment_map'])) {
	  foreach (explode(',', $_POST['zencart_payment_map']) as $i => $entry) {
	    $entry = trim($entry);
	    if ($entry === '') continue;
	    if (strpos($entry, '=') === false) {
	      $messageStack->add_session(sprintf(ZENCART_WARN_PAYMENT_MAP_FMT, $i + 1, htmlspecialchars($entry)), 'caution');
	      continue;
	    }
	    $zc_mod = trim(explode('=', $entry, 2)[0]);
	    if ($zc_mod === '') $messageStack->add_session(sprintf(ZENCART_WARN_PAYMENT_MAP_EMPTY, $i + 1), 'caution');
	  }
	}
	// Validate shipping map: pipe-separated, each entry must be zc_module:keyword=fb_module:fb_service
	if (!empty($_POST['zencart_shipping_map'])) {
	  foreach (explode('|', $_POST['zencart_shipping_map']) as $i => $entry) {
	    $entry = trim($entry);
	    if ($entry === '') continue;
	    if (strpos($entry, '=') === false) {
	      $messageStack->add_session(sprintf(ZENCART_WARN_SHIPPING_MAP_FMT, $i + 1, htmlspecialchars($entry)), 'caution');
	      continue;
	    }
	    [$zc_side, $fb_side] = explode('=', $entry, 2);
	    $zc_mod    = trim(explode(':', trim($zc_side), 2)[0]);
	    $fb_module = trim(explode(':', trim($fb_side), 2)[0]);
	    if ($zc_mod === '') {
	      $messageStack->add_session(sprintf(ZENCART_WARN_SHIPPING_MAP_EMPTY, $i + 1), 'caution');
	    }
	    if ($fb_module !== '' && !is_dir(DIR_FS_MODULES . 'shipping/methods/' . $fb_module)) {
	      $messageStack->add_session(sprintf(ZENCART_WARN_SHIPPING_MAP_MODULE, $i + 1, htmlspecialchars($fb_module)), 'caution');
	    }
	  }
	}
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	$messageStack->add(ZENCART_CONFIG_SAVED, 'success');
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
define('PAGE_TITLE', BOX_ZENCART_ADMIN);

?>