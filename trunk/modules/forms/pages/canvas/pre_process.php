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
//  Path: /modules/forms/pages/canvas/pre_process.php
//
/**
 * The form canvas, read-only (design §13.7 step 3).
 *
 * A form is positioned boxes on a page, so unlike a report it genuinely wants a
 * canvas (§13.1). This is the half of it that proves the approach before any
 * dragging is built: the REAL rendered PDF underneath, rasterized in the
 * browser, with the element outlines drawn on a transparent layer above it.
 *
 * That is decision H, and the thing it settles is open risk 1 — that a box
 * drawn at 140 mm sits over the text rendered at 140 mm, across page sizes,
 * orientations and zoom levels. It is the cheapest point at which to abandon
 * approach C if the alignment does not hold, which is why it comes before
 * anything can be moved.
 *
 * Step 4 added the editing: drag, resize, snap, add, delete, z-order and an
 * inspector. What did not change is where the picture comes from — the boxes
 * are still drawn over the real rendered PDF, which is the whole reason the
 * positions can be trusted while they are being moved.
 */

$security_level = validate_user(SECURITY_ID_FORMS_AUTHOR);

require(DIR_FS_WORKING . 'defaults.php');
// In this order, at file scope — phreeform's language file defines the PF_FONT_*
// constants its defaults.php reads, and that defines PF_DIR_MY_REPORTS.
gen_pull_language('phreeform');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{CanvasLayout, FormRunner, ImageStore, DocumentStore, DocumentStoreException};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionLoader, TemplateForm};
use FreeBooks\Forms\Document\{Align, Template, Visibility};

$forms_canvas_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;
$forms_canvas_new_id = 0;   // set when an action just made a document to open

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$loader = new DefinitionLoader($catalog);
$forms_store = new DocumentStore($db, $catalog, $loader, TABLE_PHREEFORM);
$runner = new FormRunner($db, $catalog);

$forms_canvas_definition = null;
$forms_canvas_plan       = null;
$forms_canvas_boxes      = array();
$forms_canvas_values     = array();

// New, Copy, Rename and Delete, before anything is loaded — a copy opens the
// COPY and not the document it was made from, and a delete has no document to
// open at all (§13.29).
$forms_canvas_new_id = forms_document_action($forms_store, \FreeBooks\Forms\Definition\Definition::FORM, $security_level);
if ($forms_canvas_new_id > 0) $forms_canvas_id = $forms_canvas_new_id;
if (($_REQUEST['action'] ?? '') === 'delete') $forms_canvas_id = 0;

// Arriving with nothing chosen is the ordinary way in from the menu, so the
// page answers with a list rather than with an error.
if ($forms_canvas_id > 0) {
  try {
    $forms_canvas_definition = $loader->load($forms_canvas_id);
  } catch (DefinitionException $e) {
    $messageStack->add($e->getMessage(), 'error');
  }
}

if ($forms_canvas_definition !== null && !$forms_canvas_definition->isForm()) {
  // A report is a column list, and the grid edits it. Saying so beats drawing
  // an empty canvas over a page of rows.
  $messageStack->add(FORMS_CANVAS_IS_REPORT, 'caution');
  $forms_canvas_definition = null;
}

// A receipt has no geometry to edit, so it goes to the editor that CAN edit it
// (§13.27–13.28). Sent rather than refused: which kind of layout a document has
// is the system's business to know, not something a user should have to work
// out from a list before clicking.
//
// This is still the guard it replaces. The redirect happens before anything
// reads the template, and the save branch below is gated on a definition being
// loaded — so the canvas can no more write a receipt now than when it declined
// to open one. The list beside it links receipts straight here, so this is
// reached by a bookmark or an old link rather than in normal use.
if ($forms_canvas_definition !== null && $forms_canvas_definition->isSerial()) {
  gen_redirect(html_href_link(FILENAME_DEFAULT,
      'module=forms&page=receipt&rID=' . $forms_canvas_id, 'SSL'));
}

if ($forms_canvas_definition !== null) {
  foreach ($loader->warnings() as $warning) {
    $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
  }
  $forms_canvas_plan  = $runner->planDefinition($forms_canvas_definition);
  // The same directory the renderer draws from, so an image's outline is
  // measured against the file that will actually be printed.
  $forms_canvas_boxes = CanvasLayout::boxes(
      $forms_canvas_definition->template ?? new Template(),
      PF_DIR_MY_REPORTS . 'images');
  // The editable values, in the same order as the boxes, so the canvas can put
  // an element in the inspector without asking the server for it.
  $forms_canvas_values = TemplateForm::toRequest(
      $forms_canvas_definition->template ?? new Template());
  if (!$forms_canvas_plan->runnable()) {
    $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, implode(' ', $forms_canvas_plan->notes)), 'error');
  }
}

/**
 * Keep the PhreeForm original beside the native file, once.
 *
 * §13.3 condition 2, a definition at a time. Only the FIRST save moves
 * anything: a second would overwrite the original with a native file and lose
 * the very thing this keeps.
 */
function forms_canvas_keep_original($path) {
  $kept = $path . '.pre-forms';
  if (file_exists($kept) || !file_exists($path)) return;
  @copy($path, $kept);
}

$forms_canvas_bindings = array();
if ($forms_canvas_definition !== null) {
  $forms_canvas_bindings = forms_binding_names($forms_canvas_definition, $runner, $catalog, $db);
}

if ($forms_canvas_definition !== null && ($_REQUEST['action'] ?? '') === 'save') {
  if ($security_level < 3) {
    $messageStack->add(FORMS_DESIGN_DENIED, 'error');
  } else {
    $binding = new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names());
    try {
      $edited = $binding->apply($forms_canvas_definition, $_POST, $forms_canvas_bindings);
      foreach ($binding->warnings() as $warning) {
        $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
      }

      forms_canvas_keep_original($loader->path($forms_canvas_id));
      $loader->save($edited, $forms_canvas_id);

      // The catalog row carries the title the document list shows.
      // Through the store, which refuses a name another document already
      // has. Several things resolve a document BY TITLE, so two of one
      // name makes which they find a matter of collation (§13.29).
      $forms_store->updateTitle((int)$forms_canvas_id, $edited->title);

      $messageStack->add(sprintf(FORMS_DESIGN_SAVED, $edited->title), 'success');
      gen_redirect(html_href_link(FILENAME_DEFAULT,
          'module=forms&page=canvas&rID=' . $forms_canvas_id, 'SSL'));
    } catch (\Throwable $e) {
      $messageStack->add(sprintf(FORMS_DESIGN_ERROR, $e->getMessage()), 'error');
    }
  }
}

// The images an author may choose between: what is actually in the folder, and
// nothing else. Listed by ImageStore rather than by a glob here, because that is
// also the class an upload goes through — a lister and a writer that disagree
// about which files count leave the folder holding images nobody can choose.
$forms_canvas_images = array(array('id' => '', 'text' => FORMS_DESIGN_GROUP_NONE));
foreach ((new ImageStore(PF_DIR_MY_REPORTS . 'images'))->names() as $name) {
  $forms_canvas_images[] = array('id' => $name, 'text' => $name);
}
// Taken from the class that enforces it: a template saying "4 MB" beside code
// that allows something else is the kind of drift nobody notices until an
// upload is refused for a reason the page denied.
$forms_canvas_max_mb = ImageStore::MAX_BYTES >> 20;

// The palette, and the choices the inspector offers.
$forms_canvas_palette = array();
foreach (array_keys($forms_canvas_bindings) as $name) {
  $forms_canvas_palette[] = array('id' => $name, 'text' => $name);
}
sort($forms_canvas_palette);

// The faces on offer, plus whatever this form already uses. A definition may
// name a face this list does not carry — a customised install, an older
// PhreeForm — and a select with no option for it would restyle the element on
// the first save, silently.
$forms_canvas_fonts = array(array('id' => '', 'text' => FORMS_CANVAS_FONT_DEFAULT));
foreach (forms_font_names() as $name => $label) {
  $forms_canvas_fonts[] = array('id' => $name, 'text' => $label);
}
$forms_canvas_known = forms_font_names();
foreach ($forms_canvas_definition?->template?->elements ?? array() as $element) {
  $used = $element->style?->font;
  if ($used !== null && $used !== '' && !isset($forms_canvas_known[$used])) {
    $forms_canvas_known[$used] = $used;
    $forms_canvas_fonts[] = array('id' => $used, 'text' => sprintf(FORMS_CANVAS_FONT_OTHER, $used));
  }
}

$forms_canvas_aligns = array(
  array('id' => '',                   'text' => FORMS_DESIGN_ALIGN_DEFAULT),
  array('id' => Align::Left->value,   'text' => FORMS_DESIGN_ALIGN_LEFT),
  array('id' => Align::Center->value, 'text' => FORMS_DESIGN_ALIGN_CENTER),
  array('id' => Align::Right->value,  'text' => FORMS_DESIGN_ALIGN_RIGHT),
);
$forms_canvas_visibility = array(
  array('id' => 'all',       'text' => FORMS_CANVAS_VIS_ALL),
  array('id' => 'first',     'text' => FORMS_CANVAS_VIS_FIRST),
  array('id' => 'last',      'text' => FORMS_CANVAS_VIS_LAST),
  array('id' => 'continued', 'text' => FORMS_CANVAS_VIS_CONTINUED),
);
$forms_canvas_addable = array();
foreach (TemplateForm::ADDABLE as $kind) {
  $forms_canvas_addable[] = array('id' => $kind, 'text' => constant('FORMS_CANVAS_ADD_'
      . strtoupper($kind)));
}

// How one line of an address block joins to the next (§13.30). Taken from
// TemplateForm rather than written out here, so the list an author reads cannot
// drift from the list the save accepts — and both are held against what
// Formatter actually performs by verify_forms_canvas.php.
$forms_canvas_separators = array();
foreach (TemplateForm::SEPARATORS as $separator) {
  $forms_canvas_separators[] = array(
    'id'   => $separator,
    'text' => constant('FORMS_CANVAS_SEP_' . ($separator === ''
                ? 'NONE' : strtoupper(str_replace('-', '_', $separator)))),
  );
}

// The value formats, grouped, plus whatever this form already uses that the
// list does not offer (§13.31). Gathered from the WHOLE definition rather than
// per element, because the grids are built from one <template> cloned per row —
// there is a single select markup for every column of every table on the form,
// so it has to carry every name any of them might need to keep.
$forms_canvas_formats = forms_format_choices(
    forms_formats_in_use($forms_canvas_definition));

// The barcode symbologies, plus whatever this form already uses. Same rule as
// the font list, for the same reason: a definition may name a type this list
// does not carry, and a select with no option for it would silently re-code
// somebody's barcode on the first save.
$forms_canvas_symbologies = array();
foreach (TemplateForm::SYMBOLOGIES as $symbology) {
  $forms_canvas_symbologies[] = array('id' => $symbology, 'text' => $symbology);
}
$forms_canvas_seen_codes = array_flip(TemplateForm::SYMBOLOGIES);
foreach ($forms_canvas_definition?->template?->elements ?? array() as $element) {
  if (!$element instanceof \FreeBooks\Forms\Document\Barcode) continue;
  if (isset($forms_canvas_seen_codes[$element->symbology])) continue;
  $forms_canvas_seen_codes[$element->symbology] = true;
  $forms_canvas_symbologies[] = array('id' => $element->symbology,
      'text' => sprintf(FORMS_CANVAS_SYMBOLOGY_OTHER, $element->symbology));
}

define('PAGE_TITLE', $forms_canvas_definition !== null
    ? sprintf(FORMS_CANVAS_TITLE_FOR, $forms_canvas_definition->title)
    : FORMS_CANVAS_TITLE);

// With nothing chosen, the forms this user could look at.
$forms_canvas_choices = array();
if ($forms_canvas_definition === null) {
  // This is the forms catalogue, not merely a chooser: with Documents now
  // listing reports alone, every form in the company is reached from here.
  // Folder NAMES, not the doc_group codes it used to print — `cust:inv` is an
  // internal key, and a list sorted by one looks unsorted to the person reading
  // the other.
  $forms_canvas_folders = forms_folder_titles($db);
  $result = $db->Execute("select id, doc_title, doc_group from " . TABLE_PHREEFORM . "
    where doc_type <> '0' and doc_ext = 'frm' order by doc_group, doc_title");
  while (!$result->EOF) {
    $id = (int)$result->fields['id'];
    // Each definition is READ, not merely listed, because a receipt is known by
    // its stored layout and by nothing else — `pos:rcpt` is not reserved and a
    // company may have written its own. That is a file read per form, which is
    // what the Documents page already does for every report.
    $serial = false;
    try { $serial = $loader->load($id)->isSerial(); }
    catch (DefinitionException) { /* listed anyway; opening it will say why */ }

    $forms_canvas_choices[] = array(
      'id'     => $id,
      'title'  => (string)$result->fields['doc_title'],
      'group'  => (string)$result->fields['doc_group'],
      'folder' => forms_folder_for((string)$result->fields['doc_group'], true,
                                   $forms_canvas_folders),
      // Which editor this one opens in. The list is the single catalogue of
      // forms, so it is the list that has to know.
      'serial' => $serial,
      'page'   => $serial ? 'receipt' : 'canvas',
      // Whether deleting it would leave a Print button with nothing to open.
      // Read here so the confirmation can SAY so, while there is still a
      // decision to be made about it.
      'last'   => $forms_store->isLastInGroup($id),
    );
    $result->MoveNext();
  }
  $forms_canvas_groups   = $forms_store->groups(true);
  $forms_canvas_datasets = array();
  foreach ($catalog->all() as $dataset) $forms_canvas_datasets[$dataset->id] = $dataset->label;
  asort($forms_canvas_datasets);

  usort($forms_canvas_choices, function ($a, $b) {
    return strcasecmp($a['folder'], $b['folder']) ?: strcasecmp($a['title'], $b['title']);
  });
}

$use_bootstrap_nav = true;
