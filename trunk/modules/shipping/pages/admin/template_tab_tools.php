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
//  Path: /modules/shipping/pages/admin/template_tab_tools.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'tools' ? ' show active' : ''); ?>" id="tab_tools" role="tabpanel">
<div class="fb-fieldset mt-2 mb-2" style="max-width:480px;margin-left:auto;margin-right:auto;">
  <div class="fb-fieldset-legend"><?php echo SHIPPING_TOOLS_TITLE; ?></div>
  <div class="py-2 px-3">
    <p><?php echo SHIPPING_TOOLS_CLEAN_LOG_DESC; ?></p>
    <div style="display:table;width:100%">
      <div style="display:table-row">
        <div style="display:table-cell;text-align:right;padding-right:8px;padding-bottom:4px;white-space:nowrap;vertical-align:middle"><?php echo SHIPPING_METHOD; ?></div>
        <div style="display:table-cell;padding-bottom:4px;vertical-align:middle"><?php echo html_pull_down_menu('carrier', $sel_method, !empty($_POST['carrier']) ? $_POST['carrier'] : ''); ?></div>
      </div>
      <div style="display:table-row">
        <div style="display:table-cell;text-align:right;padding-right:8px;padding-bottom:4px;white-space:nowrap;vertical-align:middle"><?php echo SHIPPING_MONTH; ?></div>
        <div style="display:table-cell;padding-bottom:4px;vertical-align:middle"><?php echo html_pull_down_menu('fy_month', $sel_fy_month, !empty($_POST['fy_month']) ? $_POST['fy_month'] : '01'); ?></div>
      </div>
      <div style="display:table-row">
        <div style="display:table-cell;text-align:right;padding-right:8px;padding-bottom:4px;white-space:nowrap;vertical-align:middle"><?php echo SHIPPING_YEAR; ?></div>
        <div style="display:table-cell;padding-bottom:4px;vertical-align:middle"><?php echo html_pull_down_menu('fy_year', $sel_fy_year, !empty($_POST['fy_year']) ? $_POST['fy_year'] : date('Y')); ?></div>
      </div>
      <div style="display:table-row">
        <div style="display:table-cell;text-align:right;padding-right:8px;padding-bottom:4px;white-space:nowrap;vertical-align:middle"><?php echo GEN_BACKUP_COMP_TYPE; ?></div>
        <div style="display:table-cell;padding-bottom:4px;vertical-align:middle">
          <?php echo html_radio_field('conv_type', 'zip', true,  '', '') . GEN_COMP_ZIP . '&nbsp;&nbsp;&nbsp;&nbsp;'; ?>
          <?php echo html_radio_field('conv_type', 'bz2', false, '', '') . GEN_COMP_BZ2; ?>
        </div>
      </div>
      <div style="display:table-row">
        <div style="display:table-cell;text-align:right;padding-right:8px;padding-bottom:4px;vertical-align:middle">
          <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('backup', true)"><?php echo GEN_ADM_TOOLS_BTN_BACKUP; ?></button>
        </div>
        <div style="display:table-cell;padding-bottom:4px;vertical-align:middle">
          <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo GEN_ADM_TOOLS_BTN_CLEAN_CONFIRM; ?>')) submitToDo('clean')"><?php echo GEN_ADM_TOOLS_BTN_CLEAN; ?></button>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
