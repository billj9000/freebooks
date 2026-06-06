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
//  Path: /modules/phreedom/pages/import_export/template_main.php
//
echo html_form('import_export', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
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
  <div class="fb-fieldset-legend"><?php echo GL_UTIL_BEG_BAL_LEGEND; ?></div>
  <div class="d-flex justify-content-between align-items-center">
    <span><?php echo GL_UTIL_BEG_BAL_TEXT; ?></span>
    <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('beg_balances')"><?php echo GL_BTN_BEG_BAL; ?></button>
  </div>
</div>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo HEADING_MODULE_IMPORT; ?></div>
  <table class="pkg-table table table-hover table-sm mb-0">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_MODULE; ?></th>
        <th><?php echo TEXT_DESCRIPTION; ?></th>
        <th><?php echo TEXT_VERSION; ?></th>
        <th>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
<?php
if (is_array($page_list)) foreach ($page_list as $key => $value) {
  echo '  <tr>' . chr(10);
  echo '    <td>' . $value['title'] . '</td>' . chr(10);
  echo '    <td>' . constant('MODULE_' . strtoupper($key) . '_DESCRIPTION') . '</td>' . chr(10);
  echo '    <td class="text-center">' . constant('MODULE_' . strtoupper($key) . '_STATUS') . '</td>' . chr(10);
  echo '    <td class="text-center">' . html_icon('actions/go-next.png', TEXT_NEXT, 'large', 'onclick="submitToDo(\'go_' . $key . '\')" style="cursor:pointer"') . '</td>' . chr(10);
  echo '  </tr>' . chr(10);
}
?>
    </tbody>
  </table>
</div>
</form>
