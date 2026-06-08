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
//  Path: /modules/shipping/pages/popup_tracking/template_main.php
//
echo html_form('popup_tracking', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = !empty($sID);
$delete_confirm = !empty($has_carrier_cancel) ? SHIPPING_CANCEL_CONFIRM : SHIPPING_DELETE_CONFIRM;
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . addslashes($delete_confirm) . '\')) submitToDo(\'delete\')"';
if (!empty($label_viewer_url)) {
  $toolbar->icon_list['print']['show']   = true;
  $toolbar->icon_list['print']['params'] = 'onclick="window.open(\'' . $label_viewer_url . '\', \'label_reprint\', \'width=800,height=600\')"';
  $toolbar->icon_list['print']['text']   = defined('SHIPPING_TEXT_PRINT_LABEL') ? SHIPPING_TEXT_PRINT_LABEL : 'Reprint Label';
} else {
  $toolbar->icon_list['print']['show']   = false;
}
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-3">
<div class="d-inline-flex flex-column">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SHIPMENT_DETAILS; ?></div>
  <div class="card-body py-2">
    <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_SHIPMENT_ID . SHIPPING_SET_BY_SYSTEM; ?></label>
      <?php echo html_input_field('shipment_id',  $cInfo->shipment_id,                  'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></label>
      <?php echo html_input_field('ref_id',        $cInfo->ref_id,                       'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_CARRIER; ?></label>
      <?php echo html_pull_down_menu('carrier',    $methods,         $cInfo->carrier,    'class="form-select form-select-sm" disabled="disabled"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_SERVICE; ?></label>
      <?php echo html_pull_down_menu('method',     $carrier_methods, $cInfo->method,     'class="form-select form-select-sm" disabled="disabled"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></label>
      <?php echo html_input_field('tracking_id',   $cInfo->tracking_id,                  'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
      <?php echo html_input_field('ship_date',     gen_locale_date($cInfo->ship_date),   'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_EXPECTED_DATE; ?></label>
      <?php echo html_input_field('deliver_date',  gen_locale_date($cInfo->deliver_date),'class="form-control form-control-sm" readonly="readonly"'); ?>
      <label class="text-end small text-nowrap"><?php echo SHIPPING_TEXT_COST; ?></label>
      <?php echo html_input_field('cost',          $cInfo->cost,                         'class="form-control form-control-sm" readonly="readonly"'); ?>
    </div>
  </div>
</div>
</div>
</div>
</form>
