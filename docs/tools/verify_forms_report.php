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
//  Path: /docs/tools/verify_forms_report.php
//
/**
 * Run one PhreeForm REPORT through both engines and compare (design §8.5).
 *
 * Two different claims are checked, because they need different evidence.
 *
 * 1. **Same data.** The row count and the value of every cell must agree with
 *    the legacy engine. This is a straight comparison after normalising
 *    formatting, and any difference is a defect.
 *
 * 2. **Better export.** The CSV must NOT agree, in a specific and intended way.
 *    PhreeForm formatted values in the data layer, so a money column reached the
 *    CSV writer as "$ 1,234.50" and a date as "08/10/2026" — unusable by an
 *    importer, which is why a readable report and an exportable one had to be
 *    maintained separately (§1.2). Forms carries typed values and lets each
 *    renderer decide, so the same report exports machine-readable numbers and
 *    ISO dates. The tool asserts that difference exists rather than treating it
 *    as a failure.
 *
 * NOT part of the application. A dev aid, run inside the container.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /tmp/fbtools/verify_forms_report.php <report_id> <dataset> [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');

// --dates keeps the definition's stored date preference on BOTH sides, so the
// range itself is what is under test; --dates=<prefs> forces one instead, which
// is how a range that actually selects rows gets exercised on a report whose
// stored preference is 'c' (Today). Without either, the preference is forced to
// 'a', because a report that returns nothing compares equal to another report
// that returns nothing.
$DATEPREFS = null;
$WITHDATES = false;
$args      = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dates')                { $WITHDATES = true; continue; }
    if (str_starts_with($arg, '--dates=')) { $WITHDATES = true; $DATEPREFS = substr($arg, 8); continue; }
    $args[] = $arg;
}

$REPORT_ID = isset($args[0]) ? (int)$args[0] : 0;
$DATASET   = $args[1] ?? 'transactions';
if (!$REPORT_ID) {
    fwrite(STDERR, "Usage: php verify_forms_report.php <report_id> <dataset> [company] [--dates]\n");
    exit(1);
}

fb_bootstrap($args[2] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/render/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/template_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/report_provider.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/report_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/financial_provider.php');

use FreeBooks\Forms\Catalog\{AppFiscalCalendar, Catalog, DateRangeResolver, ShippedDatasets};
use FreeBooks\Forms\Document\{Row, RowKind, TableDocument, ValueType};
use FreeBooks\Forms\Legacy\ReportConverter;
use FreeBooks\Forms\Provider\{BalanceSheetDocumentProvider, IncomeStatementDocumentProvider, ReportDocumentProvider};
use FreeBooks\Forms\Render\{CsvRenderer, Formatter};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$report = get_report_details($REPORT_ID);
if (!$report) { fwrite(STDERR, "Report $REPORT_ID not found.\n"); exit(1); }
echo "report:  pf_$REPORT_ID (" . ($report->title ?? '?') . ")\n";

// ── the legacy engine ────────────────────────────────────────────────────────
// Give each engine its own copy. The legacy engine CONSUMES the definition as
// it reads it: BuildDataArray() finds the default group with
// `while ($t = array_shift($report->grouplist))`, and build_criteria() writes
// each filter's resolved mode back onto the shared object. Handing the same
// $report to both engines afterwards showed Forms a report with no grouping at
// all, which reads as a Forms bug and is not one. Anything comparing the two
// must clone, and a caller in the application must never reuse a definition
// object across a render.
//
// The clone goes to FORMS, not to the legacy engine: prefixTables() resolves
// table names off `global $report`, so the object the legacy engine mutates has
// to stay the one in the global scope.
$formsReport = unserialize(serialize($report));

// The accounting period is supplied by the run dialog, not by the definition.
// Without it the financial statements build `where period = ` and the legacy
// engine dies on the SQL error before returning anything at all.
$period = $db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
    . " where start_date <= '" . date('Y-m-d') . "' and end_date >= '" . date('Y-m-d') . "'")
    ->fields['period'] ?? null;
if ($period === null) {
    $period = $db->Execute("select max(period) as period from " . TABLE_ACCOUNTING_PERIODS)->fields['period'] ?? 1;
}
$report->period = $formsReport->period = (int)$period;

// datedefault 'c' (Today) selects nothing historical, exactly as for forms.
if (!$WITHDATES)             $report->datedefault = $formsReport->datedefault = 'a';
if ($DATEPREFS !== null)     $report->datedefault = $formsReport->datedefault = $DATEPREFS;
printf("dates:   preference '%s' on %s\n", $report->datedefault, $report->datefield ?? '(no field)');
// BuildSQL returns ['level'=>..,'data'=>$sql,'description'=>..], not a string.
$built = BuildSQL($report);
if (!is_array($built) || ($built['level'] ?? '') !== 'success') {
    fwrite(STDERR, "BuildSQL failed: " . var_export($built, true) . "\n");
    exit(1);
}
$legacySql  = $built['data'];
$legacyData = BuildDataArray($legacySql, $report);
if ($legacyData === false) { echo "legacy returned no rows; pick a report with data.\n"; exit(1); }

$legacyRows = $legacyTotals = 0;
foreach ($legacyData as $row) {
    $kind = explode(':', (string)$row[0])[0];
    if ($kind === 'd') $legacyRows++; else $legacyTotals++;
}
printf("legacy:  %d data rows, %d total rows, %d columns\n", $legacyRows, $legacyTotals, count($GLOBALS['Heading'] ?? []));

// ── Forms ────────────────────────────────────────────────────────────────────
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

// A STRUCTURAL report has no dataset: its rows are sections in a fixed order,
// not a query result, so it is built by a provider and compared row for row.
// Pass '-' as the dataset name for these.
$STRUCTURAL = [
    'is_budget'     => IncomeStatementDocumentProvider::class,
    'balance_sheet' => BalanceSheetDocumentProvider::class,
];
$special     = (string)($formsReport->special_class ?? '');
$compareKind = null;
if (isset($STRUCTURAL[$special])) {
    $converter = new ReportConverter();
    $columns   = $converter->convertStructuralColumns($formsReport);
    $period    = (int)($formsReport->period ?? 0);
    printf("forms:   %d structural columns, %s, period %d\n", count($columns), $special, $period);
    foreach ($columns as $c) echo "  column:  {$c->key}  ({$c->heading})\n";

    $class    = $STRUCTURAL[$special];
    $provider = new $class(
        db:      $db,
        columns: $columns,
        period:  $period,
        title:   (string)($formsReport->title ?? 'Report'),
    );
    $doc         = $provider->build([]);
    $compareKind = RowKind::Data;
    $converterHasTotalsOnly = false;
} else {
$dataset = $catalog->get($DATASET);

$converter = new ReportConverter();
$columns   = $converter->convertColumns($formsReport, $dataset);
$group     = $converter->convertGroup($formsReport, $dataset);
$sorts     = $converter->convertSorts($formsReport, $dataset);
$filters   = $converter->convertFilters($formsReport, $dataset);

// The date range is resolved from the same preference string the legacy engine
// read, against the same accounting calendar, and appended as ordinary filters.
$range   = (new DateRangeResolver(new AppFiscalCalendar($db)))->resolve((string)$formsReport->datedefault);
$filters = array_merge($filters, $converter->convertDateFilters($formsReport, $dataset, $range));

printf("forms:   %d columns mapped, group=%s, %d sort(s), %d filter(s)%s\n",
    count($columns), $group ?? '(none)', count($sorts), count($filters),
    $converter->convertTotalsOnly($formsReport) ? ', totals only' : '');
foreach ($converter->warnings() as $w)       echo "  WARN $w\n";
foreach ($converter->unmappedFields() as $u) echo "  UNMAPPED $u\n";
foreach ($filters as $f) echo "  filter:  {$f->fieldKey} {$f->operator->value} " . json_encode($f->values) . "\n";
foreach ($columns as $c) echo "  column:  {$c->key}" . ($c->total ? ' (total)' : '') . "\n";
echo "  legacy SQL: " . substr($legacySql, 0, 220) . "\n";

$provider = new ReportDocumentProvider(
    db:        $db,
    catalog:   $catalog,
    datasetId: $dataset->id,
    columns:   $columns,
    title:     (string)($formsReport->title ?? 'Report'),
    groupBy:   $group,
    filters:   $filters,
    sorts:     $sorts,
    resolvedColumns: $converter->resolvedColumns(),
    totalsOnly: $converter->convertTotalsOnly($formsReport),
);
$doc = $provider->build([]);
}

// A totals-only report has no data rows by construction: the roll-up its
// special_class did in PHP is a group break with the rows beneath hidden. So
// the legacy's DATA rows are the Forms GROUP TOTAL rows, and comparing data to
// data would report every row missing while the output is exactly right.
$compareKind = $compareKind ?? ($converter->convertTotalsOnly($formsReport) ? RowKind::GroupTotal : RowKind::Data);

$formsRows = $formsTotals = 0;
foreach ($doc->rows as $row) {
    if ($row->kind === $compareKind) $formsRows++;
    elseif ($row->kind !== RowKind::Data) $formsTotals++;
}
printf("forms:   %d data rows, %d total rows\n", $formsRows, $formsTotals);

echo "\n=== same data ===\n";
ok('row count matches the legacy engine', $formsRows === $legacyRows,
   "legacy $legacyRows, forms $formsRows");

// Group breaks are checked separately from cell values: a report can have every
// data row right and still break its groups in the wrong places, which shows up
// as a repeated heading and a total that covers only part of its group.
$legacyGroupLabels = [];
foreach ($legacyData as $row) {
    if (str_starts_with((string)$row[0], 'g:')) $legacyGroupLabels[] = substr((string)$row[0], 2);
}
// Read the group field off the clone: the legacy engine has array_shift()ed
// the original's grouplist away by now.
$legacyGroupNames = [];
foreach ((array)($formsReport->grouplist ?? []) as $entry) {
    $def = is_array($entry->default ?? null) ? reset($entry->default) : ($entry->default ?? '');
    if (!$def || $def === '0') continue;
    $name = is_array($entry->fieldname ?? null) ? reset($entry->fieldname) : ($entry->fieldname ?? '');
    $legacyGroupNames[] = (string)$name;
}
$formsGroupLabels = [];
foreach ($doc->rows as $row) {
    if ($row->kind === RowKind::GroupTotal) $formsGroupLabels[] = (string)$row->label;
}
// PhreeForm could only group by a column the report DISPLAYS. BuildDataArray()
// sets its break key inside `if ($DataFields->visible)`, so when the author
// grouped by a hidden field — Customer Ledger groups by bill_acct_id, which is
// not in its field list at all — $GrpField stayed '', $myrow[''] was null on
// every row, nothing ever broke, and the report printed a single lump total
// labelled with an empty string. Forms selects the group field whether or not
// it is shown, so those reports group as their author intended.
//
// That is a divergence in Forms' favour, so it is asserted rather than failed —
// but only where legacy provably could not group, and only if the data rows
// still match exactly.
$legacyCanGroup = false;
foreach ($legacyGroupNames as $name) {
    foreach ((array)($formsReport->fieldlist ?? []) as $field) {
        $fname = is_array($field->fieldname ?? null) ? reset($field->fieldname) : ($field->fieldname ?? '');
        $vis   = is_array($field->visible ?? null)   ? reset($field->visible)   : ($field->visible ?? '');
        if ((string)$fname === $name && $vis && $vis !== '0') $legacyCanGroup = true;
    }
}

if ($legacyGroupNames === []) {
    // No default group at all — and PhreeForm still emits one group row.
    // BuildDataArray() writes `$GrpField = ''` and then gates the whole break
    // mechanism on `isset($GrpField)`, which is true of a variable set to the
    // empty string. So $GrpWorking goes from false to null on the first row and
    // never changes, no break ever fires, and the closing `if ($GrpWorking !==
    // false)` emits one group total for the entire report — a second copy of
    // the report total, labelled with nothing, printed directly above it.
    echo "  note: report has no default group; legacy emits one anyway.\n";
    // A structural report emits no totals at all: its subtotals are ordinary
    // rows with a caption, so there is no ungrouped-total defect to observe.
    if (isset($STRUCTURAL[$special])) {
        ok('structural report: legacy emits no group rows', count($legacyGroupLabels) === 0,
           'legacy emitted ' . count($legacyGroupLabels));
    } else {
    ok('legacy emitted its spurious ungrouped total row', count($legacyGroupLabels) === 1,
       'legacy emitted ' . count($legacyGroupLabels));
    ok('legacy\'s spurious row is unlabelled', ($legacyGroupLabels[0] ?? 'x') === '',
       'label was ' . var_export($legacyGroupLabels[0] ?? null, true));
    }
    ok('Forms emits no group total when nothing is grouped',
       $formsGroupLabels === [] || $compareKind === RowKind::GroupTotal,
       'forms emitted ' . count($formsGroupLabels));
} elseif (!$legacyCanGroup) {
    echo "  note: legacy grouped by '" . implode(', ', $legacyGroupNames)
       . "', which it does not display — so it could not group at all.\n";
    ok('legacy produced its degenerate single total', count($legacyGroupLabels) <= 1,
       'legacy emitted ' . count($legacyGroupLabels) . ' groups');
    // "More groups than legacy managed" rather than "more than one": with a
    // date range applied the surviving rows can genuinely belong to a single
    // customer or vendor, and one group is then the right answer rather than a
    // repeat of legacy's failure.
    ok(sprintf('Forms groups the report as its author intended (%d groups)', count($formsGroupLabels)),
       count($formsGroupLabels) >= max(1, count($legacyGroupLabels)),
       'forms emitted ' . count($formsGroupLabels) . ', legacy ' . count($legacyGroupLabels));
} else {
    ok(sprintf('group breaks match (%d legacy, %d forms)', count($legacyGroupLabels), count($formsGroupLabels)),
       count($legacyGroupLabels) === count($formsGroupLabels));
}
$dupes = array_filter(array_count_values($formsGroupLabels), fn($n) => $n > 1);
ok('no group heading is emitted twice', $dupes === [],
   'repeated: ' . implode(', ', array_map(fn($k) => "'$k'", array_keys($dupes))));

// Compare the values themselves — but the two engines are not comparable raw.
// PhreeForm formatted during BuildDataArray(), so a `j_desc` column arrives as
// "Sales/Invoice Journal (12)"; Forms carries the 12 and lets each renderer
// decide (§4.3.1). Comparing raw against display would report all 647 rows as
// different when nothing is wrong, so the Forms side is put through the same
// Formatter a PDF renderer would use, with the column's own hint. That is the
// honest test: it asks whether the two engines PRINT the same thing.
$formatter = new Formatter(
    currencies:      $currencies,
    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
    lookups:         fb_formatter_lookups(),
);

// Both sides are then normalised. Only strings that are ENTIRELY a formatted
// number or date are rewritten — stripping punctuation from everything turns
// the SKU "WIDGET-301" into "301" and hides real differences behind fake ones.
$normalise = function (string $v): string {
    $t = trim($v);
    if ($t === '' || $t === '0000-00-00') return '';

    // A money-ish string: optional symbol, digits, separators, optional sign.
    if (preg_match('/^[\p{Sc}\s]*-?[\d,]+(\.\d+)?[\s]*$/u', $t)
     || preg_match('/^-?[\d,]+(\.\d+)?$/', $t)) {
        $n = (float)str_replace([',', ' '], '', preg_replace('/[\p{Sc}]/u', '', $t));
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
    // A date in either engine's form.
    if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $t) || preg_match('/^\d{4}-\d{2}-\d{2}/', $t)) {
        $time = strtotime(str_contains($t, '/') ? str_replace('/', '-', $t) : $t);
        if ($time !== false) return date('Y-m-d', $time);
    }
    return strtolower($t);
};

// Neither engine defines a row order unless the report sets a default sort, so
// the comparison is of MULTISETS: every row must appear the same number of
// times on both sides. Comparing positionally would report thousands of
// differences from ordering alone and hide any real one among them.
$legacyDataRows = array_values(array_filter($legacyData, fn($r) => explode(':', (string)$r[0])[0] === 'd'));

$fingerprint = function (array $cells) use ($normalise): string {
    return implode('|', array_map(fn($c) => $normalise((string)$c), $cells));
};

$legacyBag = [];
foreach ($legacyDataRows as $row) {
    $key = $fingerprint(array_values(array_slice($row, 1)));
    $legacyBag[$key] = ($legacyBag[$key] ?? 0) + 1;
}

$formsBag = [];
foreach ($doc->rows as $row) {
    if ($row->kind !== $compareKind) continue;
    $cells = [];
    foreach ($doc->columns as $column) {
        $value   = $row->cells[$column->key] ?? null;
        $cells[] = $value === null ? '' : $formatter->format($value, $column->format);
    }
    $key = $fingerprint($cells);
    $formsBag[$key] = ($formsBag[$key] ?? 0) + 1;
}

$onlyLegacy = $onlyForms = 0;
$examples = [];
foreach ($legacyBag as $key => $n) {
    $mine = $formsBag[$key] ?? 0;
    if ($mine < $n) {
        $onlyLegacy += $n - $mine;
        if (count($examples) < 4) $examples[] = "legacy-only x" . ($n - $mine) . ": " . substr($key, 0, 110);
    }
}
foreach ($formsBag as $key => $n) {
    $theirs = $legacyBag[$key] ?? 0;
    if ($n > $theirs) {
        $onlyForms += $n - $theirs;
        if (count($examples) < 8) $examples[] = "forms-only  x" . ($n - $theirs) . ": " . substr($key, 0, 110);
    }
}
// The aged reports are expected to differ from BOTH legacy classes, and only in
// one way: a credit memo now reads negative. So the assertion is not "identical"
// but the stronger, more specific "identical up to sign" — no row may appear or
// vanish and no magnitude may change, which a blanket skip would not have shown.
const CREDIT_MEMO_SIGN_REPORTS = [46, 47, 48, 49];
$signOnly = false;
if (($onlyLegacy || $onlyForms) && in_array($REPORT_ID, CREDIT_MEMO_SIGN_REPORTS, true)) {
    $unsign = fn(string $key) => implode('|', array_map(
        fn(string $c) => is_numeric($c) ? (string)abs((float)$c) : $c, explode('|', $key)));
    $l = $f = [];
    foreach ($legacyBag as $k => $n) { $u = $unsign($k); $l[$u] = ($l[$u] ?? 0) + $n; }
    foreach ($formsBag  as $k => $n) { $u = $unsign($k); $f[$u] = ($f[$u] ?? 0) + $n; }
    $signOnly = ($l == $f);
}

if ($signOnly) {
    ok(sprintf('rows differ from legacy by sign alone (%d row(s)), as the credit-memo'
             . ' correction intends', max($onlyLegacy, $onlyForms)), true);
} else {
    ok(sprintf('every row matches (%d legacy-only, %d forms-only)', $onlyLegacy, $onlyForms),
       $onlyLegacy === 0 && $onlyForms === 0);
}
foreach ($examples as $e) echo "      $e\n";

echo "\n=== better export ===\n";
$csv   = (new CsvRenderer())->render($doc);
$lines = explode("\r\n", $csv->bytes);
$body  = implode("\n", array_slice($lines, 1, 5));

$legacyCsv = GenerateCSVFile($legacyData, $report, 'S')['pdf'] ?? '';
$legacyBody = implode("\n", array_slice(explode("\n", $legacyCsv), 1, 5));

ok('Forms CSV carries no currency symbols',
   !preg_match('/[£$€]/u', $body), 'found a symbol in: ' . substr($body, 0, 120));
// Field by field, not line by line: on an unquoted row the delimiter between
// two numeric columns (…,100,216.77) looks exactly like a thousands separator.
$grouped = [];
foreach (array_slice($lines, 1, 5) as $line) {
    foreach (str_getcsv($line) as $cell) {
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', trim($cell))) $grouped[] = $cell;
    }
}
ok('Forms CSV carries no thousands separators in numbers', $grouped === [],
   'grouped: ' . implode(', ', $grouped));
// Only meaningful where the report HAS something to mis-format. Chart of
// Accounts is all codes and names, so both engines emit the same CSV and the
// absence of a difference is not evidence of anything.
$formattedColumns = 0;
foreach ($columns as $column) {
    if ($column->format !== null || in_array($column->key, ['post_date','item_net','item_net_debit'], true)) $formattedColumns++;
}
$hasMoneyOrDate = false;
foreach ((array)($formsReport->fieldlist ?? []) as $field) {
    $p = is_array($field->processing ?? null) ? reset($field->processing) : ($field->processing ?? '');
    if (in_array((string)$p, ['date','def_cur','pcur','null_pcur','null_dlr','dlr'], true)) $hasMoneyOrDate = true;
}
// Scan the WHOLE legacy CSV, not the five-row sample: `null_pcur` renders an
// empty amount as blank, so the Audit Log's only money column shows nothing at
// all until a row that has one.
$legacyHasFormatting = (bool)preg_match('/[£$€]/u', $legacyCsv) || (bool)preg_match('#\d{2}/\d{2}/\d{4}#', $legacyCsv);
if (!$hasMoneyOrDate) {
    echo "  [--] report has no money or date column; nothing for legacy to mis-format\n";
} elseif (!$legacyHasFormatting) {
    echo "  [--] report's money/date columns are empty throughout; no formatting to compare\n";
} else {
    // The paired claim, over both files in full: legacy carried presentation
    // into the export and Forms does not. Asserting only half of it would pass
    // on a report that simply has no money in it.
    // Check the Forms side cell by cell, and only cells the dataset TYPES as
    // money or date. Scanning the whole file flags a free-text column that
    // happens to contain a date — the Inventory Aging descriptions do — and
    // reports it as the export defect surviving.
    $offenders = [];
    foreach ($doc->rows as $row) {
        foreach ($doc->columns as $column) {
            $value = $row->cells[$column->key] ?? null;
            if ($value === null) continue;
            if (!in_array($value->type, [ValueType::Money, ValueType::Decimal, ValueType::Date], true)) continue;
            $cell = (new CsvRenderer())->render(new TableDocument(
                title: 'x', columns: [$column], rows: [new Row(RowKind::Data, [$column->key => $value])]));
            if (preg_match('/[£$€]/u', $cell->bytes) || preg_match('#\d{2}/\d{2}/\d{4}#', $cell->bytes)) {
                $offenders[] = "{$column->key}=" . var_export($value->raw, true);
            }
        }
        if (count($offenders) > 3) break;
    }
    ok('legacy CSV carried display formatting and the Forms CSV strips it',
       $legacyHasFormatting && $offenders === [],
       'legacy=' . var_export($legacyHasFormatting, true) . ' forms offenders: ' . implode(', ', $offenders));
}
ok('Forms CSV dates are ISO', !preg_match('#\d{2}/\d{2}/\d{4}#', $body));
ok('CSV is declared as text/csv', $csv->mimeType === 'text/csv');

echo "\n  -- legacy CSV (first rows) --\n";
foreach (array_slice(explode("\n", $legacyCsv), 0, 4) as $l) echo '    ' . substr($l, 0, 150) . "\n";
echo "  -- Forms CSV (first rows) --\n";
foreach (array_slice($lines, 0, 4) as $l) echo '    ' . substr(str_replace("\xEF\xBB\xBF", '', $l), 0, 150) . "\n";

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
