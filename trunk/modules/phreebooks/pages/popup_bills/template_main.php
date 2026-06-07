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
//  Path: /modules/phreebooks/pages/popup_bills/template_main.php
//
echo html_form('popup_bills', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '')   . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
switch (JOURNAL_ID) {
  case 18: $toolbar->add_help('07.05.02'); break;
  case 20: $toolbar->add_help('07.05.01'); break;
}
$toolbar->search_period = $acct_period;
echo $toolbar->build_toolbar($add_search = true, $add_period = true);
$label = TEXT_DISPLAY_NUMBER . (JOURNAL_ID == 18 ? TEXT_RECEIPTS : TEXT_PAYMENTS);
?>
<div class="d-flex justify-content-between align-items-center mb-1">
  <span><?php echo $query_split->display_count($label); ?></span>
  <span><?php echo $query_split->display_links(); ?></span>
</div>
<table class="pkg-table table table-borderless table-hover table-sm mb-0">
  <thead class="table-dark">
    <tr><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
    <?php
    $odd = true;
    while (!$query_result->EOF) { ?>
    <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer" onclick='setReturnEntry(<?php echo $query_result->fields['id']; ?>)'>
      <td><?php echo gen_locale_date($query_result->fields['post_date']); ?></td>
      <?php if (ENABLE_MULTI_BRANCH) { ?>
      <td><?php echo $query_result->fields['store_id'] ? gen_get_contact_name($query_result->fields['store_id']) : COMPANY_ID; ?></td>
      <?php } ?>
      <td><?php echo $query_result->fields['purchase_invoice_id']; ?></td>
      <td><?php echo $currencies->format($query_result->fields['total_amount']); ?></td>
      <td><?php echo $query_result->fields['bill_primary_name']; ?></td>
    </tr>
    <?php
      $query_result->MoveNext();
      $odd = !$odd;
    } ?>
  </tbody>
</table>
<div class="d-flex justify-content-between align-items-center mt-1">
  <span><?php echo $query_split->display_count($label); ?></span>
  <span><?php echo $query_split->display_links(); ?></span>
</div>
</form>
