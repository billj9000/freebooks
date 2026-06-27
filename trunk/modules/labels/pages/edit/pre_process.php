<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/pages/edit/pre_process.php

$security_level = validate_user(SECURITY_ID_LABELS_MANAGE);
$use_bootstrap_nav = true;
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
gen_pull_language('labels');
/**************   page specific initialization  *************************/
$error     = false;
$processed = false;
$id        = (int)($_REQUEST['id'] ?? 0);
define('PAGE_TITLE', $id > 0 ? TEXT_EDIT_TEMPLATE : TEXT_ADD_TEMPLATE);
// Defaults for new template
$cInfo = array(
  'id'            => 0,
  'name'          => '',
  'width_mm'      => LABELS_DEFAULT_WIDTH_MM,
  'height_mm'     => LABELS_DEFAULT_HEIGHT_MM,
  'printer_type'  => 'html',
  'html_template' => '',
  'zpl_template'  => '',
);
/***************   Act on the action request   *************************/
switch ($_POST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 2);
    $name          = db_prepare_input($_POST['name'] ?? '');
    $width_mm      = (float)($_POST['width_mm']     ?? 0);
    $height_mm     = (float)($_POST['height_mm']    ?? 0);
    $printer_type  = in_array($_POST['printer_type'] ?? '', ['html', 'zpl']) ? $_POST['printer_type'] : 'html';
    $html_template = db_prepare_input($_POST['html_template'] ?? '');
    $zpl_template  = db_prepare_input($_POST['zpl_template']  ?? '');
    $rec_id        = (int)($_POST['id'] ?? 0);

    if (empty($name)) {
      $messageStack->add(TEXT_LABEL_ERROR_NAME, 'error');
      $error = true;
    }
    if ($width_mm <= 0 || $height_mm <= 0) {
      $messageStack->add(TEXT_LABEL_ERROR_SIZE, 'error');
      $error = true;
    }

    if (!$error) {
      $data = array(
        'name'          => $name,
        'width_mm'      => $width_mm,
        'height_mm'     => $height_mm,
        'printer_type'  => $printer_type,
        'html_template' => $html_template,
        'zpl_template'  => $zpl_template ?: 'null',
      );
      if ($rec_id > 0) {
        db_perform(TABLE_LABEL_TEMPLATES, $data, 'update', 'id = ' . $rec_id);
      } else {
        db_perform(TABLE_LABEL_TEMPLATES, $data, 'insert');
      }
      $messageStack->add(TEXT_LABEL_SAVED, 'success');
      gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=labels&page=main', 'SSL'));
    } else {
      // Keep posted values for re-display
      $cInfo = array(
        'id'            => $rec_id,
        'name'          => $name,
        'width_mm'      => $width_mm,
        'height_mm'     => $height_mm,
        'printer_type'  => $printer_type,
        'html_template' => $html_template,
        'zpl_template'  => $zpl_template,
      );
    }
    break;
}

// GET: load existing template
if (($_POST['action'] ?? '') !== 'save' && $id > 0) {
  $result = $db->Execute("SELECT * FROM " . TABLE_LABEL_TEMPLATES . " WHERE id = " . $id);
  if (!$result->EOF) {
    $cInfo = $result->fields;
  }
}
