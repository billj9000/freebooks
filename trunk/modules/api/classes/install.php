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
//  Path: /modules/api/classes/install.php
//

class api_admin {
  public $notes        = [];
  public $prerequisites = [
    'phreedom'   => '3.6',
    'phreebooks' => '3.6',
    'contacts'   => '3.6',
  ];
  public $keys    = [];
  public $dirlist = [];
  public $tables  = [];

  // key_prefix is 16 rather than 12 because generate_key.php stores
  // strlen(API_KEY_PREFIX) + 6 characters and API_KEY_PREFIX is 'fbk_live_', so
  // the value is 15 long. At 12 a server at its defaults refuses the insert with
  // "Data too long" and no key can be generated at all; a lax server truncates
  // it silently instead. Found by the page crawl, not by reading.
  public function __construct() {
    $this->tables = [
      TABLE_API_KEYS => "CREATE TABLE IF NOT EXISTS " . TABLE_API_KEYS . " (
        `id`         int(11)       NOT NULL AUTO_INCREMENT,
        `key_hash`   char(64)      NOT NULL          COMMENT 'SHA-256 hex of the plaintext key',
        `key_prefix` varchar(16)   NOT NULL DEFAULT '',
        `company`    varchar(64)   NOT NULL DEFAULT '' COMMENT 'my_files folder / DB name',
        `label`      varchar(64)   NOT NULL DEFAULT '',
        `scopes`     varchar(255)  NOT NULL DEFAULT '',
        `enabled`    enum('0','1') NOT NULL DEFAULT '1',
        `created`    datetime      NOT NULL,
        `last_used`  datetime          DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key_hash` (`key_hash`),
        KEY `company` (`company`)
      // COLLATE is explicit on purpose: left out, MariaDB 11.4 picks its own
      // utf8mb4_uca1400_ai_ci, which MySQL does not have, and the table cannot
      // then be restored onto one. utf8mb4_unicode_ci exists on both.
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
  }

  public function install(string $module): bool  { return false; }
  public function remove(string $module): bool   { return false; }
  public function update(string $module): bool {
    global $db;
    // key_prefix was varchar(12) while the code wrote 15 characters into it.
    // Existing installations need widening; a fresh one is created correct above.
    if (db_field_exists(TABLE_API_KEYS, 'key_prefix')) {
      $db->Execute("ALTER TABLE " . TABLE_API_KEYS . " MODIFY key_prefix varchar(16) NOT NULL DEFAULT ''");
    }
    return true;
  }
  public function load_reports(string $module): void {}
  public function load_demo(): bool              { return false; }
}
