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
//  Path: /modules/audit/pages/main/template_main.php
//
echo html_form('audit', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('subject', $subject) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=import_export', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('export', 'onclick="exportAudit()"');
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex justify-content-center mt-3">
  <div class="card fb-card" style="max-width:520px">
    <div class="card-header py-1 fb-widget-header"><?php echo TEXT_EXPORT; ?></div>
    <div class="card-body py-2">
      <div style="display:grid;grid-template-columns:200px 1fr;gap:6px 8px;align-items:center">
        <label><?php echo TEXT_START_DATE; ?></label>
        <div><?php echo html_calendar_field($cal_from); ?></div>
        <label><?php echo TEXT_END_DATE; ?></label>
        <div><?php echo html_calendar_field($cal_to); ?></div>
        <label><?php echo TEXT_ALL_OR_ACTIVE; ?></label>
        <div><?php echo html_pull_down_menu('select', $sel_options, 1, 'class="form-select form-select-sm"'); ?></div>
      </div>
    </div>
  </div>
</div>
<iframe name="download_frame" style="display:none;"></iframe>
</form>
