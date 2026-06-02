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
//  Path: /modules/payment/ajax/sq_term_pair_status.php
//
// Polls a Square device code for pairing status.
// Returns: { status, device_id } on success, { error } on failure.
// status values: UNPAIRED, PAIRED, EXPIRED, UNKNOWN

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************   Validate input   **********************************/
$code_id = isset($_GET['code_id']) ? trim($_GET['code_id']) : '';
if (!$code_id || !preg_match('/^[A-Za-z0-9_\-]+$/', $code_id)) {
  echo json_encode(['error' => 'Invalid code_id.']);
  die;
}
/**************   Validate configuration   **************************/
$token   = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
$sandbox = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX')      && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';

if (!$token) {
  echo json_encode(['error' => 'Access token not configured.']);
  die;
}

/**************   Call Square Devices API   *************************/
$base_url = $sandbox
  ? 'https://connect.squareupsandbox.com/v2/'
  : 'https://connect.squareup.com/v2/';

$ch = curl_init($base_url . 'devices/codes/' . urlencode($code_id));
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Square-Version: 2024-01-17',
  ],
  CURLOPT_TIMEOUT        => 10,
]);
$body     = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

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

$status              = $data['device_code']['status']    ?? 'UNKNOWN';
$installation_id     = $data['device_code']['device_id'] ?? '';

if ($status === 'EXPIRED') {
  echo json_encode(['error' => 'Pairing code expired. Please click Pair New Terminal to try again.']);
  die;
}

if ($status !== 'PAIRED' || !$installation_id) {
  echo json_encode(['status' => $status, 'device_id' => '']);
  die;
}

// The device_codes endpoint returns a DEVICE_INSTALLATION_ID:uuid value, not the
// serial-number device ID the Terminal Checkout API requires. Look up the device
// in the Devices API to get the correct ID.
$device_id = '';
$ch2 = curl_init($base_url . 'devices/' . urlencode($installation_id));
curl_setopt_array($ch2, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Square-Version: 2024-01-17',
  ],
  CURLOPT_TIMEOUT        => 10,
]);
$body2 = curl_exec($ch2);
curl_close($ch2);

if ($body2) {
  $d2 = json_decode($body2, true);
  // Devices API returns { device: { id: "device:SERIAL", serial_number: "SERIAL", ... } }
  // Use serial_number directly — the "device:" prefix on id is not accepted by Terminal API.
  $device_id = $d2['device']['serial_number'] ?? ($d2['device']['id'] ?? '');
}

// Fall back to the raw installation_id if the lookup failed; the admin can
// correct it manually using the Browse Devices button if needed.
if (!$device_id) {
  $device_id = $installation_id;
}

// Strip any "device:" prefix that may have come through.
if (strncmp($device_id, 'device:', 7) === 0) $device_id = substr($device_id, 7);

echo json_encode(['status' => $status, 'device_id' => $device_id]);
die;
?>
