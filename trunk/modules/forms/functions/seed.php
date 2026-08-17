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
//  Path: /modules/forms/functions/seed.php
//
/**
 * Seeding a company with native definitions (design §13.5, step 5).
 *
 * `admin_add_reports()` copies the shipped definitions into a company at
 * install, and at "add reports" from Module Administration. This is the native
 * half of it: where a `.json` sits beside a module's `.xml`, the JSON is what
 * gets seeded, so a new install has Forms definitions from the start rather
 * than legacy ones waiting to be converted on every load.
 *
 * ## Deliberately dependency-free
 *
 * No Forms class is loaded here, and none is needed. Seeding is three things —
 * copy the file, stamp the paper size, insert the catalogue row — and all three
 * are done on the decoded JSON as a plain array. That matters because this runs
 * during INSTALL, before the Forms module is registered, and because a decode /
 * re-encode through `DefinitionCodec` would put the shipped bytes through a
 * round trip for no reason: the file is already exactly what the loader wants.
 *
 * It is the same reasoning `ImportReport()` uses when it edits the page size in
 * the XML rather than rebuilding the object, and the same reasoning behind the
 * re-stamp being a substitution rather than a re-serialisation.
 *
 * ## What the catalogue row needs, and where it comes from
 *
 * `doc_title`, `doc_group` and `doc_ext` are the definition's own `title`,
 * `group` and `type` — `type` is literally `rpt` or `frm`, the same values the
 * column takes. The other two columns carry no information and are constants:
 * measured across every row on two companies, `doc_type` is always `'s'` and
 * `security` always `'u:0;g:0'`. PhreeForm's per-document ACL string is
 * replaced by the three SECURITY_ID_FORMS_* keys (decision F), so writing the
 * unrestricted value is not a shortcut — it is what the field means now.
 */

/**
 * Seed one native definition into a company.
 *
 * @param string $path       the shipped `.json`
 * @param string $save_path  the company's phreeform directory, with separator
 * @param string $paper_size Letter/A4/…; '' means leave the file's own choice
 * @return bool false on any failure, with a message on the stack
 */
function forms_seed_native($path, $save_path, $paper_size = '') {
  global $db, $messageStack;

  $contents = @file_get_contents($path);
  if ($contents === false) return false;

  // Decoded to OBJECTS, not arrays. An empty JSON object survives
  // json_decode($s, true) as an empty PHP array, and json_encode() then writes it
  // back as `[]` — so a definition with `"style": {}` was seeded as
  // `"style": []`, and every seeded file differed from the shipped one it was
  // supposed to be a copy of. Harmless to read, because DefinitionCodec treats
  // the two the same; fatal to the claim this function makes below, that a seeded
  // file is byte-identical to one the editor would write. That claim is what
  // every comparison between a company's definitions and the shipped set rests
  // on, including adopting a tidied layout back into the shipping set.
  $definition = json_decode($contents);
  if (!$definition instanceof stdClass
      || !isset($definition->title) || !isset($definition->type)) {
    // Guarded: this runs at INSTALL, where the forms language file may not
    // have been pulled yet, and a missing constant would fatal while reporting
    // a failure — the one moment nobody is watching.
    if (isset($messageStack) && defined('FORMS_SEED_UNREADABLE')) {
      $messageStack->add(sprintf(FORMS_SEED_UNREADABLE, basename($path)), 'error');
    }
    return false;
  }

  // The installer's Letter/A4 answer, applied to the seeded copy. Without this
  // a native seeding path loses that choice on exactly the installs that have
  // just made it — the trap §13.5 names, and the reason ImportReport() does the
  // same thing to the XML.
  //
  // Size only. PF_DEFAULT_ORIENTATION is deliberately NOT applied: several
  // shipped reports choose landscape on purpose and would be squeezed onto a
  // portrait page.
  if ($paper_size == '' && defined('PF_DEFAULT_PAPERSIZE')) $paper_size = PF_DEFAULT_PAPERSIZE;
  if ($paper_size <> '' && isset($definition->page->size)) {
    // The two formats spell a paper differently, and the caller passes the
    // LEGACY spelling because the installer, the company manager and
    // PF_DEFAULT_PAPERSIZE all carry it: "A4:210:297", name and dimensions.
    // A native definition stores the name alone — the converter has always
    // done explode(':')[0] — and TCPDF looks a paper up BY NAME, so writing
    // the full token here produces a size no renderer can resolve, and every
    // seeded document silently falls back to A4.
    $definition->page->size = explode(':', $paper_size)[0];
  }

  $title = (string)$definition->title;
  $group = (string)($definition->group ?? '');
  $type  = (string)$definition->type;

  // Same refusal ImportReport() makes: a definition of this name already in the
  // catalog WITH its file on disk is not re-seeded, because that would silently
  // replace a document someone may have edited.
  $rID    = '';
  $result = $db->Execute("select id from " . TABLE_PHREEFORM . "
    where doc_title = '" . $db->prepare_input($title) . "' and doc_type <> '0'");
  if ($result->RecordCount() > 0) {
    $rID = $result->fields['id'];
    if (file_exists($save_path . 'pf_' . $rID)) {
      if (isset($messageStack) && defined('PHREEFORM_REPDUP')) {
        $messageStack->add(sprintf(PHREEFORM_REPDUP, $title), 'error');
      }
      return false;
    }
  }

  // The folder this document belongs in, by group. Reports and forms have
  // separate folder rows ('fr' and 'ff'), and a group with neither falls back
  // to Miscellaneous — the same lookup save_report() makes, and the reason a
  // document in an unfoldered group still appears.
  $folder = $db->Execute("select id from " . TABLE_PHREEFORM . "
    where doc_group = '" . $db->prepare_input($group) . "' and doc_type = '0'
      and doc_ext in ('ff', 'fr')");
  if ($folder->RecordCount() == 0) {
    $folder = $db->Execute("select id from " . TABLE_PHREEFORM . "
      where doc_group = '" . ($type === 'frm' ? 'misc:misc' : 'misc') . "' and doc_type = '0'");
  }

  $sql_array = array(
    'parent_id' => $folder->RecordCount() ? $folder->fields['id'] : 0,
    'doc_type'  => 's',
    'doc_title' => $title,
    'doc_group' => $group,
    'doc_ext'   => $type,
    'security'  => 'u:0;g:0',
  );
  if ($rID) {
    $sql_array['last_update'] = date('Y-m-d');
    db_perform(TABLE_PHREEFORM, $sql_array, 'update', 'id = ' . (int)$rID);
  } else {
    $sql_array['create_date'] = date('Y-m-d');
    db_perform(TABLE_PHREEFORM, $sql_array);
    $rID = db_insert_id();
  }

  // Re-encoded rather than copied, because the paper size may have changed.
  // The flags match DefinitionCodec::encode(), the decode above keeps objects
  // objects, and the trailing newline is the one that encoder ends with — so a
  // seeded file differs from the shipped one in the page size and in nothing
  // else. That is what lets a verification tool compare the two, and what lets a
  // layout tidied in a company be adopted back into the shipping set.
  $json = json_encode($definition,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json !== false) $json .= "\n";
  $written = $json === false ? false : @file_put_contents($save_path . 'pf_' . $rID, $json);
  if ($written === false) {
    $db->Execute("delete from " . TABLE_PHREEFORM . " where id = " . (int)$rID);
    if (isset($messageStack) && defined('PHREEFORM_WRITE_ERROR')) {
      $messageStack->add(sprintf(PHREEFORM_WRITE_ERROR, $save_path . 'pf_' . $rID), 'error');
    }
    return false;
  }
  return true;
}

/**
 * Re-target one stored definition onto a paper size, if it is a native one.
 *
 * The re-stamp in Report Settings walks every `pf_*` file and substitutes the
 * page size textually. That substitution is anchored on XML, so a native
 * definition matches nothing and is counted as skipped — which is a company
 * asking to move onto A4 and being told, quietly, that some of its documents
 * would not move. Every document is native after the migration, so without
 * this the feature ends up applying to nothing at all.
 *
 * Rewritten as decoded JSON rather than by string substitution: the native
 * format has one `"size"` key inside `page`, and reaching it structurally is
 * both exact and shorter than a pattern that has to avoid every font size in
 * the file. The re-encode is faithful because the decode is — no Definition,
 * no codec, no round trip through the object model.
 *
 * @return ?bool true stamped, false failed, null not a native file (so the
 *   caller applies its own rule — the two formats coexist until step 6)
 */
function forms_restamp_native($file, $paper_size) {
  $contents = @file_get_contents($file);
  if ($contents === false) return null;
  if (!str_starts_with(ltrim($contents), '{')) return null;   // not ours to stamp

  // Objects, for the reason forms_seed_native() gives: decoding to arrays turns
  // every `{}` in the file into `[]` on the way back out, so a re-stamp quietly
  // rewrote parts of a definition it was not asked about — the very thing the
  // note above says it has no business doing.
  $definition = json_decode($contents);
  if (!$definition instanceof stdClass || !isset($definition->page->size)) return false;

  // The name alone, as the format stores it — see forms_seed_native().
  $definition->page->size = explode(':', (string)$paper_size)[0];
  // Flags and trailing newline as DefinitionCodec::encode() writes them, so a
  // re-stamp does not leave the file differing from an editor's save by whitespace.
  $json = json_encode($definition,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  $json .= "\n";
  return @file_put_contents($file, $json) !== false;
}
