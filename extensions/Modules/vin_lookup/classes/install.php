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
//  Path: /modules/vin_lookup/classes/install.php
//

class vin_lookup_admin {
  public $notes         = array();
  public $prerequisites = array();
  public $keys          = array();
  public $dirlist       = array();
  public $tables        = array();

  function __construct() {
    $this->prerequisites = array(
      'phreedom' => 3.7,
    );
    $this->keys = array(
      'VIN_LOOKUP_API_KEY'  => '',
      'VIN_LOOKUP_PACKAGES' => 'VehicleDetails',
      'VIN_LOOKUP_ENDPOINT' => 'https://uk.api.vehicledataglobal.com/r2/lookup',
    );
    $this->dirlist = array();
    $this->tables  = array(
      TABLE_VIN_LOOKUP_CACHE => "CREATE TABLE " . TABLE_VIN_LOOKUP_CACHE . " (
          id             int(11)      NOT NULL AUTO_INCREMENT,
          vrm            varchar(16)  NOT NULL DEFAULT '',
          vin            varchar(32)  NOT NULL DEFAULT '',
          make           varchar(64)  NOT NULL DEFAULT '',
          model          varchar(128) NOT NULL DEFAULT '',
          packages       varchar(255) NOT NULL DEFAULT '',
          response       mediumtext   NULL,
          date_added     datetime     NOT NULL,
          date_last_used datetime     NOT NULL,
          hit_count      int(11)      NOT NULL DEFAULT 0,
          admin_id       int(11)      NOT NULL DEFAULT 0,
          PRIMARY KEY (id),
          UNIQUE KEY idx_vrm (vrm)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );
  }

  function install($module) {
    // Force the help index to pick up the new manual/ topics on next PhreeHelp visit
    write_configure('PHREEHELP_FORCE_RELOAD', '1');
    return false;
  }

  function update($module) {
    global $messageStack;
    write_configure('PHREEHELP_FORCE_RELOAD', '1');
    write_configure('MODULE_' . strtoupper($module) . '_STATUS', constant('MODULE_' . strtoupper($module) . '_VERSION'));
    $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, constant('MODULE_' . strtoupper($module) . '_VERSION')), 'success');
    return false;
  }

  function remove($module) {
    return false;
  }

  function initialize($module) {
    return false;
  }

  function load_demo() {
    return false;
  }

  function load_reports($module) {
    return false;
  }
}
