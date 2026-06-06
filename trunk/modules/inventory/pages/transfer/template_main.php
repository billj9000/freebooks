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
//  Path: /modules/inventory/pages/transfer/template_main.php
//
$hidden_fields = NULL;
echo html_form('inv_xfer', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
echo html_hidden_field('id',     $cInfo->id) . chr(10);
echo html_hidden_field('ref_id', $cInfo->ref_id) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['open']['params']   = 'onclick="OpenXfrList()"';
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . INV_ADJ_DELETE_ALERT . '\')) submitToDo(\'delete\')"';
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"', $order = 2);
if ($security_level < 4) $toolbar->icon_list['delete']['show'] = false;
if ($security_level < 2) $toolbar->icon_list['save']['show']   = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.02');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex flex-column align-items-center">
<div class="card border-0 mb-2" style="width:fit-content">
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="d-flex flex-wrap gap-3">
      <div>
        <label class="me-1"><?php echo INV_XFER_FROM_STORE; ?></label>
        <?php echo html_pull_down_menu('source_store_id', gen_get_store_ids(), $cInfo->source_store_id ? $cInfo->source_store_id : $_SESSION['admin_prefs']['def_store_id']); ?>
        <label class="mx-1"><?php echo INV_XFER_TO_STORE; ?></label>
        <?php echo html_pull_down_menu('dest_store_id', gen_get_store_ids(), $cInfo->dest_store_id); ?>
      </div>
      <div>
        <label class="me-1"><?php echo TEXT_POST_DATE; ?></label>
        <?php echo html_calendar_field($cal_xfr); ?>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-3 mt-2">
      <div>
        <label class="me-1"><?php echo TEXT_TRANSFER_REASON; ?></label>
        <?php echo html_input_field('adj_reason', $cInfo->adj_reason, 'size="50"'); ?>
      </div>
      <div>
        <label class="me-1"><?php echo TEXT_REFERENCE; ?></label>
        <?php echo html_input_field('purchase_invoice_id', $cInfo->purchase_invoice_id); ?>
      </div>
    </div>
  </div>
</div>
</div>
<div style="width:fit-content; margin:0 auto">
<table class="pkg-table table table-borderless table-hover table-sm" style="width:auto; border:1px solid #a6c9e2">
  <thead class="table-dark">
    <tr>
      <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
      <th><?php echo TEXT_QUANTITY; ?></th>
      <th><?php echo INV_HEADING_QTY_IN_STOCK; ?></th>
      <th><?php echo TEXT_BALANCE; ?></th>
      <th><?php echo TEXT_SKU; ?></th>
      <th><?php echo TEXT_DESCRIPTION; ?></th>
    </tr>
  </thead>
  <tbody id="item_table">
<?php
if (!$error) {
  $hidden_fields .= '<script type="text/javascript">addInvRow();</script>';
} else {
  $rowCnt = 1;
  while (true) {
    if (!isset($_POST['sku_'.$rowCnt])) break;
    echo '  <tr>' . chr(10);
    echo '    <td>' . html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . INV_MSG_DELETE_INV_ITEM . '\')) removeInvRow(' . $rowCnt . ');"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('qty_' . $rowCnt, $_POST['qty_'.$rowCnt], 'size="6" maxlength="5" style="text-align:right" onchange="updateBalance()"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('stock_' . $rowCnt, $_POST['stock_'.$rowCnt], 'readonly="readonly" size="6" maxlength="5" style="text-align:right"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('balance_' . $rowCnt, $_POST['balance_'.$rowCnt], 'readonly="readonly" size="6" maxlength="5" style="text-align:right"') . '</td>' . chr(10);
    echo '    <td nowrap="nowrap">' . chr(10);
    echo html_input_field('sku_' . $rowCnt, $_POST['sku_'.$rowCnt], 'size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '" onfocus="clearField(\'sku_'.$rowCnt.'\', \''.TEXT_SEARCH.'\')" onblur="setField(\'sku_'.$rowCnt.'\', \''.TEXT_SEARCH.'\'); loadSkuDetails(0, '.$rowCnt.')"') . '&nbsp;';
    echo html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="InventoryList()"');
    $invType = $db->Execute("SELECT inventory_type FROM ".TABLE_INVENTORY." WHERE sku='".db_input($_POST['sku_'.$rowCnt])."'");
    $imgSerialView = in_array($invType->fields['inventory_type'], array('sr','sa')) ? "" : "display:none;";
    echo html_icon('actions/tab-new.png', TEXT_SERIAL_NUMBER, 'small', 'id="imgSerial_'.$rowCnt.'" align="top" style="cursor:pointer;'.$imgSerialView.'" onclick="serialList(\'serial_'.$rowCnt.'\')"');
    echo html_hidden_field('serial_' . $rowCnt, $_POST['serial_'.$rowCnt]) . chr(10);
    echo html_hidden_field('acct_' . $rowCnt, $_POST['acct_'.$rowCnt]) . chr(10);
    echo '    </td>' . chr(10);
    echo '    <td>' . html_input_field('desc_' . $rowCnt, $_POST['desc_'.$rowCnt], 'size="90"') . '</td>' . chr(10);
    echo '  </tr>' . chr(10);
    $rowCnt++;
  }
}
?>
  </tbody>
</table>
<div style="padding:4px 0">
  <?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addInvRow()"'); ?>
</div>
</div>
<?php echo $hidden_fields; ?>
</form>

