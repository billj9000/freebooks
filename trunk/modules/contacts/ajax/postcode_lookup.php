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
//  Path: /modules/contacts/ajax/postcode_lookup.php
//
$security_level = validate_ajax_user();
require_once(DIR_FS_MODULES . 'contacts/classes/postcode_lookup.php');

$add_type = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['add_type'] ?? '');
$postcode = trim($_GET['address'][$add_type]['postal_code'] ?? '');
$country  = trim($_GET['address'][$add_type]['country_code'] ?? (defined('POSTCODE_LOOKUP_COUNTRY') ? POSTCODE_LOOKUP_COUNTRY : 'GB'));

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
       . ' onclick="fillAddressFromLookup(this, \'' . htmlspecialchars($add_type, ENT_QUOTES) . '\')">'
       . htmlspecialchars($label) . '</button>';
}
echo '</div>';
