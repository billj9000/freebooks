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
//  Path: /modules/audit/pages/admin/template_tab_general.php
//
?>
<div class="tab-pane show active" id="tab_general">
  <div class="card fb-card mt-2">
    <div class="card-header py-1 fb-widget-header"><?php echo MODULE_AUDIT_CONFIG_INFO; ?></div>
    <div class="card-body py-2">
      <div style="display:grid;grid-template-columns:220px 1fr;gap:4px 8px;align-items:center">
        <label><?php echo TEXT_AUDIT_DEBIT_NUMBER; ?></label>
        <div><?php echo html_input_field('audit_debit_number', $_POST['audit_debit_number'] ? $_POST['audit_debit_number'] : AUDIT_DEBIT_NUMBER, 'class="form-control form-control-sm"'); ?></div>
      </div>
    </div>
  </div>
</div>
