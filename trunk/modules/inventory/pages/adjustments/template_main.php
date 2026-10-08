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
//  Path: /modules/inventory/pages/adjustments/template_main.php
//
echo html_form('inv_adj', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
$hidden_fields = null;
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('id', $cInfo->id ?? '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['params']   = 'onclick="OpenAdjList()"';
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . INV_ADJ_DELETE_ALERT . '\')) submitToDo(\'delete\')"';
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"', $order = 2);
if ($security_level < 4) $toolbar->icon_list['delete']['show'] = false;
if ($security_level < 2) $toolbar->icon_list['save']['show']   = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.02.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<!-- Settings card -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-body py-2 px-3" style="font-size:11px">
    <?php if (ENABLE_MULTI_BRANCH): ?>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto text-end"><?php echo GEN_STORE_ID; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('store_id', gen_get_store_ids(), !empty($cInfo->store_id) ? $cInfo->store_id : $_SESSION['admin_prefs']['def_store_id'], 'style="font-size:11px"'); ?></div>
    </div>
    <?php else: $hidden_fields .= html_hidden_field('store_id', $_SESSION['admin_prefs']['def_store_id']) . chr(10); ?>
    <?php endif; ?>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto text-end"><?php echo INV_REASON_FOR_ADJUSTMENT; ?></div>
      <div class="col-auto"><?php echo html_input_field('adj_reason', $cInfo->adj_reason ?? '', 'class="form-control form-control-sm" style="width:320px"'); ?></div>
      <div class="col-auto text-end"><?php echo TEXT_POST_DATE; ?></div>
      <div class="col-auto"><?php echo html_calendar_field($cal_adj); ?></div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-auto text-end"><?php echo INV_ADJUSTMENT_ACCOUNT; ?></div>
      <div class="col-auto"><?php echo html_pull_down_menu('gl_acct', $gl_array_list, $cInfo->gl_acct ? $cInfo->gl_acct : INV_STOCK_DEFAULT_INVENTORY, 'style="font-size:11px;width:280px"'); ?></div>
      <div class="col-auto text-end"><?php echo TEXT_REFERENCE; ?></div>
      <div class="col-auto"><?php echo html_input_field('purchase_invoice_id', $cInfo->purchase_invoice_id ?? '', 'class="form-control form-control-sm" style="width:160px"'); ?></div>
    </div>
  </div>
</div>

<!-- Items card -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th></th>
            <th><?php echo TEXT_SKU; ?></th>
            <th class="text-end"><?php echo INV_HEADING_QTY_IN_STOCK; ?></th>
            <th class="text-end"><?php echo INV_ADJ_QUANTITY; ?></th>
            <th class="text-end"><?php echo TEXT_ITEM_COST; ?></th>
            <th class="text-end"><?php echo TEXT_BALANCE; ?></th>
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
    if (!isset($_POST['sku_' . $rowCnt])) break;
    $invType        = $db->Execute("SELECT inventory_type FROM " . TABLE_INVENTORY . " WHERE sku='" . db_input($_POST['sku_' . $rowCnt]) . "'");
    $imgSerialStyle = in_array($invType->fields['inventory_type'], array('sr', 'sa')) ? '' : 'display:none;';
    echo '  <tr class="' . ($rowCnt % 2 ? 'odd' : 'even') . '">' . chr(10);
    echo '    <td class="text-center">' . html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . INV_MSG_DELETE_INV_ITEM . '\')) removeInvRow(' . $rowCnt . ');"') . '</td>' . chr(10);
    echo '    <td style="white-space:nowrap">' . chr(10);
    echo html_input_field('sku_' . $rowCnt, $_POST['sku_' . $rowCnt], 'class="form-control form-control-sm d-inline-block" style="width:auto" size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '" onfocus="clearField(\'sku_' . $rowCnt . '\', \'' . TEXT_SEARCH . '\')" onblur="setField(\'sku_' . $rowCnt . '\', \'' . TEXT_SEARCH . '\'); loadSkuDetails(0,' . $rowCnt . ',0)"') . '&nbsp;';
    echo html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="InventoryList(' . $rowCnt . ')"');
    echo html_icon('actions/tab-new.png', TEXT_SERIAL_NUMBER, 'small', 'id="imgSerial_' . $rowCnt . '" align="top" style="cursor:pointer;' . $imgSerialStyle . '" onclick="serialList(\'serial_' . $rowCnt . '\')"');
    echo html_hidden_field('serial_' . $rowCnt, $_POST['serial_' . $rowCnt]) . chr(10);
    echo html_hidden_field('acct_' . $rowCnt, $_POST['acct_' . $rowCnt]) . chr(10);
    echo html_hidden_field('def_cost_' . $rowCnt, $_POST['def_cost_' . $rowCnt]) . chr(10);
    echo '    </td>' . chr(10);
    echo '    <td>' . html_input_field('stock_' . $rowCnt, $_POST['stock_' . $rowCnt], 'readonly class="form-control form-control-sm text-end" style="width:70px"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('qty_' . $rowCnt, $_POST['qty_' . $rowCnt], 'class="form-control form-control-sm text-end" style="width:70px" onchange="updateBalance()"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('price_' . $rowCnt, $currencies->format($_POST['price_' . $rowCnt]), 'class="form-control form-control-sm text-end" style="width:100px"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('bal_' . $rowCnt, $_POST['bal_' . $rowCnt], 'readonly class="form-control form-control-sm text-end" style="width:70px"') . '</td>' . chr(10);
    echo '    <td>' . html_input_field('desc_' . $rowCnt, $_POST['desc_' . $rowCnt], 'class="form-control form-control-sm"') . '</td>' . chr(10);
    echo '  </tr>' . chr(10);
    $rowCnt++;
  }
}
?>
        </tbody>
        <tfoot class="tfoot-plain">
          <tr>
            <td><?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addInvRow()"'); ?></td>
            <td colspan="6"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
<p style="font-size:11px"><?php echo JS_COGS_AUTO_CALC; ?></p>
<?php echo $hidden_fields; ?>
</form>
