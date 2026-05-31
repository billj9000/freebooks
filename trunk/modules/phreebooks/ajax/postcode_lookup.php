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
//  Path: /modules/phreebooks/ajax/postcode_lookup.php
//
$security_level = validate_ajax_user();
require_once(DIR_FS_MODULES . 'contacts/classes/postcode_lookup.php');

$prefix   = preg_replace('/[^a-z]/', '', $_GET['prefix'] ?? '');
$postcode = trim($_GET[$prefix . '_postal_code']  ?? '');
$country  = trim($_GET[$prefix . '_country_code'] ?? (defined('POSTCODE_LOOKUP_COUNTRY') ? POSTCODE_LOOKUP_COUNTRY : 'GB'));

$provider = PostcodeLookup::active();
if (!$provider || $postcode === '') {
    echo '<p class="text-muted small mb-0">Enter a postcode and click Find Address.</p>';
    exit;
}

$candidates = $provider->lookup($postcode, $country);

if (empty($candidates)) {
    echo '<p class="text-warning small mb-0">No addresses found for this postcode.</p>';
    exit;
}

echo '<div class="list-group mt-1" style="max-height:200px;overflow-y:auto;">';
foreach ($candidates as $c) {
    $label = implode(', ', array_filter([
        $c['primary_name'], $c['address1'], $c['address2'], $c['city_town'], $c['state_province'], $c['postal_code'],
    ]));
    $attrs = '';
    foreach (['primary_name', 'address1', 'address2', 'city_town', 'state_province', 'postal_code'] as $f) {
        $attrs .= ' data-' . $f . '="' . htmlspecialchars($c[$f] ?? '', ENT_QUOTES) . '"';
    }
    echo '<button type="button" class="list-group-item list-group-item-action py-1 small"'
       . $attrs
       . ' onclick="fillOrderAddressFromLookup(this, \'' . htmlspecialchars($prefix, ENT_QUOTES) . '\')">'
       . htmlspecialchars($label) . '</button>';
}
echo '</div>';
