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
//  Path: /modules/labels/config.php
//
// Release History
// 1.00 => 2026-06-27 - Initial release: label template management, HTML+ZPL dual-path rendering

define('MODULE_LABELS_VERSION', 1.00);

// Security IDs (200-201 — check master doc to avoid overlap)
define('SECURITY_ID_LABELS_MANAGE', 200);
define('SECURITY_ID_LABELS_PRINT',  201);

// Database table
define('TABLE_LABEL_TEMPLATES', DB_PREFIX . 'label_templates');

if (defined('MODULE_LABELS_STATUS')) {
  gen_pull_language('labels');

  $mainmenu['inventory']['submenu']['labels'] = array(
    'order'                  => 90,
    'text'                   => BOX_LABELS_TEMPLATES,
    'security_id'            => SECURITY_ID_LABELS_MANAGE,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=labels&amp;page=main', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );
}
