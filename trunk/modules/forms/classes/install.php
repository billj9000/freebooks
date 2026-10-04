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
 * the existing catalog, so every stored report is runnable without a
 * migration (design §9, decisions C and D).
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
   * PhreeForm's admin tab — keeps its answer rather than having it replaced by
   * a shipped default. Where the PhreeForm constant is not defined, the
   * shipped default applies.
   */
  const INHERITS = array(
    'FORMS_DEFAULT_PAPER'        => 'PF_DEFAULT_PAPERSIZE',
    'FORMS_DEFAULT_ROWSPACE'     => 'PF_DEFAULT_ROWSPACE',
    'FORMS_DEFAULT_COLUMN_WIDTH' => 'PF_DEFAULT_COLUMN_WIDTH',
  );

  /**
   * Keys that are seeded but deliberately have no control on the settings page.
   *
   * PDF_APP is the only one, and it is here rather than absent from $keys
   * because it still has to be WRITTEN: currencies::format_full() in core takes
   * it as a parameter default, so a company without the row is a fatal. What it
   * is not is a choice — Forms' renderers extend TCPDF and nothing else, and a
   * setting with one possible value is a question nobody should be asked.
   *
   * Declared beside the keys rather than listed in the test, so that the page
   * check reads the exemption from the code it is checking.
   */
  const HIDDEN_KEYS = array('PDF_APP');

  public $notes         = array();
  public $prerequisites = array();
  public $keys          = array();
  public $dirlist       = array();
  public $tables        = array();

  function __construct() {
    $this->prerequisites = array(
      'phreedom'   => '1.0.0',
      'phreebooks' => '1.0.0',
    );
    $this->keys    = array(
      // The three renderer defaults. Each INHERITS the PhreeForm value where
      // the installation still has one, so an admin who tuned a column width or
      // chose A4 at install time keeps that answer instead of silently
      // reverting to what this module ships. See below.
      //
      // Paper is stored as the NAME: a native definition stores the name alone
      // and TCPDF looks the dimensions up itself, so a legacy "A4:210:297" is
      // reduced to its name.
      'FORMS_DEFAULT_PAPER'        => 'Letter',
      'FORMS_DEFAULT_ROWSPACE'     => '2',
      'FORMS_DEFAULT_COLUMN_WIDTH' => '25',

      // TCPDF is not a choice for Forms — its renderers extend it and nothing
      // else — but the constant is read by currencies::format_full() in core,
      // as a parameter default, so it has to exist.
      'PDF_APP'                    => 'TCPDF',
    );
    foreach (self::INHERITS as $key => $from) {
      if (!defined($from)) continue;
      $value = (string)constant($from);
      // Either spelling is accepted, and only the name is kept.
      if ($key === 'FORMS_DEFAULT_PAPER') $value = explode(':', $value)[0];
      if ($value !== '') $this->keys[$key] = $value;
    }
    // Where a company's definitions and their images live; a fresh install
    // needs them to seed a document into.
    //
    // The FOLDER keeps its name. Renaming it is a migration every backup,
    // restore and installed extension would have to be taught, in exchange for
    // a word — the same reasoning as TABLE_PHREEFORM below.
    $this->dirlist = array(
      'phreeform',
      'phreeform/images',
    );
    $this->tables  = array(
      // The document catalogue: one row per report or form, and the thing
      // every page of Forms reads first. Declared as existing companies hold
      // it, MyISAM and all, so that an existing company and a new one hold the
      // same table.
      TABLE_PHREEFORM => "CREATE TABLE " . TABLE_PHREEFORM . " (
          id int(10) unsigned NOT NULL auto_increment,
          parent_id int(11) NOT NULL default '0',
          doc_type enum('0','c','s') NOT NULL default 's',
          doc_title varchar(64) default '',
          doc_group varchar(9) default NULL,
          doc_ext varchar(3) default NULL,
          security varchar(255) default 'u:0;g:0',
          create_date date default NULL,
          last_update date default NULL,
          PRIMARY KEY (id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

      TABLE_FORMS_DATASETS => "CREATE TABLE " . TABLE_FORMS_DATASETS . " (
          id          varchar(64)  NOT NULL,
          label       varchar(128) NOT NULL DEFAULT '',
          description varchar(255) NOT NULL DEFAULT '',
          definition  mediumtext         NOT NULL,
          create_date datetime     NULL,
          last_update datetime     NULL,
          PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
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
    // does not write keys on update at all, so an installation enabled by an
    // earlier release would lack a newer key's row; but rewriting every key with
    // its shipped default would silently undo the admin's own choices on each
    // update. An absent constant is exactly an absent row — application_top
    // defines one per configuration row — so `defined()` is the test.
    foreach ($this->keys as $key => $value) {
      if (!defined($key)) write_configure($key, $value);
    }
    // Otherwise the same path as install: the table is created if it is
    // missing, which is what an installation enabled by an earlier release
    // needs.
    $result = $this->install($module);
    // And record that it ran. Without this the status stays where it was, the
    // per-login cascade sees STATUS differing from VERSION every time, and this
    // update runs again on every single login - forever, and silently.
    write_configure('MODULE_' . strtoupper($module) . '_STATUS',
                    constant('MODULE_' . strtoupper($module) . '_VERSION'));
    return $result;
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
