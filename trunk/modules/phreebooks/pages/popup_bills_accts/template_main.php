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
//  Path: /modules/phreebooks/pages/popup_bills_accts/template_main.php
//
echo html_form('popup_bills_accts', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
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
echo $toolbar->build_toolbar($add_search = true);
?>
<div class="d-flex justify-content-between align-items-center mb-1">
  <span><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_CONTACTS); ?></span>
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
      $invoice_summary = fill_paid_invoice_array(0, $query_result->fields['bill_acct_id'], ACCOUNT_TYPE); ?>
    <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer" onclick='setReturnEntry(<?php echo $query_result->fields['bill_acct_id']; ?>)'>
      <td><?php echo $query_result->fields['bill_primary_name']; ?></td>
      <td><?php echo $query_result->fields['bill_city_town']; ?></td>
      <td><?php echo $query_result->fields['bill_state_province']; ?></td>
      <td><?php echo $query_result->fields['bill_postal_code']; ?></td>
      <td class="text-end"><?php echo $currencies->format($invoice_summary['balance']); ?></td>
    </tr>
    <?php
      $query_result->MoveNext();
      $odd = !$odd;
    } ?>
  </tbody>
</table>
<div class="d-flex justify-content-between align-items-center mt-1">
  <span><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_CONTACTS); ?></span>
  <span><?php echo $query_split->display_links(); ?></span>
</div>
</form>
