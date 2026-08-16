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
//  Path: /modules/phreedom/pages/users/template_detail.php
//
echo html_form('users', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
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

<!-- General card -->
<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_GENERAL; ?></div>
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo GEN_USERNAME; ?></div>
      <div class="col-auto"><?php echo html_input_field('admin_name', $uInfo->admin_name, 'class="form-control form-control-sm" style="width:180px"'); ?></div>
      <div class="col-auto ms-3"><?php echo TEXT_INACTIVE; ?> <?php echo html_checkbox_field('inactive', '1', ($uInfo->inactive ? true : false)); ?></div>
      <div class="col-auto ms-3"><?php echo GEN_DISPLAY_NAME; ?></div>
      <div class="col-auto"><?php echo html_input_field('display_name', $uInfo->display_name, 'class="form-control form-control-sm" style="width:180px"'); ?></div>
    </div>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo TEXT_PASSWORD; ?></div>
      <div class="col-auto"><?php echo html_password_field('password_new', '', false, 'class="form-control form-control-sm" style="width:180px"'); ?></div>
      <div class="col-auto ms-3"><?php echo GEN_EMAIL; ?></div>
      <div class="col-auto"><?php echo html_input_field('admin_email', $uInfo->admin_email, 'class="form-control form-control-sm" style="width:220px"'); ?></div>
    </div>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo TEXT_CONFIRM_PASSWORD; ?></div>
      <div class="col-auto"><?php echo html_password_field('password_conf', '', false, 'class="form-control form-control-sm" style="width:180px"'); ?></div>
      <div class="col-auto ms-3"><?php echo GEN_ACCOUNT_LINK; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('account_id', gen_get_contact_array_by_type('e'), $uInfo->account_id, 'class="form-select form-select-sm"'); ?></div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-auto"><?php echo TEXT_SELECT_ROLE; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('fill_role', $fill_all_roles, $uInfo->role ?? 0, 'class="form-select form-select-sm" onchange="submitToDo(\'fill_role\')"'); ?></div>
    </div>
  </div>
</div>

<!-- Profile card -->
<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_PROFILE; ?></div>
  <div class="card-body py-2 px-3" style="font-size:11px">
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
foreach ($mainmenu as $key => $value) {
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
foreach ($mainmenu as $key => $menu_heading) {
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

<?php $pk_target_id = $uInfo->admin_id ?? 0; ?>
<?php if (wa_enabled() && $security_level > 2 && $pk_target_id && ($uInfo->is_role ?? '0') !== '1'): ?>
<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_PASSKEYS_TITLE; ?></div>
  <div class="card-body py-2 px-3" style="font-size:11px">
    <table class="table table-borderless table-hover" style="font-size:11px">
      <thead class="table-dark">
        <tr>
          <th><?php echo TEXT_PASSKEY_LABEL; ?></th>
          <th><?php echo TEXT_PASSKEY_ADDED; ?></th>
          <th><?php echo TEXT_PASSKEY_LAST_USED; ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $pk_admin_creds = wa_list_credentials($pk_target_id); ?>
        <?php if (!$pk_admin_creds): ?>
        <tr><td colspan="4" class="text-muted"><?php echo TEXT_PASSKEY_NONE; ?></td></tr>
        <?php else: foreach ($pk_admin_creds as $pk_index => $cred): $pk_row_class = $pk_index % 2 ? 'odd' : 'even'; ?>
        <tr id="pk-admin-row-<?php echo (int)$cred['id']; ?>" class="<?php echo $pk_row_class; ?>">
          <td><?php echo htmlspecialchars($cred['label']); ?></td>
          <td><?php echo htmlspecialchars($cred['created']); ?></td>
          <td><?php echo $cred['last_used'] ? htmlspecialchars($cred['last_used']) : '<em>' . TEXT_PASSKEY_NEVER_USED . '</em>'; ?></td>
          <td>
            <button type="button" class="btn btn-danger btn-sm"
              hx-post="<?php echo html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=ajax&amp;op=passkey_delete', 'SSL'); ?>"
              hx-vals='{"id": "<?php echo (int)$cred['id']; ?>", "target_admin_id": "<?php echo (int)$pk_target_id; ?>"}'
              hx-target="#pk-admin-row-<?php echo (int)$cred['id']; ?>"
              hx-swap="delete"
              hx-confirm="<?php echo addslashes(TEXT_PASSKEY_CONFIRM_REVOKE); ?>">
              <?php echo TEXT_PASSKEY_REVOKE; ?>
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</form>

<?php
function create_row($array) {
  global $odd, $settings;
  // One row per PERMISSION, not per menu entry. The radios are named
  // sID_<security_id>, so two entries sharing an id become a single radio group
  // in the browser: choosing a level on one row cleared the other, and neither
  // row looked authoritative. Report Editor and Form Layout share
  // SECURITY_ID_FORMS_AUTHOR on purpose — both are "write a document" — and any
  // module may do the same. The first entry to claim an id keeps the row.
  static $drawn = array();
  if (!empty($array['submenu']) && $array['text'] != BOX_HEADING_CONFIGURATION) foreach ($array['submenu'] as $menu_item) {
    create_row($menu_item);
  } else {
    if ($array['security_id'] == '') return;
    if (isset($drawn[$array['security_id']])) return;
    $drawn[$array['security_id']] = true;
    $checked = array();
    if ($array['show_in_users_settings'] === false) {
      return;
    } elseif (isset($settings[$array['security_id']])) {
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
