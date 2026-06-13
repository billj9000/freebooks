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
//  Path: /modules/shipping/methods/fedex_v7/label_mgr/template_main.php
//
echo html_form('label_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
$hidden_fields = NULL;
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . SHIPPING_DELETE_CONFIRM . '\')) submitToDo(\'delete\')"';
$toolbar->icon_list['print']['params']  = 'onclick="submitToDo(\'label\')"';
$toolbar->icon_list['print']['text']    = SHIPPING_TEXT_PRINT_LABEL;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('09.01');
echo $toolbar->build_toolbar();
?>
<h1 class="px-2 mb-2"><?php echo MODULE_SHIPPING_FEDEX_V7_TEXT_TITLE; ?></h1>
<?php if ($auto_print) { ?>
  <div class="px-2">
    <script src="<?php echo DIR_WS_ADMIN; ?>modules/phreedom/includes/qz-tray/qz-tray.js"></script>
    <button type="button" class="btn btn-primary btn-sm" onclick="labelPrint()"><?php echo TEXT_PRINT; ?></button>
  </div>
<?php } else { ?>
<div class="px-2">

  <!-- Recipient / Email / Billing  |  Summary / Shipment / LTL -->
  <div class="row g-2 mb-2">

    <!-- Left column -->
    <div class="col-md-6 d-flex flex-column gap-2">

      <div class="card fb-card">
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
            <?php echo html_input_field('ship_telephone1', $sInfo->ship_telephone1, 'class="form-control form-control-sm" maxlength="16"', true); ?>
          </div>
        </div>
      </div>

      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_EMAIL_NOTIFY; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo SHIPPING_EMAIL_RECIPIENT; ?></label>
            <div class="d-flex gap-2 flex-wrap">
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('email_rcp_ship', '0', $sInfo->email_rcp_ship = true, ''); ?> <?php echo TEXT_SHIP; ?></label>
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('email_rcp_excp', '1', $sInfo->email_rcp_excp, ''); ?> <?php echo SHIPPING_TEXT_EXCEPTION; ?></label>
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('email_rcp_dlvr', '2', $sInfo->email_rcp_dlvr, ''); ?> <?php echo SHIPPING_TEXT_DELIVER; ?></label>
            </div>
            <label class="text-end small"><?php echo SHIPPING_EMAIL_RECIPIENT_ADD; ?></label>
            <?php echo html_input_field('ship_email', $sInfo->ship_email, 'class="form-control form-control-sm" maxlength="32"'); ?>
            <label class="text-end small"><?php echo SHIPPING_EMAIL_SENDER; ?></label>
            <div class="d-flex gap-2 flex-wrap">
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('email_sndr_ship', '0', $sInfo->email_sndr_ship, ''); ?> <?php echo TEXT_SHIP; ?></label>
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('email_sndr_excp', '1', $sInfo->email_sndr_excp = true, ''); ?> <?php echo SHIPPING_TEXT_EXCEPTION; ?></label>
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('email_sndr_dlvr', '2', $sInfo->email_sndr_dlvr, ''); ?> <?php echo SHIPPING_TEXT_DELIVER; ?></label>
            </div>
            <label class="text-end small"><?php echo SHIPPING_EMAIL_SENDER_ADD; ?></label>
            <?php echo html_input_field('sender_email_address', COMPANY_EMAIL, 'class="form-control form-control-sm" maxlength="32"'); ?>
          </div>
        </div>
      </div>

      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_BILL_DETAIL; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo SHIPPING_BILL_CHARGES_TO; ?></label>
            <?php echo html_pull_down_menu('bill_charges', gen_build_pull_down($shipping_defaults['bill_options']), $sInfo->bill_charges, 'class="form-select form-select-sm"'); ?>
            <label class="text-end small"><?php echo SHIPPING_THIRD_PARTY; ?></label>
            <?php echo html_input_field('bill_acct', $sInfo->bill_acct, 'class="form-control form-control-sm"'); ?>
          </div>
        </div>
      </div>

    </div><!-- left col -->

    <!-- Right column -->
    <div class="col-md-6 d-flex flex-column gap-2">

      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPNIG_SUMMARY; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo SHIPPING_TOTAL_WEIGHT; ?></label>
            <?php echo html_input_field('total_weight', $sInfo->total_weight, 'class="form-control form-control-sm" style="text-align:right" readonly="readonly" maxlength="5"'); ?>
            <label class="text-end small"><?php echo SHIPPING_TOTAL_VALUE; ?></label>
            <?php echo html_input_field('total_value',  $sInfo->total_value,  'class="form-control form-control-sm" style="text-align:right" readonly="readonly" maxlength="7"'); ?>
          </div>
        </div>
      </div>

      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_SHIPMENT_DETAILS; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
            <div><?php echo html_calendar_field($cal_ship); ?></div>
            <label class="text-end small"><?php echo SHIPPING_SERVICE_TYPE; ?></label>
            <?php echo html_pull_down_menu('ship_method', gen_build_pull_down($shipping_methods), $sInfo->ship_method, 'class="form-select form-select-sm"'); ?>
            <label class="text-end small"><?php echo TEXT_INVOICE; ?></label>
            <?php echo html_input_field('purchase_invoice_id', $sInfo->purchase_invoice_id, 'class="form-control form-control-sm"'); ?>
            <label class="text-end small"><?php echo TEXT_PO_NUMBER; ?></label>
            <?php echo html_input_field('purch_order_id', $sInfo->purch_order_id, 'class="form-control form-control-sm"'); ?>
            <label class="text-end small"><?php echo SHIPPING_TEXT_PACKAGE_TYPE; ?></label>
            <?php echo html_pull_down_menu('pkg_type', gen_build_pull_down($shipping_defaults['package_type']), $sInfo->pkg_type, 'class="form-select form-select-sm"'); ?>
            <label class="text-end small"><?php echo SHIPPING_TEXT_PICKUP_SERVICE; ?></label>
            <?php echo html_pull_down_menu('pickup_service', gen_build_pull_down($shipping_defaults['pickup_service']), $sInfo->pickup_service, 'class="form-select form-select-sm"'); ?>
            <div style="grid-column:1/-1">
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('residential_address', '1', $sInfo->residential_address); ?> <?php echo SHIPPING_RESIDENTIAL_ADDRESS; ?></label>
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('additional_handling', '1', $sInfo->additional_handling); ?> <?php echo SHIPPING_ADDITIONAL_HANDLING; ?></label>
              <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php echo html_checkbox_field('delivery_confirmation', '1', $sInfo->delivery_confirmation); ?>
                <?php echo SHIPPING_TEXT_DELIVERY_CONFIRM; ?>
                <?php echo html_pull_down_menu('delivery_confirmation_type', gen_build_pull_down($shipping_defaults['delivery_confirmation']), $sInfo->delivery_confirmation_type, 'class="form-select form-select-sm" style="width:auto"'); ?>
              </div>
              <label class="d-flex align-items-center gap-1"><?php echo html_checkbox_field('saturday_delivery', '1', $sInfo->saturday_delivery); ?> <?php echo SHIPPING_SATURDAY_DELIVERY; ?></label>
              <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php echo html_checkbox_field('cod', '1', $sInfo->cod); ?>
                <?php echo SHIPPING_COD_AMOUNT; ?>
                <?php echo html_input_field('total_amount', $sInfo->total_amount, 'class="form-control form-control-sm d-inline-block" style="width:5rem;text-align:right"'); ?>
                <?php echo html_pull_down_menu('cod_currency', gen_get_pull_down(TABLE_CURRENCIES, false, false, 'code', 'title'), $sInfo->cod_currency, 'class="form-select form-select-sm" style="width:auto"'); ?>
                <?php echo html_pull_down_menu('cod_payment_type', gen_build_pull_down($shipping_defaults['cod_funds_code']), $sInfo->cod_payment_type, 'class="form-select form-select-sm" style="width:auto"'); ?>
              </div>
              <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php echo html_checkbox_field('return_service', '1', $sInfo->return_service); ?>
                <?php echo SHIPPING_TEXT_RETURN_SERVICES; ?>
                <?php echo html_pull_down_menu('return_service_value', gen_build_pull_down($shipping_defaults['return_label']), $sInfo->return_service_value, 'class="form-select form-select-sm" style="width:auto"'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_LTL_FREIGHT; ?></div>
        <div class="card-body py-2">
          <div style="display:grid;grid-template-columns:max-content 1fr;align-items:center;gap:0.3rem 0.75rem">
            <label class="text-end small"><?php echo TEXT_DESCRIPTION; ?></label>
            <?php echo html_input_field('ltl_description', $sInfo->ltl_description ? $sInfo->ltl_description : $shipping_defaults['ltl_description'], 'class="form-control form-control-sm" maxlength="32"'); ?>
            <div style="grid-column:1/-1" class="d-flex align-items-center gap-2 flex-wrap">
              <span class="small"><?php echo SHIPPING_NUM_PIECES; ?></span>
              <?php echo html_input_field('ltl_num_pieces', $sInfo->ltl_num_pieces ? $sInfo->ltl_num_pieces : $shipping_defaults['ltl_num_pieces'], 'class="form-control form-control-sm d-inline-block" style="width:4rem" maxlength="3"'); ?>
              <span class="small"><?php echo SHIPPING_LTL_CLASS; ?></span>
              <?php echo html_pull_down_menu('ltl_class', gen_build_pull_down($ltl_classes), $sInfo->ltl_class ? $sInfo->ltl_class : $shipping_defaults['ltl_class'], 'class="form-select form-select-sm" style="width:auto"'); ?>
            </div>
          </div>
        </div>
      </div>

    </div><!-- right col -->
  </div><!-- row -->

  <!-- Package details -->
  <div class="card fb-card mb-2">
    <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_PACKAGE_DETAILS; ?></div>
    <div class="card-body p-0">
      <table id="productList" class="pkg-table table table-borderless table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th class="text-center"><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
            <th class="text-center"><?php echo TEXT_QUANTITY; ?></th>
            <th class="text-center"><?php echo TEXT_WEIGHT . ' ';
              echo html_pull_down_menu('pkg_weight_unit', gen_build_pull_down($shipping_defaults['weight_unit']), $sInfo->pkg_weight_unit); ?>
            </th>
            <th class="text-center" colspan="3"><?php echo SHIPPING_TEXT_DIMENSIONS . ' ';
              echo html_pull_down_menu('pkg_dimension_unit', gen_build_pull_down($shipping_defaults['dimension_unit']), $sInfo->pkg_dimension_unit); ?>
            </th>
            <th class="text-center"><?php echo TEXT_VALUE . ' ';
              echo html_pull_down_menu('insurance_currency', $currency_array, $sInfo->insurance_currency); ?>
            </th>
          </tr>
        </thead>
        <tbody id="item_table">
          <?php if (isset($sInfo->package)) {
            $rowCnt = 1;
            foreach ($sInfo->package as $package) { ?>
            <tr>
              <td class="text-center">
                <?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'style="cursor:pointer" onclick="if (confirm(\'' . SHIPPING_DELETE_CONFIRM . '\')) removeRow(' . $rowCnt . ');"'); ?>
              </td>
              <td class="text-center"><?php echo html_input_field('qty_' . $rowCnt, '1', 'size="6" maxlength="5" style="text-align:right"'); ?></td>
              <td class="text-center"><?php echo html_input_field('wt_'  . $rowCnt, $package['weight'], 'size="5" maxlength="4" style="text-align:right" onchange="updateWeight(' . $rowCnt . ')"'); ?></td>
              <td class="text-center"><?php echo html_input_field('len_' . $rowCnt, $package['length'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
              <td class="text-center"><?php echo html_input_field('wid_' . $rowCnt, $package['width'],  'size="5" maxlength="4" style="text-align:right"'); ?></td>
              <td class="text-center"><?php echo html_input_field('hgt_' . $rowCnt, $package['height'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
              <td class="text-center"><?php echo html_input_field('ins_' . $rowCnt, $package['value'],  'size="8" maxlength="7" style="text-align:right" onchange="updateWeight(' . $rowCnt . ')"'); ?></td>
            </tr>
            <?php $rowCnt++; }
          } else {
            $hidden_fields .= '<script type="text/javascript">addRow();</script>';
          } ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer py-1">
      <?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addRow()"'); ?>
    </div>
  </div>

  <?php echo $hidden_fields; ?>
</div>
<?php } // end if ($auto_print) ?>
</form>
