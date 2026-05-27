<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/shipping/methods/dhl_express/label_mgr/template_main.php
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
$toolbar->add_help('09.01.02');
echo $toolbar->build_toolbar();
?>
<h1><?php echo MODULE_SHIPPING_DHL_EXPRESS_TEXT_TITLE; ?></h1>
<table>
<tr><td width="50%" valign="top">
<div>
  <fieldset>
  <legend><?php echo SHIPPING_RECP_INFO; ?></legend>
  <table>
    <tr>
      <td><?php echo GEN_PRIMARY_NAME; ?></td>
      <td><?php echo html_input_field('ship_primary_name', $sInfo->ship_primary_name, 'size="33" maxlength="60"', true); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_CONTACT; ?></td>
      <td><?php echo html_input_field('ship_contact', $sInfo->ship_contact ?? '', 'size="33" maxlength="60"'); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_ADDRESS1; ?></td>
      <td><?php echo html_input_field('ship_address1', $sInfo->ship_address1, 'size="33" maxlength="50"', true); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_ADDRESS2; ?></td>
      <td><?php echo html_input_field('ship_address2', $sInfo->ship_address2 ?? '', 'size="33" maxlength="50"'); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_CITY_TOWN; ?></td>
      <td><?php echo html_input_field('ship_city_town', $sInfo->ship_city_town, 'size="25" maxlength="50"', true); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_STATE_PROVINCE; ?></td>
      <td><?php echo html_input_field('ship_state_province', $sInfo->ship_state_province ?? '', 'size="15" maxlength="30"'); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_POSTAL_CODE; ?></td>
      <td><?php echo html_input_field('ship_postal_code', $sInfo->ship_postal_code, 'size="12" maxlength="15"', true); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_COUNTRY; ?></td>
      <td><?php echo html_pull_down_menu('ship_country_code', gen_get_countries(), $sInfo->ship_country_code, 'id="ship_country_code" onchange="toggleCustoms()"'); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_TELEPHONE1; ?></td>
      <td><?php echo html_input_field('ship_telephone1', $sInfo->ship_telephone1 ?? '', 'size="17" maxlength="20"'); ?></td>
    </tr>
    <tr>
      <td><?php echo GEN_EMAIL; ?></td>
      <td><?php echo html_input_field('ship_email', $sInfo->ship_email ?? '', 'size="33" maxlength="60"'); ?></td>
    </tr>
  </table>
  </fieldset>
</div>
<div id="customs_section">
  <fieldset>
  <legend><?php echo DHL_CUSTOMS_SECTION; ?></legend>
  <table>
    <tr>
      <td><?php echo DHL_TEXT_DESCRIPTION; ?></td>
      <td><?php echo html_input_field('customs_description', $sInfo->customs_description ?? '', 'size="33" maxlength="80"'); ?></td>
    </tr>
    <tr>
      <td><?php echo DHL_TEXT_DECLARED_VALUE; ?></td>
      <td><?php echo html_input_field('customs_value', $sInfo->customs_value ?? $sInfo->total_value ?? '0.00', 'size="10" maxlength="10" style="text-align:right"'); ?></td>
    </tr>
    <tr>
      <td><?php echo DHL_TEXT_HS_CODE; ?></td>
      <td><?php echo html_input_field('hs_code', $sInfo->hs_code ?? '', 'size="15" maxlength="15"'); ?></td>
    </tr>
    <tr>
      <td><?php echo DHL_TEXT_ORIGIN_COUNTRY; ?></td>
      <td><?php echo html_pull_down_menu('country_of_origin', gen_get_countries(), $sInfo->country_of_origin ?? (defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : '')); ?></td>
    </tr>
    <tr>
      <td><?php echo DHL_TEXT_EXPORT_REASON; ?></td>
      <td><?php
        $reasons = array(
          array('id' => 'permanent',   'text' => defined('TEXT_SALE')   ? TEXT_SALE   : 'Sale / Permanent Export'),
          array('id' => 'temporary',   'text' => 'Temporary Export'),
          array('id' => 'return',      'text' => defined('TEXT_RETURN') ? TEXT_RETURN : 'Return'),
          array('id' => 'used_exhibition_goods_to_origin', 'text' => 'Exhibition'),
          array('id' => 'personal_effects', 'text' => 'Personal Effects'),
          array('id' => 'commercial_purpose_or_sale', 'text' => 'Commercial Sale'),
        );
        echo html_pull_down_menu('customs_reason', $reasons, $sInfo->customs_reason ?? 'permanent');
      ?></td>
    </tr>
  </table>
  </fieldset>
</div>
</td>
<td width="50%" valign="top">
<div>
  <fieldset>
  <legend><?php echo SHIPPNIG_SUMMARY; ?></legend>
  <table>
    <tr>
      <td><?php echo SHIPPING_TOTAL_WEIGHT; ?></td>
      <td><?php echo html_input_field('total_weight', $sInfo->total_weight, 'readonly="readonly" size="6" maxlength="5" style="text-align:right"'); ?></td>
    </tr>
    <tr>
      <td><?php echo SHIPPING_TOTAL_VALUE; ?></td>
      <td><?php echo html_input_field('total_value', $sInfo->total_value, 'readonly="readonly" size="8" maxlength="7" style="text-align:right"'); ?></td>
    </tr>
  </table>
  </fieldset>
</div>
<div>
  <fieldset>
  <legend><?php echo SHIPPING_SHIPMENT_DETAILS; ?></legend>
  <table>
    <tr>
      <td><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></td>
      <td><?php echo html_calendar_field($cal_ship); ?></td>
    </tr>
    <tr>
      <td><?php echo SHIPPING_SERVICE_TYPE; ?></td>
      <td><?php echo html_pull_down_menu('ship_method', gen_build_pull_down($shipping_methods), $sInfo->ship_method); ?></td>
    </tr>
    <tr>
      <td><?php echo TEXT_INVOICE; ?></td>
      <td><?php echo html_input_field('purchase_invoice_id', $sInfo->purchase_invoice_id); ?></td>
    </tr>
    <tr>
      <td><?php echo TEXT_PO_NUMBER; ?></td>
      <td><?php echo html_input_field('purch_order_id', $sInfo->purch_order_id); ?></td>
    </tr>
  </table>
  </fieldset>
</div>
</td></tr>
</table>
<table class="ui-widget" style="border-collapse:collapse;margin-left:auto;margin-right:auto">
  <tr><th><?php echo SHIPPING_PACKAGE_DETAILS; ?></th></tr>
  <tr>
    <td id="productList">
      <table class="ui-widget" style="border-collapse:collapse;margin-left:auto;margin-right:auto">
        <thead class="ui-widget-header">
          <tr>
            <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
            <th><?php echo TEXT_QUANTITY; ?></th>
            <th><?php echo TEXT_WEIGHT;
              echo html_pull_down_menu('pkg_weight_unit', gen_build_pull_down($shipping_defaults['weight_unit']), $sInfo->pkg_weight_unit); ?>
            </th>
            <th colspan="3"><?php echo SHIPPING_TEXT_DIMENSIONS;
              echo html_pull_down_menu('pkg_dimension_unit', gen_build_pull_down($shipping_defaults['dimension_unit']), $sInfo->pkg_dimension_unit); ?>
            </th>
            <th><?php echo TEXT_VALUE; ?></th>
          </tr>
        </thead>
        <tbody id="item_table" class="ui-widget-content">
        <?php
          if (isset($sInfo->package)) {
            $rowCnt = 1;
            foreach ($sInfo->package as $package) { ?>
              <tr>
                <td align="center"><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'style="cursor:pointer" onclick="if (confirm(\'' . SHIPPING_DELETE_CONFIRM . '\')) removeRow(' . $rowCnt . ');"'); ?></td>
                <td align="center"><?php echo html_input_field('qty_' . $rowCnt, '1', 'size="5" maxlength="4" style="text-align:right"'); ?></td>
                <td align="center"><?php echo html_input_field('wt_'  . $rowCnt, $package['weight'], 'size="6" maxlength="6" style="text-align:right" onchange="updateWeight(' . $rowCnt . ')"'); ?></td>
                <td align="center"><?php echo html_input_field('len_' . $rowCnt, $package['length'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
                <td align="center"><?php echo html_input_field('wid_' . $rowCnt, $package['width'],  'size="5" maxlength="4" style="text-align:right"'); ?></td>
                <td align="center"><?php echo html_input_field('hgt_' . $rowCnt, $package['height'], 'size="5" maxlength="4" style="text-align:right"'); ?></td>
                <td align="center"><?php echo html_input_field('ins_' . $rowCnt, $package['value'],  'size="8" maxlength="7" style="text-align:right"'); ?></td>
              </tr>
          <?php $rowCnt++;
            }
          } else {
            echo '<script type="text/javascript">addRow();</script>';
          } ?>
        </tbody>
      </table>
    </td>
  </tr>
  <tr>
    <td align="left"><?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addRow()"'); ?></td>
  </tr>
</table>
</form>
