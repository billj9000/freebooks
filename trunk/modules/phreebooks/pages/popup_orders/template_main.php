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
//  Path: /modules/phreebooks/pages/popup_orders/template_main.php
//
echo html_form('popup_orders', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '')   . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
switch (JOURNAL_ID) {
  case  3: $toolbar->add_help('07.02.04.02'); break;
  case  4: $toolbar->add_help('07.02.03.02'); break;
  case  6: $toolbar->add_help('07.02.05.02'); break;
  case  7: $toolbar->add_help('07.02.07.02'); break;
  case  9: $toolbar->add_help('07.03.04.02'); break;
  case 10: $toolbar->add_help('07.03.03.02'); break;
  case 12: $toolbar->add_help('07.03.05.02'); break;
  case 13: $toolbar->add_help('07.03.07.02'); break;
  case 18: $toolbar->add_help('07.05.02');    break;
  case 20: $toolbar->add_help('07.05.01');    break;
}
$toolbar->search_period = $acct_period;
echo $toolbar->build_toolbar($add_search = true, $add_period = true, $cal_date);
?>
<div class="d-flex justify-content-between align-items-center mb-1">
  <span><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . constant('ORD_TEXT_' . JOURNAL_ID . '_WINDOW_TITLE')); ?></span>
  <span><?php echo $query_split->display_links(); ?></span>
</div>
<table class="pkg-table table table-borderless table-hover table-sm mb-0">
  <thead class="table-dark">
    <tr><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
    <?php
    $odd = true;
    while (!$query_result->EOF) {
      switch (JOURNAL_ID) {
        case  3: case  4: case  9:
        case 10: case 12: case 13: $closed = $query_result->fields['closed'];  break;
        case  6: case  7:          $closed = $query_result->fields['waiting']; break;
        default:                   $closed = false;
      }
      $purch_order_id = $query_result->fields['so_po_ref_id'] ? ord_get_so_po_num($query_result->fields['so_po_ref_id']) : '';
      $total_amount   = $currencies->format_full($query_result->fields['total_amount'], true, $query_result->fields['currencies_code'], $query_result->fields['currencies_value']);
      if (ENABLE_MULTI_CURRENCY) $total_amount .= ' (' . $query_result->fields['currencies_code'] . ')';
    ?>
    <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer" onclick="setReturnOrdr(<?php echo $query_result->fields['id']; ?>, false)">
      <td><?php echo gen_locale_date($query_result->fields['post_date']); ?></td>
      <td><?php echo $query_result->fields['purchase_invoice_id']; ?></td>
      <?php switch (JOURNAL_ID) {
        case  6: case 12: echo '<td>' . $purch_order_id . '</td>'; break;
        case  7: case 13: echo '<td>' . $query_result->fields['purch_order_id'] . '</td>'; break;
      } ?>
      <td><?php echo ($closed ? TEXT_YES : ''); ?></td>
      <td><?php echo $query_result->fields['bill_primary_name']; ?></td>
      <td class="text-end"><?php echo $total_amount; ?></td>
    </tr>
    <?php
      $query_result->MoveNext();
      $odd = !$odd;
    } ?>
  </tbody>
</table>
<div class="d-flex justify-content-between align-items-center mt-1">
  <span><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . constant('ORD_TEXT_' . JOURNAL_ID . '_WINDOW_TITLE')); ?></span>
  <span><?php echo $query_split->display_links(); ?></span>
</div>
</form>
