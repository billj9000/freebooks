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
//  Path: /modules/inventory/pages/main/template_tab_ms.php
?>
<p><?php echo TEXT_MS_HELP; ?></p>

<?php if ($cInfo->edit_ms_list): ?>
<div class="row g-3 mb-3">

  <!-- Attribute 1 -->
  <div class="col-md-6">
    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header"><?php echo INV_TEXT_ATTRIBUTE_1; ?></div>
      <div class="card-body py-2 px-3" style="font-size:11px">
        <div class="row g-2 align-items-center mb-2">
          <div class="col-4 text-end"><?php echo TEXT_TITLE; ?></div>
          <div class="col-8"><?php echo html_input_field('attr_name_0', $cInfo->attr_name_0, 'class="form-control form-control-sm" size="11" maxlength="10" onchange="masterStockTitle(0)"'); ?></div>
        </div>
        <div class="mb-2">
          <?php echo INV_MASTER_STOCK_ATTRIB_ID; ?>&nbsp;
          <?php echo html_input_field('attr_id_0', '', 'class="form-control form-control-sm d-inline-block" style="width:60px" size="3" maxlength="2"', true); ?>
          <button type="button" class="btn btn-primary btn-sm" onclick="masterStockBuildList('add', 0)"><?php echo TEXT_ADD; ?></button>
        </div>
        <div class="row g-2 align-items-center mb-2">
          <div class="col-4 text-end"><?php echo TEXT_DESCRIPTION; ?></div>
          <div class="col-8"><?php echo html_input_field('attr_desc_0', '', 'class="form-control form-control-sm"', true); ?></div>
        </div>
        <div class="mb-1"><strong><?php echo INV_TEXT_ATTRIBUTES; ?></strong></div>
        <div class="d-flex align-items-start gap-2">
          <?php echo html_pull_down_menu('attr_index_0', $cInfo->attr_array0, '', 'class="form-select form-select-sm" size="5"'); ?>
          <?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="masterStockBuildList(\'delete\', 0)"'); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Attribute 2 -->
  <div class="col-md-6">
    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header"><?php echo INV_TEXT_ATTRIBUTE_2; ?></div>
      <div class="card-body py-2 px-3" style="font-size:11px">
        <div class="row g-2 align-items-center mb-2">
          <div class="col-4 text-end"><?php echo TEXT_TITLE; ?></div>
          <div class="col-8"><?php echo html_input_field('attr_name_1', $cInfo->attr_name_1, 'class="form-control form-control-sm" size="11" maxlength="10" onchange="masterStockTitle(1)"'); ?></div>
        </div>
        <div class="mb-2">
          <?php echo INV_MASTER_STOCK_ATTRIB_ID; ?>&nbsp;
          <?php echo html_input_field('attr_id_1', '', 'class="form-control form-control-sm d-inline-block" style="width:60px" size="3" maxlength="2"', true); ?>
          <button type="button" class="btn btn-primary btn-sm" onclick="masterStockBuildList('add', 1)"><?php echo TEXT_ADD; ?></button>
        </div>
        <div class="row g-2 align-items-center mb-2">
          <div class="col-4 text-end"><?php echo TEXT_DESCRIPTION; ?></div>
          <div class="col-8"><?php echo html_input_field('attr_desc_1', '', 'class="form-control form-control-sm"', true); ?></div>
        </div>
        <div class="mb-1"><strong><?php echo INV_TEXT_ATTRIBUTES; ?></strong></div>
        <div class="d-flex align-items-start gap-2">
          <?php echo html_pull_down_menu('attr_index_1', $cInfo->attr_array1, '', 'class="form-select form-select-sm" size="5"'); ?>
          <?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="masterStockBuildList(\'delete\', 1)"'); ?>
        </div>
      </div>
    </div>
  </div>

</div><!-- /row -->
<?php endif; ?>

<!-- SKU list -->
<div class="card fb-card">
  <?php if ($cInfo->edit_ms_list): ?>
  <div class="card-header py-1 fb-widget-header"><?php echo INV_MS_CREATED_SKUS; ?></div>
  <?php endif; ?>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="sku_list" class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_SKU; ?></th>
            <th><?php echo INV_ENTRY_INVENTORY_DESC_SHORT; ?></th>
            <th><?php echo $cInfo->attr_name_0; ?></th>
            <th><?php echo $cInfo->attr_name_1; ?></th>
            <th class="text-center"><?php echo INV_QTY_ON_HAND; ?></th>
            <th class="text-center"><?php echo INV_QTY_ON_ORDER; ?></th>
            <th class="text-center"><?php echo INV_QTY_ON_SALES_ORDER; ?></th>
            <th class="text-center"><?php echo INV_ENTRY_ITEM_MINIMUM_STOCK; ?></th>
            <th class="text-center"><?php echo INV_ENTRY_ITEM_REORDER_QUANTITY; ?></th>
            <th class="text-end"><?php echo INV_ENTRY_INV_ITEM_COST; ?></th>
            <th class="text-end"><?php echo INV_ENTRY_FULL_PRICE; ?></th>
          </tr>
        </thead>
        <tbody id="sku_list_body">
        <?php
        $odd = false;
        if (!empty($cInfo->child_array)) {
          foreach ($cInfo->child_array as $value) {
            $link = html_href_link(FILENAME_DEFAULT, 'module=inventory&amp;page=main&amp;cID=' . $value['id'] . '&amp;action=edit', 'SSL');
            $row_class = $value['inactive'] ? 'table-secondary' :
              (($value['on_hand'] < $value['min_stock'] && $value['min_stock'] != 0) ? 'table-success' :
              ($odd ? 'odd' : 'even'));
            echo '<tr class="' . $row_class . '" style="cursor:pointer" onclick="window.open(\'' . $link . '\',\'_blank\')">' . chr(10);
            echo '  <td>' . htmlspecialchars($value['sku']) . '</td>' . chr(10);
            echo '  <td>' . htmlspecialchars($value['desc']) . '</td>' . chr(10);
            echo '  <td>' . htmlspecialchars($value['0']) . '</td>' . chr(10);
            echo '  <td>' . htmlspecialchars($value['1']) . '</td>' . chr(10);
            echo '  <td class="text-center">' . $value['on_hand'] . '</td>' . chr(10);
            echo '  <td class="text-center">' . $value['on_order'] . '</td>' . chr(10);
            echo '  <td class="text-center">' . $value['on_sales'] . '</td>' . chr(10);
            echo '  <td class="text-center">' . $value['min_stock'] . '</td>' . chr(10);
            echo '  <td class="text-center">' . $value['reorder_qty'] . '</td>' . chr(10);
            echo '  <td class="text-end">' . $currencies->precise($value['cost']) . '</td>' . chr(10);
            echo '  <td class="text-end">' . $currencies->precise($value['price']) . '</td>' . chr(10);
            echo '</tr>' . chr(10);
            $odd = !$odd;
          }
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
