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
//  Path: /docs/tools/render_forms_report.php
//
/**
 * Render one REPORT through both engines as PDF, for comparison (design §8.5).
 *
 * The reports counterpart of render_forms_document.php. It writes two files —
 * the PhreeForm PDF and the Forms PDF of the same report — so they can be
 * diffed on the host with pdftotext/pdfinfo, or rasterised and compared pixel
 * for pixel with pdftoppm + ImageMagick `compare`.
 *
 * PDF bytes never match between runs: TCPDF regenerates `file_id` and the
 * creation date each time, so a checksum comparison is meaningless. Page count
 * and extracted text are the useful signals, and a pixel diff is the arbiter.
 *
 * Two traps this tool exists to get right, both learned the hard way:
 *
 *  - **The legacy engine consumes the definition as it reads it.** BuildDataArray()
 *    empties `grouplist` with array_shift, and build_criteria() writes resolved
 *    modes back onto the object. Forms therefore gets a clone; the ORIGINAL goes
 *    to the legacy engine, because prefixTables() resolves table names off
 *    `global $report`.
 *  - **`datedefault` is 'c' (Today)** in most stored definitions, which selects
 *    nothing historical. Both engines are given 'a' (All), which is what the
 *    real print button does.
 *
 * Read-only: reports have no printed-counter side effect.
 * NOT part of the application. A dev aid, run inside the container.
 *
 * Usage:
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/render_forms_report.php <report_id> <dataset> [out_dir] [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');

$REPORT_ID = (int)($argv[1] ?? 0);
$DATASET   = (string)($argv[2] ?? '');
$OUT       = (string)($argv[3] ?? '/tmp/forms-report');
if (!$REPORT_ID || $DATASET === '') {
    fwrite(STDERR, "Usage: php render_forms_report.php <report_id> <dataset> [out_dir] [company]\n");
    exit(1);
}
$company = fb_bootstrap($argv[4] ?? null);

require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/render/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/template_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/report_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/report_provider.php');

use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Document\{Context, TypedValue};
use FreeBooks\Forms\Legacy\ReportConverter;
use FreeBooks\Forms\Provider\ReportDocumentProvider;
use FreeBooks\Forms\Render\{Formatter, TablePdfRenderer};

@mkdir($OUT, 0777, true);

$report = get_report_details($REPORT_ID);
if (!$report) { fwrite(STDERR, "Report $REPORT_ID not found.\n"); exit(1); }
echo "company: $company\nreport:  pf_$REPORT_ID (" . ($report->title ?? '?') . ")\n";

// Clone for Forms; the original stays global for the legacy engine. See above.
$formsReport = unserialize(serialize($report));
$report->datedefault = $formsReport->datedefault = 'a';

// ── legacy PDF ───────────────────────────────────────────────────────────────
$built = BuildSQL($report);
if (!is_array($built) || ($built['level'] ?? '') !== 'success') {
    fwrite(STDERR, "BuildSQL failed: " . var_export($built, true) . "\n"); exit(1);
}
$legacyData = BuildDataArray($built['data'], $report);
if ($legacyData === false) { echo "legacy returned no rows; pick a report with data.\n"; exit(1); }
$report->page->filter->text = $built['description'] ?? '';

$legacyOut  = GeneratePDFFile($legacyData, $report, 'S');
$legacyFile = "$OUT/pf{$REPORT_ID}_legacy.pdf";
file_put_contents($legacyFile, $legacyOut['pdf'] ?? '');
printf("legacy:  %s (%d bytes)\n", basename($legacyFile), strlen($legacyOut['pdf'] ?? ''));

// ── Forms PDF ────────────────────────────────────────────────────────────────
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$dataset = $catalog->get($DATASET);

$converter = new ReportConverter();
$columns   = $converter->convertColumns($formsReport, $dataset);
$group     = $converter->convertGroup($formsReport, $dataset);
$sorts     = $converter->convertSorts($formsReport, $dataset);
$filters   = $converter->convertFilters($formsReport, $dataset);
$style     = $converter->convertStyle($formsReport);
$pageSetup = $converter->convertPageSetup($formsReport);

foreach ($converter->warnings() as $w) echo "  WARN $w\n";

// The company name is context, not a global the renderer may read (§2.1).
$context = new Context(values: [
    'COMPANY_NAME' => TypedValue::text(defined('COMPANY_NAME') ? COMPANY_NAME : ''),
]);

$provider = new ReportDocumentProvider(
    db:        $db,
    catalog:   $catalog,
    datasetId: $dataset->id,
    columns:   $columns,
    title:     (string)($formsReport->title ?? 'Report'),
    groupBy:   $group,
    groupFormat: $converter->convertGroupFormat($formsReport),
    filters:   $filters,
    sorts:     $sorts,
    context:   $context,
    page:      $pageSetup,
    filterText: $built['description'] ?? '',
    resolvedColumns: $converter->resolvedColumns(),
    style:     $style,
);
$doc = $provider->build([]);

// The formatter needs the app's currencies object, or money renders as a bare
// number — the seam hands the renderer a raw amount and its type, and this is
// what turns it into "£ 131.76".
global $currencies;
if (!isset($currencies) || !is_object($currencies)) {
    $currencies = new currencies();   // common_classes.php, already loaded by the bootstrap
}
$formatter = new Formatter($currencies, defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '');

// Labels come from the language files, not the renderer: it holds no
// constants of its own so it can be tested without booting the app.
$renderer  = new TablePdfRenderer(
    formatter:        $formatter,
    groupTotalLabel:  defined('TEXT_GROUP_TOTAL_FOR')  ? TEXT_GROUP_TOTAL_FOR  : 'Group Total For:',
    reportTotalLabel: defined('TEXT_REPORT_TOTAL_FOR') ? TEXT_REPORT_TOTAL_FOR : 'Report Total For:',
    pageLabel:        defined('TEXT_PAGE')             ? TEXT_PAGE             : 'Page',
    defaultColumnWidth: defined('PF_DEFAULT_COLUMN_WIDTH') ? (float)PF_DEFAULT_COLUMN_WIDTH : 25.0,
);
$formsOut  = $renderer->render($doc);
$formsFile = "$OUT/pf{$REPORT_ID}_forms.pdf";
file_put_contents($formsFile, $formsOut->bytes);
printf("forms:   %s (%d bytes)\n", basename($formsFile), $formsOut->size());

printf("\nstyle:   heading=%s title1=%s data=%s truncate=%s groupBreak=%s\n",
    $style->heading?->colour ? sprintf('%d:%d:%d', $style->heading->colour->r, $style->heading->colour->g, $style->heading->colour->b) : 'default',
    $style->title1?->colour  ? sprintf('%d:%d:%d', $style->title1->colour->r, $style->title1->colour->g, $style->title1->colour->b)   : 'default',
    $style->data?->align?->value ?? 'default',
    $style->truncate ? 'yes' : 'no',
    $style->groupPageBreak ? 'yes' : 'no');

$rows = count($doc->rows);
printf("rows:    %d in the Forms document\n", $rows);
echo "\nCompare on the host:\n";
echo "  pdfinfo $legacyFile | grep Pages\n";
echo "  pdfinfo $formsFile  | grep Pages\n";
echo "  pdftotext -layout $legacyFile - | head -40\n";
