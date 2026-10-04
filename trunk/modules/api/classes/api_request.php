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
//  Path: /modules/api/classes/api_request.php
//
// Front controller for the FreeBooks REST API.
//
// Request lifecycle:
//   1. HTTPS check (env-variable override for local dev)
//   2. GET only (v1 is read-only)
//   3. Version param validation
//   4. Bearer token extraction → central DB lookup → company selection
//   5. Company DB bootstrap (mirrors application_top.php lines 86-101)
//   6. Resource dispatch + api_query execution
//   7. JSON response
//

require_once(DIR_FS_MODULES . 'api/defaults.php');
require_once(DIR_FS_MODULES . 'api/classes/api_query.php');
require_once(DIR_FS_MODULES . 'api/classes/resources/contacts.php');
require_once(DIR_FS_MODULES . 'api/classes/resources/invoices.php');
require_once(DIR_FS_MODULES . 'api/classes/resources/credit_memos.php');
require_once(DIR_FS_MODULES . 'api/classes/resources/tax_rates.php');

class api_request {

  // Map of resource name → class name
  private static array $resources = [
    'contacts'      => 'api_resource_contacts',
    'invoices'      => 'api_resource_invoices',
    'credit_memos'  => 'api_resource_credit_memos',
    'tax_rates'     => 'api_resource_tax_rates',
  ];

  public function dispatch(): void {
    try {
      // ── 1. HTTPS ────────────────────────────────────────────────────────
      if (!$this->is_secure()) {
        $this->abort(403, 'HTTPS is required.');
      }

      // ── 2. GET only ─────────────────────────────────────────────────────
      if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $this->abort(405, 'Only GET requests are supported in API v1.');
      }

      // ── 3. Version ──────────────────────────────────────────────────────
      $v = trim($_GET['v'] ?? '');
      if ($v === '') $this->abort(400, "Missing required parameter 'v' (API version).");
      $allowed = array_map('trim', explode(',', API_VERSIONS));
      if (!in_array($v, $allowed, true)) {
        $this->abort(400, "Unsupported API version '$v'. Supported: " . implode(', ', $allowed));
      }

      // ── 4. Auth ─────────────────────────────────────────────────────────
      $token    = $this->extract_token();
      $key_row  = $this->resolve_token($token);   // returns assoc array or aborts
      $company  = $key_row['company'];

      // ── 5. Company DB bootstrap ─────────────────────────────────────────
      $this->bootstrap_company($company, (int)$key_row['id']);

      // ── 6. Resource dispatch ────────────────────────────────────────────
      $resource_name = trim($_GET['resource'] ?? '');
      if ($resource_name === '') $this->abort(400, "Missing required parameter 'resource'.");
      if (!isset(self::$resources[$resource_name])) {
        $this->abort(404, "Unknown resource '$resource_name'. Available: " . implode(', ', array_keys(self::$resources)));
      }
      $class    = self::$resources[$resource_name];
      $resource = new $class();

      try {
        $result = api_query::run($resource, $_GET);
      } catch (ApiQueryException $e) {
        $this->abort(400, $e->getMessage());
      }

      // ── 7. Success response ─────────────────────────────────────────────
      $this->json_ok($result['data'], $result['meta']);

    } catch (Throwable $e) {
      // Catch-all: do not leak stack traces
      $this->abort(500, 'Internal server error.');
    }
  }

  // -----------------------------------------------------------------------
  // Extract Bearer token from the Authorization header.
  // Accepts several forms in case the web server rewrites the header.
  // -----------------------------------------------------------------------
  private function extract_token(): string {
    // Try standard header locations (Apache may expose it differently)
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';

    // Apache + mod_rewrite sometimes strips Authorization; getallheaders() may recover it
    if ($header === '' && function_exists('getallheaders')) {
      $headers = getallheaders();
      $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
      return trim($m[1]);
    }

    $this->abort(401, 'Missing Authorization header. Provide: Authorization: Bearer <key>');
  }

  // -----------------------------------------------------------------------
  // Look up the token hash in the primary company's DB.
  // The api_keys table lives in the primary company's database alongside
  // all other tables.  The company column routes subsequent queries to the
  // correct company DB when a multi-company install is in use.
  // Returns the key row or calls abort().
  // -----------------------------------------------------------------------
  private function resolve_token(string $token): array {
    $primary = $this->find_primary_company();
    $cfg     = DIR_FS_MY_FILES . $primary . '/config.php';
    if (!file_exists($cfg)) $this->abort(503, 'API service unavailable.');
    require_once($cfg);

    if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
    $host = defined('DB_SERVER_HOST') ? DB_SERVER_HOST : 'localhost';

    try {
      $pdo = new PDO(
        "mysql:host={$host};dbname={$primary};charset=utf8mb4",
        DB_SERVER_USERNAME,
        DB_SERVER_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
      );
    } catch (PDOException $e) {
      $this->abort(503, 'API service unavailable.');
    }

    $table    = DB_PREFIX . 'api_keys';
    $key_hash = hash('sha256', $token);
    $stmt     = $pdo->prepare("SELECT id, company, enabled, scopes FROM {$table} WHERE key_hash = ?");
    $stmt->execute([$key_hash]);
    $row = $stmt->fetch();

    if ($row === false)         $this->abort(401, 'Invalid API key.');
    if ($row['enabled'] !== '1') $this->abort(401, 'API key is disabled.');
    if (empty($row['company']))  $this->abort(401, 'API key is not associated with a company.');

    try {
      $pdo->prepare("UPDATE {$table} SET last_used = NOW() WHERE id = ?")->execute([$row['id']]);
    } catch (Throwable $e) {}

    return $row;
  }

  // -----------------------------------------------------------------------
  // Return the primary company name: DEFAULT_COMPANY if set, otherwise the
  // first company directory found in my_files/.
  // -----------------------------------------------------------------------
  private function find_primary_company(): string {
    if (defined('DEFAULT_COMPANY') && DEFAULT_COMPANY !== '') return DEFAULT_COMPANY;
    foreach (scandir(DIR_FS_MY_FILES) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      if (is_dir(DIR_FS_MY_FILES . $entry) && file_exists(DIR_FS_MY_FILES . $entry . '/config.php')) {
        return $entry;
      }
    }
    $this->abort(503, 'API service unavailable.');
  }

  // -----------------------------------------------------------------------
  // Bootstrap the company DB — mirrors application_top.php lines 86-101.
  // Sets up the global $db, defines TABLE_* constants, and loads module
  // configs that the resource classes need.
  // -----------------------------------------------------------------------
  private function bootstrap_company(string $company, int $key_id): void {
    global $db, $_SESSION;

    $config_path = DIR_FS_MY_FILES . $company . '/config.php';
    if (!file_exists($config_path)) {
      $this->abort(503, 'API service unavailable.'); // don't leak internal company name
    }

    // Set minimal session context so any downstream function that checks it works
    $_SESSION['company']    = $company;
    $_SESSION['admin_id']   = 0;   // API system user (no real admin session)
    $_SESSION['language']   = 'en_us';

    define('DB_DATABASE', $company);
    require_once($config_path);

    // Normalise host constant name (old PhreeBooks used DB_SERVER)
    if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);

    require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
    $db = new queryFactory();
    if (!$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE)) {
      $this->abort(503, 'API service unavailable.');
    }

    // Load configuration constants (DB_PREFIX is defined by connect but we need it now for TABLE_*)
    $cfg = $db->Execute("SELECT configuration_key, configuration_value FROM " . DB_PREFIX . "configuration");
    while (!$cfg->EOF) {
      $k = $cfg->fields['configuration_key'];
      if (!defined($k)) define($k, $cfg->fields['configuration_value']);
      $cfg->MoveNext();
    }
    gen_apply_timezone(); // the company's time zone, as the web pages use

    // Define only the TABLE_* constants the resource classes need.
    // Deliberately NOT loading module config.php files — those have menu-building
    // side effects that depend on language strings and HTML helpers we haven't loaded.
    if (!defined('TABLE_API_KEYS'))     define('TABLE_API_KEYS',     DB_PREFIX . 'api_keys');
    if (!defined('TABLE_CONTACTS'))     define('TABLE_CONTACTS',     DB_PREFIX . 'contacts');
    if (!defined('TABLE_ADDRESS_BOOK')) define('TABLE_ADDRESS_BOOK', DB_PREFIX . 'address_book');
    if (!defined('TABLE_JOURNAL_MAIN')) define('TABLE_JOURNAL_MAIN', DB_PREFIX . 'journal_main');
    if (!defined('TABLE_JOURNAL_ITEM')) define('TABLE_JOURNAL_ITEM', DB_PREFIX . 'journal_item');
    if (!defined('TABLE_TAX_AUTH'))     define('TABLE_TAX_AUTH',     DB_PREFIX . 'tax_authorities');
    if (!defined('TABLE_TAX_RATES'))    define('TABLE_TAX_RATES',    DB_PREFIX . 'tax_rates');
  }

  // -----------------------------------------------------------------------
  // Check whether the request arrived over HTTPS.
  // Set FREEBOOKS_API_ALLOW_HTTP=1 in the environment to bypass (dev only).
  // -----------------------------------------------------------------------
  private function is_secure(): bool {
    if (getenv('FREEBOOKS_API_ALLOW_HTTP') === '1') return true;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (($_SERVER['SERVER_PORT'] ?? '') == 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
  }

  // -----------------------------------------------------------------------
  // Emit a success JSON response and exit.
  // -----------------------------------------------------------------------
  private function json_ok(array $data, array $meta): void {
    echo json_encode(['ok' => true, 'data' => $data, 'meta' => $meta], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  // -----------------------------------------------------------------------
  // Emit an error JSON response with the given HTTP status code and exit.
  // -----------------------------------------------------------------------
  private function abort(int $http_code, string $message): never {
    http_response_code($http_code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
