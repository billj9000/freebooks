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

// Xero's rate limit was reached (HTTP 429). $retry_after is how many seconds Xero asks for before
// the next call, and $problem which limit it was: 'minute', 'day' or 'concurrent'.
class xero_rate_limit extends xero_api_error {
  public function __construct(string $message, public int $retry_after, public string $problem) {
    parent::__construct($message);
  }
}

class xero_api {

  const BASE_URL = 'https://api.xero.com/api.xro/2.0/';

  private xero_connection $connection;

  public function __construct(?xero_connection $connection = null) {
    $this->connection = $connection ?? new xero_connection();
  }

  // The connected organisation's Xero id.
  public function tenant_id(): string {
    return (string)$this->connection->load()['tenant_id'];
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
    $result = $this->request($method, self::BASE_URL . $endpoint, $headers,
                             $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    [$status, $answer] = $result;
    if ($status >= 200 && $status < 300 && is_array($answer)) return $answer;
    if ($status === 429) {
      $answer_headers = $result[2] ?? [];
      $problem = strtolower((string)($answer_headers['x-rate-limit-problem'] ?? 'minute'));
      throw new xero_rate_limit(sprintf(XERO_SYNC_ERR_XERO_HTTP, $status), max(1, (int)($answer_headers['retry-after'] ?? 60)), $problem);
    }
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

  // Returns [HTTP status, decoded JSON body or null, answer headers keyed in lower case]. Status 0
  // means no answer.
  protected function request(string $method, string $url, array $headers, ?string $body): array {
    $answer_headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 30,
      CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$answer_headers) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) $answer_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        return strlen($line);
      },
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    return [(int)curl_getinfo($ch, CURLINFO_HTTP_CODE), is_string($raw) ? json_decode($raw, true) : null, $answer_headers];
  }
}
