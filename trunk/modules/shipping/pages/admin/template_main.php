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
//  Path: /modules/shipping/pages/admin/template_main.php
//
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
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
$active_tab = isset($default_tab_id) ? $default_tab_id : 'methods';
$tab_list = array(
  'methods' => TEXT_METHODS,
  'general' => TEXT_GENERAL,
  'tools'   => TEXT_TOOLS,
);
if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
  $tab_list['custom'] = TEXT_CUSTOM_TAB;
}
$tab_list['stats'] = TEXT_STATISTICS;
?>
<ul class="nav nav-tabs fb-tabs" id="admintabs" role="tablist">
<?php foreach ($tab_list as $key => $label): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo ($key === $active_tab ? ' active' : ''); ?>"
      id="tab_<?php echo $key; ?>_btn" data-bs-toggle="tab"
      data-bs-target="#tab_<?php echo $key; ?>" type="button" role="tab"><?php echo $label; ?></button>
  </li>
<?php endforeach; ?>
</ul>
<div class="tab-content fb-tab-content">
<?php
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_methods.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_general.php');
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_tools.php');
  if (file_exists(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php')) {
    require(DIR_FS_MODULES . $module . '/custom/pages/admin/template_tab_custom.php');
  }
  require(DIR_FS_MODULES . $module . '/pages/admin/template_tab_stats.php');
?>
</div>
<div class="modal fade" id="shipping_dialog_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><?php echo TEXT_SETTINGS; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="shipping_dialog"></div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-primary btn-sm" id="shipping_dialog_submit"><?php echo TEXT_SUBMIT; ?></button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo TEXT_CANCEL; ?></button>
      </div>
    </div>
  </div>
</div>
</form>
