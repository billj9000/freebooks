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
//  Path: /modules/payment/ajax/sq_term_unpair.php
//
// Deletes all PAIRED Square Terminal API device codes, unlinking the terminal
// from this application and returning it to the pairing screen.
// Returns: { deleted: N } or { error }

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************   Validate configuration   **************************/
$token   = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
$sandbox = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX')      && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';

if (!$token) {
  echo json_encode(['error' => 'Access token not configured.']);
  die;
}

$base_url = $sandbox
  ? 'https://connect.squareupsandbox.com/v2/'
  : 'https://connect.squareup.com/v2/';

$headers = [
  'Authorization: Bearer ' . $token,
  'Content-Type: application/json',
  'Square-Version: 2024-01-17',
];

/**************   Fetch all device codes   **************************/
$ch = curl_init($base_url . 'devices/codes?product_type=TERMINAL_API');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => $headers,
  CURLOPT_TIMEOUT        => 10,
]);
$body     = curl_exec($ch);
$curl_err = curl_error($ch);

if ($curl_err) {
  echo json_encode(['error' => 'Network error: ' . $curl_err]);
  die;
}

$data = json_decode($body, true);
if (isset($data['errors'])) {
  echo json_encode(['error' => $data['errors'][0]['detail'] ?? 'Square API error.']);
  die;
}

/**************   Delete each PAIRED code   *************************/
$deleted = 0;
foreach (($data['device_codes'] ?? []) as $code) {
  if (($code['status'] ?? '') !== 'PAIRED') continue;
  $ch2 = curl_init($base_url . 'devices/codes/' . urlencode($code['id']));
  curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'DELETE',
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 10,
  ]);
  curl_exec($ch2);
  $deleted++;
}

echo json_encode(['deleted' => $deleted]);
die;
?>
