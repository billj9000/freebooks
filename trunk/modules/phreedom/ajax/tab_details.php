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
//  Path: /modules/phreedom/ajax/tab_details.php
//
/**************   Check user security   *****************************/
// Every caller is a module's admin page, which needs the configuration permission.
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
/**************   page specific initialization  *************************/
// Both name the file required below, and $subject is then instantiated as a
// class: word characters only, so no path can lead out of modules/, and only a
// real file is read.
$page    = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_GET['mod'] ?? ''));
$subject = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_GET['subject'] ?? ''));
$rID     = (int)($_REQUEST['rID'] ?? 0);
$xml     = NULL;

if ($page === '' || $subject === '' || !file_exists(DIR_FS_MODULES . $page . '/classes/' . $subject . '.php')) {
  echo createXmlHeader() . xmlEntry('error', GEN_ERROR_BAD_TARGET) . createXmlFooter();
  die;
}
/**************  include page specific files    *********************/
gen_pull_language('phreedom', 'admin');
gen_pull_language($page, 'admin');
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
if (!$_REQUEST['list']) 	$_REQUEST['list'] = 1;
if (!$_REQUEST['action']) 	$_REQUEST['action'] = 'go_first';

require_once(DIR_FS_MODULES . $page . '/classes/' . $subject . '.php');
if (!class_exists($subject, false) || !method_exists($subject, 'build_main_html')) {
  echo createXmlHeader() . xmlEntry('error', GEN_ERROR_BAD_TARGET) . createXmlFooter();
  die;
}
$my_class = new $subject();
$my_class->message = false;

// A change comes as a POST, which index.php has checked for its csrf_token.
if (in_array($_REQUEST['action'], array('delete', 'update'), true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo createXmlHeader() . xmlEntry('error', GEN_ERROR_BAD_TARGET) . createXmlFooter();
  die;
}

switch ($_REQUEST['action']) {
  case 'delete':      if ($rID && method_exists($my_class, 'btn_delete')) $my_class->btn_delete($rID); break;
  case 'update':      if (method_exists($my_class, 'btn_update')) $my_class->btn_update($rID); break;
  case 'go_first':    $_REQUEST['list'] = 1;       break;
  case 'go_previous': $_REQUEST['list'] = max($_REQUEST['list']-1, 1); break;
  case 'go_next':     $_REQUEST['list']++;         break;
  case 'go_last':     $_REQUEST['list'] = 99999;   break;
  case 'go_page':                            break;
}

// put the output together
$xml .= "\t" . xmlEntry("subject",      $subject);
$xml .= "\t" . xmlEntry("htmlContents", "<div>" . $my_class->build_main_html() . "</div>");
if ($my_class->message) $xml .= "\t" . xmlEntry("message", $my_class->message);
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>