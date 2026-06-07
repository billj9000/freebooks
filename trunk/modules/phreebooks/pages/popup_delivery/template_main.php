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
//  Path: /modules/phreebooks/pages/popup_delivery/template_main.php
//
echo html_form('popup_delivery', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '')   . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\', \'eta_dates\')"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
switch (JOURNAL_ID) {
  case 4: $toolbar->add_help('07.02.03.04'); break;
  case 6: $toolbar->add_help('07.03.03.04'); break;
}
echo $toolbar->build_toolbar();
?>
<div class="card fb-card mt-2">
  <div class="card-header py-1 fb-widget-header">
    <?php echo ORD_EXPECTED_DATES . constant('ORD_HEADING_NUMBER_' . JOURNAL_ID) . ' ' . $ordr_items->fields['purchase_invoice_id']; ?>
  </div>
  <div class="card-body p-0">
    <table class="pkg-table table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th class="text-center"><?php echo TEXT_QUANTITY; ?></th>
          <th class="text-center"><?php echo TEXT_SKU; ?></th>
          <th><?php echo TEXT_DESCRIPTION; ?></th>
          <th class="text-center"><?php echo ORD_DELIVERY_DATES; ?></th>
          <th class="text-center"><?php echo ORD_NEW_DELIVERY_DATES; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $j = 1;
        while (!$ordr_items->EOF) { ?>
        <tr>
          <td class="text-center"><?php echo $ordr_items->fields['qty']; ?></td>
          <td class="text-center"><?php echo $ordr_items->fields['sku']; ?></td>
          <td><?php echo $ordr_items->fields['description']; ?></td>
          <td class="text-center"><?php echo gen_locale_date($ordr_items->fields['date_1']); ?></td>
          <td class="text-center" style="white-space:nowrap">
            <?php echo html_hidden_field('id_' . $j, $ordr_items->fields['id']); ?>
            <?php echo html_calendar_field($cal_gen[$j]); ?>
          </td>
        </tr>
        <?php
          $j++;
          $ordr_items->MoveNext();
        } ?>
      </tbody>
    </table>
  </div>
</div>
</form>
