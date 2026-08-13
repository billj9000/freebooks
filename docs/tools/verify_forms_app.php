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
// +-----------------------------------------------------------------+
//  Path: /docs/tools/verify_forms_app.php
//
/**
 * Drive the whole stack the way the application does (design §2, §11.14).
 *
 * Every other tool exercises one layer. This one runs the path a page runs —
 * DocumentRunner picks the route, a renderer turns the Document into bytes, and
 * a deliverer sends them — over EVERY definition in the catalog, so that a
 * definition Forms claims it can serve is one it actually can.
 *
 * The claim being tested is narrow and worth stating: not that the numbers are
 * right (verify_forms_report.php compares those against PhreeForm), but that
 * the wiring holds for all of them at once. A route that throws, a renderer
 * that refuses the document it was handed, or an empty file are all failures
 * here; a definition Forms honestly declines is not.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_app.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, RunPlan};
use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Deliver\{DeliveryResult, HttpDelivery};
use FreeBooks\Forms\Document\TableDocument;
use FreeBooks\Forms\Render\{CsvRenderer, Formatter, TablePdfRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$runner = new DocumentRunner($db, $catalog);

$formatter = new Formatter(
    currencies:      $currencies,
    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
    lookups:         forms_formatter_lookups(),
);
$pdf = new TablePdfRenderer(formatter: $formatter, defaultColumnWidth: 25.0);
$csv = new CsvRenderer();

// A period and a cash account, standing in for the run dialog. The
// reconciliation reads nothing without both.
$period = (int)($db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
    . " where start_date <= '" . date('Y-m-d') . "' and end_date >= '" . date('Y-m-d') . "'")
    ->fields['period'] ?? 1);
$glAccount = (string)($db->Execute("select i.gl_account from " . TABLE_JOURNAL_ITEM . " i
    inner join " . TABLE_CHART_OF_ACCOUNTS . " c on c.id = i.gl_account
    where c.account_type = 0 group by i.gl_account order by count(*) desc limit 1")
    ->fields['gl_account'] ?? '');
printf("run dialog: period %d, gl account '%s'\n\n", $period, $glAccount);

$counts = ['dataset' => 0, 'provider' => 0, 'unsupported' => 0];
$ran    = $empty = 0;

$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_type <> '0' order by id");
$documents = [];
while (!$result->EOF) {
    $documents[(int)$result->fields['id']] = (string)$result->fields['doc_title'];
    $result->MoveNext();
}

echo "=== every definition is routed, and every route runs ===\n";
foreach ($documents as $id => $title) {
    $definition = get_report_details($id);
    if (!$definition) continue;

    $plan = $runner->plan($definition);
    $counts[$plan->kind]++;
    if (!$plan->runnable()) continue;

    // The one thing a page must never do: hand the same definition object to
    // two engines. DocumentRunner clones, and this proves it — the object here
    // must be untouched afterwards, or PhreeForm would see a report with no
    // grouping left.
    $before = serialize($definition);

    try {
        $document = $runner->run($definition, [
            'dates'      => 'a',
            'period'     => $period,
            'gl_account' => $glAccount,
        ]);
    } catch (\Throwable $e) {
        ok("pf_$id ($title) runs", false, get_class($e) . ': ' . $e->getMessage());
        continue;
    }

    if (serialize($definition) !== $before) {
        ok("pf_$id ($title) leaves its definition untouched", false,
           'the runner mutated the definition it was given');
        continue;
    }

    $rows = $document instanceof TableDocument ? count($document->rows) : 0;
    if ($rows === 0) { $empty++; continue; }

    $pdfOut = $pdf->render($document);
    $csvOut = $csv->render($document);

    $sane = str_starts_with($pdfOut->bytes, '%PDF')
         && $pdfOut->size() > 500
         && $csvOut->size() > 0
         && $pdfOut->mimeType === 'application/pdf'
         && $csvOut->mimeType === 'text/csv';
    if (!$sane) {
        ok("pf_$id ($title) renders", false, sprintf(
            'pdf %d bytes (%s), csv %d bytes (%s)',
            $pdfOut->size(), substr($pdfOut->bytes, 0, 4), $csvOut->size(), $csvOut->mimeType));
        continue;
    }
    $ran++;
}
ok(sprintf('%d document(s) ran and rendered as both PDF and CSV', $ran), $ran > 0);
printf("  routing: %d by dataset, %d by provider, %d not served by Forms; %d ran empty\n",
    $counts['dataset'], $counts['provider'], $counts['unsupported'], $empty);

// An empty document is not a failure — a report can legitimately select nothing
// on this company — but a company where EVERYTHING is empty proves nothing at
// all, which is the vacuous pass this suite has been caught by three times.
ok('the run produced substantive documents, not a set of empty ones',
   $ran > $empty, "$ran with rows, $empty empty");

echo "\n=== a definition Forms cannot serve is declined, not faked ===\n";
$declined = null;
foreach ($documents as $id => $title) {
    $definition = get_report_details($id);
    if ($definition && !$runner->plan($definition)->runnable()) { $declined = [$id, $title, $definition]; break; }
}
if ($declined === null) {
    echo "  [--] every definition is served; nothing to decline\n";
} else {
    [$id, $title, $definition] = $declined;
    $plan  = $runner->plan($definition);
    $threw = false;
    try { $runner->run($definition); } catch (\RuntimeException $e) { $threw = true; }
    ok("pf_$id ($title) is refused rather than run to an empty document", $threw);
    ok('...and says why', $plan->notes !== [], 'no explanation was offered');
    if ($plan->notes) echo "      " . implode(' ', $plan->notes) . "\n";
}

echo "\n=== the run dialog's answers are required where they matter ===\n";
$recon = null;
foreach ($documents as $id => $title) {
    $definition = get_report_details($id);
    if ($definition && ($definition->special_class ?? '') === 'acct_recon') { $recon = [$id, $definition]; break; }
}
if ($recon === null) {
    echo "  [--] no reconciliation definition on this company\n";
} else {
    [$id, $definition] = $recon;
    $plan = $runner->plan($definition);
    ok("pf_$id declares that it needs a GL account", $plan->needsGlAccount);
    ok("pf_$id declares that it needs a period", $plan->needsPeriod);
    // Without the account it must produce nothing rather than reconcile whichever
    // account sorted first, which is what reading filterlist[0] amounted to.
    $withoutAccount = $runner->run($definition, ['period' => $period]);
    ok('without a GL account it produces an empty document, not a wrong one',
       $withoutAccount instanceof TableDocument && count($withoutAccount->rows) === 0,
       'it produced ' . ($withoutAccount instanceof TableDocument ? count($withoutAccount->rows) : '?') . ' rows');
}

echo "\n=== delivery ===\n";
$definition = null;
foreach ($documents as $id => $title) {
    $candidate = get_report_details($id);
    if (!$candidate || !$runner->plan($candidate)->runnable()) continue;
    $document = $runner->run($candidate, ['dates' => 'a', 'period' => $period, 'gl_account' => $glAccount]);
    if ($document instanceof TableDocument && count($document->rows) > 0) { $definition = $document; break; }
}
if ($definition === null) {
    ok('a document was available to deliver', false, 'nothing rendered any rows');
} else {
    $headers = [];
    $body    = '';
    $result  = (new HttpDelivery(HttpDelivery::ATTACHMENT,
        function (string $h) use (&$headers) { $headers[] = $h; },
        function (string $b) use (&$body)    { $body .= $b; },
        static fn() => false))->deliver($pdf->render($definition));

    ok('the rendered document is delivered', $result->ok, $result->detail);
    ok('the delivered bytes are the PDF', str_starts_with($body, '%PDF'));
    ok('no response header carries a bare CR or LF', !preg_match('/[\r\n]/', implode('', $headers)));
    ok('the filename is safe on any filesystem',
       (bool)preg_match('/filename="[A-Za-z0-9._-]+"/', implode(' ', $headers)),
       implode(' | ', $headers));
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
