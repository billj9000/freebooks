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
//  Path: /modules/vin_lookup/ajax/test.php
//
// Settings-page "Test connection" — calls the provider directly with the
// form's current (possibly unsaved) values so the API key / packages /
// endpoint can be verified before Save. Returns a one-line alert fragment.
//
$security_level = validate_ajax_user(SECURITY_ID_CONFIGURATION);
if ($security_level < 3) {
  echo '<div class="alert alert-danger mb-0">' . htmlspecialchars(ERROR_NO_PERMISSION) . '</div>';
  die;
}

gen_pull_language('vin_lookup');
require_once(DIR_FS_MODULES . 'vin_lookup/defaults.php');
require_once(DIR_FS_MODULES . 'vin_lookup/classes/vin_lookup.php');

$vrm      = trim($_POST['test_vrm'] ?? '');
$apiKey   = trim($_POST['vin_lookup_api_key']  ?? '');
$packages = trim($_POST['vin_lookup_packages'] ?? '');
$endpoint = trim($_POST['vin_lookup_endpoint'] ?? '');

if ($vrm === '') {
  echo '<div class="alert alert-warning mb-0">' . htmlspecialchars(VIN_TEXT_PROMPT) . '</div>';
  die;
}

// The server makes this request: only https to the VIN provider's own host,
// so the form cannot point it at an internal address.
if ($endpoint === '') $endpoint = null;
if ($endpoint !== null) {
  $allowed_hosts = array_unique(array_filter(array(
    strtolower((string)parse_url('https://uk.api.vehicledataglobal.com/r2/lookup', PHP_URL_HOST)),
    defined('VIN_LOOKUP_ENDPOINT') ? strtolower((string)parse_url(VIN_LOOKUP_ENDPOINT, PHP_URL_HOST)) : '',
  )));
  $parts = parse_url($endpoint);
  if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
      || isset($parts['port']) || !in_array(strtolower($parts['host'] ?? ''), $allowed_hosts, true)) {
    echo '<div class="alert alert-danger mb-0">'
       . htmlspecialchars(sprintf(VIN_TEST_FAILURE, 0, 'Endpoint must be an https:// URL on ' . implode(' or ', $allowed_hosts)))
       . '</div>';
    die;
  }
}

$result = VinLookup::fetch($vrm, $apiKey, $packages, $endpoint);

if ($result['ok']) {
  echo '<div class="alert alert-success mb-0">'
     . htmlspecialchars(sprintf(VIN_TEST_SUCCESS, $result['http']))
     . '</div>';
} else {
  echo '<div class="alert alert-danger mb-0">'
     . htmlspecialchars(sprintf(VIN_TEST_FAILURE, $result['http'], $result['error']))
     . '</div>';
}
