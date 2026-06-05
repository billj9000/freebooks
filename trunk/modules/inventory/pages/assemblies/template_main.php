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
//  Path: /modules/inventory/pages/assemblies/template_main.php
//
echo html_form('inv_assy', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
$hidden_fields = null;
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('id', $cInfo->id) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['params']   = 'onclick="OpenAssyList()"';
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . INV_MSG_DELETE_INV_ITEM . '\')) submitToDo(\'delete\')"';
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"', $order = 2);
if ($security_level < 4) $toolbar->icon_list['delete']['show'] = false;
if ($security_level < 2) $toolbar->icon_list['save']['show']   = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.03.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="d-flex align-items-start gap-5 justify-content-center flex-wrap">

  <!-- Settings card -->
  <div class="card border-0" style="width:fit-content; font-size:11px; min-width:280px">
    <div class="card-body p-1">
      <?php if (ENABLE_MULTI_BRANCH): ?>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo GEN_STORE_ID; ?></div>
        <div><?php echo html_pull_down_menu('store_id', gen_get_store_ids(), $cInfo->store_id ? $cInfo->store_id : $_SESSION['admin_prefs']['def_store_id'], 'style="width:100%"'); ?></div>
      </div>
      <?php else: $hidden_fields .= html_hidden_field('store_id', $_SESSION['admin_prefs']['def_store_id']) . chr(10); ?>
      <?php endif; ?>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo TEXT_SKU; ?></div>
        <div style="white-space:nowrap">
          <?php echo html_input_field('sku_1', $cInfo->sku_1, 'size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '" onfocus="clearField(\'sku_1\', \'' . TEXT_SEARCH . '\')" onblur="setField(\'sku_1\', \'' . TEXT_SEARCH . '\'); loadSkuDetails(0, 1)"'); ?>
          <?php echo html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="InventoryList(1)"'); ?>
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo TEXT_DESCRIPTION; ?></div>
        <div><?php echo html_input_field('desc_1', $cInfo->desc_1, 'size="30" maxlength="50"'); ?></div>
      </div>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo TEXT_REFERENCE; ?></div>
        <div><?php echo html_input_field('purchase_invoice_id', $cInfo->purchase_invoice_id, 'size="21" maxlength="20"'); ?></div>
      </div>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo TEXT_POST_DATE; ?></div>
        <div><?php echo html_calendar_field($cal_assy); ?></div>
      </div>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo INV_QTY_ON_HAND; ?></div>
        <div><?php echo html_input_field('stock_1', $cInfo->stock_1, 'readonly size="13" style="text-align:right"'); ?></div>
      </div>
      <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
        <div><?php echo INV_HEADING_QTY_TO_ASSY; ?></div>
        <div><?php echo html_input_field('qty_1', $cInfo->qty_1, 'size="13" maxlength="10" style="text-align:right" onchange="updateBalance()"'); ?></div>
      </div>
      <div id="serial_row" class="d-flex justify-content-between align-items-center gap-3 mb-1" style="display:none">
        <div><?php echo INV_HEADING_SERIAL_NUMBER; ?></div>
        <div><?php echo html_input_field('serial_1', $cInfo->serial_1, 'size="25" maxlength="24" style="text-align:right"'); ?></div>
      </div>
      <div class="d-flex justify-content-between align-items-center gap-3">
        <div><?php echo TEXT_BALANCE; ?></div>
        <div><?php echo html_input_field('bal_1', $cInfo->bal_1, 'disabled size="13" style="text-align:right"'); ?></div>
      </div>
    </div>
  </div>

  <!-- BOM parts card -->
  <div class="card border-0" style="width:fit-content">
    <div class="card-header py-1 border-0 bg-transparent"><?php echo INV_ASSY_PARTS_REQUIRED; ?></div>
    <div class="card-body p-0">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_SKU; ?></th>
            <th><?php echo TEXT_DESCRIPTION; ?></th>
            <th class="text-end"><?php echo TEXT_NUM_REQUIRED; ?></th>
            <th class="text-end"><?php echo TEXT_NUM_AVAILABLE; ?></th>
          </tr>
        </thead>
        <tbody id="item_table">
<?php
$i = 1;
while (true) {
  if (!isset($_POST['assy_sku_' . $i])) break;
  echo '<tr>' . chr(10);
  echo html_hidden_field('qty_reqd_' . $i, $_POST['qty_reqd_' . $i]);
  echo '  <td>' . html_input_field('assy_sku_'  . $i, $_POST['assy_sku_'  . $i], 'readonly class="form-control form-control-sm" style="width:120px"') . '</td>' . chr(10);
  echo '  <td>' . html_input_field('assy_desc_' . $i, $_POST['assy_desc_' . $i], 'readonly class="form-control form-control-sm" style="width:240px"') . '</td>' . chr(10);
  echo '  <td>' . html_input_field('assy_qty_'  . $i, $_POST['assy_qty_'  . $i], 'readonly class="form-control form-control-sm text-end" style="width:80px"') . '</td>' . chr(10);
  echo '  <td>' . html_input_field('stk_'       . $i, $_POST['stk_'       . $i], 'readonly class="form-control form-control-sm text-end" style="width:80px"') . '</td>' . chr(10);
  echo '</tr>' . chr(10);
  $i++;
}
?>
        </tbody>
        <tfoot class="tfoot-plain">
          <tr>
            <td colspan="2" class="text-end"><?php echo TEXT_TOTAL; ?></td>
            <td><?php echo html_input_field('total_needed', '', 'readonly class="form-control form-control-sm text-end" style="width:80px"'); ?></td>
            <td><?php echo html_input_field('total_stock',  '', 'readonly class="form-control form-control-sm text-end" style="width:80px"'); ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

</div>
<?php echo $hidden_fields; ?>
</form>
