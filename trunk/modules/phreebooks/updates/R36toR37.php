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
// Path: /modules/phreebooks/updates/R36toR37.php
// Adds currency_code to inventory_purchase so supplier costs are stored
// in the supplier's native currency rather than company currency.

if (!db_field_exists(TABLE_INVENTORY_PURCHASE, 'currency_code')) {
    $db->Execute("ALTER TABLE " . TABLE_INVENTORY_PURCHASE
        . " ADD COLUMN currency_code varchar(3) NOT NULL DEFAULT '' AFTER item_cost");
    $db->Execute("UPDATE " . TABLE_INVENTORY_PURCHASE
        . " SET currency_code = '" . DEFAULT_CURRENCY . "' WHERE currency_code = ''");
}
?>
