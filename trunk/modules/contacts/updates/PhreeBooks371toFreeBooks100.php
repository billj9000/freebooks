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
//  Path: /modules/contacts/updates/PhreeBooks371toFreeBooks100.php
//
// The contacts module's tables, from PhreeBooks 3.7.1 to FreeBooks 1.0.0.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.

// ---- contacts --------------------------------------------------------------
if (!db_field_exists(TABLE_CONTACTS, 'vat_number')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD COLUMN vat_number varchar(32) NOT NULL DEFAULT '' AFTER gov_id_number");
}
if (!db_field_exists(TABLE_CONTACTS, 'eori_number')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD COLUMN eori_number varchar(32) NOT NULL DEFAULT '' AFTER vat_number");
}

// A contact's own currency; empty means the company's.
if (!db_field_exists(TABLE_CONTACTS, 'currency_code')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD currency_code VARCHAR(3) NOT NULL DEFAULT '' AFTER price_sheet");
}

// The purchase order export profile a vendor's orders are written in; 0 is none.
if (!db_field_exists(TABLE_CONTACTS, 'export_profile_id')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD COLUMN export_profile_id int(11) NOT NULL DEFAULT '0' AFTER currency_code");
}

// -1 is how a contact says it has no tax id, so the column cannot be NULL.
if (db_field_exists(TABLE_CONTACTS, 'tax_id')) {
    $db->Execute("UPDATE " . TABLE_CONTACTS . " SET tax_id = -1 WHERE tax_id IS NULL");
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS . " MODIFY tax_id int(11) NOT NULL DEFAULT '-1'");
}

// ---- column widths ----------------------------------------------------------
// Names, addresses and descriptions arrive from outside - the web shop, carrier
// and payment services - longer than these columns held. A lax server cut them
// to fit without a word; a strict one refuses the
// whole write, so an order with a long address line would not import at all.
// Widened as the installer now declares them; only ever widened.
db_widen_column(TABLE_ADDRESS_BOOK, 'primary_name', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'contact', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'address1', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'address2', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'city_town', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'state_province', 32, "varchar(32) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'postal_code', 24, "varchar(24) NOT NULL default ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'telephone1', 32, "VARCHAR(32) NULL DEFAULT ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'telephone2', 32, "VARCHAR(32) NULL DEFAULT ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'telephone3', 32, "VARCHAR(32) NULL DEFAULT ''");
db_widen_column(TABLE_ADDRESS_BOOK, 'telephone4', 32, "VARCHAR(32) NULL DEFAULT ''");
db_widen_column(TABLE_CONTACTS, 'short_name', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_CONTACTS, 'contact_first', 64, "varchar(64) default NULL");
db_widen_column(TABLE_CONTACTS, 'contact_middle', 64, "varchar(64) default NULL");
db_widen_column(TABLE_CONTACTS, 'contact_last', 64, "varchar(64) default NULL");
db_widen_column(TABLE_CONTACTS, 'gov_id_number', 32, "varchar(32) NOT NULL default ''");
