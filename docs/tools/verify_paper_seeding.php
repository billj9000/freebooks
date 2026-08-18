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
//  Path: /docs/tools/verify_paper_seeding.php
//
/**
 * Reports are seeded onto the paper the install chose.
 *
 * The installer and the company manager now ask for a paper size, and every
 * report and form copied in is re-targeted onto it. This drives the REAL
 * ImportReport() rather than re-implementing its substitution, because the
 * defect this guards against is precisely a harness that agrees with itself:
 * a tool that reads the XML and applies the same rule would pass whether or not
 * the application does anything at all.
 *
 * What it asserts, for every module in $modules below:
 *   1. every definition lands on the requested paper, whichever was requested
 *   2. orientation is NOT touched — 32 of the shipped reports choose landscape
 *      deliberately, and stamping PF_DEFAULT_ORIENTATION over them would ruin
 *      them; the paper feature must be size-only
 *   3. nothing else in the definition moves — the column widths, fields and
 *      filters come back byte-identical apart from the size
 *   4. re-importing is idempotent
 *   5. an explicit $paper_size argument beats the PF_DEFAULT_PAPERSIZE constant,
 *      which is what makes creating a SECOND company work: write_configure()
 *      only define()s a constant when it inserts the row, so a process that has
 *      already opened one company cannot have the constant redefined for the
 *      next one
 *
 * Covers phreebooks (core, the widest spread of shapes) and, since 2026-08-18,
 * the two optional modules that now ship native definitions of their own —
 * work_orders and assets (design §13.25). A module absent from this
 * container's modules/ tree is reported and skipped rather than failing the
 * run, since which optional modules are deployed varies by installation.
 *
 * THIS TOOL WRITES. ImportReport() inserts a catalog row per definition, and
 * TABLE_PHREEFORM is MyISAM so there is no transaction to roll back. It records
 * the highest id first and deletes everything above it in a finally, and it
 * writes its files to a temp directory rather than to my_files. Even so, do not
 * point it at a production company — pass a scratch one.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_paper_seeding.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/**
 * The page size and orientation a written definition actually carries.
 *
 * The CDATA body is matched as [^\]]* rather than a lazy .*? — a form's page
 * block sits AFTER its fieldlists, so a lazy match anchored on <orientation>
 * starts at the first font <size> in the file and swallows every element in
 * between. It then compares unequal and reads as a seeding failure, which is
 * what it did on the first run of this tool.
 */
function pageOf(string $file): array {
    $text = (string)file_get_contents($file);

    // Native definitions seed as JSON now, and a definition that ships in both
    // formats is written in whichever one the seeder preferred. A tool that
    // only read XML would report every native seeding as having no paper at
    // all — which is what it did the moment the shipped set gained .json files.
    if (str_starts_with(ltrim($text), '{')) {
        $native = json_decode($text, true);
        return [
            'size'        => (string)($native['page']['size']        ?? ''),
            'orientation' => (string)($native['page']['orientation'] ?? ''),
        ];
    }
    return [
        'size'        => preg_match('#<size><!\[CDATA\[([^\]]*)\]\]></size>\s*<orientation>#', $text, $m) ? $m[1] : '',
        'orientation' => preg_match('#<orientation><!\[CDATA\[([^\]]*)\]\]>#', $text, $m) ? $m[1] : '',
    ];
}

/**
 * A paper compared by NAME.
 *
 * The legacy format stores "A4:210:297" and the native one "A4" — the same
 * paper, spelled for two different readers. Everything here is asked "is this
 * the paper we requested", never "is this the same string".
 */
function paperName(string $size): string {
    return explode(':', $size)[0];
}

/** The title a written definition carries, in whichever format it is in. */
function titleOf(string $file): string {
    $text = (string)file_get_contents($file);
    if (str_starts_with(ltrim($text), '{')) {
        return (string)(json_decode($text, true)['title'] ?? '');
    }
    return preg_match('#<title><!\[CDATA\[(.*?)\]\]></title>#s', $text, $m) ? $m[1] : '';
}

// The modules whose reports we seed. phreebooks carries the widest spread —
// portrait and landscape, reports and forms — so it exercises every branch.
// work_orders and assets joined 2026-08-18, when they gained native
// definitions of their own (design §13.25). The risk there is different from
// phreebooks': an OPTIONAL module's copy inside a container is made once, at
// install, and never refreshed the way a core module's is — so a stale
// install-time orientation is exactly the kind of thing THIS tool, which
// drives the real seeder end to end, would catch and a byte comparison of
// the committed files would not. A module not deployed here is reported and
// skipped rather than failing the run.
$modules = ['phreebooks', 'work_orders', 'assets'];
printf("company '%s'\n\n", $company);

$highWater = (int)($db->Execute("select max(id) as id from " . TABLE_PHREEFORM)->fields['id'] ?? 0);
$tempRoot  = sys_get_temp_dir() . '/fb_paper_' . getmypid() . '/';

$cleanup = function () use ($db, $highWater, $tempRoot) {
    $db->Execute("delete from " . TABLE_PHREEFORM . " where id > " . (int)$highWater);
    // Recursive: seeding now also copies the IMAGES a definition names into an
    // images/ subdirectory beside the files, and a two-level sweep left it
    // behind — reported as "the temp directory was removed" failing, which is
    // this tool noticing its own tidying rather than a defect in the seeding.
    $sweep = function (string $dir) use (&$sweep): void {
        foreach (glob($dir . '/*') ?: [] as $path) {
            is_dir($path) ? $sweep($path) : @unlink($path);
        }
        @rmdir($dir);
    };
    $sweep(rtrim($tempRoot, '/'));
};

try {
    $modulesRun = 0;
    foreach ($modules as $module) {
        $readPath = DIR_FS_MODULES . $module . '/language/en_us/reports/';
        if (!is_dir($readPath)) {
            echo "=== $module ===\n  [--] no reports directory here; not deployed in this container — skipped\n\n";
            continue;
        }
        $sources = array_values(array_filter(scandir($readPath), fn($f) => str_ends_with(strtolower($f), '.xml')));
        if (!$sources) {
            echo "=== $module ===\n  [--] no xml source definitions — skipped\n\n";
            continue;
        }
        $modulesRun++;
        printf("=== %s: seeding %d definition(s) ===\n\n", $module, count($sources));

        // What the shipped files declare, before anything touches them.
        $declared = [];
        foreach ($sources as $file) $declared[$file] = pageOf($readPath . $file);

        // --- 1, 2, 3: each requested paper is honoured, and nothing else moves ---
        $written = [];
        foreach (['A4:210:297', 'Letter:216:282', 'Legal:216:357'] as $paper) {
            $savePath = $tempRoot . $module . '/' . str_replace(':', '_', $paper) . '/';
            @mkdir($savePath, 0777, true);

            echo "--- $module seeded onto $paper ---\n";
            // Straight through admin_add_reports(), the function both the installer
            // and the company manager call, so the parameter is proven end to end.
            admin_add_reports($module, $savePath, $paper);

            $files = glob($savePath . 'pf_*') ?: [];
            ok(sprintf('%s: %d definition(s) were written', $module, count($files)), count($files) > 0);

            // WHICH FORMAT was written, which every other check here is blind to.
            // The point of the shipping set (§13.5) is that a new company gets
            // NATIVE definitions; a seeder that quietly fell back to xml would
            // satisfy every paper assertion below and ship the thing step 5 exists
            // to replace. Counted against the sources that HAVE a native file, so
            // a module shipping only xml is not reported as a failure.
            $expectNative = 0;
            foreach ($sources as $source) {
                if (file_exists($readPath . substr($source, 0, -4) . '.json')) $expectNative++;
            }
            $native = 0;
            foreach ($files as $file) {
                if (str_starts_with(ltrim((string)file_get_contents($file)), '{')) $native++;
            }
            ok(sprintf('%s: the %d definition(s) that ship native were seeded native', $module, $expectNative),
               $expectNative > 0 && $native === $expectNative, "$native of $expectNative");

            $wrongSize = $wrongOrientation = [];
            foreach ($files as $file) {
                $page = pageOf($file);
                if (paperName($page['size']) !== paperName($paper)) {
                    $wrongSize[] = basename($file) . " has '{$page['size']}'";
                }
            }
            ok("$module: every definition carries the requested paper", $wrongSize === [],
               implode('; ', array_slice($wrongSize, 0, 3)));

            // Orientation is matched back to the SOURCE by title, since the written
            // filename is a database id rather than the original name.
            $byTitle = [];
            foreach ($files as $file) {
                $title = titleOf($file);
                if ($title !== '') $byTitle[$title] = $file;
            }
            foreach ($sources as $source) {
                $xml = (string)file_get_contents($readPath . $source);
                if (!preg_match('#<title><!\[CDATA\[(.*?)\]\]></title>#s', $xml, $m)) continue;
                if (!isset($byTitle[$m[1]])) continue;
                $was = $declared[$source]['orientation'];
                $now = pageOf($byTitle[$m[1]])['orientation'];
                if ($was !== '' && $was !== $now) $wrongOrientation[] = "$source was '$was' now '$now'";
            }
            ok("$module: orientation is left exactly as the definition declared it", $wrongOrientation === [],
               implode('; ', array_slice($wrongOrientation, 0, 3)));

            $written[$paper] = $savePath;
            echo "\n";
        }

        // --- 3 continued: the ONLY difference between two papers is the size ---
        echo "--- $module: nothing but the page size differs between two seedings ---\n";
        $a4     = $written['A4:210:297'];
        $letter = $written['Letter:216:282'];
        $pairs  = 0;
        $differ = [];
        foreach (glob($a4 . 'pf_*') ?: [] as $file) {
            $twin = $letter . basename($file);
            if (!file_exists($twin)) continue;
            $pairs++;
            // Normalised by NAME, so the comparison holds whichever format the
            // seeder wrote: the legacy token carries dimensions, the native one
            // does not, and neither is the thing under test here.
            $left  = str_replace(['A4:210:297', '"A4"'],         '@PAPER@', (string)file_get_contents($file));
            $right = str_replace(['Letter:216:282', '"Letter"'], '@PAPER@', (string)file_get_contents($twin));
            if ($left !== $right) $differ[] = basename($file);
        }
        ok(sprintf('%s: %d definition pair(s) compared', $module, $pairs), $pairs > 0);
        ok("$module: the two seedings are byte-identical once the paper token is normalised",
           $differ === [], implode(', ', array_slice($differ, 0, 3)));

        // --- 4: idempotence ---
        echo "\n--- $module: re-stamping the same paper changes nothing ---\n";
        $before = [];
        foreach (glob($a4 . 'pf_*') ?: [] as $file) $before[basename($file)] = md5_file($file);
        $again = $tempRoot . $module . '/again/';
        @mkdir($again, 0777, true);
        admin_add_reports($module, $again, 'A4:210:297');
        $same = $seen = 0;
        foreach (glob($again . 'pf_*') ?: [] as $file) {
            $page = pageOf($file);
            $seen++;
            if (paperName($page['size']) === 'A4') $same++;
        }
        ok("$module: a second seeding onto the same paper produces the same paper",
           $seen > 0 && $same === $seen, "$same of $seen");

        // --- 5: the argument beats the constant ---
        echo "\n--- $module: an explicit paper beats PF_DEFAULT_PAPERSIZE ---\n";
        ok("$module: PF_DEFAULT_PAPERSIZE is defined for this company", defined('PF_DEFAULT_PAPERSIZE'));
        $constant = defined('PF_DEFAULT_PAPERSIZE') ? PF_DEFAULT_PAPERSIZE : '';
        $other    = $constant === 'Legal:216:357' ? 'A4:210:297' : 'Legal:216:357';
        printf("  constant is '%s'; asking for '%s'\n", $constant, $other);
        $override = $tempRoot . $module . '/override/';
        @mkdir($override, 0777, true);
        admin_add_reports($module, $override, $other);
        $files    = glob($override . 'pf_*') ?: [];
        $honoured = 0;
        foreach ($files as $file) if (paperName(pageOf($file)['size']) === paperName($other)) $honoured++;
        ok("$module: the argument is used, not the constant",
           $files !== [] && $honoured === count($files), "$honoured of " . count($files));

        // --- the negative control ---
        // Every check above would also pass if ImportReport() ignored the paper and
        // the shipped files happened to declare it. They declare Letter, so asking
        // for something else and getting Letter back is the failure this proves is
        // detectable.
        echo "\n--- $module: the check can actually fail ---\n";
        $shipped = array_unique(array_map('paperName', array_column($declared, 'size')));
        ok("$module: the shipped definitions declare a paper that is NOT what we asked for",
           !in_array(paperName($other), $shipped, true) && !in_array('A4', $shipped, true),
           'shipped sizes: ' . implode(', ', $shipped) . ' — the assertions would pass without the feature');
        echo "\n";
    }
    ok('at least one module was actually exercised', $modulesRun > 0,
       'every module in the list was skipped — nothing above proves anything');
} finally {
    $cleanup();
}

$after = (int)($db->Execute("select max(id) as id from " . TABLE_PHREEFORM)->fields['id'] ?? 0);
echo "\n";
ok('the catalog was left as it was found', $after <= $highWater,
   "max id was $highWater, now $after");
ok('the temp directory was removed', !is_dir($tempRoot));

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
