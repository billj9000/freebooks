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
//  Path: /docs/tools/verify_forms_native.php
//
/**
 * Every stored definition converts to the native format and renders the same
 * bytes through it (design §13.7 step 1).
 *
 * This is the proof the rest of the designer stands on. The native format is
 * only worth having if converting to it changes nothing, and "changes nothing"
 * has to mean the rendered document rather than a plausible-looking object
 * graph — a binding pointed at the wrong key, a filter silently dropped or a
 * style lost all produce a Definition that looks fine and a PDF that is not the
 * one PhreeForm printed.
 *
 * So each definition is run TWICE in one process, against the same database and
 * the same fixtures: once down the legacy path that ships today, and once from
 * a native file written, read back off disk and re-decoded. The claim is that
 * the two PDFs are identical byte for byte.
 *
 * Four things are checked, in order of what they would let through:
 *
 *   1. every definition converts, or is declined for the same reason the legacy
 *      runner declines it — a converter that silently drops one definition is
 *      exactly the §13.3 condition-1 failure the migration must not have;
 *   2. encode -> decode -> encode is byte-stable, so re-saving an unedited
 *      definition produces an empty diff;
 *   3. the loader tells the two formats apart, in both directions;
 *   4. the two paths render identical bytes.
 *
 * Nothing here writes to my_files: the native files go to a scratch directory,
 * because this tool must be safe to run against a live company.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_native.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{DocumentRunner, FormRunner, RunPlan};
use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionBuilder, DefinitionCodec, DefinitionException, DefinitionLoader};
use FreeBooks\Forms\Document\{LayoutDocument, RenderOptions, TableDocument};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer, TablePdfRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

$documents = new DocumentRunner($db, $catalog);
$forms     = new FormRunner($db, $catalog);
$codec     = new DefinitionCodec();

// A scratch directory, NOT my_files. Converting the real tree is the migration
// (step 6), and a verification tool that performed it would be a tool nobody
// could safely run twice.
$scratch = sys_get_temp_dir() . '/forms-native-' . $company . '/';
if (!is_dir($scratch)) mkdir($scratch, 0777, true);
$loader = new DefinitionLoader($catalog, $scratch);
$source = new DefinitionLoader($catalog);          // the real tree, read-only

$formatter = new Formatter(
    currencies:      $currencies,
    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
    lookups:         forms_formatter_lookups(),
);
$tablePdf  = new TablePdfRenderer(formatter: $formatter, defaultColumnWidth: 25.0);
$layoutPdf = new LayoutPdfRenderer(
    formatter:      $formatter,
    defaultFont:    defined('FORMS_DEFAULT_FONT') ? FORMS_DEFAULT_FONT : 'freeserif',
    rowSpacing:     (float)FORMS_DEFAULT_ROWSPACE,
    continuedLabel: defined('TEXT_CONTINUED') ? TEXT_CONTINUED : 'Continued',
    pageOfLabel:    defined('TEXT_OF') ? TEXT_OF : 'of',
    imageDirectory: FORMS_DIR_IMAGES,
  linkedImageDirectory: DIR_FS_MY_FILES . $_SESSION['company'] . '/',
);

/**
 * A PDF with the parts that cannot be equal removed.
 *
 * TCPDF is not a deterministic function of the document. Every render stamps a
 * creation and modification time, a file id derived from them, a RANDOM uuid in
 * the XMP metadata packet as DocumentID and InstanceID, and a `/LastModified`
 * on EVERY PAGE. Rendering the same document twice in the same second still
 * produces different bytes.
 *
 * The per-page one is worth naming because of how it fails. It only differs
 * when the two renders straddle a second boundary, so missing it does not fail
 * the comparison — it fails the comparison for the three or four LARGEST
 * documents in the library and passes the rest, which reads exactly like a real
 * defect in whatever those documents have in common. They had nothing in
 * common except taking more than a second to render.
 *
 * So "byte-identical" here means identical once TCPDF's own nondeterminism is
 * removed, and a normaliser is only trustworthy if it removes ALL of it. That
 * is not an assumption: the control below renders one document twice and
 * requires the two to normalise equal. A normaliser that misses a field makes
 * comparisons fail, and one that strips too much makes them all pass — the
 * control is what tells those apart.
 */
function normalisePdf(string $bytes): string
{
    // Every PDF date string, wherever it appears: the Info dictionary's
    // CreationDate and ModDate, each page's LastModified, and the bare /M on
    // every link annotation. Matched on the `D:` prefix a PDF date always
    // carries, so this cannot eat a /M that means something else.
    $bytes = preg_replace('/\/(?:CreationDate|ModDate|LastModified|M)\s*\(D:[^)]*\)/', '/Date()', $bytes);
    $bytes = preg_replace('/\/ID\s*\[[^\]]*\]/', '/ID[]', $bytes);
    $bytes = preg_replace('/uuid:[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}/i', 'uuid:', $bytes);
    return preg_replace('/<(xmp:(?:Create|Modify|Metadata)Date)>[^<]*<\/\1>/', '<$1/>', $bytes);
}

/**
 * Where two value graphs first differ, or none.
 *
 * NOT serialize(): that encodes object IDENTITY as well as value, so two
 * structurally identical documents compare unequal purely because one of them
 * happens to share a Style instance between elements where the other built two.
 * It reported a difference on 14 definitions that had none.
 *
 * @return list<string>
 */
function differences(mixed $a, mixed $b, string $path = 'document', int $limit = 6): array
{
    if (is_object($a) && is_object($b)) {
        if ($a::class !== $b::class)  return ["$path is " . $a::class . ' vs ' . $b::class];
        if ($a instanceof \UnitEnum)  return $a === $b ? [] : ["$path: different case of " . $a::class];
        return differences((array)$a, (array)$b, $path, $limit);
    }
    // A null where an object was, or the reverse — the shape of the loss an
    // over-eager encoder produces.
    if (is_object($a) !== is_object($b)) {
        return [sprintf('%s is %s vs %s', $path,
            is_object($a) ? $a::class : var_export($a, true),
            is_object($b) ? $b::class : var_export($b, true))];
    }
    if (is_array($a) && is_array($b)) {
        if (array_keys($a) !== array_keys($b)) {
            return ["$path has " . count($a) . ' entries vs ' . count($b)];
        }
        $found = [];
        foreach ($a as $key => $value) {
            $found = array_merge($found, differences($value, $b[$key], "$path.$key", $limit));
            if (count($found) >= $limit) break;
        }
        return array_slice($found, 0, $limit);
    }
    return $a === $b ? [] : ["$path: " . var_export($a, true) . ' vs ' . var_export($b, true)];
}

/**
 * Records this form could print, found from the definition's own filters.
 *
 * The same approach as verify_forms_forms.php, and for the same reason: a
 * hard-coded fixture id that no longer exists is a tool that reports a green
 * nothing. Several candidates are tried because some filters live on a joined
 * table this query does not reach.
 *
 * @return list<string>
 */
function findRecords(\queryFactory $db, object $report, string $table, string $key, int $limit = 8): array
{
    if ($table === '' || !preg_match('/^[a-z_][a-z0-9_]*$/i', $key)) return [];
    $qualified = (defined('DB_PREFIX') ? DB_PREFIX : '') . $table;

    // Every column the record table actually has: a filter name may be
    // UNQUALIFIED and belong to another table entirely, and queryFactory
    // answers a SQL error with die() — so an unchecked column name is a tool
    // that exits silently, with status 0, having printed only its header.
    $columns = [];
    $meta = $db->Execute("select column_name from information_schema.columns
        where table_schema = database() and table_name = '" . $db->prepare_input($qualified) . "'");
    while ($meta && !$meta->EOF) {
        $columns[strtolower((string)$meta->fields['column_name'])] = true;
        $meta->MoveNext();
    }
    if (!isset($columns[strtolower($key)])) return [];

    $where = [];
    foreach ((array)($report->filterlist ?? []) as $filter) {
        $name = strtolower((string)($filter->fieldname ?? ''));
        $min  = (string)($filter->min_val ?? '');
        if ($name === '' || $min === '') continue;
        $column = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $column)) continue;
        if (str_contains($name, '.') && !str_starts_with($name, $table . '.')) continue;
        if (!isset($columns[$column])) continue;
        // A legacy filter value may be a LIST — "2, 18, 19" — and comparing a
        // column to that string makes MySQL cast it to 2.
        if (str_contains($min, ',')) {
            $values = array_filter(array_map('trim', explode(',', $min)), fn($v) => $v !== '');
            if ($values === []) continue;
            $where[] = "`$column` in (" . implode(',', array_map(
                fn($v) => "'" . $db->prepare_input($v) . "'", $values)) . ")";
            continue;
        }
        $where[] = "`$column` = '" . $db->prepare_input($min) . "'";
    }

    $ids    = [];
    $result = $db->Execute("select distinct `$key` from `$qualified`"
        . ($where ? ' where ' . implode(' and ', $where) : '')
        . " order by `$key` desc limit " . (int)$limit);
    while ($result && !$result->EOF) {
        $value = (string)$result->fields[$key];
        if ($value !== '') $ids[] = $value;
        $result->MoveNext();
    }
    return $ids;
}

// The run dialog's answers, standing in for a user: the reconciliation reads
// nothing without both.
$period = (int)($db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
    . " where start_date <= '" . date('Y-m-d') . "' and end_date >= '" . date('Y-m-d') . "'")
    ->fields['period'] ?? 1);
$glAccount = (string)($db->Execute("select i.gl_account from " . TABLE_JOURNAL_ITEM . " i
    inner join " . TABLE_CHART_OF_ACCOUNTS . " c on c.id = i.gl_account
    where c.account_type = 0 group by i.gl_account order by count(*) desc limit 1")
    ->fields['gl_account'] ?? '');

$catalogRows = [];
$result = $db->Execute("select id, doc_title, doc_ext from " . TABLE_PHREEFORM
    . " where doc_type <> '0' order by id");
while (!$result->EOF) {
    $catalogRows[(int)$result->fields['id']] = [
        'title' => (string)$result->fields['doc_title'],
        'form'  => (string)$result->fields['doc_ext'] === 'frm',
    ];
    $result->MoveNext();
}
printf("company '%s', %d definition(s), scratch %s\n", $company, count($catalogRows), $scratch);
printf("run dialog: period %d, gl account '%s'\n\n", $period, $glAccount);

// ─────────────────────────────────────────────────────────────────────────────
echo "=== every definition converts, or is declined for the same reason ===\n";

$alreadyNative = [];    // id => true, stored natively already: nothing to compare
$converted = [];        // id => Definition, read back off disk
$declined  = [];        // id => why
$warned    = [];        // id => list<string>
$stable    = $sniffed = 0;

foreach ($catalogRows as $id => $row) {
    // A definition the report editor has already saved is NATIVE on disk, so
    // there is no legacy side to convert or compare against. That is a normal
    // state for a partly-migrated company, not a failure, and calling it "could
    // not be read" — which is what the legacy reader's refusal looks like from
    // here — would send someone looking for a corrupt file.
    // A definition stored natively with NO original kept has no legacy side at
    // all — a company seeded native, which is every fresh install. One with an
    // original kept beside it is a MIGRATED definition, and that original is
    // exactly what this tool exists to compare against, so it is read.
    $legacy = legacyOriginal($source, $id);
    if ($legacy === null) {
        $stored = (string)@file_get_contents($source->path($id));
        if ($stored !== '' && DefinitionLoader::isNative($stored)) { $alreadyNative[$id] = true; continue; }
        $declined[$id] = 'the stored file could not be read';
        continue;
    }

    // What the SHIPPING code makes of it, so a conversion failure can be told
    // apart from a definition Forms already declines.
    $legacyPlan = $row['form']
        ? $forms->plan($legacy)
        : $documents->plan($legacy);

    $builder = new DefinitionBuilder($catalog);
    try {
        $definition = $builder->fromLegacy($legacy, (string)$id);
    } catch (DefinitionException $e) {
        // Declining is only acceptable where the legacy runner declines too.
        ok("pf_$id ({$row['title']}) is declined by both paths or by neither",
            !$legacyPlan->runnable(), 'the legacy runner would have run it: ' . $e->getMessage());
        $declined[$id] = $e->getMessage();
        continue;
    }
    if ($builder->warnings()) $warned[$id] = $builder->warnings();

    // (2) Re-encoding what was decoded must reproduce the file exactly, or a
    // designer that opens and closes a definition without editing it leaves a
    // diff behind.
    $json  = $codec->encode($definition);
    $again = $codec->encode($codec->decode($json, (string)$id));
    if ($json !== $again) {
        ok("pf_$id ({$row['title']}) re-encodes to the same bytes", false,
            strlen($json) . ' vs ' . strlen($again) . ' bytes');
        continue;
    }
    $stable++;

    // (3) Written, sniffed and read back through the loader — the path the
    // application will take, not just the codec in memory.
    $loader->save($definition, $id);
    $onDisk = (string)file_get_contents($loader->path($id));
    if (!DefinitionLoader::isNative($onDisk)) {
        ok("pf_$id ({$row['title']}) is stored in the native format", false, substr($onDisk, 0, 40));
        continue;
    }
    $converted[$id] = $loader->load($id);
    if ($loader->wasLegacy()) {
        ok("pf_$id ({$row['title']}) loads as native, not as legacy", false);
        continue;
    }
    $sniffed++;
}

ok(sprintf('%d of %d definition(s) convert', count($converted),
        count($catalogRows) - count($alreadyNative)),
    count($converted) + count($declined) + count($alreadyNative) === count($catalogRows));
if ($alreadyNative) {
    printf("  [--] %d definition(s) are already stored natively; nothing to compare for those:\n         %s\n",
        count($alreadyNative), implode(', ', array_map(fn($k) => "pf_$k", array_keys($alreadyNative))));
}
ok('re-encoding is byte-stable for every one of them', $stable === count($converted),
    "$stable of " . count($converted));
ok('the loader reads every one of them back as native', $sniffed === count($converted),
    "$sniffed of " . count($converted));

// The sniff must DISCRIMINATE, which is what the loader's branch depends on.
//
// This used to say "the stored files still sniff as legacy", which was true
// while every company held XML and became false the moment one was migrated —
// asserting a state step 6 exists to end. What has to hold either way is
// narrower and permanent: whatever file the comparison above read as the
// PhreeForm original sniffs LEGACY, and whatever is stored sniffs as whatever
// it actually is. A sniff that stopped discriminating would send the loader
// down the wrong branch, and it would do it silently.
$legacySniffs = $storedSniffs = $originals = 0;
foreach (array_keys($converted) as $id) {
    $path   = $source->path($id);
    $stored = (string)@file_get_contents($path);
    $native = DefinitionLoader::isNative($stored);

    // A migrated definition is native on disk with its original beside it; an
    // un-migrated one is the original.
    $original = $native ? (string)@file_get_contents($path . '.pre-forms') : $stored;
    if ($original !== '') {
        $originals++;
        if (!DefinitionLoader::isNative($original)) $legacySniffs++;
    }

    // json_decode is the second opinion: a file that sniffs native must BE
    // JSON, and one that does not must not be. The sniff reads one byte, so
    // agreeing with a real parse is the whole of its correctness.
    if ($native === (json_decode($stored) !== null)) $storedSniffs++;
}
ok('every PhreeForm original sniffs as legacy', $legacySniffs === $originals,
    "$legacySniffs of $originals");
ok('and every stored file sniffs as what it actually is',
    $storedSniffs === count($converted), "$storedSniffs of " . count($converted));

if ($declined) {
    echo "\n  declined (" . count($declined) . "):\n";
    foreach ($declined as $id => $why) echo "    pf_$id: $why\n";
}
if ($warned) {
    echo "\n  converted with warnings (" . count($warned) . ") — §13.3 condition 1 blocks on these:\n";
    foreach ($warned as $id => $notes) {
        foreach ($notes as $note) echo "    pf_$id: $note\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the byte comparison below means something ===\n";

// Rendering the same document twice must normalise to the same bytes. Without
// this the section that follows proves nothing in either direction: an
// incomplete normaliser fails every definition, and an over-eager one passes
// every definition including the broken ones.
$control = null;
foreach ($converted as $id => $definition) {
    if ($catalogRows[$id]['form'] || $definition->hasProvider()) continue;
    try {
        $control = $documents->runDefinition($definition,
            ['dates' => 'a', 'period' => $period, 'gl_account' => $glAccount]);
    } catch (\Throwable) { continue; }
    if ($control instanceof TableDocument && count($control->rows) > 0) break;
    $control = null;
}
if ($control === null) {
    // No report returned a row, so there is nothing to calibrate against and
    // nothing below to calibrate FOR. That is a property of the company — a
    // fresh install with no transactions posted — not a defect in anything this
    // tool tests, and calling it a failure on every empty company is how a
    // suite teaches people to skim past its failures.
    //
    // It is still said, loudly, because the alternative is a run that reports
    // success having compared nothing.
    $uncalibrated = true;
    echo "  [--] no report on this company returned a row, so the byte comparison
"
       . "       below has nothing to calibrate against and nothing to compare.
"
       . "       Post a transaction, or run this against a company that has one.
";
} else {
    $uncalibrated = false;
    // A full second BETWEEN the two renders, deliberately. Back-to-back they
    // usually land in the same second, which leaves the per-page /LastModified
    // stamps equal and lets an incomplete normaliser pass this control while
    // failing every document large enough to take a second to render.
    $first = $tablePdf->render($control)->bytes;
    usleep(1_100_000);
    ok('the same document rendered a second apart normalises to the same bytes',
        normalisePdf($first) === normalisePdf($tablePdf->render($control)->bytes));
    // And the normaliser must not be so broad that it hides a real change.
    // One extra row is a difference any honest comparison has to see.
    $changed = new TableDocument(
        title: $control->title . ' (control)', columns: $control->columns,
        rows: $control->rows, context: $control->context, page: $control->page,
        filterText: $control->filterText, style: $control->style);
    ok('a document that really differs still compares different',
        normalisePdf($tablePdf->render($control)->bytes)
            !== normalisePdf($tablePdf->render($changed)->bytes));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the native path renders the bytes the legacy path renders ===\n";

$compared = $identical = 0;
$skipped  = [];   // never rendered: no record to print on this company
$thin     = [];   // rendered and compared, but the report returned no rows

foreach ($converted as $id => $definition) {
    $title  = $catalogRows[$id]['title'];
    $legacy = legacyOriginal($source, $id);
    if ($legacy === null) continue;

    try {
        if ($catalogRows[$id]['form']) {
            $plan = $forms->plan($legacy);
            if (!$plan->runnable()) { $skipped[] = "pf_$id ($title): " . implode(' ', $plan->notes); continue; }

            $ids = findRecords($db, $legacy, $plan->recordTable, $plan->recordKey);
            if ($ids === []) {
                $skipped[] = "pf_$id ($title): no row in {$plan->recordTable}.{$plan->recordKey}";
                continue;
            }

            // The same record for both paths, and the first that yields one.
            $params  = ['dates' => (string)($legacy->datedefault ?? 'a')];
            $before  = $after = null;
            foreach ($ids as $candidate) {
                $built = $forms->run($legacy, [$candidate], $params);
                if ($built instanceof LayoutDocument && count($built->records) > 0) {
                    $before = $built;
                    $after  = $forms->runDefinition($definition, [$candidate], $params);
                    break;
                }
            }
            if ($before === null) {
                $skipped[] = sprintf('pf_%d (%s): none of %d candidate record(s) built a document',
                    $id, $title, count($ids));
                continue;
            }
            $options = new RenderOptions(filename: "pf$id.pdf");
            $legacyBytes = $layoutPdf->render($before, $options)->bytes;
            $nativeBytes = $layoutPdf->render($after,  $options)->bytes;
        } else {
            $plan = $documents->plan($legacy);
            if (!$plan->runnable()) { $skipped[] = "pf_$id ($title): " . implode(' ', $plan->notes); continue; }

            $params = ['dates' => 'a', 'period' => $period, 'gl_account' => $glAccount];
            $before = $documents->run($legacy, $params);
            $after  = $documents->runDefinition($definition, $params);

            // An empty report still renders its page furniture, so it is
            // compared — it would catch a heading band that moved — but it is
            // named separately, because a section reporting "56 of 56
            // identical" where a third of them were blank pages is a section
            // that overstates what it checked.
            if ($before instanceof TableDocument && count($before->rows) === 0) {
                $thin[] = "pf_$id ($title)";
            }
            $legacyBytes = $tablePdf->render($before)->bytes;
            $nativeBytes = $tablePdf->render($after)->bytes;
        }
    } catch (\Throwable $e) {
        ok("pf_$id ($title) runs down both paths", false, get_class($e) . ': ' . $e->getMessage());
        continue;
    }

    $compared++;
    if (normalisePdf($legacyBytes) === normalisePdf($nativeBytes)) { $identical++; continue; }

    // Say WHERE, not just that: a byte difference with no document difference
    // is a renderer that is not a function of its document, and the two want
    // opposite investigations.
    $where = differences($before, $after);
    ok("pf_$id ($title) renders identical bytes", false, sprintf('legacy %d bytes, native %d; %s',
        strlen($legacyBytes), strlen($nativeBytes),
        $where ? 'documents differ at ' . implode('; ', $where)
               : 'the DOCUMENTS match — the difference is in the renderer or the normaliser'));
}

// Nothing compared is a failure ONLY when something could have been. With no
// data there was never a comparison to make, and the line above has already
// said so in words.
if ($compared === 0 && $uncalibrated) {
    echo "  [--] 0 compared, for the reason given above
";
} else
ok(sprintf('%d of %d rendered definition(s) are byte-identical%s', $identical, $compared,
        $thin ? sprintf(' (%d of them empty on this company)', count($thin)) : ''),
    $compared > 0 && $identical === $compared);

if ($thin) {
    echo "\n  compared, but returned no rows here — the page furniture only ("
        . count($thin) . "):\n    " . implode(', ', $thin) . "\n";
}
if ($skipped) {
    echo "\n  never rendered (" . count($skipped) . "), data coverage rather than defects:\n";
    foreach ($skipped as $note) echo "    $note\n";
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the format refuses what it cannot read ===\n";

$refuses = function (string $what, string $json) use ($codec): void {
    try {
        $codec->decode($json);
        ok($what, false, 'it was accepted');
    } catch (DefinitionException) {
        ok($what, true);
    }
};

$refuses('a file that is not a Forms definition', '{"format":"something-else","version":1}');
$refuses('a definition from a later format version',
    json_encode(['format' => DefinitionCodec::FORMAT, 'version' => DefinitionCodec::VERSION + 1]));
$refuses('an unknown element type', json_encode([
    'format' => DefinitionCodec::FORMAT, 'version' => DefinitionCodec::VERSION, 'type' => 'frm',
    'form' => ['elements' => [['type' => 'hologram', 'box' => ['x' => 0, 'y' => 0]]]],
]));
$refuses('an unknown binding scope', json_encode([
    'format' => DefinitionCodec::FORMAT, 'version' => DefinitionCodec::VERSION, 'type' => 'frm',
    'form' => ['elements' => [['type' => 'field', 'box' => ['x' => 0, 'y' => 0],
        'bind' => ['name' => 'x', 'scope' => 'anywhere']]]],
]));
$refuses('an unknown filter operator', json_encode([
    'format' => DefinitionCodec::FORMAT, 'version' => DefinitionCodec::VERSION, 'type' => 'rpt',
    'filters' => [['field' => 'x', 'operator' => 'sounds_like', 'values' => ['y']]],
]));
// A filter expression is the one place a field key reaches SQL, so the
// identifier check has to survive the round trip through the file.
$refuses('a filter expression naming something that is not an identifier', json_encode([
    'format' => DefinitionCodec::FORMAT, 'version' => DefinitionCodec::VERSION, 'type' => 'rpt',
    'filters' => [['field' => 'x', 'operator' => 'lt',
        'expression' => [['kind' => 'field', 'text' => 'a`) or 1=1 -- ']]]],
]));

echo "\n" . ($failures ? "$failures check(s) FAILED\n" : "all checks passed\n");
exit($failures ? 1 : 0);
