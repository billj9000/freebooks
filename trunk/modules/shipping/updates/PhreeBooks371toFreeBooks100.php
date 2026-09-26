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
//  Path: /modules/shipping/updates/PhreeBooks371toFreeBooks100.php
//
// The shipping module's tables and settings, from PhreeBooks 3.7.1 to FreeBooks
// 1.0.0.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.

// ---- shipping_log ----------------------------------------------------------
// Who a label was sent to, so a label email can be addressed without asking.
if (!db_field_exists(TABLE_SHIPPING_LOG, 'recipient_name')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN recipient_name varchar(64) NOT NULL DEFAULT '' AFTER notes");
}
if (!db_field_exists(TABLE_SHIPPING_LOG, 'recipient_email')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN recipient_email varchar(100) NOT NULL DEFAULT '' AFTER recipient_name");
}

// A carrier's own id for a shipment, where it differs from the tracking number
// the user sees. Cancelling a shipment needs this one.
if (!db_field_exists(TABLE_SHIPPING_LOG, 'carrier_order_id')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG
        . " ADD COLUMN carrier_order_id varchar(64) NOT NULL DEFAULT '' AFTER recipient_email");
}

// ---- shipping_packages -----------------------------------------------------
// The cartons a shipment can be packed into, and the divisor that turns a
// carton's volume into the weight a carrier charges for.
if (!db_table_exists(TABLE_SHIPPING_PACKAGES)) $db->Execute($this->tables[TABLE_SHIPPING_PACKAGES]);
if (!defined('SHIPPING_VOL_WEIGHT_DIVISOR')) write_configure('SHIPPING_VOL_WEIGHT_DIVISOR', '5000');

// A carton offered by one carrier only names it; empty is any carrier.
if (!db_field_exists(TABLE_SHIPPING_PACKAGES, 'carrier')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_PACKAGES
        . " ADD carrier varchar(32) NOT NULL default '' AFTER inactive");
}
