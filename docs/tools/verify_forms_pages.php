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
 * `require` lines. That caught the missing `PF_DIR_MY_REPORTS` but could not
 * catch the two defects behind it — a missing `PAGE_TITLE`, and a toolbar entry
 * invented by assigning into `icon_list['new']`, which leaves the entry without
 * an `order` key and makes `array_multisort()` in `build_toolbar()` fatal. Both
 * are in the page BODY, and the only way to catch a defect in a page body is to
 * run the body.
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
const FORMS_PAGES = ['main', 'datasets', 'print', 'printgroup', 'run', 'printrun'];

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
// No phreeform: a page must load whatever it uses, which is how the missing
// PF_DIR_MY_REPORTS went unseen while every other tool reported green.
fb_bootstrap(($argv[1] ?? '') !== '' ? $argv[1] : null, false);
$PAGE = (string)$argv[2];

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
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
if ($RUNNING)  $PAGE = 'main';
if ($PRINTING) $PAGE = 'print';
if ($CHOOSING) $PAGE = 'print';

// Every security id at full rights: this checks that a page RUNS, not who may
// open it. Permission is asserted separately, by reading the id each page
// demands (below).
$_SESSION['admin_security'] = array_fill(0, 1000, 4);
$_SESSION['admin_id']       = 1;
$_SESSION['admin_display']  = 'check';
$_GET = $_POST = $_REQUEST = [];

// The print page is opened FROM a document, so with no parameters it correctly
// says nothing was asked for. The parameters come from verify_forms_forms.php,
// which publishes one (form, record) pair it has already rendered — so what is
// driven here is a pair known to produce a document, and a failure is this
// page's rather than a fixture's.
$printTarget = sys_get_temp_dir() . '/forms-print-target';
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
    $source = $CHOOSING ? sys_get_temp_dir() . '/forms-print-group' : $printTarget;
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

$output = '';
$fatal  = null;
try {
    ob_start();
    require(DIR_FS_WORKING . 'pages/' . $PAGE . '/pre_process.php');
    require(DIR_FS_WORKING . 'pages/' . $PAGE . '/template_main.php');
    require(DIR_FS_WORKING . 'pages/' . $PAGE . '/js_include.php');
    $output = (string)ob_get_clean();
} catch (\Throwable $e) {
    $output = (string)ob_get_clean();
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

    $bad = [];
    foreach ($controls as $control) {
        $url = (string)$control->getAttribute('data-url');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)
            || !str_contains($url, 'action=run')
            || $control->getAttribute('data-format') === '') {
            $bad[] = $url === '' ? '(empty)' : substr($url, 0, 60);
        }
    }
    ok('every run control carries a valid run URL and a format', $bad === [],
       implode(' | ', array_slice($bad, 0, 3)));
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
}

if ($PAGE === 'main' && $controls->length > 0) {
    file_put_contents(sys_get_temp_dir() . '/forms-run-url',
        (string)$controls->item(0)->getAttribute('data-url'));
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
