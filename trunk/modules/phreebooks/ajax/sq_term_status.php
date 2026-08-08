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
//  Path: /modules/phreebooks/ajax/sq_term_status.php
//
// Polls a Square Terminal checkout and returns its current status.
// Called every 3 seconds by the browser until COMPLETED or CANCELED.

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************   Validate input   **********************************/
$checkout_id = isset($_GET['checkout_id']) ? trim($_GET['checkout_id']) : '';
if (!$checkout_id) {
  echo json_encode(['error' => 'Missing checkout_id.']);
  die;
}

// Basic sanity check — Square checkout IDs are alphanumeric with hyphens/underscores.
if (!preg_match('/^[A-Za-z0-9_\-]+$/', $checkout_id)) {
  echo json_encode(['error' => 'Invalid checkout_id.']);
  die;
}

/**************   Validate configuration   **************************/
$token   = defined('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN') ? trim(MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN) : '';
$sandbox = defined('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX')      && MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX === 'Test';

if (!$token) {
  echo json_encode(['error' => 'Square Terminal access token not configured.']);
  die;
}

/**************   Call Square Terminal API   ************************/
$base_url = $sandbox
  ? 'https://connect.squareupsandbox.com/v2/'
  : 'https://connect.squareup.com/v2/';

$ch = curl_init($base_url . 'terminals/checkouts/' . urlencode($checkout_id));
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

if ($curl_err) {
  echo json_encode(['error' => 'Network error communicating with Square: ' . $curl_err]);
  die;
}

$data = json_decode($body, true);
if (isset($data['errors'])) {
  $msg = isset($data['errors'][0]['detail']) ? $data['errors'][0]['detail'] : 'Square API error.';
  echo json_encode(['error' => $msg]);
  die;
}

$status     = $data['checkout']['status']      ?? 'PENDING';
$payment_id = $data['checkout']['payment_ids'][0] ?? '';

echo json_encode(['status' => $status, 'payment_id' => $payment_id]);
die;
?>
