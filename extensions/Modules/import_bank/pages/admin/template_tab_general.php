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
//  Path: /modules/import_bank/pages/admin/template_tab_general.php
//
?>
<div class="tab-pane show active" id="tab_general">
  <div class="card fb-card mt-2">
    <div class="card-header py-1 fb-widget-header"><?php echo MODULE_BANK_IMPORT_CONFIG_INFO; ?></div>
    <div class="card-body py-2">
      <div style="display:grid;grid-template-columns:220px 1fr;gap:4px 8px;align-items:center">
        <label><?php echo BANK_IMPORT_QUESTION_POSTS; ?></label>
        <div><?php echo html_pull_down_menu('question_posts', $all_chart, $_POST['question_posts'] ? $_POST['question_posts'] : QUESTION_POSTS, 'class="form-select form-select-sm"'); ?></div>
        <label><?php echo BANK_IMPORT_DEBIT_CREDIT; ?></label>
        <div><?php echo html_input_field('debit_credit_description', $_POST['debit_credit_description'] ? $_POST['debit_credit_description'] : DEBIT_CREDIT_DESCRIPTION, 'class="form-control form-control-sm"'); ?></div>
      </div>
    </div>
  </div>
</div>
