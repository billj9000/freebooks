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
//  Path: /modules/payment/ajax/sq_term_list_devices.php
//
// Returns all Terminal API devices on the Square account.
// Used to help the admin find the correct Device ID.
// Returns: { devices: [ {id, serial_number, name, model, status} ] } or { error }

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************   Validate configuration   **************************/
$token    = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
$sandbox  = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX')      && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';

if (!$token) {
  echo json_encode(['error' => 'Access token must be saved before listing devices.']);
  die;
}

/**************   Call Square Devices API   *************************/
$base_url = $sandbox
  ? 'https://connect.squareupsandbox.com/v2/'
  : 'https://connect.squareup.com/v2/';

$ch = curl_init($base_url . 'devices');
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

$raw_devices = $data['devices'] ?? [];
$out = [];
foreach ($raw_devices as $d) {
  $raw_id = $d['id'] ?? '';
  if (strncmp($raw_id, 'device:', 7) === 0) $raw_id = substr($raw_id, 7);
  $out[] = [
    'id'            => $raw_id,
    'serial_number' => $d['serial_number'] ?? '',
    'name'          => $d['device_detail']['name']   ?? ($d['attributes']['name'] ?? ''),
    'model'         => $d['device_detail']['model']  ?? ($d['attributes']['model'] ?? ''),
    'status'        => $d['status']['category'] ?? '',
    'raw'           => $d,
  ];
}

// Also fetch device codes so we can see what pairings exist.
$ch2 = curl_init($base_url . 'devices/codes');
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
$codes_data = $body2 ? json_decode($body2, true) : [];

echo json_encode([
  'devices'      => $out,
  'devices_count' => count($raw_devices),
  'device_codes' => $codes_data['device_codes'] ?? [],
  'device_codes_errors' => $codes_data['errors'] ?? [],
]);
die;
?>
