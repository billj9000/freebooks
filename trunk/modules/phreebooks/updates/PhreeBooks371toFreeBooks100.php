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
//  Path: /modules/phreebooks/updates/PhreeBooks371toFreeBooks100.php
//
// Everything FreeBooks added to the PhreeBooks 3.7.1 schema, in one step.
//
// This was twelve files, R31toR32 through R42toR43, each moving the stamped
// version on by one. They are rolled into one because nobody outside this
// project has ever had a FreeBooks database: there is no installation anywhere
// sitting at 3.75 or 3.80 that needs to be walked up a rung at a time. The only
// journey that exists is PhreeBooks 3.7.1 to FreeBooks 1.0.0, and it is made
// once. After 1.0.0 is released that stops being true and migrations go back to
// being one per release, appended and never merged.
//
// The sections are marked with the migration each came from, so the reason for
// any given column is still findable in the history.
//
// Every statement is guarded, so running this against a database that already
// has some of it is safe - which is what makes it re-runnable after a failure
// part way through, since nothing is stamped until the end.

// ---- was R31toR32 --------------------------------------------------
// Adds freight_tax_id column to journal_main for per-order freight tax rate selection.
// Existing rows default to 0, which preserves prior behaviour (falls back to AR_ADD_SALES_TAX_TO_SHIPPING).

if (!db_field_exists(TABLE_JOURNAL_MAIN, 'freight_tax_id')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN freight_tax_id SMALLINT NOT NULL DEFAULT 0 AFTER freight");
}

// ---- was R32toR33 --------------------------------------------------
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

// ---- was R33toR34 --------------------------------------------------
// Adds ship_packages column to journal_main for packing list data.

if (!db_field_exists(TABLE_JOURNAL_MAIN, 'ship_packages')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN ship_packages TEXT NULL DEFAULT NULL AFTER shipper_code");
}

// ---- was R34toR35 --------------------------------------------------
// Adds recipient_name and recipient_email to shipping_log for label email pre-population.

// One guard per column: a pair guarded on the first alone breaks on a database
// that has the second and not the first.
if (!db_field_exists(TABLE_SHIPPING_LOG, 'recipient_name')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN recipient_name varchar(64) NOT NULL DEFAULT '' AFTER notes");
}
if (!db_field_exists(TABLE_SHIPPING_LOG, 'recipient_email')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN recipient_email varchar(100) NOT NULL DEFAULT '' AFTER recipient_name");
}

// ---- was R35toR36 --------------------------------------------------
// Adds vat_number and eori_number to the contacts table.

if (!db_field_exists(TABLE_CONTACTS, 'vat_number')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD COLUMN vat_number  varchar(32) NOT NULL DEFAULT '' AFTER gov_id_number,"
        . " ADD COLUMN eori_number varchar(32) NOT NULL DEFAULT '' AFTER vat_number");
}

// ---- was R36toR37 --------------------------------------------------
// Adds currency_code to inventory_purchase so supplier costs are stored
// in the supplier's native currency rather than company currency.

if (!db_field_exists(TABLE_INVENTORY_PURCHASE, 'currency_code')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE
        . " ADD COLUMN currency_code varchar(3) NOT NULL DEFAULT '' AFTER item_cost");
    $db->Execute("UPDATE " . TABLE_INVENTORY_PURCHASE
        . " SET currency_code = '" . DEFAULT_CURRENCY . "' WHERE currency_code = ''");
}

// ---- was R37toR38 --------------------------------------------------
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

// ---- was R38toR39 --------------------------------------------------
// Adds notes column to journal_main.

if (!db_field_exists(TABLE_JOURNAL_MAIN, 'notes')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN notes TEXT NULL DEFAULT NULL AFTER drop_ship");
}

// ---- was R39toR40 --------------------------------------------------
// Adds carrier_order_id column to shipping_log — stores a carrier's internal
// order/shipment identifier when it differs from the tracking number shown to
// the user (e.g. Royal Mail Click & Drop's orderIdentifier), so cancellation
// can use the correct id instead of the tracking number.

if (!db_field_exists(TABLE_SHIPPING_LOG, 'carrier_order_id')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN carrier_order_id varchar(64) NOT NULL DEFAULT '' AFTER recipient_email");
}

// ---- ordering, which the twelve separate files could not express ----------
// R40toR41 below adds a column AFTER contacts.currency_code, and the only code
// that creates contacts.currency_code is the contacts module's own update() -
// which the upgrade cascade never calls, because contacts is already at its own
// version. Split across two modules with no ordering between them, that killed
// the upgrade at exactly this point: "Unknown column 'currency_code'", exit 0,
// nothing on screen. In one file the order can simply be stated.
if (!db_field_exists(TABLE_CONTACTS, 'currency_code')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD currency_code VARCHAR(3) NOT NULL DEFAULT '' AFTER price_sheet");
}

// ---- was R40toR41 --------------------------------------------------
// Gives a vendor's part number a real home and adds purchase order export
// profiles.
//
// vendor_sku on inventory_purchase holds the supplier's own part number for an
// item. Until now it was conventionally typed as the first word of the
// free-text description_purchase; that convention still works as a fallback, so
// nothing needs migrating - see cron/backfill_vendor_sku.php to populate the
// new column from existing descriptions.
//
// purchase_export_profile describes the line format a supplier's ordering
// system expects, and contacts.export_profile_id assigns one to a vendor.

if (!db_field_exists(TABLE_INVENTORY_PURCHASE, 'vendor_sku')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE
        . " ADD COLUMN vendor_sku varchar(32) NOT NULL DEFAULT '' AFTER vendor_id");
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE . " ADD INDEX (vendor_sku)");
}

if (!db_field_exists(TABLE_CONTACTS, 'export_profile_id')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD COLUMN export_profile_id int(11) NOT NULL DEFAULT '0' AFTER currency_code");
}

$db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_PURCHASE_EXPORT_PROFILE . " (
  id int(11) NOT NULL auto_increment,
  profile_name varchar(64) NOT NULL default '',
  file_extension varchar(8) NOT NULL default 'txt',
  header_template text,
  line_template text,
  footer_template text,
  line_ending varchar(4) NOT NULL default 'crlf',
  inactive int(1) NOT NULL default '0',
  PRIMARY KEY (id)
) ENGINE=InnoDB CHARSET=utf8 COLLATE=utf8_unicode_ci;");

// ---- was R41toR42 --------------------------------------------------
// Adds a per-line "Re-stock" flag to credit memos, so a line item can be
// credited to the customer/vendor without its quantity returning to
// inventory (item lost or failed in the field - see issue #71). Default
// '1' so every existing row, and every journal type that never shows the
// checkbox, keeps today's behaviour exactly.

if (!db_field_exists(TABLE_JOURNAL_ITEM, 'restock')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_ITEM
        . " ADD COLUMN restock enum('0','1') NOT NULL DEFAULT '1' AFTER taxable");
}

// ---- was R42toR43 --------------------------------------------------
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

// ---- the users table -----------------------------------------------
// admin_pass holds a bcrypt hash, which needs 255 characters; 3.7.1 sized it at
// 40 for a sha1 digest. The four that follow carry brute-force lockout and
// password reset.
//
// One guard per column: a pair guarded on the first alone breaks on a database
// that has the second and not the first.
if (db_field_exists(TABLE_USERS, 'admin_pass')) {
    $db->Execute("ALTER TABLE " . TABLE_USERS . " MODIFY admin_pass varchar(255) NOT NULL DEFAULT ''");
}
foreach (array(
    'failed_attempts'  => "int(11) NOT NULL DEFAULT '0'",
    'locked_until'     => "datetime NULL DEFAULT NULL",
    'pw_reset_token'   => "char(64) NULL DEFAULT NULL COMMENT 'sha-256 of the emailed token, never the token'",
    'pw_reset_expires' => "datetime NULL DEFAULT NULL",
) as $column => $declaration) {
    if (!db_field_exists(TABLE_USERS, $column)) {
        $db->Execute("ALTER TABLE " . TABLE_USERS . " ADD COLUMN $column $declaration");
    }
}

// ---- the 1.0.0 schema itself ---------------------------------------------
// Everything above brings a PhreeBooks 3.7.1 database up to the columns
// FreeBooks added. This last part brings it to the shape 1.0.0 installs with:
// utf8mb4 throughout, and dates that can say they are not set.
//
// A new installation is created this way and never runs any of it. An upgraded
// or restored one has to be converted, or it keeps a deprecated charset and a
// zero date that MySQL 8 will not accept - and two different schemas exist in
// the wild for every later migration to cope with.
//
// This is the expensive part of the upgrade. CONVERT TO CHARACTER SET rewrites
// the table, so on a large installation it takes time and holds a lock. It is
// driven from information_schema so a second run finds nothing to do.

// Tables still on the old charset. utf8mb3 is what MariaDB and MySQL both now
// call utf8, and both call it deprecated.
$tbls = $db->Execute("SELECT t.table_name AS t FROM information_schema.tables t
    JOIN information_schema.collation_character_set_applicability c
      ON c.collation_name = t.table_collation
    WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'
      AND c.character_set_name <> 'utf8mb4'");
while (!$tbls->EOF) {
    $db->Execute("ALTER TABLE `" . $tbls->fields['t'] . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $tbls->MoveNext();
}

// cred_id is deliberately ascii: it holds base64url and carries a UNIQUE key at
// varchar(500), which does not fit an index once every character costs four
// bytes. CONVERT TO CHARACTER SET above would have taken it with the rest.
if (db_table_exists(TABLE_WEBAUTHN_CREDS) && db_field_exists(TABLE_WEBAUTHN_CREDS, 'cred_id')) {
    $db->Execute("ALTER TABLE " . TABLE_WEBAUTHN_CREDS
        . " MODIFY cred_id varchar(500) CHARACTER SET ascii NOT NULL DEFAULT ''"
        . " COMMENT 'base64url-encoded credential id'");
}

// Date columns that cannot say "no date", and the zero dates standing in for it.
// The column is widened first so the values have somewhere to go.
$cols = $db->Execute("SELECT table_name AS t, column_name AS c, column_type AS ty
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND data_type IN ('date','datetime')
      AND is_nullable = 'NO' AND column_default LIKE '%0000-00-00%'");
$zero_date_columns = array();
while (!$cols->EOF) {
    $zero_date_columns[] = array($cols->fields['t'], $cols->fields['c'], $cols->fields['ty']);
    $cols->MoveNext();
}
foreach ($zero_date_columns as $col) {
    list($t, $c, $ty) = $col;
    $db->Execute("ALTER TABLE `$t` MODIFY `$c` $ty NULL DEFAULT NULL");
    $db->Execute("UPDATE `$t` SET `$c` = NULL WHERE `$c` LIKE '0000-00-00%'");
}

// Two columns the installer and the update chain declare differently. The
// installer's declaration is the one to reach.
//
// tax_id is NOT NULL: -1 is how a contact says it has no tax id.
//
// currencies_code takes an empty default. A column default here would freeze
// one installation's currency into the schema; a company's currency belongs in
// its configuration.
if (db_field_exists(TABLE_CONTACTS, 'tax_id')) {
    $db->Execute("UPDATE " . TABLE_CONTACTS . " SET tax_id = -1 WHERE tax_id IS NULL");
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS . " MODIFY tax_id int(11) NOT NULL DEFAULT '-1'");
}
if (db_field_exists(TABLE_JOURNAL_MAIN, 'currencies_code')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN . " MODIFY currencies_code char(3) NOT NULL DEFAULT ''");
}
