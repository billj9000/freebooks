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
 * Creates one table. Report DEFINITIONS are left where they are — Forms reads
 * the existing PhreeForm catalog, so enabling the module makes every stored
 * report runnable through the new engine without a migration and without
 * touching the old one, which is what lets the two coexist during the
 * transition (design §9, decisions C and D).
 *
 * What is new is `forms_datasets`: the join graphs an admin defines through the
 * UI (§3.2). One row per dataset holding the whole definition as JSON — it is
 * read and written whole and never queried into, and keeping it as a document
 * means a change to the definition format is not a database migration.
 */

class forms_admin {
  /**
   * The PhreeForm setting each of Forms' own renderer defaults takes over from.
   *
   * Read at construction so that install and update both see it. An
   * installation that answered this question once — at install time, or on
   * PhreeForm's admin tab — should not be asked again by a module that is
   * taking the question over, and should certainly not have the answer
   * replaced by a shipped default without being told.
   *
   * When PhreeForm goes, these constants stop existing and the shipped
   * defaults below apply to new installations only; every existing one already
   * has its own row by then.
   */
  const INHERITS = array(
    'FORMS_DEFAULT_PAPER'        => 'PF_DEFAULT_PAPERSIZE',
    'FORMS_DEFAULT_ROWSPACE'     => 'PF_DEFAULT_ROWSPACE',
    'FORMS_DEFAULT_COLUMN_WIDTH' => 'PF_DEFAULT_COLUMN_WIDTH',
  );

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
    $this->keys    = array(
      // Whether the Print buttons on orders, bills, status and the POS open
      // Forms or PhreeForm (gen_print_form_url()). OFF on purpose: installing
      // Forms should let an admin look at it, not silently move production
      // printing onto it. Design §11.16 wants the two side by side first.
      'MODULE_FORMS_PRINT_BUTTONS' => '0',

      // The three renderer defaults, which were PhreeForm's settings and are
      // now Forms' own. Each INHERITS the PhreeForm value where the
      // installation has one, so an admin who tuned a column width or chose A4
      // at install time keeps that answer instead of silently reverting to what
      // this module happens to ship. See below.
      //
      // Paper is stored as the NAME. PhreeForm keeps "A4:210:297" because its
      // designer carried the dimensions along; a native definition stores the
      // name alone and TCPDF looks the dimensions up itself.
      'FORMS_DEFAULT_PAPER'        => 'Letter',
      'FORMS_DEFAULT_ROWSPACE'     => '2',
      'FORMS_DEFAULT_COLUMN_WIDTH' => '25',
    );
    foreach (self::INHERITS as $key => $from) {
      if (!defined($from)) continue;
      $value = (string)constant($from);
      // Either spelling is accepted, and only the name is kept.
      if ($key === 'FORMS_DEFAULT_PAPER') $value = explode(':', $value)[0];
      if ($value !== '') $this->keys[$key] = $value;
    }
    $this->dirlist = array();
    $this->tables  = array(
      TABLE_FORMS_DATASETS => "CREATE TABLE " . TABLE_FORMS_DATASETS . " (
          id          varchar(64)  NOT NULL,
          label       varchar(128) NOT NULL DEFAULT '',
          description varchar(255) NOT NULL DEFAULT '',
          definition  text         NOT NULL,
          create_date datetime     NULL,
          last_update datetime     NULL,
          PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
    );
    $this->notes = array(
      'Forms runs the existing report catalog; PhreeForm is unaffected and stays available.',
      'Defining datasets is a separate privilege from running or writing reports.',
    );
  }

  function install($module) {
    global $db;
    $error = false;
    foreach ($this->tables as $table => $create_table_sql) {
      if (!db_table_exists($table)) if (!$db->Execute($create_table_sql)) $error = true;
    }
    return $error;
  }

  function initialize($module) { return false; }

  function update($module) {
    // Only keys that are MISSING, never keys that are set. Module Administration
    // does not write keys on update at all, so an installation enabled before a
    // key existed would never get its row; but rewriting every key with its
    // shipped default would silently undo the admin's own choices on each
    // update, and one of these keys decides where the Print buttons point.
    // An absent constant is exactly an absent row — application_top defines one
    // per configuration row — so `defined()` is the test.
    foreach ($this->keys as $key => $value) {
      if (!defined($key)) write_configure($key, $value);
    }
    // Otherwise the same path as install: the table is created if it is
    // missing, which is what an installation enabled before custom datasets
    // existed needs.
    return $this->install($module);
  }

  function remove($module) {
    // Deliberately keeps the table. Dropping it would destroy dataset
    // definitions that reports still depend on, and a module can be disabled
    // to try something and enabled again.
    return false;
  }

  function load_reports($module) { return false; }
  function load_demo()           { return false; }
}
