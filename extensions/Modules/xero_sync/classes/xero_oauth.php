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
//  Path: /modules/xero_sync/classes/xero_oauth.php
//
// Xero's OAuth 2.0 calls: the authorisation link, the code and refresh token exchanges, the list
// of organisations a connection reaches, and revoking a connection. It keeps no state; see
// xero_connection for what is stored.
//
// PKCE (S256) is always used. Xero accepts it from both web and PKCE apps, so one flow serves
// whichever kind of app is registered; the client secret is added, as HTTP Basic authentication,
// only when one is set.
//

class xero_auth_error extends RuntimeException {}

class xero_oauth {

  const AUTHORIZE_URL   = 'https://login.xero.com/identity/connect/authorize';
  const TOKEN_URL       = 'https://identity.xero.com/connect/token';
  const REVOKE_URL      = 'https://identity.xero.com/connect/revocation';
  const CONNECTIONS_URL = 'https://api.xero.com/connections';

  // accounting.invoices covers invoices and credit notes, accounting.contacts lets Xero match or
  // create the contact, and accounting.settings reads tax rates and accounts and creates items.
  // accounting.transactions is not requested: Xero no longer assigns that broad scope to new
  // apps, and asking for it fails the whole request with invalid_scope.
  // The error code of a refused token request whose refresh token or code is no longer valid.
  const INVALID_GRANT = 1;

  const SCOPES = 'openid profile email offline_access accounting.invoices accounting.contacts accounting.settings';

  private string $client_id;
  private string $client_secret;

  public function __construct(string $client_id, string $client_secret = '') {
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;
  }

  // The address Xero sends the browser back to, from the server's own configured URL. It must be
  // registered on the Xero app exactly as shown. Xero refuses a redirect URI with a query string,
  // so it is the shared oauth_return.php, which forwards to the callback page named by the state.
  public static function redirect_uri(): string {
    return DIR_WS_FULL_PATH . 'oauth_return.php';
  }

  // A PKCE verifier and its S256 challenge.
  public static function pkce_pair(): array {
    $verifier = self::base64url(random_bytes(40));
    return [$verifier, self::pkce_challenge($verifier)];
  }

  public static function pkce_challenge(string $verifier): string {
    return self::base64url(hash('sha256', $verifier, true));
  }

  // Spaces in the scope are encoded as %20: Xero refuses a scope whose spaces arrive as +.
  public function authorize_url(string $redirect_uri, string $state, string $challenge): string {
    return self::AUTHORIZE_URL . '?' . http_build_query([
      'response_type'         => 'code',
      'client_id'             => $this->client_id,
      'redirect_uri'          => $redirect_uri,
      'scope'                 => self::SCOPES,
      'state'                 => $state,
      'code_challenge'        => $challenge,
      'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);
  }

  // Returns ['refresh_token', 'access_token', 'expires_in'].
  public function exchange_code(string $code, string $verifier, string $redirect_uri): array {
    return $this->token_request([
      'grant_type'    => 'authorization_code',
      'code'          => $code,
      'redirect_uri'  => $redirect_uri,
      'client_id'     => $this->client_id,
      'code_verifier' => $verifier,
    ]);
  }

  // Xero replaces the refresh token on every use, so the caller must store the one returned
  // before anything else can fail: the old one no longer works.
  public function refresh(string $refresh_token): array {
    return $this->token_request([
      'grant_type'    => 'refresh_token',
      'refresh_token' => $refresh_token,
      'client_id'     => $this->client_id,
    ]);
  }

  // The organisations the connection reaches: each ['tenantId', 'tenantName', 'tenantType', ...].
  public function connections(string $access_token): array {
    [$status, $body] = $this->request('GET', self::CONNECTIONS_URL, ['Authorization: Bearer ' . $access_token, 'Accept: application/json']);
    if ($status !== 200 || !is_array($body)) throw new xero_auth_error(sprintf(XERO_SYNC_ERR_XERO_HTTP, $status));
    return array_values(array_filter($body, fn($c) => is_array($c) && ($c['tenantType'] ?? '') === 'ORGANISATION'));
  }

  // Revokes the refresh token, ending the connection at Xero as well as here. Best effort: the
  // connection is forgotten locally whatever Xero answers.
  public function revoke(string $refresh_token): void {
    $this->request('POST', self::REVOKE_URL, $this->auth_headers(),
                   http_build_query(['token' => $refresh_token, 'client_id' => $this->client_id]));
  }

  private function token_request(array $form): array {
    [$status, $body] = $this->request('POST', self::TOKEN_URL, $this->auth_headers(), http_build_query($form));
    if ($status !== 200 || !is_array($body) || empty($body['access_token']) || empty($body['refresh_token'])) {
      $reason = is_array($body) ? trim(($body['error'] ?? '') . ' ' . ($body['error_description'] ?? '')) : '';
      throw new xero_auth_error(sprintf(XERO_SYNC_ERR_TOKEN, $status, $reason !== '' ? $reason : '-'),
                                $status === 400 && ($body['error'] ?? '') === 'invalid_grant' ? self::INVALID_GRANT : 0);
    }
    return [
      'refresh_token' => (string)$body['refresh_token'],
      'access_token'  => (string)$body['access_token'],
      'expires_in'    => (int)($body['expires_in'] ?? 1800),
    ];
  }

  private function auth_headers(): array {
    $headers = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
    if ($this->client_secret !== '') $headers[] = 'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret);
    return $headers;
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

  private static function base64url(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  }
}
