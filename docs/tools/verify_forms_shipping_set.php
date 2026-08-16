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
//  Path: /docs/tools/verify_forms_shipping_set.php
//
/**
 * The native shipping set is what the converter produces (design §13.5).
 *
 * `generate_forms_shipping_set.php` writes a `.json` beside every shipped
 * `.xml`, and those files are committed. A generated artefact that is committed
 * goes stale the moment the thing that generates it changes — so this re-runs
 * the conversion and compares, which is the only claim worth making about it.
 *
 * It also covers the two traps §13.5 names, both of which fail silently:
 *
 *   - a definition that ships in one format and not the other, so a company
 *     seeds a document nobody meant it to have — or misses one entirely;
 *   - the RE-STAMP, which substitutes the paper size textually on XML and
 *     therefore skips every native file. After the migration every document is
 *     native, so a re-stamp that cannot reach them applies to nothing.
 *
 * Seeding itself — that the native path is preferred, that the paper is
 * stamped, that orientation is untouched — is `verify_paper_seeding.php`,
 * which drives the real `admin_add_reports()`.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_shipping_set.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/functions/seed.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionBuilder, DefinitionCodec, DefinitionLoader};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);
$codec = new DefinitionCodec();

// Only the modules that ship IN trunk. An optional module installed from
// extensions/ has its own reports, and its native files — if it ever gains
// any — belong to it rather than to this set.
$shipped = [];
foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.xml') ?: [] as $path) {
    $shipped[] = $path;
}
sort($shipped);

echo "=== every shipped definition ships in both formats ===\n";
//
// Per MODULE, not across the tree. An optional module installed from
// extensions/ ships only xml and is entitled to: the seeder falls back to it,
// which is why that fallback exists. What would be a defect is a module that
// ships SOME native files and not others, because then a company seeds a
// mixture nobody chose. So the rule is consistency within a module, and a
// module with no native files at all is reported rather than failed.
$byModule = [];
foreach ($shipped as $xml) {
    $module = basename(dirname($xml, 4));
    $byModule[$module][] = $xml;
}
$xmlOnly = [];
$missing = $orphans = [];
foreach ($byModule as $module => $files) {
    $native = 0;
    foreach ($files as $xml) {
        if (is_file(preg_replace('/\.xml$/i', '.json', $xml))) $native++;
    }
    if ($native === 0) { $xmlOnly[$module] = count($files); continue; }
    foreach ($files as $xml) {
        if (!is_file(preg_replace('/\.xml$/i', '.json', $xml))) {
            $missing[] = str_replace(DIR_FS_MODULES, '', $xml);
        }
    }
}
foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.json') ?: [] as $json) {
    if (!is_file(preg_replace('/\.json$/i', '.xml', $json))) {
        $orphans[] = str_replace(DIR_FS_MODULES, '', $json);
    }
}
ok(sprintf('%d definition(s) found in %d module(s)', count($shipped), count($byModule)),
   $shipped !== []);
ok('no module ships a PARTIAL native set', $missing === [],
   implode(' | ', array_slice($missing, 0, 4)));
foreach ($xmlOnly as $module => $count) {
    printf("  [--] %s ships %d definition(s) as xml only; the seeder falls back to it\n",
        $module, $count);
}
ok('at least one module ships native', count($xmlOnly) < count($byModule),
   'every module is xml-only — the shipping set is not installed');
ok('no native file is left without its source', $orphans === [],
   implode(' | ', array_slice($orphans, 0, 4)));

echo "\n=== the committed files are what the converter produces now ===\n";
$stale = $unreadable = [];
foreach ($shipped as $xml) {
    $json = preg_replace('/\.xml$/i', '.json', $xml);
    if (!is_file($json)) continue;          // xml-only module; reported above
    $name = str_replace(DIR_FS_MODULES, '', $xml);

    $report = xml_to_object((string)file_get_contents($xml));
    if (is_object($report) && isset($report->PhreeformReport) && is_object($report->PhreeformReport)) {
        $report = $report->PhreeformReport;
    }
    if (!is_object($report)) { $unreadable[] = $name; continue; }

    $builder = new DefinitionBuilder($catalog);
    try {
        $fresh = $codec->encode($builder->fromLegacy($report, ''));
    } catch (\Throwable $e) {
        $unreadable[] = $name . ' (' . $e->getMessage() . ')';
        continue;
    }
    if ($fresh !== (string)file_get_contents($json)) $stale[] = $name;
}
ok('every shipped xml still converts', $unreadable === [],
   implode(' | ', array_slice($unreadable, 0, 3)));
ok('no committed native file is out of date', $stale === [],
   count($stale) . ' stale: ' . implode(' | ', array_slice($stale, 0, 4))
   . ' — re-run generate_forms_shipping_set.php --write');

echo "\n=== and they convert without warnings ===\n";
// A warning here reaches every new install, which is what separates it from
// the same warning on a company's own report.
$warned = [];
foreach ($shipped as $xml) {
    $report = xml_to_object((string)file_get_contents($xml));
    if (is_object($report) && isset($report->PhreeformReport) && is_object($report->PhreeformReport)) {
        $report = $report->PhreeformReport;
    }
    if (!is_object($report)) continue;
    $builder = new DefinitionBuilder($catalog);
    try { $builder->fromLegacy($report, ''); } catch (\Throwable) { continue; }
    if ($builder->warnings()) $warned[str_replace(DIR_FS_MODULES, '', $xml)] = $builder->warnings()[0];
}
ok('no shipped definition converts with a warning', $warned === [],
   implode(' | ', array_slice(array_map(
       fn($f, $w) => "$f: $w", array_keys($warned), $warned), 0, 3)));

echo "\n=== a serial (receipt) form is NOT shipped native ===\n";
// A serial form is character-cell text for a receipt printer, not a page:
// PhreeForm routes it to BuildSeq(), where width counts columns and rowbreak
// ends a line, and it carries no coordinates at all. Forms has no renderer for
// that kind (§13.16), so a native copy would leave a new install unable to
// print a receipt with EITHER engine — Forms drawing every element at 0,0 and
// PhreeForm unable to read the format.
$wrongly = $serials = [];
foreach ($shipped as $xml) {
    $text = (string)file_get_contents($xml);
    if (!preg_match('#<serialform><!\[CDATA\[([^\]]*)\]\]>#', $text, $m)) continue;
    if (trim($m[1]) === '' || trim($m[1]) === '0') continue;
    $serials[] = basename($xml);
    if (is_file(preg_replace('/\.xml$/i', '.json', $xml))) {
        $wrongly[] = str_replace(DIR_FS_MODULES, '', $xml);
    }
}
ok(sprintf('%d serial form(s) found', count($serials)), $serials !== [],
   'none — this check would pass whatever the rule did');
ok('none of them ships a native copy', $wrongly === [],
   implode(' | ', array_slice($wrongly, 0, 3)));

echo "\n=== every column keeps the break and total the xml declared ===\n";
//
// Against the XML itself, not against the converter's own output — the check
// that was missing. A report whose rows come from a PROVIDER went through a
// second column builder that never read <columnbreak> or <total>, so all five
// structural reports (Account Reconciliation, Balance Sheet and the three
// Income Statements) stacked every column into one band down the left of the
// page, and three of them printed no totals at all. Nothing noticed: the
// byte comparisons are Forms against Forms, and both sides had lost it.
$lost = [];
foreach ($shipped as $xml) {
    $json = preg_replace('/\.xml$/i', '.json', $xml);
    if (!is_file($json)) continue;
    $text = (string)file_get_contents($xml);
    if (!preg_match('#<reporttype><!\[CDATA\[rpt\]\]>#', $text)) continue;   // forms have no columns

    // What the legacy definition declares, in order, for VISIBLE fields only —
    // the converter drops the others, so comparing them would be comparing
    // against columns that were never meant to appear.
    $declared = [];
    foreach (preg_split('#<fieldlist>#', $text) as $block) {
        if (!str_contains($block, '<fieldname>')) continue;
        $visible = preg_match('#<visible><!\[CDATA\[([^\]]*)\]\]>#', $block, $m) ? trim($m[1]) : '1';
        if ($visible === '' || $visible === '0') continue;
        $declared[] = [
            'break' => preg_match('#<columnbreak><!\[CDATA\[([^\]]*)\]\]>#', $block, $m) && trim($m[1]) === '1',
            'total' => preg_match('#<total><!\[CDATA\[([^\]]*)\]\]>#', $block, $m) && trim($m[1]) === '1',
        ];
    }
    // Converted FRESH, not read from the committed file. A first version read
    // the json, which meant it judged the artefact rather than the code that
    // makes it: breaking the converter left the check green (the staleness
    // check above caught it instead). Converting here makes this an external
    // truth test of the converter itself.
    $report = xml_to_object($text);
    if (is_object($report) && isset($report->PhreeformReport) && is_object($report->PhreeformReport)) {
        $report = $report->PhreeformReport;
    }
    if (!is_object($report)) continue;
    $builder = new DefinitionBuilder($catalog);
    try { $definition = $builder->fromLegacy($report, ''); } catch (\Throwable) { continue; }
    $converted = [];
    foreach ($definition->columns as $column) {
        $converted[] = ['pageBreak' => $column->pageBreak, 'total' => $column->total];
    }
    if (count($declared) !== count($converted)) continue;   // a shape this check cannot judge

    foreach ($converted as $i => $column) {
        if ((bool)($column['pageBreak'] ?? false) !== $declared[$i]['break']) {
            $lost[] = basename($xml) . " column " . ($i + 1) . " break";
        }
        if ((bool)($column['total'] ?? false) !== $declared[$i]['total']) {
            $lost[] = basename($xml) . " column " . ($i + 1) . " total";
        }
    }
}
ok('no column loses its break or its total in conversion', $lost === [],
   count($lost) . ' lost: ' . implode(' | ', array_slice($lost, 0, 5)));

echo "\n=== a native definition can be re-stamped ===\n";
// The re-stamp is the feature that fails silently on native files: its pattern
// is anchored on XML, so before this existed it counted them as skipped.
$sample = null;
foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.json') ?: [] as $json) { $sample = $json; break; }
if ($sample === null) {
    ok('a native file is available to stamp', false);
} else {
    $scratch = sys_get_temp_dir() . '/fb_restamp_' . getmypid() . '.json';
    copy($sample, $scratch);
    $before = json_decode((string)file_get_contents($scratch), true)['page']['size'] ?? '';
    $target = $before === 'A4' ? 'Legal:216:357' : 'A4:210:297';

    $result = forms_restamp_native($scratch, $target);
    $after  = json_decode((string)file_get_contents($scratch), true)['page']['size'] ?? '';
    ok('the re-stamp reports it handled the file', $result === true, var_export($result, true));
    ok('the paper changed to the one asked for', $after === explode(':', $target)[0],
       "'$before' -> '$after', wanted '" . explode(':', $target)[0] . "'");
    // The NAME alone: TCPDF looks a paper up by name, and storing the legacy
    // token would give every document a size no renderer resolves.
    ok('and is stored as the name, without the dimensions', !str_contains($after, ':'), $after);

    // Nothing else moved. The re-stamp has no business rewriting the parts it
    // was not asked about — the same rule the XML substitution follows.
    $left  = json_decode((string)file_get_contents($sample), true);
    $right = json_decode((string)file_get_contents($scratch), true);
    unset($left['page']['size'], $right['page']['size']);
    ok('nothing but the paper changed', $left == $right);

    // An XML file is not this function's to touch: the caller still applies
    // its own substitution, and a null says so.
    $xmlScratch = sys_get_temp_dir() . '/fb_restamp_' . getmypid() . '.xml';
    copy(preg_replace('/\.json$/', '.xml', $sample), $xmlScratch);
    $wasXml = (string)file_get_contents($xmlScratch);
    ok('an xml definition is declined rather than mangled',
       forms_restamp_native($xmlScratch, $target) === null
       && (string)file_get_contents($xmlScratch) === $wasXml);

    @unlink($scratch);
    @unlink($xmlScratch);
}

echo "\n=== a seeded definition is one the loader can read ===\n";
// The set is only worth shipping if what lands in a company loads. Checked
// through DefinitionLoader, which is what every page uses.
$loader = new DefinitionLoader($catalog);
$sampleFailures = [];
foreach (array_slice(glob(DIR_FS_MODULES . '*/language/*/reports/*.json') ?: [], 0, 12) as $json) {
    $scratch = sys_get_temp_dir() . '/fb_seed_' . getmypid() . '/';
    @mkdir($scratch, 0777, true);
    $decoded = json_decode((string)file_get_contents($json), true);
    try {
        $definition = $codec->decode((string)file_get_contents($json), '0');
        if ($definition->title !== ($decoded['title'] ?? null)) {
            $sampleFailures[] = basename($json) . ' title changed';
        }
    } catch (\Throwable $e) {
        $sampleFailures[] = basename($json) . ': ' . $e->getMessage();
    }
}
ok('the first 12 native files decode, keeping their titles', $sampleFailures === [],
   implode(' | ', array_slice($sampleFailures, 0, 3)));
@rmdir(sys_get_temp_dir() . '/fb_seed_' . getmypid() . '/');

echo "\n" . ($failures === 0 ? "All checks passed.\n" : "$failures check(s) FAILED.\n");
exit($failures === 0 ? 0 : 1);
