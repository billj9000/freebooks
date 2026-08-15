<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/forms/pages/admin/template_main.php
//
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
if ($security_level > 2) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                     $toolbar->icon_list['save']['show']   = false;
$toolbar->add_help('11.05');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo FORMS_ADMIN_CONFIG_INFO; ?></div>
  <div class="card-body p-2">
    <table class="table table-borderless table-sm mb-0">
      <tbody>
        <tr>
          <td style="white-space:nowrap"><?php echo MODULE_FORMS_PRINT_BUTTONS_TEXT; ?></td>
          <?php // form-select is width:100% of its cell, so the cell needs a width
                // of its own — 1% collapsed it to a stub with the arrow only. ?>
          <td style="width:14rem"><?php echo html_pull_down_menu('module_forms_print_buttons', $forms_print_choices, $forms_print_buttons, 'class="form-select form-select-sm"'); ?></td>
          <td class="text-muted small"><?php echo MODULE_FORMS_PRINT_BUTTONS_DESC; ?></td>
        </tr>
      </tbody>
    </table>
    <div class="alert alert-warning py-1 px-2 mb-0 small"><?php echo FORMS_ADMIN_PRINT_CAUTION; ?></div>
  </div>
</div>
</form>
