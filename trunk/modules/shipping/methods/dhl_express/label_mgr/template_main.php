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
  <table>
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

<?php if (!empty($sInfo->line_items)): ?>
<div id="line_items_section">
  <table class="ui-widget" style="border-collapse:collapse;margin-left:auto;margin-right:auto;margin-top:10px;width:98%">
    <tr><th class="ui-widget-header" colspan="7"><?php echo DHL_LINE_ITEMS_HEADER; ?></th></tr>
    <tr class="ui-widget-header">
      <th><?php echo TEXT_SKU; ?></th>
      <th><?php echo DHL_TEXT_DESCRIPTION; ?></th>
      <th><?php echo DHL_TEXT_ORIGIN_COUNTRY; ?></th>
      <th><?php echo DHL_TEXT_HS_CODE; ?></th>
      <th><?php echo TEXT_QUANTITY; ?></th>
      <th><?php echo DHL_TEXT_UNIT_WEIGHT; ?> (<?php echo htmlspecialchars($sInfo->pkg_weight_unit ?? SHIPPING_DEFAULT_WEIGHT); ?>)</th>
      <th><?php echo DHL_TEXT_UNIT_VALUE; ?></th>
    </tr>
    <input type="hidden" name="num_line_items" value="<?php echo count($sInfo->line_items); ?>">
    <?php foreach ($sInfo->line_items as $li => $item): $n = $li + 1; ?>
    <tr class="ui-widget-content">
      <td style="padding:2px 4px"><?php echo htmlspecialchars($item['sku']); ?>
        <input type="hidden" name="li_sku_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['sku']); ?>">
        <input type="hidden" name="li_qty_<?php echo $n; ?>" id="li_qty_<?php echo $n; ?>" value="<?php echo (float)$item['qty']; ?>">
      </td>
      <td><input type="text" name="li_desc_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['description']); ?>" size="30" maxlength="80"></td>
      <td><input type="text" name="li_origin_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['origin']); ?>" size="4" maxlength="2" placeholder="XX" style="text-align:center;text-transform:uppercase"></td>
      <td><input type="text" name="li_hs_<?php echo $n; ?>" value="<?php echo htmlspecialchars($item['hs_code']); ?>" size="12" maxlength="20"></td>
      <td style="text-align:right"><?php echo number_format((float)$item['qty'], 0); ?></td>
      <td><input type="text" name="li_wt_<?php echo $n; ?>" id="li_wt_<?php echo $n; ?>" value="<?php echo number_format((float)$item['weight'], 3); ?>" size="8" maxlength="10" style="text-align:right" onchange="updateWeight()"></td>
      <td><input type="text" name="li_val_<?php echo $n; ?>" id="li_val_<?php echo $n; ?>" value="<?php echo number_format((float)$item['value'], 2); ?>" size="8" maxlength="10" style="text-align:right" onchange="updateWeight()"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div style="text-align:right;margin-right:1%;margin-top:4px">
    <?php echo html_icon('actions/document-save.png', DHL_TEXT_SAVE_TO_INV, 'small',
      'style="cursor:pointer;vertical-align:middle" onclick="submitToDo(\'save_items\')"'); ?>
    &nbsp;<a href="#" onclick="submitToDo('save_items'); return false;"><?php echo DHL_TEXT_SAVE_TO_INV; ?></a>
  </div>
</div>
<?php endif; ?>
</form>
