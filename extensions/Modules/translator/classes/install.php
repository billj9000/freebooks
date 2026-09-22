<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/translator/classes/install.php
//

class translator_admin {
	public $notes 			= array();// placeholder for any operational notes
	public $prerequisites 	= array();// modules required and rev level for this module to work properly
	public $keys			= array();// Load configuration constants for this module, must match entries in admin tabs
	public $dirlist			= array();// add new directories to store images and data
	public $tables			= array();// Load tables
	
  function __construct() {
	$this->prerequisites = array( // modules required and rev level for this module to work properly
	  'phreedom' => 3.3,
	);
	// add new directories to store images and data
	$this->dirlist = array(
	  '../translator', // put it in the my_files directory, not the current company directory
	);
	// Load tables
	$this->tables = array(
	  // translation carries no DEFAULT: MySQL refuses one on a TEXT or BLOB
	  // column (ERROR 1101) where MariaDB allows it, and a nullable TEXT
	  // already defaults to NULL.
	  TABLE_TRANSLATOR => "CREATE TABLE " . TABLE_TRANSLATOR  . " (
		  id smallint(5) unsigned NOT NULL auto_increment,
		  module varchar(48) NOT NULL DEFAULT '',
		  language char(5) NOT NULL DEFAULT '',
		  version char(6) NOT NULL DEFAULT '',
		  pathtofile varchar(255) collate utf8mb4_unicode_ci NOT NULL DEFAULT '/',
		  defined_constant varchar(96) collate utf8mb4_unicode_ci NOT NULL DEFAULT '',
		  translation mediumtext collate utf8mb4_unicode_ci,
		  translated enum('0','1') NOT NULL default '0',
		  PRIMARY KEY (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );
  }

  function install($module) {
  }

  function initialize($module) {
  }

  function update($module) {
    global $db, $messageStack;
	$error = false;
	// Drop the DEFAULT from the TEXT column on databases that still carry one.
	// It is harmless on MariaDB, but a dump of one carries the declaration, and
	// restoring that dump into MySQL fails at CREATE TABLE with ERROR 1101.
	if (db_field_exists(TABLE_TRANSLATOR, 'translation')) {
	  $db->Execute("ALTER TABLE " . TABLE_TRANSLATOR
	             . " MODIFY translation mediumtext COLLATE utf8mb4_unicode_ci");
	}
	if (!$error) {
	  $ver_const = 'MODULE_' . strtoupper($module) . '_VERSION';
	  $ver = defined($ver_const) ? constant($ver_const) : '';
	  write_configure('MODULE_'.strtoupper($module).'_STATUS', $ver);
   	  $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, $ver), 'success');
	}
	return $error;
  }

  function remove($module) {
  }

  function load_reports($module) {
  }

  function load_demo() {
  }

}
?>