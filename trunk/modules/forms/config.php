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

define('MODULE_FORMS_VERSION', 1.00);

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

// No table of its own yet. Forms reads the existing PhreeForm catalog so that
// every stored definition works from the day the module is enabled, with no
// migration; the definition and custom-dataset tables arrive with the authoring
// UI (design §8, decision B).

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
}
