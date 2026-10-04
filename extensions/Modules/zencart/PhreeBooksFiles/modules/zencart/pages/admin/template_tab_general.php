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
//  Path: /modules/zencart/pages/admin/template_tab_general.php
//
?>
<style>
.zc-section th   { text-align:left; padding:5px 10px; font-size:1em; letter-spacing:0.03em; }
.zc-row          { border-bottom:1px solid #ddd; }
.zc-row.shaded   { background-color:#f5f7fa; }
.zc-label        { width:40%; padding:8px 12px; vertical-align:top; }
.zc-label strong { display:block; margin-bottom:3px; font-size:1em; }
.zc-label .hint  { font-size:0.88em; color:#555; line-height:1.4em; }
.zc-field        { width:60%; padding:8px 12px; vertical-align:middle; }
</style>
<div class="tab-pane show active" id="tab_general">
  <div class="card fb-card mt-2">
    <div class="card-header py-1 fb-widget-header"><?php echo MODULE_ZENCART_CONFIG_INFO; ?></div>
    <div class="card-body p-0">
      <table class="zc-section" style="border-style:none;width:100%">
        <tbody>

          <tr class="table-secondary"><th colspan="2">Connection</th></tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Enable ZenCart integration</strong>
              <span class="hint"><?php echo ZENCART_LBL_ENABLED; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_enabled', $sel_yes_no, isset($_POST['zencart_enabled']) ? $_POST['zencart_enabled'] : (defined('ZENCART_ENABLED') ? ZENCART_ENABLED : '0'), 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Store URL</strong>
              <span class="hint"><?php echo ZENCART_ADMIN_URL; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_url', $_POST['zencart_url'] ? $_POST['zencart_url'] : ZENCART_URL, 'class="form-control-sm" size="40"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>API key</strong>
              <span class="hint"><?php echo ZENCART_ADMIN_API_KEY; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_api_key', $_POST['zencart_api_key'] ? $_POST['zencart_api_key'] : ZENCART_API_KEY, 'class="form-control-sm" size="40"'); ?></td>
          </tr>

          <tr class="table-secondary"><th colspan="2">Catalog &amp; Products</th></tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Tax class</strong>
              <span class="hint"><?php echo ZENCART_TAX_CLASS; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_product_tax_class', $_POST['zencart_product_tax_class'] ? $_POST['zencart_product_tax_class'] : ZENCART_PRODUCT_TAX_CLASS, 'class="form-control-sm" size="20"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Use price sheets</strong>
              <span class="hint"><?php echo ZENCART_USE_PRICES; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_use_price_sheets', $sel_yes_no, $_POST['zencart_use_price_sheets'] ? $_POST['zencart_use_price_sheets'] : ZENCART_USE_PRICE_SHEETS, 'class="form-select form-select-sm w-auto" onclick="togglePriceSheets()"'); ?></td>
          </tr>

          <tr class="zc-row shaded" id="price_sheet_row">
            <td class="zc-label">
              <strong>Price sheet</strong>
              <span class="hint"><?php echo ZENCART_TEXT_PRICE_SHEET; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_price_sheet', pull_down_price_sheet_list(), $_POST['zencart_price_sheet'] ? $_POST['zencart_price_sheet'] : ZENCART_PRICE_SHEET, 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Non-shipping SKUs</strong>
              <span class="hint"><?php echo ZENCART_LBL_NON_SHIP_SKUS; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_non_ship_skus', isset($_POST['zencart_non_ship_skus']) ? $_POST['zencart_non_ship_skus'] : (defined('ZENCART_NON_SHIP_SKUS') ? ZENCART_NON_SHIP_SKUS : ''), 'class="form-control-sm" size="40"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Show list-page upload icon</strong>
              <span class="hint"><?php echo ZENCART_LBL_LIST_ICON_ENABLED; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_list_icon_enabled', $sel_yes_no, isset($_POST['zencart_list_icon_enabled']) ? $_POST['zencart_list_icon_enabled'] : (defined('ZENCART_LIST_ICON_ENABLED') ? ZENCART_LIST_ICON_ENABLED : '0'), 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="table-secondary"><th colspan="2">Order Import</th></tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Catch-all customer account</strong>
              <span class="hint"><?php echo ZENCART_LBL_CATCH_ALL_ACCT; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_customer_account_id', $_POST['zencart_customer_account_id'] ? $_POST['zencart_customer_account_id'] : ZENCART_CUSTOMER_ACCOUNT_ID, 'class="form-control-sm" size="10"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Default tax rate</strong>
              <span class="hint"><?php echo ZENCART_LBL_DEFAULT_TAX; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_default_tax_rate_id', $_POST['zencart_default_tax_rate_id'] ? $_POST['zencart_default_tax_rate_id'] : ZENCART_DEFAULT_TAX_RATE_ID, 'class="form-control-sm" size="10"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Create Sales Invoice</strong>
              <span class="hint"><?php echo ZENCART_LBL_CREATE_INVOICE; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_create_invoice', $sel_yes_no, $_POST['zencart_create_invoice'] ? $_POST['zencart_create_invoice'] : ZENCART_CREATE_INVOICE, 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Default sales rep</strong>
              <span class="hint"><?php echo ZENCART_LBL_DEFAULT_REP; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_default_rep_id', gen_get_rep_ids('c'), isset($_POST['zencart_default_rep_id']) ? $_POST['zencart_default_rep_id'] : (defined('ZENCART_DEFAULT_REP_ID') ? ZENCART_DEFAULT_REP_ID : '0'), 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Last imported order ID</strong>
              <span class="hint"><?php echo ZENCART_LBL_LAST_ORDER; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_last_order', $_POST['zencart_last_order'] ? $_POST['zencart_last_order'] : (defined('ZENCART_LAST_ORDER') ? ZENCART_LAST_ORDER : '0'), 'class="form-control-sm" size="10"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Store time zone</strong>
              <span class="hint"><?php echo ZENCART_LBL_STORE_TIMEZONE; ?></span>
            </td>
            <td class="zc-field"><?php
              $zones = array(array('id' => '', 'text' => ZENCART_TEXT_SAME_AS_COMPANY . ' (' . str_replace('_', ' ', date_default_timezone_get()) . ')'));
              foreach (timezone_identifiers_list() as $zone) $zones[] = array('id' => $zone, 'text' => str_replace('_', ' ', $zone));
              echo html_pull_down_menu('zencart_store_timezone', $zones, $_POST['zencart_store_timezone'] ?? (defined('ZENCART_STORE_TIMEZONE') ? ZENCART_STORE_TIMEZONE : ''), 'class="form-select form-select-sm"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Payment module map</strong>
              <span class="hint"><?php echo ZENCART_LBL_PAYMENT_MAP; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_payment_map', isset($_POST['zencart_payment_map']) ? $_POST['zencart_payment_map'] : (defined('ZENCART_PAYMENT_MAP') ? ZENCART_PAYMENT_MAP : ''), 'class="form-control form-control-sm"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Shipping method map</strong>
              <span class="hint"><?php echo ZENCART_LBL_SHIPPING_MAP; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_shipping_map', isset($_POST['zencart_shipping_map']) ? $_POST['zencart_shipping_map'] : (defined('ZENCART_SHIPPING_MAP') ? ZENCART_SHIPPING_MAP : ''), 'class="form-control form-control-sm"'); ?></td>
          </tr>

          <tr class="table-secondary"><th colspan="2">Shipment Notifications</th></tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Shipped status code</strong>
              <span class="hint"><?php echo ZENCART_SHIP_ID; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_status_confirm_id', $_POST['zencart_status_confirm_id'] ? $_POST['zencart_status_confirm_id'] : ZENCART_STATUS_CONFIRM_ID, 'class="form-control-sm" size="10"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Partially shipped status code</strong>
              <span class="hint"><?php echo ZENCART_PARTIAL_ID; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_status_partial_id', $_POST['zencart_status_partial_id'] ? $_POST['zencart_status_partial_id'] : ZENCART_STATUS_PARTIAL_ID, 'class="form-control-sm" size="10"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Send shipping notifications</strong>
              <span class="hint"><?php echo ZENCART_LBL_NOTIFY_SHIPMENTS; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_notify_shipments', $sel_yes_no, $_POST['zencart_notify_shipments'] ? $_POST['zencart_notify_shipments'] : (defined('ZENCART_NOTIFY_SHIPMENTS') ? ZENCART_NOTIFY_SHIPMENTS : '0'), 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Notification message</strong>
              <span class="hint"><?php echo ZENCART_LBL_NOTIFY_MESSAGE; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_notify_message', isset($_POST['zencart_notify_message']) ? $_POST['zencart_notify_message'] : (defined('ZENCART_NOTIFY_MESSAGE') ? ZENCART_NOTIFY_MESSAGE : ZENCART_CONFIRM_MESSAGE), 'class="form-control-sm" size="60"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>"Updated By" name</strong>
              <span class="hint"><?php echo ZENCART_LBL_UPDATED_BY; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_updated_by', isset($_POST['zencart_updated_by']) ? $_POST['zencart_updated_by'] : (defined('ZENCART_UPDATED_BY') ? ZENCART_UPDATED_BY : 'FreeBooks'), 'class="form-control-sm" size="20"'); ?></td>
          </tr>

          <tr class="table-secondary"><th colspan="2">VAT Invoice Email</th></tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Email VAT invoices</strong>
              <span class="hint"><?php echo ZENCART_LBL_VAT_INVOICE; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_vat_invoice', $sel_yes_no, isset($_POST['zencart_vat_invoice']) ? $_POST['zencart_vat_invoice'] : (defined('ZENCART_VAT_INVOICE') ? ZENCART_VAT_INVOICE : '0'), 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Invoice form</strong>
              <span class="hint"><?php echo ZENCART_LBL_VAT_INVOICE_FORM; ?></span>
            </td>
            <td class="zc-field"><?php echo html_pull_down_menu('zencart_vat_invoice_form', zencart_pull_invoice_forms(), isset($_POST['zencart_vat_invoice_form']) ? $_POST['zencart_vat_invoice_form'] : (defined('ZENCART_VAT_INVOICE_FORM') ? ZENCART_VAT_INVOICE_FORM : ''), 'class="form-select form-select-sm w-auto"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>Email subject</strong>
              <span class="hint"><?php echo ZENCART_LBL_VAT_INVOICE_SUBJECT; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_vat_invoice_subject', isset($_POST['zencart_vat_invoice_subject']) ? $_POST['zencart_vat_invoice_subject'] : (defined('ZENCART_VAT_INVOICE_SUBJECT') ? ZENCART_VAT_INVOICE_SUBJECT : ''), 'class="form-control-sm" size="40"'); ?></td>
          </tr>

          <tr class="zc-row shaded">
            <td class="zc-label">
              <strong>Email message</strong>
              <span class="hint"><?php echo ZENCART_LBL_VAT_INVOICE_MESSAGE; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_vat_invoice_message', isset($_POST['zencart_vat_invoice_message']) ? $_POST['zencart_vat_invoice_message'] : (defined('ZENCART_VAT_INVOICE_MESSAGE') ? ZENCART_VAT_INVOICE_MESSAGE : ''), 'class="form-control-sm" size="60"'); ?></td>
          </tr>

          <tr class="zc-row">
            <td class="zc-label">
              <strong>BCC address</strong>
              <span class="hint"><?php echo ZENCART_LBL_VAT_INVOICE_BCC; ?></span>
            </td>
            <td class="zc-field"><?php echo html_input_field('zencart_vat_invoice_bcc', isset($_POST['zencart_vat_invoice_bcc']) ? $_POST['zencart_vat_invoice_bcc'] : (defined('ZENCART_VAT_INVOICE_BCC') ? ZENCART_VAT_INVOICE_BCC : ''), 'class="form-control-sm" size="30"'); ?></td>
          </tr>

        </tbody>
      </table>
    </div>
  </div>
</div>
