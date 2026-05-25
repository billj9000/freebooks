<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |

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
//  Path: /modules/shipping/pages/popup_shipping/pre_process.php
//
define('DEFAULT_MOD_DIR', DIR_FS_MODULES . 'shipping/methods/');
$security_level = validate_user(0, true);
/**************  include page specific files  *********************/
gen_pull_language('contacts');
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/shipping.php');
require_once(DIR_FS_WORKING . 'classes/shipping.php');
/**************   page specific initialization  *************************/
$error       = false;
$pkg         = new shipment();
// Pre-populate form fields from GET params on initial load.
// Back button sends both ship_to_* (TO) and ship_city_town etc. (FROM) explicitly.
// Estimate button hx-include sends only order field names (ship_city_town etc.) which map to TO only.
if (empty($_REQUEST['action'])) {
  $is_back = array_key_exists('ship_to_city', $_GET) || array_key_exists('ship_to_country_code', $_GET);
  if ($is_back) {
    // Restore FROM address (user may have edited it before clicking Continue)
    foreach (['ship_city_town', 'ship_state_province', 'ship_postal_code', 'ship_country_code'] as $f) {
      if (isset($_GET[$f]) && $_GET[$f] !== '') $pkg->$f = db_prepare_input($_GET[$f]);
    }
    // Restore TO address using the ship_to_* names sent by the Back button
    foreach (['ship_to_city', 'ship_to_state', 'ship_to_postal_code', 'ship_to_country_code'] as $f) {
      if (isset($_GET[$f]) && $_GET[$f] !== '') $pkg->$f = db_prepare_input($_GET[$f]);
    }
  } else {
    // Initial open from Estimate button (hx-include): order delivery fields map to TO address only
    if (isset($_GET['ship_city_town'])      && $_GET['ship_city_town']      !== '') $pkg->ship_to_city         = db_prepare_input($_GET['ship_city_town']);
    if (isset($_GET['ship_state_province']) && $_GET['ship_state_province'] !== '') $pkg->ship_to_state        = db_prepare_input($_GET['ship_state_province']);
    if (isset($_GET['ship_postal_code'])    && $_GET['ship_postal_code']    !== '') $pkg->ship_to_postal_code  = db_prepare_input($_GET['ship_postal_code']);
    if (isset($_GET['ship_country_code'])   && $_GET['ship_country_code']   !== '') $pkg->ship_to_country_code = db_prepare_input($_GET['ship_country_code']);
  }
  // Package fields apply in both cases
  foreach (['pkg_weight', 'pkg_weight_unit', 'pkg_length', 'pkg_width', 'pkg_height', 'pkg_dimension_unit'] as $f) {
    if (isset($_GET[$f]) && $_GET[$f] !== '') $pkg->$f = db_prepare_input($_GET[$f]);
  }
  if (isset($_GET['ship_date'])         && $_GET['ship_date']         !== '') $pkg->ship_date         = db_prepare_input($_GET['ship_date']);
  if (isset($_GET['residential_address']))                                     $pkg->residential_address = $_GET['residential_address'] ? '1' : '0';
}
$methods     = array();
$files       = scandir(DEFAULT_MOD_DIR);
foreach ($files as $choice) {
  if (defined('MODULE_SHIPPING_' . strtoupper($choice) . '_STATUS')) {
    load_method_language(DEFAULT_MOD_DIR, $choice);
    $methods[] = $choice;
  }
}
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/popup_shipping/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'rates':
	// overwrite the defaults with data from the form
	foreach ($_POST as $key => $value) $pkg->$key = db_prepare_input($value);
	// generate ISO2 codes for countries (needed by FedEx and others)
	$pkg->ship_from_country_iso2 = gen_get_country_iso_2_from_3($pkg->ship_country_code);
	$pkg->ship_to_country_iso2   = gen_get_country_iso_2_from_3($pkg->ship_to_country_code);
	// read checkboxes
	$pkg->residential_address    = isset($_POST['residential_address']) ? '1' : '0';
	$pkg->additional_handling    = isset($_POST['additional_handling']) ? '1' : '0';
	$pkg->insurance              = isset($_POST['insurance']) ? '1' : '0';
	$pkg->split_large_shipments  = isset($_POST['split_large_shipments']) ? '1' : '0';
	$pkg->delivery_confirmation  = isset($_POST['delivery_confirmation']) ? '1' : '0';
	$pkg->handling_charge        = isset($_POST['handling_charge']) ? '1' : '0';
	$pkg->cod                    = isset($_POST['cod']) ? '1' : '0';
	$pkg->saturday_pickup        = isset($_POST['saturday_pickup']) ? '1' : '0';
	$pkg->saturday_delivery      = isset($_POST['saturday_delivery']) ? '1' : '0';
	$pkg->hazardous_material     = isset($_POST['hazardous_material']) ? '1' : '0';
	// read the modules installed
	$rates = array();
	foreach ($methods as $method) {
	  if (isset($_POST['ship_method_' . $method])) {
		require_once(DEFAULT_MOD_DIR . $method . '/' . $method . '.php');
		$subject = new $method;
		$result = $subject->quote($pkg); // will return false if there was an error
		if (is_array($result)) {
		  $rates = array_merge_recursive($result, $rates);
		} else {
		  $error = true;
		}
	  }
	}
	if ($error) $_REQUEST['action'] = ''; // reload selection form
	break;
  default:
}
/*****************   prepare to display templates  *************************/
$cal_ship = array(
  'name'      => 'dateShipped',
  'form'      => 'step1',
  'fieldname' => 'ship_date',
  'imagename' => 'btn_date_1',
  'default'   => date(DATE_FORMAT),
  'params'    => array('align' => 'left'),
);
// Return a bare fragment (no page wrapper) when called by htmx
$custom_html    = !empty($_SERVER['HTTP_HX_REQUEST']);
$include_header = false;
$include_footer = false;
switch ($_REQUEST['action']) {
  case 'rates':
    $include_template = 'template_detail.php';
    define('PAGE_TITLE', SHIPPING_POPUP_WINDOW_RATE_TITLE);
	break;
  default:
    $include_template = 'template_main.php';
    define('PAGE_TITLE', SHIPPING_ESTIMATOR_OPTIONS);
}

?>