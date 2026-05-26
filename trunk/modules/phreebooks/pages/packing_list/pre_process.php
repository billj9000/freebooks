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
//  Path: /modules/phreebooks/pages/packing_list/pre_process.php
//
$security_level = validate_user(0, true);
/**************  include page specific files  *********************/
gen_pull_language('contacts');
gen_pull_language('shipping');
require_once(DIR_FS_WORKING . 'defaults.php');
if (defined('MODULE_SHIPPING_STATUS')) {
    require_once(DIR_FS_MODULES . 'shipping/defaults.php');
    require_once(DIR_FS_MODULES . 'shipping/classes/box_packer.php');
}
/**************   page specific initialization  *************************/
$order_id = (int)db_prepare_input($_GET['id'] ?? 0);
if (!$order_id) { die(htmlspecialchars(PKL_ERROR_NO_ORDER)); }

$order_rs = $db->Execute("SELECT * FROM " . TABLE_JOURNAL_MAIN . " WHERE id = '" . $order_id . "'");
if ($order_rs->EOF) { die(htmlspecialchars(PKL_ERROR_NOT_FOUND)); }
$order = $order_rs->fields;

// Load line items (sku rows only, excluding total/tax rows)
$items_rs = $db->Execute(
    "SELECT sku, qty, description FROM " . TABLE_JOURNAL_ITEM
    . " WHERE ref_id = '" . $order_id . "' AND sku != '' ORDER BY item_cnt ASC"
);
$order_items = [];
while (!$items_rs->EOF) {
    $sku = $items_rs->fields['sku'];
    $order_items[$sku] = [
        'sku'         => $sku,
        'qty'         => (float)$items_rs->fields['qty'],
        'description' => $items_rs->fields['description'],
    ];
    $items_rs->MoveNext();
}

// Parse ship_packages JSON
$packages = [];
if (!empty($order['ship_packages'])) {
    $decoded = json_decode($order['ship_packages'], true);
    if (is_array($decoded)) $packages = $decoded;
}

// Parse carrier + service from shipper_code (stored as "carrier:service")
$shipper_parts = explode(':', $order['shipper_code'] ?? '', 2);
$ship_carrier  = $shipper_parts[0] ?? '';
$ship_service  = $shipper_parts[1] ?? '';

// Resolve human-readable service name from carrier language constant, if available
$service_label = $ship_service;
if ($ship_carrier !== '' && $ship_service !== '') {
    $const = strtoupper($ship_carrier) . '_' . $ship_service;
    if (defined($const)) $service_label = constant($const);
}

// Dimension and weight unit labels
$dim_unit = defined('SHIPPING_DEFAULT_PKG_DIM_UNIT') ? SHIPPING_DEFAULT_PKG_DIM_UNIT : 'MM';
$wt_unit  = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')  ? SHIPPING_DEFAULT_WEIGHT_UNIT  : 'KGS';

/*****************   prepare to display templates  *************************/
$custom_html      = true;
$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
define('PAGE_TITLE', PKL_PAGE_TITLE);
