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
//  Path: /modules/phreedom/pages/main/template_crash.php
//
echo html_form('crash', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-4">
<div class="card fb-card" style="max-width:480px;width:100%">
  <div class="card-header py-1 fb-widget-header"><?php echo HEADING_TITLE_CRASH_TITLE; ?></div>
  <div class="card-body py-3">
    <p class="mb-3"><?php echo HEADING_TITLE_CRASH_INFORMATION; ?></p>
    <div class="text-center">
      <button type="button" class="btn btn-primary btn-sm" onclick="location.href = '<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=main&amp;action=debug', 'SSL'); ?>'"><?php echo HEADING_TITLE_CRASH_BUTTON; ?></button>
    </div>
  </div>
</div>
</div>
</form>
