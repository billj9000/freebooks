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
//  Path: /modules/forms/pages/main/pre_process.php
//
/**
 * The Forms document list, and the run path.
 *
 * This is the first place the five layers are driven by the application rather
 * than by docs/tools: catalog and definition in, Document out, renderer to
 * bytes, delivery to the browser. Each step is a separate call, which is what
 * makes "the same report as a PDF and as a CSV" one code path instead of two.
 */

$security_level = validate_user(SECURITY_ID_FORMS_RUN);

require(DIR_FS_WORKING . 'defaults.php');
// Definitions are found through FORMS_DIR_DEFINITIONS, which the require above
// sets. PhreeForm's own bootstrap still has to happen, IN THIS ORDER and at
// file scope: its language file defines the PF_FONT_* constants defaults.php
// reads, and defaults.php loads each module's pf_process_* hooks behind
// `isset($loaded_modules)` — a global it can only see from file scope, so this
// must not move inside a function.
gen_pull_language('phreeform');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');   // get_report_details()
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, RunPlan, DocumentStore, DocumentStoreException};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionException, DefinitionLoader};
use FreeBooks\Forms\Deliver\{EmailDelivery, HttpDelivery};
use FreeBooks\Forms\Render\{CsvRenderer, Formatter, TablePdfRenderer};

// Shipped first, then the admin-defined ones: a stored dataset may not shadow a
// shipped name, and registering in this order is what makes that so.
// Required of every page by includes/template_index.php, which renders it as
// the browser <title>. A page that omits it is a fatal, not an untitled page.
define('PAGE_TITLE', FORMS_PAGE_TITLE);

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$forms_custom = new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS);
$forms_custom->registerAll($catalog);
foreach ($forms_custom->problems() as $problem) $messageStack->add($problem, 'caution');

$runner  = new DocumentRunner($db, $catalog);
// Definitions are read through the format-aware loader, never through
// get_report_details(): once the report editor has saved one, it is a native
// file and the legacy XML reader hands back a string. plan() then dies on
// "must be of type object, string given", which is a whole page lost to one
// edited report.
$loader  = new DefinitionLoader($catalog);
$forms_store = new DocumentStore($db, $catalog, $loader, TABLE_PHREEFORM);

// $_REQUEST, not $_GET: a download is a navigation and arrives as a query
// string, while emailing posts the message with it.
$docID   = isset($_REQUEST['docID'])  ? (int)$_REQUEST['docID'] : 0;
$format  = isset($_REQUEST['format']) ? (string)$_REQUEST['format'] : 'pdf';
$inline  = !empty($_REQUEST['inline']);

// Whether the send got as far as the mailer. Every other way out of the email
// branch is a refusal, and a refusal has to reopen the dialog on the document it
// was for — while a SUCCESS must not, or the page comes back with the message
// still on screen as though nothing had been sent.
$forms_email_sent = false;

/***************   Act on the action request   *************************/
// New, Copy, Rename and Delete, before the list is built — otherwise the list
// is the one from before the action (§13.29). A new or copied report opens in
// the editor: an empty report on a list of documents tells you nothing.
// The AUTHOR level, not this page's own: the page is gated on being allowed to
// RUN a document, and writing one is a different key (decision F).
$forms_made_id = forms_document_action($forms_store,
    \FreeBooks\Forms\Definition\Definition::REPORT,
    (int)($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0));
if ($forms_made_id > 0) {
  gen_redirect(html_href_link(FILENAME_DEFAULT,
      'module=forms&page=design&rID=' . $forms_made_id, 'SSL'));
}

switch ($_REQUEST['action'] ?? '') {
  case 'email':
  case 'run':
    $emailing = ($_REQUEST['action'] ?? '') === 'email';
    // Checked BEFORE the report is built: running a large ledger and then
    // saying "you forgot the address" spends a minute to report a typo.
    if ($emailing && trim((string)($_POST['to_email'] ?? '')) === '') {
      $messageStack->add(FORMS_PRINT_EMAIL_NO_ADDRESS, 'error');
      break;
    }
    try {
      $report = $loader->load($docID);
    } catch (DefinitionException $e) {
      $messageStack->add(sprintf(FORMS_ERROR_NOT_FOUND, $docID), 'error');
      break;
    }

    $plan = $runner->planDefinition($report);
    if (!$plan->runnable()) {
      $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, implode(' ', $plan->notes)), 'error');
      break;
    }
    // Asked for rather than defaulted: reconciling whichever account happens to
    // sort first is a plausible-looking wrong answer, and the legacy class's
    // habit of taking it from filterlist[0] is exactly what the port removed.
    if ($plan->needsGlAccount && ($_GET['gl_account'] ?? '') === '') {
      $messageStack->add(FORMS_ERROR_GL_REQUIRED, 'error');
      break;
    }

    try {
      $document = $runner->runDefinition($report, array(
        'dates'      => isset($_REQUEST['dates'])  ? (string)$_REQUEST['dates'] : '',
        'period'     => isset($_REQUEST['period']) ? (int)$_REQUEST['period']   : 0,
        'gl_account' => isset($_REQUEST['gl_account']) ? (string)$_REQUEST['gl_account'] : '',
      ));
    } catch (\Throwable $e) {
      $messageStack->add(sprintf(FORMS_ERROR_CANNOT_RUN, $e->getMessage()), 'error');
      break;
    }

    // A column the dataset could not serve is a report that prints one column
    // fewer than its author drew. Silence there would be the PhreeForm habit.
    foreach ($runner->warnings() as $warning) {
      $messageStack->add(sprintf(FORMS_WARN_CONVERSION, $warning), 'caution');
    }

    $formatter = new Formatter(
      currencies:      $currencies,
      defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
      lookups:         forms_formatter_lookups(),
    );

    switch ($format) {
      case 'csv': $renderer = new CsvRenderer(); break;
      case 'pdf': $renderer = new TablePdfRenderer(
                      formatter:          $formatter,
                      groupTotalLabel:    defined('TEXT_GROUP_TOTAL_FOR')  ? TEXT_GROUP_TOTAL_FOR  : 'Group Total For:',
                      reportTotalLabel:   defined('TEXT_REPORT_TOTAL_FOR') ? TEXT_REPORT_TOTAL_FOR : 'Report Total For:',
                      defaultColumnWidth: (float)FORMS_DEFAULT_COLUMN_WIDTH,
                  ); break;
      default:
        $messageStack->add(sprintf(FORMS_ERROR_NO_FORMAT, $format), 'error');
        break 2;
    }

    $output = $renderer->render($document);

    // Delivery is its own call, and its own layer: the SAME bytes go to a
    // download, an email attachment or a file without the renderer knowing.
    // Emailing a report is that sentence being true rather than aspirational —
    // no second render path, and CSV attaches exactly as PDF does.
    if ($emailing) {
      $to_email = trim((string)$_POST['to_email']);
      $to_name  = trim((string)($_POST['to_name'] ?? '')) ?: $to_email;
      $subject  = trim((string)($_POST['subject'] ?? '')) !== ''
                ? (string)$_POST['subject']
                : $report->title . ' ' . TEXT_FROM . ' ' . COMPANY_NAME;
      $body     = trim((string)($_POST['message_body'] ?? '')) !== ''
                ? (string)$_POST['message_body']
                : ($report->emailMessage !== '' ? TextReplace($report->emailMessage)
                   : sprintf(FORMS_PRINT_EMAIL_BODY, $report->title, COMPANY_NAME));

      $block = forms_email_block($_POST);
      [$from_name, $from_email] = forms_email_sender(
          (string)($_POST['from_email'] ?? ''), (string)($_POST['from_name'] ?? ''));

      $result = (new EmailDelivery(
          function (string $path, string $filename, $rendered)
              use ($to_name, $to_email, $subject, $body, $from_name, $from_email, $block): bool {
            return (bool)validate_send_mail(
              $to_name, $to_email, $subject, $body, $from_name, $from_email,
              $block, array('file' => $path, 'name' => $filename)
            );
          },
          DIR_FS_MY_FILES . $_SESSION['company'] . '/temp/'
      ))->deliver($output);

      if (!$result->ok) {
        $messageStack->add(sprintf(FORMS_ERROR_DELIVERY, $result->detail), 'error');
        break;
      }
      // No exit(): nothing was sent to the browser, so the list is redrawn with
      // the outcome on it. A download exits because the response IS the file.
      $messageStack->add(EMAIL_SEND_SUCCESS, 'success');
      $forms_email_sent = true;
      break;
    }

    $result = (new HttpDelivery($inline ? HttpDelivery::INLINE : HttpDelivery::ATTACHMENT))
        ->deliver($output);
    if (!$result->ok) {
      $messageStack->add(sprintf(FORMS_ERROR_DELIVERY, $result->detail), 'error');
      break;
    }
    exit;
}

/***************   Prepare the list   *************************/
// The catalog is PhreeForm's, deliberately: enabling Forms must not require a
// migration, and a definition has to be runnable by both engines while the two
// coexist.
// REPORTS only. A form has no action on this page that works: it cannot be run
// from here — it needs a record, which only the document that owns it knows, so
// the Print button always landed on an explanation — and laying one out is what
// Tools -> Form Layout is for. Listing forms here offered two buttons, one that
// could not work and one that duplicated another page.
// Both needed while the list is built: each row carries the answers its own
// definition stores, so the run dialog can open already set to them. Required at
// top level — phreedom/defaults.php sets $DateChoices as a plain variable, and
// requiring it inside a function would silently produce an empty list rather
// than an error.
require(DIR_FS_MODULES . 'phreedom/defaults.php');
$forms_current_period = gen_calculate_period(date('Y-m-d'), false);

$forms_folders   = forms_folder_titles($db);
$forms_groups    = $forms_store->groups(false);
$forms_datasets  = array();
foreach ($catalog->all() as $forms_ds) $forms_datasets[$forms_ds->id] = $forms_ds->label;
asort($forms_datasets);
$forms_documents = array();
$result = $db->Execute("select id, doc_title, doc_group, doc_type from " . TABLE_PHREEFORM . "
  where doc_type <> '0' and doc_ext = 'rpt' order by doc_group, doc_title");
while (!$result->EOF) {
  // A definition Forms cannot serve is still LISTED, marked with the reason —
  // it is what the user is looking for when a document they expect is missing.
  // Dropping the row instead would answer "we cannot run this" with silence,
  // which is the PhreeForm habit the whole module exists to break.
  try {
    $definition = $loader->load($result->fields['id']);
    $plan       = $runner->planDefinition($definition);
  } catch (DefinitionException $e) {
    $definition = null;
    $plan       = new RunPlan(RunPlan::UNSUPPORTED, '', array($e->getMessage()));
  }
  $forms_documents[] = array(
    'id'       => (int)$result->fields['id'],
    'title'    => (string)$result->fields['doc_title'],
    'group'    => (string)$result->fields['doc_group'],
    'folder'   => forms_folder_for((string)$result->fields['doc_group'], false, $forms_folders),
    'plan'     => $plan,
    // Whether deleting it would leave a Print button with nothing to open, read
    // while the row still exists so the confirmation can say so.
    'last'     => $forms_store->isLastInGroup((int)$result->fields['id']),
    'served'   => match (true) {
                    $plan->kind === RunPlan::DATASET  => sprintf(FORMS_SERVED_DATASET, $plan->name),
                    $plan->kind === RunPlan::PROVIDER => sprintf(FORMS_SERVED_PROVIDER, $plan->name),
                    // A form is served, just not from this list — it needs
                    // records, which only the document that owns it knows.
                    $plan->isForm()                   => FORMS_SERVED_FORM,
                    default                           => FORMS_SERVED_NOT_YET,
                  },
    // What the run dialog opens on. The date range is offered as "as the report
    // is set", which sends nothing and lets the runner fall back to the stored
    // preference — the page used to preselect 'All' and send it on every run,
    // so a report drawn as "this quarter" quietly ran over all time. The period
    // has no such fallback for the structural providers, which cannot do
    // anything with period 0, so the stored one is named outright.
    // The stored preference is a CODE, and may carry operands — 'b:from:to' is
    // an explicit range — so it is the letter that names the choice.
    'dates_as_set' => $definition
                    ? ($DateChoices[explode(':', (string)$definition->dateDefault)[0]] ?? '') : '',
    'period'       => $definition && $definition->period > 0
                    ? $definition->period : $forms_current_period,
  );
  $result->MoveNext();
}

// Sorted by the name shown, not by the code sorted on. Ordering by doc_group put
// Employees between General Ledger and Inventory, because the code is `hr` —
// which is exactly the kind of "why is this here?" the codes caused.
usort($forms_documents, function ($a, $b) {
  return strcasecmp($a['folder'], $b['folder']) ?: strcasecmp($a['title'], $b['title']);
});

// The same preference letters a definition stores, so a report run here and one
// run through PhreeForm resolve to the same range. The FIRST entry sends nothing
// at all, which is what makes "as the report is set" work: the runner falls back
// to the definition's own preference whenever the parameter is empty, so leaving
// the dialog alone runs the report as its author drew it. Its text is completed
// per document by the template, which knows what that preference is.
$forms_date_choices = array(array('id' => '', 'text' => FORMS_OPT_AS_SET));
foreach ($DateChoices as $key => $text) {
  // 'b' is an explicit from/to range, which needs two date fields this page does
  // not offer yet; every other choice is self-contained. A report STORED as an
  // explicit range still runs as one — that is the entry above, untouched.
  if ($key === 'b') continue;
  $forms_date_choices[] = array('id' => $key, 'text' => $text);
}

$forms_periods = array();
$result = $db->Execute("select period, start_date, end_date from " . TABLE_ACCOUNTING_PERIODS . " order by period");
while (!$result->EOF) {
  $forms_periods[] = array(
    'id'   => (int)$result->fields['period'],
    'text' => $result->fields['period'] . ' — ' . gen_locale_date($result->fields['start_date'])
            . ' to ' . gen_locale_date($result->fields['end_date']),
  );
  $result->MoveNext();
}

// Whether to offer Edit at all. Running a document and authoring one are
// different permissions (decision F), and this page is gated on the first —
// so holding it says nothing about the second.
$forms_can_author = ($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0) > 2;

// What the email part of the run dialog shows. Redisplayed after a refused send
// so a mistyped address does not cost the user everything else they typed —
// including which document they were sending, which is what reopens the dialog
// on the right row rather than leaving the message typed into nothing.
$forms_email = array(
  'docID'     => $docID,
  'reopen'    => ($_REQUEST['action'] ?? '') === 'email' && !$forms_email_sent,
  'to_email'  => (string)($_POST['to_email']  ?? ''),
  'to_name'   => (string)($_POST['to_name']   ?? ''),
  'cc_email'  => (string)($_POST['cc_email']  ?? ''),
  'bcc_email' => (string)($_POST['bcc_email'] ?? ''),
  'subject'   => (string)($_POST['subject']   ?? ''),
  'body'      => (string)($_POST['message_body'] ?? ''),
);
[$forms_email['from_name'], $forms_email['from_email']] = forms_email_sender(
    (string)($_POST['from_email'] ?? ''), (string)($_POST['from_name'] ?? ''));

$forms_gl_accounts = array();
$result = $db->Execute("select id, description from " . TABLE_CHART_OF_ACCOUNTS . "
  where account_type = 0 order by id");
while (!$result->EOF) {
  $forms_gl_accounts[] = array(
    'id'   => $result->fields['id'],
    'text' => $result->fields['id'] . ' — ' . $result->fields['description'],
  );
  $result->MoveNext();
}

$use_bootstrap_nav = true;
