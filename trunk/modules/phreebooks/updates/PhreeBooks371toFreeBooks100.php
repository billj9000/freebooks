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
