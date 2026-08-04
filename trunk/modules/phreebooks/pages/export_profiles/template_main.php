<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/phreebooks/pages/export_profiles/template_main.php
//
echo html_form('export_profiles', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 1) $toolbar->add_icon('new', 'onclick="submitToDo(\'new\')"', 10);
echo $toolbar->build_toolbar();
?>
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo PO_EXPORT_PROFILES_TITLE; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo PO_EXPORT_COL_NAME; ?></th>
            <th class="text-center"><?php echo PO_EXPORT_COL_EXTENSION; ?></th>
            <th class="text-center"><?php echo TEXT_INACTIVE; ?></th>
            <th class="text-end"><?php echo TEXT_ACTION; ?></th>
          </tr>
        </thead>
        <tbody>
<?php
$odd = true;
if ($query_result->RecordCount() == 0) {
?>
          <tr><td colspan="4" class="text-center text-muted"><?php echo PO_EXPORT_NO_PROFILES; ?></td></tr>
<?php
}
while (!$query_result->EOF) {
?>
          <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer">
            <td onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['profile_name']); ?></td>
            <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['file_extension']); ?></td>
            <td class="text-center" onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo ($query_result->fields['inactive'] == '1' ? TEXT_YES : ''); ?></td>
            <td class="text-end">
<?php
  if ($security_level > 1) echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 'onclick="submitSeq(' . $query_result->fields['id'] . ', \'edit\')"') . chr(10);
  if ($security_level > 3) echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . PO_EXPORT_MSG_DELETE . '\')) deleteProfile(' . $query_result->fields['id'] . ')"') . chr(10);
?>
            </td>
          </tr>
<?php
  $query_result->MoveNext();
  $odd = !$odd;
}
?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</form>
