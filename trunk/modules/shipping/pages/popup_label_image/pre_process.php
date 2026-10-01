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
//  Path: /modules/shipping/pages/popup_label_image/pre_process.php
//
$security_level = validate_user(0, true);
/**************   page specific initialization  *************************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/shipping.php');
$todo   = $_REQUEST['action'];
// All three build a file path: an installed method, a Y-m-d date and a bare label name.
$method = (string)($_GET['method'] ?? '');
$date   = (string)($_GET['date'] ?? '');
$label  = (string)($_GET['label'] ?? '');
if (!shipping_method_is_valid($method) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^[A-Za-z0-9_\-]+$/', $label)) $label = '';
$date   = explode('-', $date);
switch ($todo) {
  case 'notify':
  default:
	$image = (!$label) ? SHIPPING_TEXT_NO_LABEL : '';
	// show the form with a button to download
	break;
  case 'download':
	if (!$label) { $image = SHIPPING_TEXT_NO_LABEL; break; }
	$file_path = SHIPPING_DEFAULT_LABEL_DIR.$method.'/'.$date[0].'/'.$date[1].'/'.$date[2].'/';
	$file_name = $label . '.lpt';
	if (file_exists($file_path . $file_name)) {
	  $file_size = filesize($file_path . $file_name);
	  $handle    = fopen($file_path . $file_name, "r");
	  $image     = fread($handle, $file_size);
	  fclose($handle);
	  header('Content-type: application/octet-stream');
	  header('Content-Length: ' . $file_size);
	  header('Content-Disposition: attachment; filename=' . $file_name);
	  header('Expires: 0');
	  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	  header('Pragma: public');
	  echo $image;
	  exit();
	} else {
	  $image = SHIPPING_TEXT_NO_LABEL;
	}
	break;
}
$custom_html      = true;
$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
?>