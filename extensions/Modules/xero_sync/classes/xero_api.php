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
//  Path: /modules/xero_sync/classes/xero_api.php
//
// Calls to Xero's Accounting API for the connected organisation. Each call takes a current access
// token from xero_connection, so a token that has expired is renewed rather than failing.
//

require_once(DIR_FS_MODULES . 'xero_sync/classes/xero_connection.php');

class xero_api_error extends RuntimeException {}

class xero_api {

  const BASE_URL = 'https://api.xero.com/api.xro/2.0/';

  private xero_connection $connection;

  public function __construct(?xero_connection $connection = null) {
    $this->connection = $connection ?? new xero_connection();
  }

  // The organisation's tax rates, as Xero returns them (Name, TaxType, Status, ...).
  public function tax_rates(): array {
    return $this->get('TaxRates')['TaxRates'] ?? [];
  }

  // The organisation's active accounts (Code, Name, Type, Class, ...).
  public function accounts(): array {
    return $this->get('Accounts?where=' . rawurlencode('Status=="ACTIVE"'))['Accounts'] ?? [];
  }

  public function get(string $endpoint): array {
    return $this->call('GET', $endpoint);
  }

  // A POST of $body, which Xero answers with the saved objects under $result_key. A rejected
  // object comes back with ValidationErrors rather than an error status, so those are raised too.
  public function post(string $endpoint, array $body, string $result_key): array {
    $answer = $this->call('POST', $endpoint, $body);
    $first = ($answer[$result_key] ?? [])[0] ?? null;
    if (is_array($first) && !empty($first['ValidationErrors'])) {
      throw new xero_api_error(self::messages(['Elements' => [$first]]) ?: XERO_SYNC_ERR_REJECTED);
    }
    return is_array($first) ? $first : $answer;
  }

  private function call(string $method, string $endpoint, ?array $body = null): array {
    $access = $this->connection->access();
    $headers = [
      'Authorization: Bearer ' . $access['token'],
      'Xero-tenant-id: ' . $access['tenant_id'],
      'Accept: application/json',
    ];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    [$status, $answer] = $this->request($method, self::BASE_URL . $endpoint, $headers,
                                        $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    if ($status >= 200 && $status < 300 && is_array($answer)) return $answer;
    $message = is_array($answer) ? self::messages($answer) : '';
    throw new xero_api_error($message !== '' ? $message : sprintf(XERO_SYNC_ERR_XERO_HTTP, $status));
  }

  // Xero's explanation of a refusal: its Message, then each element's validation messages.
  private static function messages(array $answer): string {
    $messages = [];
    if (!empty($answer['Message'])) $messages[] = $answer['Message'];
    foreach ($answer['Elements'] ?? [] as $element) {
      foreach ($element['ValidationErrors'] ?? [] as $error) {
        if (!empty($error['Message'])) $messages[] = $error['Message'];
      }
    }
    return implode('; ', $messages);
  }

  // Returns [HTTP status, decoded JSON body or null]. Status 0 means no answer.
  protected function request(string $method, string $url, array $headers, ?string $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    return [(int)curl_getinfo($ch, CURLINFO_HTTP_CODE), is_string($raw) ? json_decode($raw, true) : null];
  }
}
