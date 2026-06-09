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
//  Path: /modules/import_order/pages/main/template_main.php
//
echo html_form('import_order', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex justify-content-center mt-3">
  <div class="card fb-card" style="max-width:520px">
    <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
    <div class="card-body py-2 d-flex flex-column gap-2">
      <p class="mb-1"><?php echo IMPORT_ORDER_SELECT; ?></p>
      <div class="text-center"><?php echo html_file_field('file_name'); ?></div>
      <p class="mb-1 text-muted small"><?php echo IMPORT_ORDER_DIRECTIONS; ?></p>
      <div><?php echo html_button_field('download_template', TEXT_DOWNLOAD_TEMPLATE, 'onclick="downloadTemplate()"'); ?></div>
    </div>
  </div>
</div>
<iframe name="download_frame" style="display:none;"></iframe>
</form>
