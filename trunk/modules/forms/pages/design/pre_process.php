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
//  Path: /modules/forms/pages/design/pre_process.php
//
/**
 * The report editor (design §13.7 step 2).
 *
 * The smaller half of the designer, and the half that has to exist first: it
 * exercises load and save end to end before any canvas is built. A report is a
 * column list with grouping, sorting and filters — dragging boxes around one
 * would be theatre (§13.1) — so this is a grid, with the live preview rendered
 * through the engine that will print it.
 *
 * Three things about it are load-bearing rather than incidental:
 *
 * 1. **Saving writes the NATIVE format.** The definition it opened may have
 *    been PhreeForm XML; what it writes back never is. That is decision I, and
 *    it means a saved definition is one PhreeForm can no longer read — so the
 *    XML is kept beside it as `pf_<id>.pre-forms`, exactly as the bulk
 *    migration will (§13.3 condition 2), and the page says so before the
 *    author commits to it.
 * 2. **The preview and the save build the definition the same way**, through
 *    DefinitionForm. A preview assembled by different code is a preview of a
 *    different report, which is the one defect a preview must not have.
 * 3. **Every field name submitted is checked against the dataset.** The selects
 *    on the page are a convenience; the POST is whatever the browser sends
 *    (§13.4).
 *
 * Gated on SECURITY_ID_FORMS_AUTHOR — defined since 1.00 and unused until now.
 * It is the middle tier of decision F: running a document, authoring one and
 * defining which tables may join are three different privileges.
 */

$security_level = validate_user(SECURITY_ID_FORMS_AUTHOR);

require(DIR_FS_WORKING . 'defaults.php');
// In this order, at file scope: phreeform's language file defines the PF_FONT_*
// constants its defaults.php reads, and that defines PF_DIR_MY_REPORTS, which is
// where definitions live. $PaperSizes and $DateChoices are plain variables in
// those files and would vanish if this moved inside a function.
gen_pull_language('phreeform');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
require(DIR_FS_MODULES . 'phreedom/defaults.php');   // $DateChoices

use FreeBooks\Forms\App\{DocumentRunner, RunPlan};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, FilterOperator, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionForm, DefinitionLoader};
use FreeBooks\Forms\Document\Align;

$forms_design_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$forms_custom = new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS);
$forms_custom->registerAll($catalog);

$loader = new DefinitionLoader($catalog);
$runner = new DocumentRunner($db, $catalog);

$forms_design_definition = null;
$forms_design_dataset    = null;
$forms_design_was_legacy = false;

/**
 * Keep the PhreeForm original beside the native file, once.
 *
 * §13.3 condition 2, applied a definition at a time rather than in bulk: the
 * rename costs nothing and it is the only copy of what the author started
 * from. `.pre-forms` is skipped by the catalog, which reads `pf_<id>` exactly.
 * Only the FIRST save moves anything — a second would overwrite the original
 * with a native file and lose the very thing this keeps.
 */
function forms_design_keep_original($path) {
  $kept = $path . '.pre-forms';
  if (file_exists($kept) || !file_exists($path)) return;
  @copy($path, $kept);
}

/***************   Load, then act   *************************/
// Arriving with no report is the ORDINARY way in from the menu, not a failure:
// the page answers with a list to choose from. Only a report that was named and
// could not be loaded is an error — saying "No report was chosen to edit" in red
// to someone who has just clicked a menu entry blames them for the click.
if ($forms_design_id > 0) {
  try {
    $forms_design_definition = $loader->load($forms_design_id);
    $forms_design_was_legacy = $loader->wasLegacy();
  } catch (DefinitionException $e) {
    $messageStack->add($e->getMessage(), 'error');
  }
}

// A form is not a report, and the canvas that edits one is step 3. Saying so is
// better than offering a column grid for a positioned layout.
if ($forms_design_definition !== null && $forms_design_definition->isForm()) {
  $messageStack->add(FORMS_DESIGN_IS_FORM, 'caution');
  $forms_design_definition = null;
}

if ($forms_design_definition !== null) {
  // Anything the conversion could not carry is the author's business BEFORE
  // they save: saving writes what converted, so an unreported loss becomes
  // permanent at exactly this point.
  foreach ($loader->warnings() as $warning) {
    $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
  }
  if ($forms_design_definition->datasetId !== null) {
    $forms_design_dataset = $catalog->has($forms_design_definition->datasetId)
        ? $catalog->get($forms_design_definition->datasetId) : null;
  }
}

if ($forms_design_definition !== null && ($_REQUEST['action'] ?? '') === 'save') {
  $binding = new DefinitionForm();
  try {
    $edited = $binding->apply($forms_design_definition, $_POST, $forms_design_dataset);
    foreach ($binding->warnings() as $warning) {
      $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
    }

    forms_design_keep_original($loader->path($forms_design_id));
    $loader->save($edited, $forms_design_id);

    // The catalog row carries the title the document list shows, so a renamed
    // report that updates only the file is a report renamed in one place.
    $db->Execute("update " . TABLE_PHREEFORM . " set doc_title = '"
        . $db->prepare_input($edited->title) . "' where id = " . (int)$forms_design_id);

    $messageStack->add(sprintf(FORMS_DESIGN_SAVED, $edited->title), 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT,
        'module=forms&page=design&rID=' . $forms_design_id, 'SSL'));
  } catch (\Throwable $e) {
    // Redisplayed with what was typed rather than with what is stored: a
    // refused save is usually one row wrong out of a dozen.
    $messageStack->add(sprintf(FORMS_DESIGN_ERROR, $e->getMessage()), 'error');
  }
}

/***************   Prepare the page   *************************/
define('PAGE_TITLE', $forms_design_definition !== null
    ? sprintf(FORMS_DESIGN_TITLE_FOR, $forms_design_definition->title)
    : FORMS_DESIGN_TITLE);

// This page no longer lists anything. It used to answer "no report chosen" with
// a chooser of its own, which was a second list of the same reports the Reports
// page lists — labelled with the raw doc_group codes that page deliberately
// stopped showing. One list, and the editor is opened from the Edit button on
// it; arriving here with no report now says so and points at that list.

// On a refused save, redisplay the submitted values; otherwise the stored ones.
$forms_design_values = $forms_design_definition === null ? array()
    : (($_REQUEST['action'] ?? '') === 'save' && $_POST
        ? array_replace_recursive(DefinitionForm::toRequest($forms_design_definition), $_POST)
        : DefinitionForm::toRequest($forms_design_definition));

// The palette: everything this dataset declares, and nothing else (§13.4).
$forms_design_fields = array();
if ($forms_design_dataset !== null) {
  foreach ($forms_design_dataset->fields as $key => $field) {
    $forms_design_fields[] = array('id' => $key, 'text' => $field->label . ' (' . $key . ')');
  }
} elseif ($forms_design_definition !== null) {
  // A structural report has no dataset — its columns are the provider's own
  // contract, so the palette is what it already has and nothing may be added.
  foreach ($forms_design_definition->columns as $column) {
    $forms_design_fields[] = array('id' => $column->key, 'text' => $column->heading . ' (' . $column->key . ')');
  }
}
// The resolved columns have no dataset field of their own but are real columns.
if ($forms_design_definition !== null) {
  foreach (array_keys($forms_design_definition->resolvedColumns) as $key) {
    $forms_design_fields[] = array('id' => $key, 'text' => $key);
  }
}

$forms_design_sortable = $forms_design_fields;

$forms_design_operators = array();
foreach (FilterOperator::cases() as $case) {
  $forms_design_operators[] = array('id' => $case->value, 'text' => $case->label());
}

$forms_design_aligns = array(
  array('id' => '',                    'text' => FORMS_DESIGN_ALIGN_DEFAULT),
  array('id' => Align::Left->value,    'text' => FORMS_DESIGN_ALIGN_LEFT),
  array('id' => Align::Center->value,  'text' => FORMS_DESIGN_ALIGN_CENTER),
  array('id' => Align::Right->value,   'text' => FORMS_DESIGN_ALIGN_RIGHT),
);

// Paper sizes are stored as a NAME only; $PaperSizes keys them as
// "Name:width:height" because the legacy designer carried the dimensions along.
$forms_design_papers = array();
foreach ($PaperSizes as $key => $label) {
  $name = explode(':', $key)[0];
  $forms_design_papers[$name] = array('id' => $name, 'text' => $label);
}
$forms_design_papers = array_values($forms_design_papers);

$forms_design_orientations = array(
  array('id' => 'P', 'text' => FORMS_DESIGN_PORTRAIT),
  array('id' => 'L', 'text' => FORMS_DESIGN_LANDSCAPE),
);

$forms_design_dates = array();
foreach ($DateChoices as $key => $text) {
  if ($key === 'b') continue;   // an explicit from/to range needs two more fields
  $forms_design_dates[] = array('id' => $key, 'text' => $text);
}

// The preview's own range. The same choices, with "as the report is set" first:
// most of the time an author wants to see what the report will actually do, and
// only widens it when that returns nothing on this company's data.
$forms_design_preview_dates = array_merge(
  array(array('id' => '', 'text' => FORMS_DESIGN_PREVIEW_AS_SET)),
  $forms_design_dates);

$use_bootstrap_nav = true;
