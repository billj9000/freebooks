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
//  Path: /modules/phreedom/pages/admin/template_main.php
//
$active_tab = isset($default_tab_id) ? $default_tab_id : 'modules';
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
echo html_hidden_field('subject', '') . chr(10);
echo html_hidden_field('rowSeq',  '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                     $toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<ul class="nav nav-tabs fb-tabs" id="admintabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'modules'  ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_modules"  type="button" role="tab"><?php echo MENU_HEADING_MODULES; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'company'  ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_company"  type="button" role="tab"><?php echo MENU_HEADING_MY_COMPANY; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'config'   ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_config"   type="button" role="tab"><?php echo MENU_HEADING_CONFIG; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'email'    ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_email"    type="button" role="tab"><?php echo MENU_HEADING_EMAIL; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'currency' ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_currency" type="button" role="tab"><?php echo SETUP_TITLE_CURRENCIES; ?></button>
  </li>
  <?php if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) { ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'custom'   ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_custom"   type="button" role="tab"><?php echo TEXT_CUSTOM_TAB; ?></button>
  </li>
  <?php } ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'manager'  ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_manager"  type="button" role="tab"><?php echo BOX_COMPANY_MANAGER; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'tools'    ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_tools"    type="button" role="tab"><?php echo TEXT_TOOLS; ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'stats'    ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_stats"    type="button" role="tab"><?php echo TEXT_STATISTICS; ?></button>
  </li>
  <?php if ($_SESSION['admin_id'] == 1) { ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($active_tab === 'php_info' ? ' active' : ''); ?>" data-bs-toggle="tab" data-bs-target="#tab_php_info" type="button" role="tab">PHP Info</button>
  </li>
  <?php } ?>
</ul>
<div class="tab-content fb-tab-content">
<?php
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_modules.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_company.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_config.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_email.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_currency.php');
  if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
    require (DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php');
  }
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_manager.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_tools.php');
  require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_stats.php');
  if ($_SESSION['admin_id'] == 1) require (DIR_FS_MODULES . $module . '/pages/admin/template_tab_php_info.php');
?>
</div>
</form>
