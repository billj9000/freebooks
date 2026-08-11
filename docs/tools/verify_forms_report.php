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

$REPORT_ID = isset($argv[1]) ? (int)$argv[1] : 0;
$DATASET   = $argv[2] ?? 'transactions';
if (!$REPORT_ID) {
    fwrite(STDERR, "Usage: php verify_forms_report.php <report_id> <dataset> [company]\n");
    exit(1);
}

fb_bootstrap($argv[3] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/render/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/template_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/report_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/report_provider.php');

use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Document\RowKind;
use FreeBooks\Forms\Legacy\ReportConverter;
use FreeBooks\Forms\Provider\ReportDocumentProvider;
use FreeBooks\Forms\Render\CsvRenderer;

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
// datedefault 'c' (Today) selects nothing historical, exactly as for forms.
$report->datedefault = 'a';
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
$dataset = $catalog->get($DATASET);

$converter = new ReportConverter();
$columns   = $converter->convertColumns($report, $dataset);
$group     = $converter->convertGroup($report, $dataset);
$sorts     = $converter->convertSorts($report, $dataset);
$filters   = $converter->convertFilters($report, $dataset);

printf("forms:   %d columns mapped, group=%s, %d sort(s), %d filter(s)\n",
    count($columns), $group ?? '(none)', count($sorts), count($filters));
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
    title:     (string)($report->title ?? 'Report'),
    groupBy:   $group,
    filters:   $filters,
    sorts:     $sorts,
);
$doc = $provider->build([]);

$formsRows = $formsTotals = 0;
foreach ($doc->rows as $row) {
    if ($row->kind === RowKind::Data) $formsRows++; else $formsTotals++;
}
printf("forms:   %d data rows, %d total rows\n", $formsRows, $formsTotals);

echo "\n=== same data ===\n";
ok('row count matches the legacy engine', $formsRows === $legacyRows,
   "legacy $legacyRows, forms $formsRows");

// Compare the values themselves, normalised: legacy hands back display strings,
// so both sides are reduced to bare digits before comparing numerics.
// Legacy hands back display strings and Forms raw values, so both sides are
// reduced to a comparable form. Only strings that are ENTIRELY a formatted
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
    if ($row->kind !== RowKind::Data) continue;
    $cells = [];
    foreach ($doc->columns as $column) $cells[] = $row->cells[$column->key]?->raw ?? '';
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
ok(sprintf('every row matches (%d legacy-only, %d forms-only)', $onlyLegacy, $onlyForms),
   $onlyLegacy === 0 && $onlyForms === 0);
foreach ($examples as $e) echo "      $e\n";

echo "\n=== better export ===\n";
$csv   = (new CsvRenderer())->render($doc);
$lines = explode("\r\n", $csv->bytes);
$body  = implode("\n", array_slice($lines, 1, 5));

$legacyCsv = GenerateCSVFile($legacyData, $report, 'S')['pdf'] ?? '';
$legacyBody = implode("\n", array_slice(explode("\n", $legacyCsv), 1, 5));

ok('Forms CSV carries no currency symbols',
   !preg_match('/[£$€]/u', $body), 'found a symbol in: ' . substr($body, 0, 120));
ok('Forms CSV carries no thousands separators in numbers',
   !preg_match('/\d,\d{3}\b/', str_replace('","', '', $body)));
$hasLegacyFormatting = (bool)preg_match('/[£$€]/u', $legacyBody) || (bool)preg_match('#\d{2}/\d{2}/\d{4}#', $legacyBody);
ok('legacy CSV did carry display formatting (the defect being fixed)', $hasLegacyFormatting,
   'legacy sample: ' . substr($legacyBody, 0, 120));
ok('Forms CSV dates are ISO', !preg_match('#\d{2}/\d{2}/\d{4}#', $body));
ok('CSV is declared as text/csv', $csv->mimeType === 'text/csv');

echo "\n  -- legacy CSV (first rows) --\n";
foreach (array_slice(explode("\n", $legacyCsv), 0, 4) as $l) echo '    ' . substr($l, 0, 150) . "\n";
echo "  -- Forms CSV (first rows) --\n";
foreach (array_slice($lines, 0, 4) as $l) echo '    ' . substr(str_replace("\xEF\xBB\xBF", '', $l), 0, 150) . "\n";

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
