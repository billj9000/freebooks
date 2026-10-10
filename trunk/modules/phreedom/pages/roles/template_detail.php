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
//  Path: /modules/phreedom/pages/roles/template_detail.php
//
echo html_form('roles', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action',   '')        . chr(10);
echo html_hidden_field('rowSeq', $admin_id) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 2) {
  $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
} else {
  $toolbar->icon_list['save']['show']   = false;
}
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.08.07');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<!-- Profile card -->
<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_PROFILE; ?></div>
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo TEXT_ROLE_NAME; ?></div>
      <div class="col-auto"><?php echo html_input_field('admin_name', $uInfo->admin_name, 'class="form-control form-control-sm" style="width:180px"'); ?></div>
      <div class="col-auto ms-3"><?php echo TEXT_INACTIVE; ?> <?php echo html_checkbox_field('inactive', '1', ($uInfo->inactive ? true : false)); ?></div>
    </div>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo GEN_DEFAULT_STORE; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('def_store_id', gen_get_store_ids(), $error ? $_POST['def_store_id'] : $uInfo->def_store_id, 'class="form-select form-select-sm"'); ?></div>
      <div class="col-auto ms-4"><?php echo GEN_DEF_CASH_ACCT; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('def_cash_acct', gen_coa_pull_down(), $error ? $_POST['def_cash_acct'] : $uInfo->def_cash_acct, 'class="form-select form-select-sm"'); ?></div>
    </div>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo GEN_RESTRICT_STORE; ?></div>
      <div class="col-auto"><?php echo html_checkbox_field('restrict_store', '1', (($error && $_POST['restrict_store']) || $uInfo->restrict_store) ? true : false); ?></div>
      <div class="col-auto ms-4"><?php echo GEN_DEF_AR_ACCT; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('def_ar_acct', gen_coa_pull_down(), $error ? $_POST['def_ar_acct'] : $uInfo->def_ar_acct, 'class="form-select form-select-sm"'); ?></div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-auto"><?php echo GEN_RESTRICT_PERIOD; ?></div>
      <div class="col-auto"><?php echo html_checkbox_field('restrict_period', '1', (($error && $_POST['restrict_period']) || $uInfo->restrict_period) ? true : false); ?></div>
      <div class="col-auto ms-4"><?php echo GEN_DEF_AP_ACCT; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('def_ap_acct', gen_coa_pull_down(), $error ? $_POST['def_ap_acct'] : $uInfo->def_ap_acct, 'class="form-select form-select-sm"'); ?></div>
    </div>
  </div>
</div>

<!-- Security Settings card -->
<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_SECURITY_SETTINGS; ?></div>
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="mb-2">
      <?php echo TEXT_FILL_ALL_LEVELS; ?>
      <?php echo html_pull_down_menu('fill_all', $fill_all_values, '-1', 'class="form-select form-select-sm d-inline-block ms-1" style="width:auto" onchange="submitToDo(\'fill_all\')"'); ?>
    </div>
    <ul class="nav nav-tabs fb-tabs" id="accesstabs" role="tablist">
<?php
$first = true;
foreach (array_merge($mainmenu, $roles_extra_tabs ?? array()) as $key => $value) {
  if ($value['text'] == TEXT_HOME || $value['text'] == TEXT_LOGOUT) continue;
  echo '<li class="nav-item" role="presentation">' . chr(10);
  echo '  <button class="nav-link' . ($first ? ' active' : '') . '" id="tab_' . $key . '_btn" data-bs-toggle="tab" data-bs-target="#tab_' . $key . '" type="button" role="tab">' . $value['text'] . '</button>' . chr(10);
  echo '</li>' . chr(10);
  $first = false;
}
?>
    </ul>
    <div class="tab-content fb-tab-content">
<?php
$settings = gen_parse_permissions($uInfo->admin_security);
$first    = true;
foreach (array_merge($mainmenu, $roles_extra_tabs ?? array()) as $key => $menu_heading) {
  if ($menu_heading['text'] == TEXT_HOME || $menu_heading['text'] == TEXT_LOGOUT) continue;
  echo '<div class="tab-pane fade' . ($first ? ' show active' : '') . '" id="tab_' . $key . '" role="tabpanel">' . chr(10);
  echo '<table class="pkg-table table table-borderless table-hover table-sm mb-0">' . chr(10);
  echo '<thead class="table-dark">' . chr(10);
  echo '<tr>' . chr(10);
  echo '<th style="width:50%">&nbsp;</th>' . chr(10);
  echo '<th class="text-center" style="width:10%;white-space:nowrap">' . TEXT_FULL      . '</th>' . chr(10);
  echo '<th class="text-center" style="width:10%;white-space:nowrap">' . TEXT_EDIT      . '</th>' . chr(10);
  echo '<th class="text-center" style="width:10%;white-space:nowrap">' . TEXT_ADD       . '</th>' . chr(10);
  echo '<th class="text-center" style="width:10%;white-space:nowrap">' . TEXT_READ_ONLY . '</th>' . chr(10);
  echo '<th class="text-center" style="width:10%;white-space:nowrap">' . TEXT_NONE      . '</th>' . chr(10);
  echo '</tr>' . chr(10);
  echo '</thead><tbody>' . chr(10);
  $odd = true;
  foreach ($menu_heading['submenu'] as $menu_item) {
    create_row($menu_item);
  }
  echo '</tbody></table></div>' . chr(10);
  $first = false;
}
?>
    </div>
  </div>
</div>
</form>

<?php
function create_row($array) {
  global $odd, $settings, $error;
  // One row per PERMISSION, not per menu entry. The radios are named
  // sID_<security_id>, so two entries sharing an id would be one radio group in
  // the browser drawn as two rows, each clearing the other. Report Editor and
  // Form Layout share SECURITY_ID_FORMS_AUTHOR on purpose — both are "write a
  // document" — and any module may do the same. The first VISIBLE entry to claim
  // an id keeps the row.
  //
  // Visibility is tested before the claim. An entry hidden from this page can
  // never draw a row, so one allowed to claim the id first would erase the
  // visible entry's row rather than merge with it - and every "xxx_mgr" menu item
  // is preceded by a hidden "new_xxx" item sharing its security id. A permission
  // with no row is not merely ungrantable: the levels are saved from the sID_
  // fields that POST, so saving a role would strip the id it already holds.
  static $drawn = array();
  if (!empty($array['submenu'])) foreach ($array['submenu'] as $menu_item) {
    create_row($menu_item);
  } else {
    // Skipped on the absence of a KEY, and nothing else. A label is
    // presentation, and deciding by label whether a permission may be granted
    // would hide every permission that shares one - SECURITY_ID_FORMS_RUN's
    // entry is labelled Reports, for one - leaving it with no row and no way to
    // grant it.
    if ($array['security_id'] == '') return;
    if ($array['show_in_users_settings'] === false) return;
    if (isset($drawn[$array['security_id']])) return;
    $drawn[$array['security_id']] = true;
    $checked = array();
    if (isset($settings[$array['security_id']])) {
      $checked[0] = false;
      $checked[$settings[$array['security_id']]] = true;
    } elseif ($error) {
      $checked[0] = false;
      $checked[$_POST['sID_' . $array['security_id']]] = true;
    } else {
      $checked[0] = true;
    }
    echo '<tr class="' . ($odd ? 'odd' : 'even') . '">';
    echo '<td>' . $array['text'] . '</td>' . chr(10);
    echo '<td class="text-center">' . html_radio_field('sID_' . $array['security_id'], '4', $checked[4]) . '</td>' . chr(10);
    echo '<td class="text-center">' . html_radio_field('sID_' . $array['security_id'], '3', $checked[3]) . '</td>' . chr(10);
    echo '<td class="text-center">' . html_radio_field('sID_' . $array['security_id'], '2', $checked[2]) . '</td>' . chr(10);
    echo '<td class="text-center">' . html_radio_field('sID_' . $array['security_id'], '1', $checked[1]) . '</td>' . chr(10);
    echo '<td class="text-center">' . html_radio_field('sID_' . $array['security_id'], '0', $checked[0]) . '</td></tr>' . chr(10);
    $odd = !$odd;
  }
}
?>
