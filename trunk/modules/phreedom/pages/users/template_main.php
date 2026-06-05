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
//  Path: /modules/phreedom/pages/users/template_main.php
//
echo html_form('users', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 1) $toolbar->add_icon('new', 'onclick="submitToDo(\'new\')"', $order = 10);
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.08.07');
echo $toolbar->build_toolbar($add_search = true);
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex justify-content-between align-items-center mb-1" style="font-size:11px">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_USERS); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
<table class="pkg-table table table-borderless table-hover table-sm">
  <thead class="table-dark">
    <tr><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
<?php
$odd = true;
while (!$query_result->EOF) {
?>
    <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer">
      <td onclick="submitSeq(<?php echo $query_result->fields['admin_id']; ?>, 'edit')"><?php echo $query_result->fields['admin_name']; ?></td>
      <td onclick="submitSeq(<?php echo $query_result->fields['admin_id']; ?>, 'edit')"><?php echo $query_result->fields['inactive'] ? TEXT_YES : ''; ?></td>
      <td onclick="submitSeq(<?php echo $query_result->fields['admin_id']; ?>, 'edit')"><?php echo $query_result->fields['display_name']; ?></td>
      <td onclick="submitSeq(<?php echo $query_result->fields['admin_id']; ?>, 'edit')"><?php echo $query_result->fields['admin_email']; ?></td>
      <td class="text-end">
<?php
        if (function_exists('add_extra_action_bar_buttons')) echo add_extra_action_bar_buttons($query_result->fields);
        if ($security_level > 1) echo html_icon('actions/edit-find-replace.png', TEXT_EDIT,    'small', 'onclick="submitSeq(' . $query_result->fields['admin_id'] . ', \'edit\')"') . chr(10);
        if ($security_level > 3) echo html_icon('actions/edit-copy.png',          TEXT_COPY_TO, 'small', 'onclick="copyItem(' . $query_result->fields['admin_id'] . ')"') . chr(10);
        if ($security_level > 3) echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . TEXT_DELETE_INTRO_USERS . '\')) submitSeq(' . $query_result->fields['admin_id'] . ', \'delete\')"') . chr(10);
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
<div class="d-flex justify-content-between align-items-center mt-1" style="font-size:11px">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_USERS); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
</form>
