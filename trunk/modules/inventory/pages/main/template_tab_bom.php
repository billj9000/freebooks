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
//  Path: /modules/inventory/pages/main/template_tab_bom.php
?>
<div class="pkg-wrap" style="max-width:900px;margin:0 auto">
  <table class="table table-sm pkg-table">
    <thead>
      <tr>
        <th></th>
        <th><?php echo TEXT_SKU; ?></th>
        <th><?php echo INV_ENTRY_INVENTORY_DESC_SHORT; ?></th>
        <th class="text-end"><?php echo TEXT_QUANTITY; ?></th>
        <th class="text-end"><?php echo INV_ENTRY_INV_ITEM_COST; ?></th>
        <th class="text-end"><?php echo INV_ENTRY_FULL_PRICE; ?></th>
      </tr>
    </thead>
    <tbody id="bom_table_body">
<?php
if (count(is_array($cInfo->bom) ? $cInfo->bom : [])) {
  for ($j = 0, $i = 1; $j < count($cInfo->bom); $j++, $i++) {
    $readonly = '';
    echo '    <tr>';
    echo '      <td class="text-center">';
    if ($cInfo->allow_edit_bom) {
      echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small',
        'onclick="if (confirm(\'' . TEXT_DELETE_ENTRY . '\')) $(this).closest(\'tr\').remove();bomTotalValues();"');
    } else {
      echo '&nbsp;';
      $readonly = 'readonly="readonly" ';
    }
    echo '      </td>' . chr(10);
    echo '      <td>';
    echo '        <input type="hidden" name="id_' . $i . '" id="id_' . $i . '" value="' . $cInfo->bom[$j]['id'] . '" />' . chr(10);
    echo '        <input type="text" name="assy_sku[]" id="sku_' . $i . '" value="' . htmlspecialchars($cInfo->bom[$j]['sku']) . '" ' . $readonly . 'class="form-control form-control-sm d-inline-block" style="width:auto" size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '" onchange="bom_guess(' . $i . ');" />&nbsp;';
    if ($cInfo->allow_edit_bom) echo html_icon('actions/system-search.png', TEXT_SKU, 'small', 'align="top" style="cursor:pointer" onclick="InventoryList(' . $i . ')"') . chr(10);
    echo '      </td>' . chr(10);
    echo '      <td><input type="text" name="assy_desc[]" id="desc_' . $i . '" value="' . htmlspecialchars($cInfo->bom[$j]['description']) . '" ' . $readonly . 'class="form-control form-control-sm" size="40" maxlength="64" /></td>' . chr(10);
    echo '      <td><input type="text" name="assy_qty[]" id="qty_' . $i . '" value="' . $currencies->precise($cInfo->bom[$j]['qty']) . '" ' . $readonly . 'class="form-control form-control-sm text-end" size="6" maxlength="5" /></td>' . chr(10);
    echo '      <td><input type="text" name="assy_item_cost[]" id="item_cost_' . $i . '" value="' . $currencies->precise($cInfo->bom[$j]['item_cost']) . '" ' . $readonly . 'class="form-control form-control-sm text-end" size="6" maxlength="5" /></td>' . chr(10);
    echo '      <td><input type="text" name="assy_sales_price[]" id="sales_price_' . $i . '" value="' . $currencies->precise($cInfo->bom[$j]['full_price']) . '" ' . $readonly . 'class="form-control form-control-sm text-end" size="6" maxlength="5" /></td>' . chr(10);
    echo '    </tr>';
  }
} else {
  echo '<script>addBOMRow();</script>';
}
?>
    </tbody>
    <tfoot class="tfoot-plain">
      <tr>
        <td>
          <?php if ($cInfo->allow_edit_bom): ?>
          <?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addBOMRow()"'); ?>
          <?php else: ?>&nbsp;<?php endif; ?>
        </td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td class="text-end"><strong><?php echo TEXT_TOTAL; ?></strong></td>
        <td><?php echo html_input_field('total_item_cost', '', 'readonly="readonly" class="form-control form-control-sm text-end" style="width:100px"'); ?></td>
        <td><?php echo html_input_field('total_sales_price', '', 'readonly="readonly" class="form-control form-control-sm text-end" style="width:100px"'); ?></td>
      </tr>
    </tfoot>
  </table>
</div>
<?php echo '<script>bomTotalValues();</script>'; ?>
