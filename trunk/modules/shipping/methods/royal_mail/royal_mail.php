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

// Royal Mail Click & Drop REST API
// Auth: Authorization: Bearer {API key} (obtain from Click & Drop → Settings → API)
define('RM_CD_BASE', 'https://api.parcel.royalmail.com/api/v1');

// Tracking URL — append tracking number to build a full tracking link
define('royal_mail_TRACKING_URL', 'https://www.royalmail.com/track-your-item#/tracking-results/');

// Service display names — keyed as royal_mail_{internal_code}.
// pre_process.php filters service_levels to only show codes with a defined constant here.
// Royal Mail domestic — untracked
define('royal_mail_CRL24', 'Royal Mail 24');
define('royal_mail_CRL48', 'Royal Mail 48');
// Royal Mail domestic — tracked
define('royal_mail_RM24',  'Tracked 24');
define('royal_mail_RM48',  'Tracked 48');
define('royal_mail_RMSD9', 'Special Delivery 9am');
define('royal_mail_RMSD1', 'Special Delivery 1pm');
define('royal_mail_RM1CL', 'Signed For 1st Class');
define('royal_mail_RM2CL', 'Signed For 2nd Class');
// Parcelforce domestic (via Click & Drop)
define('royal_mail_NDA',   'Parcelforce Express 24');
define('royal_mail_FE0',   'Parcelforce Express 48');
define('royal_mail_FEM',   'Parcelforce Express 48 Large');
// Royal Mail international
define('royal_mail_RMIT',  'International Business Mail Tracked');
define('royal_mail_RMIS',  'International Business Mail Signed');
define('royal_mail_RMITS', 'International Business Mail Tracked & Signed');
define('royal_mail_RMIE',  'International Business Parcel Standard');
define('royal_mail_RMIPT', 'International Business Parcel Tracked');
// Parcelforce international (via Click & Drop)
define('royal_mail_ER1',   'Parcelforce Europriority');
define('royal_mail_ECA',   'Parcelforce GlobalPriority');

class royal_mail {

  // Internal code → Click & Drop API serviceCode.
  // Actual values depend on your OBA account — verify in Click & Drop Settings → Services.
  var $CDServiceMap = array(
    // Royal Mail domestic — untracked
    'CRL24' => 'CRL24',
    'CRL48' => 'CRL48',
    // Royal Mail domestic — tracked
    'RM24'  => 'TPN24',
    'RM48'  => 'TPS48',
    'RMSD9' => 'SD4',
    'RMSD1' => 'SD1',
    'RM1CL' => 'STL1',
    'RM2CL' => 'STL2',
    // Parcelforce domestic
    'NDA'   => 'NDA',
    'FE0'   => 'FE0',
    'FEM'   => 'FEM',
    // Royal Mail international
    'RMIT'  => 'MTK',
    'RMIS'  => 'MTM',
    'RMITS' => 'MTG',
    'RMIE'  => 'MP9',
    'RMIPT' => 'OTA',
    // Parcelforce international
    'ER1'   => 'ER1',
    'ECA'   => 'ECA',
  );

  var $intlServices = array('RMIT', 'RMIS', 'RMITS', 'RMIE', 'RMIPT', 'ER1', 'ECA');

  // Click & Drop rejects packageFormatIdentifier values that don't match the service's
  // mail class (e.g. "Package size 'Parcel' is not valid for the service 'MTK'") — this
  // restriction isn't in the public API schema, only surfaced by that runtime error.
  // Business Mail (RMIT/RMIS/RMITS) is letter-post; everything else here is parcel-post.
  const FORMATS_BY_SERVICE = array(
    'RMIT'  => array('letter', 'largeLetter', 'documents'),
    'RMIS'  => array('letter', 'largeLetter', 'documents'),
    'RMITS' => array('letter', 'largeLetter', 'documents'),
    'RMIE'  => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
    'RMIPT' => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
    'NDA'   => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
    'FE0'   => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
    'FEM'   => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
    'ER1'   => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
    'ECA'   => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
  );
  const ALL_FORMATS = array('letter', 'largeLetter', 'smallParcel', 'mediumParcel', 'largeParcel', 'parcel', 'documents');

  static function allowedFormats($internalCode) {
    return self::FORMATS_BY_SERVICE[$internalCode] ?? self::ALL_FORMATS;
  }

  function __construct() {
    $this->code = 'royal_mail';
  }

  function keys() {
    return array(
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_TITLE',      'default' => 'Royal Mail'),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_API_KEY',    'default' => ''),
      array('key' => 'MODULE_SHIPPING_ROYAL_MAIL_SORT_ORDER', 'default' => '40'),
    );
  }

  function configure($key) {
    return html_input_field(strtolower($key), constant($key), '');
  }

  function update() {
    foreach ($this->keys() as $key) {
      if (isset($_POST[strtolower($key['key'])])) {
        write_configure($key['key'], $_POST[strtolower($key['key'])]);
      }
    }
  }

  // ---------------------------------------------------------------
  //  Shipping estimator — RM has no live rates API; return service list with blank pricing
  // ---------------------------------------------------------------
  function quote($pkg = '') {
    $rates = array();
    foreach (array_keys($this->CDServiceMap) as $code) {
      if (defined('royal_mail_' . $code)) {
        $rates[$this->code][$code] = array('quote' => 0, 'book' => '', 'cost' => '');
      }
    }
    return array('result' => 'success', 'rates' => $rates);
  }

  // ---------------------------------------------------------------
  //  Label generation — two-step: POST /orders then GET /orders/{id}/label
  // ---------------------------------------------------------------
  function retrieveLabel($sInfo) {
    global $messageStack;

    $apiKey = defined('MODULE_SHIPPING_ROYAL_MAIL_API_KEY') ? MODULE_SHIPPING_ROYAL_MAIL_API_KEY : '';
    if (!$apiKey) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_NOKEY, 'error');
      return false;
    }

    // Map internal service code to Click & Drop API serviceCode
    $internalCode = $sInfo->ship_method ?? '';
    $serviceCode  = $this->CDServiceMap[$internalCode] ?? $internalCode;
    $isDomestic   = !in_array($internalCode, $this->intlServices);

    // Also detect domestic by country match (covers manual service code entry)
    $sc       = trim($sInfo->sender_country_code ?? '');
    $fromISO2 = $sc !== ''
      ? (strlen($sc) === 3 ? gen_get_country_iso_2_from_3($sc) : $sc)
      : (defined('COMPANY_COUNTRY') ? gen_get_country_iso_2_from_3(COMPANY_COUNTRY) : 'GB');
    $toCC     = trim($sInfo->ship_country_code ?? '');
    $toISO2   = $toCC !== ''
      ? (strlen($toCC) === 3 ? gen_get_country_iso_2_from_3($toCC) : $toCC)
      : '';
    if ($fromISO2 !== '' && $toISO2 !== '' && $fromISO2 === $toISO2) $isDomestic = true;

    // Despatch date
    $ts           = strtotime($sInfo->ship_date ?? 'today') ?: time();
    $despatchDate = date('Y-m-d', $ts);

    // Declared value in invoice currency
    $fx = max(1e-9, (float)($sInfo->currencies_value ?? 1));
    if (!empty($sInfo->line_items)) {
      $goodsValue = 0;
      foreach ($sInfo->line_items as $li) $goodsValue += (float)$li['value'] * (float)$li['qty'];
      $goodsValue = round($goodsValue * $fx, 2);
    } else {
      $goodsValue = round((float)($sInfo->customs_value ?: $sInfo->total_value ?: 0) * $fx, 2);
    }

    // Packages — weight in grams + optional format identifier
    $allowedFormats = self::allowedFormats($internalCode);
    $packages = array();
    foreach ($sInfo->package as $pkg) {
      $entry = array(
        'weightInGrams' => $this->toGrams($pkg['weight'] ?? 0, $sInfo->pkg_weight_unit ?? 'KGS'),
      );
      $fmt = trim($pkg['format'] ?? '');
      if ($fmt && $fmt !== 'undefined') {
        if (!in_array($fmt, $allowedFormats)) {
          $messageStack->add(sprintf(MODULE_SHIPPING_ROYAL_MAIL_ERROR_BAD_FORMAT,
            $fmt, $serviceCode, implode(', ', $allowedFormats)), 'error');
          return false;
        }
        $entry['packageFormatIdentifier'] = $fmt;
      }
      $packages[] = $entry;
    }
    if (empty($packages)) {
      $messageStack->add(SHIPPING_NO_PACKAGES, 'error');
      return false;
    }

    // Customs line item contents for international shipments
    $contents = array();
    if (!$isDomestic && !empty($sInfo->line_items)) {
      foreach ($sInfo->line_items as $item) {
        if ((float)($item['qty'] ?? 0) <= 0) continue;
        $originCC = trim($item['origin'] ?? '');
        if ($originCC && strlen($originCC) === 3) $originCC = gen_get_country_iso_2_from_3($originCC);
        $contents[] = array(
          'name'                       => $item['description'] ?: 'Goods',
          'SKU'                        => $item['sku'] ?? '',
          'quantity'                   => max(1, (int)round((float)$item['qty'])),
          'unitValue'                  => max(0.01, round((float)$item['value'] * $fx, 2)),
          'unitWeightInGrams'          => $this->toGrams((float)($item['weight'] ?? 0), $sInfo->pkg_weight_unit ?? 'KGS'),
          'customsCode'                => $item['hs_code'] ?? '',
          'originCountryCode'          => $originCC ?: $fromISO2,
          'customsDescription'         => substr($item['description'] ?: 'Goods', 0, 50),
          'customsDeclarationCategory' => $sInfo->customs_declaration_category ?? 'saleOfGoods',
        );
      }
    }

    $currency      = $sInfo->currencies_code ?: (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP');
    $recipientEori = trim($sInfo->ship_eori_number ?? '');
    $recipientVat  = trim($sInfo->ship_vat_number  ?? '');
    $ioss          = (!$isDomestic && defined('SHIPPING_SHIPPER_IOSS') && SHIPPING_SHIPPER_IOSS)
                     ? SHIPPING_SHIPPER_IOSS : '';

    // Build recipient object — address fields are nested inside 'address' per API spec
    $address = array(
      'addressLine1' => $sInfo->ship_address1 ?? '',
      'city'         => $sInfo->ship_city_town ?? '',
      'countryCode'  => $toISO2,
      'postcode'     => $sInfo->ship_postal_code ?? '',
      'fullName'     => $sInfo->ship_contact ?: ($sInfo->ship_primary_name ?? ''),
      'companyName'  => $sInfo->ship_primary_name ?? '',
    );
    if (trim($sInfo->ship_address2 ?? '') !== '') $address['addressLine2'] = $sInfo->ship_address2;
    $recipient = array('address' => $address);
    if (trim($sInfo->ship_telephone1 ?? '') !== '') $recipient['phoneNumber']  = $sInfo->ship_telephone1;
    if (trim($sInfo->ship_email      ?? '') !== '') $recipient['emailAddress'] = $sInfo->ship_email;

    // Contents (customs line items) belong inside the package, not on the order
    if (!empty($contents) && !empty($packages)) {
      $packages[0]['contents'] = $contents;
    }

    // postageDetails: service code + all optional fields that the API spec places here
    $postageDetails = array('serviceCode' => $serviceCode);
    if (!$isDomestic && $recipientEori) $postageDetails['recipientEoriNumber']    = $recipientEori;
    if ($ioss)                          $postageDetails['IOSSNumber']             = $ioss;
    if (!$isDomestic && !empty($sInfo->purchase_invoice_id)) {
      $postageDetails['commercialInvoiceNumber'] = $sInfo->purchase_invoice_id;
      $postageDetails['commercialInvoiceDate']   = $despatchDate;
    }

    // Billing address — required for UK orders; use sender (company) details
    $billing = array('address' => array(
      'fullName'     => $sInfo->sender_primary_name ?? '',
      'companyName'  => $sInfo->sender_primary_name ?? '',
      'addressLine1' => $sInfo->sender_address1     ?? '',
      'city'         => $sInfo->sender_city_town    ?? '',
      'postcode'     => $sInfo->sender_postal_code  ?? '',
      'countryCode'  => $fromISO2,
    ));
    if (trim($sInfo->sender_address2 ?? '') !== '') $billing['address']['addressLine2'] = $sInfo->sender_address2;

    $order = array(
      'orderDate'           => gmdate('Y-m-d\TH:i:s\Z'),
      'orderReference'      => $sInfo->purchase_invoice_id ?? '',
      'currencyCode'        => $currency,
      'subtotal'            => $goodsValue,
      'shippingCostCharged' => 0,
      'total'               => $goodsValue,
      'recipient'           => $recipient,
      'billing'             => $billing,
      'packages'            => $packages,
      'postageDetails'      => $postageDetails,
    );

    if (!$isDomestic && $recipientVat) {
      $order['importer'] = array('vatNumber' => $recipientVat);
      if ($recipientEori) $order['importer']['eoriNumber'] = $recipientEori;
    }

    // Step 1: POST /orders — body is CreateOrdersRequest: {"items":[...]}
    $body = array('items' => array($order));
    $bodyJson = json_encode($body);
    if ($bodyJson === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'JSON encode failed: ' . json_last_error_msg(), 'error');
      return false;
    }
    $response = $this->curlRequest('POST', RM_CD_BASE . '/orders',
      $this->rmHeaders($apiKey), $bodyJson);

    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'API request failed.', 'error');
      return false;
    }

    $data          = json_decode($response, true);
    $createdOrders = $data['createdOrders'] ?? array();
    if (empty($createdOrders)) {
      $failedMsg = $data['failedOrders'][0]['errors'][0]['errorMessage']
                ?? ($data['failedOrders'][0]['errors'][0]['message']
                ?? ($data['message'] ?? substr($response, 0, 300)));
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . $failedMsg, 'error');
      return false;
    }

    $orderIdentifier = $createdOrders[0]['orderIdentifier'] ?? '';
    $trackingNumber  = $createdOrders[0]['trackingNumber']
                    ?? ($createdOrders[0]['packages'][0]['trackingNumber'] ?? '');
    if (!$orderIdentifier) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'No order identifier returned.', 'error');
      return false;
    }

    // Step 2: GET /orders/{id}/label — returns raw PDF
    $includeReturns = !empty($sInfo->include_returns_label) ? 'true' : 'false';
    $labelParams    = 'documentType=postageLabel&includeReturnsLabel=' . $includeReturns;
    if (!$isDomestic) $labelParams .= '&includeCN=true';
    $labelHeaders   = $this->rmHeaders($apiKey);
    $labelHeaders[] = 'Accept: application/pdf';
    $labelResponse  = $this->curlRequest('GET',
      RM_CD_BASE . '/orders/' . urlencode($orderIdentifier) . '/label?' . $labelParams,
      $labelHeaders);

    if ($labelResponse === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'Label download failed.', 'error');
      return false;
    }
    if (substr($labelResponse, 0, 4) !== '%PDF') {
      $errData = json_decode($labelResponse, true);
      $errMsg  = $errData['message'] ?? substr($labelResponse, 0, 200);
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . $errMsg, 'error');
      return false;
    }

    // Step 3: GET /orders/{id} to retrieve the tracking number assigned during label printing
    $orderData = $this->curlRequest('GET',
      RM_CD_BASE . '/orders/' . urlencode($orderIdentifier),
      $this->rmHeaders($apiKey));
    if ($orderData !== false) {
      $ord = json_decode($orderData, true);
      $trackingNumber = $ord[0]['trackingNumber']
                     ?? ($ord[0]['packages'][0]['trackingNumber'] ?? $trackingNumber);
    }

    $file_path = SHIPPING_DEFAULT_LABEL_DIR . $this->code . '/' . str_replace('-', '/', $sInfo->ship_date) . '/';
    validate_path($file_path);
    $labelFile = ($trackingNumber ?: $orderIdentifier) . '.pdf';
    if (!file_put_contents($file_path . $labelFile, $labelResponse)) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'Cannot write label file.', 'error');
      return false;
    }

    return array(array(
      'ref_id'        => $sInfo->purchase_invoice_id,
      'tracking'      => $trackingNumber ?: $orderIdentifier,
      // Click & Drop's own order id — always required by cancelShipment()'s
      // DELETE /orders/{id} call, which does not accept a tracking number.
      'order_id'      => $orderIdentifier,
      'zone'          => '',
      'book_cost'     => 0,
      'net_cost'      => 0,
      'delivery_date' => '',
    ));
  }

  // ---------------------------------------------------------------
  //  Cancel an order by its Click & Drop orderIdentifier
  // ---------------------------------------------------------------
  public function cancelShipment($orderIdentifier) {
    global $messageStack;
    $apiKey   = defined('MODULE_SHIPPING_ROYAL_MAIL_API_KEY') ? MODULE_SHIPPING_ROYAL_MAIL_API_KEY : '';
    $response = $this->curlRequest('DELETE',
      RM_CD_BASE . '/orders/' . urlencode($orderIdentifier),
      $this->rmHeaders($apiKey));
    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'Cancellation failed.', 'caution');
      return false;
    }
    return true;
  }

  // ---------------------------------------------------------------
  //  Manifest all eligible orders (end-of-day closure with Royal Mail)
  // ---------------------------------------------------------------
  public function manifestOrders() {
    global $messageStack;
    $apiKey   = defined('MODULE_SHIPPING_ROYAL_MAIL_API_KEY') ? MODULE_SHIPPING_ROYAL_MAIL_API_KEY : '';
    $response = $this->curlRequest('POST', RM_CD_BASE . '/manifests',
      $this->rmHeaders($apiKey));
    if ($response === false) {
      $messageStack->add(MODULE_SHIPPING_ROYAL_MAIL_ERROR_LABEL . 'Manifest request failed.', 'error');
      return false;
    }
    $data = json_decode($response, true);
    return $data['manifestIdentifier'] ?? true;
  }

  // ---------------------------------------------------------------
  //  Helpers
  // ---------------------------------------------------------------
  private function rmHeaders($apiKey) {
    return array(
      'Authorization: Bearer ' . $apiKey,
      'Accept: application/json',
      'Content-Type: application/json',
    );
  }

  private function curlRequest($method, $url, $headers, $body = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    if ($method === 'POST') {
      curl_setopt($ch, CURLOPT_POST, true);
      if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    } elseif ($method !== 'GET') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    return $error ? false : $result;
  }

  private function toGrams($value, $unit) {
    $v = (float)$value;
    return (strtoupper($unit) === 'KGS') ? (int)round($v * 1000) : (int)round($v * 453.592);
  }

} // end class royal_mail
?>
