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
// Path: /modules/phreebooks/updates/R35toR36.php
// Adds vat_number and eori_number to the contacts table.

if (!db_field_exists(TABLE_CONTACTS, 'vat_number')) {
    $db->Execute("ALTER TABLE " . TABLE_CONTACTS
        . " ADD COLUMN vat_number  varchar(32) NOT NULL DEFAULT '' AFTER gov_id_number,"
        . " ADD COLUMN eori_number varchar(32) NOT NULL DEFAULT '' AFTER vat_number");
}
?>
