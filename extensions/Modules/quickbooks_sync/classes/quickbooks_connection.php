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
//  Path: /modules/quickbooks_sync/classes/quickbooks_connection.php
//
// The company's connection to one QuickBooks Online company: the environment (sandbox or
// production), the Intuit app's client id and secret, the tokens, and the company connected. It
// lives in the single row of quickbooks_sync_token, with the secret and tokens encrypted by
// quickbooks_crypto.
//
// Intuit changes the refresh token from time to time, and the old one then stops working. Two
// requests refreshing at once with the same token would leave one of them, and possibly the stored
// token, holding a dead token, so a refresh locks the row (SELECT ... FOR UPDATE), checks whether
// another request has refreshed meanwhile, and saves the new refresh token in the same transaction.
//

require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_crypto.php');
require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_oauth.php');

class quickbooks_connection {

  // An access token is renewed this many seconds before Intuit says it expires.
  const EXPIRY_MARGIN = 60;

  // An Intuit app has separate keys for its sandbox companies and for real ones.
  const ENVIRONMENTS = ['sandbox', 'production'];

  private quickbooks_crypto $crypto;

  public function __construct(?string $company = null) {
    $this->crypto = new quickbooks_crypto($company ?? $_SESSION['company']);
  }

  // The stored row, with the secret and tokens decrypted. A value that cannot be decrypted reads
  // as blank, so a lost key file shows as not connected.
  public function load(bool $for_update = false): array {
    global $db;
    $db->Execute("INSERT IGNORE INTO " . TABLE_QUICKBOOKS_SYNC_TOKEN . " (id) VALUES (1)");
    $row = $db->Execute("SELECT * FROM " . TABLE_QUICKBOOKS_SYNC_TOKEN . " WHERE id = 1" . ($for_update ? ' FOR UPDATE' : ''))->fields;
    foreach (['client_secret', 'refresh_token', 'access_token'] as $field) {
      $row[$field] = $this->crypto->decrypt((string)$row[$field]) ?? '';
    }
    $row['access_expires']  = (int)$row['access_expires'];
    $row['refresh_expires'] = (int)$row['refresh_expires'];
    return $row;
  }

  public function is_connected(): bool {
    $row = $this->load();
    return $row['refresh_token'] !== '' && $row['realm_id'] !== '';
  }

  // Saves the environment and the Intuit app's client id, and its secret unless $client_secret is
  // null. Another environment or client id means other keys, so the connection made with the old
  // ones is dropped.
  public function save_client(string $environment, string $client_id, ?string $client_secret): void {
    $row = $this->load();
    $environment = in_array($environment, self::ENVIRONMENTS, true) ? $environment : 'sandbox';
    $data = ['environment' => $environment, 'client_id' => mb_substr($client_id, 0, 64)];
    if ($client_secret !== null) $data['client_secret'] = $this->crypto->encrypt($client_secret);
    if ($client_id !== $row['client_id'] || $environment !== $row['environment']) $data += $this->cleared();
    db_perform(TABLE_QUICKBOOKS_SYNC_TOKEN, $data, 'update', 'id = 1');
  }

  // Starts a connection: keeps a state value in the session and returns the Intuit page to send the
  // browser to.
  public function start(): string {
    $row = $this->load();
    // oauth_return.php reads the module name from the front of the state.
    $state = 'quickbooks_sync.' . bin2hex(random_bytes(16));
    $_SESSION['quickbooks_sync_oauth'] = ['state' => $state];
    return $this->oauth($row)->authorize_url(quickbooks_oauth::redirect_uri(), $state);
  }

  // Finishes a connection from Intuit's redirect. The state must match the one start() kept, and is
  // used once, so a replayed or forged redirect is refused.
  public function complete(string $code, string $state, string $realm_id): void {
    $pending = $_SESSION['quickbooks_sync_oauth'] ?? null;
    unset($_SESSION['quickbooks_sync_oauth']);
    if (!$pending || $state === '' || !hash_equals($pending['state'], $state)) throw new quickbooks_auth_error(QUICKBOOKS_SYNC_ERR_STATE);
    if (!preg_match('/^[0-9]{1,32}$/', $realm_id)) throw new quickbooks_auth_error(QUICKBOOKS_SYNC_ERR_NO_COMPANY);
    $row = $this->load();
    $tokens = $this->oauth($row)->exchange_code($code, quickbooks_oauth::redirect_uri());
    db_perform(TABLE_QUICKBOOKS_SYNC_TOKEN, array_merge($this->cleared(), $this->token_fields($tokens), [
      'realm_id'     => $realm_id,
      'connected_at' => date('Y-m-d H:i:s'),
      'connected_by' => (int)($_SESSION['admin_id'] ?? 0),
    ]), 'update', 'id = 1');
  }

  public function set_company_name(string $name): void {
    db_perform(TABLE_QUICKBOOKS_SYNC_TOKEN, ['company_name' => mb_substr($name, 0, 255)], 'update', 'id = 1');
  }

  // Revokes the connection at Intuit, then forgets it here whatever Intuit answered.
  public function disconnect(): void {
    $row = $this->load();
    if ($row['refresh_token'] !== '' && $row['client_id'] !== '') {
      try { $this->oauth($row)->revoke($row['refresh_token']); } catch (Throwable $e) {}
    }
    db_perform(TABLE_QUICKBOOKS_SYNC_TOKEN, $this->cleared(), 'update', 'id = 1');
  }

  // A current access token, the company it is for and its environment:
  // ['token' => .., 'realm_id' => .., 'environment' => ..]. A refresh Intuit refuses as invalid
  // (the connection was revoked or has lapsed) drops the stored connection, so the settings page
  // shows it needs connecting again.
  public function access(): array {
    global $db;
    $row = $this->load();
    if ($row['refresh_token'] === '' || $row['realm_id'] === '') throw new quickbooks_auth_error(QUICKBOOKS_SYNC_ERR_NOT_CONNECTED);
    if ($row['access_token'] === '' || $row['access_expires'] <= time() + self::EXPIRY_MARGIN) {
      $db->transStart();
      try {
        $row = $this->load(true);
        if ($row['access_token'] === '' || $row['access_expires'] <= time() + self::EXPIRY_MARGIN) {
          if ($row['refresh_token'] === '') throw new quickbooks_auth_error(QUICKBOOKS_SYNC_ERR_NOT_CONNECTED);
          $tokens = $this->oauth($row)->refresh($row['refresh_token']);
          db_perform(TABLE_QUICKBOOKS_SYNC_TOKEN, $this->token_fields($tokens), 'update', 'id = 1');
          $row['access_token'] = $tokens['access_token'];
        }
        $db->transCommit();
      } catch (Throwable $e) {
        $db->transRollback();
        if ($e instanceof quickbooks_auth_error && $e->getCode() === quickbooks_oauth::INVALID_GRANT) {
          db_perform(TABLE_QUICKBOOKS_SYNC_TOKEN, $this->cleared(), 'update', 'id = 1');
          throw new quickbooks_auth_error(QUICKBOOKS_SYNC_ERR_EXPIRED);
        }
        throw $e;
      }
    }
    return ['token' => $row['access_token'], 'realm_id' => $row['realm_id'], 'environment' => $row['environment']];
  }

  // The OAuth client for the stored app. Tests replace it to answer without calling Intuit.
  protected function oauth(array $row): quickbooks_oauth {
    if ($row['client_id'] === '' || $row['client_secret'] === '') throw new quickbooks_auth_error(QUICKBOOKS_SYNC_ERR_NO_CLIENT);
    return new quickbooks_oauth($row['client_id'], $row['client_secret']);
  }

  // A refresh_expires of 0 means Intuit did not say when the refresh token lapses.
  private function token_fields(array $tokens): array {
    return [
      'refresh_token'   => $this->crypto->encrypt($tokens['refresh_token']),
      'access_token'    => $this->crypto->encrypt($tokens['access_token']),
      'access_expires'  => time() + $tokens['expires_in'],
      'refresh_expires' => $tokens['refresh_expires_in'] ? time() + $tokens['refresh_expires_in'] : 0,
    ];
  }

  private function cleared(): array {
    return ['refresh_token' => '', 'access_token' => '', 'access_expires' => 0, 'refresh_expires' => 0, 'realm_id' => '',
            'company_name' => '', 'connected_at' => 'null', 'connected_by' => 0];
  }
}
