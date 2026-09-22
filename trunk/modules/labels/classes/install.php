<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/classes/install.php

class labels_admin {
  public $notes         = array();
  public $prerequisites = array();
  public $keys          = array();
  public $dirlist       = array();
  public $tables        = array();

  function __construct() {
    $this->prerequisites = array(
      'inventory' => '1.0.0',
    );
    $this->keys    = array();
    $this->dirlist = array();
    $this->tables  = array(
      TABLE_LABEL_TEMPLATES => "CREATE TABLE " . TABLE_LABEL_TEMPLATES . " (
          id            int(11)          NOT NULL AUTO_INCREMENT,
          name          varchar(100)     NOT NULL DEFAULT '',
          width_mm      decimal(6,2)     NOT NULL DEFAULT '50.00',
          height_mm     decimal(6,2)     NOT NULL DEFAULT '25.00',
          printer_type  enum('html','zpl') NOT NULL DEFAULT 'html',
          html_template mediumtext             NULL,
          zpl_template  mediumtext             NULL,
          date_modified datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );
  }

  function install($module) {
    return false;
  }

  function update($module) {
    // Nothing to migrate, but the status still has to move: left where it is,
    // the cascade sees STATUS differing from VERSION on every login and calls
    // this again for ever.
    write_configure('MODULE_' . strtoupper($module) . '_STATUS',
                    constant('MODULE_' . strtoupper($module) . '_VERSION'));
    return true;
  }

  function remove($module) {
    return false;
  }

  function load_reports($module) {
    return false;
  }

  // Every install class is expected to answer this: the company manager and the
  // installer call it unconditionally when the new company is asked for demo
  // data, and its absence was a fatal there rather than a skipped step.
  function load_demo() {
    return false;
  }
}
