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
//  Path: /modules/phreebooks/pages/admin/template_tab_general.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'general' ? ' show active' : ''); ?>" id="tab_general" role="tabpanel">
  <div class="card mt-2 mb-2" style="border-color:#a6c9e2">
    <div class="card-body p-2">
      <div class="text-center fw-bold py-1" style="font-size:11px"><?php echo TEXT_OPTIONS; ?></div>
      <div class="py-1 px-3" style="font-size:11px">
        <div style="display:table; width:100%">
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo CD_13_01_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('auto_update_period', $sel_yes_no, $_POST['auto_update_period'] ? $_POST['auto_update_period'] : AUTO_UPDATE_PERIOD, ''); ?></div>
          </div>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo CD_13_05_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('show_full_gl_names', $sel_gl_desc, $_POST['show_full_gl_names'] ? $_POST['show_full_gl_names'] : SHOW_FULL_GL_NAMES, ''); ?></div>
          </div>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo CD_01_52_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('round_tax_by_auth', $sel_yes_no, $_POST['round_tax_by_auth'] ? $_POST['round_tax_by_auth'] : ROUND_TAX_BY_AUTH, ''); ?></div>
          </div>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo CD_01_55_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('enable_bar_code_readers', $sel_yes_no, $_POST['enable_bar_code_readers'] ? $_POST['enable_bar_code_readers'] : ENABLE_BAR_CODE_READERS, ''); ?></div>
          </div>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo CD_01_75_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('single_line_order_screen', $sel_order_lines, $_POST['single_line_order_screen'] ? $_POST['single_line_order_screen'] : SINGLE_LINE_ORDER_SCREEN, ''); ?></div>
          </div>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo CD_01_50_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('enable_order_discount', $sel_yes_no, $_POST['enable_order_discount'] ? $_POST['enable_order_discount'] : ENABLE_ORDER_DISCOUNT, ''); ?></div>
          </div>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:4px; width:100%; vertical-align:middle"><?php echo ALLOW_NEGATIVE_INVENTORY_DESC; ?></div>
            <div style="display:table-cell; padding-bottom:4px; vertical-align:middle; white-space:nowrap"><?php echo html_pull_down_menu('allow_negative_inventory', $sel_yes_no, $_POST['allow_negative_inventory'] ? $_POST['allow_negative_inventory'] : ALLOW_NEGATIVE_INVENTORY, ''); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
