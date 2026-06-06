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
//  Path: /modules/phreedom/pages/backup/template_main.php
//
echo html_form('popup_backup', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->icon_list['restore'] = array(
  'show'   => true,
  'icon'   => 'devices/drive-optical.png',
  'params' => 'onclick="if (confirm(\'' . GEN_BACKUP_WARNING . '\')) submitToDo(\'restore\')"',
  'text'   => BOX_HEADING_RESTORE,
  'order'  => 10,
);
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo BOX_HEADING_BACKUP; ?></h1>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_CLEAN_LOG; ?></div>
  <p><?php echo GEN_ADM_TOOLS_CLEAN_LOG_DESC; ?></p>
  <div class="d-flex justify-content-center gap-5 text-center">
    <div>
      <p class="mb-2"><?php echo GEN_ADM_TOOLS_CLEAN_LOG_BACKUP; ?></p>
      <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('backup_log', true)"><?php echo GEN_ADM_TOOLS_BTN_BACKUP; ?></button>
    </div>
    <div>
      <p class="mb-2"><?php echo GEN_ADM_TOOLS_CLEAN_LOG_CLEAN; ?></p>
      <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm('<?php echo addslashes(GEN_ADM_TOOLS_BTN_CLEAN_CONFIRM); ?>')) submitToDo('clean_log')"><?php echo GEN_ADM_TOOLS_BTN_CLEAN; ?></button>
    </div>
  </div>
</div>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo BOX_HEADING_BACKUP; ?></div>
  <p><?php echo GEN_BACKUP_GEN_INFO; ?></p>
  <div class="d-flex flex-wrap justify-content-center gap-3 align-items-start">

    <div class="card fb-card" style="width:fit-content;border:none !important">
      <div class="card-header py-1 fb-widget-header text-center"><?php echo GEN_BACKUP_COMP_TYPE; ?></div>
      <div class="card-body px-3 py-2">
        <div class="d-flex gap-3">
          <div><?php echo html_radio_field('conv_type', 'zip',  true,  '', ''); ?> <?php echo GEN_COMP_ZIP; ?></div>
          <div><?php echo html_radio_field('conv_type', 'bz2',  false, '', ''); ?> <?php echo GEN_COMP_BZ2; ?></div>
          <div><?php echo html_radio_field('conv_type', 'none', false, '', ''); ?> <?php echo GEN_COMP_NONE; ?></div>
        </div>
      </div>
    </div>

    <div class="card fb-card" style="width:fit-content;border:none !important">
      <div class="card-header py-1 fb-widget-header text-center"><?php echo TEXT_OPTIONS; ?></div>
      <div class="card-body px-3 py-2">
        <div class="mb-1"><?php echo html_radio_field('dl_type', 'file', true,  '', ''); ?> <?php echo GEN_BACKUP_DB_ONLY; ?></div>
        <div class="mb-2"><?php echo html_radio_field('dl_type', 'dir',  false, '', ''); ?> <?php echo GEN_BACKUP_FULL; ?></div>
        <div class="d-flex justify-content-between align-items-center gap-3">
          <div><?php echo html_checkbox_field('save_local', '1', false, '', ''); ?> <?php echo GEN_BACKUP_SAVE_LOCAL; ?></div>
          <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('save', true)"><?php echo GEN_BACKUP_ICON_TITLE; ?></button>
        </div>
      </div>
    </div>

  </div>
</div>
</form>
