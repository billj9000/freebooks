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
//  Path: /modules/labels/pages/print/pre_process.php

$security_level = validate_user(SECURITY_ID_LABELS_PRINT);
$use_bootstrap_nav = true;
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
gen_pull_language('labels');
/**************   page specific initialization  *************************/
define('PAGE_TITLE', TEXT_PRINT_LABELS);

$templates = array();
$result = $db->Execute("SELECT id, name, printer_type FROM " . TABLE_LABEL_TEMPLATES . " ORDER BY name");
while (!$result->EOF) {
  $templates[] = $result->fields;
  $result->MoveNext();
}

$last_template_id = (int)($_SESSION['labels_last_template_id'] ?? 0);
