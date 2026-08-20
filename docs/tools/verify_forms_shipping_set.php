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

// The definitions whose layout has been TIDIED BY HAND and is therefore no
// longer derivable from the xml (§13.18). For these the claim below — that the
// committed file is what the converter produces — is deliberately false, and
// asserting it would demand the tidying be thrown away. They get the checks that
// survive editing instead: still decodes, still canonical, still the same
// document. Everything not listed keeps the byte check, so ordinary staleness is
// still caught.
$manifestPath = __DIR__ . '/../forms-adopted.json';
$adopted = [];
if (is_file($manifestPath)) {
    $decoded = json_decode((string)file_get_contents($manifestPath), true);
    $adopted = is_array($decoded['adopted'] ?? null) ? $decoded['adopted'] : [];
}

// Every INSTALLED module, core or optional. An optional module ships its
// reports the same way a core one does, and since 2026-08-18 it ships them
// native too — so the set under test is what this installation actually holds,
// not a hand-kept list of module names.
$shipped = [];
foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.xml') ?: [] as $path) {
    $shipped[] = $path;
}
sort($shipped);

echo "=== the installed definitions are the ones the repository ships ===\n";
//
// modules/ inside the container is a COPY of the repository, and the two drift
// in one direction that nothing else notices. A core module is redeployed from
// trunk/ often enough to stay current; an OPTIONAL module is copied out of
// extensions/ once, when it is installed, and then never again. Two of its
// reports were turned landscape in the repository on 2026-08-14 and the copy
// here kept the portrait xml — so GENERATING the native set from this tree
// would have produced, and committed, definitions converted from an xml nobody
// ships. Cheap to compare, silent when wrong, so it is checked rather than
// remembered.
//
// The masters arrive by `docker cp`, not from the image, so their absence is
// reported instead of failing: a container without them can still run
// everything else here.
$repoRoot    = dirname(__DIR__, 2);          // /opt/repo, deployed as the docs say
$masterRoots = [];
foreach ([$repoRoot . '/trunk/modules', $repoRoot . '/extensions/Modules'] as $root) {
    if (is_dir($root)) $masterRoots[] = $root;
}
if ($masterRoots === []) {
    echo "  [--] no repository tree at $repoRoot; the installed copies cannot be compared\n";
} else {
    // Where a module's master lives — a module is in one root or the other,
    // never both, because installing an optional module MOVES it into place.
    $masterOf = function (string $installed) use ($masterRoots): ?string {
        $tail = str_replace(DIR_FS_MODULES, '', $installed);
        foreach ($masterRoots as $root) {
            if (is_file($root . '/' . $tail)) return $root . '/' . $tail;
        }
        return null;
    };

    $comparable = 0;
    $sourceDrift = $nativeDrift = $uncovered = [];
    foreach ($shipped as $xml) {
        foreach ([$xml, preg_replace('/\.xml$/i', '.json', $xml)] as $installed) {
            if (!is_file($installed)) continue;
            $master = $masterOf($installed);
            if ($master === null) {
                // Only worth saying for the xml: a json with no master is the
                // generate-then-forget-to-copy-out case, and it is named below.
                if ($installed === $xml) $uncovered[] = str_replace(DIR_FS_MODULES, '', $installed);
                elseif ($masterOf($xml) !== null) $nativeDrift[] = str_replace(DIR_FS_MODULES, '', $installed) . ' (no master at all)';
                continue;
            }
            $comparable++;
            if (file_get_contents($master) === file_get_contents($installed)) continue;
            $name = str_replace(DIR_FS_MODULES, '', $installed);
            if ($installed === $xml) $sourceDrift[] = $name; else $nativeDrift[] = $name;
        }
    }
    printf("  [--] %d file(s) compared against %d repository tree(s)\n", $comparable, count($masterRoots));
    ok('no installed xml differs from the one the repository ships', $sourceDrift === [],
       implode(' | ', array_slice($sourceDrift, 0, 4))
       . ' — deploy the master here BEFORE generating, or the native set is made from the wrong source');
    ok('no generated native file is missing from the repository', $nativeDrift === [],
       implode(' | ', array_slice($nativeDrift, 0, 4))
       . ' — copy it back out of the container into the repository');
    foreach (array_slice($uncovered, 0, 6) as $name) {
        echo "  [--] $name has no master in the repository tree(s) present; not compared\n";
    }
}

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
// Adopted files, checked differently. Collected here and reported after, so the
// two claims stay separate: one is about a generated artefact being current, the
// other about a hand-edited one still being sound.
$notCanonical = $notSameDocument = $bindingNotes = [];
$adoptedSeen = [];
foreach ($shipped as $xml) {
    $json = preg_replace('/\.xml$/i', '.json', $xml);
    if (!is_file($json)) continue;          // xml-only module; reported above
    $name = str_replace(DIR_FS_MODULES, '', $xml);

    $report = \FreeBooks\Forms\Legacy\LegacyReader::parse((string)file_get_contents($xml));
    if (!is_object($report)) { $unreadable[] = $name; continue; }

    $builder = new DefinitionBuilder($catalog);
    try {
        $fresh = $codec->encode($builder->fromLegacy($report, ''));
    } catch (\Throwable $e) {
        $unreadable[] = $name . ' (' . $e->getMessage() . ')';
        continue;
    }

    $committed = (string)file_get_contents($json);
    $relative  = str_replace(DIR_FS_MODULES, '', $json);
    if (in_array($relative, $adopted, true)) {
        $adoptedSeen[] = $relative;

        // Canonical, so a file edited with a text editor rather than through the
        // editor cannot ship: anything the codec would write differently is a
        // difference the app will not preserve on the next save.
        try {
            if ($codec->encode($codec->decode($committed)) !== $committed) $notCanonical[] = $relative;
        } catch (\Throwable $e) {
            $notCanonical[] = $relative . ' (' . $e->getMessage() . ')';
            continue;
        }

        // Still the same DOCUMENT as the xml beside it. A tidy moves things; it
        // does not turn an invoice into something else, change which dataset it
        // reads, or rename the file customers receive.
        $was = json_decode($fresh, true);
        $now = json_decode($committed, true);
        foreach (['type', 'title', 'group', 'filenamePrefix', 'provider', 'dataset'] as $key) {
            if (($was[$key] ?? null) !== ($now[$key] ?? null)) {
                $notSameDocument[] = "$relative: $key '"
                    . var_export($was[$key] ?? null, true) . "' -> '"
                    . var_export($now[$key] ?? null, true) . "'";
            }
        }

        // Reported, NOT failed: a tidy may legitimately delete a duplicated
        // label, and the shipped layouts have some. But a binding that vanished
        // is a field the document no longer prints, and that wants reading.
        $bind = function (array $definition): array {
            $names = [];
            foreach ($definition['form']['elements'] ?? [] as $element) {
                if (isset($element['bind']['name'])) $names[(string)$element['bind']['name']] = true;
                foreach ($element['parts'] ?? [] as $part) {
                    if (isset($part['bind']['name'])) $names[(string)$part['bind']['name']] = true;
                }
            }
            foreach ($definition['report']['columns'] ?? [] as $column) {
                if (isset($column['key'])) $names[(string)$column['key']] = true;
            }
            return $names;
        };
        $gone  = array_diff_key($bind($was), $bind($now));
        $extra = array_diff_key($bind($now), $bind($was));
        if ($gone || $extra) {
            $bindingNotes[] = $relative
                . ($gone  ? ' dropped: ' . implode(', ', array_keys($gone))  : '')
                . ($extra ? ' added: '   . implode(', ', array_keys($extra)) : '');
        }
        continue;
    }

    if ($fresh !== $committed) $stale[] = $name;
}
ok('every shipped xml still converts', $unreadable === [],
   implode(' | ', array_slice($unreadable, 0, 3)));
ok('no committed native file is out of date', $stale === [],
   count($stale) . ' stale: ' . implode(' | ', array_slice($stale, 0, 4))
   . ' — re-run generate_forms_shipping_set.php --write');

// The manifest names files, and a name that no longer resolves would put a
// tidied definition back under the byte check without saying so.
$phantom = array_values(array_diff($adopted, $adoptedSeen));
if ($adopted) {
    echo "\n=== the hand-tidied definitions are still sound ===\n";
    printf("  [--] %d definition(s) adopted; the byte check above does not apply to them\n",
        count($adopted));
    ok('every adopted file is one of the shipped definitions', $phantom === [],
       implode(' | ', array_slice($phantom, 0, 4))
       . ' — listed in docs/forms-adopted.json but not found beside a shipped xml');
    ok('the manifest is sorted and free of duplicates',
       $adopted === array_values(array_unique($adopted))
       && $adopted === (function ($a) { sort($a); return $a; })($adopted));
    ok('every adopted file is what the editor would write', $notCanonical === [],
       implode(' | ', array_slice($notCanonical, 0, 3)));
    ok('and is still the same document as its xml', $notSameDocument === [],
       implode(' | ', array_slice($notSameDocument, 0, 3)));
    foreach ($bindingNotes as $note) echo "  [--] $note\n";
}

echo "\n=== and they convert without warnings ===\n";
// A warning here reaches every new install, which is what separates it from
// the same warning on a company's own report.
$warned = [];
foreach ($shipped as $xml) {
    $report = \FreeBooks\Forms\Legacy\LegacyReader::parse((string)file_get_contents($xml));
    if (!is_object($report)) continue;
    $builder = new DefinitionBuilder($catalog);
    try { $builder->fromLegacy($report, ''); } catch (\Throwable) { continue; }
    if ($builder->warnings()) $warned[str_replace(DIR_FS_MODULES, '', $xml)] = $builder->warnings()[0];
}
ok('no shipped definition converts with a warning', $warned === [],
   implode(' | ', array_slice(array_map(
       fn($f, $w) => "$f: $w", array_keys($warned), $warned), 0, 3)));

echo "\n=== a serial (receipt) form IS shipped native ===\n";
// This check used to say the opposite, and the rule it enforced outlived its
// reason. A serial form is character-cell text for a receipt printer — width
// counts columns, rowbreak ends a line, and there are no coordinates at all —
// and while Forms had no renderer for that kind, shipping a native copy would
// have left a new install unable to print a receipt with EITHER engine.
//
// §13.28 built the renderer, and verify_forms_serial holds it to BuildSeq()
// byte for byte. The exclusion stayed anyway, which made it the quiet blocker
// on step 6: with PhreeForm deleted, a fresh install would seed 59 native
// definitions and NO RECEIPT, and the first sign of it would be a till printing
// nothing.
$missing = $serials = $noTemplate = [];
foreach ($shipped as $xml) {
    $text = (string)file_get_contents($xml);
    if (!preg_match('#<serialform><!\[CDATA\[([^\]]*)\]\]>#', $text, $m)) continue;
    if (trim($m[1]) === '' || trim($m[1]) === '0') continue;
    $serials[] = basename($xml);

    $native = preg_replace('/\.xml$/i', '.json', $xml);
    if (!is_file($native)) { $missing[] = str_replace(DIR_FS_MODULES, '', $xml); continue; }

    // Shipped is not enough: it has to carry the SERIAL template. A receipt
    // converts to a page template too, with every element at 0,0 — dead weight,
    // and the thing that would be printed if the serial half were ever lost.
    $decoded = json_decode((string)file_get_contents($native), true);
    if (empty($decoded['form']['serialLayout']['fields'] ?? null)) {
        $noTemplate[] = str_replace(DIR_FS_MODULES, '', $native);
    }
}
ok(sprintf('%d serial form(s) found', count($serials)), $serials !== [],
   'none — this check would pass whatever the rule did');
ok('each ships a native copy, so a new install can print a receipt',
   $missing === [], implode(' | ', array_slice($missing, 0, 3)));
ok('and each native copy carries its serial template',
   $noTemplate === [], implode(' | ', array_slice($noTemplate, 0, 3)));

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
    $report = \FreeBooks\Forms\Legacy\LegacyReader::parse($text);
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

echo "\n=== a stored definition is `pf_` AND DIGITS, everywhere it is walked ===\n";
// Forms keeps the original beside a definition it rewrites, as
// `pf_<id>.pre-forms`. That name begins `pf_` too, so `substr($f, 0, 3) == 'pf_'`
// picks up the BACKUP as if it were a document — and the three loops that walk
// the directory each do something destructive with what they find. The Rebuild
// catalogues and RENAMES, so a company that had used Forms came out of one with
// its list close to doubled and its backups gone; the adopt tool would have
// matched the pre-tidy copy to the same master and, being second in the
// directory listing, written the version from BEFORE the tidy over it.
$walkers = [
    'the paper re-stamp'   => DIR_FS_MODULES . 'phreeform/pages/admin/pre_process.php',
    'the catalog rebuild'  => DIR_FS_MODULES . 'phreeform/pages/admin/pre_process.php',
    'adopt_forms_layouts'  => __DIR__ . '/adopt_forms_layouts.php',
];
$loose = [];
foreach (array_unique($walkers) as $path) {
    if (!is_file($path)) { $loose[] = basename($path) . ' (missing)'; continue; }
    $source = (string)file_get_contents($path);
    // The old test, in either spelling. Its absence is the claim.
    if (preg_match('/substr\(\s*\$\w+\s*,\s*0\s*,\s*3\s*\)\s*(<>|!==|==|===)\s*.pf_./', $source)) {
        $loose[] = basename($path);
    }
}
ok('no walker matches on the first three characters', $loose === [],
   implode(' | ', $loose) . " — a `.pre-forms` backup begins `pf_` as well");
$gates = substr_count((string)file_get_contents(
    DIR_FS_MODULES . 'phreeform/pages/admin/pre_process.php'), "preg_match('/^pf_\\d+$/'");
ok('both admin loops gate on the exact name', $gates === 2, "found $gates, expected 2");
ok('and so does the adopt tool',
   str_contains((string)file_get_contents(__DIR__ . '/adopt_forms_layouts.php'),
                "preg_match('/^pf_\\d+$/'"));

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
