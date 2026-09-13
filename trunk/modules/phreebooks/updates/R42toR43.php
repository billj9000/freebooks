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
// Path: /modules/phreebooks/updates/R42toR43.php
// Adds ship_complete flag to inventory - says whether a Product Bundle travels
// as one parcel or as its separate parts. Default '0' (ships as parts), which
// matches how a bundle's pieces have always been quoted, so no existing bundle
// changes behaviour on upgrade.
//
// Deliberately NOT ships_in_own_box: that one means "needs no outer box" and
// only applies to a single-item order. These answer different questions and a
// bundle may well want both.

if (!db_field_exists(TABLE_INVENTORY, 'ship_complete')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY
        . " ADD COLUMN ship_complete ENUM('0','1') NOT NULL DEFAULT '0' AFTER ships_in_own_box");
}

// Only the two bundle types have a Bill of Materials to ship as parts, so the
// question is meaningless for every other inventory type and the field is hidden
// from them. A type missing from this list has the property unset() on load (see
// inventory::remove_unwanted_keys()), which is exactly what we want here.
$exists = $db->Execute("SELECT id FROM " . TABLE_EXTRA_FIELDS
    . " WHERE module_id = 'inventory' AND field_name = 'ship_complete' LIMIT 1");
if ($exists->EOF) {
    $params = serialize(['default' => '0', 'type' => 'check_box', 'inventory_type' => 'pb:pc']);
    $db->Execute("INSERT INTO " . TABLE_EXTRA_FIELDS
        . " (module_id, tab_id, entry_type, field_name, description, sort_order, group_by, use_in_inventory_filter, params)"
        . " VALUES ('inventory', 0, 'check_box', 'ship_complete', 'Ships Complete', 0, 0, '0', '" . db_input($params) . "')");
}
?>
