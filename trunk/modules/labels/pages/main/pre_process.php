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
//  Path: /modules/labels/pages/main/pre_process.php

$security_level = validate_user(SECURITY_ID_LABELS_MANAGE);
$use_bootstrap_nav = true;
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
gen_pull_language('labels');
/**************   page specific initialization  *************************/
$error     = false;
$processed = false;
/***************   Act on the action request   *************************/
switch ($_REQUEST['action'] ?? '') {
  case 'delete':
    validate_security($security_level, 4);
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
      $db->Execute("DELETE FROM " . TABLE_LABEL_TEMPLATES . " WHERE id = " . $id);
      $messageStack->add(TEXT_LABEL_DELETED, 'success');
    }
    gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=labels&page=main', 'SSL'));
    break;
}
define('PAGE_TITLE', TEXT_LABEL_TEMPLATES);
// Load all templates for listing
$templates = array();
$result = $db->Execute("SELECT id, name, width_mm, height_mm, printer_type, date_modified FROM " . TABLE_LABEL_TEMPLATES . " ORDER BY name");
while (!$result->EOF) {
  $templates[] = $result->fields;
  $result->MoveNext();
}
