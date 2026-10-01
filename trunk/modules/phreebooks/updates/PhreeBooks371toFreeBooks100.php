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
// The phreebooks module's tables, from PhreeBooks 3.7.1 to FreeBooks 1.0.0.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.
//
// The character set, collation and zero-date conversion of every table is
// phreedom's, which the cascade runs after all the other modules.

// Columns and tables are declared as the installer declares them, so an upgraded
// database and a new one agree.

// ---- journal_main ----------------------------------------------------------
// freight_tax_id selects the tax rate for an order's freight. 0 falls back to
// AR_ADD_SALES_TAX_TO_SHIPPING, which is what every existing order used.
if (!db_field_exists(TABLE_JOURNAL_MAIN, 'freight_tax_id')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN freight_tax_id SMALLINT NOT NULL DEFAULT 0 AFTER freight");
}

// ship_packages holds an order's packing list.
if (!db_field_exists(TABLE_JOURNAL_MAIN, 'ship_packages')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN ship_packages mediumtext NULL DEFAULT NULL AFTER shipper_code");
}

if (!db_field_exists(TABLE_JOURNAL_MAIN, 'notes')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN
        . " ADD COLUMN notes mediumtext NULL DEFAULT NULL AFTER drop_ship");
}

// An empty default: a column default would freeze one installation's currency
// into the schema, and a company's currency belongs in its configuration.
if (db_field_exists(TABLE_JOURNAL_MAIN, 'currencies_code')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN . " MODIFY currencies_code char(3) NOT NULL DEFAULT ''");
}

// purch_order_id also holds the reference a payment service gives back, and a
// Square refund's is 73 characters. At 24 a lax server cut it to something that
// identified nothing, and a strict one refused the refund's entry after the
// money had already gone back. Only widened, so no value changes.
$len = $db->Execute("SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . TABLE_JOURNAL_MAIN . "' AND COLUMN_NAME = 'purch_order_id'");
if (!$len->EOF && (int)$len->fields['len'] < 128) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_MAIN . " MODIFY purch_order_id varchar(128) NULL DEFAULT NULL");
}

// ---- journal_item ----------------------------------------------------------
// restock says whether a credit memo line returns its quantity to stock. '1' is
// what every line did before the choice existed, and what every journal that
// never shows the checkbox keeps doing.
if (!db_field_exists(TABLE_JOURNAL_ITEM, 'restock')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_ITEM
        . " ADD COLUMN restock enum('0','1') NOT NULL DEFAULT '1' AFTER taxable");
}

// ---- purchase_export_profile -----------------------------------------------
// The line format a supplier's ordering system expects. contacts.export_profile_id
// assigns one to a vendor.
if (!db_table_exists(TABLE_PURCHASE_EXPORT_PROFILE)) $db->Execute($this->tables[TABLE_PURCHASE_EXPORT_PROFILE]);

// ---- column widths ----------------------------------------------------------
// Names, addresses and descriptions arrive from outside - the web shop, carrier
// and payment services - longer than these columns held. A lax server cut them
// to fit without a word (about a thousand addresses and a thousand product
// descriptions were found cut at exactly the width); a strict one refuses the
// whole write, so an order with a long address line would not import at all.
// Widened as the installer now declares them; only ever widened.
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_primary_name', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_contact', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_address1', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_address2', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_city_town', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_state_province', 32, "varchar(32) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_postal_code', 24, "varchar(24) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'bill_telephone1', 32, "varchar(32) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_primary_name', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_contact', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_address1', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_address2', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_city_town', 64, "varchar(64) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_state_province', 32, "varchar(32) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_postal_code', 24, "varchar(24) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'ship_telephone1', 32, "varchar(32) default NULL");
db_widen_column(TABLE_JOURNAL_MAIN, 'description', 64, "varchar(64) default NULL");
