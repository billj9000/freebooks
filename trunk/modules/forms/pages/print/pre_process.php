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
//  Path: /modules/forms/pages/print/pre_process.php
//
/**
 * Printing a form from the document that owns it (design §11.16).
 *
 * Every Print button in FreeBooks opens PhreeForm's popup_gen with the same four
 * parameters, and this page answers on the same contract so that pointing a
 * button here is a URL change and nothing more:
 *
 *     ?module=forms&page=print&gID=j12:inv&date=a
 *         &xfld=journal_main.id&xcr=EQUAL&xmin=1234
 *
 *   gID   the form group — one form runs straight through, several are offered
 *   rID   a specific form, which is what choosing from that list submits
 *   xfld  the column the caller is selecting on, qualified or not
 *   xcr   EQUAL, IN_LIST or RANGE
 *   xmin  the value, a comma list, or the low end of a range
 *   xmax  the high end of a range
 *   date  a PhreeForm date preference letter, for the statement forms
 *
 * The translation from those to the ids a provider takes is RecordSelector's,
 * and choosing the provider is FormRunner's; this page is the glue and the
 * screen, which is all it should be.
 */

$security_level = validate_user(SECURITY_ID_FORMS_RUN);

require(DIR_FS_WORKING . 'defaults.php');
// Definitions and images are found through FORMS_DIR_DEFINITIONS, which the
// require above sets. Same order as the Forms main page for the rest of it:
// PhreeForm's language file feeds its defaults.php, which supplies the font
// table and loads each module's pf_process_* hooks behind an
// `isset($loaded_modules)` guard it can only satisfy at file scope.
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{FormPlan, FormRunner, RecordSelector};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionLoader};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Deliver\{EmailDelivery, HttpDelivery};
use FreeBooks\Forms\Document\{LayoutDocument, RenderOptions, SerialDocument};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer, SerialRenderer};

// Required of every page by includes/template_index.php, which renders it as
// the browser <title>. A page that omits it is a fatal, not an untitled page.
define('PAGE_TITLE', FORMS_PRINT_TITLE);

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$forms_custom = new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS);
$forms_custom->registerAll($catalog);

$runner   = new FormRunner($db, $catalog);
// Through the format-aware loader, never get_report_details(): a definition the
// report editor has saved is native, and the legacy XML reader returns a string
// for it — which reaches plan() as "must be of type object, string given".
$loader   = new DefinitionLoader($catalog);

/** The definition behind an id, or false with the reason on the stack. */
function forms_print_load($id) {
  global $loader, $messageStack;
  try {
    return $loader->load($id);
  } catch (DefinitionException $e) {
    $messageStack->add(sprintf(FORMS_ERROR_NOT_FOUND, $id), 'error');
    return false;
  }
}
$selector = new RecordSelector($db);

// ── what the caller asked for ────────────────────────────────────────────────
$forms_gID  = isset($_REQUEST['gID'])  ? (string)$_REQUEST['gID']  : '';
$forms_rID  = isset($_REQUEST['rID'])  ? (int)$_REQUEST['rID']     : 0;
$forms_xfld = isset($_REQUEST['xfld']) ? (string)$_REQUEST['xfld'] : '';
$forms_xcr  = isset($_REQUEST['xcr'])  ? (string)$_REQUEST['xcr']  : 'EQUAL';
$forms_xmin = isset($_REQUEST['xmin']) ? (string)$_REQUEST['xmin'] : '';
$forms_xmax = isset($_REQUEST['xmax']) ? (string)$_REQUEST['xmax'] : '';
$forms_date = isset($_REQUEST['date']) ? (string)$_REQUEST['date'] : '';

$forms_choices  = array();   // several forms in the group: the user picks
$forms_report   = false;
$forms_plan     = null;
$forms_selection = null;

// ── which form ───────────────────────────────────────────────────────────────
if ($forms_rID) {
  $forms_report = forms_print_load($forms_rID);
} elseif ($forms_gID !== '') {
  // Forms only. A group can hold reports too, and a Print button on an order
  // means the form, not the listing.
  $result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM . "
    where doc_group = '" . db_input($forms_gID) . "' and doc_ext = 'frm' order by doc_title");
  while (!$result->EOF) {
    $forms_choices[] = array('id' => (int)$result->fields['id'], 'text' => (string)$result->fields['doc_title']);
    $result->MoveNext();
  }
  if (count($forms_choices) === 1) {
    $forms_rID    = $forms_choices[0]['id'];
    $forms_report = forms_print_load($forms_rID);
  } elseif ($forms_choices === []) {
    $messageStack->add(sprintf(FORMS_PRINT_NO_FORMS, $forms_gID), 'error');
  }
} else {
  $messageStack->add(FORMS_PRINT_NOTHING_ASKED, 'error');
}

// ── which records ────────────────────────────────────────────────────────────
if ($forms_report) {
  $forms_plan = $runner->planDefinition($forms_report);
  if (!$forms_plan->runnable()) {
    $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, implode(' ', $forms_plan->notes)), 'error');
  } else {
    if ($forms_xmin === '' && $forms_xmax === '') {
      // Reached by opening the form from the document list, which knows which
      // form but not which record — a form is per record and only the document
      // that owns it can say which. Saying so beats "nothing was selected",
      // which is true and unhelpful.
      $messageStack->add(FORMS_PRINT_OPEN_FROM_DOCUMENT, 'caution');
      $forms_selection = null;
    } else {
      $forms_selection = $selector->resolve(
        $forms_plan->recordTable, $forms_xfld, $forms_xcr,
        $forms_xmin, $forms_xmax, $forms_plan->recordKey
      );
      foreach ($forms_selection->notes as $note) $messageStack->add($note, 'caution');
    }
  }
}

/**
 * Build and render, or add the reason it could not be done to the stack.
 *
 * Returns null on failure, so every caller below has exactly one thing to test.
 */
function forms_print_render(): ?object
{
  global $runner, $forms_report, $forms_plan, $forms_selection, $forms_date,
         $currencies, $messageStack, $forms_document;

  $forms_document = null;
  if (!$forms_report || !$forms_plan || !$forms_plan->runnable()) return null;
  if (!$forms_selection || $forms_selection->isEmpty()) {
    $messageStack->add(FORMS_PRINT_NO_RECORDS, 'error');
    return null;
  }

  try {
    $document = $forms_document = $runner->runDefinition($forms_report, $forms_selection->ids,
      array('dates' => $forms_date));
  } catch (\Throwable $e) {
    $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, $e->getMessage()), 'error');
    return null;
  }

  // An element the template could not bind is a form printing one field fewer
  // than its author drew. Saying so is the whole difference from PhreeForm.
  foreach ($runner->warnings() as $warning) {
    $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
  }

  // A receipt: text for a till printer, not a page (§13.27). Rendered here so
  // that the Print button on a point-of-sale entry works exactly as every other
  // Print button does — gen_print_form_url() switches them on a company
  // setting, not per document, so pos:rcpt arrives here whether or not anyone
  // meant it to.
  if ($document instanceof SerialDocument) {
    if (count($document->records) === 0) {
      $messageStack->add(FORMS_PRINT_NO_RECORDS, 'error');
      return null;
    }
    return (new SerialRenderer(new Formatter(
        currencies:      $currencies,
        defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
        lookups:         forms_formatter_lookups(),
    )))->render($document, new RenderOptions(
        filename: forms_print_filename($forms_report, '')
    ));
  }

  if (!$document instanceof LayoutDocument || count($document->records) === 0) {
    $messageStack->add(FORMS_PRINT_NO_RECORDS, 'error');
    return null;
  }

  $renderer = new LayoutPdfRenderer(
    formatter:      new Formatter(
                        currencies:      $currencies,
                        defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                        lookups:         forms_formatter_lookups(),
                    ),
    defaultFont:    defined('FORMS_DEFAULT_FONT')   ? FORMS_DEFAULT_FONT          : 'freeserif',
    rowSpacing:     (float)FORMS_DEFAULT_ROWSPACE,
    continuedLabel: FORMS_TABLE_CONTINUED,
    pageOfLabel:    defined('TEXT_OF')            ? TEXT_OF                   : 'of',
    imageDirectory: FORMS_DIR_IMAGES,
  linkedImageDirectory: DIR_FS_MY_FILES . $_SESSION['company'] . '/',
  );

  return $renderer->render($document, new RenderOptions(
    filename: forms_print_filename($forms_report)
  ));
}

/**
 * The download name, from the definition's own prefix where it has one.
 *
 * The extension is the CALLER's, because it is a property of what was rendered
 * rather than of the definition: the same receipt is '.txt', and SerialRenderer
 * appends that itself.
 */
function forms_print_filename(\FreeBooks\Forms\Definition\Definition $definition, string $extension = '.pdf'): string
{
  $prefix = trim($definition->filenamePrefix);
  $base   = $prefix !== '' ? $prefix : ($definition->title !== '' ? $definition->title : 'document');
  return $base . $extension;
}

/**
 * Perform what the document said should happen once it had been delivered.
 *
 * Called after a successful view, download or send, and only then — the
 * Invoice's printed counter has to mean "this went to somebody", or the Sales
 * Invoice Manager's unprinted filter is measuring page loads.
 *
 * A failure here is a caution rather than an error: the document HAS been
 * delivered by the time this runs, and telling somebody their invoice failed
 * when they are holding it is worse than telling them the counter did not move.
 */
function forms_print_delivered(): void
{
  global $db, $forms_document, $messageStack;

  if (!$forms_document instanceof \FreeBooks\Forms\Document\Document) return;

  $hooks = new \FreeBooks\Forms\App\AfterRenderRunner($db);
  $hooks->run($forms_document);
  foreach ($hooks->failures() as $failure) {
    $messageStack->add(sprintf(FORMS_WARN_NOT_COUNTED, $failure), 'caution');
  }
}

/***************   Act on the action request   *************************/
switch ($_REQUEST['action'] ?? '') {
  case 'view':
  case 'download':
    $output = forms_print_render();
    if ($output === null) break;
    $result = (new HttpDelivery(($_REQUEST['action'] === 'view')
        ? HttpDelivery::INLINE : HttpDelivery::ATTACHMENT))->deliver($output);
    if (!$result->ok) {
      $messageStack->add(sprintf(FORMS_ERROR_DELIVERY, $result->detail), 'error');
      break;
    }
    forms_print_delivered();
    exit;

  case 'email':
    $to_email = trim((string)($_POST['to_email'] ?? ''));
    if ($to_email === '') {
      $messageStack->add(FORMS_PRINT_EMAIL_NO_ADDRESS, 'error');
      break;
    }
    $output = forms_print_render();
    if ($output === null) break;

    // The delivery layer stages the bytes to a temp file and always removes it;
    // this closure is only the send, so the mailer stays out of the module.
    $subject = trim((string)($_POST['subject'] ?? '')) !== ''
             ? (string)$_POST['subject']
             : $forms_report->title . ' ' . TEXT_FROM . ' ' . COMPANY_NAME;
    $body    = (string)($_POST['message_body'] ?? '');
    $to_name = trim((string)($_POST['to_name'] ?? '')) ?: $to_email;

    // CC and BCC travel in validate_send_mail()'s $block, which is not a shape
    // anyone would guess; forms_email_block() is where that mapping lives so a
    // wrong key cannot silently drop a recipient on one page and not another.
    $block = forms_email_block($_POST);
    [$from_name, $from_email] = forms_email_sender(
        (string)($_POST['from_email'] ?? ''), (string)($_POST['from_name'] ?? ''));

    $result = (new EmailDelivery(
        // $filename is the name the RECIPIENT should see, and it is separate
        // from $path on purpose: the staged file carries a unique suffix so two
        // people emailing the same invoice cannot overwrite each other. Passing
        // only the path attaches "Invoice-fdaec59de5bc-pdf", with no extension.
        function (string $path, string $filename, $rendered)
            use ($to_name, $to_email, $subject, $body, $from_name, $from_email, $block): bool {
          return (bool)validate_send_mail(
            $to_name, $to_email, $subject, $body,
            $from_name, $from_email,
            $block, array('file' => $path, 'name' => $filename)
          );
        },
        DIR_FS_MY_FILES . $_SESSION['company'] . '/temp/'
    ))->deliver($output);

    if (!$result->ok) {
      $messageStack->add(sprintf(FORMS_ERROR_DELIVERY, $result->detail), 'error');
      break;
    }
    forms_print_delivered();
    $messageStack->add(EMAIL_SEND_SUCCESS, 'success');
    break;
}

/***************   Prepare the screen   *************************/
// Who the document is for, so the email field arrives filled in. Read from the
// journal the same way popup_gen reads it, and only when the selection is on a
// journal — a statement is per contact and has no bill_email of its own.
//
// rEmail/rName are the caller's own answer, used where the journal has none:
// the work order button sends them because wo_journal_main is not journal_main
// and the lookup below cannot reach it. Hints, not authority — anything the
// user typed wins, and so does the journal.
$forms_to_email = (string)($_POST['to_email'] ?? '');
$forms_to_name  = (string)($_POST['to_name']  ?? '');
// Redisplayed after a refused send, so a mistyped address does not cost the
// author the other five fields.
$forms_cc_email   = (string)($_POST['cc_email']   ?? '');
$forms_bcc_email  = (string)($_POST['bcc_email']  ?? '');
[$forms_from_name, $forms_from_email] = forms_email_sender(
    (string)($_POST['from_email'] ?? ''), (string)($_POST['from_name'] ?? ''));
if ($forms_to_email === '' && $forms_selection && !$forms_selection->isEmpty()
    && $forms_plan && $forms_plan->recordTable === 'journal_main'
    && $forms_plan->recordKey === 'id') {
  $first  = (int)$forms_selection->ids[0];
  $result = $db->Execute("select bill_primary_name, bill_email from " . TABLE_JOURNAL_MAIN
      . " where id = " . $first);
  if ($result && !$result->EOF) {
    $forms_to_email = (string)$result->fields['bill_email'];
    $forms_to_name  = (string)$result->fields['bill_primary_name'];
  }
}
if ($forms_to_email === '') {   // still nothing: take what the caller offered
  $forms_to_email = (string)($_REQUEST['rEmail'] ?? '');
  $forms_to_name  = (string)($_REQUEST['rName']  ?? '');
}

$forms_subject = (string)($_POST['subject'] ?? '');
if ($forms_subject === '' && $forms_report) {
  $forms_subject = $forms_report->title . ' ' . TEXT_FROM . ' ' . COMPANY_NAME;
}
$forms_body = (string)($_POST['message_body'] ?? '');
if ($forms_body === '' && $forms_report) {
  $forms_body = $forms_report->emailMessage !== ''
              ? forms_fill_tokens($forms_report->emailMessage, $forms_report->title)
              : sprintf(FORMS_PRINT_EMAIL_BODY, $forms_report->title, COMPANY_NAME);
}

// A popup: the surrounding chrome belongs to the window that opened it.
$include_header    = false;
$include_footer    = false;
$use_bootstrap_nav = true;
