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
