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
//  Path: /modules/shipping/methods/royal_mail/royal_mail.php
//

define('MODULE_SHIPPING_ROYAL_MAIL_VERSION', '1.0');

// RM Shipping API (OBA) — rate quoting
// Auth: x-ibm-client-id / x-ibm-client-secret headers
define('RM_API_BASE_LIVE', 'https://api.royalmail.net/shipping/v3');
define('RM_API_BASE_TEST', 'https://api.royalmail.net/shipping/v3'); // same endpoint, different credentials

// Click & Drop API — label generation (RM and Parcelforce)
// Auth: OBA-API-KEY header
define('RM_CD_BASE_LIVE', 'https://api.clickanddrop.royalmail.com/v2');
define('RM_CD_BASE_TEST', 'https://api.clickanddrop.royalmail.com/v2');

// Map our internal service codes → RM Shipping API service codes (for rate quoting)
// Map our internal service codes → Click & Drop service codes (for label generation)
// These match the Royal Mail OBA / Click & Drop v2 API service identifiers.

// Wire up service level constants for the shipping estimator dropdown
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM24'))  define('royal_mail_RM24',  MODULE_SHIPPING_ROYAL_MAIL_SVC_RM24);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM48'))  define('royal_mail_RM48',  MODULE_SHIPPING_ROYAL_MAIL_SVC_RM48);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD9')) define('royal_mail_RMSD9', MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD9);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD1')) define('royal_mail_RMSD1', MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD1);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM1CL')) define('royal_mail_RM1CL', MODULE_SHIPPING_ROYAL_MAIL_SVC_RM1CL);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RM2CL')) define('royal_mail_RM2CL', MODULE_SHIPPING_ROYAL_MAIL_SVC_RM2CL);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIT'))  define('royal_mail_RMIT',  MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIT);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIS'))  define('royal_mail_RMIS',  MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIS);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMITS')) define('royal_mail_RMITS', MODULE_SHIPPING_ROYAL_MAIL_SVC_RMITS);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIE'))  define('royal_mail_RMIE',  MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIE);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX9'))  define('royal_mail_PFX9',  MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX9);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX10')) define('royal_mail_PFX10', MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX10);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFXAM')) define('royal_mail_PFXAM', MODULE_SHIPPING_ROYAL_MAIL_SVC_PFXAM);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX24')) define('royal_mail_PFX24', MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX24);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX48')) define('royal_mail_PFX48', MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX48);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX4L')) define('royal_mail_PFX4L', MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX4L);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGX'))  define('royal_mail_PFGX',  MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGX);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGP'))  define('royal_mail_PFGP',  MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGP);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGV'))  define('royal_mail_PFGV',  MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGV);
if (defined('MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGE'))  define('royal_mail_PFGE',  MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGE);

class royal_mail {

  // Maps our internal codes to RM OBA API service codes (rate quoting)
  var $RMRateMap = array(
    'RM24'  => 'TM',   // Tracked 24
    'RM48'  => 'TP',   // Tracked 48
    'RMSD9' => 'SD9',  // Special Delivery Guaranteed 9am
    'RMSD1' => 'SD1',  // Special Delivery Guaranteed 1pm
    'RM1CL' => '1C',   // 1st Class
    'RM2CL' => '2C',   // 2nd Class
    'RMIT'  => 'IT',   // International Tracked
    'RMIS'  => 'IS',   // International Signed
    'RMITS' => 'ITS',  // International Tracked & Signed
    'RMIE'  => 'IE',   // International Economy
  );

  // Maps our internal codes to Click & Drop service codes (label generation)
  var $CDServiceMap = array(
    'RM24'  => 'TM',
    'RM48'  => 'TP',
    'RMSD9' => 'SD9',
    'RMSD1' => 'SD1',
    'RM1CL' => '1C',
    'RM2CL' => '2C',
    'RMIT'  => 'IT',
    'RMIS'  => 'IS',
    'RMITS' => 'ITS',
    'RMIE'  => 'IE',
    // Parcelforce via Click & Drop
    'PFX9'  => 'express9',
    'PFX10' => 'express10',
    'PFXAM' => 'expressAM',
    'PFX24' => 'express24',
    'PFX48' => 'express48',
    'PFX4L' => 'express48L',
    'PFGX'  => 'globalExpress',
    'PFGP'  => 'globalPriority',
    'PFGV'  => 'globalValue',
    'PFGE'  => 'globalEconomy',
  );

  // International service codes (require customs data on labels)
  var $intlServices = array('RMIT','RMIS','RMITS','RMIE','PFGX','PFGP','PFGV','PFGE');

  // Parcelforce service codes (use configured rates, not live API)
  var $pfServices = array('PFX9','PFX10','PFXAM','PFX24','PFX48','PFX4L','PFGX','PFGP','PFGV','PFGE');

  function __construct() {
    $this->code = 'royal_mail';
  }

  function keys() {
    return array(
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_TITLE',            'default' => 'Royal Mail / Parcelforce'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_CLICKDROP_API_KEY','default' => ''),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_ID',     'default' => ''),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_SECRET', 'default' => ''),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_OBA_ACCOUNT',      'default' => ''),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_TEST_MODE',        'default' => 'Test'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_TYPES',            'default' => 'RM24,RM48'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER',       'default' => '20'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFX9_RATE',        'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFX10_RATE',       'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFXAM_RATE',       'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFX24_RATE',       'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFX48_RATE',       'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFX4L_RATE',       'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFGX_RATE',        'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFGP_RATE',        'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFGV_RATE',        'default' => '0.00'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_PFGE_RATE',        'default' => '0.00'),
    );
  }

  function configure($key) {
    switch ($key) {
      case 'MODULE_SHIPPING_ROYAL_MAIL_TEST_MODE':
        $temp = array(
          array('id' => 'Test',       'text' => TEXT_TEST),
          array('id' => 'Production', 'text' => TEXT_PRODUCTION),
        );
        return html_pull_down_menu(strtolower($key), $temp, constant($key));

      case 'MODULE_SHIPPING_ROYAL_MAIL_TYPES':
        $all_services = array(
          array('id' => 'RM24',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RM24),
          array('id' => 'RM48',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RM48),
          array('id' => 'RMSD9', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD9),
          array('id' => 'RMSD1', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RMSD1),
          array('id' => 'RM1CL', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RM1CL),
          array('id' => 'RM2CL', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RM2CL),
          array('id' => 'RMIT',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIT),
          array('id' => 'RMIS',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIS),
          array('id' => 'RMITS', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RMITS),
          array('id' => 'RMIE',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_RMIE),
          array('id' => 'PFX9',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX9),
          array('id' => 'PFX10', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX10),
          array('id' => 'PFXAM', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFXAM),
          array('id' => 'PFX24', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX24),
          array('id' => 'PFX48', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX48),
          array('id' => 'PFX4L', 'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFX4L),
          array('id' => 'PFGX',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGX),
          array('id' => 'PFGP',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGP),
          array('id' => 'PFGV',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGV),
          array('id' => 'PFGE',  'text' => MODULE_SHIPPING_ROYAL_MAIL_SVC_PFGE),
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
      if ($key['key'] == 'MODULE_SHIPPING_ROYAL_MAIL_TYPES') {
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
    $enabled = explode(',', str_replace(' ', '', MODULE_SHIPPING_ROYAL_MAIL_TYPES));
    $arrRates = array();

    // RM services: live rates via RM Shipping API
    $rmEnabled = array_diff($enabled, $this->pfServices);
    if (!empty($rmEnabled)) {
      $rmRates = $this->queryRMRates($pkg, $rmEnabled);
      if (is_array($rmRates)) $arrRates = array_merge($arrRates, $rmRates);
    }

    // Parcelforce services: configured rates
    $pfEnabled = array_intersect($enabled, $this->pfServices);
    foreach ($pfEnabled as $code) {
      $rateKey = 'MODULE_SHIPPING_ROYAL_MAIL_' . $code . '_RATE';
      $rate = defined($rateKey) ? (float)constant($rateKey) : 0.0;
      if ($rate > 0) {
        $arrRates[$this->code][$code] = array(
          'book'  => $rate,
          'quote' => $rate,
          'cost'  => $rate,
          'note'  => '',
        );
      }
    }

    return array('result' => 'success', 'rates' => $arrRates);
  }

  private function queryRMRates($pkg, $enabledCodes) {
    global $messageStack;
    $clientId     = defined('MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_ID')     ? MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_ID     : '';
    $clientSecret = defined('MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_SECRET') ? MODULE_SHIPPING_ROYAL_MAIL_RM_CLIENT_SECRET : '';
    if (!$clientId || !$clientSecret) return array();

    $base     = (MODULE_SHIPPING_ROYAL_MAIL_TEST_MODE == 'Production') ? RM_API_BASE_LIVE : RM_API_BASE_TEST;
    $weightG  = $this->toGrams($pkg->pkg_weight, $pkg->pkg_weight_unit);
    $fromPost = defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : '';
    $toPost   = $pkg->ship_to_postal_code;
    $fromISO2 = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
    $toISO2   = $pkg->ship_to_country_iso2 ?? $fromISO2;

    $body = json_encode(array(
      'originPostcode'      => $fromPost,
      'destinationPostcode' => $toPost,
      'originCountryCode'   => $fromISO2,
      'destinationCountryCode' => $toISO2,
      'weight' => array(
        'unitOfMeasure' => 'g',
        'value'         => $weightG,
      ),
      'dimensions' => array(
        'unitOfMeasure' => 'mm',
        'length'        => $this->toMm($pkg->pkg_length, $pkg->pkg_dimension_unit),
        'width'         => $this->toMm($pkg->pkg_width,  $pkg->pkg_dimension_unit),
        'depth'         => $this->toMm($pkg->pkg_height, $pkg->pkg_dimension_unit),
      ),
    ));

    $headers = array(
      'Content-Type: application/json',
      'Accept: application/json',
      'x-ibm-client-id: '     . $clientId,
      'x-ibm-client-secret: ' . $clientSecret,
    );

    // The RM Shipping API v3 prices endpoint (verify path against RM Developer Portal)
    $response = $this->curlRequest('POST', $base . '/prices', $headers, $body);
    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_RATES . 'API request failed.', 'error');
      return array();
    }

    $data = json_decode($response, true);
    if (!isset($data['availableServices']) || !is_array($data['availableServices'])) {
      if (isset($data['httpMessage'])) {
        $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_RATES . $data['httpMessage'], 'caution');
      }
      return array();
    }

    // Build a reverse map: RM API code → our internal code
    $reverseMap = array_flip($this->RMRateMap);
    $arrRates = array();
    foreach ($data['availableServices'] as $svc) {
      $rmCode = $svc['serviceCode'] ?? '';
      $internalCode = $reverseMap[$rmCode] ?? null;
      if (!$internalCode || !in_array($internalCode, $enabledCodes)) continue;
      $price = (float)($svc['totalPackageNetCharge']['amount'] ?? $svc['price']['netPrice'] ?? 0);
      if ($price <= 0) continue;
      $arrRates[$this->code][$internalCode] = array(
        'book'  => $price,
        'quote' => $price,
        'cost'  => $price,
        'note'  => $svc['serviceName'] ?? '',
      );
    }
    return $arrRates;
  }

  // ---------------------------------------------------------------
  //  Label generation via Click & Drop
  // ---------------------------------------------------------------
  function retrieveLabel($sInfo) {
    global $messageStack;
    $apiKey = defined('MODULE_SHIPPING_ROYAL_MAIL_CLICKDROP_API_KEY') ? MODULE_SHIPPING_ROYAL_MAIL_CLICKDROP_API_KEY : '';
    if (!$apiKey) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_NO_KEY, 'error');
      return false;
    }

    $base    = (MODULE_SHIPPING_ROYAL_MAIL_TEST_MODE == 'Production') ? RM_CD_BASE_LIVE : RM_CD_BASE_TEST;
    $headers = array(
      'Content-Type: application/json',
      'Accept: application/json',
      'OBA-API-KEY: ' . $apiKey,
    );

    $serviceCode = $this->CDServiceMap[$sInfo->ship_method] ?? $sInfo->ship_method;
    $isIntl      = in_array($sInfo->ship_method, $this->intlServices);

    // Build packages array
    $packages = array();
    foreach ($sInfo->package as $pkg) {
      $packages[] = array(
        'weightInGrams' => $this->toGrams($pkg['weight'], $sInfo->pkg_weight_unit),
      );
    }

    $order = array(
      'orderDate'       => date('Y-m-d\TH:i:s', strtotime($sInfo->ship_date)),
      'orderIdentifier' => $sInfo->purchase_invoice_id,
      'label'           => array('format' => 'PDF'),
      'recipient'       => array(
        'address' => array(
          'fullName'    => $sInfo->ship_primary_name,
          'companyName' => $sInfo->ship_primary_name,
          'addressLine1'=> $sInfo->ship_address1,
          'addressLine2'=> $sInfo->ship_address2 ?? '',
          'city'        => $sInfo->ship_city_town,
          'postcode'    => $sInfo->ship_postal_code,
          'countryCode' => strlen($sInfo->ship_country_code) == 3
            ? gen_get_country_iso_2_from_3($sInfo->ship_country_code)
            : $sInfo->ship_country_code,
        ),
        'phoneNumber'   => $sInfo->ship_telephone1 ?? '',
        'emailAddress'  => $sInfo->ship_email ?? '',
      ),
      'sender' => array(
        'tradingName'  => defined('COMPANY_NAME') ? COMPANY_NAME : '',
        'phoneNumber'  => defined('COMPANY_TELEPHONE1') ? COMPANY_TELEPHONE1 : '',
        'emailAddress' => defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '',
      ),
      'serviceCode' => $serviceCode,
      'packages'    => $packages,
    );

    // Add customs for international shipments
    if ($isIntl && !empty($sInfo->customs_description)) {
      $declaredValue = (float)($sInfo->customs_value ?: $sInfo->total_value ?: 0);
      $order['customsInfo'] = array(
        'reasonForExport' => $sInfo->customs_reason ?: 'SALE',
        'invoiceDate'     => $sInfo->ship_date,
        'invoiceNumber'   => $sInfo->purchase_invoice_id,
        'totalValue'      => $declaredValue,
        'currency'        => 'GBP',
        'items'           => array(
          array(
            'description'        => $sInfo->customs_description,
            'quantity'           => 1,
            'unitValue'          => $declaredValue,
            'unitWeightInGrams'  => $this->toGrams($sInfo->total_weight ?? 0, $sInfo->pkg_weight_unit),
            'hsCode'             => $sInfo->hs_code ?? '',
            'countryOfOriginCode'=> $sInfo->country_of_origin ?? (defined('COMPANY_COUNTRY')
              ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB'),
          ),
        ),
      );
    }

    $body     = json_encode(array('orders' => array($order)));
    $response = $this->curlRequest('POST', $base . '/orders', $headers, $body);
    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'API request failed.', 'error');
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['createdOrders'][0])) {
      $msg = $data['message'] ?? $data['httpMessage'] ?? $response;
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . $msg, 'error');
      return false;
    }

    $created  = $data['createdOrders'][0];
    $tracking = $created['trackingNumber'] ?? $created['orderID'] ?? '';
    $labelB64 = $created['label']['content'] ?? $created['label']['pdf'] ?? '';

    if (!$labelB64 || !$tracking) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'No label or tracking number in response.', 'error');
      return false;
    }

    // Save label PDF
    $date      = $sInfo->ship_date;
    $file_path = SHIPPING_DEFAULT_LABEL_DIR . $this->code . '/' . str_replace('-', '/', $date) . '/';
    validate_path($file_path);
    $file_name = $tracking . '.pdf';
    $labelData = base64_decode($labelB64);
    if (!file_put_contents($file_path . $file_name, $labelData)) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'Cannot write label file.', 'error');
      return false;
    }

    return array(array(
      'ref_id'        => $sInfo->purchase_invoice_id,
      'tracking'      => $tracking,
      'zone'          => '',
      'book_cost'     => 0,
      'net_cost'      => 0,
      'delivery_date' => '',
    ));
  }

  // ---------------------------------------------------------------
  //  Helpers
  // ---------------------------------------------------------------
  private function curlRequest($method, $url, $headers, $body = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    if ($method === 'POST') {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error) return false;
    if ($httpCode >= 400) return $result; // return body so caller can extract error message
    return $result;
  }

  private function toGrams($value, $unit) {
    $v = (float)$value;
    return ($unit === 'KGS') ? (int)round($v * 1000) : (int)round($v * 453.592);
  }

  private function toMm($value, $unit) {
    $v = (float)$value;
    return ($unit === 'CM') ? (int)round($v * 10) : (int)round($v * 25.4);
  }

} // end class royal_mail
?>
