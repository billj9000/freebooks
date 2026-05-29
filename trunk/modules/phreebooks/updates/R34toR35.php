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
// Path: /modules/phreebooks/updates/R34toR35.php
// Adds recipient_name and recipient_email to shipping_log for label email pre-population.

if (!db_field_exists(TABLE_SHIPPING_LOG, 'recipient_name')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN recipient_name  varchar(64) NOT NULL DEFAULT '' AFTER notes,"
        . " ADD COLUMN recipient_email varchar(100) NOT NULL DEFAULT '' AFTER recipient_name");
}
?>
