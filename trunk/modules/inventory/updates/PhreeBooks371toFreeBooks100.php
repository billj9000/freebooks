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
//  Path: /modules/inventory/updates/PhreeBooks371toFreeBooks100.php
//
// The inventory module's tables, from PhreeBooks 3.7.1 to FreeBooks 1.0.0, and
// the inventory rows of xtra_fields.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.
//
// Some visibility lists below are rewritten, not merged, so this must never run
// against a 1.0.0 database: an administrator's own changes to them would be lost.

// ---- inventory: shipping and customs ---------------------------------------
// Dimensions for carton packing, and what a customs declaration needs.
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

// Each column needs an xtra_fields row, or what_to_save() does not save it.
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

// ---- inventory: how an item ships ------------------------------------------
// ships_in_own_box: the item needs no outer carton. It applies to an order of
// that one item alone.
if (!db_field_exists(TABLE_INVENTORY, 'ships_in_own_box')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY
        . " ADD COLUMN ships_in_own_box ENUM('0','1') NOT NULL DEFAULT '0' AFTER customs_description");
}
$exists = $db->Execute("SELECT id FROM " . TABLE_EXTRA_FIELDS
    . " WHERE module_id = 'inventory' AND field_name = 'ships_in_own_box' LIMIT 1");
if ($exists->EOF) {
    $params = serialize(['default' => '0', 'type' => 'check_box', 'inventory_type' => $phys_types]);
    $db->Execute("INSERT INTO " . TABLE_EXTRA_FIELDS
        . " (module_id, tab_id, entry_type, field_name, description, sort_order, group_by, use_in_inventory_filter, params)"
        . " VALUES ('inventory', 0, 'check_box', 'ships_in_own_box', 'Ships in Own Box', 0, 0, '0', '" . db_input($params) . "')");
}

// ship_complete: a Product Bundle travels as one parcel rather than as its parts.
// '0', ships as parts, is how a bundle's pieces were always quoted. Only the two
// bundle types have parts, so the field is shown for them alone.
if (!db_field_exists(TABLE_INVENTORY, 'ship_complete')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY
        . " ADD COLUMN ship_complete ENUM('0','1') NOT NULL DEFAULT '0' AFTER ships_in_own_box");
}
$exists = $db->Execute("SELECT id FROM " . TABLE_EXTRA_FIELDS
    . " WHERE module_id = 'inventory' AND field_name = 'ship_complete' LIMIT 1");
if ($exists->EOF) {
    $params = serialize(['default' => '0', 'type' => 'check_box', 'inventory_type' => 'pb:pc']);
    $db->Execute("INSERT INTO " . TABLE_EXTRA_FIELDS
        . " (module_id, tab_id, entry_type, field_name, description, sort_order, group_by, use_in_inventory_filter, params)"
        . " VALUES ('inventory', 0, 'check_box', 'ship_complete', 'Ships Complete', 0, 0, '0', '" . db_input($params) . "')");
}

// ---- inventory_purchase ----------------------------------------------------
// A supplier's cost is held in the supplier's currency. Existing costs were
// entered in the company's.
if (!db_field_exists(TABLE_INVENTORY_PURCHASE, 'currency_code')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE
        . " ADD COLUMN currency_code varchar(3) NOT NULL DEFAULT '' AFTER item_cost");
    $db->Execute("UPDATE " . TABLE_INVENTORY_PURCHASE
        . " SET currency_code = '" . DEFAULT_CURRENCY . "' WHERE currency_code = ''");
}

// The supplier's own part number. Where it was typed as the first word of
// description_purchase, cron/backfill_vendor_sku.php copies it across.
if (!db_field_exists(TABLE_INVENTORY_PURCHASE, 'vendor_sku')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE
        . " ADD COLUMN vendor_sku varchar(32) NOT NULL DEFAULT '' AFTER vendor_id");
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE . " ADD INDEX (vendor_sku)");
}

// ---- inventory_ms_list -----------------------------------------------------
// attr_0 and attr_1 hold every code:description pair of a master as one list,
// which grows with the business's own data, so no fixed length is safe. Declared
// as the installer declares them.
$db->Execute("ALTER TABLE " . TABLE_INVENTORY_MS_LIST . " CHANGE attr_0 attr_0 mediumtext NULL, CHANGE attr_1 attr_1 mediumtext NULL");

// ---- xtra_fields: which inventory types show each field --------------------
// Product Bundle (pb) is shown wherever an assembly is, except the stock-control
// fields a bundle has no stock of its own for.
$pb_fields = $db->Execute("SELECT id, field_name, params FROM " . TABLE_EXTRA_FIELDS . " WHERE module_id = 'inventory'");
while (!$pb_fields->EOF) {
    $params = unserialize($pb_fields->fields['params']);
    switch ($pb_fields->fields['field_name']) {
        case 'item_taxable': case 'purch_taxable': case 'item_cost': case 'price_sheet':
        case 'price_sheet_v': case 'full_price': case 'full_price_with_tax':
        case 'product_margin': case 'product_markup':
            $params['inventory_type'] = 'ci:ia:lb:ma:mb:mi:ms:ns:pb:sa:sf:si:sr:sv'; break;
        case 'image_with_path': case 'item_weight':
            $params['inventory_type'] = 'ia:ma:mb:mi:ms:ns:pb:sa:si:sr'; break;
        case 'cost_method':
            $params['inventory_type'] = 'ia:ma:mb:mi:ms:ns:pb:si'; break;
        case 'quantity_on_hand':
            $params['inventory_type'] = 'ia:ma:mi:ns:pb:sa:si:sr'; break;
        case 'quantity_on_sales_order':
            $params['inventory_type'] = 'ia:ma:mi:pb:sa:si:sr'; break;
        case 'upc_code':
            $params['inventory_type'] = 'ia:ma:mi:ns:pb:sa:si:sr'; break;
        case 'account_cost_of_sales':
            $params['inventory_type'] = 'ai:ci:ds:ia:lb:ma:mb:mi:ms:ns:pb:sa:sf:si:sr:sv'; break;
        case 'minimum_stock_level': case 'reorder_quantity': case 'lead_time':
        case 'serialize': case 'quantity_on_order': case 'quantity_on_allocation':
            break;
        default:
            if (strpos($params['inventory_type'] ?? '', 'ma') !== false && strpos($params['inventory_type'], 'pb') === false) {
                $params['inventory_type'] = str_replace(':ma:', ':ma:pb:', $params['inventory_type']);
            }
    }
    $db->Execute("UPDATE " . TABLE_EXTRA_FIELDS . " SET params='" . serialize($params) . "' WHERE id='" . $pb_fields->fields['id'] . "'");
    $pb_fields->MoveNext();
}

// Master Product Bundle (mp) is shown wherever a master assembly (mb) is, and
// Product Bundle Part (pc) wherever a Product Bundle is: each is the other's
// structural counterpart.
$mp_fields = $db->Execute("SELECT id, params FROM " . TABLE_EXTRA_FIELDS . " WHERE module_id = 'inventory'");
while (!$mp_fields->EOF) {
    $params  = unserialize($mp_fields->fields['params']);
    $types   = array_filter(explode(':', $params['inventory_type'] ?? ''), function($t) { return $t !== ''; });
    $changed = false;
    if (in_array('mb', $types) && !in_array('mp', $types)) { $types[] = 'mp'; $changed = true; }
    if (in_array('pb', $types) && !in_array('pc', $types)) { $types[] = 'pc'; $changed = true; }
    if ($changed) {
        sort($types);
        $params['inventory_type'] = implode(':', $types);
        $db->Execute("UPDATE " . TABLE_EXTRA_FIELDS . " SET params='" . serialize($params) . "' WHERE id='" . $mp_fields->fields['id'] . "'");
    }
    $mp_fields->MoveNext();
}

// ---- inventory: the price with tax -----------------------------------------
// An item priced anywhere but the inventory page carries 0 as its price with
// tax, or the one it had before its full price changed. Set it from full price
// and the item's own rate, one statement per rate in use. A price typed
// tax-inclusive lies within rounding of that value and is left as typed; the
// tolerance is the one inv_price_with_tax_is_stale() uses.
if (db_field_exists(TABLE_INVENTORY, 'full_price_with_tax')) {
    global $currencies;
    if (!function_exists('inv_customer_tax_rate')) require_once(DIR_FS_MODULES . 'inventory/functions/inventory.php');
    $dp = (int)($currencies->currencies[DEFAULT_CURRENCY]['decimal_places'] ?? 2);
    $tolerance = 2 * pow(10, -$dp);
    $in_use = $db->Execute("SELECT DISTINCT item_taxable AS t FROM " . TABLE_INVENTORY . " WHERE full_price > 0");
    $taxables = array();
    while (!$in_use->EOF) { $taxables[] = (int)$in_use->fields['t']; $in_use->MoveNext(); }
    foreach ($taxables as $taxable) {
        $factor = 1 + inv_customer_tax_rate($taxable) / 100;
        $computed = "ROUND(full_price * " . (float)$factor . ", $dp)";
        $db->Execute("UPDATE " . TABLE_INVENTORY . " SET full_price_with_tax = $computed"
            . " WHERE item_taxable = $taxable AND full_price > 0"
            . " AND (full_price_with_tax = 0 OR ABS(full_price_with_tax - $computed) > " . (float)$tolerance . ")");
    }
}

// ---- column widths ----------------------------------------------------------
// Names, addresses and descriptions arrive from outside - the web shop, carrier
// and payment services - longer than these columns held. A lax server cut them
// to fit without a word (about a thousand addresses and a thousand product
// descriptions were found cut at exactly the width); a strict one refuses the
// whole write, so an order with a long address line would not import at all.
// Widened as the installer now declares them; only ever widened.
db_widen_column(TABLE_INVENTORY, 'description_short', 128, "varchar(128) NOT NULL default ''");
db_widen_column(TABLE_INVENTORY_ASSY_LIST, 'description', 128, "varchar(128) NOT NULL default ''");
