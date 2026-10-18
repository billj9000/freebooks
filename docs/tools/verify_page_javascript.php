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
 * Five checks, and they are not the same claim:
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
 *     that decide whether opening a document rewrites it (issue #64);
 *  4. every script of OURS is included with a version. A script served from an
 *     unchanging URL can be answered from a browser's cache indefinitely, and
 *     the deploy that changes it changes nothing the browser can see. Pair that
 *     with a caller that IS fresh - a versioned file, or the page's own inline
 *     script - and the caller reaches a function the cached copy does not have.
 *     That is not hypothetical: it took the invoice page down in production the
 *     morning after issue #64 shipped, when a versioned orders.js met a cached
 *     common.js.
 *  5. every window.open() asks for its size in bare integers. The feature
 *     string is not CSS and "width=700px" is not a width; browsers read the
 *     leading digits today, and a stricter one would drop the size and hand
 *     Firefox a TAB where a popup was meant. Print opens a popup, so this is
 *     the same button again.
 *
 * The first two say the code runs. Only the third says it is right, and only
 * about that one behaviour. The last two say nothing about the code at all -
 * they are about what the browser is asked for, which is where the two faults
 * that reached users both came from. Nothing here replaces opening the page.
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

// ---------------------------------------------------------------------------
echo "\nEvery script of ours is served from a versioned URL\n";

// Third-party code, which we do not change, so a stale copy is a stale copy of
// the same thing. Ours must carry a version; theirs may or may not.
$vendored = [
    'ext/', 'jodit/', 'vendor/',
    'includes/datatables.min.js',
    'modules/phreedom/includes/wz_tooltip/',
    'modules/phreedom/includes/qz-tray/',
    'modules/doc_ctl/includes/wunderbaum/',
    'extensions/Modules/zencart/ZenCartFiles/',   // ZenCart's own, run on their side
    'extensions/Modules/doc_ctl/includes/jstree/',
    // Third-party (BigDump, Alexey Ozerov, GPL 2009). It pulls in common.js
    // but calls nothing out of it, so a stale copy there breaks nothing, and
    // editing somebody else's file to satisfy our convention is not worth it.
    'modules/phreedom/includes/bigdump/',
];
$bare = [];
$scanned = 0;
foreach (['trunk', 'extensions'] as $tree) {
    $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(fb_repo_root() . '/' . $tree));
    foreach ($dir as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $rel = substr($file->getPathname(), strlen(fb_repo_root()) + 1);
        foreach ($vendored as $skip) if (str_contains($rel, $skip)) continue 2;
        $body = (string)file_get_contents($file->getPathname());
        if (!str_contains($body, '<script')) continue;
        $scanned++;
        if (!preg_match_all('#<script\b[^>]*\bsrc\s*=\s*"([^"]+)"#i', $body, $m, PREG_SET_ORDER)) continue;
        foreach ($m as $hit) {
            $src = $hit[1];
            if (!preg_match('#\.js(\?|$|"|<)#i', $src) && !str_contains($src, '.js')) continue;
            foreach ($vendored as $skip) if (str_contains($src, $skip)) continue 2;
            if (str_contains($src, '?')) continue;              // versioned somehow
            // Which line, so the report points at something.
            $line = 0;
            foreach (explode("\n", $body) as $i => $text) {
                if (str_contains($text, $src)) { $line = $i + 1; break; }
            }
            $bare[] = sprintf('%s:%d  %s', $rel, $line, $src);
        }
    }
}
ok(sprintf('no unversioned script of ours (%d file(s) with a <script> scanned)', $scanned),
   $bare === []);
foreach ($bare as $b) printf("         %s\n", $b);

// ---------------------------------------------------------------------------
echo "\nEvery window.open() asks for a size the browser will honour\n";

// window.open()'s third argument is not CSS. Its values are parsed as bare
// integers, so "width=700px" states a size in a unit the feature string has no
// notion of. Browsers currently read the leading digits and discard the rest,
// which is how every popup in FreeBooks opened at the right size for years
// while asking for the wrong thing. The reason to care is not tidiness: a
// parser that stopped being generous would drop the size feature entirely, and
// Firefox opens a window.open with NO size features as a TAB rather than a
// popup. The symptom would not be a mis-sized window - it would be the Print
// form appearing somewhere the person was not looking for it.
$sized  = ['width', 'height', 'top', 'left', 'screenX', 'screenY',
           'innerWidth', 'innerHeight', 'outerWidth', 'outerHeight'];
$united = [];
$looked = 0;
foreach (['trunk', 'extensions'] as $tree) {
    $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(fb_repo_root() . '/' . $tree));
    foreach ($dir as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'html'], true)) continue;
        $rel = substr($file->getPathname(), strlen(fb_repo_root()) + 1);
        foreach ($vendored as $skip) if (str_contains($rel, $skip)) continue 2;
        $body = (string)file_get_contents($file->getPathname());
        if (!str_contains($body, 'window.open')) continue;
        $looked++;
        foreach (explode("\n", $body) as $i => $text) {
            // Only lines that are part of a feature string. An unquoted HTML
            // attribute - <td width=100%> - matches the unit pattern and is no
            // such thing, so the line has to name window.open itself or carry
            // one of the flags that appear nowhere else. A feature string split
            // across lines is caught by the second test; one split so that a
            // continuation carries neither is not, and would have to be seen by
            // eye.
            if (!str_contains($text, 'window.open')
                && !preg_match('/\b(resizable|scrollbars|toolbar|menubar|location|directories|status)=/', $text)) continue;
            if (!preg_match_all('/\b(' . implode('|', $sized) . ')\s*=\s*-?\d+\s*([a-zA-Z%]+)/', $text, $m, PREG_SET_ORDER)) continue;
            foreach ($m as $hit) $united[] = sprintf('%s:%d  %s=<n>%s', $rel, $i + 1, $hit[1], $hit[2]);
        }
    }
}
ok(sprintf('every window feature is a bare integer (%d file(s) with a window.open scanned)', $looked),
   $united === []);
foreach ($united as $u) printf("         %s\n", $u);

// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
echo "\nThe tax a document screen shows is the tax the ledger posts (issue #118)\n";
// Acceptance scenario A6 posts these documents through the ledger and states,
// after each one, what every account it moved holds. A document's tax is how far
// the tax accounts moved on it, and its total how far the customer's or vendor's
// account did, so the screens are held to the ledger's own figures.
$a6 = require __DIR__ . '/acceptance/scenarios/a6_tax.php';
$tax_docs = array('rates' => array(), 'documents' => array());
foreach ($a6['tax_rates'] as $name => [$type, $auth_names]) {
    $auths = array();
    foreach ($auth_names as $auth) $auths[] = array('id' => $auth, 'rate' => $a6['tax_auths'][$auth][0]);
    $tax_docs['rates'][$name] = array('rate' => array_sum(array_column($auths, 'rate')), 'auths' => $auths);
}
$held = array();   // each account as the step before left it
$sold = array();   // invoice label => its line, for a credit memo against it
foreach ($a6['steps'] as $step) {
    $moved = array();
    foreach ($step['expect'] as $acct => $value) {
        if (!is_numeric($value) || $acct === 'customer_balance') continue;
        $moved[$acct] = $value - ($held[$acct] ?? 0);
        $held[$acct]  = $value;
    }
    $tax = 0;
    foreach ($moved as $acct => $delta) if (str_starts_with($acct, 'tax_')) $tax += abs($delta);
    $doc = null;
    if ($step['do'] === 'sell') {
        $line = array('amount' => $step['qty'] * $step['price'], 'rate' => $step['tax'] ?? null);
        $sold[$step['label']] = array('price' => $step['price'], 'rate' => $line['rate']);
        $doc = array('journal' => '12', 'account' => 'c', 'lines' => array($line),
                     'freight' => $step['freight'] ?? 0, 'freight_rate' => $step['freight_tax'] ?? null,
                     'discount' => $step['order_discount'] ?? 0, 'total' => abs($moved['ar'] ?? 0));
    } elseif ($step['do'] === 'credit') {
        $inv = $sold[$step['invoice']];
        $doc = array('journal' => '13', 'account' => 'c', 'total' => abs($moved['ar'] ?? 0),
                     'lines' => array(array('amount' => $step['qty'] * $inv['price'], 'rate' => $inv['rate'])));
    } elseif ($step['do'] === 'buy') {
        $doc = array('journal' => '6', 'account' => 'v', 'total' => abs($moved['ap'] ?? 0),
                     'lines' => array(array('amount' => $step['qty'] * $step['price'], 'rate' => $step['tax'] ?? null)));
    }
    if ($doc) $tax_docs['documents'][] = $doc + array('label' => 'A6 ' . $step['label'], 'tax' => round($tax, 2));
}
$tax_suite = __DIR__ . '/js/verify_document_tax.js';
if ($node === null) {
    skip('document tax', 'no node');
} else {
    $tax_json = sys_get_temp_dir() . '/fb_doc_tax_' . getmypid() . '.json';
    file_put_contents($tax_json, json_encode($tax_docs));
    $out = []; exec(escapeshellarg($node) . ' ' . escapeshellarg($tax_suite) . ' ' . escapeshellarg($tax_json) . ' 2>&1', $out, $status);
    foreach ($out as $line) {
        if (preg_match('/^(PASS|FAIL) - /', $line)) continue;
        echo ($line === '' ? '' : '  ') . $line . "\n";
    }
    if ($status !== 0) $failures++;
    @unlink($tax_json);
}

if ($skipped > 0) {
    printf("\n%d check(s) skipped. Install node to run them; a skipped check has not passed.\n", $skipped);
}
echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
