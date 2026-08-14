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
//  Path: /docs/tools/verify_report_page_widths.php
//
/**
 * Every shipped report definition fits the page it declares.
 *
 * A definition names its column widths and its paper, and nothing has ever
 * checked that the first fits the second. PhreeForm let the columns run past the
 * right margin and TCPDF clipped whatever fell outside — so a report wider than
 * its page lost the tail of its last column, silently, on every print. It was
 * found because a three-letter currency code lost its third letter, which is
 * obvious in a way a truncated customer name is not.
 *
 * Forms turns such a report landscape at render time, which fixes every existing
 * installation without touching stored data. This check is about the other half:
 * the definitions SHIPPED in the repository, which seed new installations, so
 * that a fresh install is right rather than merely rescued.
 *
 * It reads the XML directly — no database, no company, no module bootstrap —
 * because these files are the artefact being checked. Per-company copies under
 * my_files are deliberately NOT read: they are the customer's, and several
 * legitimately differ.
 *
 * The install now asks which paper to seed (PF_DEFAULT_PAPERSIZE), so the
 * question is no longer "do these fit?" but "do these fit on the paper this
 * install chose?". Pass a paper name to check that one; pass none to check each
 * definition on the paper it declares, and to print the comparison.
 *
 *     php docs/tools/verify_report_page_widths.php          # as declared, plus comparison
 *     php docs/tools/verify_report_page_widths.php A4       # as if seeded onto A4
 */

// Paper the definitions use, in mm, portrait. Stored in each file as
// "Letter:216:282", but the numbers there disagree with reality often enough
// (Letter is 215.9 x 279.4) that the sizes are taken from here instead.
const PAPER = [
    'Letter'  => [215.9, 279.4],
    'Legal'   => [215.9, 355.6],
    'Tabloid' => [279.4, 431.8],
    'A0' => [841.0, 1189.0], 'A1' => [594.0, 841.0], 'A2' => [420.0, 594.0],
    'A3' => [297.0, 420.0],  'A4' => [210.0, 297.0], 'A5' => [148.0, 210.0],
    'A6' => [105.0, 148.0],  'A7' => [74.0, 105.0],  'A8' => [52.0, 74.0],
    'A9' => [37.0, 52.0],
];

/** PhreeForm's PF_DEFAULT_COLUMN_WIDTH, for a column that declares none. */
const DEFAULT_COLUMN_WIDTH = 25.0;

$roots = [__DIR__ . '/../../trunk/modules', __DIR__ . '/../../extensions/Modules'];
$files = [];
foreach ($roots as $root) {
    foreach (glob($root . '/*/language/en_us/reports/*.xml') ?: [] as $file) $files[] = $file;
}
sort($files);
if (!$files) { fwrite(STDERR, "No report definitions found under " . implode(', ', $roots) . "\n"); exit(1); }

$cdata = static function (string $xml, string $tag): ?string {
    return preg_match('#<' . $tag . '><!\[CDATA\[(.*?)\]\]></' . $tag . '>#s', $xml, $m) ? $m[1] : null;
};

// The paper to test against, standing in for the install's PF_DEFAULT_PAPERSIZE.
// Omitted means "whatever each definition declares", which is how the shipped
// files are actually stored.
$override = $argv[1] ?? '';
if ($override !== '' && !isset(PAPER[$override])) {
    fwrite(STDERR, "Unknown paper '$override'. Known: " . implode(', ', array_keys(PAPER)) . "\n");
    exit(1);
}
if ($override !== '') echo "Checking as if every report were seeded onto $override.\n\n";

$failures = $checked = $skipped = $scaled = 0;
$landscape = 0;
/** @var array<string,array{fit:int,total:int}> widths per paper, for the comparison */
$comparison = [];

foreach ($files as $file) {
    $xml  = (string)file_get_contents($file);
    $name = basename($file);

    // Forms only: a form positions its elements absolutely and has no columns.
    if (($cdata($xml, 'reporttype') ?? '') !== 'rpt') { $skipped++; continue; }

    $size        = $cdata($xml, 'size') ?? 'A4';
    $paper       = $override !== '' ? $override : explode(':', $size)[0];
    $orientation = strtoupper($cdata($xml, 'orientation') ?? 'P');
    if (!isset(PAPER[$paper])) {
        printf("  [--] %-40s unknown paper '%s'\n", $name, $paper);
        $skipped++;
        continue;
    }

    // Margins live inside <page><margin>; take the first of each.
    $left  = (float)(preg_match('#<left><!\[CDATA\[([\d.]+)#',  $xml, $m) ? $m[1] : 10);
    $right = (float)(preg_match('#<right><!\[CDATA\[([\d.]+)#', $xml, $m) ? $m[1] : 10);

    [$shortSide, $longSide] = PAPER[$paper];
    $pageWidth = $orientation === 'L' ? $longSide : $shortSide;
    $printable = $pageWidth - $left - $right;

    // The same band arithmetic the renderer uses: columns stack downward within
    // a band, and a column flagged columnbreak closes it. A band is as wide as
    // its widest column, so bands are summed and columns within one are not.
    preg_match_all('#<fieldlist>(.*?)</fieldlist>#s', $xml, $fields);
    $edges = [0.0];
    $band  = 1;
    foreach ($fields[1] as $field) {
        if (($cdata($field, 'visible') ?? '0') === '0') continue;
        $width = (float)($cdata($field, 'columnwidth') ?? DEFAULT_COLUMN_WIDTH);
        if ($width <= 0) $width = DEFAULT_COLUMN_WIDTH;
        $edges[$band] = max($edges[$band] ?? 0.0, $edges[$band - 1] + $width);
        if (($cdata($field, 'columnbreak') ?? '0') !== '0') $band++;
    }
    if (!isset($edges[$band])) $edges[$band] = $edges[$band - 1] + DEFAULT_COLUMN_WIDTH;
    $needed = (float)end($edges);

    $checked++;
    if ($orientation === 'L') $landscape++;

    // Same arithmetic against every paper we know, so the choice the installer
    // offers can be judged on measurements rather than on intuition.
    foreach (PAPER as $candidate => [$candShort, $candLong]) {
        $candWidth = ($orientation === 'L' ? $candLong : $candShort) - $left - $right;
        $comparison[$candidate]['total'] = ($comparison[$candidate]['total'] ?? 0) + 1;
        $comparison[$candidate]['fit']   = ($comparison[$candidate]['fit'] ?? 0)
                                         + ($needed <= $candWidth + 0.01 ? 1 : 0);
    }

    if ($needed <= $printable + 0.01) continue;

    // Two different situations, and only one is a defect in the definition.
    if ($needed <= ($longSide - $left - $right) + 0.01) {
        // It would fit if the paper were turned, and the definition can say so.
        $failures++;
        printf("  [FAIL] %-38s %5.0fmm of %5.0fmm portrait — set <orientation>L</orientation>\n",
            $name, $needed, $printable);
    } else {
        // No orientation of this paper holds it. The renderer scales such a
        // table to fit rather than clipping it, which is the agreed last
        // resort, so this is reported rather than failed — but it is reported,
        // because a report needing more paper than exists is worth knowing
        // about and is fixed by a column width or a bigger sheet.
        $scaled++;
        printf("  [--]   %-38s %5.0fmm of %5.0fmm landscape — scaled to %.0f%% at render time\n",
            $name, $needed, $printable, 100 * $printable / $needed);
    }
}

printf("\n%d report definition(s) checked (%d landscape), %d skipped as forms,"
     . " %d scaled at render time.\n", $checked, $landscape, $skipped, $scaled);

// The installer's choice, measured. Only the sizes a real install would pick.
echo "\nHow many of the $checked reports fit, by seeded paper:\n";
foreach (['Letter', 'Legal', 'A4', 'A3'] as $candidate) {
    if (!isset($comparison[$candidate])) continue;
    printf("  %-8s %2d of %2d\n", $candidate, $comparison[$candidate]['fit'], $comparison[$candidate]['total']);
}
echo $failures === 0
    ? "PASS - no shipped report is portrait where landscape would fit it.\n"
    : "FAIL - $failures definition(s) would be clipped on print.\n";
exit($failures === 0 ? 0 : 1);
