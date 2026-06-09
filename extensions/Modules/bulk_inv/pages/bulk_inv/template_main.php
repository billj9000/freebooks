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
//  Path: /modules/bulk_inv/pages/bulk_inv/template_main.php
//
echo html_form('bulk_inventory', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) {
  $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
} else {
  $toolbar->icon_list['save']['show']   = false;
}
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar($add_search = true);
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div style="height:19px"><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ITEMS); ?>
  <div style="float:right"><?php echo $query_split->display_links(); ?></div>
</div>
<table class="table table-sm table-hover table-borderless w-100">
  <thead class="table-dark">
    <tr>
      <th><?php echo '# Fields'; ?></th>
      <th><?php echo TEXT_DESCRIPTION; ?></th>
      <?php for ($i = 0; $i < $field_cnt; $i++) echo '<th>Field ' . $i . '</th>' . chr(10); ?>
      <th><?php echo TEXT_ACTION; ?></th>
    </tr>
    <tr>
      <td><?php echo html_input_field('field_cnt', $field_cnt, 'class="form-control form-control-sm" onchange="submitToDo(\'reload\')"'); ?></td>
      <td>&nbsp;</td>
      <?php for ($i = 0; $i < $field_cnt; $i++) echo '<td>' . html_pull_down_menu('field' . $i, $fields, $field[$i], 'class="form-select form-select-sm" onchange="submitToDo(\'reload\')"') . '</td>' . chr(10); ?>
      <td class="text-end"><?php echo html_button_field('filter', 'Filter', 'onclick="submitToDo(\'filter\')"'); ?></td>
    </tr>
  </thead>
  <tbody>
<?php
$row = 0;
$odd = true;
while (!$query_result->EOF) {
?>
    <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer" onclick="invDetails('<?php echo addslashes($query_result->fields['sku']); ?>')">
      <td><?php echo $query_result->fields['sku'] . html_hidden_field('id_' . $row, $query_result->fields['id']); ?></td>
      <td><?php echo $query_result->fields['description_short']; ?></td>
      <?php for ($i = 0; $i < $field_cnt; $i++) echo '<td onclick="event.stopPropagation()">' . html_input_field('f' . $i . '_' . $row, $query_result->fields['f' . $i], 'class="form-control form-control-sm"') . '</td>' . chr(10); ?>
      <td>&nbsp;</td>
    </tr>
<?php
  $row++;
  $query_result->MoveNext();
  $odd = !$odd;
}
?>
  </tbody>
</table>
<div style="float:right"><?php echo $query_split->display_links(); ?></div>
<div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ITEMS); ?></div>
</form>
