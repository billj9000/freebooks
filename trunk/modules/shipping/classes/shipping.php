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
//  Path: /modules/shipping/classes/shipping.php
//

#[\AllowDynamicProperties]

class shipment {
	var $additional_handling        = '0';
	var $cod                        = '0';
	var $cod_currency               = 'USD';
	var $cod_payment_type           = '1';
	var $delivery_confirmation      = '0';
	var $delivery_confirmation_type = '2';
	var $dry_ice                    = '0';
	var $handling_charge            = '0';
	var $handling_charge_currency   = 'USD';
	var $handling_charge_value      = '0.00';
	var $hazardous_material         = '0';
	var $insurance                  = '0';
	var $insurance_currency         = 'USD';
	var $insurance_value            = '100.00';
	var $pickup_service             = '01';
	var $pkg_dimension_unit         = 'IN';
	var $pkg_height                 = '4';
	var $pkg_length                 = '8';
	var $pkg_type                   = '02';
	var $pkg_weight_unit            = 'LBS';
	var $pkg_width                  = '6';
	var $residential_address        = '1';
	var $return_service             = '0';
	var $return_service_value       = '2';
	var $saturday_pickup            = '0';
	var $saturday_delivery          = '0';
	var $ship_city_town             = '';
	var $ship_state_province        = '';
	var $ship_postal_code           = '';
	var $ship_country_code          = '';
	var $ship_to_city               = '';
	var $ship_to_state              = '';
	var $ship_to_postal_code        = '';
	var $ship_to_country_code       = '';
	var $split_large_shipments      = '1';
	var $split_large_shipments_unit = 'LBS';
	var $split_large_shipments_value= '75';
	// Multi-package: array of ['weight', 'length', 'width', 'height'] in pkg_weight_unit / pkg_dimension_unit
	var $packages = array();
	// Order item list for carrier-side box selection: [['sku' => string, 'qty' => int], ...]
	var $items = array();

// class constructor
  function __construct() {
	$this->arrOutput     = array();
	$this->terminal_date = date('m/d/Y', time());
	$this->additional_handling        = defined('SHIPPING_DEFAULT_ADDITIONAL_HANDLING_CHECKED')   ? SHIPPING_DEFAULT_ADDITIONAL_HANDLING_CHECKED   : $this->additional_handling;
	$this->cod                        = defined('SHIPPING_DEFAULT_COD_CHECKED')                   ? SHIPPING_DEFAULT_COD_CHECKED                   : $this->cod;
	$this->cod_currency               = defined('SHIPPING_DEFAULT_CURRENCY')                      ? SHIPPING_DEFAULT_CURRENCY                      : $this->cod_currency;
	$this->cod_payment_type           = defined('SHIPPING_DEFAULT_PAYMENT_TYPE')                  ? SHIPPING_DEFAULT_PAYMENT_TYPE                  : $this->cod_payment_type;
	$this->delivery_confirmation      = defined('SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_CHECKED') ? SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_CHECKED  : $this->delivery_confirmation;
	$this->delivery_confirmation_type = defined('SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_TYPE')    ? SHIPPING_DEFAULT_DELIVERY_COMFIRMATION_TYPE     : $this->delivery_confirmation_type;
	$this->dry_ice                    = defined('SHIPPING_DEFAULT_DRY_ICE_CHECKED')               ? SHIPPING_DEFAULT_DRY_ICE_CHECKED               : $this->dry_ice;
	$this->handling_charge            = defined('SHIPPING_DEFAULT_HANDLING_CHARGE_CHECKED')       ? SHIPPING_DEFAULT_HANDLING_CHARGE_CHECKED        : $this->handling_charge;
	$this->handling_charge_currency   = defined('SHIPPING_DEFAULT_CURRENCY')                      ? SHIPPING_DEFAULT_CURRENCY                      : $this->handling_charge_currency;
	$this->handling_charge_value      = defined('SHIPPING_DEFAULT_HANDLING_CHARGE_VALUE')         ? SHIPPING_DEFAULT_HANDLING_CHARGE_VALUE          : $this->handling_charge_value;
	$this->hazardous_material         = defined('SHIPPING_DEFAULT_HAZARDOUS_MATERIAL_CHECKED')    ? SHIPPING_DEFAULT_HAZARDOUS_MATERIAL_CHECKED     : $this->hazardous_material;
	$this->insurance                  = defined('SHIPPING_DEFAULT_INSURANCE_CHECKED')             ? SHIPPING_DEFAULT_INSURANCE_CHECKED              : $this->insurance;
	$this->insurance_currency         = defined('SHIPPING_DEFAULT_CURRENCY')                      ? SHIPPING_DEFAULT_CURRENCY                      : $this->insurance_currency;
	$this->insurance_value            = defined('SHIPPING_DEFAULT_INSURANCE_VALUE')               ? SHIPPING_DEFAULT_INSURANCE_VALUE                : $this->insurance_value;
	$this->pickup_service             = defined('SHIPPING_DEFAULT_PICKUP_SERVICE')                ? SHIPPING_DEFAULT_PICKUP_SERVICE                 : $this->pickup_service;
	$this->pkg_dimension_unit         = defined('SHIPPING_DEFAULT_PKG_DIM_UNIT')                  ? SHIPPING_DEFAULT_PKG_DIM_UNIT                  : $this->pkg_dimension_unit;
	$this->pkg_height                 = defined('SHIPPING_DEFAULT_HEIGHT')                        ? SHIPPING_DEFAULT_HEIGHT                        : $this->pkg_height;
	$this->pkg_length                 = defined('SHIPPING_DEFAULT_LENGTH')                        ? SHIPPING_DEFAULT_LENGTH                        : $this->pkg_length;
	$this->pkg_type                   = defined('SHIPPING_DEFAULT_PACKAGE_TYPE')                  ? SHIPPING_DEFAULT_PACKAGE_TYPE                  : $this->pkg_type;
	$this->pkg_weight_unit            = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')                   ? SHIPPING_DEFAULT_WEIGHT_UNIT                   : $this->pkg_weight_unit;
	$this->pkg_width                  = defined('SHIPPING_DEFAULT_WIDTH')                         ? SHIPPING_DEFAULT_WIDTH                         : $this->pkg_width;
	$this->residential_address        = defined('SHIPPING_DEFAULT_RESIDENTIAL')                   ? SHIPPING_DEFAULT_RESIDENTIAL                   : $this->residential_address;
	$this->return_service             = defined('SHIPPING_DEFAULT_RETURN_SERVICE_CHECKED')        ? SHIPPING_DEFAULT_RETURN_SERVICE_CHECKED         : $this->return_service;
	$this->return_service_value       = defined('SHIPPING_DEFAULT_RETURN_SERVICE')                ? SHIPPING_DEFAULT_RETURN_SERVICE                : $this->return_service_value;
	$this->saturday_pickup            = defined('SHIPPING_DEFAULT_SATURDAY_PICKUP_CHECKED')       ? SHIPPING_DEFAULT_SATURDAY_PICKUP_CHECKED        : $this->saturday_pickup;
	$this->saturday_delivery          = defined('SHIPPING_DEFAULT_SATURDAY_DELIVERY_CHECKED')     ? SHIPPING_DEFAULT_SATURDAY_DELIVERY_CHECKED      : $this->saturday_delivery;
	$this->ship_city_town             = defined('COMPANY_CITY_TOWN')                              ? COMPANY_CITY_TOWN                              : $this->ship_city_town;
	$this->ship_state_province        = defined('COMPANY_ZONE')                                   ? COMPANY_ZONE                                   : $this->ship_state_province;
	$this->ship_postal_code           = defined('COMPANY_POSTAL_CODE')                            ? COMPANY_POSTAL_CODE                            : $this->ship_postal_code;
	$this->ship_country_code          = defined('COMPANY_COUNTRY')                                ? COMPANY_COUNTRY                                : $this->ship_country_code;
	$this->ship_to_country_code       = defined('COMPANY_COUNTRY')                                ? COMPANY_COUNTRY                                : $this->ship_to_country_code;
	$this->split_large_shipments      = defined('SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_CHECKED') ? SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_CHECKED  : $this->split_large_shipments;
	$this->split_large_shipments_unit = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')                   ? SHIPPING_DEFAULT_WEIGHT_UNIT                   : $this->split_large_shipments_unit;
	$this->split_large_shipments_value= defined('SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_VALUE')   ? SHIPPING_DEFAULT_SPLIT_LARGE_SHIPMENTS_VALUE    : $this->split_large_shipments_value;
  }

  /**
   * Returns packages array, falling back to a single synthetic package from scalar properties.
   * Each element: ['weight' => float, 'length' => float, 'width' => float, 'height' => float]
   */
  function get_packages() {
    if (!empty($this->packages)) return $this->packages;
    return array(array(
      'weight' => (float)$this->pkg_weight,
      'length' => (float)$this->pkg_length,
      'width'  => (float)$this->pkg_width,
      'height' => (float)$this->pkg_height,
    ));
  }

  function get_total_weight() {
    if (!empty($this->packages)) return array_sum(array_column($this->packages, 'weight'));
    return (float)$this->pkg_weight;
  }

  function split_shipment($pkg) {
	$package = array();
// TBD convert both to same units to compare
	if ($pkg->pkg_weight == 0) return false;
	$num_packages = (($pkg->split_large_shipments) ? ceil($pkg->pkg_weight / $pkg->split_large_shipments_value) : 1);
	for ($i=0; $i<$num_packages; $i++) {
	  $package[] = array(
		'PackageTypeCode'       => $pkg->pkg_type,
		'DimensionUnit'         => $pkg->pkg_dimension_unit,
		'Length'                => $pkg->pkg_length,
		'Width'                 => $pkg->pkg_width,
		'Height'                => $pkg->pkg_height,
		'WeightUnit'            => $pkg->pkg_weight_unit,
		'Weight'                => ceil($pkg->pkg_weight / $num_packages),
		'DeliveryConfirmation'  => (($pkg->delivery_confirmation) ? $pkg->delivery_confirmation_type : ''),
		'InsuranceCurrencyCode' => (($pkg->insurance) ? $pkg->insurance_currency : ''),
		'InsuranceValue'        => (($pkg->insurance) ? $pkg->insurance_value : ''),
		'AdditionalHandling'    => $pkg->additional_handling,
	  );
	}
	$this->num_packages = $num_packages;
	return $package;
  }

}
?>
