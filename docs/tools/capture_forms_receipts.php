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
//  Path: /docs/tools/capture_forms_receipts.php
//
/**
 * What each till receipt printed on the day PhreeForm was still here. §13.35.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/capture_forms_receipts.php [company] [--force]
 *
 * ## Why this is not the other baseline
 *
 * `capture_forms_baseline.php` writes a table of pure functions over invented
 * inputs, so it ships in the repository and works on any company. A receipt
 * baseline cannot: it is a RENDERED SALE — customer, SKUs, prices, taxes — and
 *
 *   - everything in this repository is public, and
 *   - a sale on one company means nothing on another.
 *
 * So it is written beside the company's own data, under
 * `my_files/<company>/forms-baselines/`, which is not in version control. One
 * per installation, captured once, on the last day it can be captured at all.
 *
 * ## What it is for
 *
 * `verify_forms_serial` compares Forms' SerialRenderer against PhreeForm's
 * BuildSeq() byte for byte. That is the only check that means anything about
 * the receipt — the one document Forms renders as text rather than as a page —
 * and it dies with the engine it compares against. Afterwards the question
 * becomes "does the till still print what it printed then", which this answers
 * and nothing else can.
 *
 * ## What is recorded besides the bytes
 *
 * The RECORD IDS, because the check must ask about the same sales rather than
 * whichever five are newest — on a live company that set changes daily, and a
 * baseline for records nobody asks about is no baseline.
 *
 * And a FINGERPRINT of each receipt's layout. A receipt that has since been
 * edited should print differently, and the check has to be able to tell that
 * apart from a renderer that has broken.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Definition\DefinitionLoader;
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Legacy\LegacyReader;

if (!function_exists('BuildForm')) {
    fwrite(STDERR, "PhreeForm is not installed, so there is nothing left to capture.\n"
                 . "Whatever is already in my_files/$company/forms-baselines/ is the only\n"
                 . "record there will ever be of what the till used to print.\n");
    exit(1);
}

$force = in_array('--force', $argv, true);
$dir   = rtrim(DIR_FS_MY_FILES, '/\\') . '/' . $company . '/forms-baselines';
$path  = $dir . '/receipts.json';
if (is_file($path) && !$force) {
    fwrite(STDERR, "A receipt baseline already exists at\n  $path\n\n"
        . "Recapturing replaces the record of what the till used to print with what it\n"
        . "prints today. Pass --force only if that is genuinely what you mean.\n");
    exit(1);
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);
$loader = new DefinitionLoader($catalog);

/** Values that are not valid UTF-8 are stored base64; the rest stay legible. */
const B64 = '~b64~';
function encodeBytes(string $value): string
{
    return preg_match('//u', $value) ? $value : B64 . base64_encode($value);
}

/** The shape of a receipt's layout, so an edit can be told from a regression. */
function layoutFingerprint(?object $template): string
{
    if ($template === null) return '';
    $cells = array_map(fn($f) => sprintf('%s:%d:%s:%d:%d',
        (new ReflectionClass($f->element))->getShortName(),
        $f->cell->width, $f->cell->align->value,
        (int)$f->cell->rowBreak, (int)$f->cell->wholeLine), $template->fields);
    return md5(implode('|', $cells));
}

// The POS sales to print. Newest first and capped, matching what
// verify_forms_serial has always chosen — but WRITTEN DOWN here, so the check
// keeps asking about these five rather than about whichever five are newest.
$records = [];
$rows = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . "
    where journal_id = 19 order by id desc limit 5");
while ($rows && !$rows->EOF) { $records[] = (int)$rows->fields['id']; $rows->MoveNext(); }

if ($records === []) {
    fwrite(STDERR, "No point-of-sale entry on this company, so no receipt can be printed.\n");
    exit(1);
}

$captured = [];
$layouts  = [];
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM . " where doc_type <> '0' order by id");
while ($result && !$result->EOF) {
    $id    = (int)$result->fields['id'];
    $title = (string)$result->fields['doc_title'];
    $result->MoveNext();

    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    if ($definition->serialTemplate === null) continue;          // not a receipt

    // Whichever file still holds the PhreeForm original: the stored one until
    // it has been saved in the Forms editor, the kept copy afterwards.
    $stored = (string)@file_get_contents($loader->path($id));
    $xml    = DefinitionLoader::isNative($stored)
            ? (string)@file_get_contents($loader->path($id) . LegacyReader::KEPT)
            : $stored;
    if (LegacyReader::parse($xml) === null) {
        echo "  [--] pf_$id ($title): no PhreeForm original to print from\n";
        continue;
    }

    $layouts['pf_' . $id] = layoutFingerprint($definition->serialTemplate);

    foreach ($records as $recordId) {
        // The legacy engine CONSUMES the object it is given — BuildDataArray()
        // and build_criteria() write back onto it — so it is rebuilt per record.
        $report = LegacyReader::parse($xml);
        $filter = new stdClass();
        $filter->fieldname = 'journal_main.id';
        $filter->default   = 'EQUAL';
        $filter->min_val   = (string)$recordId;
        $filter->type      = '';
        $report->xfilterlist = [$filter];
        $report->datedefault = 'a';
        // Never move the printed counter. A form carrying setprintedfield makes
        // BuildForm() fire "UPDATE journal_main SET printed = printed + 1" per
        // record, so a tool that only meant to LOOK at a receipt would mark it
        // printed — on a live company, silently. No shipped receipt carries it
        // today; that is not a reason to leave the door open (§8.1 trap 1).
        $report->setprintedfield = '';
        $GLOBALS['report'] = $report;

        $printed = BuildForm($report, 'S');
        // BuildForm() returns true — '1' as a string — when it declines.
        if (!is_string($printed) || $printed === '' || $printed === '1') continue;
        $captured['pf_' . $id . '|' . $recordId] = encodeBytes($printed);
    }
    printf("  pf_%-4d %-28s %d record(s)\n", $id, substr($title, 0, 28),
        count(array_filter(array_keys($captured), fn($k) => str_starts_with($k, "pf_$id|"))));
}

if ($captured === []) {
    fwrite(STDERR, "Nothing printed on the old engine, so there is nothing to record.\n");
    exit(1);
}

@mkdir($dir, 0777, true);
$json = json_encode([
    'what'    => 'PhreeForm BuildSeq() output per receipt and record, captured before deletion',
    'records' => $records,
    'layouts' => $layouts,
    'printed' => $captured,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "The baseline could not be encoded: " . json_last_error_msg() . "\n");
    exit(1);
}
if (file_put_contents($path, $json . "\n") === false) {
    fwrite(STDERR, "Could not write $path\n");
    exit(1);
}
printf("\ncaptured %d receipt printing(s) over %d record(s)\n  -> %s\n",
    count($captured), count($records), $path);
