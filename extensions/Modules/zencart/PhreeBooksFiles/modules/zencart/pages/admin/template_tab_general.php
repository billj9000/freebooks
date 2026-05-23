<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft, LLC (www.PhreeSoft.com)       |
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
<div id="tab_general">
<fieldset>
  <legend></legend>
<table class="ui-widget" style="border-style:none;width:100%">
 <thead class="ui-widget-header">
  <tr><th colspan="2"><?php echo MODULE_ZENCART_CONFIG_INFO; ?></th></tr>
 </thead>
 <tbody class="ui-widget-content">
	  
	  <tr>
	    <td colspan="4"><?php echo ZENCART_ADMIN_URL; ?></td>
	    <td><?php echo html_input_field('zencart_url', $_POST['zencart_url'] ? $_POST['zencart_url'] : ZENCART_URL, 'size="64"'); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_ADMIN_API_KEY; ?></td>
	    <td><?php echo html_input_field('zencart_api_key', $_POST['zencart_api_key'] ? $_POST['zencart_api_key'] : ZENCART_API_KEY, 'size="64"'); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_TAX_CLASS; ?></td>
	    <td><?php echo html_input_field('zencart_product_tax_class', $_POST['zencart_product_tax_class'] ? $_POST['zencart_product_tax_class'] : ZENCART_PRODUCT_TAX_CLASS, ''); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_USE_PRICES; ?></td>
	    <td><?php echo html_pull_down_menu('zencart_use_price_sheets', $sel_yes_no, $_POST['zencart_use_price_sheets'] ? $_POST['zencart_use_price_sheets'] : ZENCART_USE_PRICE_SHEETS, 'onclick="togglePriceSheets()"'); ?></td>
	  </tr>
  	  <tr id="price_sheet_row">
	    <td colspan="4"><?php echo ZENCART_TEXT_PRICE_SHEET; ?></td>
        <td><?php echo html_pull_down_menu('zencart_price_sheet', pull_down_price_sheet_list(), $_POST['zencart_price_sheet'] ? $_POST['zencart_price_sheet'] : ZENCART_PRICE_SHEET, ''); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_SHIP_ID; ?></td>
	    <td><?php echo html_input_field('zencart_status_confirm_id', $_POST['zencart_status_confirm_id'] ? $_POST['zencart_status_confirm_id'] : ZENCART_STATUS_CONFIRM_ID, ''); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_PARTIAL_ID; ?></td>
	    <td><?php echo html_input_field('zencart_status_partial_id', $_POST['zencart_status_partial_id'] ? $_POST['zencart_status_partial_id'] : ZENCART_STATUS_PARTIAL_ID, ''); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_LBL_CATCH_ALL_ACCT; ?></td>
	    <td><?php echo html_input_field('zencart_customer_account_id', $_POST['zencart_customer_account_id'] ? $_POST['zencart_customer_account_id'] : ZENCART_CUSTOMER_ACCOUNT_ID, 'size="10"'); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_LBL_DEFAULT_TAX; ?></td>
	    <td><?php echo html_input_field('zencart_default_tax_rate_id', $_POST['zencart_default_tax_rate_id'] ? $_POST['zencart_default_tax_rate_id'] : ZENCART_DEFAULT_TAX_RATE_ID, 'size="10"'); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_LBL_CREATE_INVOICE; ?></td>
	    <td><?php echo html_pull_down_menu('zencart_create_invoice', $sel_yes_no, $_POST['zencart_create_invoice'] ? $_POST['zencart_create_invoice'] : ZENCART_CREATE_INVOICE, ''); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_LBL_DEFAULT_REP; ?></td>
	    <td><?php echo html_pull_down_menu('zencart_default_rep_id', gen_get_rep_ids('c'), isset($_POST['zencart_default_rep_id']) ? $_POST['zencart_default_rep_id'] : (defined('ZENCART_DEFAULT_REP_ID') ? ZENCART_DEFAULT_REP_ID : '0'), ''); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_LBL_LAST_ORDER; ?></td>
	    <td><?php echo html_input_field('zencart_last_order', $_POST['zencart_last_order'] ? $_POST['zencart_last_order'] : (defined('ZENCART_LAST_ORDER') ? ZENCART_LAST_ORDER : '0'), 'size="10"'); ?></td>
	  </tr>
	  <tr>
	    <td colspan="4"><?php echo ZENCART_LBL_PAYMENT_MAP; ?></td>
	    <td><?php echo html_input_field('zencart_payment_map', isset($_POST['zencart_payment_map']) ? $_POST['zencart_payment_map'] : (defined('ZENCART_PAYMENT_MAP') ? ZENCART_PAYMENT_MAP : ''), 'size="64"'); ?></td>
	  </tr>
	</tbody>
</table>
</fieldset>
</div>
