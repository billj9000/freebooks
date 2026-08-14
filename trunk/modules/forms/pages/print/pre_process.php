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
// Same order, and for the same reasons, as the Forms main page: PhreeForm's
// language file feeds its defaults.php, which defines PF_DIR_MY_REPORTS and
// loads each module's pf_process_* hooks behind an `isset($loaded_modules)`
// guard it can only satisfy at file scope.
gen_pull_language('phreeform');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');   // get_report_details()
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{FormPlan, FormRunner, RecordSelector};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Deliver\{EmailDelivery, HttpDelivery};
use FreeBooks\Forms\Document\{LayoutDocument, RenderOptions};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer};

// Required of every page by includes/template_index.php, which renders it as
// the browser <title>. A page that omits it is a fatal, not an untitled page.
define('PAGE_TITLE', FORMS_PRINT_TITLE);

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$forms_custom = new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS);
$forms_custom->registerAll($catalog);

$runner   = new FormRunner($db, $catalog);
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
  $forms_report = get_report_details($forms_rID);
  if (!$forms_report) $messageStack->add(sprintf(FORMS_ERROR_NOT_FOUND, $forms_rID), 'error');
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
    $forms_report = get_report_details($forms_rID);
  } elseif ($forms_choices === []) {
    $messageStack->add(sprintf(FORMS_PRINT_NO_FORMS, $forms_gID), 'error');
  }
} else {
  $messageStack->add(FORMS_PRINT_NOTHING_ASKED, 'error');
}

// ── which records ────────────────────────────────────────────────────────────
if ($forms_report) {
  $forms_plan = $runner->plan($forms_report);
  if (!$forms_plan->runnable()) {
    $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, implode(' ', $forms_plan->notes)), 'error');
  } else {
    $forms_selection = $selector->resolve(
      $forms_plan->recordTable, $forms_xfld, $forms_xcr,
      $forms_xmin, $forms_xmax, $forms_plan->recordKey
    );
    foreach ($forms_selection->notes as $note) $messageStack->add($note, 'caution');
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
         $currencies, $messageStack;

  if (!$forms_report || !$forms_plan || !$forms_plan->runnable()) return null;
  if (!$forms_selection || $forms_selection->isEmpty()) {
    $messageStack->add(FORMS_PRINT_NO_RECORDS, 'error');
    return null;
  }

  try {
    $document = $runner->run($forms_report, $forms_selection->ids,
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
    defaultFont:    defined('PDF_DEFAULT_FONT')   ? PDF_DEFAULT_FONT          : 'helvetica',
    rowSpacing:     defined('PF_DEFAULT_ROWSPACE') ? (float)PF_DEFAULT_ROWSPACE : 2.0,
    continuedLabel: defined('TEXT_CONTINUED')     ? TEXT_CONTINUED            : 'Continued',
    pageOfLabel:    defined('TEXT_OF')            ? TEXT_OF                   : 'of',
    imageDirectory: PF_DIR_MY_REPORTS . 'images',
  );

  return $renderer->render($document, new RenderOptions(
    filename: forms_print_filename($forms_report)
  ));
}

/** The download name, from the definition's own prefix where it has one. */
function forms_print_filename(object $report): string
{
  $prefix = trim((string)($report->filenameprefix ?? ''));
  $base   = $prefix !== '' ? $prefix : (string)($report->title ?? 'document');
  return $base . '.pdf';
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
             : (string)($forms_report->title ?? '') . ' ' . TEXT_FROM . ' ' . COMPANY_NAME;
    $body    = (string)($_POST['message_body'] ?? '');
    $to_name = trim((string)($_POST['to_name'] ?? '')) ?: $to_email;

    $result = (new EmailDelivery(
        // $filename is the name the RECIPIENT should see, and it is separate
        // from $path on purpose: the staged file carries a unique suffix so two
        // people emailing the same invoice cannot overwrite each other. Passing
        // only the path attaches "Invoice-fdaec59de5bc-pdf", with no extension.
        function (string $path, string $filename, $rendered) use ($to_name, $to_email, $subject, $body): bool {
          return (bool)validate_send_mail(
            $to_name, $to_email, $subject, $body,
            $_SESSION['display_name'] ?? '', $_SESSION['admin_email'] ?? '',
            array(), array('file' => $path, 'name' => $filename)
          );
        },
        DIR_FS_MY_FILES . $_SESSION['company'] . '/temp/'
    ))->deliver($output);

    if (!$result->ok) {
      $messageStack->add(sprintf(FORMS_ERROR_DELIVERY, $result->detail), 'error');
      break;
    }
    $messageStack->add(EMAIL_SEND_SUCCESS, 'success');
    break;
}

/***************   Prepare the screen   *************************/
// Who the document is for, so the email field arrives filled in. Read from the
// journal the same way popup_gen reads it, and only when the selection is on a
// journal — a statement is per contact and has no bill_email of its own.
$forms_to_email = (string)($_POST['to_email'] ?? '');
$forms_to_name  = (string)($_POST['to_name']  ?? '');
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

$forms_subject = (string)($_POST['subject'] ?? '');
if ($forms_subject === '' && $forms_report) {
  $forms_subject = (string)($forms_report->title ?? '') . ' ' . TEXT_FROM . ' ' . COMPANY_NAME;
}
$forms_body = (string)($_POST['message_body'] ?? '');
if ($forms_body === '' && $forms_report) {
  $forms_body = !empty($forms_report->emailmessage)
              ? TextReplace($forms_report->emailmessage)
              : sprintf(FORMS_PRINT_EMAIL_BODY, (string)($forms_report->title ?? ''), COMPANY_NAME);
}

// A popup: the surrounding chrome belongs to the window that opened it.
$include_header    = false;
$include_footer    = false;
$use_bootstrap_nav = true;
