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
//  Path: /modules/phreebooks/ajax/sq_term_create.php
//
// Creates a Square Terminal checkout and returns the checkout_id to the
// browser so JavaScript can begin polling for completion.

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************   Validate configuration   **************************/
$token     = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
$location  = defined('MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID')  ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID)  : '';
$device_id = defined('MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID')    ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID)    : '';
$sandbox   = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX')      && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';

if (!$token || !$location || !$device_id) {
  echo json_encode(['error' => 'Square Terminal is not fully configured (token/location/device).']);
  die;
}

/**************   Build the charge amount   *************************/
$raw_amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
if ($raw_amount <= 0) {
  echo json_encode(['error' => 'Invalid amount.']);
  die;
}

$allowed_payment_types = ['CARD_PRESENT', 'MANUAL_CARD_ENTRY'];
$payment_type = isset($_GET['payment_type']) ? trim($_GET['payment_type']) : 'CARD_PRESENT';
if (!in_array($payment_type, $allowed_payment_types)) $payment_type = 'CARD_PRESENT';

// The currency the entry settles in, which is not always the company's: a
// receipt against a USD invoice is a USD charge (issue #63). Taken from the
// page but never trusted from it - a code the currencies table does not carry
// is a charge Square would reject, or worse accept at some other rate - so it
// is checked against what this company actually has and falls back home.
$charge_currency = isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : '';
if ($charge_currency === '' || !isset($currencies->currencies[$charge_currency])) {
  $charge_currency = DEFAULT_CURRENCY;
}

// Convert to smallest currency unit (cents for 2-decimal currencies). The
// decimals are the CHARGE currency's: JPY has none, and 100 yen sent as 10000
// is a charge a hundred times too big.
$decimal_places = isset($currencies->currencies[$charge_currency]['decimal_places'])
  ? (int)$currencies->currencies[$charge_currency]['decimal_places']
  : 2;
$amount_minor = intval(round($raw_amount * pow(10, $decimal_places)));

/**************   Call Square Terminal API   ************************/
$base_url = $sandbox
  ? 'https://connect.squareupsandbox.com/v2/'
  : 'https://connect.squareup.com/v2/';

$payload = json_encode([
  'idempotency_key' => bin2hex(random_bytes(16)),
  'checkout'        => [
    'amount_money'   => [
      'amount'   => $amount_minor,
      'currency' => $charge_currency,
    ],
    'payment_type'   => $payment_type,
    'device_options' => [
      'device_id' => $device_id,
    ],
    'location_id'    => $location,
  ],
]);

$ch = curl_init($base_url . 'terminals/checkouts');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Square-Version: 2024-01-17',
  ],
  CURLOPT_TIMEOUT        => 20,
]);
$body     = curl_exec($ch);
$curl_err = curl_error($ch);

if ($curl_err) {
  echo json_encode(['error' => 'Network error communicating with Square: ' . $curl_err]);
  die;
}

$data = json_decode($body, true);
if (isset($data['errors'])) {
  $e = $data['errors'][0];
  $msg = ($e['category'] ?? '') . '/' . ($e['code'] ?? '') . ': ' . ($e['detail'] ?? 'Square API error.');
  echo json_encode(['error' => $msg, 'errors' => $data['errors']]);
  die;
}

$checkout_id = $data['checkout']['id'] ?? '';
if (!$checkout_id) {
  echo json_encode(['error' => 'Square did not return a checkout ID.']);
  die;
}

echo json_encode(['checkout_id' => $checkout_id]);
die;
?>
