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
//  Path: /modules/phreedom/ajax/passkey_register_finish.php
//
// Step 2 of registering a passkey: verify the browser's attestation
// response against the challenge from passkey_register_begin.php and save
// the credential against the current user.
$security_level = validate_ajax_user(SECURITY_ID_MY_PROFILE);

gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_MODULES . 'phreedom/functions/webauthn.php');

header('Content-Type: application/json');

if (!wa_enabled()) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_DISABLED));
  die;
}

$admin_id       = (int)$_SESSION['admin_id'];
$challenge      = wa_consume_challenge('register');
$client_data    = $_POST['client_data'] ?? '';
$attestation    = $_POST['attestation_object'] ?? '';
$label          = trim(db_prepare_input($_POST['label'] ?? ''));
if ($label === '') $label = sprintf(TEXT_PASSKEY_DEFAULT_LABEL, date(DATE_FORMAT));

if (!$challenge || !$client_data || !$attestation) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_SERVER));
  die;
}

try {
  $webAuthn = wa_new_instance();
  $data = $webAuthn->processCreate(
    wa_b64url_decode($client_data),
    wa_b64url_decode($attestation),
    $challenge,
    false, // requireUserVerification - registration was requested with 'preferred'
    true,  // requireUserPresent
    true,  // failIfRootMismatch - moot, no root CAs configured
    true   // requireCtsProfileMatch
  );
} catch (\Throwable $e) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_ATTESTATION));
  die;
}

$user_handle_hex = wa_consume_pending_user_handle();
if (!$user_handle_hex) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_SERVER));
  die;
}
wa_save_credential(
  $admin_id,
  wa_b64url_encode($data->credentialId),
  $data->credentialPublicKey,
  $user_handle_hex,
  bin2hex($data->AAGUID),
  $label
);

echo json_encode(array('ok' => true));
die;
