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
//  Path: /modules/phreepos/pages/pos_mgr/template_main.php
//
echo html_form('pos_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
// pull in extra toolbar overrides and additions
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
// add the help file index and build the toolbar
$toolbar->add_help('');
$toolbar->search_period = $acct_period;
echo $toolbar->build_toolbar($add_search = true, $add_periods = true, $cal_date);
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex justify-content-between mb-1">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ENTRIES); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
<table class="pkg-table table table-borderless table-hover">
  <thead class="table-dark">
    <tr><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
<?php
  $odd = true;
  while (!$query_result->EOF) {
    $oID = $query_result->fields['id'];
?>
    <tr class="<?php echo $odd ? 'odd' : 'even'; ?>">
      <td><?php echo gen_locale_date($query_result->fields['post_date']); ?></td>
      <td class="text-center"><?php echo $query_result->fields['purchase_invoice_id']; ?></td>
      <td class="text-center"><?php echo $currencies->format_full($query_result->fields['total_amount']); ?></td>
      <?php if (ENABLE_MULTI_CURRENCY) echo '<td class="text-center">' . $currencies->format_full($query_result->fields['total_amount'], true, $query_result->fields['currencies_code'], $query_result->fields['currencies_value']) . '</td>'; ?>
      <td><?php echo htmlspecialchars($query_result->fields['bill_primary_name']); ?></td>
      <td class="text-end text-nowrap">
<?php
    if (function_exists('add_extra_action_bar_buttons')) echo add_extra_action_bar_buttons($query_result->fields);
    echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder(' . $oID . ')"') . chr(10);
    if ($security_level > 3) echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . POS_MSG_DELETE_CONFIRM . '\')) submitSeq(' . $oID . ', \'delete\')"') . chr(10);
?>
      </td>
    </tr>
<?php
    $odd = !$odd;
    $query_result->MoveNext();
  }
?>
  </tbody>
</table>
<div class="d-flex justify-content-between mt-1">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ENTRIES); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
</form>
