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
//  Path: /modules/inventory/pages/popup_price_mgr/template_main.php
//
echo html_form('price_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.06');
echo $toolbar->build_toolbar();
?>
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
  <div class="card-body p-2">
    <div class="row g-1">
      <div class="col-6">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo TEXT_SKU; ?>:</td><td class="py-0"><strong><?php echo $inventory_details->fields['sku']; ?></strong></td></tr>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_QTY_ON_HAND; ?>:</td><td class="py-0"><?php echo $inventory_details->fields['quantity_on_hand']; ?></td></tr>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_QTY_ON_SALES_ORDER; ?>:</td><td class="py-0"><?php echo $inventory_details->fields['quantity_on_sales_order']; ?></td></tr>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_QTY_ON_ALLOCATION; ?>:</td><td class="py-0"><?php echo $inventory_details->fields['quantity_on_allocation']; ?></td></tr>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_QTY_ON_ORDER; ?>:</td><td class="py-0"><?php echo $inventory_details->fields['quantity_on_order']; ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="col-6">
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_ENTRY_INVENTORY_DESC_SHORT; ?>:</td><td class="py-0"><strong><?php echo $inventory_details->fields['description_short']; ?></strong></td></tr>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_ENTRY_INV_ITEM_COST; ?>:</td><td class="py-0"><?php echo $currencies->precise($item_cost); ?></td></tr>
            <tr><td class="text-nowrap pe-2 py-0"><?php echo INV_ENTRY_FULL_PRICE; ?>:</td><td class="py-0"><?php echo $currencies->precise($full_price); ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if ($price_sheets->RecordCount() > 0): ?>
  <?php if (ENABLE_MULTI_CURRENCY): ?>
    <p class="text-danger small"><?php echo sprintf(GEN_PRICE_SHEET_CURRENCY_NOTE, $currencies->currencies[DEFAULT_CURRENCY]['title']); ?></p>
  <?php endif; ?>
  <ul class="nav nav-tabs fb-tabs mb-0" id="priceTabs" role="tablist">
    <?php $m = 1; $price_sheets->Move(0); while (!$price_sheets->EOF): $active = ($m == 1) ? ' active' : ''; ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link<?php echo $active; ?>" id="tab_<?php echo $price_sheets->fields['id']; ?>-btn"
              data-bs-toggle="tab" data-bs-target="#tab_<?php echo $price_sheets->fields['id']; ?>"
              type="button" role="tab">
        <?php echo $price_sheets->fields['sheet_name'] . ' (Rev. ' . $price_sheets->fields['revision'] . ')'; ?>
      </button>
    </li>
    <?php $price_sheets->MoveNext(); $m++; endwhile; ?>
  </ul>
  <div class="tab-content fb-tab-content" id="priceTabsContent">
    <?php $m = 1; $price_sheets->Move(0); while (!$price_sheets->EOF): $active = ($m == 1) ? ' show active' : ''; ?>
    <div class="tab-pane fade<?php echo $active; ?>" id="tab_<?php echo $price_sheets->fields['id']; ?>" role="tabpanel">
      <?php
        $checked = isset($special_prices[$price_sheets->fields['id']]) ? false : true;
        echo html_checkbox_field('def_' . $m, '1', $checked, '', '') . '&nbsp;' . TEXT_USE_DEFAULT_PRICE_SHEET . '<br>';
        echo html_hidden_field('id_' . $m, $price_sheets->fields['id']) . chr(10);
        echo html_hidden_field('sheet_name_' . $m, $price_sheets->fields['sheet_name']) . chr(10);
      ?>
      <table class="pkg-table table table-borderless table-hover table-sm mt-2" style="width:100%;">
        <thead class="table-dark">
          <tr>
            <th class="text-center"><?php echo TEXT_LEVEL; ?></th>
            <th class="text-center"><?php echo TEXT_QUANTITY; ?></th>
            <th class="text-center"><?php echo TEXT_SOURCE; ?></th>
            <th class="text-center"><?php echo TEXT_ADJUSTMENT; ?></th>
            <th class="text-center"><?php echo INV_ADJ_VALUE; ?></th>
            <th class="text-center"><?php echo INV_ROUNDING; ?></th>
            <th class="text-center"><?php echo INV_RND_VALUE; ?></th>
            <th class="text-center"><?php echo TEXT_PRICE; ?></th>
            <th class="text-center"><?php echo TEXT_MARGIN; ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $levels = isset($special_prices[$price_sheets->fields['id']]) ? $special_prices[$price_sheets->fields['id']] : $price_sheets->fields['default_levels'];
          $price_levels = explode(';', $levels);
          $first_source_list = $price_mgr_sources;
          array_shift($first_source_list);
          array_pop($first_source_list);
          for ($i=0, $j=1; $i < MAX_NUM_PRICE_LEVELS; $i++, $j++):
            $objID = $m . '_' . $j;
            $level_info = explode(':', $price_levels[$i]);
            $price   = $level_info[0] ? $level_info[0] : (($i == 0) ? $full_price : 0);
            $qty     = $level_info[1] ? $level_info[1] : $j;
            $src     = $level_info[2] ? $level_info[2] : 0;
            $adj     = $level_info[3] ? $level_info[3] : 0;
            $adj_val = $level_info[4] ? $level_info[4] : '0';
            $rnd     = $level_info[5] ? $level_info[5] : 0;
            $rnd_val = $level_info[6] ? $level_info[6] : '0';
          ?>
          <tr>
            <td class="text-center"><?php echo $j; ?></td>
            <td><?php echo html_input_field('qty_' . $objID, $qty, 'size="5" style="text-align:right" onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_pull_down_menu('src_' . $objID, gen_build_pull_down(($i==0) ? $first_source_list : $price_mgr_sources), $src, 'onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_pull_down_menu('adj_' . $objID, gen_build_pull_down($price_mgr_adjustments), $adj, 'onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_input_field('adj_val_' . $objID, $currencies->format($adj_val), 'size="10" style="text-align:right" onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_pull_down_menu('rnd_' . $objID, gen_build_pull_down($price_mgr_rounding), $rnd, 'onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_input_field('rnd_val_' . $objID, $currencies->precise($rnd_val), 'size="10" style="text-align:right" onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_input_field('price_' . $objID, $currencies->precise($price), 'size="11" style="text-align:right" onchange="updatePrice(' . $m . ')"'); ?></td>
            <td><?php echo html_input_field('margin_' . $objID, $currencies->precise('0'), 'readonly="readonly" size="6" style="text-align:right"'); ?></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
    <?php $price_sheets->MoveNext(); $m++; endwhile; ?>
  </div>
<?php else: ?>
  <p class="text-center"><strong><?php echo INV_NO_PRICE_SHEETS; ?></strong></p>
<?php endif; ?>
</form>
