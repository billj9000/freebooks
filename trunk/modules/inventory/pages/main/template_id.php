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
//  Path: /modules/inventory/pages/main/template_id.php
//

echo html_form('inventory', FILENAME_DEFAULT, gen_get_all_get_params(array('action')));
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action','page')) . '&amp;page=main', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('continue', 'onclick="submitToDo(\'create\')"', $order = 10);
$toolbar->add_help('07.04.01.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo INV_HEADING_NEW_ITEM; ?></h1>

<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header">
    <?php echo sprintf(INV_ENTER_SKU, MAX_INVENTORY_SKU_LENGTH, MAX_INVENTORY_SKU_LENGTH - 5); ?>
  </div>
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="row g-2 align-items-center mb-2">
      <div class="col-5 text-end"><?php echo TEXT_SKU; ?></div>
      <div class="col-7">
        <?php echo html_input_field('sku', $sku, 'class="form-control form-control-sm" size="' . (MAX_INVENTORY_SKU_LENGTH + 2) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '"'); ?>
      </div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-5 text-end"><?php echo INV_ENTRY_INVENTORY_TYPE; ?></div>
      <div class="col-7">
        <?php echo html_pull_down_menu('inventory_type', gen_build_pull_down($inventory_types), isset($inventory_type) ? $inventory_type : 'si', 'class="form-select form-select-sm" onchange="setSkuLength()"'); ?>
      </div>
    </div>
  </div>
</div>
</form>
