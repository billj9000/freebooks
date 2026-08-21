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
 *       /opt/repo/docs/tools/generate_forms_shipping_set.php [company] [--write] [--force]
 *
 * Without --write it reports what would change and touches nothing.
 *
 * ## Except where a layout has been tidied by hand
 *
 * The shipped layouts came from PhreeBooks and some of them need design work that
 * only makes sense on the canvas. Once a layout has been tidied in a company and
 * adopted back — `adopt_forms_layouts.php`, design §13.18 — it is no longer
 * derivable from the xml, and re-running this tool would silently throw it away.
 * Those files are listed in `docs/forms-adopted.json`, and are skipped and named
 * here. `--force` overwrites them, which is what abandoning a tidy looks like.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionBuilder, DefinitionCodec};

$write = in_array('--write', $argv, true);
// Overwrite a hand-tidied layout anyway. There is one reason to want this — the
// tidy is being abandoned and the generated layout is wanted back — and it should
// take saying so.
$force = in_array('--force', $argv, true);

// The definitions whose layout was tidied by hand and adopted (§13.18). Writing
// over one throws that work away silently, which is exactly what this tool would
// otherwise do on its next run, so they are skipped and named.
$adoptedManifest = __DIR__ . '/../forms-adopted.json';
$adopted = [];
if (is_file($adoptedManifest)) {
    $decoded = json_decode((string)file_get_contents($adoptedManifest), true);
    $adopted = is_array($decoded['adopted'] ?? null) ? $decoded['adopted'] : [];
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$codec = new DefinitionCodec();

$converted = $failed = $unchanged = $written = $left = 0;
$warned = [];

// The xml this reads is the container's COPY of the repository, and for an
// OPTIONAL module that copy is made once, at install, and never refreshed.
// Two extension reports were turned landscape in the repository and the copy
// here kept the portrait xml, so a run at that moment would have generated —
// and committed — a native set converted from a definition nobody ships.
// Generating from the wrong source is worse than not generating, so a file
// whose master is visible and DIFFERENT is skipped and named.
//
// The masters arrive by `docker cp` into /opt/repo, not from the image; where
// they are absent there is nothing to compare and every file simply proceeds.
$repoRoot    = dirname(__DIR__, 2);
$masterRoots = array_values(array_filter(
    [$repoRoot . '/trunk/modules', $repoRoot . '/extensions/Modules'], 'is_dir'));
$staleSource = function (string $installed) use ($masterRoots): ?string {
    $tail = str_replace(DIR_FS_MODULES, '', $installed);
    foreach ($masterRoots as $root) {
        if (!is_file($root . '/' . $tail)) continue;
        return file_get_contents($root . '/' . $tail) === file_get_contents($installed)
             ? null : $root . '/' . $tail;
    }
    return null;                              // no master here; nothing to say
};
$stale = 0;

foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.xml') ?: [] as $path) {
    $shown = str_replace(DIR_FS_MODULES, 'modules/', $path);
    $target = preg_replace('/\.xml$/i', '.json', $path);

    // Before anything is read: an adopted file is no longer this tool's output.
    if (!$force && in_array(str_replace(DIR_FS_MODULES, '', $target), $adopted, true)) {
        echo "  [adopted] " . str_replace(DIR_FS_MODULES, 'modules/', $target)
           . " was tidied by hand; left alone (--force overrides)\n";
        $left++;
        continue;
    }

    // And before it is converted: is this xml the one the repository ships?
    if (($master = $staleSource($path)) !== null) {
        echo "  [STALE] $shown differs from " . str_replace($repoRoot . '/', '', $master)
           . "; deploy the master here first — skipped\n";
        $stale++;
        continue;
    }

    $contents = (string)file_get_contents($path);
    $report   = \FreeBooks\Forms\Legacy\LegacyReader::parse($contents);
    if (!is_object($report)) {
        echo "  [FAIL] $shown — not readable as a PhreeForm definition\n";
        $failed++;
        continue;
    }

    // A SERIAL form is not a page — it is character-cell text for a receipt
    // printer, where `width` counts columns and `rowbreak` ends a line, and it
    // carries no coordinates at all. It used to be EXCLUDED from the shipping
    // set for that reason: Forms had no renderer for the kind, so a native copy
    // would have left a new install unable to print a receipt with either
    // engine.
    //
    // That reason died with §13.28 — SerialRenderer prints byte for byte what
    // BuildSeq() printed — and the exclusion outlived it. Left in place it
    // would have been the quiet blocker on step 6: a fresh install with
    // PhreeForm deleted would seed 59 native definitions and NO receipt, and
    // the first sign of it would be a till that prints nothing.
    //
    // The converted file also carries a page template with every element at
    // 0,0, which is what the old comment worried about. It is dead weight and
    // not a hazard: a definition with a serial template is served as one, which
    // is what every migrated company's receipt has been doing since the
    // migration.

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
   . ", failed $failed"
   . ($left ? ", left alone $left (hand-tidied)" : "")
   . ($stale ? ", SKIPPED $stale (installed copy is not the master)" : "") . "\n";

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

if ($stale) {
    echo "\n  $stale definition(s) were skipped because the copy installed here is not\n"
       . "  the one the repository ships. Deploy those modules and run this again;\n"
       . "  what it would have written is derived from the wrong source.\n";
}

exit($failed === 0 && $stale === 0 ? 0 : 1);
