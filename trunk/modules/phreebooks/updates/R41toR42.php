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
// Path: /modules/phreebooks/updates/R41toR42.php
// Adds a per-line "Re-stock" flag to credit memos, so a line item can be
// credited to the customer/vendor without its quantity returning to
// inventory (item lost or failed in the field - see issue #71). Default
// '1' so every existing row, and every journal type that never shows the
// checkbox, keeps today's behaviour exactly.

if (!db_field_exists(TABLE_JOURNAL_ITEM, 'restock')) {
    $db->Execute("ALTER TABLE " . TABLE_JOURNAL_ITEM
        . " ADD COLUMN restock enum('0','1') NOT NULL DEFAULT '1' AFTER taxable");
}
?>
