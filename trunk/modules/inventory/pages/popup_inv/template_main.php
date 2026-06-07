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
//  Path: /modules/inventory/pages/popup_inv/template_main.php
//
echo html_form('search_form', FILENAME_DEFAULT, gen_get_all_get_params(array('action','list', 'f0', 'f1', 'f2', 'f3', 'f4', 'f5'))) . chr(10);
echo html_hidden_field('action',   '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.04.01');
$toolbar->search_period = $acct_period;
echo $toolbar->build_toolbar($add_search = true);
?>
<h1><?php echo INV_POPUP_WINDOW_TITLE; ?></h1>
<div id="filter_bar" class="d-flex align-items-center gap-3 mb-2">
  <span><?php echo TEXT_FILTERS; ?></span>
  <span class="d-flex align-items-center gap-1"><?php echo TEXT_SHOW_INACTIVE; ?> <?php echo html_checkbox_field('f0', '1', $f0); ?></span>
  <span class="d-flex align-items-center gap-1"><?php echo INV_ENTRY_INVENTORY_TYPE; ?> <?php echo html_pull_down_menu('f1', $type_select_list, $f1, ''); ?></span>
  <?php if ($account_type == 'v' && $contactID): ?>
  <span class="d-flex align-items-center gap-1"><?php echo INV_HEADING_PREFERRED_VENDOR; ?> <?php echo html_checkbox_field('f2', '1', $f2); ?></span>
  <?php endif; ?>
  <?php echo html_button_field('apply', TEXT_APPLY, 'onclick="submitToDo();"'); ?>
</div>
<table class="pkg-table table table-borderless table-hover" style="width:100%;">
  <thead class="table-dark">
    <tr><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
  <?php
  $odd = true;
  while (!$query_result->EOF) {
    $display_stock = true;
    if ($query_result->fields['inventory_type'] == 'pb') {
      $vr = $db->Execute(
        "SELECT MIN(FLOOR(i.quantity_on_hand / a.qty)) AS v
         FROM " . TABLE_INVENTORY_ASSY_LIST . " a
         INNER JOIN " . TABLE_INVENTORY . " i ON a.sku = i.sku
         WHERE a.ref_id = '" . $query_result->fields['id'] . "' AND a.qty > 0"
      );
      $query_result->fields['quantity_on_hand'] = (!$vr->EOF && $vr->fields['v'] !== null) ? max(0, (int)$vr->fields['v']) : 0;
      $store_stock = $query_result->fields['quantity_on_hand'];
    } elseif (strpos(COG_ITEM_TYPES, $query_result->fields['inventory_type']) === false) {
      $display_stock = false;
      $return_stock  = TEXT_NA;
    } elseif (ENABLE_MULTI_BRANCH) {
      $store_stock = load_store_stock($query_result->fields['sku'], $store_id);
    }
    switch ($account_type) {
      default:
      case 'c': $price = inv_calculate_sales_price(1, $query_result->fields['id'], 0, 'c'); break;
      case 'v': $price = inv_calculate_sales_price(1, $query_result->fields['id'], 0, 'v'); break;
    }
    $bkgnd = ($query_result->fields['inactive'] == 1) ? ' class="table-warning"' : '';
  ?>
  <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer" onclick="setReturnItem(<?php echo $query_result->fields['id']; ?>)">
    <td<?php echo $bkgnd; ?>><?php echo $query_result->fields['sku']; ?></td>
    <td><?php echo $query_result->fields['description_short']; ?></td>
    <td class="text-end"><?php echo $currencies->precise($price['price']); ?></td>
    <td class="text-center"><?php echo $display_stock ? $query_result->fields['quantity_on_hand'] : '&nbsp;'; ?></td>
    <td class="text-center"><?php echo $display_stock ? $query_result->fields['quantity_on_order'] : '&nbsp;'; ?></td>
    <?php if (ENABLE_MULTI_BRANCH) echo '<td class="text-center">' . ($display_stock ? $store_stock : '&nbsp;') . '</td>' . chr(10); ?>
  </tr>
  <?php
    $query_result->MoveNext();
    $odd = !$odd;
  }
  ?>
  </tbody>
</table>
<div class="d-flex justify-content-between align-items-center mt-1">
  <span><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_ITEMS); ?></span>
  <span><?php echo $query_split->display_links(); ?></span>
</div>
</form>
