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
// Path: /modules/phreebooks/updates/R40toR41.php
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
?>
