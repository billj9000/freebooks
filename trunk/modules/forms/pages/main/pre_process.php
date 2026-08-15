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
// Report definitions are still read from the PhreeForm catalog, so its own
// bootstrap has to happen first and IN THIS ORDER, at file scope:
//   - its language file defines the PF_FONT_* constants defaults.php reads;
//   - defaults.php defines PF_DIR_MY_REPORTS, which get_report_details() needs,
//     and loads each module's pf_process_* hooks behind `isset($loaded_modules)`
//     — a global it can only see from file scope, so this must not move inside
//     a function.
gen_pull_language('phreeform');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');   // get_report_details()
require_once(DIR_FS_WORKING . 'functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, RunPlan};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Deliver\HttpDelivery;
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

$docID   = isset($_GET['docID']) ? (int)$_GET['docID'] : 0;
$format  = isset($_GET['format']) ? (string)$_GET['format'] : 'pdf';
$inline  = !empty($_GET['inline']);

/***************   Act on the action request   *************************/
switch ($_REQUEST['action'] ?? '') {
  case 'run':
    $report = $docID ? get_report_details($docID) : false;
    if (!$report) {
      $messageStack->add(sprintf(FORMS_ERROR_NOT_FOUND, $docID), 'error');
      break;
    }

    $plan = $runner->plan($report);
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
      $document = $runner->run($report, array(
        'dates'      => isset($_GET['dates'])  ? (string)$_GET['dates'] : '',
        'period'     => isset($_GET['period']) ? (int)$_GET['period']   : 0,
        'gl_account' => isset($_GET['gl_account']) ? (string)$_GET['gl_account'] : '',
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
                      groupTotalLabel:    defined('TEXT_GROUP_TOTAL')  ? TEXT_GROUP_TOTAL  : 'Group Total For:',
                      reportTotalLabel:   defined('TEXT_REPORT_TOTAL') ? TEXT_REPORT_TOTAL : 'Report Total For:',
                      defaultColumnWidth: (float)FORMS_DEFAULT_COLUMN_WIDTH,
                  ); break;
      default:
        $messageStack->add(sprintf(FORMS_ERROR_NO_FORMAT, $format), 'error');
        break 2;
    }

    $output = $renderer->render($document);
    // Delivery is its own call, and its own layer: the same bytes go to a
    // download, an email attachment or a file without the renderer knowing.
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
$forms_documents = array();
$result = $db->Execute("select id, doc_title, doc_group, doc_type from " . TABLE_PHREEFORM . "
  where doc_type <> '0' order by doc_group, doc_title");
while (!$result->EOF) {
  $definition = get_report_details($result->fields['id']);
  if ($definition) {
    $plan = $runner->plan($definition);
    $forms_documents[] = array(
      'id'       => (int)$result->fields['id'],
      'title'    => (string)$result->fields['doc_title'],
      'group'    => (string)$result->fields['doc_group'],
      'plan'     => $plan,
      'served'   => match (true) {
                      $plan->kind === RunPlan::DATASET  => sprintf(FORMS_SERVED_DATASET, $plan->name),
                      $plan->kind === RunPlan::PROVIDER => sprintf(FORMS_SERVED_PROVIDER, $plan->name),
                      // A form is served, just not from this list — it needs
                      // records, which only the document that owns it knows.
                      $plan->isForm()                   => FORMS_SERVED_FORM,
                      default                           => FORMS_SERVED_NOT_YET,
                    },
    );
  }
  $result->MoveNext();
}

// The same preference letters a definition stores, so a report run here and one
// run through PhreeForm resolve to the same range. Required at top level: this
// file sets $DateChoices as a plain variable, and requiring it inside a function
// would silently produce an empty list rather than an error.
require(DIR_FS_MODULES . 'phreedom/defaults.php');
$forms_date_choices = array();
foreach ($DateChoices as $key => $text) {
  // 'b' is an explicit from/to range, which needs two date fields this page does
  // not offer yet; every other choice is self-contained.
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
$forms_current_period = gen_calculate_period(date('Y-m-d'), false);

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
