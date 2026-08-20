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
//                      preview and would not have wrapped in life. An element
//                      bound to a company detail says so in the inspector
//                      instead of showing an empty list it cannot be set from.
// 1.10 => 2026-08-18 - The document list asks its questions per document. The
//                      page-wide options bar and email card are gone; PDF runs a
//                      report as its author drew it, and a dialog headed with the
//                      document's name asks only what that document needs. This
//                      fixes a real defect: the bar preselected 'All' and sent it
//                      on every run, so a report drawn as "this quarter" quietly
//                      ran over all time. The list gains a filter box and folding
//                      groups, drops its alert() boxes for validation on the
//                      control itself, and shows the dataset serving a report to
//                      the people who can act on it rather than to everyone.
//                      A report that must be told something the company cannot
//                      supply — the reconciliation on a company with no cash
//                      accounts — says so and refuses, instead of presenting a
//                      mandatory list with nothing in it.
//                      The Report Editor's own chooser and its Tools menu entry
//                      are retired: they were a second list of the same reports,
//                      labelled with the raw group codes the Reports list stopped
//                      showing. The editor opens from Edit beside a report, and
//                      Cancel returns there. SECURITY_ID_FORMS_AUTHOR stays
//                      grantable because Form Layout names it too.
//                      The folder headings on that list are a ruled band with a
//                      small uppercase label, not bold text the size and colour
//                      of the report names beneath them.

define('MODULE_FORMS_VERSION', 1.10);

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

  // The report editor has NO entry of its own, and that is deliberate. It is
  // reached from the Edit button beside a report on the list above, which is the
  // only list of reports there is; an entry here landed on a second list of the
  // same reports, labelled with the raw doc_group codes the list above stopped
  // showing. Two lists of one thing, and the worse of them on the menu.
  //
  // What the entry USED to be for, and what replaced it: phreedom's roles page
  // builds itself by walking $mainmenu, so a security id named by no entry
  // appears nowhere on it and cannot be granted to anybody. That is still true —
  // but SECURITY_ID_FORMS_AUTHOR is named by the form canvas below, which was
  // added later and shares it. Removing THAT entry would make the permission
  // ungrantable; removing this one does not.
  //
  // The form canvas. Shares SECURITY_ID_FORMS_AUTHOR with the report editor:
  // both are "write a document", and the two exist separately because a form is
  // positioned boxes and a report is a column list (§13.1), not because they are
  // different privileges. Unlike reports, forms have no other list — they are
  // kept off the Reports page on purpose — so this chooser is the only way in.
  $mainmenu['tools']['submenu']['forms_canvas'] = array(
    'order'                  => 28,
    'text'                   => BOX_FORMS_CANVAS,
    'security_id'            => SECURITY_ID_FORMS_AUTHOR,
    'link'                   => html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=canvas', 'SSL'),
    'show_in_users_settings' => true,
    'params'                 => '',
  );

  // The RECEIPT editor has no entry of its own, for the same reason the report
  // editor has none: there is one list of forms, and it is the canvas chooser
  // above. A receipt is listed there like any other form, marked as one, and
  // opening it lands on the editor that can edit it — the list knows which,
  // so nobody has to (§13.28). A second menu entry would have been a second
  // list of the same forms, which is exactly what the report editor's entry
  // was removed for.
  //
  // Safe to omit for the reason spelled out above: SECURITY_ID_FORMS_AUTHOR is
  // still named by the canvas entry, so it remains grantable on the roles page.

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

// The document catalogue: one row per report or form, read by Forms and — while
// it is still installed — by PhreeForm. Shared BY DESIGN (§13.3), which is what
// made running the two side by side possible at all.
//
// Defined here as well as in phreeform/config.php, guarded, so that Forms keeps
// working when that module goes. The TABLE keeps its name: renaming it is a
// migration every backup, restore and installed extension would have to be
// taught, in exchange for a word.
if (!defined('TABLE_PHREEFORM')) define('TABLE_PHREEFORM', DB_PREFIX . 'phreeform');
