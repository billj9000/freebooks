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
//  Path: /modules/import_bank/pages/main/template_main.php
//
echo html_form('import_export', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('subject', $subject) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=import_export', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('10');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex justify-content-center mt-3">
  <div class="fb-fieldset mb-2" style="max-width:640px">
    <div class="fb-fieldset-legend"><?php echo TEXT_IMPORT; ?></div>
    <p class="mb-2"><?php echo GEN_BANK_IMPORT_MESSAGE; ?></p>
<?php
foreach ($page_list as $mod => $params) {
  if ($subject <> $mod) continue;
  $structure = $params['structure'];
  if (!$structure->Module->Table) continue;
  if (!is_array($structure->Module->Table)) $structure->Module->Table = array($structure->Module->Table);
  foreach ($structure->Module->Table as $table) {
    if ($table->CanImport) {
?>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <div><?php echo html_pull_down_menu('bank_acct', $cash_chart, '', 'class="form-select form-select-sm"'); ?></div>
        <div><?php echo html_file_field('file_name'); ?></div>
        <div><?php echo html_button_field('import_table', TEXT_IMPORT, 'onclick="submitToDo(\'import_csv\')"'); ?></div>
        <div><?php echo html_button_field('sample_csv', SAMPLE_CSV, 'onclick="downloadSampleCsv()"'); ?></div>
      </div>
<?php
    }
  }
}
?>
  </div>
</div>
<iframe name="download_frame" style="display:none;"></iframe>
</form>
