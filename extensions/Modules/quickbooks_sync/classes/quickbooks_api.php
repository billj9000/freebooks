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
//  Path: /modules/quickbooks_sync/classes/quickbooks_api.php
//
// Calls to the QuickBooks Online Accounting API for the connected company. Each call takes a
// current access token from quickbooks_connection, so a token that has expired is renewed rather
// than failing, and names minor version 75: Intuit answers older ones as 75 anyway.
//

require_once(DIR_FS_MODULES . 'quickbooks_sync/classes/quickbooks_connection.php');

class quickbooks_api_error extends RuntimeException {}

// QuickBooks' rate limit was reached (HTTP 429). $retry_after is how many seconds to wait before
// the next call.
class quickbooks_rate_limit extends quickbooks_api_error {
  public function __construct(string $message, public int $retry_after) {
    parent::__construct($message);
  }
}

class quickbooks_api {

  const BASE_URLS = [
    'sandbox'    => 'https://sandbox-quickbooks.api.intuit.com/v3/company/',
    'production' => 'https://quickbooks.api.intuit.com/v3/company/',
  ];
  const MINOR_VERSION = 75;

  // QuickBooks returns at most 1000 objects to one query.
  const PAGE_SIZE = 1000;

  private quickbooks_connection $connection;

  public function __construct(?quickbooks_connection $connection = null) {
    $this->connection = $connection ?? new quickbooks_connection();
  }

  // The connected company's QuickBooks id.
  public function realm_id(): string {
    return (string)$this->connection->load()['realm_id'];
  }

  // The connected company's name, from its CompanyInfo.
  public function company_name(): string {
    $realm_id = $this->connection->access()['realm_id'];
    return (string)($this->get('companyinfo/' . $realm_id)['CompanyInfo']['CompanyName'] ?? '');
  }

  // The active services and non-inventory products a sales line can name (Name, Type,
  // IncomeAccountRef, ...). Inventory items are left out: FreeBooks keeps the stock, and selling
  // one in QuickBooks would move QuickBooks' stock and cost of sales as well.
  public function items(): array {
    return array_values(array_filter($this->query_all('Item', "Active = true"),
                                     fn($item) => in_array($item['Type'] ?? '', ['Service', 'NonInventory'], true)));
  }

  // The active VAT codes (Name, SalesTaxRateList, ...).
  public function tax_codes(): array {
    return $this->query_all('TaxCode', "Active = true");
  }

  // The tax rates behind the VAT codes (Id, Name, RateValue, ...).
  public function tax_rates(): array {
    return $this->query_all('TaxRate', '');
  }

  // The active accounts (Name, AccountType, ...).
  public function accounts(): array {
    return $this->query_all('Account', "Active = true");
  }

  // Every object of $entity matching $where, a page at a time.
  public function query_all(string $entity, string $where): array {
    $objects = [];
    for ($start = 1; ; $start += self::PAGE_SIZE) {
      $sql = "SELECT * FROM $entity" . ($where !== '' ? " WHERE $where" : '') . " STARTPOSITION $start MAXRESULTS " . self::PAGE_SIZE;
      $page = $this->get('query?query=' . rawurlencode($sql))['QueryResponse'][$entity] ?? [];
      $objects = array_merge($objects, $page);
      if (count($page) < self::PAGE_SIZE) return $objects;
    }
  }

  public function get(string $endpoint): array {
    return $this->call('GET', $endpoint);
  }

  // A POST of $body to $entity (Invoice, CreditMemo, Customer, ...), which QuickBooks answers with
  // the saved object under that name.
  public function post(string $entity, array $body): array {
    $answer = $this->call('POST', strtolower($entity), $body);
    return is_array($answer[$entity] ?? null) ? $answer[$entity] : $answer;
  }

  private function call(string $method, string $endpoint, ?array $body = null): array {
    $access = $this->connection->access();
    $url = self::BASE_URLS[$access['environment']] . $access['realm_id'] . '/' . $endpoint
         . (strpos($endpoint, '?') === false ? '?' : '&') . 'minorversion=' . self::MINOR_VERSION;
    $headers = ['Authorization: Bearer ' . $access['token'], 'Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    [$status, $answer, $answer_headers] = $this->request($method, $url, $headers,
      $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    if ($status >= 200 && $status < 300 && is_array($answer) && empty($answer['Fault'])) return $answer;
    if ($status === 429) {
      throw new quickbooks_rate_limit(sprintf(QUICKBOOKS_SYNC_ERR_QB_HTTP, $status), max(1, (int)($answer_headers['retry-after'] ?? 60)));
    }
    $message = is_array($answer) ? self::messages($answer) : '';
    throw new quickbooks_api_error($message !== '' ? $message : sprintf(QUICKBOOKS_SYNC_ERR_QB_HTTP, $status));
  }

  // QuickBooks' explanation of a refusal: each error's Message and Detail.
  private static function messages(array $answer): string {
    $messages = [];
    foreach ($answer['Fault']['Error'] ?? [] as $error) {
      $text = trim((string)($error['Message'] ?? ''));
      $detail = trim((string)($error['Detail'] ?? ''));
      if ($detail !== '' && $detail !== $text) $text .= ($text !== '' ? ': ' : '') . $detail;
      if ($text !== '') $messages[] = $text;
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
