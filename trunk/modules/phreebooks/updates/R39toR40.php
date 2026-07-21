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
// Path: /modules/phreebooks/updates/R39toR40.php
// Adds carrier_order_id column to shipping_log — stores a carrier's internal
// order/shipment identifier when it differs from the tracking number shown to
// the user (e.g. Royal Mail Click & Drop's orderIdentifier), so cancellation
// can use the correct id instead of the tracking number.

if (!db_field_exists(TABLE_SHIPPING_LOG, 'carrier_order_id')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN carrier_order_id varchar(64) NOT NULL DEFAULT '' AFTER recipient_email");
}
?>
