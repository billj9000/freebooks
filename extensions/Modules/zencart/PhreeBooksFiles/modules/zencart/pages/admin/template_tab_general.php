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
<div class="tab-pane show active" id="tab_general">
  <div class="card fb-card mt-2">
    <div class="card-header py-1 fb-widget-header"><?php echo MODULE_ZENCART_CONFIG_INFO; ?></div>
    <div class="card-body py-2">
      <div style="display:grid;grid-template-columns:1fr 280px;gap:4px 8px;align-items:center;font-weight:normal">
        <div><?php echo ZENCART_LBL_ENABLED; ?></div>
        <div><?php echo html_pull_down_menu('zencart_enabled', $sel_yes_no, isset($_POST['zencart_enabled']) ? $_POST['zencart_enabled'] : (defined('ZENCART_ENABLED') ? ZENCART_ENABLED : '0'), 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_ADMIN_URL; ?></div>
        <div><?php echo html_input_field('zencart_url', $_POST['zencart_url'] ? $_POST['zencart_url'] : ZENCART_URL, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_ADMIN_API_KEY; ?></div>
        <div><?php echo html_input_field('zencart_api_key', $_POST['zencart_api_key'] ? $_POST['zencart_api_key'] : ZENCART_API_KEY, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_TAX_CLASS; ?></div>
        <div><?php echo html_input_field('zencart_product_tax_class', $_POST['zencart_product_tax_class'] ? $_POST['zencart_product_tax_class'] : ZENCART_PRODUCT_TAX_CLASS, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_USE_PRICES; ?></div>
        <div><?php echo html_pull_down_menu('zencart_use_price_sheets', $sel_yes_no, $_POST['zencart_use_price_sheets'] ? $_POST['zencart_use_price_sheets'] : ZENCART_USE_PRICE_SHEETS, 'class="form-select form-select-sm w-auto" onclick="togglePriceSheets()"'); ?></div>
        <div id="price_sheet_label"><?php echo ZENCART_TEXT_PRICE_SHEET; ?></div>
        <div id="price_sheet_row"><?php echo html_pull_down_menu('zencart_price_sheet', pull_down_price_sheet_list(), $_POST['zencart_price_sheet'] ? $_POST['zencart_price_sheet'] : ZENCART_PRICE_SHEET, 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_SHIP_ID; ?></div>
        <div><?php echo html_input_field('zencart_status_confirm_id', $_POST['zencart_status_confirm_id'] ? $_POST['zencart_status_confirm_id'] : ZENCART_STATUS_CONFIRM_ID, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_PARTIAL_ID; ?></div>
        <div><?php echo html_input_field('zencart_status_partial_id', $_POST['zencart_status_partial_id'] ? $_POST['zencart_status_partial_id'] : ZENCART_STATUS_PARTIAL_ID, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_NOTIFY_SHIPMENTS; ?></div>
        <div><?php echo html_pull_down_menu('zencart_notify_shipments', $sel_yes_no, $_POST['zencart_notify_shipments'] ? $_POST['zencart_notify_shipments'] : (defined('ZENCART_NOTIFY_SHIPMENTS') ? ZENCART_NOTIFY_SHIPMENTS : '0'), 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_LBL_NOTIFY_MESSAGE; ?></div>
        <div><?php echo html_input_field('zencart_notify_message', isset($_POST['zencart_notify_message']) ? $_POST['zencart_notify_message'] : (defined('ZENCART_NOTIFY_MESSAGE') ? ZENCART_NOTIFY_MESSAGE : ZENCART_CONFIRM_MESSAGE), 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_UPDATED_BY; ?></div>
        <div><?php echo html_input_field('zencart_updated_by', isset($_POST['zencart_updated_by']) ? $_POST['zencart_updated_by'] : (defined('ZENCART_UPDATED_BY') ? ZENCART_UPDATED_BY : 'FreeBooks'), 'class="form-control form-control-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_LBL_CATCH_ALL_ACCT; ?></div>
        <div><?php echo html_input_field('zencart_customer_account_id', $_POST['zencart_customer_account_id'] ? $_POST['zencart_customer_account_id'] : ZENCART_CUSTOMER_ACCOUNT_ID, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_DEFAULT_TAX; ?></div>
        <div><?php echo html_input_field('zencart_default_tax_rate_id', $_POST['zencart_default_tax_rate_id'] ? $_POST['zencart_default_tax_rate_id'] : ZENCART_DEFAULT_TAX_RATE_ID, 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_CREATE_INVOICE; ?></div>
        <div><?php echo html_pull_down_menu('zencart_create_invoice', $sel_yes_no, $_POST['zencart_create_invoice'] ? $_POST['zencart_create_invoice'] : ZENCART_CREATE_INVOICE, 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_LBL_DEFAULT_REP; ?></div>
        <div><?php echo html_pull_down_menu('zencart_default_rep_id', gen_get_rep_ids('c'), isset($_POST['zencart_default_rep_id']) ? $_POST['zencart_default_rep_id'] : (defined('ZENCART_DEFAULT_REP_ID') ? ZENCART_DEFAULT_REP_ID : '0'), 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_LBL_LAST_ORDER; ?></div>
        <div><?php echo html_input_field('zencart_last_order', $_POST['zencart_last_order'] ? $_POST['zencart_last_order'] : (defined('ZENCART_LAST_ORDER') ? ZENCART_LAST_ORDER : '0'), 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_PAYMENT_MAP; ?></div>
        <div><?php echo html_input_field('zencart_payment_map', isset($_POST['zencart_payment_map']) ? $_POST['zencart_payment_map'] : (defined('ZENCART_PAYMENT_MAP') ? ZENCART_PAYMENT_MAP : ''), 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_SHIPPING_MAP; ?></div>
        <div><?php echo html_input_field('zencart_shipping_map', isset($_POST['zencart_shipping_map']) ? $_POST['zencart_shipping_map'] : (defined('ZENCART_SHIPPING_MAP') ? ZENCART_SHIPPING_MAP : ''), 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_NON_SHIP_SKUS; ?></div>
        <div><?php echo html_input_field('zencart_non_ship_skus', isset($_POST['zencart_non_ship_skus']) ? $_POST['zencart_non_ship_skus'] : (defined('ZENCART_NON_SHIP_SKUS') ? ZENCART_NON_SHIP_SKUS : ''), 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_VAT_INVOICE; ?></div>
        <div><?php echo html_pull_down_menu('zencart_vat_invoice', $sel_yes_no, isset($_POST['zencart_vat_invoice']) ? $_POST['zencart_vat_invoice'] : (defined('ZENCART_VAT_INVOICE') ? ZENCART_VAT_INVOICE : '0'), 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_LBL_VAT_INVOICE_FORM; ?></div>
        <div><?php echo html_pull_down_menu('zencart_vat_invoice_form', zencart_pull_invoice_forms(), isset($_POST['zencart_vat_invoice_form']) ? $_POST['zencart_vat_invoice_form'] : (defined('ZENCART_VAT_INVOICE_FORM') ? ZENCART_VAT_INVOICE_FORM : ''), 'class="form-select form-select-sm w-auto"'); ?></div>
        <div><?php echo ZENCART_LBL_VAT_INVOICE_SUBJECT; ?></div>
        <div><?php echo html_input_field('zencart_vat_invoice_subject', isset($_POST['zencart_vat_invoice_subject']) ? $_POST['zencart_vat_invoice_subject'] : (defined('ZENCART_VAT_INVOICE_SUBJECT') ? ZENCART_VAT_INVOICE_SUBJECT : ''), 'class="form-control form-control-sm"'); ?></div>
        <div><?php echo ZENCART_LBL_VAT_INVOICE_MESSAGE; ?></div>
        <div><?php echo html_input_field('zencart_vat_invoice_message', isset($_POST['zencart_vat_invoice_message']) ? $_POST['zencart_vat_invoice_message'] : (defined('ZENCART_VAT_INVOICE_MESSAGE') ? ZENCART_VAT_INVOICE_MESSAGE : ''), 'class="form-control form-control-sm"'); ?></div>
      </div>
    </div>
  </div>
</div>
