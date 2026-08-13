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
//  Path: /docs/tools/diff_forms_provider.php
//
/**
 * Semantic diff: a Forms DocumentProvider against a PhreeForm baseline.
 *
 * This is the regression gate from docs/forms-design.md §8 — a port is not
 * finished until its data matches what PhreeForm produced for the same record.
 * It compares the DATA, not the PDF: TCPDF regenerates file_id and the creation
 * date per run, so rendered bytes never match, while wrong values are exactly
 * what must not slip through.
 *
 * The two sides are shaped differently on purpose, and reconciling them is the
 * point. PhreeForm's special_class returned a POSITIONAL array ordered by the
 * template's Data elements; the provider returns values addressed BY NAME. The
 * baseline's element snapshot carries each Data element's boxfield fieldname,
 * so the positional list can be re-keyed and compared like for like.
 *
 * COVERAGE IS BOUNDED TWICE OVER, and both bounds have bitten:
 *
 *   1. By the template. Only fields the form actually binds are compared, so
 *      a provider field no template displays is unverified. pf_91 (Invoice)
 *      has no tax column; pf_67 (Invoice-VAT) binds invoice_line_tax_rate.
 *      Diff against several templates, not one.
 *   2. By the data. A record whose values are all zero cannot reveal a bug in
 *      the arithmetic producing them. Record 349 is a zero-rated EU export, so
 *      a deliberately doubled tax rate still rendered '0%' and passed; record
 *      110 (20% VAT) caught it immediately. Pick records that exercise the
 *      values, not just the fields.
 *
 * Usage (inside the container, which is where the database lives):
 *   docker cp docs/tools/ <container>:/tmp/fbtools/
 *   docker exec <container> php -d display_errors=1 \
 *       /tmp/fbtools/diff_forms_provider.php <report_id> <ids> <baseline_dir> [company]
 *
 * Exits non-zero if any field or line item differs.
 */

define('PAGE_EXECUTION_START_TIME', microtime(true));
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

$REPORT_ID = isset($argv[1]) ? (int)$argv[1] : 0;
$RECORDS   = isset($argv[2]) ? array_values(array_filter(array_map('intval', explode(',', $argv[2])))) : [];
$BASE      = $argv[3] ?? '/tmp/baseline';
if (!$REPORT_ID || !$RECORDS) {
    fwrite(STDERR, "Usage: php diff_forms_provider.php <report_id> <id,id,...> <baseline_dir> [company]\n");
    exit(1);
}

// ─── Bootstrap (see forms-design.md §8.1 trap 4) ──────────────────────────────
require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[4] ?? null);
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');

require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
foreach (['entry', 'statement', 'pos', 'work_order'] as $p) {
    require_once(DIR_FS_MODULES . "forms/classes/providers/{$p}_provider.php");
}

use FreeBooks\Forms\Catalog\{AppFiscalCalendar, DateRange, DateRangeResolver};
use FreeBooks\Forms\Document\{Context, DocumentProvider, Template, TypedValue};
use FreeBooks\Forms\Provider\{
    EntryDocumentProvider, PosDocumentProvider, StatementDocumentProvider, WorkOrderDocumentProvider
};

/**
 * The provider under test, chosen by the special_class the baseline was taken
 * from. Everything is left at its default so the comparison is of data, not of
 * layout: a Template with no elements still yields a full Record.
 *
 * The statement provider is given whatever range the baseline was captured with,
 * resolved through the same DateRangeResolver the application uses, so the two
 * engines are asked the same question.
 */
function forms_range(\queryFactory $db, string $dateDefault): DateRange
{
    return (new DateRangeResolver(new AppFiscalCalendar($db)))->resolve($dateDefault);
}
function provider_for(string $specialClass, \queryFactory $db, string $dateDefault = 'a'): DocumentProvider
{
    return match ($specialClass) {
        'statement_builder' => new StatementDocumentProvider(
            $db, new Template(), forms_range($db, $dateDefault), new Context(), 'Statement'),
        'pos_builder'       => new PosDocumentProvider($db, new Template(), new Context(), 'Receipt'),
        'wo_builder'        => new WorkOrderDocumentProvider($db, new Template(), new Context(), 'Work Order'),
        default             => new EntryDocumentProvider($db, new Template(), new Context(), 'Invoice'),
    };
}

/**
 * Fields to skip, for values PhreeForm only derives at render time.
 * Deliberately EMPTY: the entry provider matches the baseline on every field
 * with no exceptions, and it should stay that way. Adding a name here weakens
 * the gate, so justify it in the commit that does.
 */
const RENDER_TIME_ONLY = [];

/**
 * Values a special_class formatted in the data layer and the provider leaves
 * typed — the seam's whole point (§2.1). These are not skipped: the provider's
 * raw value is put through the same locale formatter the legacy called, and the
 * result must equal the legacy string. A skip would hide a wrong date; this
 * proves the date is right and only its presentation moved.
 */
const LOCALE_DATE = ['post_date'];

/** Loose equality: the legacy returns DB strings, the provider keeps native types. */
function same(mixed $a, mixed $b): bool
{
    if ($a === null) $a = '';
    if ($b === null) $b = '';
    if (is_numeric($a) && is_numeric($b)) return abs((float)$a - (float)$b) < 0.0000001;
    return (string)$a === (string)$b;
}

$totalDiffs = 0;
$vacuous    = 0;
foreach ($RECORDS as $recId) {
    $file = "$BASE/pf{$REPORT_ID}_rec{$recId}.json";
    if (!file_exists($file)) { echo "  rec $recId: no baseline at $file\n"; $totalDiffs++; continue; }
    $base = json_decode(file_get_contents($file), true);

    echo "\n=== record $recId (" . ($base['record_ref'] ?? '?') . ", " . ($base['currency'] ?? '?') . ") ===\n";

    // Re-key the baseline's positional values by their bound fieldname.
    //
    // The three types must match what BuildForm() puts in the positional list,
    // not just 'Data'. wo_builder also sequences ImgLink and BarCode, so taking
    // Data alone left the names one short of the values and every field on the
    // Work Order compared against its neighbour — which reads as six wrong
    // values rather than as one misaligned list.
    $names = [];
    foreach ($base['elements'] as $el) {
        if (in_array($el['type'] ?? '', ['Data', 'BarCode', 'ImgLink'], true)
            && !empty($el['boxfields'][0]['fieldname'])) {
            $names[] = $el['boxfields'][0]['fieldname'];
        }
    }
    $legacyValues = [];
    foreach (array_values($base['field_values']) as $i => $v) {
        if (isset($names[$i])) $legacyValues[$names[$i]] = $v;
    }

    // Legacy line items are keyed r0..rN by column position; map via the Tbl columns.
    $legacyRows = [];
    foreach ($base['elements'] as $el) {
        if (($el['type'] ?? '') !== 'Tbl' || empty($el['table_data'])) continue;
        $cols = array_map(fn($b) => $b['fieldname'] ?? null, $el['boxfields'] ?? []);
        foreach (array_slice($el['table_data'], 1) as $row) {   // row 0 is the headings
            $mapped = [];
            foreach ($cols as $i => $name) {
                if ($name !== null && array_key_exists('r' . $i, $row)) $mapped[$name] = $row['r' . $i];
            }
            $legacyRows[] = $mapped;
        }
        break;
    }

    // Run the provider.
    $provider = provider_for((string)($base['special_class'] ?? ''), $db, (string)($base['datedefault'] ?? 'a'));
    $doc      = $provider->build(['record_ids' => [$recId]]);
    $rec      = $doc->records[0] ?? null;
    if ($rec === null) { echo "  provider returned no record\n"; $totalDiffs++; continue; }

    // ── compare header values ────────────────────────────────────────────────
    $diffs = 0; $checked = 0;
    foreach ($legacyValues as $name => $legacy) {
        if (in_array($name, RENDER_TIME_ONLY, true)) continue;
        $checked++;
        $mine = $rec->value($name);
        if ($mine === null) {
            // A template may bind a header field its special_class never sets —
            // the statements do, for rep_id and terms. PhreeForm returned null
            // for those; a provider that does not invent them agrees.
            if ($legacy === null || $legacy === '') continue;
            printf("  MISSING  %-24s legacy=%s\n", $name, var_export($legacy, true));
            $diffs++;
            continue;
        }
        if (in_array($name, LOCALE_DATE, true) && function_exists('gen_locale_date')
            && !$mine->isEmpty() && same($legacy, gen_locale_date((string)$mine->raw))) {
            continue;   // same date, formatted by the renderer instead of the provider
        }
        if (!same($legacy, $mine->raw)) {
            printf("  DIFF     %-24s legacy=%-24s provider=%s\n",
                $name, var_export($legacy, true), var_export($mine->raw, true));
            $diffs++;
        }
    }

    // ── compare line items ───────────────────────────────────────────────────
    $mineRows = $rec->table('items');
    if (count($legacyRows) !== count($mineRows)) {
        printf("  DIFF     line count            legacy=%d provider=%d\n", count($legacyRows), count($mineRows));
        $diffs++;
    }
    foreach ($legacyRows as $i => $lrow) {
        foreach ($lrow as $name => $legacy) {
            $checked++;
            $mine = $mineRows[$i][$name] ?? null;
            if ($mine === null) {
                // Same equivalence as the header: a table can hold rows of more
                // than one shape. The POS receipt appends payment lines to its
                // item lines, and PhreeForm gave those nulls for every item
                // column; carrying only the keys a row actually has says the same.
                if ($legacy === null || $legacy === '') continue;
                printf("  MISSING  row%-2d %-18s legacy=%s\n", $i, $name, var_export($legacy, true));
                $diffs++;
                continue;
            }
            if (!same($legacy, $mine->raw)) {
                printf("  DIFF     row%-2d %-18s legacy=%-18s provider=%s\n",
                    $i, $name, var_export($legacy, true), var_export($mine->raw, true));
                $diffs++;
            }
        }
    }

    // Zero comparisons is not a pass. A date range that excludes every row of a
    // record leaves the baseline empty, and an empty baseline agrees with any
    // provider at all — the same vacuous green the prefix check once reported
    // over 0 rows (§12.1). Say so instead of printing a tick.
    if ($checked === 0) {
        echo "  VACUOUS  no values in the baseline — this record proves nothing\n";
        $vacuous++;
    }
    printf("  %d checked, %d differ%s\n", $checked, $diffs, ($diffs === 0 && $checked > 0) ? '  ✓' : '');
    $totalDiffs += $diffs;
}

printf("\n%s%s\n",
    $totalDiffs === 0 ? 'PROVIDER MATCHES BASELINE' : "$totalDiffs difference(s)",
    $vacuous ? " ($vacuous record(s) proved nothing)" : '');
exit($totalDiffs === 0 ? 0 : 1);
