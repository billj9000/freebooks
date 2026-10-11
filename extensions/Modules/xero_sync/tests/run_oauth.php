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
//  Path: /modules/xero_sync/tests/run_oauth.php
//
// Checks the parts of the Xero connection that need neither a database nor Xero: the encryption
// of stored secrets, the PKCE challenge, the authorisation link, and how token answers and
// refusals are read. Xero's answers are canned. Run it where PHP has sodium:
//
//   php tests/run_oauth.php
//
// Exits 1 when any check fails.
//
if (PHP_SAPI !== 'cli') exit;

$base = dirname(__DIR__);
$files = sys_get_temp_dir() . '/xero_sync_test_' . bin2hex(random_bytes(4)) . '/';
mkdir($files . 'demo', 0700, true);
define('DIR_FS_MY_FILES', $files);
define('DIR_WS_FULL_PATH', 'https://erp.example.com/');
require($base . '/language/en_us/language.php');
require($base . '/classes/xero_crypto.php');
require($base . '/classes/xero_oauth.php');

$failed = 0;
function check(string $name, bool $ok, string $detail = ''): void {
  global $failed;
  if (!$ok) $failed++;
  echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : "\n     $detail") . "\n";
}

// An OAuth client whose requests are answered from a queue and recorded.
class canned_oauth extends xero_oauth {
  public array $answers = [];
  public array $sent = [];
  protected function request(string $method, string $url, array $headers, ?string $body = null): array {
    $this->sent[] = compact('method', 'url', 'headers', 'body');
    return array_shift($this->answers) ?? [0, null];
  }
}

// Encryption
$crypto = new xero_crypto('demo');
$stored = $crypto->encrypt('refresh-token-value');
check('the key file is made on first use, readable by its owner only',
      is_file($files . 'demo/xero_sync.key') && (fileperms($files . 'demo/xero_sync.key') & 0777) === 0600);
check('a stored value does not contain the plain text', strpos(base64_decode($stored), 'refresh-token-value') === false);
check('a stored value decrypts', $crypto->decrypt($stored) === 'refresh-token-value');
check('the same text encrypts differently each time', $crypto->encrypt('x') !== $crypto->encrypt('x'));
check('a blank value stays blank', $crypto->encrypt('') === '' && $crypto->decrypt('') === '');
$altered = base64_encode(substr_replace(base64_decode($stored), 'X', -1));
check('an altered value does not decrypt', $crypto->decrypt($altered) === null);
mkdir($files . 'other', 0700);
check("another company's key does not decrypt it", (new xero_crypto('other'))->decrypt($stored) === null);
rename($files . 'demo/xero_sync.key', $files . 'demo/moved.key');
check('without its key file a value does not decrypt, and no key is made',
      $crypto->decrypt($stored) === null && !is_file($files . 'demo/xero_sync.key'));

// PKCE: the example in RFC 7636, appendix B
check('the S256 challenge matches RFC 7636', xero_oauth::pkce_challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk') === 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
[$verifier, $challenge] = xero_oauth::pkce_pair();
check('a verifier is 43 to 128 unreserved characters', (bool)preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier));
check('a pair belongs together', xero_oauth::pkce_challenge($verifier) === $challenge);

// The authorisation link
$oauth = new canned_oauth('CLIENT', '');
check('the redirect URI comes from the server URL', xero_oauth::redirect_uri() === 'https://erp.example.com/oauth_return.php');
$url = $oauth->authorize_url(xero_oauth::redirect_uri(), 'STATE', 'CHALLENGE');
parse_str(parse_url($url, PHP_URL_QUERY), $query);
check('the link asks for the scopes, encoded with %20', strpos($url, 'scope=openid%20profile%20email%20offline_access%20accounting.invoices%20accounting.contacts%20accounting.settings') !== false && strpos($url, '+') === false, $url);
check('the link carries state, client, redirect and S256 challenge',
      $query['state'] === 'STATE' && $query['client_id'] === 'CLIENT' && $query['code_challenge'] === 'CHALLENGE'
      && $query['code_challenge_method'] === 'S256' && $query['redirect_uri'] === xero_oauth::redirect_uri());

// Token requests
$oauth->answers[] = [200, ['access_token' => 'A1', 'refresh_token' => 'R1', 'expires_in' => 1800]];
$tokens = $oauth->exchange_code('CODE', 'VERIFIER', 'https://erp.example.com/cb');
parse_str($oauth->sent[0]['body'], $form);
check('a code exchange returns the tokens', $tokens === ['refresh_token' => 'R1', 'access_token' => 'A1', 'expires_in' => 1800]);
check('a code exchange sends the code, verifier and redirect', $form['grant_type'] === 'authorization_code' && $form['code'] === 'CODE'
      && $form['code_verifier'] === 'VERIFIER' && $form['redirect_uri'] === 'https://erp.example.com/cb');
check('without a client secret there is no Basic authentication',
      !preg_grep('/^Authorization:/', $oauth->sent[0]['headers']));

$secret = new canned_oauth('CLIENT', 'SECRET');
$secret->answers[] = [200, ['access_token' => 'A2', 'refresh_token' => 'R2']];
$tokens = $secret->refresh('R1');
parse_str($secret->sent[0]['body'], $form);
check('a refresh sends the old refresh token and returns the new one', $form['refresh_token'] === 'R1' && $tokens['refresh_token'] === 'R2');
check('a missing expires_in reads as 1800 seconds', $tokens['expires_in'] === 1800);
check('with a client secret, Basic authentication is sent',
      in_array('Authorization: Basic ' . base64_encode('CLIENT:SECRET'), $secret->sent[0]['headers'], true));

$secret->answers[] = [400, ['error' => 'invalid_grant']];
try { $secret->refresh('R0'); $code = null; } catch (xero_auth_error $e) { $code = $e->getCode(); $message = $e->getMessage(); }
check('a refused refresh token is told apart', $code === xero_oauth::INVALID_GRANT, (string)$code);
check('the refusal names Xero\'s reason', isset($message) && strpos($message, 'invalid_grant') !== false);
$secret->answers[] = [500, null];
try { $secret->refresh('R0'); $code = null; } catch (xero_auth_error $e) { $code = $e->getCode(); }
check('a server error is not taken for a refused token', $code === 0);
$secret->answers[] = [200, ['access_token' => 'A3']];
try { $secret->refresh('R0'); $ok = false; } catch (xero_auth_error $e) { $ok = true; }
check('an answer without a refresh token is refused', $ok);

// Organisations
$oauth->answers[] = [200, [['tenantId' => 'T1', 'tenantName' => 'Demo Ltd', 'tenantType' => 'ORGANISATION'],
                           ['tenantId' => 'P1', 'tenantName' => 'Practice', 'tenantType' => 'PRACTICEMANAGER']]];
$tenants = $oauth->connections('A1');
check('only organisations are offered', count($tenants) === 1 && $tenants[0]['tenantId'] === 'T1');
check('the connections call sends the access token', in_array('Authorization: Bearer A1', end($oauth->sent)['headers'], true));

array_map('unlink', glob($files . '*/*'));
array_map('rmdir', glob($files . '*'));
rmdir($files);
echo "\n" . ($failed ? "$failed checks failed" : 'all checks passed') . "\n";
exit($failed ? 1 : 0);
