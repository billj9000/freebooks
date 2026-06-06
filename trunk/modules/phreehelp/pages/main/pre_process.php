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
//  Path: /modules/phreehelp/pages/main/pre_process.php
//
$security_level = validate_user(0, true);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/phreehelp.php');
/**************   page specific initialization  *************************/
if (PHREEHELP_FORCE_RELOAD == '1') { // load/reload db tables if forced to
  synchronize();
  write_configure('PHREEHELP_FORCE_RELOAD', '0');
}
$context_ref = isset($_GET['idx'])          ? $_GET['idx']          : '';
$search_text = isset($_GET['search_text'])  ? $_GET['search_text']  : '';
$start_page  = DOC_ROOT_URL;
if ($context_ref) {
  $result = $db->Execute("select doc_url from " . TABLE_PHREEHELP . " where doc_pos = '" . $context_ref . "'");
  if ($result->RecordCount() > 0) $start_page = $result->fields['doc_url'];
}
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/main/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/*****************   prepare to display templates  *************************/
$include_header   = false;
$include_footer   = false;
$custom_html      = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', HEADING_TITLE);

?>