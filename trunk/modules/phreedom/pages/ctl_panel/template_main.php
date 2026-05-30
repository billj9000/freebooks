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
//  Path: /modules/phreedom/pages/ctl_panel/template_main.php
//
echo html_form('cpanel', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '&mID=' . $menu_id, 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.09.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo CP_ADD_REMOVE_BOXES; ?></h1>
<table class="table table-sm table-bordered" style="max-width:600px">
  <thead class="fb-widget-header">
    <tr>
      <th class="text-center" style="width:60px"><?php echo TEXT_SHOW; ?></th>
      <th><?php echo TEXT_TITLE; ?></th>
      <th><?php echo TEXT_DESCRIPTION; ?></th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($dashboards as $dashboard) {
  load_method_language(DIR_FS_MODULES . $dashboard['module_id'] . '/dashboards/', $dashboard['dashboard_id']);
  require_once        (DIR_FS_MODULES . $dashboard['module_id'] . '/dashboards/' . $dashboard['dashboard_id'] . '/' . $dashboard['dashboard_id'] . '.php');
  $dashboard = new $dashboard['dashboard_id'];
  echo $dashboard->pre_install(true, $my_profile);
}
?>
  </tbody>
</table>
</form>
