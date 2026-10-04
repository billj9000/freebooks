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
//  Path: /modules/forms/ajax/design_preview.php
//
/**
 * The report editor's live preview (design §13.7 step 2).
 *
 * Renders the definition AS EDITED — not as stored — through the same
 * DocumentRunner and TablePdfRenderer that print it, and streams the PDF into
 * the editor's iframe. Nothing is saved.
 *
 * It builds the definition through DefinitionForm, which is the same class the
 * save uses. That is the whole point of the class existing: a preview assembled
 * by its own code is a preview of a different report, and it would be right
 * often enough to be trusted.
 *
 * It answers with a PDF or with an HTML page, never with the XML envelope the
 * other ajax ops use — the target is an iframe showing a document, and an error
 * has to be readable in that iframe rather than parsed by a caller that is not
 * there.
 */

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();

/**************  include page specific files    *********************/
gen_pull_language('forms');

// validate_ajax_user() proves there is a SESSION, not that it holds this
// permission. The editor is gated on FORMS_AUTHOR and so is its preview: an
// endpoint that renders any report for anyone with a login is the report
// engine handed out sideways. Checked after the language pull so the refusal
// can be read.
if (($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0) < 1) {
  http_response_code(403);
  die('<!doctype html><meta charset="utf-8">'
    . '<body style="font:13px system-ui,sans-serif;padding:1rem">'
    . FORMS_DESIGN_DENIED . '</body>');
}

// A module's defaults.php is NOT loaded for you — a page requires its own, and
// an ajax op has no DIR_FS_WORKING to require it from. FORMS_DEFAULT_COLUMN_WIDTH
// lives there, and reaching it undefined is fatal at the last line of this file,
// after the report has already been queried and built.
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, SampleData};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionForm, DefinitionLoader};
use FreeBooks\Forms\Deliver\HttpDelivery;
use FreeBooks\Forms\Document\TableDocument;
use FreeBooks\Forms\Render\{Formatter, TablePdfRenderer};

/**
 * A message the author can read, inside the preview pane.
 *
 * Deliberately a whole HTML document: this is loaded as an iframe's src, so a
 * bare string would be rendered as one anyway, and a fragment inherits none of
 * the page's styling.
 */
function forms_preview_message($text, $kind = 'error') {
  $colour = $kind === 'error' ? '#b02a37' : '#664d03';
  die('<!doctype html><meta charset="utf-8">'
    . '<body style="font:13px system-ui,sans-serif;color:' . $colour . ';padding:1rem">'
    . htmlspecialchars($text) . '</body>');
}

$forms_preview_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

try {
  $loader     = new DefinitionLoader($catalog);
  $definition = $loader->load($forms_preview_id);
} catch (\Throwable $e) {
  forms_preview_message($e->getMessage());
}

if ($definition->isForm()) forms_preview_message(FORMS_DESIGN_IS_FORM, 'caution');

$dataset = $definition->datasetId !== null && $catalog->has($definition->datasetId)
    ? $catalog->get($definition->datasetId) : null;

$binding = new DefinitionForm();
try {
  $edited = $binding->apply($definition, $_POST, $dataset);
} catch (\Throwable $e) {
  forms_preview_message($e->getMessage());
}

// The run dialog's answers are not part of a definition, so the preview picks
// the obvious ones. A structural report reads nothing without a period.
$period = (int)($db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
    . " where start_date <= '" . date('Y-m-d') . "' and end_date >= '" . date('Y-m-d') . "'")
    ->fields['period'] ?? 0);

// A date range JUST FOR THE PREVIEW, and its own field name so DefinitionForm
// never sees it. Without this, an author whose report covers "this quarter" on
// a company with older data has to change the report's stored preference — and
// then save that change — merely to see a row.
$previewDates = isset($_POST['preview_dates']) ? (string)$_POST['preview_dates'] : '';

try {
  $document = (new DocumentRunner($db, $catalog))->runDefinition($edited, array(
    'dates'      => $previewDates,
    'period'     => $period,
    'gl_account' => isset($_REQUEST['gl_account']) ? (string)$_REQUEST['gl_account'] : '',
  ));
} catch (\Throwable $e) {
  forms_preview_message($e->getMessage());
}

$warnings = $binding->warnings();
if ($document instanceof TableDocument && count($document->rows) === 0) {
  // An empty report is filled with invented rows, and says so in its filter
  // band (§13.2): column widths, alignment, grouping and totals are exactly
  // what an author is judging, and none of them is visible on a page of
  // furniture. A fresh install has no data at all and must still be able to
  // lay a report out.
  $note = FORMS_DESIGN_PREVIEW_SAMPLE
        . ($warnings ? ' ' . implode(' ', $warnings) : '');
  $document = SampleData::fill($document, $dataset, $note, $edited->groupBy);
}

$renderer = new TablePdfRenderer(
  formatter:          new Formatter(
                        currencies:      $currencies,
                        defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                        lookups:         forms_formatter_lookups()),
  groupTotalLabel:    FORMS_GROUP_TOTAL_FOR,
  reportTotalLabel:   FORMS_REPORT_TOTAL_FOR,
  defaultColumnWidth: (float)FORMS_DEFAULT_COLUMN_WIDTH,
);

(new HttpDelivery(HttpDelivery::INLINE))->deliver($renderer->render($document));
