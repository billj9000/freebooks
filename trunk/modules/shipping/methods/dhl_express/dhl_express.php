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
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER',        'default' => ''),
      array('key' => 'MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_IMPORT', 'default' => ''),
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

    // Run carrier-specific box packing when item list is available
    $api_packages  = [];
    $rate_packages = [];
    if (!empty($pkg->items)) {
      $pack_result      = BoxPackingService::pack($pkg->items, $this->code);
      $boxes            = BoxPackingService::summarise($pack_result['packed']);
      $weight_only_g    = $pack_result['weight_only_g'];
      $weight_only_skus = $pack_result['weight_only_skus'];
      $unpacked_skus    = array_unique(array_column($pack_result['unpacked'], 'sku'));
      if (!empty($boxes)) {
        foreach ($boxes as $bi => $b) {
          $extra_g  = ($bi === 0) ? $weight_only_g : 0;
          $total_g  = $b['weight_g'] + $extra_g;
          $weightKg = round($total_g / 1000, 3);
          $lenCm    = (int)round($b['length_mm'] / 10);
          $widCm    = (int)round($b['width_mm']  / 10);
          $hgtCm    = (int)round($b['depth_mm']  / 10);
          $p = array('weight' => $weightKg);
          if ($lenCm && $widCm && $hgtCm) {
            $p['dimensions'] = array('length' => $lenCm, 'width' => $widCm, 'height' => $hgtCm);
          }
          $api_packages[]  = $p;
          $rate_packages[] = array(
            'box_code' => $b['box_code'],
            'box_name' => $b['box_name'],
            'qty'      => 1,
            'weight'   => BoxPackingService::fromGrams($total_g),
            'length'   => BoxPackingService::fromMm($b['length_mm']),
            'width'    => BoxPackingService::fromMm($b['width_mm']),
            'height'   => BoxPackingService::fromMm($b['depth_mm']),
            'items'    => $b['items'],
          );
        }
      }
      if (!empty($weight_only_skus)) {
        $messageStack->add(SHIPPING_BOX_PACKER_NO_DIMS . implode(', ', $weight_only_skus), 'caution');
      }
      if (!empty($unpacked_skus)) {
        $messageStack->add(SHIPPING_BOX_PACKER_NOT_FOUND . implode(', ', $unpacked_skus), 'caution');
      }
    }
    // Fallback: no items provided, or no DHL-compatible boxes configured
    if (empty($api_packages)) {
      $totalWeight = $pkg->get_total_weight();
      if ($totalWeight == 0) {
        $messageStack->add(SHIPPING_ERROR_WEIGHT_ZERO, 'error');
        return false;
      }
      $p = array('weight' => $this->toKg($totalWeight, $pkg->pkg_weight_unit));
      $dimUnit = strtoupper(trim($pkg->pkg_dimension_unit ?? 'IN'));
      $factor  = ($dimUnit === 'CM') ? 1.0 : 2.54;
      $lenCm   = (int)round((float)($pkg->pkg_length ?? 0) * $factor);
      $widCm   = (int)round((float)($pkg->pkg_width  ?? 0) * $factor);
      $hgtCm   = (int)round((float)($pkg->pkg_height ?? 0) * $factor);
      if ($lenCm > 0 && $widCm > 0 && $hgtCm > 0) {
        $p['dimensions'] = array('length' => $lenCm, 'width' => $widCm, 'height' => $hgtCm);
      }
      $api_packages[] = $p;
    }

    $apiKey    = defined('MODULE_SHIPPING_DHL_EXPRESS_API_KEY')        ? MODULE_SHIPPING_DHL_EXPRESS_API_KEY        : '';
    $apiSecret = defined('MODULE_SHIPPING_DHL_EXPRESS_API_SECRET')     ? MODULE_SHIPPING_DHL_EXPRESS_API_SECRET     : '';
    $account   = defined('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER') ? MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER : '';
    if (!$apiKey || !$apiSecret) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY, 'caution');
      return array('result' => 'success', 'rates' => array());
    }

    $base     = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    $fromISO2 = !empty($pkg->ship_from_country_iso2) ? $pkg->ship_from_country_iso2
              : (defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB');
    $fromPost = trim($pkg->ship_postal_code ?? (defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : ''));
    $fromCity = trim($pkg->ship_city_town   ?? (defined('COMPANY_CITY_TOWN')   ? COMPANY_CITY_TOWN   : ''));
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

    $packages = $api_packages;

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
      'packages'                   => $packages,
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
        'book'     => $price,
        'quote'    => $price,
        'cost'     => $price,
        'note'     => $deliveryNote,
        'packages' => $rate_packages,
      );
    }

    return array('result' => 'success', 'rates' => $arrRates);
  }

  // ---------------------------------------------------------------
  //  Label generation
  // ---------------------------------------------------------------
  function retrieveLabel($sInfo) {
    global $messageStack;
    $apiKey        = defined('MODULE_SHIPPING_DHL_EXPRESS_API_KEY')               ? MODULE_SHIPPING_DHL_EXPRESS_API_KEY               : '';
    $apiSecret     = defined('MODULE_SHIPPING_DHL_EXPRESS_API_SECRET')            ? MODULE_SHIPPING_DHL_EXPRESS_API_SECRET            : '';
    $accountExport = defined('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER')        ? MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER        : '';
    $accountImport = defined('MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_IMPORT') ? MODULE_SHIPPING_DHL_EXPRESS_ACCOUNT_NUMBER_IMPORT : '';
    if (!$apiKey || !$apiSecret) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_NOKEY, 'error');
      return false;
    }

    $base = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    // ship_method is stored with DHL_ prefix (matches service_levels key); strip it for the API
    $rawMethod   = $sInfo->ship_method;
    $productCode = (strncmp($rawMethod, 'DHL_', 4) === 0) ? substr($rawMethod, 4) : $rawMethod;
    $sc = trim($sInfo->sender_country_code ?? '');
    if ($sc !== '') {
      $fromISO2 = strlen($sc) === 3 ? gen_get_country_iso_2_from_3($sc) : $sc;
    } elseif (!empty($sInfo->vendor_country_code)) {
      $vc = $sInfo->vendor_country_code;
      $fromISO2 = strlen($vc) === 3 ? gen_get_country_iso_2_from_3($vc) : $vc;
    } else {
      $fromISO2 = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
    }
    $companyISO2 = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : '';
    $toISO2      = strlen($sInfo->ship_country_code) == 3
      ? gen_get_country_iso_2_from_3($sInfo->ship_country_code)
      : $sInfo->ship_country_code;
    $isDomestic  = ($fromISO2 === $toISO2);
    $ts       = strtotime($sInfo->ship_date);
    $noonUtc  = gmmktime(12, 0, 0, (int)gmdate('n', $ts), (int)gmdate('j', $ts), (int)gmdate('Y', $ts));
    if ($noonUtc <= time()) $ts = strtotime('+1 day', $ts);
    $shipDate = gmdate('Y-m-d', $ts) . 'T12:00:00GMT+00:00';
    // item values and total_amount are stored in base (home) currency; convert to invoice currency
    $fx = max(1e-9, (float)($sInfo->currencies_value ?? 1));
    if (!empty($sInfo->line_items)) {
      $declaredValue = 0;
      foreach ($sInfo->line_items as $_li) $declaredValue += (float)$_li['value'] * (float)$_li['qty'];
      $declaredValue = round($declaredValue * $fx, 2);
    } else {
      $declaredValue = round((float)($sInfo->customs_value ?: $sInfo->total_value ?: 0) * $fx, 2);
    }

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
    // DHL rejects any contactInformation/postalAddress field that is present but empty.
    $a2  = function($v) { $v = trim($v ?? ''); return $v !== '' ? array('addressLine2' => $v) : array(); };
    $tel = function($v) { $v = trim($v ?? ''); return $v !== '' ? array('phone' => $v)        : array(); };
    $eml = function($v) { $v = trim($v ?? ''); return $v !== '' ? array('email' => $v)        : array(); };

    // Build registration number arrays for shipper and receiver.
    // Sender EORI: prefer form value; fall back to SHIPPING_SHIPPER_EORI constant when the
    // sender is the company itself (fromISO2 matches the configured company country).
    $shipperRegNums = [];
    $senderEori = trim($sInfo->sender_eori_number ?? '');
    if ($senderEori === '' && defined('SHIPPING_SHIPPER_EORI') && SHIPPING_SHIPPER_EORI !== ''
        && $companyISO2 !== '' && $fromISO2 === $companyISO2) {
      $senderEori = SHIPPING_SHIPPER_EORI;
    }
    if ($senderEori !== '') {
      $shipperRegNums[] = array('typeCode' => 'EOR', 'number' => $senderEori, 'issuerCountryCode' => $fromISO2);
    }
    $senderVat = trim($sInfo->sender_vat_number ?? '');
    if ($senderVat === '' && defined('SHIPPING_SHIPPER_VAT') && SHIPPING_SHIPPER_VAT !== ''
        && $companyISO2 !== '' && $fromISO2 === $companyISO2) {
      $senderVat = SHIPPING_SHIPPER_VAT;
    }
    if ($senderVat !== '') {
      $shipperRegNums[] = array('typeCode' => 'VAT', 'number' => $senderVat, 'issuerCountryCode' => $fromISO2);
    }

    $receiverRegNums = [];
    $shipEori = trim($sInfo->ship_eori_number ?? '');
    if ($shipEori !== '') {
      $receiverRegNums[] = array('typeCode' => 'EOR', 'number' => $shipEori, 'issuerCountryCode' => $toISO2);
    }
    $shipVat = trim($sInfo->ship_vat_number ?? '');
    if ($shipVat !== '') {
      $receiverRegNums[] = array('typeCode' => 'VAT', 'number' => $shipVat, 'issuerCountryCode' => $toISO2);
    }

    $body = array(
      'plannedShippingDateAndTime' => $shipDate,
      'pickup'      => array('isRequested' => false),
      'productCode' => $productCode,
      'accounts'    => array(array(
        'typeCode' => 'shipper',
        'number'   => ($companyISO2 !== '' && $fromISO2 === $companyISO2)
          ? $accountExport
          : ($accountImport ?: $accountExport),
      )),
      'customerDetails' => array(
        'shipperDetails' => array(
          'postalAddress' => array(
            'postalCode'   => $sInfo->sender_postal_code   ?? '',
            'cityName'     => $sInfo->sender_city_town     ?? '',
            'countryCode'  => $fromISO2,
            'addressLine1' => $sInfo->sender_address1      ?? '',
          ) + $a2($sInfo->sender_address2 ?? ''),
          'contactInformation' => array(
            'companyName' => $sInfo->sender_primary_name   ?? '',
            'fullName'    => $sInfo->sender_contact ?: ($sInfo->sender_primary_name ?? ''),
          ) + $tel($sInfo->sender_telephone1 ?? '') + $eml($sInfo->sender_email ?? ''),
        ) + ($shipperRegNums ? array('registrationNumbers' => $shipperRegNums) : array()),
        'receiverDetails' => array(
          'postalAddress' => array(
            'postalCode'   => $sInfo->ship_postal_code,
            'cityName'     => $sInfo->ship_city_town,
            'countryCode'  => $toISO2,
            'addressLine1' => $sInfo->ship_address1,
          ) + $a2($sInfo->ship_address2 ?? ''),
          'contactInformation' => array(
            'companyName' => $sInfo->ship_primary_name,
            'fullName'    => $sInfo->ship_contact ?: $sInfo->ship_primary_name,
          ) + $tel($sInfo->ship_telephone1 ?? '') + $eml($sInfo->ship_email ?? ''),
        ) + ($receiverRegNums ? array('registrationNumbers' => $receiverRegNums) : array()),
      ),
      'content' => array(
        'packages'             => $packages,
        'isCustomsDeclarable'  => !$isDomestic,
        'description'          => (!empty($sInfo->line_items) ? ($sInfo->line_items[0]['description'] ?: 'Goods') : ($sInfo->customs_description ?: 'Goods')),
        'declaredValue'        => $declaredValue,
        'declaredValueCurrency'=> $sInfo->currencies_code ?: 'GBP',
        'unitOfMeasurement'    => 'metric',
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

    if (!$isDomestic) {
      $currency     = $sInfo->currencies_code ?: (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP');
      $fallbackCountry = defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB';
      $lineItems  = array();

      if (!empty($sInfo->line_items)) {
        $itemNum = 1;
        foreach ($sInfo->line_items as $item) {
          if ((float)$item['qty'] <= 0) continue;
          $lineItems[] = array(
            'number'           => $itemNum++,
            'description'      => $item['description'] ?: 'Goods',
            'price'            => max(0.01, round((float)$item['value'] * $fx, 2)),
            'priceCurrency'    => $currency,
            'exportReasonType' => $sInfo->customs_reason ?: 'permanent',
            'quantity'         => array('value' => max(1, (int)round((float)$item['qty'])), 'unitOfMeasurement' => 'PCS'),
            'commodityCodes'   => array(
              array('typeCode' => 'outbound', 'value' => $item['hs_code'] ?: ''),
            ),
            'manufacturerCountry' => $item['origin'] ?: $fallbackCountry,
            'weight' => array(
              'netValue'   => round($this->toKg((float)$item['weight'] * (float)$item['qty'], $sInfo->pkg_weight_unit), 3),
              'grossValue' => round($this->toKg((float)$item['weight'] * (float)$item['qty'], $sInfo->pkg_weight_unit), 3),
            ),
          );
        }
      }

      // Fall back to single-line summary when no per-item data is available
      if (empty($lineItems) && !empty($sInfo->customs_description)) {
        $lineItems[] = array(
          'number'           => 1,
          'description'      => $sInfo->customs_description,
          'price'            => max(0.01, (float)$declaredValue),
          'priceCurrency'    => $currency,
          'exportReasonType' => 'permanent',
          'quantity'         => array('value' => 1, 'unitOfMeasurement' => 'PCS'),
          'commodityCodes'   => array(
            array('typeCode' => 'outbound', 'value' => $sInfo->hs_code ?? ''),
          ),
          'manufacturerCountry' => !empty($sInfo->country_of_origin)
            ? gen_get_country_iso_2_from_3($sInfo->country_of_origin)
            : $fallbackCountry,
          'weight' => array(
            'netValue'   => $this->toKg($sInfo->total_weight ?? 0, $sInfo->pkg_weight_unit),
            'grossValue' => $this->toKg($sInfo->total_weight ?? 0, $sInfo->pkg_weight_unit),
          ),
        );
      }

      if (!empty($lineItems)) {
        $body['content']['exportDeclaration'] = array(
          'lineItems'    => $lineItems,
          'invoice'      => array(
            'number'        => $sInfo->purchase_invoice_id,
            'date'          => $sInfo->ship_date,
            'signatureName' => defined('AR_CONTACT_NAME') ? AR_CONTACT_NAME : '',
          ),
          'exportReason' => $sInfo->customs_reason ?: 'permanent',
        );
      }
    }

    $bodyJson = json_encode($body);
    $response = $this->curlRequest('POST', $base . '/shipments',
      $this->dhlHeaders(), $bodyJson, $apiKey, $apiSecret);

    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'API request failed.', 'error');
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['shipmentTrackingNumber'])) {
      $parts = array_filter([
        $data['title']  ?? '',
        $data['detail'] ?? '',
        !empty($data['additionalDetails']) && is_array($data['additionalDetails'])
          ? implode('; ', $data['additionalDetails']) : '',
      ]);
      $msg = implode(' — ', $parts) ?: $response;
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . $msg, 'error');
      return false;
    }

    $tracking  = $data['shipmentTrackingNumber'];
    $file_path = SHIPPING_DEFAULT_LABEL_DIR . $this->code . '/' . str_replace('-', '/', $sInfo->ship_date) . '/';
    validate_path($file_path);

    // DHL returns label documents at the root level; packages[] has tracking numbers but no documents
    $results  = array();
    $labelIdx = 0;
    foreach ($data['documents'] ?? array() as $doc) {
      if (($doc['typeCode'] ?? '') !== 'label') continue;
      $labelData = base64_decode($doc['content'] ?? '');
      if (!$labelData) continue;
      $labelTracking = $tracking . ($labelIdx > 0 ? '-' . $labelIdx : '');
      if (!file_put_contents($file_path . $labelTracking . '.pdf', $labelData)) {
        $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'Cannot write label file.', 'error');
        return false;
      }
      $results[] = array(
        'ref_id'        => $sInfo->purchase_invoice_id . ($labelIdx > 0 ? '-' . ($labelIdx + 1) : ''),
        'tracking'      => $labelTracking,
        'zone'          => '',
        'book_cost'     => 0,
        'net_cost'      => 0,
        'delivery_date' => $data['estimatedDeliveryTime'] ?? '',
      );
      $labelIdx++;
    }

    if (empty($results)) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'No label documents in API response.', 'error');
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
    } elseif ($method !== 'GET') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);
    return $error ? false : $result;
  }

  public function cancelShipment($tracking) {
    global $messageStack;
    $apiKey   = MODULE_SHIPPING_DHL_EXPRESS_API_KEY;
    $apiSecret= MODULE_SHIPPING_DHL_EXPRESS_API_SECRET;
    $base     = (MODULE_SHIPPING_DHL_EXPRESS_TEST_MODE == 'Production') ? DHL_API_BASE_LIVE : DHL_API_BASE_TEST;
    $response = $this->curlRequest('DELETE', $base . '/shipments/' . urlencode($tracking),
      $this->dhlHeaders(), null, $apiKey, $apiSecret);
    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . 'Cancellation request failed.', 'caution');
      return false;
    }
    // 200 with empty body or a confirmation object both indicate success
    $data = json_decode($response, true);
    if (!empty($data['title']) && stripos($data['title'], 'error') !== false) {
      $messageStack->add(MODULE_SHIPPING_DHL_EXPRESS_ERROR_LABEL . ($data['detail'] ?? $data['title']), 'caution');
      return false;
    }
    return true;
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
