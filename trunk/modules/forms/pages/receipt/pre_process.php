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
//  Path: /modules/forms/pages/receipt/pre_process.php
//
/**
 * Forms — the receipt editor. See §13.27.
 *
 * The canvas edits a page and this edits a receipt, and they are different
 * pages because the two documents are laid out by different things. A receipt
 * has NO coordinates: its fields are placed by sequence, by a width in
 * characters, by an alignment, and by whether the field ends the line. So this
 * shows a list of lines, and each line the cells written into it — which is
 * also how someone reads a printed receipt.
 *
 * The preview is the point of the page. A receipt's one genuinely surprising
 * behaviour is that successive fields OVERLAY the same line rather than sitting
 * beside each other, so what a change does is very hard to predict and very
 * easy to see. It renders through the real SerialRenderer against a real sale,
 * so it is the receipt, not an impression of one.
 */

// The same key the canvas asks for: this edits a document's layout, and which
// KIND of layout it is does not change who should be allowed to.
$security_level = validate_user(SECURITY_ID_FORMS_AUTHOR);

require(DIR_FS_WORKING . 'defaults.php');
// FORMS_DIR_DEFINITIONS is what the loader resolves `pf_<id>` against, and the
// require above sets it. Without it every load fails on a relative path, and a
// page that finds no definitions says the company has none. Nothing from
// PhreeForm: a receipt has no faces to choose and no paper to sit on.
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
require(DIR_FS_MODULES . 'phreedom/defaults.php');

use FreeBooks\Forms\App\{FormRunner, DocumentStore, DocumentStoreException};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionLoader, SerialForm};
use FreeBooks\Forms\Document\{Align, SerialDocument, StaticText, Table};
use FreeBooks\Forms\Render\{Formatter, SerialRenderer};

$forms_receipt_id = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$loader = new DefinitionLoader($catalog);
$forms_store = new DocumentStore($db, $catalog, $loader, TABLE_PHREEFORM);
$runner = new FormRunner($db, $catalog);

$forms_receipt_definition = null;
$forms_receipt_rows       = array();
$forms_receipt_preview    = '';
$forms_receipt_sample     = 0;

// This page has no chooser and no menu entry: there is ONE list of forms, the
// canvas chooser, and it links a receipt straight here (§13.28). Arriving with
// nothing named therefore means a bookmark or a hand-typed URL, and the right
// answer is that list rather than a second one built here.
if ($forms_receipt_id <= 0) {
  gen_redirect(html_href_link(FILENAME_DEFAULT, 'module=forms&page=canvas', 'SSL'));
}

try {
  $forms_receipt_definition = $loader->load($forms_receipt_id);
} catch (DefinitionException $e) {
  $messageStack->add($e->getMessage(), 'error');
}

// The mirror of the canvas's own redirect: a page form goes to the canvas, and
// a report to the report editor. Between them the two pages mean a link to
// either editor lands on the right one whatever it names, which is what lets
// the single list link straight to an editor without being certain.
if ($forms_receipt_definition !== null && !$forms_receipt_definition->isSerial()) {
  gen_redirect(html_href_link(FILENAME_DEFAULT,
      ($forms_receipt_definition->isForm() ? 'module=forms&page=canvas&rID=' : 'module=forms&page=design&rID=')
      . $forms_receipt_id, 'SSL'));
}

if ($forms_receipt_definition !== null && $forms_receipt_definition->serialTemplate === null) {
  $messageStack->add(FORMS_RECEIPT_NO_LAYOUT, 'error');
  $forms_receipt_definition = null;
}

// What this receipt can supply, computed before the save so the save can refuse
// a name that is not in it. Shared with ajax/receipt_preview.php through one
// function rather than two copies: a preview that allowed more than the save
// would show the author a receipt they cannot keep.
//
// After the redirects on purpose. It can run the definition against real
// records to find out what a provider offers, which is work worth nothing for a
// document that is on its way to another editor.
$forms_receipt_bindings = $forms_receipt_definition === null
    ? array()
    : forms_binding_names($forms_receipt_definition, $runner, $catalog, $db);

// The formats offered, including any this receipt already uses that the closed
// vocabulary does not list - dropping one of those from the select would turn
// an author's next save into a silent reformatting of a field they never
// touched.
$forms_receipt_formats = forms_format_choices(forms_formats_in_use($forms_receipt_definition));

/**
 * Keep the PhreeForm original beside the native file, once.
 *
 * The same guarantee the canvas and the report editor make, and it matters
 * most here: PhreeForm is the only other engine that can print a receipt, and
 * once this page has saved one it can no longer read it.
 */
function forms_receipt_keep_original($path) {
  $kept = $path . '.pre-forms';
  if (file_exists($kept) || !file_exists($path)) return;
  // Only a PhreeForm original. A definition that is ALREADY native has no
  // original to keep, and copying it here would put a native file under a name
  // that says it is XML — which is what `test` holds fourteen of, written by
  // this function before the guard. Nothing reads them (LegacyReader::original()
  // returns null for one), but a file whose name lies about its contents is a
  // trap laid for the next tool.
  $contents = (string)@file_get_contents($path);
  if (\FreeBooks\Forms\Definition\DefinitionLoader::isNative($contents)) return;
  @copy($path, $kept);
}

/***************   Act on the action request   *************************/
// A save writes the definition, and only a POST carries the CSRF token.
if ($forms_receipt_definition !== null && ($_REQUEST['action'] ?? '') === 'save'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($security_level < 3) {
    $messageStack->add(FORMS_DESIGN_DENIED, 'error');
  } else {
    $binding = new SerialForm();
    try {
      $edited = $binding->apply($forms_receipt_definition, $_POST, $forms_receipt_bindings);
      foreach ($binding->warnings() as $warning) {
        $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
      }

      forms_receipt_keep_original($loader->path($forms_receipt_id));
      $loader->save($edited, $forms_receipt_id);

      // Through the store, which refuses a name another document already
      // has. Several things resolve a document BY TITLE, so two of one
      // name makes which they find a matter of collation (§13.29).
      $forms_store->updateTitle((int)$forms_receipt_id, $edited->title);

      $messageStack->add(sprintf(FORMS_DESIGN_SAVED, $edited->title), 'success');
      gen_redirect(html_href_link(FILENAME_DEFAULT,
          'module=forms&page=receipt&rID=' . $forms_receipt_id, 'SSL'));
    } catch (\Throwable $e) {
      // Redisplayed with what was typed rather than with what is stored, so a
      // rejected save does not throw away the author's other changes.
      $messageStack->add($e->getMessage(), 'error');
    }
  }
}

/***************   What the page shows   *******************************/
if ($forms_receipt_definition !== null) {
  $template = $forms_receipt_definition->serialTemplate;
  $values   = SerialForm::toRequest($template);

  // The lines, each with the cells written into it, and each cell paired with
  // the form values that edit it. Built here rather than in the template so the
  // page has nothing to work out while it prints.
  $index = 0;
  foreach ($template->lines() as $lineNumber => $line) {
    $cells = array();
    foreach ($line as $field) {
      $element = $field->element;
      $cells[] = array(
        'index'   => $index,
        'values'  => $values[$index],
        'kind'    => forms_receipt_kind($element),
        'bind'    => forms_receipt_binding($element),
        'isText'  => $element instanceof StaticText,
        'isTable' => $element instanceof Table,
        // Only a plain bound value takes a format here. A table's formats
        // belong to its columns and a block's to its parts, and neither is
        // one answer for the row - offering a single box for them would
        // silently set the wrong thing.
        'isValue' => $element instanceof \FreeBooks\Forms\Document\Field,
      );
      $index++;
    }
    $forms_receipt_rows[] = array('number' => $lineNumber + 1, 'cells' => $cells);
  }

  // A real sale, so the preview is the receipt. Without one the page still
  // works — the fields and their cells are all editable — and says why the
  // preview is missing rather than showing an empty box.
  $sale = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . "
      where journal_id = 19 order by id desc limit 1");
  if (!$sale->EOF) {
    $forms_receipt_sample = (int)$sale->fields['id'];
    try {
      $document = $runner->runDefinition($forms_receipt_definition, array($forms_receipt_sample));
      if ($document instanceof SerialDocument) {
        $forms_receipt_preview = (new SerialRenderer(new Formatter(
            currencies:      $currencies,
            defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
            lookups:         forms_formatter_lookups(),
        )))->renderToString($document);
      }
    } catch (\Throwable $e) {
      $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, $e->getMessage()), 'caution');
    }
  }
}

/** What kind of thing a field is, in the author's words rather than the format's. */
function forms_receipt_kind($element): string
{
  return match (true) {
    $element instanceof \FreeBooks\Forms\Document\StaticText => FORMS_RECEIPT_KIND_TEXT,
    $element instanceof \FreeBooks\Forms\Document\Field      => FORMS_RECEIPT_KIND_VALUE,
    $element instanceof \FreeBooks\Forms\Document\FieldBlock => FORMS_RECEIPT_KIND_BLOCK,
    $element instanceof \FreeBooks\Forms\Document\Table      => FORMS_RECEIPT_KIND_TABLE,
    default                                                  => FORMS_RECEIPT_KIND_OTHER,
  };
}

/** Which value a field prints, named so an author can recognise it. */
function forms_receipt_binding($element): string
{
  if ($element instanceof \FreeBooks\Forms\Document\Field) return $element->bind->name;
  if ($element instanceof \FreeBooks\Forms\Document\FieldBlock) {
    return implode(', ', array_map(fn($p) => $p->bind->name, $element->parts));
  }
  if ($element instanceof \FreeBooks\Forms\Document\Table) {
    return implode(', ', array_map(fn($c) => $c->bind->name, $element->columns));
  }
  return '';
}

define('PAGE_TITLE', $forms_receipt_definition !== null
    ? sprintf(FORMS_RECEIPT_TITLE_FOR, $forms_receipt_definition->title)
    : FORMS_RECEIPT_TITLE);

$use_bootstrap_nav = true;
