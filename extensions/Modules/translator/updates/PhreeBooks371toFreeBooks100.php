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
//  Path: /modules/translator/updates/PhreeBooks371toFreeBooks100.php
//
// The translator module's table, from PhreeBooks 3.7.1 to FreeBooks 1.0.0.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.

// ---- translator ------------------------------------------------------------
// translation carries no DEFAULT. MariaDB accepts one on a TEXT column and MySQL
// does not, so a dump of a table that has one fails to restore into MySQL with
// ERROR 1101.
if (db_field_exists(TABLE_TRANSLATOR, 'translation')) {
    $db->Execute("ALTER TABLE " . TABLE_TRANSLATOR
        . " MODIFY translation mediumtext COLLATE utf8mb4_unicode_ci");
}
