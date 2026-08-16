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

use FreeBooks\Forms\App\{CanvasLayout, FormRunner, ImageStore};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionLoader, TemplateForm};
use FreeBooks\Forms\Document\{Align, Template, Visibility};

$forms_canvas_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$loader = new DefinitionLoader($catalog);
$runner = new FormRunner($db, $catalog);

$forms_canvas_definition = null;
$forms_canvas_plan       = null;
$forms_canvas_boxes      = array();
$forms_canvas_values     = array();

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
      $db->Execute("update " . TABLE_PHREEFORM . " set doc_title = '"
          . $db->prepare_input($edited->title) . "' where id = " . (int)$forms_canvas_id);

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

define('PAGE_TITLE', $forms_canvas_definition !== null
    ? sprintf(FORMS_CANVAS_TITLE_FOR, $forms_canvas_definition->title)
    : FORMS_CANVAS_TITLE);

// With nothing chosen, the forms this user could look at.
$forms_canvas_choices = array();
if ($forms_canvas_definition === null) {
  $result = $db->Execute("select id, doc_title, doc_group from " . TABLE_PHREEFORM . "
    where doc_type <> '0' and doc_ext = 'frm' order by doc_group, doc_title");
  while (!$result->EOF) {
    $forms_canvas_choices[] = array(
      'id'    => (int)$result->fields['id'],
      'title' => (string)$result->fields['doc_title'],
      'group' => (string)$result->fields['doc_group'],
    );
    $result->MoveNext();
  }
}

$use_bootstrap_nav = true;
