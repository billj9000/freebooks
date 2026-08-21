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
//  Path: /docs/tools/verify_forms_pages.php
//
/**
 * Render each Forms page for real, and fail on anything it emits.
 *
 * Replaces verify_forms_page_bootstrap.php, which only replayed a page's
 * `require` lines. That caught the missing definitions-directory constant but
 * could not catch the two defects behind it — a missing `PAGE_TITLE`, and a
 * toolbar entry invented by assigning into `icon_list['new']`, which leaves the
 * entry without an `order` key and makes `array_multisort()` in
 * `build_toolbar()` fatal. Both are in the page BODY, and the only way to catch
 * a defect in a page body is to run the body.
 *
 * So this does what index.php does: set `DIR_FS_WORKING`, include
 * `pre_process.php`, then `template_main.php` and `js_include.php`, with output
 * buffered. `validate_user()` needs nothing but `$_SESSION['admin_security']`,
 * so a session is cheap to stand up honestly rather than stubbing the function.
 *
 * **Warnings are failures here.** An undefined array key or a deprecated call is
 * how every one of these defects announced itself before it became a fatal on
 * someone's screen, and a tool that prints them without failing is a tool whose
 * output gets skimmed.
 *
 * What it does NOT cover: `template_index.php`, which wraps the page and is what
 * reads `PAGE_TITLE`. That contract is asserted statically instead.
 *
 * One page per process — a constant cannot be undefined once set, so a page that
 * forgets to load something would otherwise be covered by a page that did.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_pages.php [company] [page]
 */

// 'run' and 'printrun' are not pages: they are the action branches of 'main' and
// 'print', driven through parameters taken from what was actually rendered or
// proven elsewhere. Listing them means the suite exercises what clicking the
// button does, rather than only that the button is wired.
const FORMS_PAGES = ['main', 'datasets', 'datasetsnew', 'admin', 'design', 'designpick', 'canvas', 'canvaspick', 'receipt', 'print', 'printgroup', 'run', 'printrun'];

if (!isset($argv[2])) {
    $failed = 0;
    foreach (FORMS_PAGES as $page) {
        passthru(sprintf('%s %s %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__),
            escapeshellarg((string)($argv[1] ?? '')), escapeshellarg($page)), $status);
        if ($status !== 0) $failed++;
    }
    echo "\n" . ($failed === 0 ? "PASS\n" : "FAIL - $failed page(s) failed.\n");
    exit($failed === 0 ? 0 : 1);
}

// In run mode, buffering starts before ANYTHING is printed — including the
// bootstrap's own "company:" line. HttpDelivery refuses to deliver once output
// has begun, which is right of it (a document appended to a half-rendered page
// is a corrupt download), and in a CLI that means the harness's own chatter
// would look like the run branch failing. It did, twice.
if (in_array($argv[2] ?? '', ['run', 'printrun'], true)) ob_start();

require_once(__DIR__ . '/bootstrap_freebooks.php');
// No page bootstrap from the harness: a page must load whatever it uses, which
// is how a missing definitions directory went unseen while every other tool
// reported green.
$company = fb_bootstrap(($argv[1] ?? '') !== '' ? $argv[1] : null, false);
$PAGE = (string)$argv[2];

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/**
 * The body of the event handler registered at $from, and nothing after it.
 *
 * Every check that reads a handler has to be bounded to that handler, or it
 * quietly reads the rest of the file: an unbounded `[\s\S]*` finds the thing it
 * is looking for in the NEXT handler and reports the code correct, while a
 * fixed character window finds nothing and reports correct code broken. Both
 * have already happened here.
 *
 * Braces are matched from the first one after $from, skipping quoted strings so
 * a brace inside a CSS or JSON literal cannot close the body early — and skipping
 * COMMENTS, because prose is not code. An apostrophe in an ordinary English
 * possessive opened a string that ran to the next quote in real source, the
 * matcher lost count of the braces, the body ran on into the handler below, and a
 * correct file was reported broken. That is the same failure as the two above,
 * from a third direction: the bound was wrong, not the code.
 */
function handlerBody(string $js, int $from): string
{
    $open = strpos($js, '{', $from);
    if ($open === false) return '';
    $depth = 0;
    $n = strlen($js);
    for ($i = $open; $i < $n; $i++) {
        $c = $js[$i];
        if ($c === '/' && $i + 1 < $n && $js[$i + 1] === '/') {
            $i = strpos($js, "\n", $i);
            if ($i === false) break;
            continue;
        }
        if ($c === '/' && $i + 1 < $n && $js[$i + 1] === '*') {
            $end = strpos($js, '*/', $i + 2);
            if ($end === false) break;
            $i = $end + 1;
            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            for ($i++; $i < $n && $js[$i] !== $c; $i++) if ($js[$i] === '\\') $i++;
            continue;
        }
        if ($c === '{') $depth++;
        elseif ($c === '}' && --$depth === 0) return substr($js, $open, $i - $open + 1);
    }
    return substr($js, $open);
}

if (!defined('MODULE_FORMS_STATUS')) {
    echo "  [--] Forms is not installed on this company; nothing to render.\n";
    exit(0);
}

// ── stand up what index.php gives a page ─────────────────────────────────────
$module = 'forms';
define('DIR_FS_WORKING', DIR_FS_MODULES . $module . '/');
$RUNNING  = $PAGE === 'run';
$PRINTING = $PAGE === 'printrun';
$CHOOSING = $PAGE === 'printgroup';
// The datasets EDITOR is a branch of the datasets page, and the list view is
// what the suite rendered until now — so the form that actually saves a dataset
// had never been rendered by anything. That is where the missing CSRF token
// was, and why nothing caught it.
$DEFINING = $PAGE === 'datasetsnew';
// The editor reached from the MENU, with no report named. That is the ordinary
// way in — the menu entry exists so the permission can be granted at all — and
// it is a different branch from the editor proper.
$PICKING  = $PAGE === 'designpick';
// The form canvas, and the same page reached from the menu with nothing chosen.
$CANVASPICK = $PAGE === 'canvaspick';
// The receipt editor. It has no chooser of its own: there is ONE list of forms
// and it is the canvas's, which links a receipt straight to this page (§13.28).
if ($RUNNING)  $PAGE = 'main';
if ($PRINTING) $PAGE = 'print';
if ($CHOOSING) $PAGE = 'print';
if ($DEFINING) $PAGE = 'datasets';
if ($PICKING)  $PAGE = 'design';
if ($CANVASPICK) $PAGE = 'canvas';

// Every security id at full rights: this checks that a page RUNS, not who may
// open it. Permission is asserted separately, by reading the id each page
// demands (below).
$_SESSION['admin_security'] = array_fill(0, 1000, 4);
$_SESSION['admin_id']       = 1;
$_SESSION['admin_display']  = 'check';
// application_top.php mints this on every request, and html_form() only emits
// the hidden field when it is set. Without it here, a form built correctly and
// a form built by hand look identical — and index.php refuses a POST from
// either, so the difference is a page whose save can only 403.
$_SESSION['csrf_token']     = str_repeat('0', 64);
$_GET = $_POST = $_REQUEST = [];

// The print page is opened FROM a document, so with no parameters it correctly
// says nothing was asked for. The parameters come from verify_forms_forms.php,
// which publishes one (form, record) pair it has already rendered — so what is
// driven here is a pair known to produce a document, and a failure is this
// page's rather than a fixture's.
// Per company: the ids inside are only meaningful against the database they
// were taken from (see verify_forms_forms.php, where they are published).
$printTarget = sys_get_temp_dir() . '/forms-print-target-' . $company;
if ($PAGE === 'print') {
    // Self-sufficient: a suite that only passes when another tool ran first is a
    // suite that fails for the wrong reason and gets ignored. If the pair is not
    // published yet, publish it — by running the tool that owns that knowledge,
    // rather than by keeping a second copy of it here.
    if (!file_exists($printTarget)) {
        exec(sprintf('%s %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/verify_forms_forms.php'),
            escapeshellarg((string)($argv[1] ?? ''))));
    }
    if (!file_exists($printTarget)) {
        echo "  [--] no form on this company produced a record; nothing to print\n";
        exit(0);
    }
    $source = $CHOOSING ? sys_get_temp_dir() . '/forms-print-group-' . $company : $printTarget;
    if (!file_exists($source)) {
        echo "  [--] no multi-form group on this company; nothing to choose between\n";
        exit(0);
    }
    parse_str((string)file_get_contents($source), $printParams);
    $_GET = $_REQUEST = $printParams;
    // module and page are part of a real request, and gen_get_all_get_params()
    // reads $_GET — a link built without them points at the default page.
    $_GET['module'] = $_REQUEST['module'] = 'forms';
    $_GET['page']   = $_REQUEST['page']   = 'print';
    if ($PRINTING) $_REQUEST['action'] = $_GET['action'] = 'view';
}

// The editor is opened FROM a report, so with no rID it correctly says nothing
// was chosen — which would render a page that proves nothing. The id comes from
// the catalog rather than being hard-coded: a fixture id that no longer exists
// is a check that reports a green nothing.
if ($PAGE === 'canvas' && $CANVASPICK) {
    $_GET = $_REQUEST = ['module' => 'forms', 'page' => 'canvas'];
    $_SESSION['messageToStack'] = [];
} elseif ($PAGE === 'canvas') {
    // A FORM, not a report: the canvas declines a report on purpose.
    $row = $db->Execute("select id from " . TABLE_PHREEFORM
        . " where doc_type <> '0' and doc_ext = 'frm' order by id limit 1");
    if (!$row || $row->EOF) {
        echo "  [--] no form definition on this company; nothing to lay out\n";
        exit(0);
    }
    $_GET = $_REQUEST = ['module' => 'forms', 'page' => 'canvas', 'rID' => (string)$row->fields['id']];
} elseif ($PAGE === 'receipt') {
    // A SERIAL form, which the id has to be found by loading: a receipt is
    // known by its stored layout, not by its group — pos:rcpt is not reserved
    // and a company may have written its own.
    // The directory is computed here rather than read from FORMS_DIR_DEFINITIONS,
    // and the module defaults that define it are deliberately NOT required:
    // defining that constant before the page runs is exactly what let a page
    // missing its own bootstrap render an empty chooser and still pass. The harness must not
    // supply what the page is supposed to supply (see the page contract note).
    require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
    $rcptDir     = DIR_FS_MY_FILES . ($_SESSION['company'] ?? '') . '/phreeform/';
    $rcptCatalog = new \FreeBooks\Forms\Catalog\Catalog();
    \FreeBooks\Forms\Catalog\ShippedDatasets::registerAll($rcptCatalog);
    $rcptLoader  = new \FreeBooks\Forms\Definition\DefinitionLoader($rcptCatalog, $rcptDir);

    $rcptId = 0;
    $rows = $db->Execute("select id from " . TABLE_PHREEFORM
        . " where doc_type <> '0' and doc_ext = 'frm' order by id");
    while ($rows && !$rows->EOF) {
        try {
            if ($rcptLoader->load((int)$rows->fields['id'])->isSerial()) {
                $rcptId = (int)$rows->fields['id'];
                break;
            }
        } catch (\FreeBooks\Forms\Definition\DefinitionException) {}
        $rows->MoveNext();
    }
    if ($rcptId === 0) {
        echo "  [--] no receipt printer form on this company; nothing to edit\n";
        exit(0);
    }
    $_GET = $_REQUEST = ['module' => 'forms', 'page' => 'receipt', 'rID' => (string)$rcptId];
} elseif ($PAGE === 'design' && $PICKING) {
    // No rID at all: what a menu click sends.
    $_GET = $_REQUEST = ['module' => 'forms', 'page' => 'design'];
    $_SESSION['messageToStack'] = [];
} elseif ($PAGE === 'design') {
    $row = $db->Execute("select id from " . TABLE_PHREEFORM
        . " where doc_type <> '0' and doc_ext <> 'frm' order by id limit 1");
    if (!$row || $row->EOF) {
        echo "  [--] no report definition on this company; nothing to edit\n";
        exit(0);
    }
    $_GET = $_REQUEST = ['module' => 'forms', 'page' => 'design', 'rID' => (string)$row->fields['id']];
}

if ($DEFINING) {
    // action=new renders the editor without needing a stored dataset, so this
    // works on a company that has never defined one.
    $_GET = $_REQUEST = ['module' => 'forms', 'page' => 'datasets', 'action' => 'new'];
}

/**
 * Where two value graphs first differ, or none.
 *
 * NOT serialize(): that encodes object identity as well as value, so two
 * structurally identical definitions compare unequal because one shared a
 * Style instance where the other built two.
 *
 * @return list<string>
 */
function fb_definition_differences(mixed $a, mixed $b, string $path = 'definition', int $limit = 6): array
{
    if (is_object($a) && is_object($b)) {
        if ($a::class !== $b::class) return ["$path is " . $a::class . ' vs ' . $b::class];
        if ($a instanceof \UnitEnum) return $a === $b ? [] : ["$path: different case"];
        return fb_definition_differences((array)$a, (array)$b, $path, $limit);
    }
    if (is_object($a) !== is_object($b)) {
        return [sprintf('%s is %s vs %s', $path, is_object($a) ? $a::class : var_export($a, true),
            is_object($b) ? $b::class : var_export($b, true))];
    }
    if (is_array($a) && is_array($b)) {
        if (array_keys($a) !== array_keys($b)) return ["$path has " . count($a) . ' vs ' . count($b) . ' entries'];
        $found = [];
        foreach ($a as $key => $value) {
            $found = array_merge($found, fb_definition_differences($value, $b[$key], "$path.$key", $limit));
            if (count($found) >= $limit) break;
        }
        return array_slice($found, 0, $limit);
    }
    return $a === $b ? [] : ["$path: " . var_export($a, true) . ' vs ' . var_export($b, true)];
}

gen_pull_language('phreedom');          // TEXT_CANCEL etc., used by the toolbar
$toolbar = new toolbar();

// ── record everything PHP has to say ─────────────────────────────────────────
// Attributed by the file that raised it. Rendering a Forms page walks through
// PhreeForm and three other modules' language files, and those carry warnings of
// their own that predate this work — failing on them would make the tool's
// output something to skim past rather than act on. Forms' own files are the
// failure; the rest is reported as inherited.
$problems  = [];   // raised inside modules/forms
$inherited = [];   // raised anywhere else while rendering it
set_error_handler(function (int $no, string $message, string $file, int $line)
        use (&$problems, &$inherited): bool {
    $where  = sprintf('%s in %s:%d', $message, basename($file), $line);
    $isOurs = str_contains(str_replace('\\', '/', $file), '/modules/forms/');
    if ($isOurs) $problems[] = $where; else $inherited[$where] = true;
    return true;   // keep going: one page usually has more than one thing wrong
});

// Printing a form ends the same way: bytes, then exit(). Same shutdown-handler
// shape as the run branch below, and for the same reason.
if ($PRINTING) {
    register_shutdown_function(function () use (&$problems) {
        $delivered = (string)ob_get_clean();
        echo "\n=== the print page delivers a form ===\n";
        ok('it delivered bytes', $delivered !== '', 'nothing was sent');
        ok('the bytes are a PDF', str_starts_with($delivered, '%PDF'),
           'first bytes: ' . var_export(substr($delivered, 0, 24), true));
        ok('the PDF is substantial', strlen($delivered) > 2000, strlen($delivered) . ' bytes');
        ok('Forms\' own files emitted nothing while printing', $problems === [],
           implode(' | ', array_slice($problems, 0, 4)));
        global $failures;
        echo "\n" . ($failures === 0 ? "  printrun: ok\n" : "  printrun: FAILED $failures check(s)\n");
        exit($failures === 0 ? 0 : 1);
    });

    ob_clean();   // drop the bootstrap's chatter, keep the buffer open
    require(DIR_FS_WORKING . 'pages/print/pre_process.php');   // delivers, then exits
    ob_end_clean();
    ok('the print page delivered rather than falling through to the screen', false,
       'it rendered the screen instead of sending a document');
    echo "\n  printrun: FAILED $failures check(s)\n";
    exit(1);
}

// Running a document ends in exit(), after the bytes have been delivered — so
// the assertions live in a shutdown handler, which is the only thing that runs
// afterwards. Anything less would be checking that the page did not crash on
// its way to producing nothing.
if ($RUNNING) {
    $delivered = '';
    register_shutdown_function(function () use (&$delivered, &$problems, &$inherited) {
        $delivered = (string)ob_get_clean();
        global $following;
        echo "\n=== the main page's run branch delivers a document ===\n";
        echo "  following: " . (string)$following . "\n";
        ok('it delivered bytes', $delivered !== '', 'nothing was sent');
        ok('the bytes are a PDF', str_starts_with($delivered, '%PDF'),
           'first bytes: ' . var_export(substr($delivered, 0, 24), true));
        ok('the PDF is substantial', strlen($delivered) > 2000, strlen($delivered) . ' bytes');
        ok('Forms\' own files emitted nothing while running it', $problems === [],
           implode(' | ', array_slice($problems, 0, 4)));
        global $failures;
        echo "\n" . ($failures === 0 ? "  run: ok\n" : "  run: FAILED $failures check(s)\n");
        exit($failures === 0 ? 0 : 1);
    });

    // The URL comes from what the main page actually rendered, so what is
    // exercised is the link a user clicks rather than one composed here.
    $urlFile = sys_get_temp_dir() . '/forms-run-url';
    if (!file_exists($urlFile)) {
        echo "  [FAIL] no run URL published; run the 'main' page check first\n";
        exit(1);
    }
    $query = [];
    parse_str((string)parse_url((string)file_get_contents($urlFile), PHP_URL_QUERY), $query);
    $query['format'] = 'pdf';
    $query['dates']  = 'a';
    $_GET = $_REQUEST = $query;
    $following = http_build_query($query);

    // Drop the bootstrap's output but keep the buffer OPEN, so nothing has been
    // sent and the page's own delivery can proceed.
    ob_clean();
    require(DIR_FS_WORKING . 'pages/main/pre_process.php');   // delivers, then exits
    // Only reached if the run branch did NOT deliver.
    ob_end_clean();
    ok('the run branch delivered rather than falling through to the list', false,
       'it returned to the document list instead of sending a document');
    echo "\n  run: FAILED $failures check(s)\n";
    exit(1);
}

// In the order template_index.php uses, which is NOT the order they are named
// in. js_include.php is emitted inside the <HEAD>, before <body> and before the
// page's own markup — so the script runs while none of the elements it works on
// exist yet. Rendering it last, as this did, produced a document no browser ever
// sees and hid a page whose every button was dead: a top-level getElementById
// returned null, the guard that tested it bailed, and nothing was ever wired up.
$output  = '';
$script  = '';
$prelude = '';
$fatal   = null;
try {
    ob_start();
    require(DIR_FS_WORKING . 'pages/' . $PAGE . '/pre_process.php');
    $prelude = (string)ob_get_clean();
    ob_start();
    require(DIR_FS_WORKING . 'pages/' . $PAGE . '/js_include.php');
    $script = (string)ob_get_clean();
    ob_start();
    require(DIR_FS_WORKING . 'pages/' . $PAGE . '/template_main.php');
    $output = $prelude . $script . (string)ob_get_clean();
} catch (\Throwable $e) {
    $output = $prelude . $script . (string)ob_get_clean();
    $fatal  = get_class($e) . ': ' . $e->getMessage()
            . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}
restore_error_handler();

echo "\n=== modules/$module/pages/$PAGE renders ===\n";
ok('it completes without throwing', $fatal === null, (string)$fatal);
ok('Forms\' own files emit no warnings, notices or deprecations', $problems === [],
   implode(' | ', array_slice($problems, 0, 6)));
if ($inherited) {
    printf("  [--] %d inherited warning(s) from outside modules/forms, not counted:\n",
        count($inherited));
    foreach (array_slice(array_keys($inherited), 0, 5) as $note) echo "         $note\n";
}
ok('it produces markup', strlen($output) > 500, strlen($output) . ' bytes');
ok('the toolbar rendered', str_contains($output, 'tb_main_'), 'no toolbar in the output');
// '<h1' rather than '<h1>': a heading carrying Bootstrap classes is still a
// heading, and requiring the bare tag made a correctly-styled page fail.
ok('the page heading rendered', str_contains($output, '<h1'));

// An editor's Cancel goes back to the list of things IT edits — the canvas to
// the forms it lays out, the report editor to the reports it edits. Both used
// to point at the document list, which is right only if that is where the
// author came from; from Tools -> Form Layout it threw the author out of the
// section they were working in. Read from the rendered toolbar, because that
// is the markup the browser acts on.
// Anchored on the icon rather than on `location.href`: the handler is written
// onclick="location.href = '…'", so a pattern that refuses quote characters
// stops dead at the opening one and reports a correct page as broken. It did.
if (in_array($PAGE, ['canvas', 'design', 'receipt'], true) && !$CANVASPICK && !$PICKING) {
    preg_match('/tb_icon_cancel[^>]*/', $output, $cancel);
    $target = $cancel[0] ?? '';
    // The canvas returns to its own chooser, which is the only list a form
    // appears on; the report editor returns to the Reports list, which is the
    // only list a report appears on. Same rule, different page — the editor's
    // own chooser was retired because it duplicated that list.
    //
    // The RECEIPT editor returns to the form list too, and that is the same
    // rule again rather than an exception: a receipt is listed there beside the
    // page forms. Leaving this page out of the check is why it shipped pointing
    // at the Reports list, which a receipt never appears on.
    $home = $PAGE === 'design' ? 'page=main' : 'page=canvas';
    ok('Cancel returns to the list this document appears on',
       str_contains($target, $home),
       $target === '' ? 'no cancel icon in the toolbar' : $target);
    // Back to the LIST, not to the document just left — that would be a Cancel
    // that cancels nothing.
    ok('and not to the document it was editing', !str_contains($target, 'rID='));
}

// ── the page's script runs in the HEAD, so it may not touch the page yet ─────
//
// This is the one contract the render cannot show, because a parsed document has
// no notion of when a line ran. template_index.php emits js_include.php inside
// the <head>: at that moment <body> does not exist, and every getElementById in
// the file returns null. A script that acts on the page immediately is therefore
// dead on arrival — silently, with no error in the log and every button inert.
//
// Three ways to be safe, and the check accepts any of them: wait for
// DOMContentLoaded, register with the framework's own addLoadEvent(), or be a
// `type="module"` script, which the browser defers by definition. Delegating on
// `document` is safe too, since `document` exists in the head — so a file that
// never reaches for an element at all has nothing to answer for.
//
// What counts as "at the top level" needs care, because the wrapper this module
// favours is an IIFE — and the body of `(function () { … })()` runs immediately
// even though it sits one brace deep. So the scan measures brace depth, and
// treats depth 1 as the top when the file OPENS with an IIFE. A lookup inside a
// function declared at that level is fine: declaring it is not calling it.
$actsBeforeTheBody = function (string $html): bool {
    preg_match_all('#<script\b([^>]*)>(.*?)</script>#si', $html, $blocks, PREG_SET_ORDER);
    foreach ($blocks as $block) {
        if (stripos($block[1], 'type="module"') !== false) continue;   // deferred by definition
        $code = $block[2];
        // An IIFE's body is the top level, one brace down.
        $opener = ltrim(preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code));
        $top    = preg_match('/^[(!+]\s*(async\s+)?(function|\()/', $opener) ? 1 : 0;
        $depth  = 0;
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $two = substr($code, $i, 2);
            if ($two === '//') { while ($i < $n && $code[$i] !== "\n") $i++; continue; }
            if ($two === '/*') { $end = strpos($code, '*/', $i); $i = $end === false ? $n : $end + 1; continue; }
            if ($code[$i] === '"' || $code[$i] === "'" || $code[$i] === '`') {
                $quote = $code[$i++];
                while ($i < $n && $code[$i] !== $quote) { if ($code[$i] === '\\') $i++; $i++; }
                continue;
            }
            if ($code[$i] === '{') { $depth++; continue; }
            if ($code[$i] === '}') { $depth--; continue; }
            if ($depth !== $top) continue;
            if (substr($code, $i, 15) === 'getElementById(') return true;
            if (substr($code, $i, 14) === 'querySelector(') return true;
            if (substr($code, $i, 17) === 'querySelectorAll(') return true;
        }
    }
    return false;
};
// ── every privilege this module defines must be grantable ───────────────────
//
// phreedom's roles page builds itself by walking $mainmenu and drawing one row
// per entry that names a security id. An id named by NO entry appears nowhere on
// that page, so no role can be given it, so the page it guards is unreachable —
// for everybody, including whoever installed the module. Nothing errors; the
// feature is simply not there.
//
// This became worth asserting when the Report Editor's menu entry was removed:
// it was safe only because the form canvas names the same id. Remove that one
// too and the report editor, the canvas and every author privilege go with it.
if ($PAGE === 'main' && !$RUNNING) {
    $granted = [];
    foreach ($mainmenu as $section) {
        foreach ((array)($section['submenu'] ?? []) as $entry) {
            if (($entry['security_id'] ?? '') !== '') $granted[(int)$entry['security_id']] = true;
        }
    }
    foreach (['SECURITY_ID_FORMS_RUN'      => SECURITY_ID_FORMS_RUN,
              'SECURITY_ID_FORMS_AUTHOR'   => SECURITY_ID_FORMS_AUTHOR,
              'SECURITY_ID_FORMS_DATASETS' => SECURITY_ID_FORMS_DATASETS] as $name => $id) {
        ok("$name is named by a menu entry, so a role can be given it",
           isset($granted[$id]), 'no entry names it; the roles page cannot offer it');
    }
}

// The detector is tested on the two shapes it exists to tell apart, because a
// checker that answers "safe" to everything passes every page and protects none
// — and this one is a hand-written scanner over JavaScript, which is exactly the
// sort of thing that quietly stops matching.
if ($PAGE === 'main' && !$RUNNING) {
    $iife  = '<script>(function () { const a = document.getElementById("x"); })();</script>';
    $ready = '<script>document.addEventListener("DOMContentLoaded", function () {'
           . ' const a = document.getElementById("x"); });</script>';
    $inFn  = '<script>function go() { return document.querySelector(".x"); }</script>';
    $quote = '<script>(function () { const s = "getElementById("; })();</script>';
    ok('the timing check catches an IIFE that acts at once', $actsBeforeTheBody($iife));
    ok('and clears one that waits for the document',        !$actsBeforeTheBody($ready));
    ok('and clears a lookup inside a function it declares', !$actsBeforeTheBody($inFn));
    ok('and is not fooled by the name in a string',         !$actsBeforeTheBody($quote));
}
if ($script !== '') {
    ok('the page script waits for the document before reaching into it',
       !$actsBeforeTheBody($script),
       'it calls into the page at the top level, where nothing has been parsed yet');
}

// ── every element the script reaches for is an element the page emits ───────
//
// The sibling of the timing check above, and it catches the same failure from
// the other end: that one asks whether the script runs too early to find the
// page, this one asks whether what it is looking for is there at all.
//
// Both exist because of the same afternoon. "None of the new buttons do
// anything" was a script reaching for controls it could not reach — and a
// getElementById that finds nothing returns null SILENTLY, so the page renders,
// the console stays quiet, and the button is simply dead. The failure has no
// symptom a test can see except this one: compare the names the script asks for
// against the names the markup carries.
//
// Only literal, single-argument lookups are checked. An id built at runtime is
// not a name this can know, and there is no point guessing at one.
//
// The names are compared against the page's SOURCE as well as its output,
// because one render is one branch. The canvas draws either the list of forms
// or the editor, never both, and its script serves both — so half its controls
// are legitimately absent from any given rendering, and a check that read only
// the output would report six dead controls that work perfectly. A name that is
// genuinely mistyped is in neither, which is the distinction worth drawing.
if ($script !== '') {
    preg_match_all('/getElementById\(\s*[\'"]([A-Za-z0-9_\-]+)[\'"]\s*\)/', $script, $asked);
    $wanted = array_values(array_unique($asked[1]));

    // What the page CAN emit: every literal id in its own template, plus every
    // id this rendering actually produced — which covers the ones written by
    // shared helpers rather than by the template itself.
    $canEmit = $output . (string)@file_get_contents(
        DIR_FS_WORKING . 'pages/' . $PAGE . '/template_main.php');
    preg_match_all('/\bid\s*=\s*[\'"]([A-Za-z0-9_\-]+)[\'"]/', $canEmit, $has);
    $present = array_flip($has[1]);

    // A form's id is its NAME: html_form() writes `name` and `id` from the same
    // first argument, so the template says `html_form('forms_doc_action', ...)`
    // and never the word `id` at all.
    preg_match_all('/html_form\(\s*[\'"]([A-Za-z0-9_\-]+)[\'"]/', $canEmit, $forms);
    foreach ($forms[1] as $name) $present[$name] = true;

    $missing = [];
    foreach ($wanted as $id) if (!isset($present[$id])) $missing[] = $id;

    ok('every id the page script looks up is one the page can emit', $missing === [],
       implode(', ', $missing) . ' — a lookup that finds nothing returns null'
       . ' silently, which is a dead control and a quiet console');
    echo '  [--] ' . count($wanted) . " id(s) looked up, all accounted for\n";

    // The same rule one level down, for controls a script finds by attribute
    // rather than by id. A grid built from a <template> has no ids at all — its
    // rows are cloned, so every one of them would carry the same one — and it
    // reaches its controls with `[data-col=heading]`. A name misspelled on
    // either side is a field that silently never saves, which is the id bug
    // wearing different clothes.
    preg_match_all('/\[data-col=[\'"]?([A-Za-z0-9_\-]+)[\'"]?\]/', $script, $reads);
    $readNames = array_values(array_unique($reads[1]));
    if ($readNames !== []) {
        preg_match_all('/\bdata-col\s*=\s*[\'"]([A-Za-z0-9_\-]+)[\'"]/', $canEmit, $written);
        $writtenNames = array_flip($written[1]);

        $unwired = [];
        foreach ($readNames as $name) if (!isset($writtenNames[$name])) $unwired[] = $name;
        ok('every control the script reads by data-col is one the markup carries',
           $unwired === [], implode(', ', $unwired));
        echo '  [--] ' . count($readNames) . " data-col name(s) read, all present\n";
    }
}

// ── a format is chosen, not typed ───────────────────────────────────────────
//
// The Format control was free text until §13.31, and free text was the wrong
// shape for it: the vocabulary is closed at thirty names and an unknown one
// does nothing at all, silently. So wherever a page offers a format it must
// offer the LIST — a page that quietly went back to an input would put the
// silent failure back with it, and would look completely normal doing so.
//
// Checked against the rendered markup rather than the source, because what
// matters is what reached the browser.
if (preg_match('/\[format\]|data-col=[\'"]format[\'"]/', $output)) {
    $typed = (bool)preg_match(
        '/<input[^>]*(?:\[format\]|data-col=[\'"]format[\'"])[^>]*>/', $output);
    ok('a format is chosen from a list rather than typed', !$typed,
       'a text input still carries a format, where a misspelling fails in silence');

    // The groups are most of what makes twenty-five entries usable, and they
    // are the part html_pull_down_menu() cannot draw — so their absence is the
    // likely symptom of someone swapping the hand-written select back for it.
    ok('and the list is grouped', str_contains($output, '<optgroup'),
       'twenty-five formats in one flat list is a list nobody reads to the end of');

    // The empty option has to be there and has to be first: it is the commonest
    // answer, and a list without it cannot express "leave the value alone".
    ok('and it offers leaving the value as stored',
       (bool)preg_match('/<option value=""[^>]*>/', $output));
}

// ── the contract template_index.php imposes, which this render does not ──────
$source = (string)file_get_contents(DIR_FS_WORKING . 'pages/' . $PAGE . '/pre_process.php');
ok('the page defines PAGE_TITLE', defined('PAGE_TITLE') || str_contains($source, "define('PAGE_TITLE'"),
   'template_index.php echoes it unguarded, so the page is a fatal without it');
ok('the page gates on a security id', (bool)preg_match('/validate_user\(\s*SECURITY_ID_/', $source),
   'a page with no validate_user() is open to any logged-in user');

// ── the markup has to be markup ─────────────────────────────────────────────
//
// The three earlier defects were all fatals, so "it rendered" was enough to
// catch them. This one was not: the page rendered perfectly and emitted
//   onclick="formsRun("http://...", 'pdf')"
// where json_encode()'s double quote ended the attribute, leaving a button that
// highlights and does nothing. Producing output is not the same as producing
// working output, so the output is parsed.
echo "\n=== the markup parses, and its controls are wired ===\n";
$dom = new \DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML('<meta charset="utf-8">' . $output);
$malformed = [];
foreach (libxml_get_errors() as $error) {
    // Unknown/unclosed tags are noise from a fragment; an attribute error is not.
    if (stripos($error->message, 'attribute') !== false || stripos($error->message, 'quote') !== false) {
        $malformed[] = trim($error->message);
    }
}
libxml_clear_errors();
libxml_use_internal_errors($previous);
ok('no attribute in the output is malformed', $malformed === [],
   implode(' | ', array_slice($malformed, 0, 4)));

// An inline handler carrying a quoted string is the shape that broke; a handler
// whose value contains a bare double quote could not have survived parsing.
$rawHandlers = [];
if (preg_match_all('/\bon[a-z]+="([^"]*)"/i', $output, $matches)) {
    foreach ($matches[0] as $index => $whole) {
        // A handler that stops at a `(` with no `)` was truncated by its own quoting.
        $value = $matches[1][$index];
        if (substr_count($value, '(') !== substr_count($value, ')')) $rawHandlers[] = $whole;
    }
}
ok('no inline handler was truncated by its own quoting', $rawHandlers === [],
   implode(' | ', array_slice($rawHandlers, 0, 3)));

// Every function an inline handler NAMES must be reachable from global scope.
//
// A <script type="module"> has its own scope: nothing declared inside it is
// global, so `onclick="doThing()"` finds no such function and the button does
// NOTHING — no error on the page, no clue. The canvas is a module because
// pdf.js is one, and its Save, Delete and z-order buttons were all dead on
// arrival; the suite rendered the markup, never ran it, and reported the page
// as fine.
//
// This is a static check because the harness cannot execute JavaScript. It
// asks the only question it can: if the handler's script is a module, is the
// function put on `window` somewhere in the same output?
$unreachable = [];
if (preg_match('/<script[^>]*\btype\s*=\s*"module"/i', $output)) {
    if (preg_match_all('/\bon[a-z]+="\s*([A-Za-z_$][\w$]*)\s*\(/i', $output, $named)) {
        foreach (array_unique($named[1]) as $function) {
            // Anything the application defines elsewhere is fine; only a
            // function this page declares can be trapped in its module.
            if (!preg_match('/\bfunction\s+' . preg_quote($function, '/') . '\s*\(/', $output)) continue;
            if (!preg_match('/\bwindow\.' . preg_quote($function, '/') . '\s*=/', $output)) {
                $unreachable[] = $function;
            }
        }
    }
}
ok('every function an inline handler names can be reached from it', $unreachable === [],
   implode(', ', $unreachable) . ' — declared inside a module, so the handler sees nothing');

// index.php refuses ANY post without a csrf_token, so a form built by hand
// instead of through html_form() is a save that can only ever 403 — which is
// how it fails: not a wrong value, a whole page whose Save button does nothing.
// Asserted over every form on every page rather than per page, because the
// defect belongs to the shape, not to one template.
// Its own DOMXPath: the shared one is built further down, and this check sits
// with the other markup checks rather than with the per-page ones.
$forms     = new \DOMXPath($dom);
$tokenless = [];
foreach ($forms->query('//form') as $form) {
    if (strtolower((string)$form->getAttribute('method')) !== 'post') continue;
    $name = (string)($form->getAttribute('id') ?: $form->getAttribute('name') ?: '(unnamed)');
    if ($forms->query(".//input[@name='csrf_token']", $form)->length === 0) $tokenless[] = $name;
}
ok('every posting form carries a CSRF token', $tokenless === [], implode(', ', $tokenless));

// Every generated link must survive being read back by a browser.
//
// html_href_link() already separates parameters with &amp;, which is correct in
// an attribute. Wrapping it in htmlspecialchars() encodes that AGAIN, so the
// browser decodes &amp;amp; back to a literal "&amp;" and the parameter is
// named "amp;page" rather than "page". index.php then defaults to page=main and
// the button silently lands on the document list — which is what the Print link
// did from the day it shipped.
//
// The old check counted links matching 'page=print'; a double-encoded href
// still contains that text, so counting could never see it. Parsing can.
$brokenLinks = [];
foreach ($forms->query('//a[@href] | //*[@data-url]') as $node) {
    $raw = (string)($node->getAttribute('href') ?: $node->getAttribute('data-url'));
    if (!str_contains($raw, 'index.php?')) continue;
    // getAttribute() has already decoded entities once, exactly as a browser
    // does before it requests the URL.
    $query = [];
    parse_str((string)parse_url($raw, PHP_URL_QUERY), $query);
    foreach (array_keys($query) as $key) {
        if (str_starts_with((string)$key, 'amp;')) {
            $brokenLinks[] = substr($raw, strpos($raw, 'index.php'));
            break;
        }
    }
}
ok('every link parses into the parameters it names', $brokenLinks === [],
   implode(' | ', array_slice(array_unique($brokenLinks), 0, 3)));

// index.php picks the file to run from $_POST['module'] and $_POST['page'],
// BEFORE it looks at the query string. A control named for either decides where
// its own submission goes: the page-setup group was named
// page[size]/page[margin][...], so $_POST['page'] was an ARRAY and index.php
// went looking for modules/forms/pages/Array/pre_process.php. It broke the
// preview and the save together, and neither the form nor the URL looked wrong.
//
// `action` is deliberately NOT in this list. Posting it is the framework's own
// submit idiom — a hidden field set by submitToDo() — and every page that saves
// anything uses it. It selects a branch WITHIN a page rather than the page.
$reserved = ['module' => true, 'page' => true];
$collisions = [];
foreach ($forms->query('//input[@name] | //select[@name] | //textarea[@name]') as $control) {
    $name = (string)$control->getAttribute('name');
    // `page[size]` posts as $_POST['page'], so the base name is what matters.
    $base = explode('[', $name)[0];
    if (isset($reserved[$base])) $collisions[$name] = true;
}
ok('no form control is named what index.php routes on', $collisions === [],
   implode(', ', array_slice(array_keys($collisions), 0, 5)));

// Every runnable document must offer both formats, and each control must carry a
// usable absolute URL — the specific thing that was missing.
$xpath    = new \DOMXPath($dom);
$controls = $xpath->query("//*[contains(@class,'fb-run')]");
if ($PAGE === 'main') {
    $runnable = 0;
    $printable = 0;
    foreach ($forms_documents as $document) {
        if ($document['plan']->runnable())  $runnable++;
        if ($document['plan']->isForm())    $printable++;
    }
    ok(sprintf('every runnable document offers PDF and CSV (%d documents, %d controls)',
               $runnable, $controls->length),
       $controls->length === $runnable * 2);

    // A form is listed too, but it is printed rather than run: it gets one link
    // to the print page instead of two format buttons. Asserting the count
    // stops a regression that quietly turns forms back into tables.
    $printLinks = $xpath->query("//a[contains(@href,'page=print')]");
    ok(sprintf('every form offers a print link instead (%d forms, %d links)',
               $printable, $printLinks->length),
       $printLinks->length === $printable);

    // Emailing a report: the card, its five recipient fields, and one Email
    // button per runnable document. PhreeForm offered this and Forms did not,
    // so a missing control here is a report the user can only download.
    foreach (['from_email', 'to_email', 'to_name', 'cc_email', 'bcc_email',
              'subject', 'message_body'] as $field) {
        ok("the email card draws $field",
           $xpath->query("//form[@id='forms_run_form']//*[@name='" . $field . "']")->length === 1);
    }
    $emailButtons = $xpath->query("//*[contains(@class,'fb-options') and @data-email='1']");
    ok(sprintf('every runnable document offers Email (%d documents, %d buttons)',
               $runnable, $emailButtons->length),
       $emailButtons->length === $runnable);

    // Which document, and what it may be asked before it runs, are the ROW's
    // answers now — one document, several ways to run it, and repeating them on
    // each control is how they drift apart. So the row is what must be complete.
    $incomplete = [];
    foreach ($xpath->query("//tr[contains(@class,'fb-forms-row')][.//*[contains(@class,'fb-run')]]") as $tr) {
        $url = html_entity_decode((string)$tr->getAttribute('data-url'));
        if (!ctype_digit((string)$tr->getAttribute('data-doc-id'))
            || $url === '' || !filter_var($url, FILTER_VALIDATE_URL)
            || !str_contains($url, 'action=run')) {
            $incomplete[] = $url === '' ? '(empty)' : substr($url, 0, 60);
            continue;
        }
        // The row's own URL must impose NOTHING about dates or period. The
        // answers are added by the dialog when the user has seen them; a range
        // baked in here runs every report over it whatever its author drew,
        // which is what the page used to do with 'All'.
        if (str_contains($url, 'dates=') || str_contains($url, 'period=')) {
            $incomplete[] = 'a run URL imposes a date range: ' . substr($url, 0, 60);
        }
        // A flag that is neither 0 nor 1 reads as false in the browser, so a
        // document that must be asked for a GL account would be run without
        // one. It has to be present and it has to be one of the two.
        foreach (['needs-dates', 'needs-period', 'needs-gl', 'must-ask'] as $flag) {
            if (!in_array((string)$tr->getAttribute('data-' . $flag), ['0', '1'], true)) {
                $incomplete[] = 'data-' . $flag . ' on document '
                              . (string)$tr->getAttribute('data-doc-id');
            }
        }
    }
    ok('every runnable row names its document, its URL and what it may be asked',
       $incomplete === [], implode(' | ', array_slice($incomplete, 0, 3)));

    $formats = [];
    foreach ($controls as $control) {
        if (!in_array((string)$control->getAttribute('data-format'), ['pdf', 'csv'], true)) {
            $formats[] = 'one control has no format';
        }
    }
    ok('every run control names a format', $formats === []);

    // The run dialog. Its controls are named for the REQUEST, not for the page,
    // because they post with the email and are read into the URL for a
    // download — the same three answers reaching the runner by both routes.
    ok('the run dialog is drawn',
       $xpath->query("//div[@id='forms_run_dialog']")->length === 1);
    foreach (['dates', 'period', 'gl_account'] as $control) {
        ok("the dialog offers $control",
           $xpath->query("//div[@id='forms_run_dialog']//select[@name='" . $control . "']")->length === 1);
    }
    // The first date choice must send NOTHING. It is what lets the runner fall
    // back to the definition's own preference, so a report runs as its author
    // drew it; a value here would silently re-impose this page's idea of a date
    // range on every report, which is the bug this replaced.
    $first = $xpath->query("//div[@id='forms_run_dialog']//select[@name='dates']/option[1]");
    ok('leaving the date range alone sends nothing',
       $first->length === 1 && (string)$first->item(0)->getAttribute('value') === '');

    // The filter is a view control, and it must not be able to submit the page.
    // An <input> inside a form takes Enter as "submit", and the only submit this
    // page has sends an email.
    ok('the filter box cannot submit the run form',
       $xpath->query("//form[@id='forms_run_form']//*[@id='forms_filter']")->length === 0);

    // Every id the page's own script reaches for must be on the page. There is
    // no JavaScript engine here to run it in, so a renamed element cannot be
    // caught by exercising it — and getElementById returning null is silent
    // until the line that uses it, which is a button that does nothing. This is
    // the one check that stands in for running the script.
    preg_match_all("/getElementById\('([^']+)'\)/", $output, $wanted);
    $absent = [];
    foreach (array_unique($wanted[1]) as $id) {
        if ($xpath->query("//*[@id='" . $id . "']")->length === 0) $absent[] = $id;
    }
    ok(sprintf('every id the page\'s script reaches for exists (%d checked)',
               count(array_unique($wanted[1]))),
       $absent === [], implode(' | ', $absent));

    // The same for the classes it delegates on. A listener bound to a class the
    // markup no longer carries is a control that highlights and does nothing —
    // which is exactly how the run buttons failed once before.
    preg_match_all("/closest\('\.([a-z0-9-]+)'\)/", $output, $delegated);
    $unbound = [];
    foreach (array_unique($delegated[1]) as $class) {
        if ($xpath->query("//*[contains(@class,'" . $class . "')]")->length === 0) $unbound[] = $class;
    }
    ok(sprintf('every class the page delegates on is in the markup (%d checked)',
               count(array_unique($delegated[1]))),
       $unbound === [], implode(' | ', $unbound));

    // No alert(). A modal dialog that stops the page to say a field is wrong,
    // while the field itself says nothing, is the pattern this page replaced.
    ok('the page reports a bad answer on the control, not in an alert',
       !str_contains($output, 'alert('));
}

// ── the split: reports here, forms on the canvas, nothing lost between ───────
//
// Documents used to list both. A form had no action on it that worked — Print
// needs a record, which a list cannot supply, so it always landed on an
// explanation — and the Layout button duplicated the canvas's own chooser. The
// risk of splitting a list in two is a document that falls down the crack, so
// what is checked is COVERAGE, against the catalogue itself.
//
// The forms half is checked on the CHOOSER render, not on the editing one: with
// an rID the canvas shows one open form and no list at all, and a first version
// of this check asked the editor to list every form and called it a failure.
$listing = $CANVASPICK ? 'frm' : ($PAGE === 'main' && !$RUNNING ? 'rpt' : '');
if ($listing !== '') {
    // The words a node shows ITSELF, ignoring what its child elements add. A
    // title cell carries more than the title now — a warning sign for a
    // document Forms cannot serve, and the dataset serving it for the people
    // who write reports — and textContent would run all three together, which
    // reported every such document as missing from its own list.
    $ownText = function (\DOMNode $node): string {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) $text .= $child->nodeValue;
        }
        return trim((string)preg_replace('/\s+/', ' ', $text));
    };
    // A cell whose words are wrapped in a single control still has one label:
    // the folder headings are buttons so they can be operated from the keyboard.
    $labelOf = function (\DOMNode $node) use (&$labelOf, $ownText): string {
        $own = $ownText($node);
        if ($own !== '') return $own;
        $elements = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) $elements[] = $child;
        }
        return count($elements) === 1 ? $labelOf($elements[0]) : '';
    };

    // Read the TITLE CELL of each row, not the page text. Substring matching
    // over the whole render reported four forms as leaking onto the reports
    // page, because a report named "Purchase Orders by Vendor" contains the
    // form title "Purchase Order". Cells compare as whole values.
    $shown = [];
    foreach ($xpath->query('//tbody/tr') as $tr) {
        $cells = $xpath->query('./td', $tr);
        // One cell means a folder heading, which spans the row.
        if ($cells->length < 2) continue;
        $title = $labelOf($cells->item(0));
        if ($title !== '') $shown[$title] = true;
    }

    $expected = $strays = [];
    $rows = $db->Execute("select doc_title, doc_ext from " . TABLE_PHREEFORM . "
      where doc_type <> '0' and doc_ext in ('rpt', 'frm')");
    while ($rows && !$rows->EOF) {
        $title = (string)$rows->fields['doc_title'];
        if ($rows->fields['doc_ext'] === $listing) $expected[$title] = true;
        $rows->MoveNext();
    }
    // A company may name a report and a form alike, and that is not a leak — so
    // only titles belonging to the other kind ALONE can be judged.
    $rows = $db->Execute("select doc_title from " . TABLE_PHREEFORM . "
      where doc_type <> '0' and doc_ext = '" . ($listing === 'rpt' ? 'frm' : 'rpt') . "'");
    while ($rows && !$rows->EOF) {
        $title = (string)$rows->fields['doc_title'];
        if (!isset($expected[$title]) && isset($shown[$title])) $strays[] = $title;
        $rows->MoveNext();
    }

    $missing = array_keys(array_diff_key($expected, $shown));
    ok(sprintf('every %s in the catalogue is listed (%d)',
               $listing === 'rpt' ? 'report' : 'form', count($expected)),
       $expected !== [] && $missing === [], implode(' | ', array_slice($missing, 0, 4)));
    ok('and nothing of the other kind', $strays === [], implode(' | ', array_slice($strays, 0, 4)));

    // The codes are the reason this work happened: `cust:inv` sorted the list
    // and was printed as its own label, so an ordered page looked unordered.
    // Checked against the HEADING CELLS rather than the page text: a two-letter
    // code like `gl` occurs inside ordinary words, and matching those would fail
    // on prose rather than on a heading.
    $headings = [];
    foreach ($xpath->query('//tbody/tr') as $tr) {
        $cells = $xpath->query('./td', $tr);
        if ($cells->length !== 1) continue;        // a heading spans the row
        // ...but so does the line that says nothing matched what was typed,
        // which the page renders hidden and shows only when the filter empties
        // the list. A row the page starts by hiding is not a heading.
        if (str_contains((string)$tr->getAttribute('class'), 'd-none')) continue;
        $text = $labelOf($cells->item(0));
        if ($text !== '') $headings[] = $text;
    }
    ok('the list is grouped under headings', $headings !== [], 'none found');

    // Which groups CAN be named, and which have no folder row to name them.
    $named = $orphans = [];
    $rows = $db->Execute("select doc_group, doc_title from " . TABLE_PHREEFORM . "
      where doc_type = '0'");
    while ($rows && !$rows->EOF) {
        $named[(string)$rows->fields['doc_title']] = true;
        $rows->MoveNext();
    }
    $rows = $db->Execute("select distinct d.doc_group from " . TABLE_PHREEFORM . " d
      where d.doc_type <> '0' and d.doc_ext = '" . $listing . "'
        and not exists (select 1 from " . TABLE_PHREEFORM . " f
                        where f.doc_type = '0' and f.doc_group = d.doc_group)");
    while ($rows && !$rows->EOF) { $orphans[] = (string)$rows->fields['doc_group']; $rows->MoveNext(); }

    // A heading is either a folder's name, or the raw group of a document filed
    // where no folder was ever created. The second is a gap in the CATALOGUE —
    // `asset` holds a report and has no folder row on either company — and the
    // page shows the code because inventing a name would be a guess. Reported
    // rather than failed: the fix is a row of data, not a line of code.
    $unexplained = array_values(array_filter($headings,
        fn($h) => !isset($named[$h]) && !in_array($h, $orphans, true)));
    ok('every heading is a folder name, or a group with no folder row',
       $unexplained === [], implode(' | ', array_slice($unexplained, 0, 4)));
    if ($orphans) {
        printf("  [--] %d group(s) with documents but no folder row, shown by code: %s\n",
            count($orphans), implode(', ', $orphans));
    }
}

// The URL the 'run' mode follows, taken from what this render produced. Passed
// through a file rather than re-including the page in the run process: a second
// include re-defines the page's constants, and the noise buries what is being
// checked.
if ($CHOOSING) {
    // The chooser branch. Its links were built by concatenating 'rID=' onto
    // gen_get_all_get_params(), which returns no trailing separator — so the id
    // fused with the previous value, never arrived, and clicking simply
    // re-rendered this page. Nothing else in the suite touched it, because
    // every other check drives rID directly.
    $links = $xpath->query("//a[contains(@class,'btn')]");
    ok('it offers a choice of forms', $links->length > 1, $links->length . ' link(s)');
    $bad = [];
    foreach ($links as $link) {
        $href = html_entity_decode((string)$link->getAttribute('href'));
        $query = [];
        parse_str((string)parse_url($href, PHP_URL_QUERY), $query);
        if (!filter_var($href, FILTER_VALIDATE_URL)
            || ($query['module'] ?? '') !== 'forms'
            || ($query['page'] ?? '') !== 'print'
            || !ctype_digit((string)($query['rID'] ?? ''))
            || !ctype_digit((string)($query['xmin'] ?? ''))) {
            $bad[] = substr($href, -70);
        }
    }
    ok('every choice links to this page with a numeric rID, and leaves xmin intact',
       $bad === [], implode(' | ', array_slice($bad, 0, 3)));
}

if ($PAGE === 'print' && !$CHOOSING) {
    // The page's job is to say what it is about to print and offer a way to
    // print it. An empty selection here would mean RecordSelector failed on the
    // very parameters a Print button sends.
    ok('it names the form it will print', str_contains($output, '<h1'));
    ok('it reports a non-empty record selection',
       !str_contains($output, FORMS_PRINT_NO_RECORDS),
       'the page says nothing was selected');
    foreach (['view', 'download', 'email'] as $action) {
        ok("it offers the $action action", str_contains($output, "formsPrint('$action')"));
    }
    ok('the form posts back to itself', (bool)preg_match('/<form[^>]+name="forms_print"/', $output));

    // The same recipients the document list offers. Two email forms in one
    // module that differ in who can be copied is a difference nobody can
    // explain to a user.
    foreach (['from_email', 'to_email', 'to_name', 'cc_email', 'bcc_email'] as $field) {
        ok("it offers $field", $xpath->query("//*[@name='" . $field . "']")->length === 1);
    }
}

if ($PAGE === 'admin') {
    // The settings page is only worth having if the controls it draws are the
    // ones the application reads. A control named for a different key is a
    // setting that appears to work and changes nothing — the failure this whole
    // page exists to make impossible.
    //
    // The three renderer defaults are here because PhreeForm's admin tab is
    // where they used to be, and that tab goes with the module. A setting with
    // nowhere to set it is not a setting.
    //
    // The installer's key list is what the SAVE branch iterates, so it is the
    // list the page is held to rather than one written here: a key in one and
    // not the other is a control that cannot be saved, or a setting with no
    // control at all.
    require_once(DIR_FS_MODULES . 'forms/classes/install.php');
    $keys = array_diff(array_keys((new forms_admin())->keys), forms_admin::HIDDEN_KEYS);
    ok('the installer ships some settings to draw', $keys !== []);

    $undrawn = [];
    foreach ($keys as $key) {
        $field = strtolower($key);
        if ($xpath->query("//select[@name='$field']")->length
            || $xpath->query("//input[@name='$field']")->length) continue;
        $undrawn[] = $key;
    }
    ok('every setting the save branch writes has a control on the page',
       $undrawn === [], implode(', ', $undrawn));

    // And the paper control offers what Forms can actually draw on, rather
    // than PhreeForm's "A4:210:297" spelling, which no native definition uses.
    require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
    $paper = $xpath->query("//select[@name='forms_default_paper']");
    if ($paper->length === 1) {
        $values = [];
        foreach ($xpath->query(".//option", $paper->item(0)) as $option) {
            $values[] = (string)$option->getAttribute('value');
        }
        ok('the paper control offers the sizes Forms knows, by name',
           $values === array_keys(forms_paper_sizes()), implode(',', $values));
    }
}

if ($PICKING) {
    // Arriving from the menu is not a failure, and must not be reported as one:
    // a red "No report was chosen to edit" blames the author for the click that
    // brought them here. The page answers with a list instead.
    $errors = [];
    foreach ((array)($_SESSION['messageToStack'] ?? []) as $message) {
        if (($message['type'] ?? '') === 'error') $errors[] = strip_tags((string)$message['text']);
    }
    ok('opening the editor from the menu raises no error', $errors === [],
       implode(' | ', array_slice($errors, 0, 3)));

    // And it lists NOTHING. This page used to answer an empty rID with a chooser
    // of its own — a second list of the same reports the Reports page lists, and
    // labelled with the raw doc_group codes that page stopped showing. It now
    // says which list to use and links to it.
    $editLinks = $xpath->query("//a[contains(@href,'page=design') and contains(@href,'rID=')]");
    ok('it lists no reports of its own', $editLinks->length === 0,
       $editLinks->length . ' link(s), where the chooser used to be');
    ok('it sends the author to the list that does',
       $xpath->query("//a[contains(@href,'page=main')]")->length > 0);
}

if ($PAGE === 'design' && !$PICKING) {
    // The editor is only worth having if the controls it draws are the ones the
    // save reads. A select whose name does not match what DefinitionForm looks
    // for is a field that appears to edit something and changes nothing — the
    // same class of defect the settings page check exists for.
    foreach (['title', 'paper[size]', 'paper[orientation]', 'group[by]', 'dates[default]'] as $name) {
        ok("it draws the $name control",
           $xpath->query("//*[@name='" . $name . "']")->length === 1);
    }
    $columnKeys = $xpath->query("//select[starts-with(@name,'column[') and contains(@name,'[key]')]");
    ok('it draws one field selector per column', $columnKeys->length > 0,
       $columnKeys->length . ' column control(s)');

    // The palette is the dataset's fields and nothing else (§13.4). An option
    // naming something the dataset does not declare would be the page inviting
    // exactly what DefinitionForm then refuses.
    if ($columnKeys->length > 0 && $forms_design_dataset !== null) {
        $declared = $forms_design_dataset->fields;
        $strangers = [];
        foreach ($xpath->query('.//option', $columnKeys->item(0)) as $option) {
            $value = (string)$option->getAttribute('value');
            if ($value === '') continue;
            if (!isset($declared[$value])
                && !isset($forms_design_definition->resolvedColumns[$value])) $strangers[] = $value;
        }
        ok('every field the palette offers is one the dataset declares', $strangers === [],
           implode(', ', array_slice($strangers, 0, 5)));
    }

    // ── the round trip, through what the BROWSER would send ─────────────────
    //
    // verify_forms_designer.php asserts that applying DefinitionForm::toRequest()
    // gives the definition back. That is the class checked against itself, and
    // it passed while the page was losing a field on every column: the template
    // rendered no control for `pageBreak`, so an untouched save posted nothing
    // for it, every column stopped ending its band, and all five collapsed into
    // one stacked column. toRequest() had the value; the FORM did not.
    //
    // So the POST is built here from the rendered controls, the way a browser
    // builds it — an unchecked box contributes nothing, a select contributes its
    // selected option — and the result must still be the definition that was
    // loaded.
    $post = [];
    foreach ($xpath->query("//form[@id='forms_design_form']//input[@name] "
                         . "| //form[@id='forms_design_form']//select[@name]") as $control) {
        $name = (string)$control->getAttribute('name');
        if ($name === 'csrf_token') continue;
        if (strtolower($control->nodeName) === 'select') {
            $value = '';
            foreach ($xpath->query('.//option', $control) as $option) {
                if ($option->hasAttribute('selected')) { $value = (string)$option->getAttribute('value'); break; }
            }
            // With nothing marked selected a browser submits the FIRST option.
            if ($value === '') {
                $first = $xpath->query('.//option', $control)->item(0);
                if ($first !== null) $value = (string)$first->getAttribute('value');
            }
        } else {
            $type = strtolower((string)$control->getAttribute('type'));
            // The two that do not always submit: an unchecked box sends nothing,
            // and a submit button only sends when it is the one clicked.
            if ($type === 'submit' || $type === 'button') continue;
            if (($type === 'checkbox' || $type === 'radio') && !$control->hasAttribute('checked')) continue;
            $value = (string)$control->getAttribute('value');
        }
        // "column[0][key]" -> $post['column'][0]['key'], as PHP itself does.
        parse_str($name . '=' . rawurlencode($value), $one);
        $post = array_replace_recursive($post, $one);
    }

    require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
    $binding  = new \FreeBooks\Forms\Definition\DefinitionForm();
    $rebuilt  = $binding->apply($forms_design_definition, $post, $forms_design_dataset);
    $lost     = fb_definition_differences($forms_design_definition, $rebuilt);
    ok('what the page posts back rebuilds the definition it loaded', $lost === [],
       implode('; ', $lost));
    ok('the page refuses none of its own values', $binding->warnings() === [],
       implode(' ', $binding->warnings()));

    // The preview posts the SAME form as the save, through formaction — two
    // forms would be two different reports previewed and saved.
    ok('the preview posts the editor form to the preview endpoint',
       (bool)preg_match('/formaction="[^"]*op=design_preview/', $output));
    ok('the preview target is the iframe on the page',
       str_contains($output, 'formtarget="forms_design_preview"')
       && str_contains($output, 'name="forms_design_preview"'));

}

if ($PAGE === 'canvas' && !$CANVASPICK) {
    // The canvas is a stack: a rasterized page, and boxes over it at the same
    // origin and scale. Both halves have to be there, and the boxes have to
    // carry millimetres — a box list in pixels would be right at one zoom.
    ok('it draws the page canvas', $xpath->query("//canvas[@id='forms_canvas_page']")->length === 1);
    ok('it draws the overlay layer', $xpath->query("//*[@id='forms_canvas_boxes']")->length === 1);
    ok('the overlay sits inside the same stack as the canvas',
       $xpath->query("//*[@id='forms_canvas_stack']//canvas[@id='forms_canvas_page']")->length === 1
       && $xpath->query("//*[@id='forms_canvas_stack']//*[@id='forms_canvas_boxes']")->length === 1);
    // A shift-click builds a group here, and the browser's own meaning for it
    // is "extend the text selection" — which paints a blue swathe over the
    // canvas and buries the selection that did happen. Half the fix is in the
    // handler; this is the other half, and it is markup, so it is checked here.
    $stackStyle = $xpath->query("//*[@id='forms_canvas_stack']")->length
        ? $xpath->query("//*[@id='forms_canvas_stack']")->item(0)->getAttribute('style') : '';
    ok('the stack does not let a shift-click select text',
       str_contains(str_replace(' ', '', $stackStyle), 'user-select:none'), $stackStyle);

    ok('it passes the elements to the page in millimetres',
       $forms_canvas_boxes !== [] && isset($forms_canvas_boxes[0]['x'], $forms_canvas_boxes[0]['y']),
       count($forms_canvas_boxes) . ' box(es)');
    // The editing controls (§13.7 step 4). Asserted against things that exist:
    // an earlier version of this check tested for the ABSENCE of 'tb_main_save'
    // to prove the canvas was read-only, and passed the whole time because the
    // toolbar numbers its icons — tb_main_0 — so the string was never there to
    // find. A check that cannot fail is not a check.
    ok('the canvas posts its elements back through a form',
       $xpath->query("//form[@id='forms_canvas_form']")->length === 1
       && $xpath->query("//*[@id='forms_canvas_fields']")->length === 1);
    ok('the save is wired to the canvas serializer',
       str_contains($output, 'formsCanvasSave()'),
       'the toolbar Save must submit what was dragged, not the loaded values');
    ok('it offers a snap setting', $xpath->query("//select[@name='forms_canvas_snap']")->length === 1);

    // The underlay is the last version DRAWN, so it goes stale the moment
    // anything moves — which is when it is most needed. Redrawing posts the
    // canvas as it stands and renders from that.
    ok('it offers to redraw the page',
       $xpath->query("//*[@id='forms_canvas_render']")->length === 1);
    ok('redrawing posts the canvas rather than fetching the stored form',
       str_contains($output, 'load(true)') && str_contains($output, "method: 'POST'"));
    // index.php refuses every POST without a token, so a redraw that did not
    // carry one could only ever 403 — and pdf.js reports that as an opaque
    // parse failure, which shows up as a blank page and no reason.
    ok('the redraw carries a CSRF token',
       str_contains($output, "input[name=csrf_token]"));
    ok('it can report what the redraw refused',
       $xpath->query("//*[@id='forms_canvas_warnings']")->length === 1);

    // Undo. Essential once editing is real: without it one wrong move means
    // abandoning a long edit and starting again.
    ok('it offers undo and redo',
       $xpath->query("//*[@id='forms_canvas_undo']")->length === 1
       && $xpath->query("//*[@id='forms_canvas_redo']")->length === 1);
    ok('both start disabled, because nothing has happened yet',
       $xpath->query("//*[@id='forms_canvas_undo'][@disabled]")->length === 1
       && $xpath->query("//*[@id='forms_canvas_redo'][@disabled]")->length === 1);

    // EVERY mutation must snapshot, or undo skips the one edit the author most
    // wants back — and skipping is silent: the button still works, it just
    // steps over that change.
    foreach (['formsCanvasZ', 'formsCanvasDelete', 'formsCanvasAdd', 'inspectorChanged'] as $action) {
        ok("a change through $action is remembered first",
           (bool)preg_match('/function ' . $action . '\b[\s\S]{0,600}?\bremember\(\)/', $output),
           'undo would step over this change');
    }
    // A drag is ONE state, snapshotted at the first MOVEMENT — not on the press,
    // because a click that merely selects an element is not a change, and not on
    // every pointermove, which would fill the history with a hundred states
    // nobody meant to make. So the snapshot sits behind the same flag that says
    // whether this drag has moved anything yet.
    $downAt = strpos($output, "'pointerdown'");
    $downBody = $downAt === false ? '' : handlerBody($output, $downAt);
    ok('a press that only selects makes no undo state',
       $downAt !== false && !str_contains($downBody, 'remember()'),
       'clicking about would fill the history with states nobody made');

    $moveAt   = strpos($output, "'pointermove'");
    $moveBody = $moveAt === false ? '' : handlerBody($output, $moveAt);
    ok('a drag is remembered once, at the first movement',
       $moveAt !== false
       && (bool)preg_match('/if \(!dragging\.moved\) \{ remember\(\); dragging\.moved = true; \}/',
                           $moveBody));
    ok('and not on every pointer move',
       $moveAt !== false && substr_count($moveBody, 'remember()') === 1);

    // Ctrl+Z inside a text box is the browser's own undo; taking it over would
    // make typing feel broken to solve a problem it does not have.
    ok('the keyboard shortcut leaves text boxes alone',
       (bool)preg_match('/INPUT\|TEXTAREA\|SELECT/', $output));
    ok('a new edit abandons the redo path',
       str_contains($output, 'future.length = 0'));

    // Redrawing as the author works, with the button kept for a slow machine.
    ok('it offers to redraw as the author works',
       $xpath->query("//*[@id='forms_canvas_live']")->length === 1);
    // Every change has to reach the scheduler, or the switch is on and some
    // edits still leave the picture stale — worse than no live redraw at all,
    // because the author stops checking.
    foreach (['formsCanvasZ', 'formsCanvasDelete', 'formsCanvasAdd', 'inspectorChanged'] as $action) {
        ok("a change through $action schedules a redraw",
           (bool)preg_match('/function ' . $action . '\b.*?\btouched\s*\(/s', $output),
           'the switch would be on and this change would not show');
    }
    // A drag redraws on RELEASE. Firing on pointermove would spend a PDF per
    // pixel to redraw a page that is under the pointer.
    //
    // Both halves are bounded to the handler's OWN body. A single regex with /s
    // searches to the end of the file, so "pointermove … touched(" matched the
    // release handler further down and the check failed on correct code; and a
    // fixed character window around 'pointerup' failed the same way as soon as
    // the release handler grew a second branch.
    $upAt   = strpos($output, "'pointerup'");
    $upBody = $upAt === false ? '' : handlerBody($output, $upAt);
    ok('a drag redraws when the pointer is released, not while it moves',
       $moveAt !== false && !str_contains($moveBody, 'touched(')
       && $upAt !== false && str_contains($upBody, 'touched(0)'),
       'redrawing on every pointermove would spend a PDF per pixel');
    // An older answer must not paint over a newer one.
    ok('a superseded redraw is abandoned rather than allowed to land',
       str_contains($output, 'AbortController') && str_contains($output, "'AbortError'"));
    ok('it draws the inspector', $xpath->query("//*[@id='forms_canvas_inspector']")->length === 1);
    foreach (['ins_label', 'ins_x', 'ins_y', 'ins_w', 'ins_h', 'ins_bind', 'ins_font', 'ins_size',
              'ins_colour', 'ins_bordercolour', 'ins_align', 'ins_visible',
              'ins_bold', 'ins_italic'] as $control) {
        ok("the inspector draws $control", $xpath->query("//*[@id='" . $control . "']")->length === 1);
    }

    // The font is a stored token, not a name to be typed. A free-text box gives
    // an author no way to know that the default face is spelled "freeserif".
    $fontOptions = [];
    foreach ($xpath->query("//select[@id='ins_font']/option") as $option) {
        $fontOptions[] = (string)$option->getAttribute('value');
    }
    ok('the font is chosen from a list, not typed',
       $xpath->query("//select[@id='ins_font']")->length === 1);
    ok('the list offers the faces the document can be drawn in',
       array_diff(array_keys(forms_font_names()), $fontOptions) === [],
       implode(', ', array_diff(array_keys(forms_font_names()), $fontOptions)));
    ok('and an empty option, for the report default', in_array('', $fontOptions, true));

    // The palette is the whole of §13.4 for a form: an author picks a declared
    // name or nothing. An option naming something the form cannot supply would
    // be the page inviting exactly what TemplateForm then refuses.
    $offered = [];
    foreach ($xpath->query("//select[@id='ins_bind']/option") as $option) {
        $value = (string)$option->getAttribute('value');
        if ($value !== '') $offered[] = $value;
    }
    ok('the binding palette offers something', $offered !== [], count($offered) . ' name(s)');
    $strangers = array_diff($offered, array_keys($forms_canvas_bindings));
    ok('every name the palette offers is one this form can supply', $strangers === [],
       implode(', ', array_slice($strangers, 0, 5)));

    // Adding is limited to the kinds that need no sub-structure: a Table or a
    // FieldBlock created here would have no columns and no parts.
    require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
    // Read through the DOM, which decodes entities exactly as a browser does.
    // Searching the raw output for the kind name found nothing, because a
    // correctly escaped attribute is not the string that was written.
    $addable = [];
    foreach ($xpath->query("//*[contains(@class,'fb-canvas-add')]") as $button) {
        $addable[] = (string)$button->getAttribute('data-add');
    }
    foreach (\FreeBooks\Forms\Definition\TemplateForm::ADDABLE as $kind) {
        ok("it offers to add a '$kind'", in_array($kind, $addable, true));
    }

    // The PDF comes from the endpoint by URL, so a page naming the wrong op
    // renders an empty white rectangle and no error.
    ok('it points at the canvas PDF endpoint',
       str_contains($output, 'op=canvas_pdf'));
    ok('the endpoint exists', is_file(DIR_FS_MODULES . 'forms/ajax/canvas_pdf.php'));

    // An image element can be pointed at a file, and the file has to be able to
    // get there. Shown only to an author who may save — the endpoint refuses at
    // the same level — so the two are checked together rather than separately.
    // Read from the session rather than from the page's own $security_level:
    // that variable belongs to the scope pre_process ran in, and a check that
    // silently read null would call every level "may not save" and pass.
    $mayEdit = (int)($_SESSION['admin_security'][SECURITY_ID_FORMS_AUTHOR] ?? 0) > 2;
    $chooser = $xpath->query("//input[@type='file' and @id='ins_upload']");
    ok('an author who may save is offered a way to add an image',
       !$mayEdit || $chooser->length === 1, (string)$chooser->length);
    // The other direction — that a lower level is NOT offered it — cannot be
    // checked here: this harness renders every page at level 4, so the test
    // would pass without ever running. What matters anyway is the endpoint
    // rather than the button, and verify_forms_image_upload.php calls that at
    // level 1 and watches it refuse.
    if ($chooser->length === 1) {
        // Nameless on purpose: it sits inside the urlencoded form that saves the
        // canvas, and a named file input posts its FILENAME as a stray field on
        // every save. The upload builds its own FormData.
        ok('the file input is not part of the save',
           !$chooser->item(0)->hasAttribute('name'));
        ok('it points at the upload endpoint', str_contains($output, 'op=canvas_image'));
        ok('the upload endpoint exists',
           is_file(DIR_FS_MODULES . 'forms/ajax/canvas_image.php'));
    }
}

if ($CANVASPICK) {
    $formCount = 0;
    $rows = $db->Execute("select count(*) as n from " . TABLE_PHREEFORM
        . " where doc_type <> '0' and doc_ext = 'frm'");
    if ($rows && !$rows->EOF) $formCount = (int)$rows->fields['n'];

    $errors = [];
    foreach ((array)($_SESSION['messageToStack'] ?? []) as $message) {
        if (($message['type'] ?? '') === 'error') $errors[] = strip_tags((string)$message['text']);
    }
    ok('opening the canvas from the menu raises no error', $errors === [],
       implode(' | ', array_slice($errors, 0, 3)));
    $links = $xpath->query("//a[contains(@href,'page=canvas') and contains(@href,'rID=')]");
    ok('it offers the forms it can lay out', $links->length > 0, $links->length . ' link(s)');

    // Only forms. A report chosen here would land on a page that declines it.
    $offered = [];
    foreach ($links as $link) {
        $query = [];
        parse_str((string)parse_url(html_entity_decode((string)$link->getAttribute('href')),
            PHP_URL_QUERY), $query);
        if (isset($query['rID'])) $offered[] = (int)$query['rID'];
    }
    $reportIds = [];
    $rows = $db->Execute("select id from " . TABLE_PHREEFORM . " where doc_ext <> 'frm'");
    while ($rows && !$rows->EOF) { $reportIds[] = (int)$rows->fields['id']; $rows->MoveNext(); }
    ok('no report is offered in the form chooser', array_intersect($offered, $reportIds) === []);

    // This is the ONE list of forms (§13.28), so a receipt has to be ON it and
    // has to lead to the editor that can edit it. Both halves matter: a receipt
    // missing from the list is unreachable, and a receipt linked to the canvas
    // lands on a page that redirects — which works, but means the list did not
    // know what it was listing.
    //
    // The directory is computed rather than read from FORMS_DIR_DEFINITIONS, and
    // the module defaults that define it are deliberately not required here:
    // supplying a constant the PAGE is supposed to supply is what let an empty
    // chooser pass once already.
    require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
    $rcptCatalog = new \FreeBooks\Forms\Catalog\Catalog();
    \FreeBooks\Forms\Catalog\ShippedDatasets::registerAll($rcptCatalog);
    $rcptLoader  = new \FreeBooks\Forms\Definition\DefinitionLoader($rcptCatalog,
        DIR_FS_MY_FILES . ($_SESSION['company'] ?? '') . '/phreeform/');

    $serialIds = [];
    $rows = $db->Execute("select id from " . TABLE_PHREEFORM
        . " where doc_type <> '0' and doc_ext = 'frm' order by id");
    while ($rows && !$rows->EOF) {
        try {
            if ($rcptLoader->load((int)$rows->fields['id'])->isSerial()) {
                $serialIds[] = (int)$rows->fields['id'];
            }
        } catch (\FreeBooks\Forms\Definition\DefinitionException) {}
        $rows->MoveNext();
    }

    ok('every form this company has is offered',
       count($offered) + count($xpath->query("//a[contains(@href,'page=receipt') and contains(@href,'rID=')]"))
           >= $formCount,
       "$formCount stored");

    if ($serialIds === []) {
        printf("  [--] no receipt printer form on this company; none to route\n");
    } else {
        $toReceipt = [];
        foreach ($xpath->query("//a[contains(@href,'page=receipt') and contains(@href,'rID=')]") as $link) {
            $query = [];
            parse_str((string)parse_url(html_entity_decode((string)$link->getAttribute('href')),
                PHP_URL_QUERY), $query);
            if (isset($query['rID'])) $toReceipt[] = (int)$query['rID'];
        }
        sort($toReceipt);
        ok('every receipt is listed, and opens the receipt editor',
           $toReceipt === $serialIds,
           'listed for the receipt editor: ' . implode(',', $toReceipt)
               . '; stored: ' . implode(',', $serialIds));
        ok('no receipt is offered to the canvas',
           array_intersect($offered, $serialIds) === [],
           'the canvas would be handed a document with no coordinates');
    }
}

if ($PAGE === 'main' && $controls->length > 0) {
    // The run URL sits on the ROW, not on the button: one document has several
    // ways to be run, and repeating its URL on each of them is how they drift
    // apart.
    //
    // And it must be a row that can run WITHOUT being asked anything. The first
    // row on a stock install is the Account Reconciliation, which properly
    // refuses to run until it is told which account to reconcile — so following
    // it proved only that the refusal works, and reported a page that delivers
    // documents perfectly well as broken.
    $rows = $xpath->query("//tr[@data-must-ask='0' and @data-url]");
    $node = $rows->length > 0 ? $rows->item(0) : $controls->item(0);
    while ($node !== null
        && !($node instanceof DOMElement && $node->hasAttribute('data-url'))) {
        $node = $node->parentNode;
    }
    $url = $node instanceof DOMElement ? (string)$node->getAttribute('data-url') : '';
    // An empty file would send the run check off to run document 0, which fails
    // for a reason that has nothing to do with running documents.
    ok('the run control leads to a URL', $url !== '',
       'no data-url on the control or any of its ancestors');
    file_put_contents(sys_get_temp_dir() . '/forms-run-url', html_entity_decode($url));
}

// ── everything the page's own code names must be available ──────────────────
//
// Rendering alone is not enough. The datasets page calls get_report_details()
// only when deleting, so its list view renders perfectly with the PhreeForm
// bootstrap missing and breaks the first time someone deletes a dataset. A page
// has to load what it NAMES, not merely what the happy path executes.
echo "\n=== what the page names is loaded ===\n";
// Scanned over TOKENS, not raw text: a first version regexed the file and
// flagged `PF_FONT_*` out of a comment explaining why PF_FONT_* matters. Prose
// and string literals are not references.
$code = '';
foreach (token_get_all($source) as $token) {
    if (!is_array($token)) { $code .= $token; continue; }
    if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING,
                             T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) continue;
    $code .= $token[1];
}

$referenced = [];
if (preg_match_all('/\b(get_report_details|forms_formatter_lookups|gen_[a-z_]+|db_[a-z_]+)\s*\(/',
                   $code, $matches)) {
    foreach (array_unique($matches[1]) as $function) {
        if (!function_exists($function)) $referenced[] = "function $function()";
    }
}
if (preg_match_all('/\b(PF_[A-Z0-9_]+|TABLE_[A-Z0-9_]+|SECURITY_ID_[A-Z0-9_]+|FORMS_[A-Z0-9_]+)\b/',
                   $code, $matches)) {
    foreach (array_unique($matches[1]) as $constant) {
        if (!defined($constant)) $referenced[] = "constant $constant";
    }
}
ok(sprintf('every function and constant the page names is defined (%d checked)',
           count(array_unique(array_merge($matches[1] ?? [], []))) ),
   $referenced === [], 'undefined: ' . implode(', ', $referenced));

// ── the toolbar, which is where an invented icon becomes a fatal ─────────────
// build_toolbar() feeds every entry's 'order' to array_multisort(); an entry
// created by assigning into icon_list has no 'order', the key arrays come out
// different lengths, and the page dies. add_icon() sets all five keys.
$complete = true;
$missing  = [];
foreach ($toolbar->icon_list as $name => $icon) {
    foreach (['show', 'icon', 'params', 'text', 'order'] as $key) {
        if (!array_key_exists($key, $icon)) { $complete = false; $missing[] = "$name.$key"; }
    }
}
ok('every toolbar entry is complete, so none was invented by assignment',
   $complete, 'missing: ' . implode(', ', $missing));

// The mode as invoked, not the page it resolved to: 'print' and 'printgroup'
// both render pages/print, and reporting both as "print" made the suite look
// like it had run the same check twice.
$label = (string)$argv[2];
echo "\n" . ($failures === 0 ? "  $label: ok\n" : "  $label: FAILED $failures check(s)\n");
exit($failures === 0 ? 0 : 1);
