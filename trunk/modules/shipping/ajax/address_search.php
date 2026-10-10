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
//  Path: /modules/shipping/ajax/address_search.php
//
// The customers and vendors whose name, contact, account id, postcode or email contains the
// search text, each with its main and delivery addresses, as buttons that fill a label form's
// address (shipping/functions/address_tools.php). Returned as HTML for htmx.
$security_level = validate_ajax_user();
gen_pull_language('shipping');

$term = trim((string)($_GET['q'] ?? ''));
if (strlen($term) < 2) {
  echo '<p class="text-muted small mb-0">' . SHIPPING_FIND_CONTACT_SHORT . '</p>';
  exit;
}
$like = "'%" . db_input($term) . "%'";
$found = $db->Execute("SELECT DISTINCT c.id, c.type, c.short_name FROM " . TABLE_CONTACTS . " c
  JOIN " . TABLE_ADDRESS_BOOK . " a ON a.ref_id = c.id
  WHERE c.type IN ('c', 'v') AND c.inactive = '0'
    AND (c.short_name LIKE $like OR a.primary_name LIKE $like OR a.contact LIKE $like
      OR a.postal_code LIKE $like OR a.email LIKE $like)
  ORDER BY c.type, c.short_name LIMIT 20");
$ids = array();
while (!$found->EOF) { $ids[] = (int)$found->fields['id']; $found->MoveNext(); }
if (!$ids) {
  echo '<p class="text-warning small mb-0">' . SHIPPING_FIND_CONTACT_NONE . '</p>';
  exit;
}
$rows = $db->Execute("SELECT a.type AS add_type, a.primary_name, a.contact, a.address1, a.address2,
    a.city_town, a.state_province, a.postal_code, a.country_code, a.telephone1, a.email,
    c.id, c.type, c.short_name, c.vat_number, c.eori_number
  FROM " . TABLE_ADDRESS_BOOK . " a JOIN " . TABLE_CONTACTS . " c ON c.id = a.ref_id
  WHERE a.ref_id IN (" . implode(',', $ids) . ") AND a.type IN ('cm', 'cs', 'vm', 'vs')
  ORDER BY c.type, c.short_name, c.id, a.type, a.primary_name");

$fill = array('primary_name', 'contact', 'address1', 'address2', 'city_town', 'state_province',
  'postal_code', 'country_code', 'telephone1', 'email', 'vat_number', 'eori_number');
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
echo '<div class="list-group" style="max-height:360px;overflow-y:auto;">';
$current = null;
while (!$rows->EOF) {
  $r = $rows->fields;
  if ($r['id'] !== $current) {
    $current = $r['id'];
    echo '<div class="list-group-item list-group-item-secondary py-1 small fw-bold">' . $h($r['short_name'])
       . ' <span class="fw-normal">&mdash; ' . ($r['type'] === 'v' ? SHIPPING_CONTACT_VENDOR : SHIPPING_CONTACT_CUSTOMER) . '</span></div>';
  }
  $label = implode(', ', array_filter(array($r['primary_name'], $r['address1'], $r['city_town'], $r['postal_code'], $r['country_code'])));
  $kind  = substr($r['add_type'], 1) === 'm' ? SHIPPING_ADDRESS_MAIN : SHIPPING_ADDRESS_DELIVERY;
  $attrs = '';
  foreach ($fill as $f) $attrs .= ' data-' . str_replace('_', '-', $f) . '="' . $h($r[$f] ?? '') . '"';
  echo '<button type="button" class="list-group-item list-group-item-action py-1 small"' . $attrs
     . ' onclick="shipFillFromContact(this)"><span class="text-muted">' . $kind . ':</span> ' . $h($label) . '</button>';
  $rows->MoveNext();
}
echo '</div>';
