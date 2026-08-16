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
//  Path: /docs/tools/generate_forms_shipping_set.php
//
/**
 * Generate the native shipping set from the XML one (design §13.5, step 5).
 *
 * 52 definitions ship as `.xml` under
 * `modules/{module}/language/{locale}/reports/` and are seeded per company by
 * `admin_add_reports()`. A new install should get NATIVE definitions from the
 * start, which means converting them once, committing the result, and teaching
 * the installer to seed those instead.
 *
 * This writes `<Name>.json` beside each `<Name>.xml`. Both are committed: the
 * XML set stays because it is what the Import feature reads (decision L) and
 * what an older company already has, and because a third-party module may ship
 * only XML — the seeder falls back to it.
 *
 * ## Why generated rather than hand-written
 *
 * The converter is the thing under test everywhere else in this suite: 56 of 57
 * definitions on each company convert and render byte-identical to the legacy
 * path (§13.9). Hand-authoring 52 native files would be 52 chances to differ
 * from what that proof covers. Generating them means the shipped set is exactly
 * what the converter produces, and `verify_forms_shipping_set.php` re-runs the
 * conversion to prove the committed files still match.
 *
 * ## What it checks as it goes
 *
 * A conversion warning here is not the same as one on a customer's company: it
 * is a definition WE ship converting imperfectly, which would be shipped that
 * way to every new install. So warnings are reported per file and counted, and
 * the tool exits non-zero if any file fails outright.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/generate_forms_shipping_set.php [company] [--write]
 *
 * Without --write it reports what would change and touches nothing.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionBuilder, DefinitionCodec};

$write = in_array('--write', $argv, true);

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$codec = new DefinitionCodec();

$converted = $failed = $unchanged = $written = 0;
$warned = [];

foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.xml') ?: [] as $path) {
    $shown = str_replace(DIR_FS_MODULES, 'modules/', $path);
    $target = preg_replace('/\.xml$/i', '.json', $path);

    $contents = (string)file_get_contents($path);
    $report   = xml_to_object($contents);
    // The shipped files carry a <PhreeformReport> container that ImportReport()
    // unwraps; a native file has no container, so it is unwrapped here too.
    if (is_object($report) && isset($report->PhreeformReport) && is_object($report->PhreeformReport)) {
        $report = $report->PhreeformReport;
    }
    if (!is_object($report)) {
        echo "  [FAIL] $shown — not readable as a PhreeForm definition\n";
        $failed++;
        continue;
    }

    // A SERIAL form is not a page — it is character-cell text for a receipt
    // printer. PhreeForm routes it to BuildSeq(), where `width` counts columns
    // and `rowbreak` ends a line; there are no coordinates at all, which is why
    // converting one produces a template with every element at 0,0.
    //
    // Forms has no renderer for that kind yet (§13.16), so shipping a native
    // copy would leave a NEW install unable to print a receipt with either
    // engine: Forms would draw nonsense and PhreeForm cannot read the format.
    // Left as xml, which the seeder falls back to and PhreeForm still prints.
    if (preg_match('#<serialform><!\[CDATA\[([^\]]*)\]\]>#', $contents, $serial)
        && trim($serial[1]) !== '' && trim($serial[1]) !== '0') {
        $native = preg_replace('/\.xml$/i', '.json', $path);
        if (is_file($native) && $write) { @unlink($native); echo "  [remove] " . str_replace(DIR_FS_MODULES, 'modules/', $native) . " (serial form)\n"; }
        echo "  [--] " . $shown . " is a serial (receipt) form; it stays xml\n";
        continue;
    }

    $builder = new DefinitionBuilder($catalog);
    try {
        // No id: a shipped file is not yet in any catalog, and the id it will
        // get is whatever the seeding company assigns.
        $definition = $builder->fromLegacy($report, '');
        $json = $codec->encode($definition);
    } catch (\Throwable $e) {
        echo "  [FAIL] $shown — " . $e->getMessage() . "\n";
        $failed++;
        continue;
    }

    // A definition WE ship converting imperfectly would be shipped that way to
    // every new install, so this is louder than the same warning would be on a
    // customer's own report.
    if ($builder->warnings()) $warned[$shown] = $builder->warnings();

    $converted++;
    $existing = is_file($target) ? (string)file_get_contents($target) : null;
    if ($existing === $json) { $unchanged++; continue; }

    if ($write) {
        if (file_put_contents($target, $json) === false) {
            echo "  [FAIL] could not write " . str_replace(DIR_FS_MODULES, 'modules/', $target) . "\n";
            $failed++;
            continue;
        }
        $written++;
        echo "  [write] " . str_replace(DIR_FS_MODULES, 'modules/', $target) . "\n";
    } else {
        echo "  [would write] " . str_replace(DIR_FS_MODULES, 'modules/', $target)
           . ($existing === null ? " (new)\n" : " (differs)\n");
    }
}

echo "\n  converted $converted, unchanged $unchanged, "
   . ($write ? "written $written" : "would write " . ($converted - $unchanged))
   . ", failed $failed\n";

if ($warned) {
    echo "\n  " . count($warned) . " definition(s) converted WITH WARNINGS:\n";
    foreach ($warned as $file => $notes) {
        echo "    $file\n";
        foreach ($notes as $note) echo "        $note\n";
    }
    echo "\n  These ship as they are. A warning here reaches every new install,\n"
       . "  so it wants a decision rather than a shrug.\n";
}

if (!$write && $converted > $unchanged) {
    echo "\n  Nothing was written. Re-run with --write to update the shipping set.\n";
}

exit($failed === 0 ? 0 : 1);
