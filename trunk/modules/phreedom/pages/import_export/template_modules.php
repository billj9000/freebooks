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
//  Path: /modules/phreedom/pages/import_export/template_modules.php
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

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo TEXT_IMPORT_EXPORT_INFO; ?></div>
  <p><?php echo GEN_IMPORT_EXPORT_MESSAGE; ?></p>
  <table class="pkg-table table table-hover table-sm mb-0">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_MODULE; ?></th>
        <th><?php echo TEXT_TABLE; ?></th>
        <th>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
<?php
foreach ($page_list as $mod => $params) {
  if ($subject <> $mod) continue;
  $structure = $params['structure'];
  if (!$structure->Module->Table) continue;
  foreach ($structure->Module->Table as $table) {
?>
      <tr>
        <td><?php echo TEXT_MODULE . ' - ' . $mod; ?></td>
        <td><?php echo TEXT_TABLE  . ' - ' . $table->Name; ?></td>
        <td>
          <button type="button" class="btn btn-primary btn-sm me-1" onclick="submitToDo('sample_xml_<?php echo $table->Name; ?>')"><?php echo SAMPLE_XML; ?></button>
          <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('sample_csv_<?php echo $table->Name; ?>')"><?php echo SAMPLE_CSV; ?></button>
        </td>
      </tr>
<?php
  }
}
?>
    </tbody>
  </table>
</div>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo TEXT_IMPORT; ?></div>
  <p><?php echo GEN_IMPORT_MESSAGE; ?></p>
  <table class="pkg-table table table-hover table-sm mb-0">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_MODULE; ?></th>
        <th><?php echo TEXT_TABLE; ?></th>
        <th>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
<?php
foreach ($page_list as $mod => $params) {
  if ($subject <> $mod) continue;
  $structure = $params['structure'];
  if (!$structure->Module->Table) continue;
  if (!is_array($structure->Module->Table)) $structure->Module->Table = array($structure->Module->Table);
  foreach ($structure->Module->Table as $table) {
    if ($table->CanImport) {
?>
      <tr>
        <td><?php echo TEXT_MODULE . ' - ' . $mod; ?></td>
        <td><?php echo TEXT_TABLE  . ' - ' . $table->Name; ?></td>
        <td>
          <span class="me-2">
            <?php echo html_radio_field('import_format_' . $table->Name, 'xml', true,  '', ''); ?> <?php echo TEXT_XML; ?>
            <?php echo html_radio_field('import_format_' . $table->Name, 'csv', false, '', ''); ?> <?php echo TEXT_CSV; ?>
          </span>
          <?php echo html_file_field('file_name_' . $table->Name); ?>
          <button type="button" class="btn btn-primary btn-sm ms-1" onclick="submitToDo('import_table_<?php echo $table->Name; ?>')"><?php echo TEXT_IMPORT . ' ' . $table->Name; ?></button>
        </td>
      </tr>
<?php
    }
  }
}
?>
    </tbody>
  </table>
</div>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo TEXT_EXPORT; ?></div>
  <p><?php echo GEN_EXPORT_MESSAGE; ?></p>
  <table class="pkg-table table table-hover table-sm mb-0">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_MODULE; ?></th>
        <th><?php echo TEXT_TABLE; ?></th>
        <th>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
<?php
foreach ($page_list as $mod => $params) {
  if ($subject <> $mod) continue;
  $structure = $params['structure'];
  if (!$structure->Module->Table) continue;
  if (!is_array($structure->Module->Table)) $structure->Module->Table = array($structure->Module->Table);
  foreach ($structure->Module->Table as $table) {
?>
      <tr>
        <td><?php echo TEXT_MODULE . ' - ' . $mod; ?></td>
        <td><?php echo TEXT_TABLE  . ' - ' . $table->Name; ?></td>
        <td>
          <span class="me-2">
            <?php echo html_radio_field('export_format_' . $table->Name, 'xml', true,  '', ''); ?> <?php echo TEXT_XML; ?>
            <?php echo html_radio_field('export_format_' . $table->Name, 'csv', false, '', ''); ?> <?php echo TEXT_CSV; ?>
          </span>
          <button type="button" class="btn btn-primary btn-sm ms-1" onclick="submitToDo('export_table_<?php echo $table->Name; ?>')"><?php echo TEXT_EXPORT . ' ' . $table->Name; ?></button>
        </td>
      </tr>
<?php
  }
}
?>
    </tbody>
  </table>
</div>
</form>
