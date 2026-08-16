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
 * Nothing here writes. The page has no save, and the overlay has no drag.
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

use FreeBooks\Forms\App\{CanvasLayout, FormRunner};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionLoader};
use FreeBooks\Forms\Document\Template;

$forms_canvas_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$loader = new DefinitionLoader($catalog);
$runner = new FormRunner($db, $catalog);

$forms_canvas_definition = null;
$forms_canvas_plan       = null;
$forms_canvas_boxes      = array();

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
  if (!$forms_canvas_plan->runnable()) {
    $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, implode(' ', $forms_canvas_plan->notes)), 'error');
  }
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
