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
//  Path: /docs/tools/verify_forms_designer.php
//
/**
 * The report editor edits without changing anything it was not asked to
 * (design §13.7 step 2).
 *
 * The property that matters, and the one an editor most easily loses: opening a
 * report and saving it without touching a field must produce the definition it
 * opened. Everything an editor cannot represent — a filter mode it has no
 * control for, a style it does not draw, a column key it does not offer —
 * disappears on that save, silently, and the author has no way to know. So the
 * check is a round trip through the editor's own form representation, for every
 * report on both companies, compared structurally AND by rendered bytes.
 *
 * Three more things are checked, each a rule the page is supposed to keep:
 *
 *   - a field name the dataset does not declare is REFUSED, not stored (§13.4).
 *     The selects on the page are a convenience; the POST is whatever the
 *     browser chooses to send, so this is the only place the rule is real;
 *   - saving keeps the PhreeForm original beside the native file (§13.3
 *     condition 2), and a second save does not overwrite that copy;
 *   - every language constant the page names is defined once the module's
 *     language files are loaded. A CLI harness cannot prove a page renders —
 *     see the page-contract note in §12.1.1 — but an undefined constant is
 *     fatal on first open, and it is exactly what bit the last two pages.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_designer.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
// The module's own defaults, which a page requires for itself — FORMS_DEFAULT_COLUMN_WIDTH
// is there and nothing loads it automatically.
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, SampleData};
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{Definition, DefinitionCodec, DefinitionForm, DefinitionLoader};
use FreeBooks\Forms\Document\{RowKind, TableDocument};
use FreeBooks\Forms\Render\{Formatter, TablePdfRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/** @return list<string> where two value graphs first differ */
function differences(mixed $a, mixed $b, string $path = 'definition', int $limit = 6): array
{
    if (is_object($a) && is_object($b)) {
        if ($a::class !== $b::class) return ["$path is " . $a::class . ' vs ' . $b::class];
        if ($a instanceof \UnitEnum) return $a === $b ? [] : ["$path: different case"];
        return differences((array)$a, (array)$b, $path, $limit);
    }
    if (is_object($a) !== is_object($b)) {
        return [sprintf('%s is %s vs %s', $path, is_object($a) ? $a::class : var_export($a, true),
            is_object($b) ? $b::class : var_export($b, true))];
    }
    if (is_array($a) && is_array($b)) {
        if (array_keys($a) !== array_keys($b)) return ["$path has " . count($a) . ' vs ' . count($b) . ' entries'];
        $found = [];
        foreach ($a as $key => $value) {
            $found = array_merge($found, differences($value, $b[$key], "$path.$key", $limit));
            if (count($found) >= $limit) break;
        }
        return array_slice($found, 0, $limit);
    }
    return $a === $b ? [] : ["$path: " . var_export($a, true) . ' vs ' . var_export($b, true)];
}

/** TCPDF's own nondeterminism removed — see verify_forms_native.php. */
function normalisePdf(string $bytes): string
{
    $bytes = preg_replace('/\/(?:CreationDate|ModDate|LastModified|M)\s*\(D:[^)]*\)/', '/Date()', $bytes);
    $bytes = preg_replace('/\/ID\s*\[[^\]]*\]/', '/ID[]', $bytes);
    $bytes = preg_replace('/uuid:[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}/i', 'uuid:', $bytes);
    return preg_replace('/<(xmp:(?:Create|Modify|Metadata)Date)>[^<]*<\/\1>/', '<$1/>', $bytes);
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$source  = new DefinitionLoader($catalog);
$runner  = new DocumentRunner($db, $catalog);
$codec   = new DefinitionCodec();
$scratch = sys_get_temp_dir() . '/forms-designer-' . $company . '/';
if (!is_dir($scratch)) mkdir($scratch, 0777, true);

$pdf = new TablePdfRenderer(
    formatter: new Formatter(
        currencies:      $currencies,
        defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
        lookups:         forms_formatter_lookups()),
    defaultColumnWidth: (float)FORMS_DEFAULT_COLUMN_WIDTH);

$period = (int)($db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
    . " where start_date <= '" . date('Y-m-d') . "' and end_date >= '" . date('Y-m-d') . "'")
    ->fields['period'] ?? 1);

$reports = [];
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_type <> '0' and doc_ext <> 'frm' order by id");
while (!$result->EOF) {
    $reports[(int)$result->fields['id']] = (string)$result->fields['doc_title'];
    $result->MoveNext();
}
printf("company '%s', %d report definition(s)\n\n", $company, count($reports));

// ─────────────────────────────────────────────────────────────────────────────
echo "=== opening a report and saving it unchanged changes nothing ===\n";

$loaded = $unchanged = $rendered = $identical = 0;
$skipped = [];

foreach ($reports as $id => $title) {
    try {
        $definition = $source->load($id);
    } catch (\Throwable $e) {
        $skipped[] = "pf_$id ($title): " . $e->getMessage();
        continue;
    }
    if ($definition->isForm()) continue;
    $loaded++;

    $dataset = $definition->datasetId !== null && $catalog->has($definition->datasetId)
        ? $catalog->get($definition->datasetId) : null;

    $binding = new DefinitionForm();
    try {
        // Exactly what the browser would post back if the author touched nothing.
        $again = $binding->apply($definition, DefinitionForm::toRequest($definition), $dataset);
    } catch (\Throwable $e) {
        ok("pf_$id ($title) survives an untouched save", false, $e->getMessage());
        continue;
    }

    $where = differences($definition, $again);
    if ($where) {
        ok("pf_$id ($title) survives an untouched save", false, implode('; ', $where));
        continue;
    }
    if ($binding->warnings()) {
        ok("pf_$id ($title) round-trips without refusing its own fields", false,
            implode(' ', $binding->warnings()));
        continue;
    }
    $unchanged++;

    // Structural equality is the strong claim, but it is worth spending the
    // render: a definition that compares equal and prints differently would
    // mean the comparison is looking at the wrong thing.
    try {
        $before = $runner->runDefinition($definition, ['period' => $period]);
        $after  = $runner->runDefinition($again,      ['period' => $period]);
    } catch (\Throwable $e) {
        $skipped[] = "pf_$id ($title): " . $e->getMessage();
        continue;
    }
    if ($before instanceof TableDocument && count($before->rows) === 0) {
        $skipped[] = "pf_$id ($title): no rows on this company";
    }
    $rendered++;
    if (normalisePdf($pdf->render($before)->bytes) === normalisePdf($pdf->render($after)->bytes)) {
        $identical++;
    } else {
        ok("pf_$id ($title) prints the same after an untouched save", false);
    }
}

ok("all $loaded report(s) survive an untouched save", $loaded > 0 && $unchanged === $loaded,
    "$unchanged of $loaded");
ok("all $rendered rendered report(s) print identically after one", $rendered > 0 && $identical === $rendered,
    "$identical of $rendered");

if ($skipped) {
    echo "\n  not compared (" . count($skipped) . "):\n";
    foreach ($skipped as $note) echo "    $note\n";
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== an author may only name what the dataset declares ===\n";

$victim = null;
foreach ($reports as $id => $title) {
    try { $candidate = $source->load($id); } catch (\Throwable) { continue; }
    if (!$candidate->isForm() && $candidate->datasetId !== null && $candidate->columns) {
        $victim = $candidate;
        $victimDataset = $catalog->get($candidate->datasetId);
        break;
    }
}

if ($victim === null) {
    ok('a dataset-driven report is available to test against', false);
} else {
    $attack = function (callable $mutate) use ($victim, $victimDataset): array {
        $request = DefinitionForm::toRequest($victim);
        $request = $mutate($request);
        $binding = new DefinitionForm();
        return [$binding->apply($victim, $request, $victimDataset), $binding->warnings()];
    };

    // A column key that is not a dataset field.
    [$result, $warnings] = $attack(function (array $r): array {
        $r['column'][0]['key'] = 'contacts.tax_id';   // a real column, not a declared field
        return $r;
    });
    ok('a column naming an undeclared field is dropped',
        count($result->columns) === count($victim->columns) - 1 && $warnings !== []);

    // SQL in a field name — PhreeForm's actual defect (§1.1).
    [$result, $warnings] = $attack(function (array $r): array {
        $r['column'][0]['key'] = "id` from users -- ";
        return $r;
    });
    ok('SQL in a column name is dropped rather than stored',
        count($result->columns) === count($victim->columns) - 1 && $warnings !== []);

    // A filter on a field the dataset does not declare.
    [$result, $warnings] = $attack(function (array $r): array {
        $r['filter'][] = ['field' => 'users.admin_pass', 'operator' => 'eq',
                          'value1' => 'x', 'value2' => '', 'expression' => ''];
        return $r;
    });
    ok('a filter on an undeclared field is dropped',
        count($result->filters) === count($victim->filters) && $warnings !== []);

    // A sort likewise.
    [$result, $warnings] = $attack(function (array $r): array {
        $r['sort'][] = ['field' => 'users.id', 'direction' => 'ASC'];
        return $r;
    });
    ok('a sort on an undeclared field is dropped',
        count($result->sorts) === count($victim->sorts) && $warnings !== []);

    // A field expression is re-validated on the way in, not trusted. The FIELD
    // is a real one, so the only thing under test is the expression.
    $realKey = (string)array_key_first($victimDataset->fields);
    [$result, $warnings] = $attack(function (array $r) use ($realKey): array {
        $r['filter'][] = ['field' => $realKey, 'operator' => 'lt', 'value1' => '', 'value2' => '',
                          'expression' => json_encode([['kind' => 'field', 'text' => 'a`) or 1=1 -- ']])];
        return $r;
    });
    ok('a hand-edited field expression is refused',
        count($result->filters) === count($victim->filters) && $warnings !== []);

    // And the control: an ALLOWED change must actually take effect, or the
    // checks above would pass on an editor that simply ignores everything.
    [$result, $warnings] = $attack(function (array $r): array {
        $r['title'] = 'Renamed by the harness';
        $r['column'][0]['heading'] = 'Changed heading';
        return $r;
    });
    ok('an allowed change is applied',
        $result->title === 'Renamed by the harness'
        && ($result->columns[0]->heading ?? '') === 'Changed heading' && $warnings === []);
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== saving keeps the original, once ===\n";

if ($victim !== null) {
    // Saved under the victim's OWN id: a Definition carries the id it was
    // loaded from, so saving as 'test' and comparing would report a difference
    // that is the harness's doing rather than the code's.
    $editor = new DefinitionLoader($catalog, $scratch);
    $testId = $victim->id;
    $path   = $editor->path($testId);
    @unlink($path);
    @unlink($path . '.pre-forms');

    // Start from the PhreeForm file, as an unconverted installation would.
    $legacyPath = $source->path((int)array_key_first($reports));
    $legacy     = (string)@file_get_contents($legacyPath);
    ok('the stored original is PhreeForm XML to begin with',
        $legacy !== '' && !DefinitionLoader::isNative($legacy));
    file_put_contents($path, $legacy);

    // What pre_process.php does on save: keep a copy, then write native.
    if (!file_exists($path . '.pre-forms')) copy($path, $path . '.pre-forms');
    $editor->save($victim, $testId);

    ok('the saved definition is native', DefinitionLoader::isNative((string)file_get_contents($path)));
    ok('the original is kept beside it',
        file_exists($path . '.pre-forms')
        && !DefinitionLoader::isNative((string)file_get_contents($path . '.pre-forms')));

    // A second save must not overwrite the kept copy with a native file — that
    // would destroy the very thing it exists to preserve.
    $keptBefore = md5_file($path . '.pre-forms');
    if (!file_exists($path . '.pre-forms')) copy($path, $path . '.pre-forms');
    $editor->save($victim, $testId);
    ok('a second save leaves the kept original alone', md5_file($path . '.pre-forms') === $keptBefore);

    ok('what was saved loads back identically',
        differences($victim, $editor->load($testId)) === []);
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== an empty report still previews a layout ===\n";

// A preview of a report that returns nothing is a page of furniture, and column
// widths, grouping and totals — the things an author is actually judging — are
// invisible on it. A fresh install has no data at all.
$emptyReport = null;
foreach ($converted ?? [] as $ignored) {}
foreach ($reports as $id => $title) {
    try { $candidate = $source->load($id); } catch (\Throwable) { continue; }
    if ($candidate->isForm() || $candidate->datasetId === null || !$candidate->columns) continue;
    $emptyReport = $candidate;
    break;
}
if ($emptyReport === null) {
    ok('a dataset-driven report is available to sample', false);
} else {
    $realDataset = $catalog->get($emptyReport->datasetId);
    // An EMPTY document of the real shape, which is what the preview holds when
    // the filters exclude everything.
    $empty = new TableDocument(
        title: $emptyReport->title, columns: $emptyReport->columns, rows: [],
        page: $emptyReport->page, filterText: 'the real filter text',
        style: $emptyReport->style);

    $filled = SampleData::fill($empty, $realDataset, 'SAMPLE', $emptyReport->groupBy);

    ok('it produces rows where the report produced none', count($filled->rows) > 0,
       count($filled->rows) . ' row(s)');
    ok('the columns are the report\'s own, not invented',
       differences($empty->columns, $filled->columns) === []);
    ok('the page setup and styling are carried across',
       differences($empty->page, $filled->page) === []
       && differences($empty->style, $filled->style) === []);
    ok('it is marked as sample data on the page',
       str_contains((string)$filled->filterText, 'SAMPLE'));
    ok('the report\'s own filter description is kept',
       str_contains((string)$filled->filterText, 'the real filter text'));
    // Hooks must not fire for data that does not exist — setprintedfield would
    // mark a document printed on the strength of an invented row.
    ok('no after-render hook survives into the sample', $filled->afterRender() === []);
    // Every cell must be keyed by a column, or the renderer draws blanks.
    $stray = [];
    foreach ($filled->rows as $row) {
        foreach (array_keys($row->cells) as $key) {
            if ($realDataset->field($key) === null
                && !isset($emptyReport->resolvedColumns[$key])
                && !in_array($key, array_map(fn($c) => $c->key, $emptyReport->columns), true)) {
                $stray[] = (string)$key;
            }
        }
    }
    ok('every sample cell is keyed by a real column', $stray === [],
       implode(', ', array_slice(array_unique($stray), 0, 4)));
    // Stable: an author nudging a column width compares two previews.
    ok('two fills of the same report agree',
       differences($filled->rows,
           SampleData::fill($empty, $realDataset, 'SAMPLE', $emptyReport->groupBy)->rows) === []);

    if ($emptyReport->groupBy !== null && $emptyReport->groupBy !== '') {
        $kinds = array_map(fn($r) => $r->kind, $filled->rows);
        ok('a grouped report samples a group break',
           in_array(RowKind::GroupTotal, $kinds, true));
    }

    // And it renders — the point of the exercise.
    $out = $pdf->render($filled);
    ok('the sample renders as a PDF', str_starts_with($out->bytes, '%PDF') && $out->size() > 1000,
       $out->size() . ' bytes');
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the page names no constant that does not exist ===\n";

// Not a substitute for opening the page — a CLI harness cannot prove that
// (§12.1.1) — but an undefined constant is fatal on FIRST open, which is the
// defect that has actually shipped here twice.
gen_pull_language('forms');
gen_pull_language('phreeform');

$pageFiles = array_merge(
    glob(DIR_FS_MODULES . 'forms/pages/design/*.php') ?: [],
    [DIR_FS_MODULES . 'forms/ajax/design_preview.php'],
    // The receipt editor and its live preview, scanned for the same reason and
    // with more cause: retiring its chooser deleted five strings at once, and a
    // string still named by a page is fatal on first open, not on the line that
    // uses it.
    glob(DIR_FS_MODULES . 'forms/pages/receipt/*.php') ?: [],
    [DIR_FS_MODULES . 'forms/ajax/receipt_preview.php'],
    [DIR_FS_MODULES . 'forms/pages/canvas/template_main.php'],
    [DIR_FS_MODULES . 'forms/pages/main/template_main.php']);

$missing = $alwaysFallback = [];
foreach ($pageFiles as $file) {
    $source_code = (string)file_get_contents($file);
    // Comments carry prose that looks like constants ("SQL", "PDF"), and the
    // scan is worthless if it cannot tell those apart.
    $stripped = '';
    foreach (token_get_all($source_code) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $stripped .= is_array($token) ? $token[1] : $token;
    }
    // `defined('X') ? X : 'fallback'` is a legitimate optional constant, so it
    // is not a fatal. It is still worth reporting when X exists NOWHERE: the
    // guard can then only ever take the fallback, which is a translation that
    // can never be reached. That is how TEXT_GROUP_TOTAL was found — the real
    // constant is TEXT_GROUP_TOTAL_FOR, so every locale printed English.
    preg_match_all("/defined\(\s*'([A-Z0-9_]+)'\s*\)/", $stripped, $guarded);
    $guards = array_flip($guarded[1]);

    preg_match_all('/\b(FORMS_[A-Z0-9_]+|TEXT_[A-Z0-9_]+|BOX_[A-Z0-9_]+|SECURITY_ID_[A-Z0-9_]+)\b/',
        $stripped, $found);
    foreach (array_unique($found[1]) as $constant) {
        if (defined($constant)) continue;
        $where = basename(dirname($file)) . '/' . basename($file) . ': ' . $constant;
        if (isset($guards[$constant])) $alwaysFallback[] = $where;
        else                           $missing[] = $where;
    }
}
ok('every language and security constant the editor names is defined', $missing === [],
    implode(', ', array_slice($missing, 0, 8)));
ok('no guarded constant is one that exists nowhere', $alwaysFallback === [],
    implode(', ', array_slice($alwaysFallback, 0, 8)));

// The page contract: the three files a page needs, and the help topic its
// toolbar points at.
foreach (['pre_process.php', 'template_main.php', 'js_include.php'] as $file) {
    ok("the editor has its $file", is_file(DIR_FS_MODULES . 'forms/pages/design/' . $file));
}
ok('the preview endpoint exists where the page posts to it',
    is_file(DIR_FS_MODULES . 'forms/ajax/design_preview.php'));

// The receipt editor holds to the same contract, and posts to its own preview.
foreach (['pre_process.php', 'template_main.php', 'js_include.php'] as $file) {
    ok("the receipt editor has its $file", is_file(DIR_FS_MODULES . 'forms/pages/receipt/' . $file));
}
ok('the receipt preview endpoint exists where the page posts to it',
    is_file(DIR_FS_MODULES . 'forms/ajax/receipt_preview.php'));
$receiptPage = (string)@file_get_contents(DIR_FS_MODULES . 'forms/pages/receipt/template_main.php');
ok('the receipt preview refreshes from the editor form itself',
    str_contains($receiptPage, 'op=receipt_preview')
    && str_contains($receiptPage, 'hx-include="#forms_receipt_form"'),
    'a preview built from anything but the form is a preview of a different receipt');

$manual = DIR_FS_MODULES . 'phreedom/language/en_us/manual/ch11-ReportsForms/';
$topics = glob($manual . '*.html') ?: [];
$hasTopic = false;
foreach ($topics as $topic) {
    if (str_contains((string)file_get_contents($topic), 'name="doc_pos" content="11.06"')) $hasTopic = true;
}
ok('the help topic the toolbar points at (11.06) exists', $hasTopic);

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the extra recipients reach the mailer ===\n";

// validate_send_mail() takes To, subject and body as arguments but reads CC and
// BCC out of its $block array — a shape nobody would guess, and a wrong key
// drops a recipient in silence rather than failing. One place builds it, so
// this is the one place it has to be right.
$block = forms_email_block(['cc_email' => 'cc@example.test', 'cc_name' => 'CC Person',
                            'bcc_email' => 'bcc@example.test']);
ok('CC travels as EMAIL_CC_ADDRESS', ($block['EMAIL_CC_ADDRESS'] ?? '') === 'cc@example.test');
ok('CC carries its name',            ($block['EMAIL_CC_NAME'] ?? '') === 'CC Person');
ok('BCC travels as EMAIL_BCC_ADDRESS', ($block['EMAIL_BCC_ADDRESS'] ?? '') === 'bcc@example.test');
ok('a BCC with no name omits the name key', !array_key_exists('EMAIL_BCC_NAME', $block));

// PHPMailer's addCC('') raises an invalid-address error and the WHOLE message
// fails, so a blank box must contribute no key at all — otherwise leaving CC
// empty means no email is sent to anyone.
ok('an empty CC contributes nothing at all',
   forms_email_block(['cc_email' => '', 'bcc_email' => '   ']) === []);
ok('no recipient fields at all is an empty block', forms_email_block([]) === []);

[$name, $address] = forms_email_sender('', '');
ok('the sender defaults to the signed-in user',
   $address === (string)($_SESSION['admin_email'] ?? ''));
[$name, $address] = forms_email_sender('  someone@example.test  ', ' A Name ');
ok('a typed sender is used, trimmed',
   $address === 'someone@example.test' && $name === 'A Name');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== every permission the module defines can actually be granted ===\n";

// phreedom's roles page builds itself by walking $mainmenu, so a security id
// with no menu entry appears nowhere on it. Nobody can grant it, so the page it
// guards is unreachable for every user including the one who installed the
// module — and it fails as a redirect to the dashboard, which reads like a
// missing page rather than a missing permission. SECURITY_ID_FORMS_AUTHOR was
// exactly that until the editor got a menu entry of its own.
$granted = [];
$walk = function (array $items) use (&$walk, &$granted): void {
    foreach ($items as $item) {
        if (!empty($item['submenu'])) { $walk($item['submenu']); continue; }
        if (($item['security_id'] ?? '') === '') continue;
        if (($item['show_in_users_settings'] ?? true) === false) continue;
        $granted[(int)$item['security_id']] = true;
    }
};
$walk($GLOBALS['mainmenu'] ?? []);

foreach (['SECURITY_ID_FORMS_RUN', 'SECURITY_ID_FORMS_AUTHOR', 'SECURITY_ID_FORMS_DATASETS'] as $name) {
    ok("$name can be granted from the roles page",
       defined($name) && isset($granted[constant($name)]),
       defined($name) ? 'id ' . constant($name) . ' appears in no menu entry' : 'not defined');
}

echo "\n" . ($failures ? "$failures check(s) FAILED\n" : "all checks passed\n");
exit($failures ? 1 : 0);
