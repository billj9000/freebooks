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
//  Path: /modules/phreedom/functions/webauthn.php
//
// Shared helpers for passkey (WebAuthn) registration and login.
// The credential store (TABLE_WEBAUTHN_CREDS) lives in the per-company
// database, same as pb_users, so a passkey is scoped to one company.

// How long (seconds) a registration/login challenge stays valid.
define('WEBAUTHN_CHALLENGE_TTL', 120);

/**
 * True when WebAuthn's hard requirement - a secure context - is met. This is
 * a server-wide constant (ENABLE_SSL_ADMIN, from configure.php), available
 * even before any company database is connected, so it's safe to use for
 * deciding whether to show passkey UI on the bare login screen.
 */
function wa_ssl_ok() {
  return defined('ENABLE_SSL_ADMIN') && ENABLE_SSL_ADMIN === 'true';
}

/**
 * True only when the feature can actually work: wa_ssl_ok(), and the admin
 * hasn't turned it off for the *currently connected* company via
 * pb_configuration. WEBAUTHN_ENABLED lives in each company's own database,
 * so this is only meaningful once a company is connected (ie. everywhere
 * except the bare pre-login screen - see wa_ssl_ok() for that case). It's
 * still the authoritative, server-side check for every ceremony endpoint.
 */
function wa_enabled() {
  return wa_ssl_ok() && defined('WEBAUTHN_ENABLED') && WEBAUTHN_ENABLED === '1';
}

/**
 * Relying Party ID (= domain). Defaults to the host portion of HTTPS_SERVER;
 * overridable via WEBAUTHN_RP_ID for installs behind a proxy/hostname that
 * doesn't match the configured server URL.
 */
function wa_rp_id() {
  if (defined('WEBAUTHN_RP_ID') && WEBAUTHN_RP_ID !== '') return WEBAUTHN_RP_ID;
  $host = defined('HTTPS_SERVER') ? parse_url(HTTPS_SERVER, PHP_URL_HOST) : false;
  return $host ?: (defined('HTTP_SERVER') ? parse_url(HTTP_SERVER, PHP_URL_HOST) : 'localhost');
}

/**
 * Builds a configured WebAuthn server instance. Binary fields (challenge,
 * credential ids) are serialized to JSON as plain base64url strings so the
 * browser-side JS in modules/phreedom/includes/webauthn/passkey.js can
 * decode them without a custom reviver.
 * @return \lbuchs\WebAuthn\WebAuthn
 * @throws \lbuchs\WebAuthn\WebAuthnException
 */
function wa_new_instance() {
  $company_name = defined('COMPANY_NAME') && COMPANY_NAME !== '' ? COMPANY_NAME : 'FreeBooks';
  return new \lbuchs\WebAuthn\WebAuthn($company_name, wa_rp_id(), null, true);
}

/**
 * Stashes a one-shot challenge (raw binary string, as returned by
 * ByteBuffer::getBinaryString()) in the session for the ceremony's second
 * step to consume. $purpose distinguishes a login challenge from a
 * registration challenge so one can't be replayed as the other.
 */
function wa_store_challenge($challenge_raw, $purpose) {
  $_SESSION['wa_challenge']      = $challenge_raw;
  $_SESSION['wa_challenge_time'] = time();
  $_SESSION['wa_challenge_for']  = $purpose;
}

/**
 * Retrieves and clears the pending challenge (raw binary string, ready to
 * pass straight into processCreate()/processGet()) if it matches $purpose
 * and hasn't expired. Returns false otherwise so the caller can reject the
 * ceremony without ever reusing a stale/foreign challenge.
 */
function wa_consume_challenge($purpose) {
  $challenge = $_SESSION['wa_challenge']      ?? false;
  $for       = $_SESSION['wa_challenge_for']  ?? false;
  $time      = $_SESSION['wa_challenge_time'] ?? 0;
  unset($_SESSION['wa_challenge'], $_SESSION['wa_challenge_time'], $_SESSION['wa_challenge_for']);
  if ($challenge === false || $for !== $purpose) return false;
  if ((time() - (int)$time) > WEBAUTHN_CHALLENGE_TTL) return false;
  return $challenge;
}

/**
 * Decodes a base64url string (as sent by the browser) to a raw binary
 * string. Implemented directly rather than via ByteBuffer::fromBase64Url()
 * so it doesn't depend on the library's global $useBase64UrlEncoding flag
 * having already been set by an earlier wa_new_instance() call this request.
 */
function wa_b64url_decode($base64url) {
  $b64 = strtr((string)$base64url, '-_', '+/');
  $pad = strlen($b64) % 4;
  if ($pad) $b64 .= str_repeat('=', 4 - $pad);
  return base64_decode($b64);
}

/** Encodes a raw binary string (eg. from processCreate()'s returned credentialId) to base64url. */
function wa_b64url_encode($raw) {
  return rtrim(strtr(base64_encode((string)$raw), '+/', '-_'), '=');
}

/**
 * A WebAuthn user handle must be stable for a given account (so a device
 * that already has a passkey for this account can recognize a second
 * registration as belonging to the same user) but must not leak admin_id.
 * Reuses the handle from the account's first credential, if any, else mints
 * a new random one.
 *
 * Registration is a two-request ceremony (passkey_register_begin.php then
 * passkey_register_finish.php): the handle minted here for a user's *first*
 * credential must be the exact same value in both requests, since it's
 * baked into the credential in the first request but only saved to
 * pb_webauthn_creds in the second. Callers must not call this twice across
 * the two requests for a brand new account - use
 * wa_store_pending_user_handle()/wa_consume_pending_user_handle() to carry
 * the value from begin to finish instead.
 */
function wa_get_or_create_user_handle($admin_id) {
  global $db;
  $result = $db->Execute("select user_handle from " . TABLE_WEBAUTHN_CREDS
    . " where admin_id = " . (int)$admin_id . " limit 1");
  if ($result && !$result->EOF && gen_not_null($result->fields['user_handle'])) {
    return $result->fields['user_handle'];
  }
  return bin2hex(random_bytes(16));
}

/** Carries the user handle minted in passkey_register_begin.php to passkey_register_finish.php. */
function wa_store_pending_user_handle($user_handle_hex) {
  $_SESSION['wa_pending_user_handle'] = $user_handle_hex;
}

/** One-shot retrieval of the handle stored by wa_store_pending_user_handle(). */
function wa_consume_pending_user_handle() {
  $handle = $_SESSION['wa_pending_user_handle'] ?? false;
  unset($_SESSION['wa_pending_user_handle']);
  return $handle;
}

/** All passkeys registered for a given admin_id, newest first. */
function wa_list_credentials($admin_id) {
  global $db;
  $rows = array();
  $result = $db->Execute("select * from " . TABLE_WEBAUTHN_CREDS
    . " where admin_id = " . (int)$admin_id . " order by created desc");
  while ($result && !$result->EOF) {
    $rows[] = $result->fields;
    $result->MoveNext();
  }
  return $rows;
}

/** Look up a single credential row by its base64url credential id (as sent by the browser). */
function wa_get_credential_by_cred_id($cred_id_b64url) {
  global $db;
  $result = $db->Execute("select * from " . TABLE_WEBAUTHN_CREDS
    . " where cred_id = '" . db_input($cred_id_b64url) . "' limit 1");
  return ($result && !$result->EOF) ? $result->fields : false;
}

/** Look up a single credential row by its primary key, optionally restricted to one admin_id. */
function wa_get_credential($id, $admin_id = null) {
  global $db;
  $sql = "select * from " . TABLE_WEBAUTHN_CREDS . " where id = " . (int)$id;
  if ($admin_id !== null) $sql .= " and admin_id = " . (int)$admin_id;
  $result = $db->Execute($sql);
  return ($result && !$result->EOF) ? $result->fields : false;
}

/** Persists a newly registered credential. Returns the new row id. */
function wa_save_credential($admin_id, $cred_id_b64url, $public_key_pem, $user_handle_hex, $aaguid_hex, $label) {
  db_perform(TABLE_WEBAUTHN_CREDS, array(
    'admin_id'    => (int)$admin_id,
    'cred_id'     => $cred_id_b64url,
    'public_key'  => $public_key_pem,
    'user_handle' => $user_handle_hex,
    'sign_count'  => 0,
    'aaguid'      => $aaguid_hex,
    'label'       => $label,
    'created'     => 'now()',
  ), 'insert');
  return db_insert_id();
}

/** Updates the stored signature counter after a successful login (clone detection). */
function wa_update_sign_count($id, $sign_count) {
  global $db;
  $db->Execute("update " . TABLE_WEBAUTHN_CREDS . " set sign_count = " . (int)$sign_count
    . ", last_used = UTC_TIMESTAMP() where id = " . (int)$id);
}

/** Renames a credential; restricted to its owner. */
function wa_rename_credential($id, $admin_id, $label) {
  global $db;
  $result = $db->Execute("update " . TABLE_WEBAUTHN_CREDS . " set label = '" . db_input($label) . "'"
    . " where id = " . (int)$id . " and admin_id = " . (int)$admin_id);
  return $result && $result->AffectedRows() > 0;
}

/**
 * Deletes a credential. When $admin_id is given, deletion is restricted to
 * that owner (self-service); pass null only from an admin-authorized path.
 */
function wa_delete_credential($id, $admin_id = null) {
  global $db;
  $sql = "delete from " . TABLE_WEBAUTHN_CREDS . " where id = " . (int)$id;
  if ($admin_id !== null) $sql .= " and admin_id = " . (int)$admin_id;
  $result = $db->Execute($sql);
  return $result && $result->AffectedRows() > 0;
}
