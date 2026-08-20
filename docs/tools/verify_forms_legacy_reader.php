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
//  Path: /docs/tools/verify_forms_legacy_reader.php
//
/**
 * Forms' own reader against the one it replaces, definition by definition.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_legacy_reader.php [company]
 *
 * ## Why this exists
 *
 * `LegacyReader` took over from `get_report_details()`, and the whole claim of
 * that move is that nothing changed. It is a claim with a short window in which
 * it can be TESTED: only while PhreeForm is still installed is there a second
 * implementation to compare against. Once step 6 deletes it, the only evidence
 * that the reader is right will be that documents still print — which is
 * evidence found by a customer, not by a tool.
 *
 * So the comparison is made now, against every definition both can read.
 *
 * ## Two halves, with different lifetimes
 *
 * **The comparison** is the section that dies with PhreeForm. When
 * `get_report_details()` goes, that section goes with it, and this file keeps
 * only the second half. It should be deleted then rather than left to pass
 * vacuously — a comparison against nothing reports success.
 *
 * **The shape checks** stay. They are built from constructed XML rather than
 * from a company, so they need no PhreeForm, no database and no definitions,
 * and they hold the one property every converter downstream depends on: a list
 * with one entry arrives as an ARRAY, exactly like a list with two.
 *
 * ## Comparing on a migrated company
 *
 * `get_report_details()` reads `pf_<id>` and refuses a native one by design, so
 * on a migrated company — which is every company after step 6 — it can read
 * nothing and the comparison is 0 of 0. The fixture is therefore BUILT: the
 * whole report directory is copied aside, some kept `.pre-forms` originals are
 * put back as `pf_<id>` so both readers can see them, and the directory is
 * restored from a shutdown function so it happens on a fatal as well as on
 * success. Same machinery, and the same reason, as verify_forms_migration.php.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/legacy_reader.php');

use FreeBooks\Forms\Legacy\LegacyReader;

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

/** Two report objects reduced to something comparable and printable. */
function shape(mixed $value): mixed
{
    if (is_object($value)) $value = get_object_vars($value);
    if (!is_array($value)) return $value;
    $out = [];
    foreach ($value as $key => $item) $out[$key] = shape($item);
    return $out;
}

$dir = rtrim(FORMS_DIR_DEFINITIONS, '/\\');
if (!is_dir($dir)) { echo "  [--] no report directory on this company\n"; exit(0); }

// ── the safety net, before anything can change ──────────────────────────────
//
// Through the shared snapshot, which restores OWNERSHIP as well as bytes. A
// tool run with `docker exec` is root, so removing and recopying a company's
// directory leaves every file owned by root — and the next save from the web
// server fails with "the definition could not be written". See
// fb_snapshot_tree().
$snapshot = fb_snapshot_tree($dir);
register_shutdown_function(function () use ($snapshot) {
    fb_restore_tree($snapshot);
    echo "\n  [--] the report directory was restored exactly as it was found\n";
});

// ── the fixture ─────────────────────────────────────────────────────────────
$restored = [];
foreach (glob("$dir/pf_*" . LegacyReader::KEPT) ?: [] as $kept) {
    $id = (int)substr(basename($kept), 3);
    if (!is_file("$dir/pf_$id")) continue;
    $xml = ltrim((string)file_get_contents($kept, false, null, 0, 32));
    if ($xml === '' || $xml[0] === '{') continue;      // no real original
    if (@copy($kept, "$dir/pf_$id")) $restored[] = $id;
}

$ids = [];
$result = $db->Execute("select id from " . TABLE_PHREEFORM . " where doc_type <> '0' order by id");
while ($result && !$result->EOF) { $ids[] = (int)$result->fields['id']; $result->MoveNext(); }

// Whether a PhreeForm original EXISTS here, asked of the contents and not of
// the filename. A `.pre-forms` can hold native JSON — the editors wrote such
// copies before they were guarded — and counting one as an original would turn
// "this company has nothing to compare" into a failure that no change to the
// reader could ever fix.
$anyOriginals = false;
foreach (glob("$dir/pf_*" . LegacyReader::KEPT) ?: [] as $kept) {
    $head = ltrim((string)@file_get_contents($kept, false, null, 0, 32));
    if ($head !== '' && $head[0] !== '{') { $anyOriginals = true; break; }
}
$anyLegacyStored = false;
foreach ($ids as $id) {
    $head = ltrim((string)@file_get_contents("$dir/pf_$id", false, null, 0, 32));
    if ($head !== '' && $head[0] !== '{') { $anyLegacyStored = true; break; }
}

printf("company '%s', %d catalogued definition(s); %d original(s) put back for the comparison\n\n",
    $company, count($ids), count($restored));

// ─────────────────────────────────────────────────────────────────────────────
echo "=== the new reader returns what get_report_details() returned ===\n";

if (!function_exists('get_report_details')) {
    echo "  [--] PhreeForm is gone, so there is nothing left to compare against.\n"
       . "       Delete this section; the shape checks below stand on their own.\n";
} elseif (!$anyOriginals && !$anyLegacyStored) {
    // A company seeded native has never had a PhreeForm original, and that is a
    // fact about the company rather than a fault. Asked of the DIRECTORY, not
    // of what the fixture managed to put back — otherwise a fixture that
    // silently did nothing would land here and be reported as "nothing to
    // check" rather than as the blindness §13.32 is about.
    echo "  [--] this company was seeded native, so it holds no PhreeForm original\n"
       . "       to compare against. Nothing here is being checked.\n";
} else {
    $compared = $differed = 0;
    foreach ($ids as $id) {
        $path     = "$dir/pf_$id";
        $contents = (string)@file_get_contents($path);
        if ($contents === '' || str_starts_with(ltrim($contents), '{')) continue;

        $theirs = get_report_details($id);
        $ours   = LegacyReader::parse($contents);
        if (!is_object($theirs)) continue;      // it refused; nothing to compare

        $compared++;
        if (shape($ours) !== shape($theirs)) {
            $differed++;
            if ($differed <= 3) echo "        pf_$id differs\n";
        }
    }
    ok("both readers agree on every definition ($compared compared)",
       $compared > 0 && $differed === 0,
       $compared === 0 ? 'nothing was comparable, so this proves nothing'
                       : "$differed of $compared differ");
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a list of one is a list ===\n";
//
// The property every converter downstream depends on and none of them checks.
// xml_to_object() collapses a single child to an object, so without this a
// report with one table and a report with two arrive in different shapes — and
// the one-table report converts to nothing, silently.

$one = '<?xml version="1.0"?><PhreeformReport><title>One</title>'
     . '<tables><tablename>journal_main</tablename></tables></PhreeformReport>';
$two = '<?xml version="1.0"?><PhreeformReport><title>Two</title>'
     . '<tables><tablename>journal_main</tablename></tables>'
     . '<tables><tablename>journal_item</tablename></tables></PhreeformReport>';

$parsedOne = LegacyReader::parse($one);
$parsedTwo = LegacyReader::parse($two);

ok('one <tables> parses', is_object($parsedOne));
ok('and arrives as an array of one',
   is_array($parsedOne->tables ?? null) && count($parsedOne->tables) === 1,
   gettype($parsedOne->tables ?? null));
ok('two <tables> arrive as an array of two',
   is_array($parsedTwo->tables ?? null) && count($parsedTwo->tables) === 2,
   gettype($parsedTwo->tables ?? null));
ok('and the container tag is gone from both',
   !isset($parsedOne->PhreeformReport) && !isset($parsedTwo->PhreeformReport));

$box = '<?xml version="1.0"?><PhreeformReport><fieldlist><boxfield>'
     . '<fieldname>a</fieldname></boxfield></fieldlist></PhreeformReport>';
$parsedBox = LegacyReader::parse($box);
ok("a block's single line is an array too",
   is_array($parsedBox->fieldlist[0]->boxfield ?? null)
   && count($parsedBox->fieldlist[0]->boxfield) === 1,
   gettype($parsedBox->fieldlist[0]->boxfield ?? null));

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== what is not a PhreeForm definition comes back as nothing ===\n";
//
// Null, never a string. xml_to_object() hands JSON straight back as text, and
// a caller that passes that on kills the page it is on with "must be of type
// object, string given" — from a file that is perfectly valid and simply not
// this engine's.

ok('a native definition reads as null',
   LegacyReader::parse('{"format":"forms/1","title":"x"}') === null);
ok('an empty file reads as null', LegacyReader::parse('') === null);
ok('whitespace reads as null', LegacyReader::parse("  \n\t ") === null);

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the original is found whichever file is stored ===\n";

$scratch = sys_get_temp_dir() . '/forms-reader-' . getmypid();
@mkdir($scratch, 0777, true);
try {
    $stored = "$scratch/pf_1";

    file_put_contents($stored, $one);
    $direct = LegacyReader::original($stored);
    ok('a stored XML definition is read from the stored file',
       is_object($direct) && ($direct->title ?? '') === 'One');

    file_put_contents($stored, '{"format":"forms/1","title":"migrated"}');
    file_put_contents($stored . LegacyReader::KEPT, $two);
    $kept = LegacyReader::original($stored);
    ok('and once it is migrated, from the original kept beside it',
       is_object($kept) && ($kept->title ?? '') === 'Two');

    // A kept file that is itself NATIVE is not an original. The editors wrote
    // fourteen of those on one company before they were guarded — a copy of an
    // already-native definition, under a name that says PhreeForm XML. Reading
    // one as an original would hand a converter a shape it cannot use, so the
    // reader has to answer "nothing" rather than "this".
    file_put_contents($stored . LegacyReader::KEPT, '{"format":"forms/1","title":"not an original"}');
    ok('a kept file that is itself native is not mistaken for an original',
       LegacyReader::original($stored) === null);

    @unlink($stored . LegacyReader::KEPT);
    ok('with no original there is nothing, rather than a guess',
       LegacyReader::original($stored) === null);
} finally {
    foreach (glob("$scratch/*") ?: [] as $f) @unlink($f);
    @rmdir($scratch);
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
