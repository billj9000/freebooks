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
//  Path: /modules/forms/classes/install.php
//
/**
 * Module installer for Forms.
 *
 * Creates nothing. Forms reads the definitions already in the PhreeForm catalog,
 * so enabling the module makes every stored report runnable through the new
 * engine without a migration and without touching the old one — which is what
 * lets the two coexist during the transition (design §9, decisions C and D).
 *
 * The definition and custom-dataset tables arrive with the authoring UI.
 */

class forms_admin {
  public $notes         = array();
  public $prerequisites = array();
  public $keys          = array();
  public $dirlist       = array();
  public $tables        = array();

  function __construct() {
    $this->prerequisites = array(
      'phreedom'   => 3.6,
      'phreebooks' => 3.7,
    );
    $this->keys    = array();
    $this->dirlist = array();
    $this->tables  = array();
    $this->notes   = array(
      'Forms runs the existing report catalog; PhreeForm is unaffected and stays available.',
    );
  }

  function install($module)      { return false; }
  function initialize($module)   { return false; }
  function update($module)       { return false; }
  function remove($module)       { return false; }
  function load_reports($module) { return false; }
  function load_demo()           { return false; }
}
