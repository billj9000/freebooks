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
//  Path: /modules/forms/ajax/receipt_preview.php
//
/**
 * The receipt editor's live preview. See §13.28.
 *
 * Renders the receipt AS EDITED — not as stored — through the same SerialForm
 * and SerialRenderer that save and print it. Nothing is written.
 *
 * It matters more here than on any other Forms page. A receipt's fields OVERLAY
 * the line being built rather than sitting beside each other, so what a change
 * to a width or an alignment does is genuinely hard to predict and trivial to
 * see. A preview that only appeared after saving would make the author commit
 * to a change in order to find out what it was.
 *
 * Built through SerialForm, the same class the save uses, for the reason the
 * report preview gives: a preview assembled by its own code is a preview of a
 * different document, and it would agree often enough to be trusted.
 *
 * Answers with an HTML FRAGMENT, not the XML envelope the older ajax ops use —
 * htmx swaps it straight into the preview pane, and an error has to be readable
 * there rather than parsed by a caller that is not listening for one.
 */

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();

/**************  include page specific files    *********************/
gen_pull_language('forms');

// validate_ajax_user() proves there is a SESSION, not that it holds this
// permission — and this endpoint renders a document from a definition anyone
// could name. Gated on the same key the editor is.
if (($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0) < 1) {
  http_response_code(403);
  die('<p class="text-danger small mb-0">' . htmlspecialchars(FORMS_DESIGN_DENIED) . '</p>');
}

// FORMS_DIR_DEFINITIONS is what the loader resolves `pf_<id>` against; without
// it every load runs at a relative path and finds nothing. A module's
// defaults.php is not loaded for you, and an ajax op has no DIR_FS_WORKING to
// require it from.
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\FormRunner;
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionLoader, SerialForm};
use FreeBooks\Forms\Document\SerialDocument;
use FreeBooks\Forms\Render\{Formatter, SerialRenderer};

/** A message the author can read, in the preview pane. */
function forms_receipt_preview_message($text, $kind = 'error') {
  die('<p class="' . ($kind === 'error' ? 'text-danger' : 'text-muted')
    . ' small mb-0">' . htmlspecialchars($text) . '</p>');
}

$forms_preview_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

try {
  $definition = (new DefinitionLoader($catalog))->load($forms_preview_id);
} catch (\Throwable $e) {
  forms_receipt_preview_message($e->getMessage());
}

if (!$definition->isSerial()) forms_receipt_preview_message(FORMS_RECEIPT_NOT_A_RECEIPT);

$binding = new SerialForm();
try {
  // The same allowed set the SAVE will use, from the same function. A preview
  // that accepted a binding the save refuses would show the author a receipt
  // they cannot keep - and they would find out at the save, having built the
  // rest of the layout around it.
  $edited = $binding->apply($definition, $_POST,
      forms_binding_names($definition, new FormRunner($db, $catalog), $catalog, $db));
} catch (\Throwable $e) {
  // A receipt edited down to nothing is the ordinary way to reach this, and it
  // is a state the author is passing THROUGH rather than one they have made.
  forms_receipt_preview_message($e->getMessage(), 'caution');
}

// The sample is whatever the page was already showing, so the preview does not
// change which sale it is of while the author is comparing two versions of it.
$sample = isset($_REQUEST['sample']) ? (int)$_REQUEST['sample'] : 0;
if ($sample <= 0) {
  $row = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . "
      where journal_id = 19 order by id desc limit 1");
  $sample = $row->EOF ? 0 : (int)$row->fields['id'];
}
if ($sample <= 0) forms_receipt_preview_message(FORMS_RECEIPT_NO_SAMPLE, 'caution');

try {
  $document = (new FormRunner($db, $catalog))->runDefinition($edited, array($sample));
} catch (\Throwable $e) {
  forms_receipt_preview_message($e->getMessage());
}
if (!$document instanceof SerialDocument) forms_receipt_preview_message(FORMS_RECEIPT_NOT_A_RECEIPT);

$text = (new SerialRenderer(new Formatter(
    currencies:      $currencies,
    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
    lookups:         forms_formatter_lookups(),
)))->renderToString($document);

// The ruler is what makes a width mean something: a cell of 15 is only legible
// against the column it lands in. Drawn to the paper width the author has
// typed, not the stored one, so widening the paper widens the rule with it.
$paper = max(1, min(200, (int)$edited->serialTemplate->width));
$ruler = '';
for ($i = 1; $i <= $paper; $i++) {
  $ruler .= ($i % 10 === 0) ? (string)(intdiv($i, 10) % 10) : (($i % 5 === 0) ? '+' : '.');
}

foreach ($binding->warnings() as $warning) {
  echo '<p class="text-warning-emphasis small mb-1">' . htmlspecialchars($warning) . '</p>';
}
?>
<p class="text-muted small"><?php echo sprintf(FORMS_RECEIPT_PREVIEW_OF, $sample); ?></p>
<div style="overflow-x:auto">
  <pre class="small mb-0" style="line-height:1.25"><code><?php
    echo htmlspecialchars($ruler) . "\n";
    echo htmlspecialchars(rtrim($text, "\n"));
  ?></code></pre>
</div>
