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
//  Path: /modules/payment/ajax/sq_term_pair_start.php
//
// Creates a Square device code for Terminal API pairing.
// Returns: { code_id, code } on success, { error } on failure.

/**************   Check user security   *****************************/
// Pairing a terminal is payment method configuration: full level.
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
if ($security_level < 4) {
  echo json_encode(['error' => ERROR_NO_PERMISSION]);
  die;
}
// It changes the account's devices: only as a POST, which index.php has
// checked for its csrf_token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['error' => ERROR_NO_PERMISSION]);
  die;
}
/**************   Validate configuration   **************************/
$token    = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
$location = defined('MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID')  ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID)  : '';
$sandbox  = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX')      && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';

if (!$token || !$location) {
  echo json_encode(['error' => 'Access token and location ID must be saved before pairing.']);
  die;
}

/**************   Call Square Devices API   *************************/
$base_url = $sandbox
  ? 'https://connect.squareupsandbox.com/v2/'
  : 'https://connect.squareup.com/v2/';

$payload = json_encode([
  'idempotency_key' => bin2hex(random_bytes(16)),
  'device_code'     => [
    'name'         => 'FreeBooks Terminal',
    'product_type' => 'TERMINAL_API',
    'location_id'  => $location,
  ],
]);

$ch = curl_init($base_url . 'devices/codes');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Square-Version: 2024-01-17',
  ],
  CURLOPT_TIMEOUT        => 15,
]);
$body     = curl_exec($ch);
$curl_err = curl_error($ch);

if ($curl_err) {
  echo json_encode(['error' => 'Network error: ' . $curl_err]);
  die;
}

$data = json_decode($body, true);
if (isset($data['errors'])) {
  $msg = $data['errors'][0]['detail'] ?? 'Square API error.';
  echo json_encode(['error' => $msg]);
  die;
}

$code_id = $data['device_code']['id']   ?? '';
$code    = $data['device_code']['code'] ?? '';

if (!$code_id || !$code) {
  echo json_encode(['error' => 'Square did not return a pairing code.']);
  die;
}

echo json_encode(['code_id' => $code_id, 'code' => $code]);
die;
?>
