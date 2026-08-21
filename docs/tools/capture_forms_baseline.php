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
//  Path: /docs/tools/capture_forms_baseline.php
//
/**
 * PhreeForm's answers, written down before PhreeForm is deleted. See §13.35.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/capture_forms_baseline.php [company]
 *
 * ## Why this has to happen before, not after
 *
 * Forms was built by comparison. `verify_forms_formatter` holds every value
 * format against `ProcessData()`, every separator against `AddSep()`, every
 * amount in words against `value_to_words_en_us()` — several hundred
 * assertions whose entire content is "the same as the engine we are replacing".
 *
 * Step 6 deletes that engine. It does not just remove a module; it removes the
 * STANDARD everything has been measured against. Afterwards those checks cannot
 * run at all, and the only remaining evidence that a format is right would be
 * that nobody has complained — evidence found by a customer, on a printed
 * invoice, months later.
 *
 * So the answers are captured now, into a file that ships with the repository,
 * and the checks are held to the file instead. What was "Forms agrees with
 * PhreeForm" becomes "Forms produces what PhreeForm produced", which is the
 * same guarantee with the dependency removed.
 *
 * ## What stops the file rotting
 *
 * A golden file is a hostage to whoever regenerates it: run the capture after
 * breaking something and the bug becomes the standard. Two things guard it.
 *
 * First, while PhreeForm is still installed, `verify_forms_formatter` checks
 * the FILE against the live engine as well as checking Forms against the file —
 * three-way, so a stale or doctored baseline is caught by the engine that
 * produced it.
 *
 * Second, this tool refuses to overwrite an existing baseline without
 * `--force`. Recapturing is not a thing to do casually, and after step 6 it is
 * not a thing that can be done at all.
 *
 * ## The environment
 *
 * Some answers depend on the company — DEFAULT_CURRENCY, the currency table,
 * the loaded language. Those cases are recorded separately, with a fingerprint
 * of the environment they were taken in, so a check running elsewhere compares
 * the half that cannot vary rather than reporting differences that are only
 * differences of company.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');

if (!function_exists('ProcessData')) {
    fwrite(STDERR, "PhreeForm is not installed, so there is nothing to capture.\n"
                 . "If it has already been deleted, the baseline in docs/tools/baselines/\n"
                 . "is the only record there will ever be — do not replace it.\n");
    exit(1);
}

$force = in_array('--force', $argv, true);
$path  = __DIR__ . '/baselines/formatter.json';
if (is_file($path) && !$force) {
    fwrite(STDERR, "A baseline already exists at\n  $path\n\n"
        . "Recapturing replaces the record of what the old engine did with what it\n"
        . "does TODAY, and if something has been broken since, the break becomes the\n"
        . "standard. Pass --force only if that is genuinely what you mean.\n");
    exit(1);
}

/**
 * PhreeForm's answer, or null when it does not have one.
 *
 * ProcessData() is not total: `neg` does `-$strData` on a string and `null-dlr`
 * feeds one to number_format(), so a name or a date is a PHP 8 TypeError rather
 * than a value. Those cases are real — they are how the legacy engine behaves
 * on garbage input — and a baseline that omitted them would quietly stop
 * holding Forms to degrading safely where the old engine died. Recorded as
 * null, which the checker reads as "no answer to match, but there must still be
 * no crash".
 */
/**
 * A captured answer, made safe to put in JSON.
 *
 * One hint needs this and it is worth naming: `euro` emits `chr(128)`, which is
 * not valid UTF-8 — it is a byte that means the euro sign in the single-byte
 * encoding the old FPDF fonts used, and PhreeForm's own comment says as much.
 * json_encode() refuses the whole document for it and returns false, which is
 * how the first capture failed: no exception, no message, just a file that did
 * not appear.
 *
 * So a value that is not valid UTF-8 is stored base64 behind a marker. Every
 * other value stays legible, which is most of what a golden file is for.
 */
const B64 = '~b64~';
function encodeAnswer(?string $value): ?string
{
    if ($value === null) return null;
    return preg_match('//u', $value) ? $value : B64 . base64_encode($value);
}

function legacyAnswer(callable $call): ?string
{
    try { return encodeAnswer((string)$call()); } catch (\Throwable) { return null; }
}

$cases = require(__DIR__ . '/formatter_cases.php');

// The posted currency is pinned rather than read: it is per-record in the
// application, so leaving it to the company would make every capture different.
$GLOBALS['posted_currencies'] = ['currencies_code' => 'EUR', 'currencies_value' => 1.225018];

$free = $bound = [];

foreach ($cases['free']['hints'] as $hint) {
    foreach ($cases['free']['values'] as $value) {
        $free['hint|' . $hint . '|' . var_export($value, true)] =
            legacyAnswer(fn() => @ProcessData($value, $hint));
    }
}
foreach ([$cases['bound'], $cases['dates']] as $group)
    foreach ($group['hints'] as $hint) {
    foreach ($group['values'] as $value) {
        $bound['hint|' . $hint . '|' . var_export($value, true)] =
            legacyAnswer(fn() => @ProcessData($value, $hint));
    }
}
foreach ($cases['amounts'] as $amount) {
    $free['n2wrd|' . var_export($amount, true)] =
        legacyAnswer(fn() => value_to_words_en_us($amount));
}
foreach ($cases['separators']['names'] as $name) {
    foreach ($cases['separators']['values'] as $value) {
        $free['sep|' . $name . '|' . var_export($value, true)] =
            legacyAnswer(fn() => AddSep($value, $name));
    }
}
foreach ($cases['codes'] as $hint => $values) {
    foreach ($values as $value) {
        $bound['hint|' . $hint . '|' . var_export($value, true)] =
            legacyAnswer(fn() => @ProcessData($value, $hint));
    }
}
// The chart-of-accounts types are the company's, so the keys are read rather
// than listed — and they go in `bound` for exactly that reason.
require(DIR_FS_MODULES . 'phreebooks/defaults.php');    // $coa_types_list
foreach (array_slice(array_keys($coa_types_list ?? []), 0, 8) as $value) {
    $bound['hint|coa_type|' . var_export($value, true)] =
        legacyAnswer(fn() => @ProcessData($value, 'coa_type'));
}

ksort($free);
ksort($bound);

$baseline = [
    'what'        => 'PhreeForm ProcessData() / AddSep() / value_to_words_en_us(), captured before deletion',
    'environment' => [
        'language' => (string)($_SESSION['language'] ?? ''),
        'currency' => defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
        'posted'   => 'EUR@1.225018',
        'modules'  => array_values(array_intersect(['assets', 'phreepos'], (array)$loaded_modules)),
    ],
    // Deliberately NOT the company name, and not a timestamp. Neither is
    // checkable, and one of them is a private name.
    'free'        => $free,
    'bound'       => $bound,
];

@mkdir(dirname($path), 0777, true);
$json = json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    // Said out loud rather than reported as "could not write": json_encode()
    // returning false for a byte it cannot represent is exactly the failure
    // encodeAnswer() exists to prevent, and it took a while to find once.
    fwrite(STDERR, "The baseline could not be encoded: " . json_last_error_msg() . "\n");
    exit(1);
}
if (file_put_contents($path, $json . "\n") === false) {
    fwrite(STDERR, "Could not write $path\n");
    exit(1);
}

printf("captured %d environment-free and %d environment-bound answer(s)\n", count($free), count($bound));
printf("  language %s, currency %s\n", $baseline['environment']['language'], $baseline['environment']['currency']);
printf("  -> %s\n", $path);
