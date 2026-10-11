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
//  Path: /modules/xero_sync/classes/install.php
//

class xero_sync_admin {
  public $notes         = [];
  public $prerequisites = [
    'phreedom'   => '1.0.0',
    'phreebooks' => '1.0.0',
  ];
  public $keys    = [];
  public $dirlist = [];
  public $tables  = [];

  // The mapping table holds three kinds of row, each keyed by its FreeBooks value:
  //   account  a GL account => its Xero account code (xero_value) and, when foreign sales post
  //            elsewhere, the foreign account code (xero_foreign)
  //   tax      a tax_rate_id => the Xero tax rate name
  //   contact  a customer name => the Xero contact name, where they differ
  // The token table holds one row, the connection to Xero; xero_connection describes it.
  public function __construct() {
    $this->keys = [
      'XERO_SYNC_HOME_COUNTRY'       => defined('COMPANY_COUNTRY') ? COMPANY_COUNTRY : '',
      'XERO_SYNC_COUNTRY_SOURCE'     => 'shipping',
      'XERO_SYNC_DUE_DAYS'           => '30',
      'XERO_SYNC_ROUNDING_TOLERANCE' => '0.01',
      'XERO_SYNC_USE_PAYMENT_REF'    => '0',
      'XERO_SYNC_DEFAULT_ACCOUNT'    => '',
      'XERO_SYNC_ROUNDING_ACCOUNT'   => '',
      'XERO_SYNC_NO_VAT_TYPE'        => 'No VAT',
    ];
    $this->tables = [
      TABLE_XERO_SYNC_MAP => "CREATE TABLE IF NOT EXISTS " . TABLE_XERO_SYNC_MAP . " (
        `id`           int(11)                          NOT NULL AUTO_INCREMENT,
        `map_type`     enum('account','tax','contact')  NOT NULL,
        `fb_key`       varchar(255)                     NOT NULL,
        `xero_value`   varchar(255)                     NOT NULL DEFAULT '',
        `xero_foreign` varchar(255)                     NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `map_key` (`map_type`, `fb_key`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      TABLE_XERO_SYNC_TOKEN => "CREATE TABLE IF NOT EXISTS " . TABLE_XERO_SYNC_TOKEN . " (
        `id`             tinyint(4)   NOT NULL,
        `client_id`      varchar(64)  NOT NULL DEFAULT '',
        `client_secret`  text             DEFAULT NULL COMMENT 'encrypted',
        `refresh_token`  text             DEFAULT NULL COMMENT 'encrypted',
        `access_token`   text             DEFAULT NULL COMMENT 'encrypted',
        `access_expires` int(11)      NOT NULL DEFAULT 0  COMMENT 'Unix time',
        `tenant_id`      varchar(64)  NOT NULL DEFAULT '',
        `tenant_name`    varchar(255) NOT NULL DEFAULT '',
        `connected_at`   datetime         DEFAULT NULL,
        `connected_by`   int(11)      NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
  }

  public function install(string $module): bool { return false; }
  public function remove(string $module): bool  { return false; }
  public function update(string $module): bool {
    // Record that it ran: the cascade repeats update() until STATUS reaches VERSION.
    write_configure('MODULE_' . strtoupper($module) . '_STATUS',
                    constant('MODULE_' . strtoupper($module) . '_VERSION'));
    return false;
  }
  public function load_reports(string $module): void {}
  public function load_demo(): bool              { return false; }
}
