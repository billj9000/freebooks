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
//  Path: /modules/phreedom/ajax/passkey_register_begin.php
//
// Step 1 of registering a passkey for the current user. Returns the
// navigator.credentials.create() options as JSON.
$security_level = validate_ajax_user(SECURITY_ID_MY_PROFILE);

gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_MODULES . 'phreedom/functions/webauthn.php');

header('Content-Type: application/json');

if (!wa_enabled()) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_DISABLED));
  die;
}

$admin_id = (int)$_SESSION['admin_id'];
$who = $db->Execute("select admin_name, display_name from " . TABLE_USERS . " where admin_id = " . $admin_id);
if (!$who || $who->EOF) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_SERVER));
  die;
}

$user_handle_hex = wa_get_or_create_user_handle($admin_id);
wa_store_pending_user_handle($user_handle_hex);

$exclude_ids = array();
foreach (wa_list_credentials($admin_id) as $existing) {
  $exclude_ids[] = wa_b64url_decode($existing['cred_id']);
}

try {
  $webAuthn = wa_new_instance();
} catch (\Throwable $e) {
  echo json_encode(array('ok' => false, 'message' => ERROR_PASSKEY_SERVER));
  die;
}

// All companies on this install share one domain, so the OS/browser passkey
// picker groups every company's credentials under one site entry - suffix
// the account name with the company so entries for different companies are
// distinguishable in that picker (eg. Windows Passkey Manager). Use the
// company's dropdown title, not COMPANY_NAME - the latter is free-text
// company data (invoices etc.) that different companies can coincidentally
// share, whereas the title is structurally guaranteed unique (it's what
// the login screen's company selector itself distinguishes them by).
$company_folder = $_SESSION['company'] ?? '';
$company_label  = ($company_folder && defined($company_folder . '_TITLE'))
  ? constant($company_folder . '_TITLE')
  : (defined('COMPANY_NAME') && COMPANY_NAME !== '' ? COMPANY_NAME : '');
$pk_user_name    = $who->fields['admin_name'];
$pk_display_name = $who->fields['display_name'] ?: $who->fields['admin_name'];
if ($company_label !== '') {
  $pk_user_name    .= ' — ' . $company_label;
  $pk_display_name .= ' — ' . $company_label;
}

$args = $webAuthn->getCreateArgs(
  hex2bin($user_handle_hex),
  $pk_user_name,
  $pk_display_name,
  30,          // timeout (seconds)
  'required',  // requireResidentKey - store on device, no server-side username lookup at login
  'preferred', // requireUserVerification
  null,        // crossPlatformAttachment - allow both platform and roaming authenticators
  $exclude_ids
);
wa_store_challenge($webAuthn->getChallenge()->getBinaryString(), 'register');

echo json_encode(array('ok' => true, 'publicKey' => $args->publicKey));
die;
