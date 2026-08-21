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
//  Path: /docs/tools/verify_forms_printed_field.php
//
/**
 * Verify the printed counter, end to end. See docs/forms-design.md §13.45.
 *
 * `journal_main.printed` is what the Sales Invoice Manager's "unprinted" filter
 * reads, and printing an invoice is the only thing that sets it. PhreeForm did
 * it from inside its PDF builder; Forms declares it on the document and lets
 * the caller perform it, which is the right seam and was also — until §13.45 —
 * a seam with nothing on either side of it. The field was dropped by the
 * converter, never passed by the runner, and no caller ran a hook, so every
 * migrated company silently stopped counting and nothing noticed.
 *
 * That is the shape this tool exists for: a capability that is absent rather
 * than wrong. Nothing errors, no name is borrowed, no byte differs — the
 * counter simply never moves. So the assertions here follow one invoice all the
 * way from the XML tag to the incremented row, and each link is checked
 * separately, because any one of them going missing again would put the whole
 * chain back to silence.
 *
 *     docker exec <c> php -d display_errors=1 \
 *         /opt/repo/docs/tools/verify_forms_printed_field.php [company]
 *
 * The database IS written: one record's counter is incremented for real, then
 * put back. Nothing else on the company is touched, and the restore runs from a
 * shutdown function so it happens on a fatal too.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{AfterRenderRunner, FormRunner};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{Definition, DefinitionBuilder, DefinitionCodec, DefinitionForm,
    SerialForm, TemplateForm};
use FreeBooks\Forms\Document\{AfterRenderHook, LayoutDocument, SerialTemplate, Template};
use FreeBooks\Forms\Legacy\LegacyReader;

$failures = 0; $passes = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures, $passes;
    if ($cond) { $passes++; echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);
$codec = new DefinitionCodec();

// ── the tag survives conversion ─────────────────────────────────────────────
//
// Read from the shipped masters rather than from a hand-built object: these two
// files are the only ones in the product that declare a counter, so they are
// what "it converts" has to mean.
echo "\n=== from the legacy tag ===\n";
$masters = DIR_FS_MODULES . 'phreebooks/language/en_us/reports/';
foreach (['Invoice', 'DepositReceipt'] as $name) {
    $report = LegacyReader::fromFile($masters . $name . '.xml');
    ok("$name.xml declares a printed counter",
       trim((string)($report->setprintedfield ?? '')) === 'journal_main.printed');

    $definition = (new DefinitionBuilder($catalog))->fromLegacy($report);
    ok("...and converting it keeps the counter",
       $definition->printedField === 'journal_main.printed', $definition->printedField);
    ok("...parsed into a table and a field",
       $definition->printedTarget() === ['table' => 'journal_main', 'field' => 'printed'],
       var_export($definition->printedTarget(), true));

    $shipped = $codec->decode((string)file_get_contents($masters . $name . '.json'));
    ok("$name.json — the file a NEW company is seeded from — carries it too",
       $shipped->printedField === 'journal_main.printed', $shipped->printedField);
}

// A form that declares nothing must store nothing: the key is absent from the
// file rather than present and empty, which is what keeps the other 50 shipped
// definitions byte-identical to what they were before the field existed.
$plain = $codec->decode((string)file_get_contents($masters . 'PackingSlip.json'));
ok('a form with no counter imports as none', $plain->printedField === '');
ok('...and stores no key for it',
   !str_contains($codec->encode($plain), 'printedField'));

// A malformed tag costs the counter, not the document.
$broken = LegacyReader::parse(str_replace(
    'journal_main.printed', 'printed', (string)file_get_contents($masters . 'Invoice.xml')));
$import = new DefinitionBuilder($catalog);
$brokenDefinition = $import->fromLegacy($broken);
ok('a counter that is not table.field is dropped', $brokenDefinition->printedField === '');
ok('...with a warning saying so, rather than silently',
   (bool)preg_grep('/printed-counter/', $import->warnings()),
   implode(' | ', $import->warnings()));
ok('...and the form still converts', $brokenDefinition->isForm());

// ── the format keeps it ─────────────────────────────────────────────────────
echo "\n=== through the format and the editors ===\n";
$invoice = (new DefinitionBuilder($catalog))->fromLegacy(LegacyReader::fromFile($masters . 'Invoice.xml'));
ok('encode/decode round-trips the counter',
   $codec->decode($codec->encode($invoice))->printedField === 'journal_main.printed');

// Every editor rebuilds a Definition from scratch, so a field none of them
// carries is deleted by the first save. That is how filenamePrefix was nearly
// lost, and it is the likeliest way this one would go missing again.
$form    = new TemplateForm();
$allowed = forms_binding_names($invoice, new FormRunner($db, $catalog), $catalog, $db);
ok('the canvas carries the counter through a save',
   $form->apply($invoice, ['element' => TemplateForm::toRequest($invoice->template)], $allowed)->printedField
       === 'journal_main.printed');

// The report editor cannot reach a counter at all — it refuses a form outright,
// which is why a printed field is form-only in the first place. Asserted rather
// than assumed: if that ever changed, the field would need carrying there too.
$refused = false;
try { (new DefinitionForm())->apply($invoice, ['title' => $invoice->title], null); }
catch (\FreeBooks\Forms\Definition\DefinitionException $e) { $refused = true; }
ok('the report editor never sees a form, so it cannot drop the counter', $refused);

// The company's own receipt, given a counter it does not normally have: what is
// being checked is the SAVE, and a receipt with no fields is refused before it
// gets that far.
$receiptFile = null;
foreach (glob(rtrim(FORMS_DIR_DEFINITIONS, '/\\') . '/pf_*') ?: [] as $path) {
    if (str_contains($path, '.pre-forms')) continue;
    try { $candidate = $codec->decode((string)file_get_contents($path)); }
    catch (\Throwable $e) { continue; }   // still in the legacy format
    if ($candidate->isSerial() && $candidate->serialTemplate !== null) { $receiptFile = $candidate; break; }
}
if ($receiptFile === null) {
    echo "  [--] no receipt on this company to save\n";
} else {
    $receipt = $codec->decode(str_replace('"serial": true',
        '"printedField": "journal_main.printed",' . "\n        " . '"serial": true',
        $codec->encode($receiptFile)));
    ok('a receipt can hold a counter at all', $receipt->printedField === 'journal_main.printed');
    ok('the receipt editor carries it through a save',
       (new SerialForm())->apply($receipt, [
           'field' => SerialForm::toRequest($receipt->serialTemplate),
           'paper' => (string)$receipt->serialTemplate->width,
       ])->printedField === 'journal_main.printed');
}

// ── the runner asks for it ──────────────────────────────────────────────────
echo "\n=== the runner declares the side effect ===\n";
$runner = new FormRunner($db, $catalog);

$record = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . "
    where journal_id in (12,13) order by id desc limit 1");
if (!$record || $record->EOF) {
    echo "  [--] no sales journal entry on this company to print\n";
    printf("\n%d passed, %d failed\n", $passes, $failures);
    exit($failures === 0 ? 0 : 1);
}
$recordId = (int)$record->fields['id'];

$document = $runner->runDefinition($invoice, [$recordId]);
ok('printing an invoice builds a record', $document instanceof LayoutDocument
    && count($document->records) === 1);
$hooks = $document->afterRender();
ok('...and declares exactly one printed-counter hook for it', count($hooks) === 1,
   (string)count($hooks));
ok('...naming the table, the field, the key column and the record',
   count($hooks) === 1 && $hooks[0]->type === 'increment_field'
   && $hooks[0]->params === ['table' => 'journal_main', 'field' => 'printed',
                             'key' => 'id', 'id' => $recordId],
   count($hooks) ? json_encode($hooks[0]->params) : '');

// The same definition without the field: the difference between "counts" and
// "does not count" must be the definition, not the code path.
$uncounted = $codec->decode(str_replace('"printedField": "journal_main.printed"',
    '"printedField": ""', $codec->encode($invoice)));
ok('the same form with no counter declares no hook',
   count($runner->runDefinition($uncounted, [$recordId])->afterRender()) === 0);

// Two records, two hooks — PhreeForm incremented per record and so must this.
$two = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . "
    where journal_id in (12,13) order by id desc limit 2");
$ids = [];
while ($two && !$two->EOF) { $ids[] = (int)$two->fields['id']; $two->MoveNext(); }
if (count($ids) === 2) {
    ok('two records printed together declare two hooks',
       count($runner->runDefinition($invoice, $ids)->afterRender()) === 2);
}

// A builder that cannot honour a counter must SAY so. Silence here is the whole
// defect being fixed, one provider along.
$statement = null;
foreach (glob(rtrim(FORMS_DIR_DEFINITIONS, '/\\') . '/pf_*') ?: [] as $path) {
    if (str_contains($path, '.pre-forms')) continue;
    try { $candidate = $codec->decode((string)file_get_contents($path)); }
    catch (\Throwable $e) { continue; }
    if ($candidate->provider === 'statement_builder') { $statement = $candidate; break; }
}
if ($statement === null) {
    echo "  [--] no statement form on this company\n";
} else {
    $counted = $codec->decode(str_replace('"recordTable"',
        '"printedField": "contacts.printed",' . "\n        " . '"recordTable"',
        $codec->encode($statement)));
    ok('a statement can be given a counter in its file', $counted->printedField !== '');
    $runner->runDefinition($counted, [0]);
    ok('...but the builder that serves it says it cannot count them',
       (bool)preg_grep('/print uncounted/', $runner->warnings()),
       implode(' | ', $runner->warnings()));
}

if ($receiptFile !== null) {
    $countedReceipt = $codec->decode(str_replace('"serial": true',
        '"printedField": "journal_main.printed",' . "\n        " . '"serial": true',
        $codec->encode($receiptFile)));
    try { $runner->runDefinition($countedReceipt, [$recordId]); } catch (\Throwable $e) {}
    ok('a receipt asking to count is told a till roll is not counted',
       (bool)preg_grep('/not counted/', $runner->warnings()),
       implode(' | ', $runner->warnings()));
}

// ── and the caller performs it ──────────────────────────────────────────────
echo "\n=== the counter actually moves ===\n";
$before = (int)$db->Execute("select printed from " . TABLE_JOURNAL_MAIN . "
    where id = $recordId")->fields['printed'];

// Put back from a shutdown function, so an assertion that throws below still
// leaves the company as it was found.
register_shutdown_function(function () use ($db, $recordId, $before) {
    $db->Execute("update " . TABLE_JOURNAL_MAIN . " set printed = $before where id = $recordId");
});

$hookRunner = new AfterRenderRunner($db);
$done = $hookRunner->run($document);
$after = (int)$db->Execute("select printed from " . TABLE_JOURNAL_MAIN . "
    where id = $recordId")->fields['printed'];
ok('running the hooks reports one done', $done === 1, (string)$done);
ok('...with no failures', $hookRunner->failures() === [], implode(' | ', $hookRunner->failures()));
ok('...and the counter is one higher than it was',
   $after === $before + 1, "was $before, now $after");

$again = new AfterRenderRunner($db);
$again->run($runner->runDefinition($invoice, [$recordId]));
$twice = (int)$db->Execute("select printed from " . TABLE_JOURNAL_MAIN . "
    where id = $recordId")->fields['printed'];
ok('printing it a second time counts again', $twice === $before + 2, "now $twice");

// A record that is not there must be reported rather than counted: an id the
// table does not hold means the counter would never move however often the
// form was printed, which is exactly the fault being fixed.
$missing = new AfterRenderRunner($db);
$missingDone = $missing->run(new LayoutDocument(title: 'x', template: new Template(), records: [], afterRender: [
    new AfterRenderHook('increment_field',
        ['table' => 'journal_main', 'field' => 'printed', 'key' => 'id', 'id' => 0]),
]));
ok('a counter for a record that is not there is not counted as done', $missingDone === 0);
ok('...and says which record and column', (bool)preg_grep('/journal_main/', $missing->failures()),
   implode(' | ', $missing->failures()));

// Identifiers reach an UPDATE unescapable, so the pattern check is load-bearing.
$hostile = new AfterRenderRunner($db);
$hostileDone = $hostile->run(new LayoutDocument(title: 'x', template: new Template(), records: [], afterRender: [
    new AfterRenderHook('increment_field',
        ['table' => 'journal_main` set `printed` = 99 where 1=1 -- ',
         'field' => 'printed', 'key' => 'id', 'id' => $recordId]),
]));
ok('a table name that is not an identifier is refused', $hostileDone === 0);
// Refused BY THE PATTERN CHECK, which is the assertion that matters: an
// injected name that merely happens to be invalid SQL is refused by the
// database, and would stop being refused the day one is found that is not.
ok('...by the identifier check rather than by luck at the database',
   (bool)preg_grep('/not a table or column name/', $hostile->failures()),
   implode(' | ', $hostile->failures()));
$untouched = (int)$db->Execute("select printed from " . TABLE_JOURNAL_MAIN . "
    where id = $recordId")->fields['printed'];
ok('...and changed nothing', $untouched === $twice, "now $untouched");

$unknown = new AfterRenderRunner($db);
ok('an unknown hook type is reported, not ignored',
   $unknown->run(new LayoutDocument(title: 'x', template: new Template(), records: [], afterRender: [
       new AfterRenderHook('deliver_by_owl', []),
   ])) === 0 && $unknown->failures() !== []);

// ── only delivery runs them ─────────────────────────────────────────────────
//
// The seam is worth nothing if a preview quietly acquires a hook runner. This
// is a source check because that is the only way to state the rule for code
// that has not been written yet.
echo "\n=== who is allowed to perform them ===\n";
$module  = DIR_FS_MODULES . 'forms/';
$callers = [];
foreach (['pages', 'ajax', 'functions', 'classes'] as $where) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module . $where));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $source = (string)file_get_contents($file->getPathname());
        if (str_contains($source, 'AfterRenderRunner')
            && !str_ends_with($file->getPathname(), 'after_render.php')) {
            $callers[] = str_replace($module, '', $file->getPathname());
        }
    }
}
sort($callers);
$allowed = ['functions/forms.php', 'pages/print/pre_process.php'];
ok('the hook runner is used by the delivery paths and nothing else',
   $callers === $allowed, implode(', ', $callers));

$print = (string)file_get_contents($module . 'pages/print/pre_process.php');
ok('the print page performs them after a view or download', (bool)preg_match(
   '/forms_print_delivered\(\);\s*\n\s*exit;/', $print));
ok('...and after an email is sent', substr_count($print, 'forms_print_delivered();') === 2);

$previewers = ['ajax/canvas_pdf.php', 'ajax/design_preview.php', 'ajax/receipt_preview.php'];
foreach ($previewers as $preview) {
    ok("$preview builds a document but marks nothing printed",
       !str_contains((string)file_get_contents($module . $preview), 'AfterRenderRunner'));
}

printf("\n%d passed, %d failed\n", $passes, $failures);
exit($failures === 0 ? 0 : 1);
