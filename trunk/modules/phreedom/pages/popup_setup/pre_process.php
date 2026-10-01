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
//  Path: /modules/phreedom/pages/popup_setup/pre_process.php
//
/**************   Check user security   *****************************/
// Checked before the subject class is built: its constructor reads the posted form.
// A refusal leaves the page rather than rendering the form.
$security_level = validate_user(SECURITY_ID_CONFIGURATION);
/**************  include page specific files    *********************/
// Both name paths the require below reads from, and $subject is then
// instantiated as a class. Stripping everything but word characters means no
// path can lead out of modules/; the file check means only a real one is read.
$topic   = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_GET['topic'] ?? ''));
$subject = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_GET['subject'] ?? ''));
if ($topic === '' || $subject === '' || !file_exists(DIR_FS_MODULES . $topic . '/classes/' . $subject . '.php')) {
	$messageStack->add(GEN_ERROR_BAD_TARGET, 'error');
	gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=phreedom&page=main', 'SSL'));
}
gen_pull_language($topic, 'admin');
gen_pull_language($topic);
gen_pull_language('phreedom','admin');
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
require_once(DIR_FS_MODULES . $topic . '/classes/' . $subject . '.php');
/**************   page specific initialization  *************************/
$close_popup    = false;
// The admin classes put it into SQL unquoted: a whole number, or '' for a new
// record. Except where the record's key is text - an account number, which may
// hold letters, dots or dashes - and that class escapes it wherever it is used.
$text_keys      = array('chart_of_accounts');
$id_rule        = function ($v) use ($subject, $text_keys) {
  $v = (string)$v;
  return in_array($subject, $text_keys, true) ? $v : (ctype_digit($v) ? $v : '');
};
$sID            = $id_rule($_GET['sID'] ?? '');
$_GET['sID']    = $sID; // the classes' constructors read it from here too
if (isset($_POST['sID'])) $_POST['sID'] = $id_rule($_POST['sID']);
$subject_module = new $subject();
/***************   hook for custom actions  ***************************/
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
    if ($subject_module->btn_save($sID)) $close_popup = true;
	break;
  default:
}
/*****************   prepare to display templates  *************************/
$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
define('PAGE_TITLE', $subject_module->title);

?>