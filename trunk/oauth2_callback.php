<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
//  Path: /oauth2_callback.php
//
// Redirect target for OAuth2 authorization flows (Gmail / Office 365).
// Exchanges the authorization code for a refresh token, then passes
// the token back to the parent admin window via window.opener.
//
// This file must be reachable without FreeBooks authentication because
// the OAuth2 provider redirects the user's browser here directly.
// The user's session must already be active (they initiated the flow
// from the admin panel while logged in).
//
session_start();

// Minimal bootstrap — just enough to reach the database
if (!file_exists('includes/configure.php')) { die('Configuration not found.'); }
require('includes/configure.php');
if (!defined('DB_TYPE')) { die('DB_TYPE not defined.'); }
require_once(DIR_FS_ADMIN . 'includes/db/' . DB_TYPE . '/query_factory.php');

function oc_die($message) {
  $msg = htmlspecialchars($message);
  echo "<!DOCTYPE html><html><head><title>OAuth2 Callback</title></head><body>
  <p style='color:#a94442;font-family:sans-serif'>$msg</p>
  <script>if(window.opener&&window.opener.oauth2Failed)window.opener.oauth2Failed(" . json_encode($message) . ");</script>
  </body></html>";
  exit;
}

// PATH_TO_MY_FILES is normally defined by application_top.php; define it here since we skip that bootstrap.
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');

// Validate state / check for error from provider
if (!empty($_GET['error'])) {
  oc_die($_GET['error_description'] ?? $_GET['error']);
}
if (empty($_GET['code'])) {
  oc_die('No authorization code returned.');
}

// Read provider and client credentials from the DB (loaded after connecting)
if (empty($_SESSION['company'])) { oc_die('Session expired. Log in again and retry.'); }

// DB_DATABASE = company name, set explicitly (mirrors application_top.php)
define('DB_DATABASE', $_SESSION['company']);
$company_dir = DIR_FS_ADMIN . PATH_TO_MY_FILES . $_SESSION['company'] . '/';
if (!file_exists($company_dir . 'config.php')) { oc_die('Company config not found.'); }
require_once($company_dir . 'config.php');
if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);

$db = new queryFactory();
if (!$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE)) {
  oc_die('Cannot connect to database.');
}

// Load OAuth2 config from DB
$result = $db->Execute("SELECT configuration_key, configuration_value FROM " . DB_PREFIX . "configuration
  WHERE configuration_key IN ('EMAIL_OAUTH2_PROVIDER','EMAIL_OAUTH2_CLIENT_ID','EMAIL_OAUTH2_CLIENT_SECRET','EMAIL_OAUTH2_TENANT')");
$cfg = array();
while (!$result->EOF) {
  $cfg[$result->fields['configuration_key']] = $result->fields['configuration_value'];
  $result->MoveNext();
}

$provider      = $cfg['EMAIL_OAUTH2_PROVIDER']      ?? 'google';
$client_id     = $cfg['EMAIL_OAUTH2_CLIENT_ID']     ?? '';
$client_secret = $cfg['EMAIL_OAUTH2_CLIENT_SECRET'] ?? '';
$tenant        = $cfg['EMAIL_OAUTH2_TENANT']        ?? 'common';

if (!$client_id || !$client_secret) {
  oc_die('Client ID or secret not saved yet. Save the form before authorising.');
}

// Build redirect_uri (must match what was sent to the provider)
$redirect_uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
  . '://' . $_SERVER['HTTP_HOST']
  . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/oauth2_callback.php';

// Exchange authorization code for tokens
if ($provider === 'google') {
  $token_url = 'https://oauth2.googleapis.com/token';
} else {
  $token_url = 'https://login.microsoftonline.com/' . urlencode($tenant) . '/oauth2/v2.0/token';
}

$post_data = http_build_query(array(
  'code'          => $_GET['code'],
  'client_id'     => $client_id,
  'client_secret' => $client_secret,
  'redirect_uri'  => $redirect_uri,
  'grant_type'    => 'authorization_code',
));

if (!function_exists('curl_init')) { oc_die('PHP cURL extension is required for OAuth2.'); }
$ch = curl_init($token_url);
curl_setopt_array($ch, array(
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $post_data,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 15,
  CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
));
$response = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

if (!$response) { oc_die('Token request failed: ' . $curl_err); }
$json = json_decode($response, true);
if (!empty($json['error'])) { oc_die($json['error_description'] ?? $json['error']); }
if (empty($json['refresh_token'])) { oc_die('No refresh token returned. Ensure the app requests offline_access / access_type=offline and prompt=consent.'); }

$refresh_token = $json['refresh_token'];

// Store refresh token in DB configuration table
$db->Execute("UPDATE " . DB_PREFIX . "configuration SET configuration_value = '" . addslashes($refresh_token) . "'
  WHERE configuration_key = 'EMAIL_OAUTH2_REFRESH_TOKEN'");

// Pass token back to parent window and close popup
$token_js = json_encode($refresh_token);
echo "<!DOCTYPE html><html><head><title>Authorised</title></head><body>
<p style='font-family:sans-serif;color:#3c763d'>Authorised successfully. This window will close.</p>
<script>
if (window.opener && window.opener.oauth2Complete) {
  window.opener.oauth2Complete($token_js);
} else {
  document.write('<p>Close this window and reload the admin page.</p>');
}
window.close();
</script>
</body></html>";
exit;
?>
