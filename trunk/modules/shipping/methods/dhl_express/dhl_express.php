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
//  Path: /modules/shipping/methods/dhl_express/dhl_express.php
//

define('MODULE_SHIPPING_DHL_EXPRESS_VERSION', '1.0');

// DHL Express Commerce REST API — auth: HTTP Basic (apiKey:apiSecret)
define('DHL_API_BASE_LIVE', 'https://express.api.dhl.com/mydhlapi');
define('DHL_API_BASE_TEST', 'https://express.api.dhl.com/mydhlapi/test');

// Pre-define display names for DHL product codes (keyed as dhl_express_DHL_{code}).
// These are used by build_js_methods() at page-load time and by template_detail.php.
// quote() will override these with live names from the API if they differ.
define('dhl_express_DHL_P', 'DHL Express Worldwide');
define('dhl_express_DHL_D', 'DHL Express Worldwide (Dutiable)');
define('dhl_express_DHL_I', 'DHL Express Worldwide (Doc)');
define('dhl_express_DHL_N', 'DHL Domestic Express');
define('dhl_express_DHL_G', 'DHL Domestic Economy Select');
define('dhl_express_DHL_Y', 'DHL Express 9:00');
define('dhl_express_DHL_E', 'DHL Express 9:00 (Dutiable)');
define('dhl_express_DHL_K', 'DHL Express 9:00 (Regional)');
define('dhl_express_DHL_T', 'DHL Express 12:00');
define('dhl_express_DHL_L', 'DHL Express 10:30');
define('dhl_express_DHL_M', 'DHL Express 10:30 (Dutiable)');
define('dhl_express_DHL_W', 'DHL Express 12:00 (Dutiable)');
define('dhl_express_DHL_Q', 'DHL Medical Express');
define('dhl_express_DHL_C', 'DHL Medical Express (Domestic)');
define('dhl_express_DHL_H', 'DHL Economy Select');
define('dhl_express_DHL_8', 'DHL Express Easy');

class dhl_express {

  function __construct() {
    $this->code = 'dhl_express';
  }

  function keys() {
    return array(
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_TITLE',          'default' => 'DHL Express'),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_API_KEY',        'default' => ''),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_API_SECRET',     'default' => ''),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER', 'default' => ''),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE',      'default' => 'Test'),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_SORT_ORDER',     'default' => '50'),
    );
  }

  function configure($key) {
    switch ($key) {
      case 'MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE':
        $temp = array(
          array('id' => 'Test',       'text' => TEXT_TEST),
          array('id' => 'Production', 'text' => TEXT_PRODUCTION),
        );
        return html_pull_down_menu(strtolower($key), $temp, constant($key));
      default:
        return html_input_field(strtolower($key), constant($key), '');
    }
  }

  function update() {
    foreach ($this->keys() as $key) {
      if (isset($_POST[strtolower($key['key'])])) {
        write_configure($key['key'], $_POST[strtolower($key['key'])]);
      }
    }
  }

  // ---------------------------------------------------------------
  //  Rate quoting — returns all products DHL quotes for the route
  // ---------------------------------------------------------------
  function quote($pkg) {
    global $messageStack;
    if ($pkg->pkg_weight == 0) {
      $messageStack->add(SHIPPING_ERROR_WEIGHT_ZERO, 'error');
      return false;
    }
    $apiKey    = defined('MODULE_SHIPPING_DHL_EXPRESS_API_KEY')        ? MODULE_SHIPPING_DHL_EXPRESS_API_KEY        : '';
    $apiSecret = defined('MODULE_SHIPPING_DHL_EXPRESS_API_SECRET')     ? MODULE_SHIPPING_DHL_EXPRESS_API_SECRET     : '';
    $account   = defined('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER') ? MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER : '';
    if (!$apiKey || !$apiSecret) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY, 'caution');
      return array('result' => 'success', 'rates' => array());
    }

    $base     = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    $weightKg = $this->toKg($pkg->pkg_weight, $pkg->pkg_weight_unit);
    $lenCm    = $this->toCm($pkg->pkg_length, $pkg->pkg_dimension_unit);
    $widCm    = $this->toCm($pkg->pkg_width,  $pkg->pkg_dimension_unit);
    $hgtCm    = $this->toCm($pkg->pkg_height, $pkg->pkg_dimension_unit);
    $fromISO2 = defined('COMPANY_COUNTRY')     ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
    $fromPost = defined('COMPANY_POSTAL_CODE') ? trim(COMPANY_POSTAL_CODE) : '';
    $fromCity = defined('COMPANY_CITY_TOWN')   ? trim(COMPANY_CITY_TOWN)   : '';
    $toISO2   = $pkg->ship_to_country_iso2 ?? $fromISO2;
    $toPost   = trim($pkg->ship_to_postal_code ?? '');
    $toCity   = trim($pkg->ship_to_city_town ?? $pkg->ship_to_city ?? '');
    $isDomestic = ($fromISO2 === $toISO2);

    $ts = strtotime($pkg->ship_date);
    if (!$ts) $ts = time();
    // Advance to tomorrow if noon UTC today has already passed
    $noonUtc = gmmktime(12, 0, 0, (int)gmdate('n', $ts), (int)gmdate('j', $ts), (int)gmdate('Y', $ts));
    if ($noonUtc <= time()) $ts = strtotime('+1 day', $ts);
    $shipDate = gmdate('Y-m-d', $ts) . 'T12:00:00GMT+00:00';

    $package = array('weight' => $weightKg);
    if ($lenCm && $widCm && $hgtCm) {
      $package['dimensions'] = array('length' => $lenCm, 'width' => $widCm, 'height' => $hgtCm);
    }

    $shipper  = array('postalCode' => $fromPost, 'countryCode' => $fromISO2);
    $receiver = array('postalCode' => $toPost,   'countryCode' => $toISO2);
    if ($fromCity) $shipper['cityName']  = $fromCity;
    if ($toCity)   $receiver['cityName'] = $toCity;

    $body = array(
      'customerDetails'            => array('shipperDetails' => $shipper, 'receiverDetails' => $receiver),
      'accounts'                   => array(array('typeCode' => 'shipper', 'number' => $account)),
      'plannedShippingDateAndTime' => $shipDate,
      'unitOfMeasurement'          => 'metric',
      'isCustomsDeclarable'        => !$isDomestic,
      'packages'                   => array($package),
      'nextBusinessDay'            => true,
    );

    $response = $this->curlRequest('POST', $base . '/rates',
      $this->dhlHeaders(), json_encode($body), $apiKey, $apiSecret);

    if ($response === false) {
      $messageStack->add('DHL curl failed', 'error');
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['products']) || !is_array($data['products'])) {
      // "Product not found" = no services for this route/date — not an error
      if (($data['title'] ?? '') !== 'Product not found') {
        $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_RATES . ($data['detail'] ?? $response), 'caution');
      }
      return array('result' => 'success', 'rates' => array());
    }

    $arrRates = array();
    foreach ($data['products'] as $product) {
      $dhlCode = $product['productCode'] ?? '';
      if (!$dhlCode) continue;

      // Rate key uses DHL_ prefix so it matches the service_levels keys in defaults.php
      $rateKey   = 'DHL_' . $dhlCode;
      $constName = 'dhl_express_' . $rateKey;
      // Pre-defined constants cannot be redefined; only define if this code wasn't pre-defined
      if (!defined($constName)) {
        define($constName, $product['productName'] ?? ('DHL ' . $dhlCode));
      }

      // Prefer BILLC billing price; fall back to first available price
      $price = 0;
      foreach ($product['totalPrice'] ?? array() as $tp) {
        if (($tp['currencyType'] ?? '') === 'BILLC') {
          $price = (float)($tp['price'] ?? 0);
          break;
        }
      }
      if (!$price) {
        foreach ($product['totalPrice'] ?? array() as $tp) {
          if (($tp['price'] ?? 0) > 0) { $price = (float)$tp['price']; break; }
        }
      }

      $deliveryNote = '';
      if (!empty($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'])) {
        $deliveryNote = 'Est: ' . substr($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'], 0, 10);
      }

      $arrRates[$this->code][$rateKey] = array(
        'book'  => $price,
        'quote' => $price,
        'cost'  => $price,
        'note'  => $deliveryNote,
      );
    }

    return array('result' => 'success', 'rates' => $arrRates);
  }

  // ---------------------------------------------------------------
  //  Label generation
  // ---------------------------------------------------------------
  function retrieveLabel($sInfo) {
    global $messageStack;
    $apiKey    = defined('MODULE_SHIPPING_DHL_EXPRESS_API_KEY')        ? MODULE_SHIPPING_DHL_EXPRESS_API_KEY        : '';
    $apiSecret = defined('MODULE_SHIPPING_DHL_EXPRESS_API_SECRET')     ? MODULE_SHIPPING_DHL_EXPRESS_API_SECRET     : '';
    $account   = defined('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER') ? MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER : '';
    if (!$apiKey || !$apiSecret) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY, 'error');
      return false;
    }

    $base = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    // ship_method is stored with DHL_ prefix (matches service_levels key); strip it for the API
    $rawMethod   = $sInfo->ship_method;
    $productCode = (strncmp($rawMethod, 'DHL_', 4) === 0) ? substr($rawMethod, 4) : $rawMethod;
    $fromISO2    = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
    $toISO2      = strlen($sInfo->ship_country_code) == 3
      ? gen_get_country_iso_2_from_3($sInfo->ship_country_code)
      : $sInfo->ship_country_code;
    $isDomestic  = ($fromISO2 === $toISO2);
    $shipDate    = date('Y-m-d\TH:i:00', strtotime($sInfo->ship_date . ' 12:00:00')) . 'GMT+00:00';
    $declaredValue = (float)($sInfo->customs_value ?: $sInfo->total_value ?: 0);

    $packages = array();
    foreach ($sInfo->package as $pkg) {
      $packages[] = array(
        'weight'     => $this->toKg($pkg['weight'], $sInfo->pkg_weight_unit),
        'dimensions' => array(
          'length' => $this->toCm($pkg['length'], $sInfo->pkg_dimension_unit),
          'width'  => $this->toCm($pkg['width'],  $sInfo->pkg_dimension_unit),
          'height' => $this->toCm($pkg['height'], $sInfo->pkg_dimension_unit),
        ),
      );
    }

    $body = array(
      'plannedShippingDateAndTime' => $shipDate,
      'pickup'      => array('isRequested' => false),
      'productCode' => $productCode,
      'accounts'    => array(array('typeCode' => 'shipper', 'number' => $account)),
      'customerDetails' => array(
        'shipperDetails' => array(
          'postalAddress' => array(
            'postalCode'   => defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : '',
            'cityName'     => defined('COMPANY_CITY_TOWN')   ? COMPANY_CITY_TOWN   : '',
            'countryCode'  => $fromISO2,
            'addressLine1' => defined('COMPANY_ADDRESS1')    ? COMPANY_ADDRESS1    : '',
            'addressLine2' => defined('COMPANY_ADDRESS2')    ? COMPANY_ADDRESS2    : '',
          ),
          'contactInformation' => array(
            'phone'       => defined('COMPANY_TELEPHONE1') ? COMPANY_TELEPHONE1 : '',
            'companyName' => defined('COMPANY_NAME')       ? COMPANY_NAME       : '',
            'fullName'    => defined('AR_CONTACT_NAME')    ? AR_CONTACT_NAME    : (defined('COMPANY_NAME') ? COMPANY_NAME : ''),
            'email'       => defined('COMPANY_EMAIL')      ? COMPANY_EMAIL      : '',
          ),
        ),
        'receiverDetails' => array(
          'postalAddress' => array(
            'postalCode'   => $sInfo->ship_postal_code,
            'cityName'     => $sInfo->ship_city_town,
            'countryCode'  => $toISO2,
            'addressLine1' => $sInfo->ship_address1,
            'addressLine2' => $sInfo->ship_address2 ?? '',
          ),
          'contactInformation' => array(
            'phone'       => $sInfo->ship_telephone1 ?? '',
            'companyName' => $sInfo->ship_primary_name,
            'fullName'    => $sInfo->ship_contact ?: $sInfo->ship_primary_name,
            'email'       => $sInfo->ship_email ?? '',
          ),
        ),
      ),
      'content' => array(
        'packages'             => $packages,
        'isCustomsDeclarable'  => !$isDomestic,
        'description'          => $sInfo->customs_description ?: 'Goods',
        'declaredValue'        => $declaredValue,
        'declaredValueCurrency'=> 'GBP',
        'unitOfMeasurement'    => 'SI',
        'incoterm'             => 'DAP',
      ),
      'outputImageProperties' => array(
        'printerDPI'     => 300,
        'encodingFormat' => 'pdf',
        'imageOptions'   => array(
          array('typeCode' => 'label', 'templateName' => 'ECOM26_84_001', 'isRequested' => true),
        ),
      ),
      'customerReferences' => array(
        array('value' => $sInfo->purchase_invoice_id, 'typeCode' => 'CU'),
      ),
    );

    if (!$isDomestic && !empty($sInfo->customs_description)) {
      $body['content']['exportDeclaration'] = array(
        'lineItems' => array(
          array(
            'number'           => 1,
            'description'      => $sInfo->customs_description,
            'price'            => $declaredValue,
            'priceCurrency'    => 'GBP',
            'exportReasonType' => 'permanent',
            'quantity'         => array('value' => 1, 'unitOfMeasurement' => 'PCS'),
            'commodityCodes'   => array(
              array('typeCode' => 'outbound', 'value' => $sInfo->hs_code ?? ''),
            ),
            'manufacturerCountry' => $sInfo->country_of_origin
              ?? (defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB'),
            'weight' => array(
              'netValue'   => $this->toKg($sInfo->total_weight ?? 0, $sInfo->pkg_weight_unit),
              'grossValue' => $this->toKg($sInfo->total_weight ?? 0, $sInfo->pkg_weight_unit),
            ),
          ),
        ),
        'invoice' => array(
          'number'        => $sInfo->purchase_invoice_id,
          'date'          => $sInfo->ship_date,
          'signatureName' => defined('AR_CONTACT_NAME') ? AR_CONTACT_NAME : '',
        ),
        'exportReason' => $sInfo->customs_reason ?: 'permanent',
      );
    }

    $response = $this->curlRequest('POST', $base . '/shipments',
      $this->dhlHeaders(), json_encode($body), $apiKey, $apiSecret);

    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'API request failed.', 'error');
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['shipmentTrackingNumber'])) {
      $msg = $data['title'] ?? $data['detail'] ?? $response;
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . $msg, 'error');
      return false;
    }

    $tracking  = $data['shipmentTrackingNumber'];
    $file_path = SHIPPING_DEFAULT_LABEL_DIR . $this->code . '/' . str_replace('-', '/', $sInfo->ship_date) . '/';
    validate_path($file_path);

    $results = array();
    $pkgs = $data['packages'] ?? array(array('trackingNumber' => $tracking, 'documents' => $data['documents'] ?? array()));
    foreach ($pkgs as $idx => $pkg_result) {
      $pkgTracking = $pkg_result['trackingNumber'] ?? $tracking;
      foreach ($pkg_result['documents'] ?? array() as $doc) {
        if (($doc['typeCode'] ?? '') !== 'label') continue;
        $labelData = base64_decode($doc['content'] ?? '');
        if (!$labelData) continue;
        $file_name = $pkgTracking . ($idx > 0 ? '-' . $idx : '') . '.pdf';
        if (!file_put_contents($file_path . $file_name, $labelData)) {
          $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'Cannot write label file.', 'error');
          return false;
        }
      }
      $results[] = array(
        'ref_id'        => $sInfo->purchase_invoice_id . ($idx > 0 ? '-' . ($idx + 1) : ''),
        'tracking'      => $pkgTracking,
        'zone'          => '',
        'book_cost'     => 0,
        'net_cost'      => 0,
        'delivery_date' => $data['estimatedDeliveryTime'] ?? '',
      );
    }

    if (empty($results)) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'No label documents in response.', 'error');
      return false;
    }
    return $results;
  }

  // ---------------------------------------------------------------
  //  Helpers
  // ---------------------------------------------------------------
  private function dhlHeaders() {
    return array(
      'Accept: application/json',
      'Content-Type: application/json',
      'Message-Reference: freebooks-' . uniqid(),
      'Message-Reference-Date: ' . gmdate('Y-m-d\TH:i:s \G\M\T+00:00'),
    );
  }

  private function curlRequest($method, $url, $headers, $body = null, $user = null, $pass = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    if ($user !== null) {
      curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }
    if ($method === 'POST') {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);
    return $error ? false : $result;
  }

  private function toKg($value, $unit) {
    $v = (float)$value;
    return ($unit === 'KGS') ? round($v, 3) : round($v * 0.453592, 3);
  }

  private function toCm($value, $unit) {
    $v = (float)$value;
    return ($unit === 'CM') ? (int)round($v) : (int)round($v * 2.54);
  }

} // end class dhl_express
?>
