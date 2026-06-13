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
//  Path: /modules/phreepos/pages/main/template_main.php
//

echo html_form('pos', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
$hidden_fields = NULL;

echo html_hidden_field('id',                  $order->id) . chr(10);
echo html_hidden_field('bill_acct_id',        $order->bill_acct_id) . chr(10);
echo html_hidden_field('bill_address_id',     $order->bill_address_id) . chr(10);
echo html_hidden_field('currencies_code',     $order->currencies_code) . chr(10);
echo html_hidden_field('printed',             $order->printed) . chr(10);
echo html_hidden_field('purchase_invoice_id', $order->purchase_invoice_id) . chr(10);
echo html_hidden_field('post_date',           $order->post_date) . chr(10);
echo html_hidden_field('gl_acct_id',          $order->gl_acct_id) . chr(10);
echo html_hidden_field('store_id',            $order->store_id) . chr(10);
if (!ENABLE_MULTI_CURRENCY) echo html_hidden_field('display_currency', DEFAULT_CURRENCY) . chr(10);
if (!ENABLE_MULTI_CURRENCY) echo html_hidden_field('currencies_value', '1') . chr(10);

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'jID')) . '&jID='.JOURNAL_ID, 'SSL') . '\'"', 2);
if ($security_level > 1) {
  $toolbar->add_icon('pos_return', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'jID')) . '&action=pos_return&jID='.JOURNAL_ID, 'SSL') . '\'"', 50);
  $toolbar->icon_list['pos_return']['icon'] = 'actions/document-save-as.png';
  $toolbar->icon_list['pos_return']['text'] = TEXT_RETURN;
}
$toolbar->add_icon('previous_print', 'onclick="GetPrintPreviousReceipt()"', 50);
$toolbar->icon_list['previous_print']['icon'] = 'actions/go-previous.png';
$toolbar->icon_list['previous_print']['text'] = TEXT_PRINT_PREVIOUS;
if (defined('PHREEPOS_ENABLE_DIRECT_PRINTING') && PHREEPOS_ENABLE_DIRECT_PRINTING == true) {
  $toolbar->add_icon('open_drawer', 'onclick="OpenDrawer()"', 50);
  $toolbar->icon_list['open_drawer']['icon'] = 'actions/go-bottom.png';
  $toolbar->icon_list['open_drawer']['text'] = TEXT_OPEN_DRAWER;
}
if (!empty($extra_toolbar_buttons)) {
  foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
}
?>
<div class="d-flex align-items-center fb-toolbar-section" style="border:0">
  <div class="flex-shrink-0"><?php echo $toolbar->build_toolbar(); ?></div>
  <h1 class="mb-0 ms-3 flex-grow-1" style="font-size:2.5em"><?php echo PAGE_TITLE; ?></h1>
  <h3 id='open_other_options' class="btn btn-primary ms-2 flex-shrink-0" style="font-size:1.6em; padding:6px 18px; white-space:nowrap"><?php echo OTHER_OPTIONS; ?></h3>
</div>
<div id='other_options'>
  <?php echo html_button_field('home', TEXT_HOME, 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"'); ?><br><br>
  <?php echo html_button_field('open_sales', POS_PRINT_OTHER, 'onclick="OpenOrdrList(this)"'); ?><br><br>
  <?php echo html_button_field('other_trans', TEXT_OTHER_TRANS, 'onclick="ShowOtherTrans()"'); ?><br><br>
  <?php if (in_array($_SESSION['admin_security'][SECURITY_ID_POS_CLOSING], array(1,2,3,4))) echo html_button_field('close_till', POS_HEADING_CLOSING, 'onclick="CloseTill()"') . '<br><br>'; ?>
</div>

<div id="search_part" class="d-flex flex-wrap gap-3 align-items-start mb-2 px-2">
<?php if ($tills->showDropDown()) {
        echo '<span>' . TEXT_TILL . ' ' . html_pull_down_menu('till_id', $tills->till_array(), $order->till_id ? $order->till_id : $tills->default_till(), 'onchange="changeOfTill();"') . '</span>';
      } else {
        echo html_hidden_field('till_id', $tills->default_till());
      } ?>
  <div style="display:grid; grid-template-columns:max-content auto; gap:3px 6px; align-items:center; justify-items:start">
    <span><?php echo TEXT_SALES_REP; ?></span>
    <?php echo html_pull_down_menu('rep_id', gen_get_rep_ids($account_type), $order->rep_id ? $order->rep_id : $default_sales_rep); ?>
<?php if (ENABLE_MULTI_CURRENCY) { ?>
    <span><?php echo TEXT_CURRENCY; ?></span>
    <?php echo html_pull_down_menu('display_currency', gen_get_pull_down(TABLE_CURRENCIES, false, false, 'code', 'title'), $order->currencies_code, 'onchange="recalculateCurrencies();"'); ?>
    <span><?php echo TEXT_EXCHANGE_RATE; ?></span>
    <?php echo html_input_field('currencies_value', $order->currencies_value, 'readonly="readonly"'); ?>
<?php } ?>
    <span><?php echo TEXT_SKU; ?></span>
    <span><?php
      echo html_input_field('sku', '', ' size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" maxlength="' . MAX_INVENTORY_SKU_LENGTH . '" title="' . TEXT_SEARCH . '"  onchange ="loadSkuDetails(0, 0)"') . chr(10);
      echo html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'id="sku_open" align="top" style="cursor:pointer" onclick="InventoryList(0)"') . chr(10);
    ?></span>
  </div>
  <img id='curr_image'>
</div>

<!-- customer_div: hidden popup, shown centred by popupContact() -->
<div id="customer_div" style="display:none; z-index:1001; position:absolute; background:#fff; border:1px solid #a6c9e2; padding:12px">
<?php
  echo ORD_ACCT_ID . ' ' . html_input_field('search', isset($order->short_name) ? $order->short_name : TEXT_SEARCH, 'size="21" maxlength="30" title="' . TEXT_SEARCH . '" onchange="accountGuess(true)"');
  echo '&nbsp;' . html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="accountGuess(true)"');
  echo '<br>' . html_pull_down_menu('bill_to_select', gen_null_pull_down(), '', 'onchange="fillAddress(\'bill\')"');
  echo '<br>' . html_input_field('bill_primary_name', $order->bill_primary_name, 'size="33" maxlength="32" onfocus="clearField(\'bill_primary_name\', \'' . GEN_PRIMARY_NAME . '\')" onblur="setField(\'bill_primary_name\', \'' . GEN_PRIMARY_NAME . '\')"', true) . chr(10);
  echo html_checkbox_field('bill_add_update', '1', ($order->bill_add_update) ? true : false, '', '') . TEXT_ADD_UPDATE;
  echo '<br>' . html_input_field('bill_contact',      $order->bill_contact,      'size="33" maxlength="32" onfocus="clearField(\'bill_contact\', \'' . GEN_CONTACT . '\')" onblur="setField(\'bill_contact\', \'' . GEN_CONTACT . '\')"', ADDRESS_BOOK_CONTACT_REQUIRED);
  echo '<br>' . html_input_field('bill_address1',     $order->bill_address1,     'size="33" maxlength="32" onfocus="clearField(\'bill_address1\', \'' . GEN_ADDRESS1 . '\')" onblur="setField(\'bill_address1\', \'' . GEN_ADDRESS1 . '\')"', ADDRESS_BOOK_ADDRESS1_REQUIRED);
  echo '<br>' . html_input_field('bill_address2',     $order->bill_address2,     'size="33" maxlength="32" onfocus="clearField(\'bill_address2\', \'' . GEN_ADDRESS2 . '\')" onblur="setField(\'bill_address2\', \'' . GEN_ADDRESS2 . '\')"', ADDRESS_BOOK_ADDRESS2_REQUIRED);
  echo '<br>' . html_input_field('bill_city_town',    $order->bill_city_town,    'size="25" maxlength="24" onfocus="clearField(\'bill_city_town\', \'' . GEN_CITY_TOWN . '\')" onblur="setField(\'bill_city_town\', \'' . GEN_CITY_TOWN . '\')"', ADDRESS_BOOK_CITY_TOWN_REQUIRED) . chr(10);
  echo         html_input_field('bill_state_province', $order->bill_state_province, 'size="3" maxlength="24" onfocus="clearField(\'bill_state_province\', \'' . GEN_STATE_PROVINCE . '\')" onblur="setField(\'bill_state_province\', \'' . GEN_STATE_PROVINCE . '\')"', ADDRESS_BOOK_STATE_PROVINCE_REQUIRED) . chr(10);
  echo         html_input_field('bill_postal_code',   $order->bill_postal_code,   'size="11" maxlength="10" onfocus="clearField(\'bill_postal_code\', \'' . GEN_POSTAL_CODE . '\')" onblur="setField(\'bill_postal_code\', \'' . GEN_POSTAL_CODE . '\')"', ADDRESS_BOOK_POSTAL_CODE_REQUIRED);
  echo '<br>' . html_pull_down_menu('bill_country_code', gen_get_countries(), $order->bill_country_code ? $order->bill_country_code : COMPANY_COUNTRY);
  echo '<br>' . html_input_field('bill_telephone1',   $order->bill_telephone1,   'size="21" maxlength="20" onfocus="clearField(\'bill_telephone1\', \'' . GEN_TELEPHONE1 . '\')" onblur="setField(\'bill_telephone1\', \'' . GEN_TELEPHONE1 . '\')"', ADDRESS_BOOK_TELEPHONE1_REQUIRED) . chr(10);
  echo '<br>' . html_input_field('bill_email',        $order->bill_email,        'size="35" maxlength="48" onfocus="clearField(\'bill_email\', \'' . GEN_EMAIL . '\')" onblur="setField(\'bill_email\', \'' . GEN_EMAIL . '\')"', ADDRESS_BOOK_EMAIL_REQUIRED);
?>
</div>

<!-- item table (left) + customer search (right) -->
<div class="d-flex gap-3 align-items-start mb-0" style="min-height:140px">
  <div style="flex:1">
    <table id="item_table" class="table table-sm table-bordered" style="width:auto">
      <thead class="table-dark">
        <tr>
          <th></th>
          <th><?php echo TEXT_QUANTITY; ?></th>
          <th><?php echo TEXT_SKU; ?></th>
          <th><?php echo TEXT_DESCRIPTION; ?></th>
          <th><?php echo TEXT_UNIT_PRICE; ?></th>
          <th><?php echo TEXT_AMOUNT; ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody id="item_table_body">
      </tbody>
      <tfoot class="table-secondary">
        <tr>
          <th></th><th></th><th></th><th></th><th></th><th></th><th></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <div id="search_customer">
  <?php
    echo ORD_ACCT_ID . ' ' . html_input_field('copy_search', isset($order->short_name) ? $order->short_name : TEXT_SEARCH, 'size="21" maxlength="30" title="' . TEXT_SEARCH . '" onchange="accountGuess(true)"');
    echo '&nbsp;' . html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="accountGuess(true)"') . '<br>' . chr(10);
    echo html_input_field('copy_bill_primary_name', $order->bill_primary_name, 'size="33" maxlength="32" onfocus="clearField(\'bill_primary_name\', \'' . GEN_PRIMARY_NAME . '\')" onblur="setField(\'bill_primary_name\', \'' . GEN_PRIMARY_NAME . '\')"', true) . '<br>' . chr(10);
    echo html_button_field('customer_popup_buttom', TEXT_SELECT_CUSTOMER, 'onclick="popupContact()"') . '<br>' . chr(10);
  ?>
  </div>
</div>

<!-- totals: right-aligned, ~3rem above payment table -->
<div class="text-end mb-3">
  <div id="totals_div" class="d-inline-block text-end px-2">
    <?php echo TEXT_SUBTOTAL . ' ' . html_input_field('subtotal', $currencies->format($order->subtotal, true, $order->currencies_code, $order->currencies_value), 'readonly="readonly" size="10" maxlength="20"'); ?>
    <?php if (ENABLE_ORDER_DISCOUNT) {
            $hidden_fields .= html_hidden_field('disc_gl_acct_id', '') . chr(10);
            echo '<br>' . TEXT_DISCOUNT_PERCENT . ' ' . html_input_field('disc_percent', ($order->disc_percent ? number_format(100*$order->disc_percent,3) : $currencies->format(0)), 'size="10" maxlength="6" onchange="calculateDiscountPercent()"');
            echo '<br>' . TEXT_DISCOUNT_AMOUNT  . ' ' . html_input_field('discount', $currencies->format(($order->discount ? $order->discount : '0'), true, $order->currencies_code, $order->currencies_value), 'size="10" maxlength="20" onchange="calculateDiscount()"');
          } else {
            $hidden_fields .= html_hidden_field('disc_gl_acct_id', '') . chr(10);
            $hidden_fields .= html_hidden_field('discount',     '0') . chr(10);
            $hidden_fields .= html_hidden_field('disc_percent', '0') . chr(10);
          } ?>
    <br><?php echo ORD_SALES_TAX . ' ' . html_input_field('sales_tax', $currencies->format(($order->sales_tax ? $order->sales_tax : '0.00'), true, $order->currencies_code, $order->currencies_value), 'readonly="readonly" size="10" maxlength="20" onchange="updateTotalPrices()"'); ?>
    <?php if (!PHREEPOS_ROUNDING == 0) {
            echo '<br>' . TEXT_ROUNDING_OF . ' ' . html_input_field('rounded_of', $currencies->format($order->rounded_of, true, $order->currencies_code, $order->currencies_value), 'readonly="readonly" size="10" maxlength="20"');
          } ?>
    <br><?php echo TEXT_TOTAL . ' ' . html_input_field('total', $currencies->format($order->total_amount, true, $order->currencies_code, $order->currencies_value), 'readonly="readonly" size="10" maxlength="20"'); ?>
  </div>
</div>

<table id="payment_table" class="table table-sm table-bordered" style="width:auto">
  <caption><?php echo html_button_field('payment', 'Pay', 'onclick="popupPayment()"'); ?></caption>
  <thead class="table-dark">
    <tr>
      <th></th>
      <th><?php echo TEXT_PAYMENT_METHOD; ?></th>
      <th><?php echo TEXT_AMOUNT; ?></th>
      <th></th>
    </tr>
  </thead>
  <tbody id="payment_table_body">
  </tbody>
  <tfoot class="table-secondary">
    <tr>
      <th></th>
      <th><?php echo TEXT_AMOUNT_PAID; ?></th>
      <th><?php echo html_input_field('pmt_recvd', $currencies->format($order->pmt_recvd), 'readonly="readonly" size="15" maxlength="20" style="text-align:right"'); ?></th>
      <th></th>
    </tr>
    <tr>
      <th></th>
      <th><?php echo TEXT_BALANCE_DUE; ?></th>
      <th><?php echo html_input_field('bal_due', $currencies->format($order->bal_due), 'readonly="readonly" size="15" maxlength="20" style="text-align:right"'); ?></th>
      <th></th>
    </tr>
  </tfoot>
</table>

<footer><?php echo "<b><u>" . TEXT_NOTES . "</u></b><br>" . PHREEPOS_ITEM_NOTES; ?></footer>
<?php
echo $hidden_fields;
if (defined('PHREEPOS_ENABLE_DIRECT_PRINTING') && PHREEPOS_ENABLE_DIRECT_PRINTING == true) {
?>
<script src="<?php echo DIR_WS_ADMIN; ?>modules/phreedom/includes/qz-tray/qz-tray.js"></script>
<?php } ?>

<div id="popupPayment">
<?php
$SeccondToolbar = new toolbar;
$SeccondToolbar->icon_list['cancel']['params'] = 'onclick="disablePopup()"';
$SeccondToolbar->icon_list['open']['show']     = false;
$SeccondToolbar->icon_list['save']['params']   = 'onclick="SavePayment(\'save\')"';
$SeccondToolbar->icon_list['save']['show']     = true;
$SeccondToolbar->icon_list['delete']['show']   = false;
$SeccondToolbar->icon_list['print']['params']  = 'onclick="SavePayment(\'print\')"';
$SeccondToolbar->icon_list['print']['show']    = true;
if (!empty($extra_SeccondToolbar_buttons)) {
  foreach ($extra_SeccondToolbar_buttons as $key => $value) $SeccondToolbar->icon_list[$key] = $value;
}
echo $SeccondToolbar->build_toolbar();
?>
  <h2 class="text-center"><?php echo PAYMENT_TITLE; ?></h2>
<?php
  echo '<div class="fb-fieldset">';
  echo '<div class="fb-fieldset-legend">' . TEXT_PAYMENT_METHOD . '</div>';
  echo '<div style="position: relative; height: 150px;">';
  echo html_pull_down_menu('payment_method', $payment_modules, $order->shipper_code, 'onchange="activateFields()"') . chr(10);
  $count = 0;
  foreach ($payment_modules as $value) {
    echo '<div id="pm_' . $count . '" style="visibility:hidden; position:absolute; top:22px; left:1px">' . chr(10);
    $pmt_class   = $value['id'];
    $disp_fields = $$pmt_class->selection();
    for ($i = 0; $i < count($disp_fields['fields'] ?? []); $i++) {
      echo $disp_fields['fields'][$i]['title'] . '<br>' . chr(10);
      echo $disp_fields['fields'][$i]['field']  . '<br>' . chr(10);
    }
    echo '</div>' . chr(10);
    $count++;
  }
  echo '</div>';
  echo '</div>';
?>
  <table id="payment_extra_buttons">
    <tr><td><?php echo html_icon('devices/media-floppy.png',     TEXT_SAVE,  'large', 'onclick="SavePayment(\'save\')"',  0, 0, 'btn_save') . '</td><td>' . TEXT_SAVE;  ?></td></tr>
    <tr><td><?php echo html_icon('phreebooks/pdficon_large.gif', TEXT_PRINT, 'large', 'onclick="SavePayment(\'print\')"', 0, 0, 'btn_save') . '</td><td>' . TEXT_PRINT; ?></td></tr>
  </table>
  <?php echo TEXT_AMOUNT . ' ' . html_input_field('amount', $currencies->format($amount), 'size="15" maxlength="20" style="text-align:right; font-size: 1.5em"'); ?>
  <footer><?php echo PHREEPOS_PAYMENT_NOTES; ?></footer>
</div>

<div id="backgroundPopup" style="z-index:1000"></div>
</form>

<?php
echo html_form('popupOtherTrans', FILENAME_DEFAULT) . chr(10);
$thirdToolbar = new toolbar;
$thirdToolbar->icon_list['cancel']['params'] = 'onclick="disablePopup()"';
$thirdToolbar->icon_list['open']['show']     = false;
$thirdToolbar->icon_list['save']['params']   = 'onclick="SaveOt()"';
$thirdToolbar->icon_list['save']['show']     = true;
$thirdToolbar->icon_list['delete']['show']   = false;
$thirdToolbar->icon_list['print']['show']    = false;
if (!empty($extra_ThirdToolbar_buttons)) {
  foreach ($extra_ThirdToolbar_buttons as $key => $value) $thirdToolbar->icon_list[$key] = $value;
}
echo $thirdToolbar->build_toolbar();
?>
  <h2 class="text-center"><?php echo TEXT_OTHER_TRANS; ?></h2>
  <table id="ot_table" class="table table-sm table-bordered" style="width:auto; position:relative;">
    <caption><?php echo TEXT_TYPE_OF_TRANSACTION . '   ' . html_pull_down_menu('Other_trans_type', '', '', 'onchange="changeOfType();"'); ?></caption>
    <thead class="table-dark">
      <tr>
        <th class="ot_desc"><?php echo TEXT_DESCRIPTION; ?></th>
        <th class="ot_amount"><?php echo TEXT_AMOUNT; ?></th>
        <th class="ot_rate"><?php echo ORD_TAX_RATE; ?></th>
        <th class="ot_tax"><?php echo TEXT_TAX; ?></th>
      </tr>
    </thead>
    <tbody id="ot_table_body">
      <tr>
        <td class="ot_desc"><?php echo html_input_field('ot_desc', '', 'size="64" maxlength="64" style="font-size: 1.5em"'); ?></td>
        <td class="ot_amount"><?php echo html_input_field('ot_amount', $currencies->format(0), 'size="15" maxlength="20" style="text-align:right; font-size: 1.5em" onchange="updateOt()"'); ?></td>
        <td class="ot_rate"><?php echo html_pull_down_menu('ot_rate', $ot_tax_rates, '', 'onchange="updateOt()" style="font-size: 1.5em"'); ?></td>
        <td class="ot_tax"><?php echo html_input_field('ot_tax', $currencies->format(0), 'size="15" maxlength="20" style="text-align:right; font-size: 1.5em"'); ?></td>
      </tr>
    </tbody>
  </table>
<?php
echo html_hidden_field('ot_till_id',          $tills->default_till())    . chr(10);
echo html_hidden_field('ot_currencies_value', '1')                       . chr(10);
echo html_hidden_field('ot_currencies_code',  $order->currencies_code)   . chr(10);
?>
</form>
