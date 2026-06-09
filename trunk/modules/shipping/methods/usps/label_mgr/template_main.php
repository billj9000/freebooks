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
//  Path: /modules/shipping/methods/usps/label_mgr/template_main.php
//
echo html_form('label_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . SHIPPING_DELETE_CONFIRM . '\')) submitToDo(\'delete\')"';
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('09.01');
echo $toolbar->build_toolbar();
$title = defined('MODULE_SHIPPING_' . strtoupper($shipping_module) . '_TEXT_TITLE')
       ? constant('MODULE_SHIPPING_' . strtoupper($shipping_module) . '_TEXT_TITLE')
       : SHIPPING_POPUP_WINDOW_TITLE;
?>
<h1><?php echo $title; ?></h1>
<div class="d-flex justify-content-center">
  <div class="d-inline-flex flex-column">
    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SHIPMENT_DETAILS; ?></div>
      <div class="card-body py-2">
        <div style="display:grid;grid-template-columns:max-content 1fr;gap:4px 8px;align-items:center">
          <label><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></label>
          <div><?php echo html_input_field('purchase_invoice_id', $sInfo->purchase_invoice_id, 'class="form-control form-control-sm"'); ?></div>
          <label><?php echo SHIPPING_SERVICE_TYPE; ?></label>
          <div><?php echo html_pull_down_menu('ship_method', gen_build_pull_down($shipping_methods), $sInfo->ship_method, 'class="form-select form-select-sm"'); ?></div>
          <label><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
          <div><?php echo html_calendar_field($cal_ship); ?></div>
          <label><?php echo SHIPPING_TEXT_EXPECTED_DATE; ?></label>
          <div><?php echo html_calendar_field($cal_exp); ?></div>
          <label><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></label>
          <div><?php echo html_input_field('tracking_id', $sInfo->tracking_id, 'class="form-control form-control-sm" size="25" maxlength="24"'); ?></div>
          <label><?php echo SHIPPING_TEXT_COST; ?></label>
          <div><?php echo html_input_field('cost', $currencies->format($sInfo->cost), 'class="form-control form-control-sm" style="text-align:right" size="15" maxlength="14"'); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
