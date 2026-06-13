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
//  Path: /modules/shipping/methods/endicia/label_mgr/template_main.php
//
echo html_form('label_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['params']  = 'onclick="submitToDo(\'label\')"';
$toolbar->icon_list['print']['text']    = SHIPPING_TEXT_PRINT_LABEL;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('09.01');
echo $toolbar->build_toolbar();
?>
<h1 class="px-2 mb-2"><?php echo PAGE_TITLE; ?></h1>
<?php if ($auto_print) { ?>
  <div class="px-2">
    <script src="<?php echo DIR_WS_ADMIN; ?>modules/phreedom/includes/qz-tray/qz-tray.js"></script>
    <button type="button" class="btn btn-primary btn-sm" onclick="labelPrint()"><?php echo TEXT_PRINT; ?></button>
  </div>
<?php } else { ?>
<div class="px-2">

  <!-- Package details -->
  <div class="card fb-card mb-2">
    <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_PACKAGE_DETAILS; ?></div>
    <div class="card-body p-0">
      <table id="productList" class="pkg-table table table-borderless table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th class="text-center"><?php echo TEXT_QUANTITY; ?></th>
            <th class="text-center"><?php echo TEXT_WEIGHT . ' ';
              echo html_pull_down_menu('pkg_weight_unit', gen_build_pull_down($shipping_defaults['weight_unit']), $sInfo->pkg_weight_unit); ?>
            </th>
            <th class="text-center" colspan="3"><?php echo SHIPPING_TEXT_DIMENSIONS . ' ';
              echo html_pull_down_menu('pkg_dimension_unit', gen_build_pull_down($shipping_defaults['dimension_unit']), $sInfo->pkg_dimension_unit); ?>
            </th>
            <th class="text-center"><?php echo TEXT_VALUE . ' ';
              echo html_pull_down_menu('insurance_currency', gen_get_pull_down(TABLE_CURRENCIES, false, false, 'code', 'title'), $sInfo->insurance_currency); ?>
            </th>
          </tr>
        </thead>
        <tbody id="item_table">
          <tr>
            <td class="text-center"><?php echo html_input_field('qty_1', '1', 'readonly="readonly" size="6" maxlength="5" style="text-align:right"'); ?></td>
            <td class="text-center"><?php echo html_input_field('wt_1',  $package['weight'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
            <td class="text-center"><?php echo html_input_field('len_1', $package['length'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
            <td class="text-center"><?php echo html_input_field('wid_1', $package['width'],  'size="5" maxlength="4" style="text-align:right"'); ?></td>
            <td class="text-center"><?php echo html_input_field('hgt_1', $package['height'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
            <td class="text-center"><?php echo html_input_field('ins_1', $package['value'],  'size="8" maxlength="7" style="text-align:right"'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recipient + Shipment details -->
  <div class="row g-2">
    <div class="col-md-6">
      <div class="card fb-card h-100">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_RECP_INFO; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo GEN_PRIMARY_NAME; ?></label>
            <?php echo html_input_field('ship_primary_name', $sInfo->ship_primary_name, 'class="form-control form-control-sm" maxlength="32"', true); ?>
            <label class="text-end small"><?php echo GEN_CONTACT; ?></label>
            <?php echo html_input_field('ship_contact', $sInfo->ship_contact, 'class="form-control form-control-sm" maxlength="32"'); ?>
            <label class="text-end small"><?php echo GEN_ADDRESS1; ?></label>
            <?php echo html_input_field('ship_address1', $sInfo->ship_address1, 'class="form-control form-control-sm" maxlength="32"', true); ?>
            <label class="text-end small"><?php echo GEN_ADDRESS2; ?></label>
            <?php echo html_input_field('ship_address2', $sInfo->ship_address2, 'class="form-control form-control-sm" maxlength="32"'); ?>
            <label class="text-end small"><?php echo GEN_CITY_TOWN; ?></label>
            <?php echo html_input_field('ship_city_town', $sInfo->ship_city_town, 'class="form-control form-control-sm" maxlength="24"', true); ?>
            <label class="text-end small"><?php echo GEN_STATE_PROVINCE; ?></label>
            <?php echo html_input_field('ship_state_province', $sInfo->ship_state_province, 'class="form-control form-control-sm" maxlength="2"', true); ?>
            <label class="text-end small"><?php echo GEN_POSTAL_CODE; ?></label>
            <?php echo html_input_field('ship_postal_code', $sInfo->ship_postal_code, 'class="form-control form-control-sm" maxlength="10"', true); ?>
            <label class="text-end small"><?php echo GEN_COUNTRY; ?></label>
            <?php echo html_pull_down_menu('ship_country_code', gen_get_countries(), $sInfo->ship_country_code, 'class="form-select form-select-sm"') . chr(10); ?>
            <label class="text-end small"><?php echo GEN_TELEPHONE1; ?></label>
            <?php echo html_input_field('ship_telephone1', $sInfo->ship_telephone1, 'class="form-control form-control-sm" maxlength="16"'); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card fb-card h-100">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SHIPMENT_DETAILS; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
            <div><?php echo html_calendar_field($cal_ship); ?></div>
            <label class="text-end small"><?php echo SHIPPING_SERVICE_TYPE; ?></label>
            <?php echo html_pull_down_menu('ship_method', gen_build_pull_down($shipping_methods), $sInfo->ship_method, 'class="form-select form-select-sm"'); ?>
            <label class="text-end small"><?php echo TEXT_INVOICE; ?></label>
            <?php echo html_input_field('purchase_invoice_id', $sInfo->purchase_invoice_id, 'class="form-control form-control-sm"'); ?>
            <label class="text-end small"><?php echo SHIPPING_TEXT_PACKAGE_TYPE; ?></label>
            <?php echo html_pull_down_menu('pkg_type', gen_build_pull_down($shipping_defaults['package_type']), $sInfo->pkg_type ? $sInfo->pkg_type : 'FlatRateEnvelope', 'class="form-select form-select-sm"'); ?>
            <label class="text-end small"><?php echo SHIPPING_TEXT_DELIVERY_CONFIRM; ?></label>
            <div><?php echo html_checkbox_field('delivery_confirmation', '1', $sInfo->delivery_confirmation ? $sInfo->delivery_confirmation : true); ?></div>
            <label class="text-end small"><?php echo SHIPPING_COD_AMOUNT; ?>&nbsp;<?php echo html_checkbox_field('cod', '1', $sInfo->cod); ?></label>
            <?php echo html_input_field('total_amount', $sInfo->total_amount, 'class="form-control form-control-sm" style="text-align:right"'); ?>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
<?php } // end if ($auto_print) else ?>
</form>
