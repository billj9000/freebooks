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

// DHL Express Commerce REST API
// Auth: HTTP Basic (apiKey:apiSecret)
define('DHL_API_BASE_LIVE', 'https://express.api.dhl.com/mydhlapi');
define('DHL_API_BASE_TEST', 'https://express.api.dhl.com/mydhlapi'); // use DHL sandbox credentials for test

// Wire up service level constants for the shipping estimator dropdown
if (defined('MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLWW')) define('dhl_express_DHLWW', MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLWW);
if (defined('MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLE9')) define('dhl_express_DHLE9', MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLE9);
if (defined('MODULE_SHIPPING_DHL_EXPRESS_SVC_DHL12')) define('dhl_express_DHL12', MODULE_SHIPPING_DHL_EXPRESS_SVC_DHL12);
if (defined('MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLEV')) define('dhl_express_DHLEV', MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLEV);

class dhl_express {

  // Maps our internal codes → DHL product codes
  var $DHLProductMap = array(
    'DHLWW' => 'P',  // Express Worldwide
    'DHLE9' => 'Y',  // Express 9:00
    'DHL12' => 'T',  // Express 12:00
    'DHLEV' => 'D',  // Express Envelope (document)
  );

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
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_TYPES',          'default' => 'DHLWW'),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_SORT_ORDER',     'default' => '30'),
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

      case 'MODULE_SHIPPING_DHL_EXPRESS_TYPES':
        $all_services = array(
          array('id' => 'DHLWW', 'text' => MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLWW),
          array('id' => 'DHLE9', 'text' => MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLE9),
          array('id' => 'DHL12', 'text' => MODULE_SHIPPING_DHL_EXPRESS_SVC_DHL12),
          array('id' => 'DHLEV', 'text' => MODULE_SHIPPING_DHL_EXPRESS_SVC_DHLEV),
        );
        $enabled = explode(',', str_replace(' ', '', constant($key)));
        $choices = array();
        foreach ($all_services as $svc) {
          $choices[] = html_checkbox_field(strtolower($key) . '[]', $svc['id'],
            in_array($svc['id'], $enabled), '', '') . ' ' . $svc['text'];
        }
        return implode('<br />' . chr(10), $choices);

      default:
        return html_input_field(strtolower($key), constant($key), '');
    }
  }

  function update() {
    foreach ($this->keys() as $key) {
      $field = strtolower($key['key']);
      if ($key['key'] == 'MODULE_SHIPPING_DHL_EXPRESS_TYPES') {
        write_configure($key['key'], implode(',', $_POST[$field]));
      } elseif (isset($_POST[$field])) {
        write_configure($key['key'], $_POST[$field]);
      }
    }
  }

  // ---------------------------------------------------------------
  //  Rate quoting
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
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY, 'error');
      return false;
    }

    $base      = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    $enabled   = explode(',', str_replace(' ', '', MODULE_SHIPPING_DHL_EXPRESS_TYPES));
    $weightKg  = $this->toKg($pkg->pkg_weight, $pkg->pkg_weight_unit);
    $lenCm     = $this->toCm($pkg->pkg_length, $pkg->pkg_dimension_unit);
    $widCm     = $this->toCm($pkg->pkg_width,  $pkg->pkg_dimension_unit);
    $hgtCm     = $this->toCm($pkg->pkg_height, $pkg->pkg_dimension_unit);
    $fromISO2  = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
    $fromPost  = defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : '';
    $fromCity  = defined('COMPANY_CITY_TOWN') ? COMPANY_CITY_TOWN : '';
    $toISO2    = $pkg->ship_to_country_iso2 ?? $fromISO2;
    $toPost    = $pkg->ship_to_postal_code;
    $toCity    = $pkg->ship_to_city ?? '';
    $shipDate  = date('Y-m-d\TH:i:00', strtotime($pkg->ship_date . ' 12:00:00')) . 'GMT+00:00';
    $isDomestic = ($fromISO2 === $toISO2);

    $params = http_build_query(array(
      'accountNumber'               => $account,
      'originCountryCode'           => $fromISO2,
      'originCityName'              => $fromCity,
      'originPostalCode'            => $fromPost,
      'destinationCountryCode'      => $toISO2,
      'destinationCityName'         => $toCity,
      'destinationPostalCode'       => $toPost,
      'weight'                      => number_format($weightKg, 3, '.', ''),
      'length'                      => $lenCm,
      'width'                       => $widCm,
      'height'                      => $hgtCm,
      'plannedShippingDateAndTime'  => $shipDate,
      'isCustomsDeclarable'         => $isDomestic ? 'false' : 'true',
      'unitOfMeasurement'           => 'metric',
      'nextBusinessDay'             => 'false',
      'strictValidation'            => 'false',
      'getAllValueAddedServices'     => 'false',
      'requestEstimatedDeliveryDate'=> 'true',
    ));

    $response = $this->curlRequest('GET', $base . '/rates?' . $params,
      array('Accept: application/json'),
      null, $apiKey, $apiSecret);

    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_RATES . 'API request failed.', 'error');
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['products']) || !is_array($data['products'])) {
      $msg = $data['title'] ?? $data['detail'] ?? $response;
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_RATES . $msg, 'caution');
      return array('result' => 'success', 'rates' => array());
    }

    $reverseMap = array_flip($this->DHLProductMap);
    $arrRates   = array();
    foreach ($data['products'] as $product) {
      $dhlCode      = $product['productCode'] ?? '';
      $internalCode = $reverseMap[$dhlCode] ?? null;
      if (!$internalCode || !in_array($internalCode, $enabled)) continue;

      $price = 0;
      if (!empty($product['totalPrice'])) {
        foreach ($product['totalPrice'] as $tp) {
          if (($tp['currencyType'] ?? '') === 'BILLC') {
            $price = (float)($tp['price'] ?? 0);
            break;
          }
        }
        if (!$price) $price = (float)($product['totalPrice'][0]['price'] ?? 0);
      }
      if ($price <= 0) continue;

      $deliveryNote = '';
      if (!empty($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'])) {
        $deliveryNote = 'Est: ' . substr($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'], 0, 10);
      }

      $arrRates[$this->code][$internalCode] = array(
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

    $base       = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    $productCode= $this->DHLProductMap[$sInfo->ship_method] ?? 'P';
    $fromISO2   = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
    $toISO2     = strlen($sInfo->ship_country_code) == 3
      ? gen_get_country_iso_2_from_3($sInfo->ship_country_code)
      : $sInfo->ship_country_code;
    $isDomestic  = ($fromISO2 === $toISO2);
    $shipDate    = date('Y-m-d\TH:i:00', strtotime($sInfo->ship_date . ' 12:00:00')) . 'GMT+00:00';
    $declaredValue = (float)($sInfo->customs_value ?: $sInfo->total_value ?: 0);

    // Build packages
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
      'pickup'   => array('isRequested' => false),
      'productCode' => $productCode,
      'accounts' => array(
        array('typeCode' => 'shipper', 'number' => $account),
      ),
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
        'packages'            => $packages,
        'isCustomsDeclarable' => !$isDomestic,
        'description'         => $sInfo->customs_description ?: 'Goods',
        'declaredValue'       => $declaredValue,
        'declaredValueCurrency'=> 'GBP',
        'unitOfMeasurement'   => 'metric',
        'incoterm'            => 'DAP',
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

    // Add export declaration for international shipments
    if (!$isDomestic && !empty($sInfo->customs_description)) {
      $body['content']['exportDeclaration'] = array(
        'lineItems' => array(
          array(
            'number'      => 1,
            'description' => $sInfo->customs_description,
            'price'       => $declaredValue,
            'priceCurrency'=> 'GBP',
            'exportReasonType' => 'permanent',
            'quantity'    => array('value' => 1, 'unitOfMeasurement' => 'PCS'),
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

    $headers  = array('Content-Type: application/json', 'Accept: application/json');
    $response = $this->curlRequest('POST', $base . '/shipments', $headers,
      json_encode($body), $apiKey, $apiSecret);

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

    $tracking = $data['shipmentTrackingNumber'];
    $date     = $sInfo->ship_date;
    $file_path = SHIPPING_DEFAULT_LABEL_DIR . $this->code . '/' . str_replace('-', '/', $date) . '/';
    validate_path($file_path);

    // DHL returns one label per package in packages[].documents[]
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
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error) return false;
    if ($httpCode >= 400) return $result;
    return $result;
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
