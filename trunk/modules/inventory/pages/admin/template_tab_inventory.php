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
//  Path: /modules/inventory/pages/admin/template_tab_inventory.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'inventory' ? ' show active' : ''); ?>" id="tab_inventory" role="tabpanel">
  <div class="card mt-2 mb-2" style="border-color:#a6c9e2">
    <div class="card-body p-2">
      <div class="text-center fw-bold py-1" style="font-size:11px"><?php echo TEXT_DEFAULT_GL_ACCOUNTS; ?></div>
      <table class="table table-borderless table-hover table-sm mb-0" style="font-size:11px">
        <thead>
          <tr>
            <th><?php echo TEXT_INVENTORY_TYPES; ?></th>
            <th><?php echo TEXT_SALES_ACCOUNT; ?></th>
            <th><?php echo TEXT_INVENTORY_ACCOUNT; ?></th>
            <th><?php echo TEXT_COGS_ACCOUNT; ?></th>
            <th><?php echo TEXT_COST_METHOD; ?></th>
          </tr>
        </thead>
        <tbody>
<?php
$gl_rows = array(
  array('label' => TEXT_STOCK_ITEMS,  'prefix' => 'inv_stock',         'has_cogs' => true,  'has_cost' => true),
  array('label' => TEXT_MS_ITEMS,     'prefix' => 'inv_master_stock',   'has_cogs' => true,  'has_cost' => true),
  array('label' => TEXT_ASSY_ITEMS,   'prefix' => 'inv_assy',           'has_cogs' => true,  'has_cost' => true),
  array('label' => TEXT_SERIAL_ITEMS, 'prefix' => 'inv_serialize',      'has_cogs' => true,  'has_cost' => false),
  array('label' => TEXT_NS_ITEMS,     'prefix' => 'inv_non_stock',      'has_cogs' => true,  'has_cost' => false),
  array('label' => TEXT_SRV_ITEMS,    'prefix' => 'inv_service',        'has_cogs' => true,  'has_cost' => false),
  array('label' => TEXT_LABOR_ITEMS,  'prefix' => 'inv_labor',          'has_cogs' => true,  'has_cost' => false),
  array('label' => TEXT_ACT_ITEMS,    'prefix' => 'inv_activity',       'has_cogs' => false, 'has_cost' => false),
  array('label' => TEXT_CHARGE_ITEMS, 'prefix' => 'inv_charge',         'has_cogs' => false, 'has_cost' => false),
  array('label' => TEXT_DESC_ITEMS,   'prefix' => 'inv_desc',           'has_cogs' => false, 'has_cost' => false),
);
$odd = true;
foreach ($gl_rows as $row):
  $p = $row['prefix'];
  $const_sales = strtoupper($p) . '_DEFAULT_SALES';
  $const_inv   = strtoupper($p) . '_DEFAULT_INVENTORY';
  $const_cogs  = strtoupper($p) . '_DEFAULT_COS';
  $const_cost  = strtoupper($p) . '_DEFAULT_COSTING';
?>
          <tr class="<?php echo $odd ? 'odd' : 'even'; ?>">
            <td><?php echo $row['label']; ?></td>
            <td><?php echo html_combo_box($p.'_default_sales',      $inc_chart, $_POST[$p.'_default_sales']      ?: (defined($const_sales) ? constant($const_sales) : ''), ''); ?></td>
            <td><?php echo html_combo_box($p.'_default_inventory',  $inv_chart, $_POST[$p.'_default_inventory']  ?: (defined($const_inv)   ? constant($const_inv)   : ''), ''); ?></td>
            <td><?php echo $row['has_cogs'] ? html_combo_box($p.'_default_cos', $cog_chart, $_POST[$p.'_default_cos'] ?: (defined($const_cogs) ? constant($const_cogs) : ''), '') : '&nbsp;'; ?></td>
            <td><?php echo $row['has_cost'] ? html_pull_down_menu($p.'_default_costing', $cost_methods, $_POST[$p.'_default_costing'] ?: (defined($const_cost) ? constant($const_cost) : ''), '') : '&nbsp;'; ?></td>
          </tr>
<?php $odd = !$odd; endforeach; ?>
        </tbody>
      </table>
      <div class="text-center fw-bold py-1 mt-2" style="font-size:11px"><?php echo TEXT_PREFERENCES; ?></div>
      <div class="py-2 px-3" style="font-size:11px">
        <div style="display:table; width:100%">
<?php
$prefs = array(
  array('label' => CD_05_50_DESC, 'field' => html_pull_down_menu('inventory_default_tax',        $sel_sales_tax,  $_POST['inventory_default_tax']        ?: INVENTORY_DEFAULT_TAX,        '')),
  array('label' => CD_05_52_DESC, 'field' => html_pull_down_menu('inventory_default_purch_tax',   $sel_purch_tax,  $_POST['inventory_default_purch_tax']   ?: INVENTORY_DEFAULT_PURCH_TAX,  '')),
  array('label' => CD_05_55_DESC, 'field' => html_pull_down_menu('inventory_auto_add',            $sel_yes_no,     $_POST['inventory_auto_add']            ?: INVENTORY_AUTO_ADD,           '')),
  array('label' => CD_05_60_DESC, 'field' => html_pull_down_menu('inventory_auto_fill',           $sel_yes_no,     $_POST['inventory_auto_fill']           ?: INVENTORY_AUTO_FILL,          '')),
  array('label' => CD_05_65_DESC, 'field' => html_pull_down_menu('ord_enable_line_item_bar_code', $sel_yes_no,     $_POST['ord_enable_line_item_bar_code'] ?: ORD_ENABLE_LINE_ITEM_BAR_CODE, '')),
  array('label' => CD_05_70_DESC, 'field' => html_input_field(   'ord_bar_code_length',                           $_POST['ord_bar_code_length']           ?: ORD_BAR_CODE_LENGTH,           '')),
  array('label' => CD_05_75_DESC, 'field' => html_pull_down_menu('enable_auto_item_cost',         $sel_item_cost,  $_POST['enable_auto_item_cost']         ?: ENABLE_AUTO_ITEM_COST,        '')),
);
foreach ($prefs as $pref):
?>
          <div style="display:table-row">
            <div style="display:table-cell; padding-right:16px; padding-bottom:6px; width:100%; vertical-align:middle"><?php echo $pref['label']; ?></div>
            <div style="display:table-cell; padding-bottom:6px; vertical-align:middle; white-space:nowrap"><?php echo $pref['field']; ?></div>
          </div>
<?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
