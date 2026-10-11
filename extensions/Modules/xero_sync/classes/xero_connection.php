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
//  Path: /modules/xero_sync/classes/xero_connection.php
//
// The company's connection to one Xero organisation: the Xero app's client id and secret, the
// tokens, and the organisation chosen. It lives in the single row of xero_sync_token, with the
// secret and tokens encrypted by xero_crypto.
//
// Xero replaces the refresh token each time it is used, and the old one stops working. Two
// requests refreshing at once with the same token would leave one of them, and possibly the stored
// token, holding a dead token, so a refresh locks the row (SELECT ... FOR UPDATE), checks whether
// another request has refreshed meanwhile, and saves the new refresh token in the same transaction.
//

require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_crypto.php');
require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_oauth.php');

class xero_connection {

  // An access token is renewed this many seconds before Xero says it expires.
  const EXPIRY_MARGIN = 60;

  private xero_crypto $crypto;

  public function __construct(?string $company = null) {
    $this->crypto = new xero_crypto($company ?? $_SESSION['company']);
  }

  // The stored row, with the secret and tokens decrypted. A value that cannot be decrypted reads
  // as blank, so a lost key file shows as not connected.
  public function load(bool $for_update = false): array {
    global $db;
    $db->Execute("INSERT IGNORE INTO " . TABLE_XERO_SYNC_TOKEN . " (id) VALUES (1)");
    $row = $db->Execute("SELECT * FROM " . TABLE_XERO_SYNC_TOKEN . " WHERE id = 1" . ($for_update ? ' FOR UPDATE' : ''))->fields;
    foreach (['client_secret', 'refresh_token', 'access_token'] as $field) {
      $row[$field] = $this->crypto->decrypt((string)$row[$field]) ?? '';
    }
    $row['access_expires'] = (int)$row['access_expires'];
    return $row;
  }

  public function is_connected(): bool {
    $row = $this->load();
    return $row['refresh_token'] !== '' && $row['tenant_id'] !== '';
  }

  // Saves the Xero app's client id, and its secret unless $client_secret is null. A different
  // client id belongs to a different app, so the connection made through the old one is dropped.
  public function save_client(string $client_id, ?string $client_secret): void {
    $row = $this->load();
    $data = ['client_id' => mb_substr($client_id, 0, 64)];
    if ($client_secret !== null) $data['client_secret'] = $this->crypto->encrypt($client_secret);
    if ($client_id !== $row['client_id']) $data += $this->cleared();
    db_perform(TABLE_XERO_SYNC_TOKEN, $data, 'update', 'id = 1');
  }

  // Starts a connection: keeps the PKCE verifier and a state value in the session and returns the
  // Xero page to send the browser to.
  public function start(): string {
    $row = $this->load();
    [$verifier, $challenge] = xero_oauth::pkce_pair();
    // oauth_return.php reads the module name from the front of the state.
    $state = 'xero_sync.' . bin2hex(random_bytes(16));
    $_SESSION['xero_sync_oauth'] = ['state' => $state, 'verifier' => $verifier];
    return $this->oauth($row)->authorize_url(xero_oauth::redirect_uri(), $state, $challenge);
  }

  // Finishes a connection from Xero's redirect. The state must match the one start() kept, and is
  // used once, so a replayed or forged redirect is refused. Returns the organisations reached.
  public function complete(string $code, string $state): array {
    $pending = $_SESSION['xero_sync_oauth'] ?? null;
    unset($_SESSION['xero_sync_oauth']);
    if (!$pending || $state === '' || !hash_equals($pending['state'], $state)) throw new xero_auth_error(XERO_SYNC_ERR_STATE);
    $row = $this->load();
    $oauth = $this->oauth($row);
    $tokens = $oauth->exchange_code($code, $pending['verifier'], xero_oauth::redirect_uri());
    db_perform(TABLE_XERO_SYNC_TOKEN, array_merge($this->cleared(), $this->token_fields($tokens)), 'update', 'id = 1');
    return $oauth->connections($tokens['access_token']);
  }

  public function set_tenant(string $tenant_id, string $tenant_name): void {
    db_perform(TABLE_XERO_SYNC_TOKEN, [
      'tenant_id'    => mb_substr($tenant_id, 0, 64),
      'tenant_name'  => mb_substr($tenant_name, 0, 255),
      'connected_at' => date('Y-m-d H:i:s'),
      'connected_by' => (int)($_SESSION['admin_id'] ?? 0),
    ], 'update', 'id = 1');
  }

  // Revokes the connection at Xero, then forgets it here whatever Xero answered.
  public function disconnect(): void {
    $row = $this->load();
    if ($row['refresh_token'] !== '' && $row['client_id'] !== '') {
      try { $this->oauth($row)->revoke($row['refresh_token']); } catch (Throwable $e) {}
    }
    db_perform(TABLE_XERO_SYNC_TOKEN, $this->cleared(), 'update', 'id = 1');
  }

  // A current access token and the organisation it is for: ['token' => .., 'tenant_id' => ..].
  // A refresh Xero refuses as invalid (the connection was revoked or has lapsed) drops the stored
  // connection, so the settings page shows it needs connecting again.
  public function access(): array {
    global $db;
    $row = $this->load();
    if ($row['refresh_token'] === '' || $row['tenant_id'] === '') throw new xero_auth_error(XERO_SYNC_ERR_NOT_CONNECTED);
    if ($row['access_token'] !== '' && $row['access_expires'] > time() + self::EXPIRY_MARGIN) {
      return ['token' => $row['access_token'], 'tenant_id' => $row['tenant_id']];
    }
    $db->transStart();
    try {
      $row = $this->load(true);
      if ($row['access_token'] === '' || $row['access_expires'] <= time() + self::EXPIRY_MARGIN) {
        if ($row['refresh_token'] === '') throw new xero_auth_error(XERO_SYNC_ERR_NOT_CONNECTED);
        $tokens = $this->oauth($row)->refresh($row['refresh_token']);
        db_perform(TABLE_XERO_SYNC_TOKEN, $this->token_fields($tokens), 'update', 'id = 1');
        $row['access_token'] = $tokens['access_token'];
      }
      $db->transCommit();
    } catch (Throwable $e) {
      $db->transRollback();
      if ($e instanceof xero_auth_error && $e->getCode() === xero_oauth::INVALID_GRANT) {
        db_perform(TABLE_XERO_SYNC_TOKEN, $this->cleared(), 'update', 'id = 1');
        throw new xero_auth_error(XERO_SYNC_ERR_EXPIRED);
      }
      throw $e;
    }
    return ['token' => $row['access_token'], 'tenant_id' => $row['tenant_id']];
  }

  // The OAuth client for the stored app. Tests replace it to answer without calling Xero.
  protected function oauth(array $row): xero_oauth {
    if ($row['client_id'] === '') throw new xero_auth_error(XERO_SYNC_ERR_NO_CLIENT);
    return new xero_oauth($row['client_id'], $row['client_secret']);
  }

  private function token_fields(array $tokens): array {
    return [
      'refresh_token'  => $this->crypto->encrypt($tokens['refresh_token']),
      'access_token'   => $this->crypto->encrypt($tokens['access_token']),
      'access_expires' => time() + $tokens['expires_in'],
    ];
  }

  private function cleared(): array {
    return ['refresh_token' => '', 'access_token' => '', 'access_expires' => 0, 'tenant_id' => '',
            'tenant_name' => '', 'connected_at' => 'null', 'connected_by' => 0];
  }
}
