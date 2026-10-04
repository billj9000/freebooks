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
//  Path: /modules/forms/ajax/canvas_pdf.php
//
/**
 * The PDF the form canvas draws on top of (design §13.7 step 3).
 *
 * The REAL document, through the renderer that prints it — not a preview
 * renderer, not an HTML approximation. That is the whole of decision H: an
 * author drags against true output, so the output has to be true.
 *
 * Bound to a real record where the company has one, and to invented values
 * where it does not (§13.2). A fresh install has no invoices and must still be
 * able to lay an invoice out, and a form rendered against an empty record draws
 * its labels and its rules and nothing else — which is precisely the case where
 * an author cannot tell whether a box sits over its value.
 *
 * Answers with a PDF, or with a readable HTML page: pdf.js reports a failed
 * fetch as an opaque parse error, so an error that arrives as JSON or as an XML
 * envelope shows up in the canvas as nothing at all.
 *
 * ## Re-rendering while editing
 *
 * POST the canvas's elements and the page is drawn from THOSE rather than from
 * what is stored — nothing is saved. Without it the underlay is the last saved
 * version, so the second change an author makes is judged against a picture
 * that no longer matches the first, and every change after that is worth less
 * than the one before.
 *
 * It applies them through TemplateForm, which is the class the SAVE uses. That
 * is the same rule the report editor's preview keeps: a picture built by
 * different code is a picture of a different form, and it would agree often
 * enough to be trusted.
 */

/**************   Check user security   *****************************/
$security_level = validate_ajax_user();

/**************  include page specific files    *********************/
gen_pull_language('forms');

// Authoring is its own permission, and validate_ajax_user() proves only a
// session. Without this, any signed-in user could render any form.
if (($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0) < 1) {
  http_response_code(403);
  die('<!doctype html><meta charset="utf-8"><body style="font:13px system-ui,sans-serif;padding:1rem">'
    . FORMS_DESIGN_DENIED . '</body>');
}

// A module's defaults.php is not loaded for you, and an ajax op has no
// DIR_FS_WORKING to require it from.
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{FormRunner, RecordSelector, SampleData};
use FreeBooks\Forms\Definition\TemplateForm;
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\DefinitionLoader;
use FreeBooks\Forms\Deliver\HttpDelivery;
use FreeBooks\Forms\Document\{Context, LayoutDocument, RenderOptions, Template};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer};

function forms_canvas_fail($text) {
  die('<!doctype html><meta charset="utf-8">'
    . '<body style="font:13px system-ui,sans-serif;color:#b02a37;padding:1rem">'
    . htmlspecialchars($text) . '</body>');
}

$forms_canvas_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

try {
  $definition = (new DefinitionLoader($catalog))->load($forms_canvas_id);
} catch (\Throwable $e) {
  forms_canvas_fail($e->getMessage());
}
if (!$definition->isForm()) forms_canvas_fail(FORMS_CANVAS_IS_REPORT);

$runner = new FormRunner($db, $catalog);

// The canvas as it stands, if it sent one. Applied BEFORE the plan is taken:
// the plan reads the definition, and the definition being drawn is the edited
// one.
$forms_canvas_warnings = array();
if (!empty($_POST['element'])) {
  $binding = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
  try {
    $definition = $binding->apply($definition, $_POST,
        forms_binding_names($definition, $runner, $catalog, $db));
    $forms_canvas_warnings = $binding->warnings();
  } catch (\Throwable $e) {
    forms_canvas_fail($e->getMessage());
  }
}

$plan = $runner->planDefinition($definition);
if (!$plan->runnable()) forms_canvas_fail(implode(' ', $plan->notes));

/**
 * A record this form could print, newest first — the one the author asked for
 * ahead of the rest (§13.53).
 *
 * The form's own filters are what make a Purchase Order a purchase order, and
 * they are already converted — so the ids come from asking the record table for
 * rows, and whether any of them actually builds a document is settled by trying.
 *
 * The schema-checking lives in RecordSelector, the module's answer to "which
 * records": it checks a column against the schema rather than quoting it, and
 * knows what a record is CALLED. The picker needs that last part and this
 * endpoint the first two, and having them in one place is what stops the canvas
 * and the print page naming the same invoice differently.
 */
$selector  = new RecordSelector($db);
$available = $selector->recent($plan->recordTable, $plan->recordKey, 20, $definition->group);

// What the author picked, first in the queue and never the only one. A record
// that no longer builds — deleted, or edited out of this form's filters since
// the page was opened — must fall back to whatever does rather than drop the
// canvas to invented data, which would read as "this form is broken now".
$forms_canvas_wanted = trim((string)($_REQUEST['rcd'] ?? ''));

// A reference typed rather than chosen — an invoice number remembered, which is
// how an author asks for a record that is not among the recent ones. Resolved
// HERE rather than through an endpoint of its own: the answer is only useful as
// a page to look at, and a separate lookup would mean a round trip whose only
// result is a second round trip. The first match wins, newest first.
$forms_canvas_reference = trim((string)($_REQUEST['rcdref'] ?? ''));
if ($forms_canvas_reference !== '') {
  $matched = $selector->findByReference($plan->recordTable, $plan->recordKey,
                                        $forms_canvas_reference, 1, $definition->group);
  $forms_canvas_wanted = $matched === array() ? $forms_canvas_wanted
                                              : (string)array_key_first($matched);
}

// strval, because PHP turns a numeric-string array key into an INTEGER. The
// record keys here are '516' and friends, so array_keys() hands back ints and a
// strict comparison against the posted string would never match - quietly,
// sending every choice down the slower path below.
$candidates = array_map('strval', array_keys($available));
if ($forms_canvas_wanted !== '' && in_array($forms_canvas_wanted, $candidates, true)) {
  $candidates = array_merge(array($forms_canvas_wanted),
      array_values(array_diff($candidates, array($forms_canvas_wanted))));
} elseif ($forms_canvas_wanted !== ''
          && $selector->label($plan->recordTable, $plan->recordKey, $forms_canvas_wanted,
                              $definition->group) !== null) {
  // Not in the recent list is not the same as not a record: an author who
  // searched out an old invoice by its number is asking for one this list was
  // never going to hold. One lookup settles whether it is a key of the record
  // table at all — and the value reaches that query escaped, never as an
  // identifier.
  array_unshift($candidates, $forms_canvas_wanted);
}

$document = null;
$forms_canvas_drawn = '';
foreach ($candidates as $candidate) {
  try {
    $built = $runner->runDefinition($definition, array($candidate),
        array('dates' => $definition->dateDefault));
  } catch (\Throwable $e) {
    continue;   // a record the form's own filters exclude is not an error
  }
  if ($built instanceof LayoutDocument && count($built->records) > 0) {
    $document = $built;
    // Which one it settled on, not which one was asked for. The two differ
    // whenever the first choice is a record this form declines, and an author
    // told the wrong one would go looking for a fault in the layout that is
    // really a fault in their choice of record.
    $forms_canvas_drawn = (string)$candidate;
    break;
  }
}

// Nothing on this company matches: render the same template against invented
// values instead. The TEMPLATE is the real one; only the data is not.
$sampled = false;
if ($document === null) {
  $sampled  = true;
  $template = $definition->template ?? new Template();
  $dataset  = $definition->datasetId !== null && $catalog->has($definition->datasetId)
      ? $catalog->get($definition->datasetId) : null;

  // The company block is resolved for real even here — those are constants, not
  // data, and a canvas showing "Sample COMPANY_NAME" would misjudge the width
  // of the one block whose width is fixed.
  $values = array();
  foreach ($template->elements as $element) {
    $names = array();
    if ($element instanceof \FreeBooks\Forms\Document\Field
        && $element->bind->scope === \FreeBooks\Forms\Document\Scope::Context) {
      $names[] = $element->bind->name;
    }
    if ($element instanceof \FreeBooks\Forms\Document\FieldBlock) {
      foreach ($element->parts as $part) {
        if ($part->bind->scope === \FreeBooks\Forms\Document\Scope::Context) $names[] = $part->bind->name;
      }
    }
    foreach ($names as $name) {
      if ($name !== '' && !isset($values[$name]) && defined($name)) {
        $values[$name] = \FreeBooks\Forms\Document\TypedValue::text((string)constant($name));
      }
    }
  }

  $document = new LayoutDocument(
    title:   $definition->title,
    template: $template,
    records: array(SampleData::record($template, $dataset)),
    context: new Context($values, $_SESSION['language'] ?? 'en_us'),
    page:    $definition->page,
  );
}

$renderer = new LayoutPdfRenderer(
  formatter:      new Formatter(
                    currencies:      $currencies,
                    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                    lookups:         forms_formatter_lookups()),
  defaultFont:    defined('FORMS_DEFAULT_FONT') ? FORMS_DEFAULT_FONT : 'freeserif',
  rowSpacing:     (float)FORMS_DEFAULT_ROWSPACE,
  continuedLabel: FORMS_TABLE_CONTINUED,
  pageOfLabel:    defined('TEXT_OF') ? TEXT_OF : 'of',
  imageDirectory: FORMS_DIR_IMAGES,
  linkedImageDirectory: DIR_FS_MY_FILES . $_SESSION['company'] . '/',
);

// Whether the page is looking at real data is the author's business, and a
// header carries it without disturbing the bytes pdf.js is about to parse.
// The warnings travel the same way: a binding the form cannot supply is refused
// here exactly as it would be on save, and an author who only finds that out
// when they save has been editing something that was never going to be kept.
header('X-Forms-Sampled: ' . ($sampled ? '1' : '0'));
if ($forms_canvas_warnings) {
  header('X-Forms-Warnings: ' . str_replace(array("\r", "\n"), ' ',
      substr(implode(' ', $forms_canvas_warnings), 0, 500)));
}

// Rendered BEFORE delivery rather than inside the call, because the answer to
// "which elements did a condition keep off this page" is only known once the
// drawing has happened, and a header cannot follow a body.
//
// The renderer is asked rather than the condition re-tested here. Re-testing
// would be four lines and would be a second implementation of the predicate,
// free to disagree with the first — and it would disagree precisely on the
// element the author is looking at and cannot see, which is the case this
// whole header exists to make visible (§13.51).
$rendered = $renderer->render($document, new RenderOptions(filename: 'canvas.pdf'));
header('X-Forms-Suppressed: ' . implode(',', $renderer->suppressed()));
// Which record the page is of. Sent even when it is the one that was asked for,
// because the page cannot tell the difference between "your choice" and "your
// choice was declined and this is the fallback" without being told.
header('X-Forms-Record: ' . rawurlencode($forms_canvas_drawn));
// And what it is CALLED, because a record reached by typing a reference is not
// in the list the page was built with, so the page has no label for it and a
// bare key is not something an author can check their answer against.
header('X-Forms-Record-Label: ' . rawurlencode($forms_canvas_drawn === '' ? ''
    : ($available[$forms_canvas_drawn]
       ?? $selector->label($plan->recordTable, $plan->recordKey, $forms_canvas_drawn,
                           $definition->group)
       ?? $forms_canvas_drawn)));

(new HttpDelivery(HttpDelivery::INLINE))->deliver($rendered);
