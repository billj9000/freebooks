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
//  Path: /modules/work_orders/pages/popup_tasks/template_main.php
//
echo html_form('search_form', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
// include hidden fields
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show'] = false;
$toolbar->icon_list['save']['show'] = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show'] = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.WO.02');
echo $toolbar->build_toolbar($add_search = true);
?>
<h1><?php echo WO_POPUP_TASK_WINDOW_TITLE; ?></h1>
<div class="d-flex justify-content-between align-items-center mb-1">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_TASKS); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
<table class="table table-sm table-hover table-borderless w-100">
 <thead class="table-dark">
  <tr><?php echo $list_header; ?></tr>
 </thead>
 <tbody>
<?php
  $java_string = 'var task_list = new Array(' . $query_result->RecordCount() . ');' . chr(10);
  $pointer = 0;
  $odd = true;
  while (!$query_result->EOF) {
?>
  <tr class="<?php echo $odd?'odd':'even'; ?>" style="cursor:pointer" onclick='setReturnItem(<?php echo $pointer . ', ' . $rowID; ?>)'>
    <td nowrap="nowrap"><?php echo $query_result->fields['task_name']; ?></td>
    <td><?php echo $query_result->fields['description']; ?></td>
  </tr>
<?php
    $java_string .= 'task_list[' . $pointer . '] = new taskRecord(' . $query_result->fields['id'] . ', "' . gen_js_encode($query_result->fields['task_name']) . '", "' . gen_js_encode($query_result->fields['description']) . '");' . chr(10);
    $pointer++;
    $query_result->MoveNext();
    $odd = !$odd;
  }
?>
 </tbody>
</table>
<div class="d-flex justify-content-between align-items-center mt-1">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_TASKS); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
</form>
<script type="text/javascript">
<?php echo $java_string; ?>
</script>
