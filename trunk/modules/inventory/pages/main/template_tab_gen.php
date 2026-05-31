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
//  Path: /modules/inventory/pages/main/template_tab_gen.php
?>
<!-- jQuery UI image dialog (still uses jQuery UI dialog alongside Bootstrap) -->
<div id="inv_image" title="<?php echo htmlspecialchars($cInfo->sku); ?>" style="display:none">
  <?php if (isset($cInfo->image_with_path) && $cInfo->image_with_path): ?>
    <?php echo html_image(DIR_WS_MY_FILES . $_SESSION['company'] . '/inventory/images/' . $cInfo->image_with_path, '', 600) . chr(10); ?>
  <?php else: ?>
    <?php echo TEXT_NO_IMAGE; ?>
  <?php endif; ?>
  <div>
    <h2><?php echo TEXT_SKU . ': ' . $cInfo->sku; ?></h2>
    <p><?php echo $cInfo->description_sales; ?></p>
  </div>
</div>

<!-- ── Basic Info ─────────────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-body py-2">
    <div class="row g-2 align-items-center">

      <div class="col-auto">
        <label class="form-label mb-0"><?php echo TEXT_SKU; ?></label>
        <?php echo html_input_field('sku', $cInfo->sku, 'readonly="readonly" class="form-control form-control-sm d-inline-block" style="width:auto" size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '"', false); ?>
      </div>

      <div class="col-auto d-flex align-items-center gap-2 mt-3">
        <div class="form-check mb-0">
          <?php echo html_checkbox_field('inactive', '1', $cInfo->inactive, '', 'class="form-check-input"'); ?>
          <label class="form-check-label"><?php echo TEXT_INACTIVE; ?></label>
        </div>
      </div>

      <?php if (isset($cInfo->image_with_path) && $cInfo->image_with_path): ?>
      <div class="col-auto ms-auto">
        <?php echo html_image(DIR_WS_MY_FILES . $_SESSION['company'] . '/inventory/images/' . $cInfo->image_with_path, $cInfo->image_with_path, '', '80', 'onclick="showImage()" style="cursor:pointer"'); ?>
      </div>
      <?php endif; ?>

    </div><!-- /row -->

    <div class="row g-2 mt-1">
      <div class="col-md-6">
        <label class="form-label mb-0"><?php echo INV_ENTRY_INVENTORY_DESC_SHORT; ?></label>
        <div class="input-group input-group-sm">
          <?php echo html_input_field('description_short', $cInfo->description_short, 'class="form-control form-control-sm" maxlength="32"', false); ?>
          <?php if ($cInfo->id) echo html_icon('categories/preferences-system.png', TEXT_WHERE_USED, 'small', 'onclick="ajaxWhereUsed()" style="cursor:pointer;align-self:center;margin-left:4px"') . chr(10); ?>
        </div>
      </div>

      <?php if (isset($cInfo->quantity_on_hand)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_QTY_ON_HAND; ?></label>
        <?php echo html_input_field('quantity_on_hand', $currencies->precise($cInfo->quantity_on_hand), 'disabled="disabled" class="form-control form-control-sm text-end" style="width:80px"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->quantity_on_order)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_QTY_ON_ORDER; ?></label>
        <?php echo html_input_field('quantity_on_order', $currencies->precise($cInfo->quantity_on_order), 'disabled="disabled" class="form-control form-control-sm text-end" style="width:80px"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->quantity_on_allocation)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_QTY_ON_ALLOCATION; ?></label>
        <?php echo html_input_field('quantity_on_allocation', $currencies->precise($cInfo->quantity_on_allocation), 'disabled="disabled" class="form-control form-control-sm text-end" style="width:80px"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->quantity_on_sales_order)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_QTY_ON_SALES_ORDER; ?></label>
        <?php echo html_input_field('quantity_on_sales_order', $currencies->precise($cInfo->quantity_on_sales_order), 'disabled="disabled" class="form-control form-control-sm text-end" style="width:80px"', false); ?>
      </div>
      <?php endif; ?>
    </div><!-- /row -->

    <div class="row g-2 mt-1">
      <?php if (isset($cInfo->minimum_stock_level)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_ENTRY_ITEM_MINIMUM_STOCK; ?></label>
        <?php echo html_input_field('minimum_stock_level', $currencies->precise($cInfo->minimum_stock_level), 'class="form-control form-control-sm text-end" style="width:80px" maxlength="5"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->reorder_quantity)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_ENTRY_ITEM_REORDER_QUANTITY; ?></label>
        <?php echo html_input_field('reorder_quantity', $currencies->precise($cInfo->reorder_quantity), 'class="form-control form-control-sm text-end" style="width:80px" maxlength="5"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->lead_time)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_HEADING_LEAD_TIME; ?></label>
        <?php echo html_input_field('lead_time', $cInfo->lead_time, 'class="form-control form-control-sm text-end" style="width:80px" maxlength="5"', false); ?>
      </div>
      <?php endif; ?>
    </div><!-- /row -->

  </div>
</div>

<?php if (in_array('sell', $cInfo->posible_transactions)): ?>
<!-- ── Customer Details ───────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-header py-1"><strong><?php echo TEXT_CUSTOMER_DETAILS; ?></strong></div>
  <div class="card-body py-2">

    <?php if (isset($cInfo->description_sales)): ?>
    <div class="mb-2">
      <label class="form-label mb-0"><?php echo INV_ENTRY_INVENTORY_DESC_SALES; ?></label>
      <?php echo html_textarea_field('description_sales', 75, 2, $cInfo->description_sales, 'class="form-control form-control-sm"', true); ?>
    </div>
    <?php endif; ?>

    <div class="row g-3 align-items-start">

      <!-- Column 1: Full Price / Margin -->
      <div class="col-auto">
        <?php if (isset($cInfo->full_price)): ?>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_FULL_PRICE; ?><?php if (ENABLE_MULTI_CURRENCY) echo ' (' . DEFAULT_CURRENCY . ')'; ?></label>
          <div class="input-group input-group-sm">
            <?php echo html_input_field('full_price', $currencies->precise($cInfo->full_price), 'onchange="update_full_price_incl_tax(true, true, false);" class="form-control form-control-sm text-end" style="width:120px" maxlength="20"', false); ?>
            <?php if (isset($cInfo->price_sheet)) echo html_icon('mimetypes/x-office-spreadsheet.png', BOX_SALES_PRICE_SHEETS, 'small', 'onclick="priceMgr(' . $cInfo->id . ', 0, 0, \'c\', \'\')" style="cursor:pointer;align-self:center;margin-left:4px"') . chr(10); ?>
          </div>
          <small class="text-muted"><?php echo $currencies->precise($cInfo->full_price); ?></small>
        </div>
        <?php endif; ?>
        <?php if (isset($cInfo->product_margin)): ?>
        <div>
          <label class="form-label mb-0"><?php echo INV_MARGIN; ?></label>
          <div class="input-group input-group-sm" style="width:140px">
            <?php echo html_input_field('product_margin', $currencies->precise($cInfo->product_margin), 'onchange="product_margin_change();" class="form-control form-control-sm text-end" maxlength="5"', false); ?>
            <span class="input-group-text">%</span>
          </div>
          <small class="text-muted"><?php echo $currencies->precise($cInfo->product_margin); ?>%</small>
        </div>
        <?php endif; ?>
      </div>

      <!-- Column 2: Full Price with Tax / Retail Markup -->
      <div class="col-auto">
        <?php if (isset($cInfo->full_price_with_tax)): ?>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_FULL_PRICE_WT; ?><?php if (ENABLE_MULTI_CURRENCY) echo ' (' . DEFAULT_CURRENCY . ')'; ?></label>
          <?php echo html_input_field('full_price_with_tax', $currencies->precise($cInfo->full_price_with_tax), 'onchange="update_full_price_incl_tax(true, false, true);" class="form-control form-control-sm text-end" style="width:120px" maxlength="20"', false); ?>
          <small class="text-muted"><?php echo $cInfo->full_price_with_tax; ?></small>
        </div>
        <?php endif; ?>
        <?php if (isset($cInfo->product_markup)): ?>
        <div>
          <label class="form-label mb-0"><?php echo TEXT_RETAIL_MARKUP; ?></label>
          <?php echo html_input_field('product_markup', $currencies->precise($cInfo->product_markup), 'onchange="product_markup_change();" class="form-control form-control-sm text-end" style="width:120px" maxlength="5"', false); ?>
          <small class="text-muted"><?php echo $currencies->precise($cInfo->product_markup); ?></small>
        </div>
        <?php endif; ?>
      </div>

      <!-- Column 3: Default Sales Tax / Default Price Sheet -->
      <div class="col-auto">
        <?php if (isset($cInfo->item_taxable)): ?>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_ITEM_TAXABLE; ?></label>
          <?php echo html_pull_down_menu('item_taxable', $tax_rates, $cInfo->item_taxable, 'onchange="update_full_price_incl_tax(true, true, false);" class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>
        <?php if (isset($cInfo->price_sheet)): ?>
        <div>
          <label class="form-label mb-0"><?php echo TEXT_DEFAULT_PRICE_SHEET; ?></label>
          <?php echo html_pull_down_menu('price_sheet', get_price_sheet_data('c'), $cInfo->price_sheet, 'class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>
      </div>

    </div>

  </div>
</div>
<?php endif; ?>

<?php if ($_SESSION['admin_security'][SECURITY_ID_PURCHASE_INVENTORY] > 0 && in_array('purchase', $cInfo->posible_transactions)): ?>
<!-- ── Vendor Details ─────────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-header py-1"><strong><?php echo TEXT_VENDOR_DETAILS; ?></strong></div>
  <div class="card-body py-2 p-0">
    <table class="table table-sm pkg-table mb-0">
      <thead>
        <tr>
          <th style="width:5%"></th>
          <th><?php echo INV_HEADING_PREFERRED_VENDOR; ?></th>
          <th><?php echo INV_ENTRY_INVENTORY_DESC_PURCHASE; ?></th>
          <th><?php echo INV_ENTRY_COST_CURRENCY; ?></th>
          <th><?php echo INV_ENTRY_INV_ITEM_COST; ?></th>
          <th><?php echo TEXT_PACKAGE_QUANTITY; ?></th>
          <th><?php echo INV_ENTRY_PURCH_TAX; ?></th>
          <th><?php echo TEXT_DEFAULT_PRICE_SHEET; ?></th>
        </tr>
      </thead>
      <tbody id="vendor_table_tbody">
      <?php
      $odd = false;
      if (is_array($cInfo->purchase_array)) foreach ($cInfo->purchase_array as $purchaseRow) {
        $i = rand();
        echo '<tr class="' . ($odd ? 'odd' : 'even') . '" id="row_id_' . $i . '">';
        echo '<td>' . html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . INV_MSG_DELETE_VENDOR_ROW . '\')) removeVendorRow(' . $i . ');"') . '</td>';
        echo html_hidden_field('row_id_array[]', $purchaseRow['id']);
        echo '<td>' . (isset($cInfo->vendor_id) ? html_pull_down_menu('vendor_id_array[]', gen_get_contact_array_by_type('v'), $purchaseRow['vendor_id'], 'class="form-select form-select-sm"') : '') . '</td>';
        echo '<td>' . (isset($cInfo->description_purchase) ? html_textarea_field('description_purchase_array[]', 40, 2, $purchaseRow['description_purchase'], 'class="form-control form-control-sm"', true) : '') . '</td>';
        echo '<td>' . (isset($cInfo->item_cost) ? html_pull_down_menu('item_cost_currency_array[]', gen_get_pull_down(TABLE_CURRENCIES, false, false, 'code', 'title'), $purchaseRow['currency_code'] ?: DEFAULT_CURRENCY, 'class="form-select form-select-sm"') : '') . '</td>';
        echo '<td>' . (isset($cInfo->item_cost) ? html_input_field('item_cost_array[]', $currencies->precise($purchaseRow['item_cost'], false, $purchaseRow['currency_code'] ?: DEFAULT_CURRENCY), 'onchange="what_to_update();" class="form-control form-control-sm text-end" style="width:100px" maxlength="20"', false) : '') . '</td>';
        echo '<td>' . (isset($cInfo->item_cost) ? html_input_field('purch_package_quantity_array[]', $currencies->precise($purchaseRow['purch_package_quantity']), 'class="form-control form-control-sm text-end" style="width:70px" maxlength="5"') : '') . '</td>';
        echo '<td>' . (isset($cInfo->purch_taxable) ? html_pull_down_menu('purch_taxable_array[]', $purch_tax_rates, $purchaseRow['purch_taxable'], 'class="form-select form-select-sm"') : '') . '</td>';
        echo '<td>' . (isset($cInfo->price_sheet_v) ? html_pull_down_menu('price_sheet_v_array[]', get_price_sheet_data('v'), $purchaseRow['price_sheet_v'], 'class="form-select form-select-sm"')
          . '&nbsp;' . html_icon('mimetypes/x-office-spreadsheet.png', BOX_SALES_PRICE_SHEETS, 'small', 'onclick="priceMgr(' . $cInfo->id . ', 0, 0, \'v\', ' . $purchaseRow['id'] . ')"') : '') . '</td>';
        echo '</tr>';
        $odd = !$odd;
      }
      ?>
      </tbody>
      <tfoot>
        <tr>
          <td><?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addVendorRow()"'); ?></td>
          <td colspan="7"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Item Details ───────────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-header py-1"><strong><?php echo TEXT_ITEM_DETAILS; ?></strong></div>
  <div class="card-body py-2">
    <div class="row g-2">

      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_ENTRY_INVENTORY_TYPE; ?></label>
        <?php
        echo html_hidden_field('inventory_type', $cInfo->inventory_type);
        echo html_input_field('inv_type_desc', $cInfo->title, 'readonly="readonly" class="form-control form-control-sm"', false);
        ?>
      </div>

      <?php if (isset($cInfo->upc_code)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_HEADING_UPC_CODE; ?></label>
        <?php echo html_input_field('upc_code', $cInfo->upc_code, 'class="form-control form-control-sm text-end" style="width:140px" maxlength="13"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($cInfo->posible_cost_methodes)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_ENTRY_INVENTORY_COST_METHOD; ?></label>
        <?php
        $cost_pulldown_array = [];
        foreach ($cost_methods as $key => $value) {
          if (in_array($key, $cInfo->posible_cost_methodes)) $cost_pulldown_array[] = ['id' => $key, 'text' => $value];
        }
        echo html_pull_down_menu('cost_method', $cost_pulldown_array, $cInfo->cost_method,
          ($cInfo->last_journal_date == '0000-00-00 00:00:00' || is_null($cInfo->last_journal_date) ? '' : 'disabled="disabled" ') . 'class="form-select form-select-sm"');
        if (isset($cInfo->serialize)) echo '&nbsp;' . INV_ENTRY_INVENTORY_SERIALIZE;
        ?>
      </div>
      <?php endif; ?>

    </div><!-- /row -->

    <div class="row g-2 mt-2">
      <!-- GL Accounts -->
      <div class="col-md-6">
        <?php if (isset($cInfo->account_sales_income)): ?>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_ACCT_SALES; ?></label>
          <?php echo html_pull_down_menu('account_sales_income', $gl_array_list, $cInfo->account_sales_income, 'class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>
        <?php if (isset($cInfo->account_inventory_wage)): ?>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_ACCT_INV; ?></label>
          <?php echo html_pull_down_menu('account_inventory_wage', $gl_array_list, $cInfo->account_inventory_wage, 'class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>
        <?php if (isset($cInfo->account_cost_of_sales)): ?>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_ACCT_COS; ?></label>
          <?php echo html_pull_down_menu('account_cost_of_sales', $gl_array_list, $cInfo->account_cost_of_sales, 'class="form-select form-select-sm"'); ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Image + Attachments -->
      <div class="col-md-6">
        <?php if (isset($cInfo->image_with_path)): ?>
        <div class="mb-2">
          <?php echo html_hidden_field('image_with_path', $cInfo->image_with_path); ?>
          <label class="form-label mb-0"><?php echo INV_ENTRY_IMAGE_PATH; ?></label>
          <?php echo html_input_field('inventory_path', substr($cInfo->image_with_path, 0, strrpos($cInfo->image_with_path, '/')), 'class="form-control form-control-sm"'); ?>
        </div>
        <div class="mb-2">
          <label class="form-label mb-0"><?php echo INV_ENTRY_SELECT_IMAGE; ?></label>
          <?php echo html_file_field('inventory_image'); ?>
          <?php if ($cInfo->image_with_path) echo '&nbsp;' . html_checkbox_field('remove_image', '1', $cInfo->remove_image, '', 'class="form-check-input"') . ' ' . TEXT_REMOVE . ': ' . htmlspecialchars($cInfo->image_with_path); ?>
        </div>
        <?php endif; ?>

        <fieldset class="border rounded p-2">
          <legend class="float-none w-auto fs-6 px-1"><?php echo TEXT_ATTACHMENTS; ?></legend>
          <div class="mb-1"><?php echo TEXT_SELECT_FILE_TO_ATTACH . ' ' . html_file_field('file_name'); ?></div>
          <table class="table table-sm pkg-table mb-0">
            <thead>
              <tr>
                <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
                <th><?php echo TEXT_FILENAME; ?></th>
                <th><?php echo TEXT_ACTION; ?></th>
              </tr>
            </thead>
            <tbody>
            <?php
            if (!empty($cInfo->attachments)) {
              foreach ($cInfo->attachments as $key => $value) {
                echo '<tr>';
                echo '<td>' . html_checkbox_field('rm_attach_' . $key, '1', false, '', 'class="form-check-input"') . '</td>';
                echo '<td>' . htmlspecialchars($value) . '</td>';
                echo '<td><button type="button" class="btn btn-sm btn-outline-secondary" onclick="submitSeq(' . $key . ', \'download\', true)">' . TEXT_DOWNLOAD . '</button></td>';
                echo '</tr>' . chr(10);
              }
            } else {
              echo '<tr><td colspan="3">' . TEXT_NO_DOCUMENTS . '</td></tr>';
            }
            ?>
            </tbody>
          </table>
        </fieldset>
      </div>
    </div><!-- /row -->

    <?php if ($cInfo->qty_table): ?>
    <div class="mt-2"><?php echo $cInfo->qty_table; ?></div>
    <?php endif; ?>

  </div>
</div>
