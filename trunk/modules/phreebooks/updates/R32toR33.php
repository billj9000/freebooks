<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
// Path: /modules/phreebooks/updates/R32toR33.php
// Adds shipping/customs columns to inventory table for BoxPacker integration
// and customs declaration on international shipments.

$shipping_cols = [
    'item_length'          => "ADD COLUMN item_length DOUBLE NOT NULL DEFAULT 0 AFTER item_weight",
    'item_width'           => "ADD COLUMN item_width  DOUBLE NOT NULL DEFAULT 0 AFTER item_length",
    'item_height'          => "ADD COLUMN item_height DOUBLE NOT NULL DEFAULT 0 AFTER item_width",
    'country_of_origin'    => "ADD COLUMN country_of_origin VARCHAR(2)  NOT NULL DEFAULT '' AFTER item_height",
    'hs_code'              => "ADD COLUMN hs_code       VARCHAR(20)  NOT NULL DEFAULT '' AFTER country_of_origin",
    'customs_description'  => "ADD COLUMN customs_description VARCHAR(255) NOT NULL DEFAULT '' AFTER hs_code",
];
foreach ($shipping_cols as $col => $ddl) {
    if (!db_field_exists(TABLE_INVENTORY, $col)) {
        $db->Execute("ALTER TABLE " . TABLE_INVENTORY . " " . $ddl);
    }
}

// Register new fields in xtra_fields so what_to_save() persists them.
$phys_types = 'ia:ma:mb:mi:ms:ns:sa:si:sr:pb';
$new_fields = [
    ['item_length',         'decimal', 'Shipping Length'],
    ['item_width',          'decimal', 'Shipping Width'],
    ['item_height',         'decimal', 'Shipping Height'],
    ['country_of_origin',   'text',    'Country of Origin'],
    ['hs_code',             'text',    'HS Code'],
    ['customs_description', 'text',    'Customs Description'],
];
foreach ($new_fields as [$fname, $etype, $desc]) {
    $exists = $db->Execute("SELECT id FROM " . TABLE_EXTRA_FIELDS
        . " WHERE module_id = 'inventory' AND field_name = '" . db_input($fname) . "' LIMIT 1");
    if ($exists->EOF) {
        $params = ($etype === 'decimal')
            ? serialize(['default' => '1', 'type' => 'decimal',   'inventory_type' => $phys_types])
            : serialize(['default' => '',  'type' => 'text',       'inventory_type' => $phys_types, 'length' => ($fname === 'customs_description' ? '255' : ($fname === 'hs_code' ? '20' : '2'))]);
        $db->Execute("INSERT INTO " . TABLE_EXTRA_FIELDS
            . " (module_id, tab_id, entry_type, field_name, description, sort_order, group_by, use_in_inventory_filter, params)"
            . " VALUES ('inventory', 0, '" . db_input($etype) . "', '" . db_input($fname) . "', '" . db_input($desc) . "', 0, 0, '0', '" . db_input($params) . "')");
    }
}
?>
