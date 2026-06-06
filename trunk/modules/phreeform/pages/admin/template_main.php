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
//  Path: /modules/phreeform/pages/admin/template_main.php
//
$active_tab = isset($default_tab_id) ? $default_tab_id : 'general';
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                     $toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) {
  foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
}
if ($security_level > 3 && db_table_exists(DB_PREFIX.'reports')) {
  $toolbar->icon_list['new_rpt'] = array(
    'show'   => true,
    'icon'   => 'phreebooks/phreebooks_logo.png',
    'params' => 'onclick="submitToDo(\'convert\')"',
    'text'   => PB_CONVERT_REPORTS,
    'order'  => '30',
  );
}
$toolbar->add_help('11.01.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<ul class="nav nav-tabs fb-tabs" id="admintabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'general' ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_general" type="button" role="tab"><?php echo TEXT_GENERAL; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'tools'   ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_tools"   type="button" role="tab"><?php echo TEXT_TOOLS; ?></button>
  </li>
  <?php if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) { ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'custom'  ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_custom"  type="button" role="tab"><?php echo TEXT_CUSTOM_TAB; ?></button>
  </li>
  <?php } ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'stats'   ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_stats"   type="button" role="tab"><?php echo TEXT_STATISTICS; ?></button>
  </li>
</ul>
<div class="tab-content fb-tab-content">
<?php
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_general.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_tools.php');
  if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
    require (DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php');
  }
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_stats.php');
?>
</div>
</form>
