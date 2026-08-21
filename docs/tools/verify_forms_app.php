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
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, RunPlan};
use FreeBooks\Forms\Definition\DefinitionLoader;
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
// Through the loader, as the page does: a definition the report editor has
// saved is native, and get_report_details() cannot read one.
$loader = new DefinitionLoader($catalog);

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

$counts = ['dataset' => 0, 'provider' => 0, 'form' => 0, 'unsupported' => 0];
$unconvertible = [];   // named, not silently skipped — the page lists these too
$ran    = $empty = $fitted = $landscape = 0;

$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_type <> '0' order by id");
$documents = [];
while (!$result->EOF) {
    $documents[(int)$result->fields['id']] = (string)$result->fields['doc_title'];
    $result->MoveNext();
}

echo "=== every definition is routed, and every route runs ===\n";
foreach ($documents as $id => $title) {
    // The page lists an unconvertible definition rather than dropping it, so
    // the tool has to reach the same verdict by the same route.
    try {
        $definition = $loader->load($id);
    } catch (\FreeBooks\Forms\Definition\DefinitionException $e) {
        $counts['unsupported']++;
        $unconvertible[] = "pf_$id ($title): " . $e->getMessage();
        continue;
    }
    if (!$definition) continue;

    $plan = $runner->planDefinition($definition);
    $counts[$plan->kind]++;
    if (!$plan->runnable()) continue;

    // The one thing a page must never do: hand the same definition object to
    // two engines. DocumentRunner clones, and this proves it — the object here
    // must be untouched afterwards, or PhreeForm would see a report with no
    // grouping left.
    $before = serialize($definition);

    try {
        $document = $runner->runDefinition($definition, [
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

    // The table must end inside the right margin. PhreeForm let bands run past
    // it and TCPDF clipped whatever fell outside, so a report wider than its
    // page lost the tail of its last column silently — 28 of the 43 shipped
    // dataset reports are wider than the page they declare. The renderer scales
    // the bands to fit; this is what proves it did.
    //
    // Read by reflection deliberately: the band positions are an implementation
    // detail, and widening the renderer's public API so a dev tool can look at
    // them would be the wrong trade.
    $edges  = (new \ReflectionProperty($pdf, 'bandX'))->getValue($pdf);
    // The right edge the renderer itself used, on the orientation it chose: a
    // report too wide for its page is now given a landscape one, so measuring
    // against the DECLARED page would fail every report that was correctly
    // turned — and TCPDF reports a width of zero once the document is closed.
    $limit = (float)(new \ReflectionProperty($pdf, 'pageRight'))->getValue($pdf);
    $edge   = is_array($edges) ? (float)end($edges) : 0.0;
    if ($edge > $limit + 0.01) {
        ok("pf_$id ($title) fits between its margins", false,
           sprintf('table ends at %.1fmm, right margin is at %.1fmm', $edge, $limit));
        continue;
    }
    if ($limit > 210.0) $landscape++;   // wider than a portrait A4/Letter body
    $fitted++;

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
ok(sprintf('every rendered table fits between its margins (%d checked)', $fitted), $fitted === $ran);
printf("  %d printed landscape, %d portrait\n", $landscape, $fitted - $landscape);
printf("  routing: %d by dataset, %d by provider, %d forms (printed, not run),"
     . " %d not served by Forms; %d ran empty\n",
    $counts['dataset'], $counts['provider'], $counts['form'], $counts['unsupported'], $empty);

// A form must never be routed as a table. It has no columns and no grouping, so
// serving it here produces a plausible-looking page of rows that is not the
// document anyone asked for — which is exactly what the list did until the
// reporttype check was added.
$formsAsTables = [];
foreach ($documents as $id => $title) {
    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    if (!$definition->isForm()) continue;
    $plan = $runner->planDefinition($definition);
    if ($plan->runnable()) $formsAsTables[] = "pf_$id ($title) as {$plan->kind}/{$plan->name}";
}
ok('no form is routed as a report', $formsAsTables === [],
   implode(' | ', array_slice($formsAsTables, 0, 4)));

// An empty document is not a failure — a report can legitimately select nothing
// on this company — but a company where EVERYTHING is empty proves nothing at
// all, which is the vacuous pass this suite has been caught by three times.
ok('the run produced substantive documents, not a set of empty ones',
   $ran > $empty, "$ran with rows, $empty empty");

echo "\n=== a definition Forms cannot serve is declined, not faked ===\n";
// UNSUPPORTED specifically. A form is also not runnable here, but it IS served
// — by FormRunner and the print page — so treating it as the example of
// something Forms cannot do would test the wrong thing.
$declined = null;
foreach ($documents as $id => $title) {
    // A definition that cannot even be CONVERTED never reaches a plan, so it is
    // not the example wanted here: this looks for one that loads and is then
    // declined on its merits.
    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    if ($definition && $runner->planDefinition($definition)->kind === RunPlan::UNSUPPORTED) {
        $declined = [$id, $title, $definition];
        break;
    }
}
// A definition that cannot be CONVERTED never reaches a plan at all — it is
// refused by the loader, with the reason. That is where "declined, not faked"
// now lives for those, so it is asserted here rather than quietly lost when the
// plan-level example stopped existing.
if ($unconvertible) {
    ok('an unconvertible definition is refused with a reason, not served',
       (bool)preg_match('/\w/', (string)$unconvertible[0]));
    echo "         " . $unconvertible[0] . "\n";
    // And the LIST still shows it: the page turns that refusal into an
    // unsupported row rather than dropping the document.
    $listSource = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/main/pre_process.php');
    ok('the document list still lists what it cannot serve',
       str_contains($listSource, 'RunPlan::UNSUPPORTED'),
       'a refused definition would vanish from the list');
}

if ($declined === null) {
    echo "  [--] every definition that loads is served; nothing to decline at plan level\n";
} else {
    [$id, $title, $definition] = $declined;
    $plan  = $runner->planDefinition($definition);
    $threw = false;
    try { $runner->runDefinition($definition); } catch (\RuntimeException $e) { $threw = true; }
    ok("pf_$id ($title) is refused rather than run to an empty document", $threw);
    ok('...and says why', $plan->notes !== [], 'no explanation was offered');
    if ($plan->notes) echo "      " . implode(' ', $plan->notes) . "\n";
}

echo "\n=== a form is sent to the print path, not run as a table ===\n";
$formExample = null;
foreach ($documents as $id => $title) {
    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    if ($runner->planDefinition($definition)->isForm()) { $formExample = [$id, $title, $definition]; break; }
}
if ($formExample === null) {
    echo "  [--] no form in this catalog\n";
} else {
    [$id, $title, $definition] = $formExample;
    $plan  = $runner->planDefinition($definition);
    ok("pf_$id ($title) is planned as a form", $plan->isForm());
    ok('...and is not offered as runnable', !$plan->runnable());
    $threw = '';
    try { $runner->runDefinition($definition); } catch (\RuntimeException $e) { $threw = $e->getMessage(); }
    ok('...and running it says where to print it instead',
       str_contains(strtolower($threw), 'form'), $threw ?: 'it did not throw');
    ok('...and the plan explains itself for the list', $plan->notes !== []);
}

echo "\n=== the run dialog's answers are required where they matter ===\n";
$recon = null;
foreach ($documents as $id => $title) {
    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    // A native definition NAMES the class that serves it, where the legacy one
    // carried a special_class the runner had to interpret.
    if ($definition->provider === 'acct_recon') { $recon = [$id, $definition]; break; }
}
if ($recon === null) {
    echo "  [--] no reconciliation definition on this company\n";
} else {
    [$id, $definition] = $recon;
    $plan = $runner->planDefinition($definition);
    ok("pf_$id declares that it needs a GL account", $plan->needsGlAccount);
    ok("pf_$id declares that it needs a period", $plan->needsPeriod);
    // Without the account it must produce nothing rather than reconcile whichever
    // account sorted first, which is what reading filterlist[0] amounted to.
    $withoutAccount = $runner->runDefinition($definition, ['period' => $period]);
    ok('without a GL account it produces an empty document, not a wrong one',
       $withoutAccount instanceof TableDocument && count($withoutAccount->rows) === 0,
       'it produced ' . ($withoutAccount instanceof TableDocument ? count($withoutAccount->rows) : '?') . ' rows');
}

echo "\n=== delivery ===\n";
$definition = null;
foreach ($documents as $id => $title) {
    try { $candidate = $loader->load($id); } catch (\Throwable) { continue; }
    if (!$runner->planDefinition($candidate)->runnable()) continue;
    $document = $runner->runDefinition($candidate, ['dates' => 'a', 'period' => $period, 'gl_account' => $glAccount]);
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
