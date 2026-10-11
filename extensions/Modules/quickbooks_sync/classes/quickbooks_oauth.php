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
//  Path: /modules/quickbooks_sync/classes/quickbooks_oauth.php
//
// Intuit's OAuth 2.0 calls: the authorisation link, the code and refresh token exchanges, and
// revoking a connection. It keeps no state; see quickbooks_connection for what is stored.
//
// Intuit apps are confidential clients: every token request carries the client id and secret as
// HTTP Basic authentication, so a secret is required. The company is chosen on Intuit's own page,
// which returns its id as realmId alongside the code.
//

class quickbooks_auth_error extends RuntimeException {}

class quickbooks_oauth {

  const AUTHORIZE_URL = 'https://appcenter.intuit.com/connect/oauth2';
  const TOKEN_URL     = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
  const REVOKE_URL    = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

  // The accounting scope covers invoices, credit memos, customers, items, accounts and tax codes.
  const SCOPE = 'com.intuit.quickbooks.accounting';

  // The error code of a refused token request whose refresh token or code is no longer valid.
  const INVALID_GRANT = 1;

  private string $client_id;
  private string $client_secret;

  public function __construct(string $client_id, string $client_secret) {
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;
  }

  // The address Intuit sends the browser back to, from the server's own configured URL. It must be
  // registered on the Intuit app exactly as shown. It is the shared oauth_return.php, which
  // forwards to the callback page named by the state, so every provider's redirect URI is the same
  // plain address.
  public static function redirect_uri(): string {
    return DIR_WS_FULL_PATH . 'oauth_return.php';
  }

  public function authorize_url(string $redirect_uri, string $state): string {
    return self::AUTHORIZE_URL . '?' . http_build_query([
      'client_id'     => $this->client_id,
      'response_type' => 'code',
      'scope'         => self::SCOPE,
      'redirect_uri'  => $redirect_uri,
      'state'         => $state,
    ], '', '&', PHP_QUERY_RFC3986);
  }

  // Returns ['refresh_token', 'access_token', 'expires_in', 'refresh_expires_in'].
  public function exchange_code(string $code, string $redirect_uri): array {
    return $this->token_request(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect_uri]);
  }

  // Intuit changes the refresh token from time to time and the old value then stops working, so
  // the caller must store the one returned before anything else can fail.
  public function refresh(string $refresh_token): array {
    return $this->token_request(['grant_type' => 'refresh_token', 'refresh_token' => $refresh_token]);
  }

  // Revokes the refresh token, which ends the connection at Intuit as well as here. Best effort:
  // the connection is forgotten locally whatever Intuit answers.
  public function revoke(string $refresh_token): void {
    $this->request('POST', self::REVOKE_URL, $this->auth_headers('application/json'), json_encode(['token' => $refresh_token]));
  }

  private function token_request(array $form): array {
    [$status, $body] = $this->request('POST', self::TOKEN_URL, $this->auth_headers('application/x-www-form-urlencoded'), http_build_query($form));
    if ($status !== 200 || !is_array($body) || empty($body['access_token']) || empty($body['refresh_token'])) {
      $reason = is_array($body) ? trim(($body['error'] ?? '') . ' ' . ($body['error_description'] ?? '')) : '';
      throw new quickbooks_auth_error(sprintf(QUICKBOOKS_SYNC_ERR_TOKEN, $status, $reason !== '' ? $reason : '-'),
                                      $status === 400 && ($body['error'] ?? '') === 'invalid_grant' ? self::INVALID_GRANT : 0);
    }
    return [
      'refresh_token'      => (string)$body['refresh_token'],
      'access_token'       => (string)$body['access_token'],
      'expires_in'         => (int)($body['expires_in'] ?? 3600),
      'refresh_expires_in' => (int)($body['x_refresh_token_expires_in'] ?? 0),
    ];
  }

  private function auth_headers(string $content_type): array {
    return [
      'Content-Type: ' . $content_type,
      'Accept: application/json',
      'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
    ];
  }

  // Returns [HTTP status, decoded JSON body or null]. Status 0 means no answer.
  protected function request(string $method, string $url, array $headers, ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$status, is_string($raw) ? json_decode($raw, true) : null];
  }
}
