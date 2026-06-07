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
//  Path: /modules/inventory/pages/popup_prices/template_main.php
//
echo html_form('prices', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '')   . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
if (!ENABLE_MULTI_CURRENCY) echo html_hidden_field('display_currency', DEFAULT_CURRENCY);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.06');
echo $toolbar->build_toolbar();
?>
<div class="d-inline-flex flex-column gap-2">
<div>
  <div class="d-flex justify-content-between align-items-baseline mb-1">
    <h1 class="h5 fw-bold mb-0"><?php echo PAGE_TITLE . ' - ' . htmlspecialchars($sku); ?></h1>
    <?php if (ENABLE_MULTI_CURRENCY): ?>
    <span class="ms-3"><?php echo TEXT_CURRENCY . ' ' . html_input_field('display_currency', DEFAULT_CURRENCY, 'readonly="readonly" size="4"'); ?></span>
    <?php endif; ?>
  </div>
  <div class="mb-1"><?php echo htmlspecialchars($inventory_details->fields['description_short']); ?></div>
  <?php if ($type == 'v'): ?>
  <table class="pkg-table table table-borderless table-hover table-sm mb-0" style="width:auto">
    <tbody>
      <tr style="cursor:pointer" onclick="setReturnPrice('<?php echo $rowId; ?>', 'cost')">
        <td><?php echo INV_ENTRY_INV_ITEM_COST; ?></td>
        <td class="text-end"><?php echo html_input_field('cost', $currencies->precise($inventory_details->fields['item_cost']), 'style="cursor:pointer;text-align:right" readonly="readonly" size="10"'); ?></td>
      </tr>
    </tbody>
  </table>
  <?php else: ?>
  <table class="pkg-table table table-borderless table-hover table-sm mb-0" style="width:auto">
    <tbody>
      <tr style="cursor:pointer" onclick="setReturnPrice('<?php echo $rowId; ?>', 'full')">
        <td><?php echo INV_ENTRY_FULL_PRICE; ?></td>
        <td class="text-end"><?php echo html_input_field('full', $currencies->precise($inventory_details->fields['full_price']), 'style="cursor:pointer;text-align:right" readonly="readonly" size="10"'); ?></td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php
if (is_object($price_sheets)) {
  if ($price_sheets->RecordCount() > 0) {
    $cnt = 0;
    while (!$price_sheets->EOF) {
      $first_source_list = $price_mgr_sources;
      array_shift($first_source_list);
      array_pop($first_source_list);
      $levels = isset($special_prices[$price_sheets->fields['id']]) ? $special_prices[$price_sheets->fields['id']] : $price_sheets->fields['default_levels'];
      $prices = inv_calculate_prices($inventory_details->fields['item_cost'], $inventory_details->fields['full_price'], $levels);
?>
<div class="card">
  <div class="card-header py-1"><span class="fw-normal"><?php echo PRICE_SHEETS_LOG; ?></span><strong><?php echo $price_sheets->fields['sheet_name']; ?></strong></div>
  <div class="card-body p-0">
    <table class="pkg-table table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th class="text-center"><?php echo TEXT_QUANTITY; ?></th>
          <th class="text-center"><?php echo TEXT_PRICE; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($prices as $price_level): ?>
        <tr style="cursor:pointer" onclick="setReturnPrice('<?php echo $rowId; ?>', 'price_<?php echo $cnt; ?>')">
          <td class="text-center"><?php echo $price_level['qty']; ?></td>
          <td class="text-end"><?php echo html_input_field('price_' . $cnt, $price_level['price'], 'style="cursor:pointer;text-align:right" readonly="readonly" size="10"'); ?></td>
        </tr>
        <?php $cnt++; endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
      $price_sheets->MoveNext();
    }
    echo html_hidden_field('num_prices', $cnt);
  }
} else {
  echo '<p class="text-center">' . INV_NO_PRICE_SHEETS . '</p>';
}
?>
</div><!-- /d-inline-flex -->
</form>
