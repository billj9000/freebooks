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
// Path: /modules/phreebooks/updates/R37toR38.php
// Adds ships_in_own_box flag to inventory table.

if (!db_field_exists(TABLE_INVENTORY, 'ships_in_own_box')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY
        . " ADD COLUMN ships_in_own_box ENUM('0','1') NOT NULL DEFAULT '0' AFTER customs_description");
}

$exists = $db->Execute("SELECT id FROM " . TABLE_EXTRA_FIELDS
    . " WHERE module_id = 'inventory' AND field_name = 'ships_in_own_box' LIMIT 1");
if ($exists->EOF) {
    $phys_types = 'ia:ma:mb:mi:ms:ns:sa:si:sr:pb';
    $params = serialize(['default' => '0', 'type' => 'check_box', 'inventory_type' => $phys_types]);
    $db->Execute("INSERT INTO " . TABLE_EXTRA_FIELDS
        . " (module_id, tab_id, entry_type, field_name, description, sort_order, group_by, use_in_inventory_filter, params)"
        . " VALUES ('inventory', 0, 'check_box', 'ships_in_own_box', 'Ships in Own Box', 0, 0, '0', '" . db_input($params) . "')");
}
?>
