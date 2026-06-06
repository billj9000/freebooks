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
//  Path: /modules/phreebooks/pages/admin/template_main.php
//
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
echo html_hidden_field('subject','') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                     $toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<?php
$active_tab = isset($default_tab_id) ? $default_tab_id : 'general';
$tab_list = array(
  'general'           => TEXT_GENERAL,
  'customers'         => MENU_HEADING_CUSTOMERS,
  'vendors'           => MENU_HEADING_VENDORS,
  'chart_of_accounts' => GL_POPUP_WINDOW_TITLE,
  'tax_auths'         => SETUP_TITLE_TAX_AUTHS,
  'tax_auths_vend'    => SETUP_TITLE_TAX_AUTHS_VEND,
  'tax_rates'         => SETUP_TITLE_TAX_RATES,
  'tax_rates_vend'    => SETUP_TITLE_TAX_RATES_VEND,
);
if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
  $tab_list['custom'] = TEXT_CUSTOM_TAB;
}
$tab_list['stats'] = TEXT_STATISTICS;
?>
<ul class="nav nav-tabs fb-tabs" id="admintabs" role="tablist">
<?php foreach ($tab_list as $key => $label): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($key === $active_tab ? ' active' : ''); ?>" id="tab_<?php echo $key; ?>_btn"
      data-bs-toggle="tab" data-bs-target="#tab_<?php echo $key; ?>" type="button" role="tab"><?php echo $label; ?></button>
  </li>
<?php endforeach; ?>
</ul>
<div class="tab-content fb-tab-content">
<?php
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_general.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_customers.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_vendors.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_chart_of_accounts.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_tax_auths.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_tax_auths_vend.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_tax_rates.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_tax_rates_vend.php');
  if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
    require(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php');
  }
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_stats.php');
?>
</div>
</form>
