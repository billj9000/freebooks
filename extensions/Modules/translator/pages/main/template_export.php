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
//  Path: /modules/translator/pages/main/template_export.php
//
echo html_form('translator', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
array_shift($sel_language); // remove 'all' option
?>
<h1><?php echo TEXT_EXPORT_TRANSLATION; ?></h1>
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_EXPORT_TRANSLATION; ?></div>
  <div class="card-body py-2">
    <p><?php echo TRANSLATOR_EXPORT_DESC; ?></p>
    <div class="mb-1">
      <?php echo TRANSLATOR_ISO_EXPORT; ?>
      <?php echo html_pull_down_menu('lang', $sel_language, DEFAULT_LANGUAGE); ?>
    </div>
    <div class="text-end mt-2">
      <?php echo html_button_field('export', TEXT_EXPORT, 'onclick="submitToDo(\'export_all_go\', true)"'); ?>
    </div>
  </div>
</div>
</form>
