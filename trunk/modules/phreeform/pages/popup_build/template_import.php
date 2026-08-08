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
//  Path: /modules/phreeform/pages/popup_build/template_import.php
//
echo html_form('popup_build', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"') . chr(10);
echo html_hidden_field('action',      '')         . chr(10);
echo html_hidden_field('id',          $id)        . chr(10);
echo html_hidden_field('parent_id',   $parent_id) . chr(10);
echo html_hidden_field('doc_title',   '')         . chr(10);
echo html_hidden_field('folder_path', '/');
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close();"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->add_help('11.02');
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-3">
<div class="d-inline-flex flex-column gap-2" style="min-width:480px;max-width:640px;width:100%">

  <div class="card fb-card">
    <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
    <div class="card-body py-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span><?php echo TEXT_ENTER_SEARCH_PARAMS_A; ?></span>
        <?php echo html_pull_down_menu('mod',  $sel_modules,  $def_module,  'class="form-select form-select-sm" style="width:auto"'); ?>
        <span><?php echo TEXT_ENTER_SEARCH_PARAMS_B; ?></span>
        <?php echo html_pull_down_menu('lang', $sel_language, $def_lang,    'class="form-select form-select-sm" style="width:auto"'); ?>
        <span><?php echo TEXT_ENTER_SEARCH_PARAMS_C; ?></span>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('refresh_dir')"><?php echo TEXT_SEARCH; ?></button>
      </div>
    </div>
  </div>

  <div class="card fb-card">
    <div class="card-header py-1 fb-widget-header"><?php echo PHREEFORM_DEFIMP; ?></div>
    <div class="card-body py-2">
      <div class="mb-2"><?php echo ReadDefReports('RptFileName', $import_path); ?></div>
      <div class="mb-2">
        <label class="form-label small mb-1"><?php echo PHREEFORM_RPTBROWSE; ?></label>
        <?php echo html_file_field('reportfile', 'class="form-control form-control-sm"'); ?>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1"><?php echo PHREEFORM_RPTENTER; ?> <span class="text-muted"><?php echo PHREEFORM_RPTNOENTER; ?></span></label>
        <?php echo html_input_field('reportname', $reportname ?? '', 'class="form-control form-control-sm" maxlength="30"'); ?>
      </div>
      <div class="text-end">
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_one')"><?php echo TEXT_IMPORT_ONE; ?></button>
      </div>
    </div>
  </div>

  <div class="card fb-card">
    <div class="card-body py-2">
      <div class="d-flex justify-content-between align-items-center">
        <span><?php echo TEXT_IMPORT_ALL_DESC; ?></span>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_all')"><?php echo TEXT_IMPORT_ALL; ?></button>
      </div>
    </div>
  </div>

</div>
</div>
</form>
