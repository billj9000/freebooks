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
//  Path: /modules/contacts/pages/admin/template_main.php
//
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action',  '') . chr(10);
echo html_hidden_field('subject', '') . chr(10);
echo html_hidden_field('rowSeq',  '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                      $toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<ul class="nav nav-tabs fb-tabs" id="admintabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_postcode_lookup" type="button" role="tab"><?php echo TEXT_POSTCODE_LOOKUP; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_add_book"         type="button" role="tab"><?php echo TEXT_ADDRESS_BOOK; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_departments"      type="button" role="tab"><?php echo HR_POPUP_WINDOW_TITLE; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_dept_types"       type="button" role="tab"><?php echo SETUP_TITLE_DEPT_TYPES; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_project_costs"    type="button" role="tab"><?php echo SETUP_TITLE_PROJECTS_COSTS; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_project_phases"   type="button" role="tab"><?php echo SETUP_TITLE_PROJECTS_PHASES; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_tabs"             type="button" role="tab"><?php echo TEXT_EXTRA_TABS; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_fields"           type="button" role="tab"><?php echo TEXT_EXTRA_FIELDS; ?></button></li>
<?php if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')): ?>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_custom"           type="button" role="tab"><?php echo TEXT_CUSTOM_TAB; ?></button></li>
<?php endif; ?>
  <li class="nav-item" role="presentation"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab_stats"            type="button" role="tab"><?php echo TEXT_STATISTICS; ?></button></li>
</ul>
<div class="tab-content fb-tab-content">
<?php
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_postcode_lookup.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_add_book.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_departments.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_dept_types.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_project_costs.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_project_phases.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_tabs.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_fields.php');
  if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
    require(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php');
  }
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_stats.php');
?>
</div>
</form>
