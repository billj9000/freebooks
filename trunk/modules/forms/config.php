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
//  Path: /modules/forms/config.php
//
// Release History
// 1.00 => 2026-08-14 - Initial release: runs the shipped report catalog through
//                      the Forms engine, alongside PhreeForm.
// 1.01 => 2026-08-15 - Print buttons can be pointed at Forms, per company, from
//                      the module's own settings page. Adds the configuration
//                      key MODULE_FORMS_PRINT_BUTTONS, which an already
//                      installed company picks up by running Update.
// 1.02 => 2026-08-15 - The report editor, at ?module=forms&page=design, behind
//                      SECURITY_ID_FORMS_AUTHOR. Saving rewrites a definition in
//                      the Forms format and keeps the original beside it. No new
//                      configuration keys or tables.
// 1.03 => 2026-08-16 - The form canvas, at ?module=forms&page=canvas: the real
//                      rendered PDF with the element boxes over it, read-only.
//                      Emailing a report from the document list, with From, CC
//                      and BCC. Vendors pdf.js under ext/pdfjs/.
// 1.04 => 2026-08-16 - The canvas edits: drag, resize, snap, add, delete,
//                      z-order and an element inspector. Saving writes the
//                      Forms format and keeps the original beside it.
// 1.05 => 2026-08-16 - An image can be uploaded from the canvas, into the
//                      company's own reports images folder. The type is read
//                      from the file and decides the name it is stored under.
//                      An image element can also be ADDED to a form, so one
//                      that shipped without a logo can be given one, and any
//                      element can be given a name of the author's own.
// 1.06 => 2026-08-16 - Reports and forms are listed separately: Documents
//                      becomes Reports, and Form Layout is the forms
//                      catalogue. Both group by the folder NAMES the catalog
//                      already carries rather than by the doc_group code. An
//                      editor's Cancel returns to its own list.
// 1.07 => 2026-08-16 - The shipped definitions ship in the Forms format too,
//                      and a new install seeds those: 56 native files beside
//                      the xml, the installer's paper size stamped onto them,
//                      and the Report Settings re-stamp taught to read them.
//                      A FRESH install turns MODULE_FORMS_PRINT_BUTTONS on,
//                      because a new company has only native definitions and
//                      PhreeForm cannot open one.
// 1.08 => 2026-08-17 - The canvas gains a group: shift-click, or sweep a band
//                      around them, to select several elements and move them
//                      together, keeping their spacing exactly. Every side and
//                      corner resizes, with the conventional double-arrow
//                      cursors. A click takes the smallest element under the
//                      pointer, so a field inside a frame can be selected.
// 1.09 => 2026-08-17 - Sample values, which stand in where a form or report has
//                      no data, are invented but believable in LENGTH instead of
//                      being the field's own name. A name is longer than the
//                      data it stands for, so a narrow box wrapped in the
//                      preview and would not have wrapped in life.

define('MODULE_FORMS_VERSION', 1.09);

// Security IDs.
//
// Three, not one. PhreeForm conflated running a document with authoring one and
// with defining which tables may be joined — SECURITY_ID_PHREEFORM granted all
// three, which is why any report author was a database-wide read primitive
// (design §1.1, §3.1, decision F). Running is constant and harmless; defining a
// join graph is rare and dangerous, and they should never have shared a key.
define('SECURITY_ID_FORMS_RUN',      8);  // run and print documents
define('SECURITY_ID_FORMS_AUTHOR',   9);  // write a report inside a dataset
define('SECURITY_ID_FORMS_DATASETS', 10); // define which tables join, and how

// Datasets defined through the admin UI (design §3.2). Report DEFINITIONS still
// live in the PhreeForm catalog, so enabling Forms needs no migration and both
// engines keep working on the same definitions; only this is new.
define('TABLE_FORMS_DATASETS', DB_PREFIX . 'forms_datasets');

if (defined('MODULE_FORMS_STATUS')) {
  gen_pull_language('forms');

  $mainmenu['tools']['submenu']['forms'] = array(
    'order'                  => 26,
    'text'                   => BOX_FORMS_DOCUMENTS,
    'security_id'            => SECURITY_ID_FORMS_RUN,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=main', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );

  // The report editor. It is normally reached from the Edit button on the
  // document list rather than from here, and with no report chosen it says so —
  // but it needs a menu entry all the same, and that is not a convenience.
  //
  // phreedom's roles page builds itself by walking $mainmenu: a security id with
  // no menu entry appears nowhere on it, so nobody can grant it, so the page it
  // guards is unreachable for every user including the one who installed the
  // module. An entry here is the only way SECURITY_ID_FORMS_AUTHOR can be given
  // to a role at all.
  $mainmenu['tools']['submenu']['forms_design'] = array(
    'order'                  => 27,
    'text'                   => BOX_FORMS_DESIGN,
    'security_id'            => SECURITY_ID_FORMS_AUTHOR,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=design', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );

  // The form canvas. Shares SECURITY_ID_FORMS_AUTHOR with the report editor:
  // both are "write a document", and the two exist separately because a form is
  // positioned boxes and a report is a column list (§13.1), not because they are
  // different privileges.
  $mainmenu['tools']['submenu']['forms_canvas'] = array(
    'order'                  => 28,
    'text'                   => BOX_FORMS_CANVAS,
    'security_id'            => SECURITY_ID_FORMS_AUTHOR,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=canvas', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );

  // Its own key, and a rare one to hold. Defining which tables join is the
  // dangerous privilege of the three (§3.1); running a report is not.
  $mainmenu['tools']['submenu']['forms_datasets'] = array(
    'order'                  => 29,
    'text'                   => BOX_FORMS_DATASETS,
    'security_id'            => SECURITY_ID_FORMS_DATASETS,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=datasets', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );
}
