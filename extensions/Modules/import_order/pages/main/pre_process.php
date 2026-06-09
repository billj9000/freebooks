<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
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
//  Path: /modules/import_order/pages/main/pre_process.php
//
$security_level = validate_user(SECURITY_ID_IMPORT_ORDER);
/**************  include page specific files    *********************/
gen_pull_language('contacts');
gen_pull_language('phreebooks');
$_soap_lang = DIR_FS_ADMIN . 'soap/language/' . $_SESSION['language'] . '/language.php';
if (!file_exists($_soap_lang)) $_soap_lang = DIR_FS_ADMIN . 'soap/language/en_us/language.php';
if (file_exists($_soap_lang)) include_once($_soap_lang);
require_once (DIR_FS_WORKING.'classes/import_order.php');
require_once (DIR_FS_ADMIN  .'soap/classes/parser.php');
require_once (DIR_FS_ADMIN  .'soap/classes/orders.php');
require_once (DIR_FS_MODULES.'phreebooks/functions/phreebooks.php');
require_once (DIR_FS_MODULES.'phreebooks/classes/gen_ledger.php');
require_once (DIR_FS_MODULES.'phreebooks/classes/orders.php');
/**************   page specific initialization  *************************/
$upload_name = 'file_name';
// set some defaults
define('DEF_INV_GL_ACCT', AR_DEF_GL_SALES_ACCT);
define('DEF_GL_ACCT',     AR_DEFAULT_GL_ACCT);
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'download_template':
	$headers = 'Reference,StoreID,SalesGLAccount,ReceivablesGLAccount,OrderID,PurchaseOrderID,OrderDate,'
	         . 'OrderTotal,TaxTotal,ShippingTotal,ShippingCarrier,ShippingMethod,SalesRepID,'
	         . 'Payment->CardHolderName,Payment->Method,Payment->CardType,Payment->CardNumber,Payment->ExpirationDate,Payment->CVV2Number,'
	         . '$entry->CompanyName,$entry->Contact,$entry->Address1,$entry->Address2,$entry->CityTown,'
	         . '$entry->StateProvince,$entry->PostalCode,$entry->CountryCode,$entry->Telephone,$entry->Email,$entry->CustomerID';
	$example = 'REF001,1,,,' . date('d-m-Y') . ',100.00,0,0,,,,,,,,,,,,,,,,,,,,';
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename=import_order_template.csv');
	header('Pragma: no-cache');
	echo $headers . "\n" . $example . "\n";
	exit();
  case 'save':
	if ($security_level < 1) {
	  $messageStack->add(ERROR_NO_PERMISSION, 'error');
	  gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	}
	// first verify the file was uploaded ok
	if (!validate_upload($upload_name, 'text', 'csv')) {
	  $messageStack->add(TEXT_IMP_ERMSG10,'error');
	} else {
	  $post_pay = new import_order();
	  $post_pay->processCSV($upload_name);
    }
	break;
  default:
}
/*****************   prepare to display templates  *************************/
$use_bootstrap_nav = true;
$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', IMPORT_ORDER_PAGE_TITLE);

?>