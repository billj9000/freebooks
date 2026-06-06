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
//  Path: /modules/phreedom/pages/admin/template_tab_modules.php
//
?>
<style>
.module-installed {
  background-color:    #6eac2c;
  background-image:    url(/themes/default/css/start/images/ui-bg_gloss-wave_50_6eac2c_500x100.png);
  background-repeat:   repeat-x;
  background-position: 50% 50%;
  color:               #fff !important;
}
</style>
<div class="tab-pane fade<?php echo ($active_tab === 'modules' ? ' show active' : ''); ?>" id="tab_modules" role="tabpanel">
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo TEXT_AVAILABLE_MODULES; ?></div>
  <div class="card-body p-0">
    <table class="pkg-table table table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo TEXT_MODULE; ?></th>
          <th><?php echo TEXT_DESCRIPTION; ?></th>
          <th><?php echo TEXT_VERSION; ?></th>
          <th><?php echo TEXT_ACTION; ?></th>
        </tr>
      </thead>
      <tbody>
<?php
if (is_array($page_list)) foreach ($page_list as $key => $value) {
  $installed = defined('MODULE_' . strtoupper($key) . '_STATUS');
  echo '      <tr>' . chr(10);
  echo '        <td' . ($installed ? ' class="module-installed"' : '') . '>' . $value . '</td>' . chr(10);
  echo '        <td>' . constant('MODULE_' . strtoupper($key) . '_DESCRIPTION') . '</td>' . chr(10);
  if (!$installed && $security_level > 1) {
    echo '        <td>&nbsp;</td>' . chr(10);
    echo '        <td class="text-center"><button type="button" class="btn btn-primary btn-sm" onclick="submitToDo(\'install_' . $key . '\')">' . TEXT_INSTALL . '</button></td>' . chr(10);
  } else {
    echo '        <td class="text-center">' . constant('MODULE_' . strtoupper($key) . '_STATUS') . '</td>' . chr(10);
    echo '        <td class="text-center" nowrap="nowrap">' . chr(10);
    if ($security_level > 3) echo '          <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm(\'' . TEXT_REMOVE_MESSAGE . '\')) submitToDo(\'remove_' . $key . '\')">' . TEXT_REMOVE . '</button>' . chr(10);
    if (file_exists(DIR_FS_MODULES . $key . '/pages/admin/pre_process.php')) {
      echo html_icon('categories/preferences-system.png', TEXT_PROPERTIES, 'medium', 'onclick="location.href=\'' . html_href_link(FILENAME_DEFAULT, 'module=' . $key . '&amp;page=admin', 'SSL') . '\'"') . chr(10);
    }
    echo '        </td>' . chr(10);
  }
  echo '      </tr>' . chr(10);
  echo '      <tr><td colspan="4" class="py-1"><hr class="m-0" /></td></tr>' . chr(10);
}
?>
      </tbody>
    </table>
  </div>
</div>
</div>
