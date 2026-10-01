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
//  Path: /modules/forms/pages/admin/pre_process.php
//
/**
 * Forms module settings (design §11.16).
 *
 * Reached from Company -> Module Administration, which links every installed
 * module's `page=admin` from the properties icon. One setting so far: whether
 * the Print buttons open Forms or PhreeForm.
 *
 * It is a page rather than a line in the installer because the answer changes
 * during the transition and has to be reversible without a deploy — that is the
 * whole reason the switch exists rather than the buttons simply being repointed.
 */

$security_level = validate_user(SECURITY_ID_CONFIGURATION);

/**************  include page specific files    *********************/
gen_pull_language($module, 'admin');
gen_pull_language('phreedom', 'admin');
require_once(DIR_FS_WORKING . 'classes/install.php');

/**************   page specific initialization  *************************/
$install = new forms_admin();

/***************   Act on the action request   *************************/
// Every action here writes, and only a POST carries the CSRF token.
if (in_array($_REQUEST['action'] ?? '', array('save', 'restamp', 'rebuild'), true)
    && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die('This action must be submitted from its page.');
}
switch ($_REQUEST['action'] ?? '') {
  case 'save':
    validate_security($security_level, 3);
    // Same shape as every other module's settings tab: the installer's key list
    // is the list of settings, so a key cannot be added in one place and
    // forgotten in the other.
    foreach ($install->keys as $key => $default) {
      $field = strtolower($key);
      if (isset($_POST[$field])) write_configure($key, db_prepare_input($_POST[$field]));
    }
    $messageStack->add(GENERAL_CONFIG_SAVED, 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
    break;

  case 'restamp':
    // Re-target every stored document onto FORMS_DEFAULT_PAPER.
    //
    // Documents are seeded on the paper the install chose, but an installation
    // that predates that choice — or one that answered it wrongly — has
    // definitions on the wrong paper and no way to fix them short of editing
    // each by hand. This and the rebuild below lived on PhreeForm's tools tab
    // and came over with the engine (§13.42).
    validate_security($security_level, 3);
    require_once(DIR_FS_WORKING . 'functions/seed.php');
    $stamped = $skipped = 0;
    // Anchored on the <orientation> that FOLLOWS the page size, so it cannot
    // match one of the many font <size> elements. Only reachable now on a
    // company restored from a backup old enough to still hold xml; every
    // migrated definition goes through forms_restamp_native() above it.
    $pattern = '#(<size><!\[CDATA\[)[^\]]*(\]\]></size>\s*<orientation>)#';
    foreach (scandir(FORMS_DIR_DEFINITIONS) as $document) {
      // `pf_` AND DIGITS. A definition Forms has rewritten keeps its original
      // beside it as `pf_<id>.pre-forms`, which begins `pf_` too — a looser
      // test re-stamps the backups as well and counts them in the total.
      if (!preg_match('/^pf_\d+$/', $document)) continue;
      $file = FORMS_DIR_DEFINITIONS . $document;

      $done = forms_restamp_native($file, FORMS_DEFAULT_PAPER);
      if ($done === true)  { $stamped++; continue; }
      if ($done === false) { $skipped++; continue; }

      // null: not a native definition. Substituted TEXTUALLY rather than parsed
      // and written back — a round trip re-serialises the whole file, and
      // anything the reader and writer disagree about would be quietly lost. A
      // definition is the user's work and this action has no business
      // rewriting the parts it was not asked about.
      $contents = file_get_contents($file);
      if ($contents === false || preg_match_all($pattern, $contents) <> 1) { $skipped++; continue; }
      $updated = preg_replace($pattern, '${1}' . FORMS_DEFAULT_PAPER . '${2}', $contents, 1);
      if ($updated === null || $updated === $contents)   { $skipped++; continue; }
      if (file_put_contents($file, $updated) === false)  { $skipped++; continue; }
      $stamped++;
    }
    $messageStack->add(sprintf(FORMS_RESTAMP_DONE, $stamped, FORMS_DEFAULT_PAPER), 'success');
    if ($skipped) $messageStack->add(sprintf(FORMS_RESTAMP_SKIPPED, $skipped), 'caution');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
    break;

  case 'rebuild':
    // Rebuild the document list from the files on disk.
    //
    // The first thing anyone reaches for after a restore has left the list
    // wrong, which is exactly when a company is likeliest to hold a mixture of
    // native definitions and xml originals — so it reads BOTH. It used to read
    // only xml, and handed a native file to xml_to_object(), which returns the
    // JSON string rather than failing: every field read off it was NULL, and a
    // native company was rebuilt into a catalogue of nameless documents, filed
    // under Miscellaneous, each file renamed to a fresh id, reported as
    // success.
    validate_security($security_level, 3);
    require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
    require_once(DIR_FS_MODULES . 'forms/classes/legacy/legacy_reader.php');
    // load_reports() below calls admin_add_report_heading() and
    // admin_add_report_folder(), which live here. PhreeForm's tools tab got
    // them for free because its own page required this file; this one did not,
    // and the rebuild died on the first module it tried to re-file.
    require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');

    $db->Execute("truncate " . TABLE_PHREEFORM);
    // The folder structure first, from every installed module, or every
    // document below lands in Miscellaneous for want of a parent.
    foreach (scandir(DIR_FS_MODULES) as $entry) {
      if (in_array($entry, array('.', '..'), true) || !is_dir(DIR_FS_MODULES . $entry)) continue;
      if (!defined('MODULE_' . strtoupper($entry) . '_STATUS')) continue;
      if (!file_exists(DIR_FS_MODULES . $entry . '/classes/install.php')) continue;
      // The module's own ADMIN language, before its install class runs: the
      // folder names load_reports() files documents under are constants in it
      // (PB_PF_CUST_QUOTE and its kind), and without them the rebuild dies on
      // the first module it reaches — after truncating the list. PhreeForm's
      // equivalent pulled it per module in the same loop, and the port dropped
      // the line. Fourth time a transitive language pull has bitten in this
      // work (§13.34, §13.37, §13.41).
      gen_pull_language($entry, 'admin');
      require_once(DIR_FS_MODULES . $entry . '/classes/install.php');
      $classname = $entry . '_admin';
      if (!class_exists($classname)) continue;
      $module_install = new $classname;
      if (method_exists($module_install, 'load_reports')) $module_install->load_reports($entry);
    }

    $rebuilt = $orphaned = $unreadable = 0;
    $renames = array();
    foreach (scandir(FORMS_DIR_DEFINITIONS) as $document) {
      if (!preg_match('/^pf_\d+$/', $document)) continue;   // never a .pre-forms backup
      $old_id   = substr($document, 3);
      $contents = (string)file_get_contents(FORMS_DIR_DEFINITIONS . $document);

      if (str_starts_with(ltrim($contents), '{')) {
        $native = json_decode($contents);
        if (!$native instanceof stdClass || !isset($native->title, $native->type)) { $unreadable++; continue; }
        $title = (string)$native->title;
        $group = (string)($native->group ?? '');
        $type  = (string)$native->type;
        // A native definition carries no per-document ACL: PhreeForm's was
        // replaced by the three SECURITY_ID_FORMS_* keys, and 'u:0;g:0' —
        // unrestricted — is what that column means now, and what
        // forms_seed_native() writes.
        $security = 'u:0;g:0';
      } else {
        $legacy = \FreeBooks\Forms\Legacy\LegacyReader::parse($contents);
        if ($legacy === null) { $unreadable++; continue; }
        $title    = $legacy->title      ?? '';
        $group    = $legacy->groupname  ?? '';
        $type     = $legacy->reporttype ?? '';
        $security = $legacy->security   ?? '';
        if ($security == 'u:-1;g:-1') $security = 'u:' . $_SESSION['admin_id'] . 'g:-1';
      }

      $folder = $db->Execute("select id from " . TABLE_PHREEFORM . "
        where doc_group = '" . $db->prepare_input($group) . "' and doc_type = '0'");
      if ($folder->RecordCount() == 0) {
        $orphaned++;
        $folder = $db->Execute("select id from " . TABLE_PHREEFORM . " where doc_group = '"
            . ($type == 'frm' ? 'misc:misc' : 'misc') . "' and doc_type = '0'");
      }
      db_perform(TABLE_PHREEFORM, array(
        'parent_id'   => $folder->fields['id'],
        'doc_type'    => 's',
        'doc_title'   => $title,
        'doc_group'   => $group,
        'doc_ext'     => $type,
        'security'    => $security,
        'create_date' => date('Y-m-d'),
      ));
      $renames[$old_id] = db_insert_id();
      // Through a temporary name, because a new id may collide with an old one
      // that has not been walked yet.
      rename(FORMS_DIR_DEFINITIONS . $document, FORMS_DIR_DEFINITIONS . 'tmp_' . $old_id);
      $rebuilt++;
    }
    foreach ($renames as $old => $new) {
      rename(FORMS_DIR_DEFINITIONS . 'tmp_' . $old, FORMS_DIR_DEFINITIONS . 'pf_' . $new);
    }
    gen_add_audit_log(FORMS_TOOLS_REBUILD_TITLE);
    $messageStack->add(sprintf(FORMS_TOOLS_REBUILD_SUCCESS, $rebuilt, $orphaned), 'success');
    // Named rather than passed over: a file this cannot read is a document that
    // has just vanished from the list, and the table it would have been listed
    // in has already been truncated.
    if ($unreadable) $messageStack->add(sprintf(FORMS_TOOLS_REBUILD_SKIPPED, $unreadable), 'caution');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
    break;

  default:
}

/*****************   prepare to display templates  *************************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/forms.php');

$forms_paper_choices = array();
foreach (forms_paper_sizes() as $name => $label) {
  $forms_paper_choices[] = array('id' => $name, 'text' => $label);
}

// An installation enabled before a key existed has no row for it, so the
// constant falls back to the define() in defaults.php — which is the shipped
// value, and what such an installation is actually using.
$forms_paper        = (string)($_POST['forms_default_paper']        ?? FORMS_DEFAULT_PAPER);
$forms_rowspace     = (string)($_POST['forms_default_rowspace']     ?? FORMS_DEFAULT_ROWSPACE);
$forms_column_width = (string)($_POST['forms_default_column_width'] ?? FORMS_DEFAULT_COLUMN_WIDTH);

$include_header    = true;
$include_footer    = true;
$use_bootstrap_nav = true;
$include_template  = 'template_main.php';
define('PAGE_TITLE', FORMS_ADMIN_TITLE);
