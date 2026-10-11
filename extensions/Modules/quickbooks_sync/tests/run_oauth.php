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
//  Path: /modules/quickbooks_sync/tests/run_oauth.php
//
// Checks the parts of the QuickBooks connection that need neither a database nor Intuit: the
// encryption of stored secrets, the authorisation link, how token answers and refusals are read,
// revoking, and how API calls are addressed and their refusals read. Intuit's answers are canned.
// Run it where PHP has sodium:
//
//   php tests/run_oauth.php
//
// Exits 1 when any check fails.
//
if (PHP_SAPI !== 'cli') exit;

$base = dirname(__DIR__);
$files = sys_get_temp_dir() . '/quickbooks_sync_test_' . bin2hex(random_bytes(4)) . '/';
mkdir($files . 'demo', 0700, true);
define('DIR_FS_MY_FILES', $files);
define('DIR_WS_FULL_PATH', 'https://erp.example.com/');
define('DIR_FS_MODULES', dirname($base) . '/');
require($base . '/language/en_us/language.php');
require($base . '/classes/quickbooks_api.php');

$failed = 0;
function check(string $name, bool $ok, string $detail = ''): void {
  global $failed;
  if (!$ok) $failed++;
  echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : "\n     $detail") . "\n";
}

// An OAuth client whose requests are answered from a queue and recorded.
class canned_oauth extends quickbooks_oauth {
  public array $answers = [];
  public array $sent = [];
  protected function request(string $method, string $url, array $headers, ?string $body = null): array {
    $this->sent[] = compact('method', 'url', 'headers', 'body');
    return array_shift($this->answers) ?? [0, null];
  }
}

// A connection that hands out a fixed token, and an API client answered from a queue.
class fixed_connection extends quickbooks_connection {
  public string $environment = 'sandbox';
  public function access(): array { return ['token' => 'TOKEN', 'realm_id' => '4620816365', 'environment' => $this->environment]; }
}
class canned_api extends quickbooks_api {
  public array $answers = [];
  public array $sent = [];
  protected function request(string $method, string $url, array $headers, ?string $body): array {
    $this->sent[] = compact('method', 'url', 'headers', 'body');
    return array_shift($this->answers) ?? [0, null, []];
  }
}

// Encryption
$crypto = new quickbooks_crypto('demo');
$stored = $crypto->encrypt('refresh-token-value');
check('the key file is made on first use, readable by its owner only',
      is_file($files . 'demo/quickbooks_sync.key') && (fileperms($files . 'demo/quickbooks_sync.key') & 0777) === 0600);
check('a stored value does not contain the plain text', strpos(base64_decode($stored), 'refresh-token-value') === false);
check('a stored value decrypts', $crypto->decrypt($stored) === 'refresh-token-value');
check('the same text encrypts differently each time', $crypto->encrypt('x') !== $crypto->encrypt('x'));
check('a blank value stays blank', $crypto->encrypt('') === '' && $crypto->decrypt('') === '');
$altered = base64_encode(substr_replace(base64_decode($stored), 'X', -1));
check('an altered value does not decrypt', $crypto->decrypt($altered) === null);
mkdir($files . 'other', 0700);
check("another company's key does not decrypt it", (new quickbooks_crypto('other'))->decrypt($stored) === null);
rename($files . 'demo/quickbooks_sync.key', $files . 'demo/moved.key');
check('without its key file a value does not decrypt, and no key is made',
      $crypto->decrypt($stored) === null && !is_file($files . 'demo/quickbooks_sync.key'));

// The authorisation link
$oauth = new canned_oauth('CLIENT', 'SECRET');
check('the redirect URI comes from the server URL', quickbooks_oauth::redirect_uri() === 'https://erp.example.com/oauth_return.php');
$url = $oauth->authorize_url(quickbooks_oauth::redirect_uri(), 'quickbooks_sync.STATE');
parse_str(parse_url($url, PHP_URL_QUERY), $query);
check('the link goes to Intuit\'s authorisation page', strpos($url, 'https://appcenter.intuit.com/connect/oauth2?') === 0, $url);
check('the link asks for the accounting scope and a code',
      $query['scope'] === 'com.intuit.quickbooks.accounting' && $query['response_type'] === 'code');
check('the link carries state, client and redirect',
      $query['state'] === 'quickbooks_sync.STATE' && $query['client_id'] === 'CLIENT' && $query['redirect_uri'] === quickbooks_oauth::redirect_uri());

// Token requests
$basic = 'Authorization: Basic ' . base64_encode('CLIENT:SECRET');
$oauth->answers[] = [200, ['access_token' => 'A1', 'refresh_token' => 'R1', 'expires_in' => 3600, 'x_refresh_token_expires_in' => 8726400]];
$tokens = $oauth->exchange_code('CODE', 'https://erp.example.com/cb');
parse_str($oauth->sent[0]['body'], $form);
check('a code exchange returns the tokens and both lifetimes',
      $tokens === ['refresh_token' => 'R1', 'access_token' => 'A1', 'expires_in' => 3600, 'refresh_expires_in' => 8726400]);
check('a code exchange sends the code and redirect, not the secret, in the body',
      $form === ['grant_type' => 'authorization_code', 'code' => 'CODE', 'redirect_uri' => 'https://erp.example.com/cb']);
check('a code exchange goes to Intuit\'s token address with Basic authentication',
      $oauth->sent[0]['url'] === quickbooks_oauth::TOKEN_URL && in_array($basic, $oauth->sent[0]['headers'], true));

$oauth->answers[] = [200, ['access_token' => 'A2', 'refresh_token' => 'R2']];
$tokens = $oauth->refresh('R1');
parse_str($oauth->sent[1]['body'], $form);
check('a refresh sends the old refresh token and returns the new one', $form['refresh_token'] === 'R1' && $tokens['refresh_token'] === 'R2');
check('a missing expires_in reads as 3600 seconds, a missing refresh lifetime as unknown',
      $tokens['expires_in'] === 3600 && $tokens['refresh_expires_in'] === 0);

$oauth->answers[] = [400, ['error' => 'invalid_grant']];
try { $oauth->refresh('R0'); $code = null; } catch (quickbooks_auth_error $e) { $code = $e->getCode(); $message = $e->getMessage(); }
check('a refused refresh token is told apart', $code === quickbooks_oauth::INVALID_GRANT, (string)$code);
check('the refusal names Intuit\'s reason', isset($message) && strpos($message, 'invalid_grant') !== false);
$oauth->answers[] = [500, null];
try { $oauth->refresh('R0'); $code = null; } catch (quickbooks_auth_error $e) { $code = $e->getCode(); }
check('a server error is not taken for a refused token', $code === 0);
$oauth->answers[] = [200, ['access_token' => 'A3']];
try { $oauth->refresh('R0'); $ok = false; } catch (quickbooks_auth_error $e) { $ok = true; }
check('an answer without a refresh token is refused', $ok);

$oauth->answers[] = [200, null];
$oauth->revoke('R2');
$revoke = end($oauth->sent);
check('a revoke sends the refresh token as JSON, with Basic authentication',
      $revoke['url'] === quickbooks_oauth::REVOKE_URL && json_decode($revoke['body'], true) === ['token' => 'R2']
      && in_array('Content-Type: application/json', $revoke['headers'], true) && in_array($basic, $revoke['headers'], true));

// API calls
$connection = new fixed_connection('demo');
$api = new canned_api($connection);
$api->answers[] = [200, ['CompanyInfo' => ['CompanyName' => 'Sandbox Company_GB_1']], []];
check('the company name is read from CompanyInfo', $api->company_name() === 'Sandbox Company_GB_1');
check('a sandbox call goes to the sandbox address, for the company, with minor version 75',
      $api->sent[0]['url'] === 'https://sandbox-quickbooks.api.intuit.com/v3/company/4620816365/companyinfo/4620816365?minorversion=75', $api->sent[0]['url']);
check('a call sends the access token', in_array('Authorization: Bearer TOKEN', $api->sent[0]['headers'], true));

$connection->environment = 'production';
$api->answers[] = [200, ['QueryResponse' => ['TaxCode' => [['Name' => '20.0% S'], ['Name' => 'No VAT']]]], []];
$codes = $api->tax_codes();
$sent = end($api->sent);
parse_str(parse_url($sent['url'], PHP_URL_QUERY), $query);
check('a production call goes to the production address', strpos($sent['url'], 'https://quickbooks.api.intuit.com/v3/company/4620816365/query?') === 0, $sent['url']);
check('a query names the entity, a page and the minor version',
      $query['query'] === 'SELECT * FROM TaxCode WHERE Active = true STARTPOSITION 1 MAXRESULTS 1000' && $query['minorversion'] === '75');
check('a short page ends the query', count($codes) === 2 && count($api->sent) === 2);

$full = array_fill(0, quickbooks_api::PAGE_SIZE, ['Name' => 'x', 'Type' => 'Service']);
$api->answers[] = [200, ['QueryResponse' => ['Item' => $full]], []];
$api->answers[] = [200, ['QueryResponse' => ['Item' => [['Name' => 'Stock', 'Type' => 'Inventory'], ['Name' => 'Post', 'Type' => 'NonInventory']]]], []];
$items = $api->items();
parse_str(parse_url(end($api->sent)['url'], PHP_URL_QUERY), $query);
check('a full page asks for the next one', strpos($query['query'], 'STARTPOSITION 1001 ') !== false, $query['query']);
check('items leave out inventory products', count($items) === quickbooks_api::PAGE_SIZE + 1 && end($items)['Name'] === 'Post');

$api->answers[] = [400, ['Fault' => ['Error' => [['Message' => 'Duplicate Document Number Error', 'Detail' => 'You must specify a different number.']], 'type' => 'ValidationFault']], []];
try { $api->post('Invoice', ['DocNumber' => '1']); $message = ''; } catch (quickbooks_api_error $e) { $message = $e->getMessage(); }
check('a refusal names QuickBooks\' message and detail', $message === 'Duplicate Document Number Error: You must specify a different number.', $message);
check('a post goes to the entity\'s address', strpos(end($api->sent)['url'], '/4620816365/invoice?minorversion=75') !== false);
$api->answers[] = [200, ['Fault' => ['Error' => [['Message' => 'Stale object']]]], []];
try { $api->post('Invoice', []); $message = ''; } catch (quickbooks_api_error $e) { $message = $e->getMessage(); }
check('a fault in a 200 answer is a refusal too', $message === 'Stale object', $message);
$api->answers[] = [200, ['Invoice' => ['Id' => '145', 'SyncToken' => '0']], []];
check('a saved object is returned', $api->post('Invoice', []) === ['Id' => '145', 'SyncToken' => '0']);
$api->answers[] = [429, null, ['retry-after' => '12']];
try { $api->post('Invoice', []); $wait = null; } catch (quickbooks_rate_limit $e) { $wait = $e->retry_after; }
check('the rate limit is told apart, with its wait', $wait === 12);

array_map('unlink', glob($files . '*/*'));
array_map('rmdir', glob($files . '*'));
rmdir($files);
echo "\n" . ($failed ? "$failed checks failed" : 'all checks passed') . "\n";
exit($failed ? 1 : 0);
