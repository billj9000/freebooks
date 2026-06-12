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
//  Path: /modules/assets/pages/main/template_detail.php
//
echo html_form('assets', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'cID', 'asset_id')), 'post', 'enctype="multipart/form-data"');
// include hidden fields
echo html_hidden_field('id', $cInfo->id) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action','page')) . '&page=main', 'SSL') . '\'"';
$toolbar->icon_list['open']['show'] = false;
$toolbar->icon_list['delete']['show'] = false;
if ($security_level > 2) {
  $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
} else {
  $toolbar->icon_list['save']['show'] = false;
}
$toolbar->icon_list['print']['show'] = false;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
$fields->set_fields_to_display($asset_type);

// Convert extra_tab_li (legacy tab format) to Bootstrap nav buttons
$extra_nav_items = '';
if (!empty($fields->extra_tab_li)) {
  preg_match_all("/<li><a href='#(tab_\d+)'>(.*?)<\/a><\/li>/i", $fields->extra_tab_li, $matches, PREG_SET_ORDER);
  foreach ($matches as $m) {
    $extra_nav_items .= '<li class="nav-item">'
      . '<button class="nav-link" data-bs-toggle="tab" data-bs-target="#' . $m[1] . '" type="button">'
      . $m[2] . '</button></li>' . chr(10);
  }
}

// Add tab-pane class to extra_tab_html divs (legacy tab format uses plain <div id='tab_X'>)
$extra_tab_panes = '';
if (!empty($fields->extra_tab_html)) {
  $extra_tab_panes = preg_replace("/<div id='(tab_\d+)'>/", "<div id='$1' class='tab-pane'>", $fields->extra_tab_html);
}
?>
<h1><?php echo MENU_HEADING_ASSETS . ' - ' . TEXT_ASSET_ID . '# ' . $cInfo->asset_id; ?></h1>

<ul class="nav nav-tabs fb-tabs" id="detailtabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_general" type="button"><?php echo TEXT_GENERAL; ?></button></li>
  <?php echo $extra_nav_items; ?>
<?php if (isset($extra_assets_tabs) && is_array($extra_assets_tabs)) {
  foreach ($extra_assets_tabs as $tabs) {
    echo '<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#' . $tabs['tab_id'] . '" type="button">' . $tabs['tab_title'] . '</button></li>' . chr(10);
  }
} ?>
</ul>
<div class="tab-content fb-tab-content">

<div class="tab-pane show active" id="tab_general">
  <table>
    <tr>
      <td><?php echo TEXT_ASSET_ID; ?></td>
      <td>
        <?php echo html_input_field('asset_id', $cInfo->asset_id, 'readonly="readonly"', false); ?>
        <?php echo TEXT_INACTIVE; ?>
        <?php echo html_checkbox_field('inactive', '1', $cInfo->inactive); ?>
      </td>
      <td rowspan="4" align="center">
        <?php if ($cInfo->image_with_path) {
          echo html_image(DIR_WS_FULL_PATH . 'my_files/' . $_SESSION['company'] . '/assets/images/' . $cInfo->image_with_path, $cInfo->image_with_path, '', '100', 'rel="#photo1"');
        } else echo '&nbsp;'; ?>
      </td>
      <td><?php echo TEXT_IMAGE . ' (' . TEXT_REMOVE . ' ' . html_checkbox_field('remove_image', '1', $cInfo->remove_image) . ')'; ?></td>
    </tr>
    <tr>
      <td><?php echo TEXT_DESCRIPTION_SHORT; ?></td>
      <td><?php echo html_input_field('description_short', $cInfo->description_short, 'size="33" maxlength="32"', false); ?></td>
      <td><?php echo html_file_field('asset_image'); ?></td>
    </tr>
    <tr>
      <td><?php echo ASSETS_ENTRY_ASSETS_TYPE; ?></td>
      <td><?php echo html_hidden_field('asset_type', $cInfo->asset_type);
        echo html_input_field('inv_type_desc', $assets_types[$cInfo->asset_type], 'readonly="readonly"', false); ?></td>
      <td><?php echo ASSETS_ENTRY_IMAGE_PATH; ?></td>
    </tr>
    <tr>
      <td><?php echo ASSETS_ENTRY_FULL_PRICE; ?></td>
      <td>
        <?php echo html_input_field('full_price', $currencies->format($cInfo->full_price), 'size="11" maxlength="10" style="text-align:right"', false) . (ENABLE_MULTI_CURRENCY ? (' (' . DEFAULT_CURRENCY . ')') : ''); ?>
      </td>
      <td>
        <?php echo html_hidden_field('image_with_path', $cInfo->image_with_path);
        echo html_input_field('asset_path', substr($cInfo->image_with_path, 0, strrpos($cInfo->image_with_path, '/'))); ?>
      </td>
    </tr>
    <tr>
      <td><?php echo ASSETS_ENTRY_ASSETS_SERIALIZE; ?></td>
      <td><?php echo html_input_field('serial_number', $cInfo->serial_number, 'size="33" maxlength="32"'); ?></td>
      <td><?php echo ASSETS_PURCHASE_CONDITION; ?></td>
      <td><?php echo html_pull_down_menu('purch_cond', $purch_cond_array, $cInfo->purch_cond); ?></td>
    </tr>
    <tr>
      <td valign="top"><?php echo TEXT_DETAIL_DESCRIPTION; ?></td>
      <td colspan="3"><?php echo html_textarea_field('description_long', 75, 3, $cInfo->description_long, '', $reinsert_value = true); ?></td>
    </tr>
    <tr>
      <td><?php echo ASSETS_ENTRY_ACCT_SALES; ?></td>
      <td><?php echo html_pull_down_menu('account_asset', $gl_array_list, $cInfo->account_asset); ?></td>
      <td><?php echo ASSETS_DATE_ACCOUNT_CREATION; ?></td>
      <td><?php echo html_calendar_field($cal_date1); ?></td>
    </tr>
    <tr>
      <td><?php echo ASSETS_ENTRY_ACCT_INV; ?></td>
      <td><?php echo html_pull_down_menu('account_depreciation', $gl_array_list, $cInfo->account_depreciation); ?></td>
      <td><?php echo ASSETS_DATE_LAST_UPDATE; ?></td>
      <td><?php echo html_calendar_field($cal_date2); ?></td>
    </tr>
    <tr>
      <td><?php echo ASSETS_ENTRY_ACCT_COS; ?></td>
      <td><?php echo html_pull_down_menu('account_maintenance', $gl_array_list, $cInfo->account_maintenance); ?></td>
      <td><?php echo ASSETS_DATE_LAST_JOURNAL_DATE; ?></td>
      <td><?php echo html_calendar_field($cal_date3); ?></td>
    </tr>
  </table>
  <div class="fb-fieldset mb-2">
    <div class="fb-fieldset-legend"><?php echo TEXT_ATTACHMENTS; ?></div>
    <table class="table table-sm table-bordered">
      <thead class="table-dark">
        <tr>
          <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
          <th><?php echo TEXT_FILENAME; ?></th>
          <th><?php echo TEXT_ACTION; ?></th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="3"><?php echo TEXT_SELECT_FILE_TO_ATTACH . ' ' . html_file_field('file_name'); ?></td></tr>
<?php
if (!empty($attachments)) {
  foreach ($attachments as $key => $value) {
    echo '<tr>';
    echo ' <td>' . html_checkbox_field('rm_attach_'.$key, '1', false) . '</td>' . chr(10);
    echo ' <td>' . $value . '</td>' . chr(10);
    echo ' <td>' . html_button_field('dn_attach_'.$key, TEXT_DOWNLOAD, 'onclick="submitSeq(' . $key . ', \'download\', true)"') . '</td>';
    echo '</tr>' . chr(10);
  }
} else {
  echo '<tr><td colspan="3">' . TEXT_NO_DOCUMENTS . '</td></tr>';
} ?>
      </tbody>
    </table>
  </div>
</div>

<?php echo $extra_tab_panes; ?>

<?php
if (isset($extra_assets_tabs) && is_array($extra_assets_tabs)) {
  foreach ($extra_assets_tabs as $tabs) {
    $file_path = DIR_FS_WORKING . 'custom/pages/main/' . $tabs['tab_filename'] . '.php';
    if (file_exists($file_path)) { require($file_path); }
  }
}
?>
</div>
</form>
