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
const FORMS_PAGES = ['main', 'datasets', 'datasetsnew', 'admin', 'design', 'designpick', 'print', 'printgroup', 'run', 'printrun'];

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
$company = fb_bootstrap(($argv[1] ?? '') !== '' ? $argv[1] : null, false);
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
// The datasets EDITOR is a branch of the datasets page, and the list view is
// what the suite rendered until now — so the form that actually saves a dataset
// had never been rendered by anything. That is where the missing CSRF token
// was, and why nothing caught it.
$DEFINING = $PAGE === 'datasetsnew';
// The editor reached from the MENU, with no report named. That is the ordinary
// way in — the menu entry exists so the permission can be granted at all — and
// it is a different branch from the editor proper.
$PICKING  = $PAGE === 'designpick';
if ($RUNNING)  $PAGE = 'main';
if ($PRINTING) $PAGE = 'print';
if ($CHOOSING) $PAGE = 'print';
if ($DEFINING) $PAGE = 'datasets';
if ($PICKING)  $PAGE = 'design';

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
if ($PAGE === 'design' && $PICKING) {
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

if ($PAGE === 'admin') {
    // The settings page is only worth having if the control it draws is the one
    // the application reads. A select named for a different key, or one whose
    // options are not the values gen_print_form_url() tests for, is a switch
    // that appears to work and changes nothing — the failure this whole page
    // exists to make impossible.
    $select = $xpath->query("//select[@name='module_forms_print_buttons']");
    ok('it draws the print-button setting', $select->length === 1,
       $select->length . ' matching control(s)');
    if ($select->length === 1) {
        $values = [];
        foreach ($xpath->query(".//option", $select->item(0)) as $option) {
            $values[] = (string)$option->getAttribute('value');
        }
        sort($values);
        ok("it offers exactly the two values the helper tests for", $values === ['0', '1'],
           implode(',', $values));

        // The installer's key list is what the save branch iterates, so a key
        // present in one and not the other is a setting that cannot be saved.
        require_once(DIR_FS_MODULES . 'forms/classes/install.php');
        $keys = array_keys((new forms_admin())->keys);
        ok('the setting is in the installer key list, so saving it works',
           in_array('MODULE_FORMS_PRINT_BUTTONS', $keys, true), implode(',', $keys));
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

    $editLinks = $xpath->query("//a[contains(@href,'page=design') and contains(@href,'rID=')]");
    ok('it offers the reports it can edit', $editLinks->length > 0,
       $editLinks->length . ' link(s)');

    // Forms must NOT be offered: a form is laid out on a page, and choosing one
    // here would land the author on a page that declines it.
    $offered = [];
    foreach ($editLinks as $link) {
        $query = [];
        parse_str((string)parse_url(html_entity_decode((string)$link->getAttribute('href')),
            PHP_URL_QUERY), $query);
        if (isset($query['rID'])) $offered[] = (int)$query['rID'];
    }
    $formIds = [];
    $rows = $db->Execute("select id from " . TABLE_PHREEFORM . " where doc_ext = 'frm'");
    while ($rows && !$rows->EOF) { $formIds[] = (int)$rows->fields['id']; $rows->MoveNext(); }
    ok('no form is offered in the report chooser', array_intersect($offered, $formIds) === []);
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
