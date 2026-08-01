<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/ajax/sku_search.php
//
// Returns a list-group fragment of inventory items matching a SKU prefix
// or a vendor part number (which lives inside inventory_purchase_details.
// description_purchase - there's no dedicated vendor part number column,
// vendors' part numbers are conventionally embedded in that free-text field),
// for the Quick Label Print page's live search box. Mirrors the matching
// behavior of the Inventory Manager's own search box (pre_process.php),
// minus the description_short/description_sales fields, which aren't
// useful for a "find the part number" lookup.

validate_ajax_user(SECURITY_ID_LABELS_PRINT);

gen_pull_language('labels');

$q = db_prepare_input($_GET['q'] ?? '');
if (strlen($q) < 3) {
  exit;
}

$term = db_input($q);
$result = $db->Execute(
  "SELECT DISTINCT i.sku, i.description_short"
  . " FROM " . TABLE_INVENTORY . " i"
  . " LEFT JOIN " . TABLE_INVENTORY_PURCHASE . " p ON p.sku = i.sku"
  . " WHERE i.inactive = '0' AND (i.sku LIKE '" . $term . "%' OR p.description_purchase LIKE '%" . $term . "%')"
  . " ORDER BY (i.sku LIKE '" . $term . "%') DESC, i.sku"
  . " LIMIT 15"
);

if ($result->EOF) {
  echo '<div class="list-group-item text-muted small">' . TEXT_SKU_NO_MATCHES . '</div>';
}
while (!$result->EOF) {
  $sku  = $result->fields['sku'];
  $desc = $result->fields['description_short'];
  echo '<button type="button" class="list-group-item list-group-item-action py-1 px-2 small"'
     . ' onclick="lblSelectSku(' . htmlspecialchars(json_encode($sku), ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars(json_encode($desc), ENT_QUOTES, 'UTF-8') . ')">'
     . '<strong>' . htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') . '</strong> &mdash; ' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')
     . '</button>';
  $result->MoveNext();
}
