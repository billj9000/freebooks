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
//  Path: /docs/tools/verify_forms_formatter.php
//
/**
 * Prove Forms' Formatter renders values exactly as PhreeForm's ProcessData()
 * and AddSep() do (docs/forms-design.md §8).
 *
 * Every value in every migrated form passes through one of these hints, so a
 * silent divergence here is a silent divergence on every printed document. The
 * tool runs both implementations over the same value/hint grid and fails on any
 * difference that is not explicitly recorded as intended below.
 *
 * It also asserts the seam holds: the five DB-backed hints must be REFUSED by
 * the Formatter, not quietly performed (see formatter.php for why rep_id in
 * particular matters - it reads the `users` table).
 *
 * NOT part of the application. A dev aid, run inside the container.
 *
 * Usage (from the host):
 *   docker cp docs/tools/ <container>:/tmp/fbtools/
 *   docker exec <container> php -d display_errors=1 /tmp/fbtools/verify_forms_formatter.php [company]
 *
 * Exit code 0 = parity holds. 1 = an unexpected divergence; the run prints it.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);

require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/render/formatter.php');

use FreeBooks\Forms\Document\{Currency, Format, TypedValue};
use FreeBooks\Forms\Render\Formatter;

/**
 * Divergences that are deliberate, not defects.
 *
 * All three are numeric hints applied to non-numeric input - nonsense in, and
 * legacy answers with either a hard PHP 8 fatal (neg does `-$strData`, null-dlr
 * feeds a string to number_format) or a nonsense number ('2026-07-14' -> -2026).
 * The Formatter guards with is_numeric and passes the value through instead.
 * Keyed "<hint>|<var_export of value>".
 */
const EXPECTED_DIVERGENCES = [
    "neg|'2026-07-14'"      => "legacy coerces a date to -2026",
    "neg|NULL"              => "legacy coerces null to '0'",
    "null-dlr|'mixed Case Text'" => "legacy blanks a non-numeric; we keep it",
];

/** Hints whose legacy hook ships in a module that may not be installed. */
const HINT_OWNER = ['NewUsed' => 'assets', 'idx' => 'phreepos'];

$fmt = new Formatter($currencies, DEFAULT_CURRENCY, fb_formatter_lookups());

// posted_cur/null_pcur read this global in the legacy path; the Formatter takes
// it as an explicit per-record argument instead. Same values, both sides.
$GLOBALS['posted_currencies'] = ['currencies_code' => 'EUR', 'currencies_value' => 1.225018];
$recordCurrency = new Currency('EUR', 1.225018);

$failures = 0;

// ─── 1. presentation hints ───────────────────────────────────────────────────
$values = [1234.5678, 0, -42.5, '2026-07-14', 'mixed Case Text', '', null, '7.91'];
$hints  = ['', 'blank', 'uc', 'lc', 'neg', 'yesBno', 'printed', 'date', 'rnd2d',
           'dlr', 'null-dlr', 'euro', 'rnd_dec', 'rnd_pre', 'def_cur',
           'null_dcur', 'posted_cur', 'null_pcur', 'terms'];

$matched = 0; $diffs = []; $expected = []; $fatals = [];
foreach ($hints as $hint) {
    foreach ($values as $value) {
        $mine = $fmt->format(new TypedValue($value), $hint === '' ? null : new Format($hint), $recordCurrency);
        $key  = $hint . '|' . var_export($value, true);
        try {
            $legacy = (string)ProcessData($value, $hint);
        } catch (\Throwable $e) {
            $fatals[] = sprintf("  %-10s %-18s legacy FATAL (%s)  ->  we return %s",
                $hint, var_export($value, true), $e->getMessage(), var_export($mine, true));
            continue;
        }
        if ($legacy === $mine)                     { $matched++; continue; }
        if (isset(EXPECTED_DIVERGENCES[$key]))     { $expected[] = sprintf("  %-10s %-18s %s", $hint, var_export($value, true), EXPECTED_DIVERGENCES[$key]); continue; }
        $diffs[] = sprintf("  %-10s %-18s legacy=%-24s formatter=%s",
            $hint === '' ? '(none)' : $hint, var_export($value, true), var_export($legacy, true), var_export($mine, true));
    }
}
echo "\n=== presentation hints vs ProcessData() ===\n";
printf("  %d matched, %d unexpected difference(s)\n", $matched, count($diffs));
foreach ($diffs as $d) echo $d . "\n";
$failures += count($diffs);
if ($expected) { echo "  -- intended divergences (garbage input) --\n"; foreach ($expected as $e) echo $e . "\n"; }
if ($fatals)   { echo "  -- legacy crashes where we degrade safely --\n"; foreach ($fatals as $f) echo $f . "\n"; }

// ─── 2. separators ───────────────────────────────────────────────────────────
echo "\n=== separators vs AddSep() ===\n";
$matched = 0; $diffs = [];
foreach (['sp', '2sp', 'comma', 'com-sp', 'nl', 'semi-sp', 'del-nl', '(unknown)'] as $sep) {
    foreach (['Acme Ltd', ''] as $value) {          // '' proves del-nl collapses the line
        $legacy = (string)AddSep($value, $sep);
        $mine   = $fmt->separator($value, $sep);
        if ($legacy === $mine) { $matched++; continue; }
        $diffs[] = sprintf("  %-10s %-12s legacy=%-18s formatter=%s",
            $sep, var_export($value, true), var_export($legacy, true), var_export($mine, true));
    }
}
printf("  %d matched, %d difference(s)\n", $matched, count($diffs));
foreach ($diffs as $d) echo $d . "\n";
$failures += count($diffs);

// ─── 3. code-to-label hints (no query, so they stay below the seam) ──────────
echo "\n=== lookup hints vs their module hooks ===\n";
require(DIR_FS_MODULES . 'phreebooks/defaults.php');    // $coa_types_list
$cases = [
    'coa_type'  => array_slice(array_keys($coa_types_list), 0, 8),
    'cvt_iso2'  => ['GBR', 'USA', 'DEU', 'ZZZ'],        // ZZZ = unknown -> shows as itself
    'cvt_cntry' => ['GBR', 'USA', 'DEU', 'ZZZ'],
    'j_desc'    => [2, 6, 12, 18, 99],                  // 99 = no such journal
    'NewUsed'   => ['u', 'n', ''],
];
$matched = 0; $diffs = [];
foreach ($cases as $hint => $vals) {
    if (isset(HINT_OWNER[$hint]) && !in_array(HINT_OWNER[$hint], $loaded_modules, true)) {
        printf("  %-10s SKIPPED - module '%s' is not installed here\n", $hint, HINT_OWNER[$hint]);
        continue;
    }
    foreach ($vals as $value) {
        $legacy = (string)ProcessData($value, $hint);
        $mine   = $fmt->format(new TypedValue($value), new Format($hint), null);
        if ($legacy === $mine) { $matched++; continue; }
        $diffs[] = sprintf("  %-10s %-8s legacy=%-30s formatter=%s",
            $hint, var_export($value, true), var_export($legacy, true), var_export($mine, true));
    }
}
printf("  %d matched, %d difference(s)\n", $matched, count($diffs));
foreach ($diffs as $d) echo $d . "\n";
$failures += count($diffs);

// ─── 4. the seam: DB-backed hints must be refused ────────────────────────────
echo "\n=== DB-backed hints are refused, not performed ===\n";
foreach (Formatter::RESOLUTION_HINTS as $hint) {
    $out = $fmt->format(new TypedValue('1'), new Format($hint), null);
    if ($out !== '1') {
        echo "  FAIL $hint resolved to " . var_export($out, true) . " - the renderer queried something\n";
        $failures++;
    }
}
$unresolved = $fmt->unresolvedHints();
sort($unresolved);
$want = Formatter::RESOLUTION_HINTS;
sort($want);
if ($unresolved !== $want) {
    echo "  FAIL unresolvedHints() = [" . implode(', ', $unresolved) . "], expected ["
       . implode(', ', $want) . "]\n";
    $failures++;
} else {
    echo "  all " . count($want) . " passed through and reported: " . implode(', ', $unresolved) . "\n";
}

echo "\n" . ($failures === 0
    ? "PASS - Formatter matches PhreeForm and the seam holds.\n"
    : "FAIL - $failures problem(s) above.\n");
exit($failures === 0 ? 0 : 1);
