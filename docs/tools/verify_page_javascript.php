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
//  Path: /docs/tools/verify_page_javascript.php
//
/**
 * The document pages' javascript parses, and decides correctly what to save.
 *
 * A broken script in FreeBooks does not announce itself. The file fails to
 * parse, every function in it goes undefined, and the page comes up looking
 * ordinary until a button does nothing. The buttons this covers are Print and
 * Save on orders, invoices, receipts and work orders, so "does nothing" would
 * be somebody unable to invoice a customer.
 *
 * Three checks, and they are not the same claim:
 *
 *  1. the standalone scripts PARSE - node --check on the files as shipped;
 *  2. every BRANCH of each js_include.php parses. These are PHP that prints
 *     javascript, and they print a different program per action, per query
 *     string, and after a failed post. render_page_js.php renders each branch
 *     so node can be pointed at it. This is the check that catches an edit to
 *     one branch breaking another, which is the mistake this shape of file
 *     invites. Each inline script is checked under the rules its own <script>
 *     tag asks for: the form canvas is a `type="module"`, and checking a module
 *     as a classic script rejects its import and reports a fault that is not
 *     there;
 *  3. the document-modified tracking DECIDES correctly - js/verify_document_
 *     modified.js runs the real scripts against a stub DOM and walks the states
 *     that decide whether opening a document rewrites it (issue #64).
 *
 * The first two say the code runs. Only the third says it is right, and only
 * about that one behaviour. Nothing here replaces opening the page.
 *
 *   php docs/tools/verify_page_javascript.php
 *
 * Takes no company and opens no database, so it runs on the development machine
 * as readily as in the container. It does need node: without it the parse and
 * behaviour checks cannot run and are reported as skipped rather than passed,
 * because a check that did not run has not passed.
 */

require_once(__DIR__ . '/render_page_js.php');   // which requires node_check.php

$failures = 0;
$skipped  = 0;

function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if (!$cond) $failures++;
    printf("  [%s] %s%s\n", $cond ? 'PASS' : 'FAIL', $what, $detail !== '' ? " ($detail)" : '');
}
function skip(string $what, string $why): void {
    global $skipped;
    $skipped++;
    printf("  [--] %s - skipped: %s\n", $what, $why);
}

$node = fb_find_node();   // node_check.php
if ($node === null) {
    echo "node was not found on this machine.\n\n";
} else {
    printf("node %s (%s)\n\n", fb_node_version($node), $node);
}

// The scripts every document page loads with a <script src>. A syntax error in
// one of these takes out every page that loads it, not just one.
$standalone = [
    'trunk/includes/common.js',
    'trunk/modules/phreebooks/javascript/orders.js',
    'trunk/modules/phreebooks/javascript/banking.js',
];

echo "Scripts as shipped\n";
foreach ($standalone as $rel) {
    $full = fb_repo_root() . '/' . $rel;
    if (!is_file($full)) { ok($rel, false, 'missing'); continue; }
    if ($node === null)  { skip($rel, 'no node'); continue; }
    // These are loaded with a plain <script src>, so classic rules.
    $r = fb_node_check($node, file_get_contents($full), false);
    ok($rel, $r['ok'], $r['error']);
}

echo "\nJavascript printed by each js_include.php, branch by branch\n";
$stem = sys_get_temp_dir() . '/fb_page_js_' . getmypid();
foreach (fb_page_js_pages() as $page => $spec) {
    foreach ($spec['variants'] as $v) {
        $label = sprintf('%s: action=%s%s%s', $page,
            $v['action'] === '' ? '(none)' : $v['action'],
            $v['get']   === '' ? '' : ' ' . $v['get'],
            $v['error'] ? ' after a failed post' : '');

        foreach (glob($stem . '.*') as $stale) @unlink($stale);
        // A separate process per branch: the renderer defines constants, and a
        // constant cannot be defined twice with different values.
        $cmd = sprintf('FB_POST_FAILED=%s php %s %s %s %s %s 2>&1',
            $v['error'] ? '1' : '0',
            escapeshellarg(__DIR__ . '/render_page_js.php'),
            escapeshellarg($page), escapeshellarg($v['action']),
            escapeshellarg($v['get']), escapeshellarg($stem));
        $out = []; exec($cmd, $out, $status);
        $text = trim(implode(' ', $out));
        if ($status !== 0 || preg_match('/Fatal error|Parse error/', $text)) {
            ok($label, false, 'would not render: ' . substr($text, 0, 120));
            continue;
        }
        $files = glob($stem . '.*');
        sort($files);
        if (!$files) { ok($label, false, 'rendered no javascript'); continue; }
        if ($node === null) { skip($label, 'no node'); continue; }

        // One line per branch however many scripts it emitted, but the flavour
        // of each is honoured — and named, when there is more than one, since
        // "the module block" is the useful half of a failure report.
        $bad = [];
        foreach ($files as $f) {
            $is_module = str_ends_with($f, '.mjs');
            $r = fb_node_check($node, file_get_contents($f), $is_module);
            if (!$r['ok']) $bad[] = ($is_module ? 'module: ' : 'classic: ') . $r['error'];
        }
        $shape = count($files) > 1
            ? sprintf('%d scripts', count($files))
            : (str_ends_with($files[0], '.mjs') ? 'module' : '');
        ok($label, $bad === [], $bad ? implode('; ', $bad) : $shape);
    }
}
foreach (glob($stem . '.*') as $stale) @unlink($stale);

echo "\nWhat opening a document does to it (issue #64)\n";
// work_orders keeps printDocument() inside its js_include.php rather than in a
// script of its own, so the suite needs that branch rendered. Rendered here,
// for itself: hanging on to a file from the loop above coupled this to the
// order of the page table, and adding a page after work_orders deleted it.
$wo_stem = sys_get_temp_dir() . '/fb_wo_js_' . getmypid();
$rendered_work_orders = null;
exec(sprintf('php %s work_orders edit %s %s 2>&1',
     escapeshellarg(__DIR__ . '/render_page_js.php'), escapeshellarg(''), escapeshellarg($wo_stem)));
$wo_files = glob($wo_stem . '.*');
if ($wo_files) { sort($wo_files); $rendered_work_orders = $wo_files[0]; }

$suite = __DIR__ . '/js/verify_document_modified.js';
if ($node === null) {
    skip('document-modified tracking', 'no node');
} elseif (!is_file($suite)) {
    ok('document-modified tracking', false, 'suite is missing: ' . $suite);
} else {
    $cmd = escapeshellarg($node) . ' ' . escapeshellarg($suite)
         . ($rendered_work_orders ? ' ' . escapeshellarg($rendered_work_orders) : '') . ' 2>&1';
    $out = []; exec($cmd, $out, $status);
    foreach ($out as $line) {
        // The suite already speaks this dialect; pass it through indented, and
        // drop its own terminal marker so this file has only one.
        if (preg_match('/^(PASS|FAIL) - /', $line)) continue;
        echo ($line === '' ? '' : '  ') . $line . "\n";
    }
    if ($status !== 0) $failures++;
}
foreach (glob($wo_stem . '.*') as $stale) @unlink($stale);

if ($skipped > 0) {
    printf("\n%d check(s) skipped. Install node to run them; a skipped check has not passed.\n", $skipped);
}
echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
