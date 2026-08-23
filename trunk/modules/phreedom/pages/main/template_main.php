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
//  Path: /modules/phreedom/pages/main/template_main.php
//
echo $messageStack->output();

// Pre-group dashboard rows by column
$columns_data = [];
while (!$cp_boxes->EOF) {
  $col = (int)$cp_boxes->fields['column_id'];
  $columns_data[$col][] = $cp_boxes->fields;
  $cp_boxes->MoveNext();
}
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="mb-0"><?php echo PAGE_TITLE; ?></h1>
  <a href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=ctl_panel&amp;mID=' . $menu_id, 'SSL'); ?>"
     class="btn btn-sm btn-outline-secondary"><?php echo CP_CHANGE_PROFILE; ?></a>
</div>
<div class="row g-2">
<?php for ($c = 1; $c <= MAX_CP_COLUMNS; $c++): ?>
  <div class="col-md-4" id="col_<?php echo $c; ?>">
    <?php foreach ($columns_data[$c] ?? [] as $box):
      $dashboard  = $box['dashboard_id'];
      $module_id  = $box['module_id'];
      $params     = unserialize($box['params']);
      if (defined('MODULE_' . strtoupper($module_id) . '_STATUS') && file_exists(DIR_FS_MODULES . "$module_id/dashboards/$dashboard/$dashboard.php")):
        load_method_language(DIR_FS_MODULES . "$module_id/dashboards/$dashboard");
        require_once(DIR_FS_MODULES . "$module_id/dashboards/$dashboard/$dashboard.php");
        $new_box            = new $dashboard;
        $new_box->menu_id   = $menu_id;
        $new_box->module_id = $module_id;
        $new_box->column_id = $box['column_id'];
        $new_box->row_id    = $box['row_id'];
        echo $new_box->Output($params);
      endif;
    endforeach; ?>
  </div>
<?php endfor; ?>
</div>
