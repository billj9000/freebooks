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
 * and AddSep() did (docs/forms-design.md §8, §13.35).
 *
 * Every value in every migrated form passes through one of these hints, so a
 * silent divergence here is a silent divergence on every printed document.
 *
 * ## Past tense, on purpose
 *
 * This used to run both implementations side by side. Step 6 deletes one of
 * them — and deleting PhreeForm does not merely remove a module, it removes the
 * STANDARD this file measures against. So the answers were captured first, into
 * docs/tools/baselines/formatter.json, and Forms is held to the FILE.
 *
 * While the engine is still installed the file is checked against it too, which
 * is what stops a golden file becoming a hostage to whoever last regenerated
 * it: run the capture after breaking something and the bug becomes the
 * standard, unless the engine that produced the file still has to agree with
 * it. Three-way now, two-way after, same guarantee.
 *
 * One matrix drives both, in formatter_cases.php. Two lists that drift apart
 * give a baseline with entries nothing asks for and questions nothing has an
 * answer to, and both failures are quiet.
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
 *
 * Requires a baseline. If there is none, run capture_forms_baseline.php WHILE
 * PHREEFORM IS STILL INSTALLED — afterwards there is nothing to capture from.
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
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

// ─── 1. every value, against the captured baseline ───────────────────────────
//
// Forms was built by comparison against PhreeForm, and step 6 deletes the thing
// it was compared with. So the answers were captured first — into
// docs/tools/baselines/formatter.json, by capture_forms_baseline.php — and this
// section holds Forms to the FILE. "Forms agrees with PhreeForm" becomes "Forms
// produces what PhreeForm produced", which is the same guarantee without the
// dependency (§13.35).
//
// While the engine is still installed, the file is checked against it too. A
// golden file is a hostage to whoever regenerates it, and a three-way
// comparison is what stops a stale or doctored one passing: the engine that
// produced the file has to still agree with it.
//
// One matrix, in formatter_cases.php, shared with the capture. Two lists that
// drift apart give a baseline with entries nothing asks for and questions
// nothing has an answer to, and both failures are quiet.

$cases      = require(__DIR__ . '/formatter_cases.php');
$haveLegacy = function_exists('ProcessData');

$baselinePath = __DIR__ . '/baselines/formatter.json';
$baseline     = is_file($baselinePath)
              ? json_decode((string)file_get_contents($baselinePath), true) : null;

/** The marker capture_forms_baseline.php puts on a value that is not UTF-8. */
const BASE64_MARKER = '~b64~';
function baselineValue(?string $stored): ?string
{
    if ($stored === null) return null;
    return str_starts_with($stored, BASE64_MARKER)
        ? (string)base64_decode(substr($stored, strlen(BASE64_MARKER)))
        : $stored;
}

echo "\n=== every value, against the captured baseline ===\n";

if (!is_array($baseline) || !isset($baseline['free'])) {
    $failures++;
    echo "  [FAIL] no baseline at $baselinePath — capture_forms_baseline.php has\n"
       . "         to be run while PhreeForm is still installed, and it is the only\n"
       . "         record there will be once it is not.\n";
} else {
    // The environment-bound half is only comparable where the environment
    // matches: `def_cur` on a GBP company is not `def_cur` on a USD one, and a
    // difference of company must not read as a difference of behaviour.
    $env     = $baseline['environment'] ?? [];
    $sameEnv = ($env['language'] ?? '') === (string)($_SESSION['language'] ?? '')
            && ($env['currency'] ?? '') === (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '');
    if (!$sameEnv) {
        printf("  [--] captured under language '%s' / currency '%s'; this company is '%s' / '%s',\n"
             . "       so the %d environment-bound answer(s) are not comparable here.\n",
            $env['language'] ?? '?', $env['currency'] ?? '?',
            (string)($_SESSION['language'] ?? ''),
            defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
            count($baseline['bound'] ?? []));
    }

    $stored = $baseline['free'] + ($sameEnv ? ($baseline['bound'] ?? []) : []);

    // Driven by the MATRIX, not by the file's keys. Walking the file would mean
    // reconstructing each value from its printed form, and it would also make a
    // question that has silently dropped out of the matrix look like a question
    // nobody asks. This way a case with no stored answer is reported.
    $asked = [];
    foreach ($cases['free']['hints'] as $hint) {
        foreach ($cases['free']['values'] as $value) $asked[] = ['hint', $hint, $value];
    }
    foreach ([$cases['bound'], $cases['dates']] as $group) {
        foreach ($group['hints'] as $hint) {
            foreach ($group['values'] as $value) $asked[] = ['hint', $hint, $value];
        }
    }
    foreach ($cases['codes'] as $hint => $values) {
        foreach ($values as $value) $asked[] = ['hint', $hint, $value];
    }
    foreach ($cases['amounts'] as $amount) $asked[] = ['n2wrd', 'n2wrd', $amount];
    foreach ($cases['separators']['names'] as $name) {
        foreach ($cases['separators']['values'] as $value) $asked[] = ['sep', $name, $value];
    }

    $matched = $rotted = 0;
    $diffs = $rot = $expected = $survived = $unanswered = [];

    foreach ($asked as [$kind, $hint, $value]) {
        $key = $kind === 'n2wrd'
             ? 'n2wrd|' . var_export($value, true)
             : $kind . '|' . $hint . '|' . var_export($value, true);

        // A hint whose hook ships in a module this company does not have is not
        // a gap in the baseline; it is a question that cannot be asked here.
        if (isset(HINT_OWNER[$hint]) && !in_array(HINT_OWNER[$hint], $loaded_modules, true)) continue;
        if (!array_key_exists($key, $stored)) {
            if (!$sameEnv) continue;              // environment-bound, already reported
            $unanswered[] = $key;
            continue;
        }
        $want = baselineValue($stored[$key]);

        $mine = $kind === 'sep'
            ? $fmt->separator((string)$value, $hint)
            : $fmt->format(new TypedValue($value), $hint === '' ? null : new Format($hint), $recordCurrency);

        // Where the legacy engine CRASHED there is no answer to match, and what
        // matters is that Forms did not crash either — it degrades to passing
        // the value through. Recorded rather than skipped, because "the old one
        // died here" is a fact worth keeping after the old one is gone.
        if ($want === null) {
            $survived[] = sprintf("  %-10s %-18s legacy could not answer; we return %s",
                $hint, var_export($value, true), var_export($mine, true));
            continue;
        }

        $divergence = $hint . '|' . var_export($value, true);
        if ($mine === $want)                            { $matched++; }
        elseif (isset(EXPECTED_DIVERGENCES[$divergence])) {
            $expected[] = sprintf("  %-10s %-18s %s", $hint, var_export($value, true),
                                  EXPECTED_DIVERGENCES[$divergence]);
        } else {
            $diffs[] = sprintf("  %-10s %-18s baseline=%-24s formatter=%s",
                $hint === '' ? '(none)' : $hint, var_export($value, true),
                var_export($want, true), var_export($mine, true));
        }

        // The anti-rot half, while there is something to check it against.
        if (!$haveLegacy) continue;
        try {
            $live = $kind === 'sep'    ? (string)AddSep((string)$value, $hint)
                  : ($kind === 'n2wrd' ? (string)value_to_words_en_us($value)
                                       : (string)@ProcessData($value, $hint));
        } catch (\Throwable) { $live = null; }
        if ($live !== null && $live !== $want) {
            $rotted++;
            if (count($rot) < 4) {
                $rot[] = sprintf("  %-10s %-18s file=%-24s engine=%s",
                    $hint, var_export($value, true), var_export($want, true), var_export($live, true));
            }
        }
    }

    printf("  %d matched, %d unexpected difference(s), %d case(s) the legacy could not answer\n",
        $matched, count($diffs), count($survived));
    foreach ($diffs as $d) echo $d . "\n";
    $failures += count($diffs);

    ok('the baseline covers something', $matched > 0, 'nothing was compared');
    ok('and it has an answer for every case the matrix asks',
       $unanswered === [],
       count($unanswered) . ' unanswered, e.g. ' . implode(', ', array_slice($unanswered, 0, 3)));

    if ($expected) { echo "  -- intended divergences (garbage input) --\n"; foreach ($expected as $e) echo $e . "\n"; }
    if ($survived) { echo "  -- legacy crashed where we degrade safely --\n"; foreach (array_slice($survived, 0, 4) as $f) echo $f . "\n"; }

    if ($haveLegacy) {
        ok('and the baseline still says what the live engine says', $rotted === 0,
           "$rotted answer(s) have drifted:\n" . implode("\n", $rot));
    } else {
        echo "  [--] PhreeForm is gone, so the baseline is the only standard left.\n";
    }
}

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

// ─── 5. the chain: performed -> offered -> labelled ──────────────────────────
//
// The Format control used to be a free-text box, which meant a misspelling and
// a decision looked identical: format() ends in `default => plain()` and this
// class has no extension point, so an unknown name printed the value
// unformatted and said nothing (§13.31). It is a list now, and a list has three
// ways to go quietly wrong that free text did not:
//
//   - a format the class performs but the list does not offer is unreachable;
//   - a format the list offers but the class does not perform does nothing;
//   - an option with no label is a blank row nobody can report.
//
// All three are cheap to prevent and impossible to notice by eye across
// thirty entries, so they are checked rather than reviewed.
echo "\n=== the editors offer exactly what the Formatter performs ===\n";

gen_pull_language('forms');

$armSource = (string)file_get_contents(
    DIR_FS_MODULES . 'forms/classes/render/formatter.php');
$armsOnly = substr($armSource, strpos($armSource, 'return match ($name)'));
$armsOnly = substr($armsOnly, 0, strpos($armsOnly, 'default'));
preg_match_all("/^\s*'([A-Za-z0-9_\-]+)'/m", $armsOnly, $armNames);

// What can be honoured by a format alone: everything performed, less the five
// that need the provider to have fetched an answer first.
$honourable = array_values(array_diff(
    array_unique(array_merge($armNames[1], Formatter::LOOKUP_HINTS)),
    Formatter::RESOLUTION_HINTS));

$offered = [];
foreach (Formatter::CHOOSABLE as $group => $hints) {
    foreach ($hints as $hint) $offered[] = $hint;
}

sort($honourable);
$sortedOffered = $offered;
sort($sortedOffered);

if ($sortedOffered !== $honourable) {
    $missing = array_diff($honourable, $sortedOffered);
    $extra   = array_diff($sortedOffered, $honourable);
    if ($missing) {
        echo "  FAIL performed but not offered: " . implode(', ', $missing)
           . " - no author can reach it\n";
        $failures++;
    }
    if ($extra) {
        echo "  FAIL offered but not performed: " . implode(', ', $extra)
           . " - choosing it would do nothing\n";
        $failures++;
    }
} else {
    echo "  " . count($offered) . " format(s) offered, exactly what can be honoured\n";
}

// A duplicate would put the same entry under two headings, which reads as two
// different formats that happen to share a name.
if (count($offered) !== count(array_unique($offered))) {
    echo "  FAIL a format appears in more than one group: "
       . implode(', ', array_diff_assoc($offered, array_unique($offered))) . "\n";
    $failures++;
}

// The five that are deliberately NOT offered must still be keepable, or a
// converted report loses its format the first time anybody opens and saves it.
$unlabelled = [];
foreach ($offered as $hint) {
    $constant = Formatter::labelConstant($hint);
    if (!defined($constant) || trim((string)constant($constant)) === '') $unlabelled[] = $hint;
}
if ($unlabelled) {
    echo "  FAIL no label defined for: " . implode(', ', $unlabelled)
       . " - each is a blank row in the list\n";
    $failures++;
} else {
    echo "  every offered format has a label\n";
}

foreach (['FORMS_FORMAT_NONE', 'FORMS_FORMAT_OTHER', 'FORMS_FORMAT_GROUP_OTHER'] as $needed) {
    if (!defined($needed)) {
        echo "  FAIL $needed is not defined\n";
        $failures++;
    }
}
foreach (array_keys(Formatter::CHOOSABLE) as $group) {
    $constant = 'FORMS_FORMAT_GROUP_' . strtoupper($group);
    if (!defined($constant)) {
        echo "  FAIL group '$group' has no heading ($constant)\n";
        $failures++;
    }
}

// And the keeping rule itself, which is what stops the list being a trap: a
// name the list does not carry survives being offered alongside it.
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
$kept = forms_format_choices(['rep_id', 'something_a_module_added']);
$keptNames = [];
foreach ($kept as $group) foreach ($group['options'] as $option) $keptNames[] = $option['id'];
foreach (['rep_id', 'something_a_module_added'] as $name) {
    if (!in_array($name, $keptNames, true)) {
        echo "  FAIL '$name' is in use on a document but was not offered back"
           . " - saving would silently clear it\n";
        $failures++;
    }
}
if (in_array('rep_id', $keptNames, true)) {
    echo "  a format already in use is kept even when it is not on offer\n";
}

// ─── 5. the manual documents exactly the formats that exist ──────────────────
//
// The Format box is FREE TEXT, and an unknown name is ignored in silence: the
// value prints unformatted, with no warning and nothing in any log. So the
// manual's list is not a convenience, it is the only place an author can check
// a spelling — which makes a wrong entry in it worse than no entry at all, and
// makes an undocumented format one nobody will ever use.
//
// Both directions are checked. Writing this found `null-dlr` missing from the
// page, which is the one whose name breaks the pattern its neighbours keep
// (a hyphen where null_pcur and null_dcur have underscores) and so the one an
// author is least likely to guess.
echo "\n=== the manual lists the formats the Formatter performs ===\n";

$manualPath = DIR_FS_MODULES
    . 'phreedom/language/en_us/manual/ch11-ReportsForms/08-ValueFormats.html';

if (!is_file($manualPath)) {
    echo "  FAIL the Value Formats topic is missing: $manualPath\n";
    $failures++;
} else {
    $manual = (string)file_get_contents($manualPath);

    // What the class actually performs: every arm of the match, plus the two
    // declared lists it answers before reaching it.
    $source = (string)file_get_contents(
        DIR_FS_MODULES . 'forms/classes/render/formatter.php');
    $arms = substr($source, strpos($source, 'return match ($name)'));
    $arms = substr($arms, 0, strpos($arms, 'default'));
    preg_match_all("/^\s*'([A-Za-z0-9_\-]+)'/m", $arms, $found);
    $performed = $found[1];
    foreach ([Formatter::RESOLUTION_HINTS, Formatter::LOOKUP_HINTS] as $list) {
        foreach ($list as $hint) $performed[] = $hint;
    }
    $performed = array_values(array_unique($performed));

    // A format is documented either as a row of one of the tables or, for the
    // five the renderer deliberately refuses, named in the prose that explains
    // why. Those get no table row on purpose: a row would read as an offer.
    preg_match_all('/<td>(?:<strong>)?([A-Za-z0-9_\-]+)(?:<\/strong>)?<\/td>/', $manual, $rows);
    preg_match_all('/<em>([A-Za-z0-9_\-]+)<\/em>/', $manual, $prose);
    $documented = array_flip(array_merge($rows[1], $prose[1]));

    $undocumented = [];
    foreach ($performed as $name) if (!isset($documented[$name])) $undocumented[] = $name;
    if ($undocumented) {
        echo "  FAIL the manual does not list: " . implode(', ', $undocumented)
           . " - an author has no way to learn a format that is not written down\n";
        $failures++;
    }

    // And the other way. A name in the manual that the class does not perform
    // sends an author to type something that will be ignored in silence, which
    // is the one failure this whole page exists to prevent.
    $known = array_flip($performed);
    $invented = [];
    foreach (array_keys($documented) as $name) {
        // Prose emphasis is also used for ordinary words; only a name that
        // LOOKS like a format is worth holding the manual to.
        if (!preg_match('/^[a-z][a-z0-9]*([_\-][a-z0-9]+)+$/', $name)) continue;
        if (!isset($known[$name])) $invented[] = $name;
    }
    // The page names one misspelling on purpose, to show what one looks like.
    $invented = array_values(array_diff($invented, ['posted_curr']));
    if ($invented) {
        echo "  FAIL the manual lists a format nothing performs: " . implode(', ', $invented)
           . " - typing it would be ignored in silence\n";
        $failures++;
    }

    if (!$undocumented && !$invented) {
        echo "  all " . count($performed) . " format(s) documented, and nothing documented"
           . " that does not exist\n";
    }
}

// ─── 3b. the tables behind the lookup hints ──────────────────────────────────
echo "\n=== every lookup hint has something to look up in ===\n";
//
// THE check this file was missing. A LOOKUP_HINT is a name plus a table, and
// only the name was ever tested: `format()` ends its lookup in `?? $key`, so a
// hint whose table is empty returns the code unchanged and says nothing. Two of
// the three were in that state — offered by the format list, described in the
// manual, and doing nothing whatever.
//
// §13.31 built a chain of performed -> offered -> labelled -> documented. This
// is the link below it: a format can be all four and still be inert.

$lookups = fb_formatter_lookups();
foreach (Formatter::LOOKUP_HINTS as $hint) {
    $count = count($lookups[$hint] ?? []);
    if ($count > 0) {
        printf("  [ok] %-10s %d code(s) to look up\n", $hint, $count);
    } else {
        $failures++;
        printf("  [FAIL] %-10s has no table, so it returns the raw code and says nothing\n", $hint);
    }
}

// And that the tables are the RIGHT ones. Read out of phreedom's locales.xml
// rather than written here, so this cannot drift from what ships — it is the
// same file gen_pull_countries() reads, parsed a second time on purpose.
$expected = [];
foreach (gen_pull_countries()->country ?? [] as $country) {
    $expected[(string)$country->iso3] = [(string)$country->iso2, (string)$country->name];
}
$wrong = 0; $sampled = 0;
foreach (['GBR', 'USA', 'DEU', 'AUS', 'FRA'] as $iso3) {
    if (!isset($expected[$iso3])) continue;
    [$iso2, $name] = $expected[$iso3];
    $sampled++;
    $gotIso2  = $fmt->format(new TypedValue($iso3), new Format('cvt_iso2'), null);
    $gotName  = $fmt->format(new TypedValue($iso3), new Format('cvt_cntry'), null);
    if ($gotIso2 !== $iso2)  { $wrong++; echo "  [FAIL] cvt_iso2 $iso3 -> '$gotIso2', locales.xml says '$iso2'\n"; }
    if ($gotName !== $name)  { $wrong++; echo "  [FAIL] cvt_cntry $iso3 -> '$gotName', locales.xml says '$name'\n"; }
}
if ($wrong === 0 && $sampled > 0) {
    echo "  [ok] cvt_iso2 and cvt_cntry agree with locales.xml on $sampled country/countries\n";
}
$failures += $wrong;
if ($sampled === 0) { $failures++; echo "  [FAIL] no country was sampled, so this proves nothing\n"; }

// An unknown code must come back as ITSELF. A report showing a raw country code
// is recoverable; one showing an empty cell is not.
$unknown = $fmt->format(new TypedValue('ZZZ'), new Format('cvt_cntry'), null);
if ($unknown === 'ZZZ') {
    echo "  [ok] a code the table does not carry prints as itself\n";
} else {
    $failures++;
    echo "  [FAIL] an unknown code printed as '$unknown' rather than as itself\n";
}

echo "\n" . ($failures === 0
    ? "PASS - Formatter matches PhreeForm and the seam holds.\n"
    : "FAIL - $failures problem(s) above.\n");
exit($failures === 0 ? 0 : 1);
