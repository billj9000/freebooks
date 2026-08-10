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
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /docs/tools/capture_phreeform_baseline.php
//
/**
 * Capture a PhreeForm baseline for the Forms migration (docs/forms-design.md §8.1).
 *
 * Renders an existing PhreeForm form through the LEGACY engine and snapshots two
 * artefacts per record:
 *   1. the resolved data crossing into the renderer  -> the semantic baseline
 *   2. the PDF bytes                                 -> for positional/visual diff
 *
 * The semantic snapshot is the important half: it is exactly what a Forms
 * DocumentProvider must reproduce, and it diffs cleanly (unlike PDF bytes, which
 * never match because TCPDF regenerates file_id and the creation date per run).
 *
 * NOT part of the application. A dev aid, run inside the container against a
 * TEST database. Do not point it at production - see the printed-counter note.
 *
 * Usage (from the host):
 *   docker cp docs/tools/capture_phreeform_baseline.php <container>:/tmp/cap.php
 *   docker exec <container> php -d display_errors=1 /tmp/cap.php <report_id> <ids> [out_dir]
 *
 *   report_id  phreeform.id of the form (e.g. 91 = Invoice, 73 = Purchase Order)
 *   ids        comma-separated journal_main.id values, e.g. 349,89,110
 *   out_dir    defaults to /tmp/baseline
 *
 * Company: $argv[4], else FREEBOOKS_COMPANY, else the first company under my_files/.
 * Webroot: FREEBOOKS_WEBROOT, else /var/www/html.
 *
 * Four traps this script exists to handle - each yields silently empty or wrong
 * output rather than an error (all four documented in forms-design.md §8.1):
 *   1. setprintedfield: some forms (e.g. pf_91 Invoice) carry
 *      setprintedfield=journal_main.printed, and BuildPDF fires
 *      "UPDATE journal_main SET printed = printed + 1" per record. Suppressed
 *      below, and asserted unchanged afterwards.
 *   2. datedefault: stored default is often 'c' (Today), which selects zero rows
 *      for historical records. Overridden to 'a' (All), matching the real print
 *      button (...&date=a&xfld=journal_main.id&xcr=EQUAL&xmin=<id>).
 *   3. display_errors is Off in the image, so CLI fatals are invisible. Always
 *      invoke with -d display_errors=1.
 *   4. Bootstrap must mirror application_top.php's ordering or it dies on
 *      undefined SECURITY_ID_* / MENU_HEADING_* / PF_FONT_* constants.
 */

define('PAGE_EXECUTION_START_TIME', microtime(true));
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

$REPORT_ID = isset($argv[1]) ? (int)$argv[1] : 0;
$RECORDS   = isset($argv[2]) ? array_values(array_filter(array_map('intval', explode(',', $argv[2])))) : [];
$OUT       = $argv[3] ?? '/tmp/baseline';
if (!$REPORT_ID || !$RECORDS) {
    fwrite(STDERR, "Usage: php capture_phreeform_baseline.php <report_id> <id,id,...> [out_dir] [company]\n");
    exit(1);
}

// ─── Bootstrap: mirrors application_top.php's ordering (trap 4) ───────────────
$fs_admin = rtrim(getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html', '/') . '/';
if (!file_exists($fs_admin . 'includes/configure.php')) {
    fwrite(STDERR, "No configure.php under $fs_admin - set FREEBOOKS_WEBROOT.\n");
    exit(1);
}
require($fs_admin . 'includes/configure.php');
if (!defined('DIR_FS_ADMIN'))     define('DIR_FS_ADMIN', $fs_admin);
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');
define('DIR_WS_MODULES',  'modules/');
define('DIR_WS_MY_FILES', PATH_TO_MY_FILES);
define('DIR_FS_INCLUDES', DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MODULES',  DIR_FS_ADMIN . 'modules/');
define('DIR_FS_MY_FILES', DIR_FS_ADMIN . PATH_TO_MY_FILES);
define('DIR_FS_THEMES',   DIR_FS_ADMIN . 'themes/');
define('FILENAME_DEFAULT','index');
define('DIR_WS_ICONS',    '');

$company = (string)($argv[4] ?? getenv('FREEBOOKS_COMPANY') ?: '');
if ($company === '' && is_dir(DIR_FS_MY_FILES)) {
    foreach (scandir(DIR_FS_MY_FILES) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir(DIR_FS_MY_FILES . $entry) && file_exists(DIR_FS_MY_FILES . $entry . '/config.php')) {
            $company = $entry;
            break;
        }
    }
}
if ($company === '' || !file_exists(DIR_FS_MY_FILES . $company . '/config.php')) {
    fwrite(STDERR, "Company '$company' not found under " . DIR_FS_MY_FILES . "\n");
    exit(1);
}
// Which company we ended up on is load-bearing: baselines from the wrong
// database still render plausible-looking output. Say it out loud.
if (!isset($argv[4]) && !getenv('FREEBOOKS_COMPANY')) {
    fwrite(STDERR, "WARNING: company auto-detected as '$company'. Pass it explicitly "
                 . "(argv[4] or FREEBOOKS_COMPANY) if that is not the one you meant.\n");
}
echo "company: $company\n";

$_SESSION['company']  = $company;
$_SESSION['language'] = 'en_us';
$_SESSION['admin_id'] = 1;

if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) require_once(DIR_FS_ADMIN . 'vendor/autoload.php');

require_once(DIR_FS_MODULES . 'phreedom/language/en_us/language.php');
require_once(DIR_FS_MODULES . 'phreedom/defaults.php');
require_once(DIR_FS_INCLUDES . 'common_functions.php');
require_once(DIR_FS_INCLUDES . 'common_classes.php');

define('DB_DATABASE', $company);
require_once(DIR_FS_MY_FILES . $company . '/config.php');
if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
$db = new queryFactory();
$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

$cfg = $db->Execute("SELECT configuration_key, configuration_value FROM " . DB_PREFIX . "configuration");
while (!$cfg->EOF) {
    if (!defined($cfg->fields['configuration_key']))
        define($cfg->fields['configuration_key'], $cfg->fields['configuration_value']);
    $cfg->MoveNext();
}
if (!defined('DATE_FORMAT_CALENDAR')) define('DATE_FORMAT_CALENDAR', 'mm-dd-yy');
// web-path constants html_href_link() needs (normally derived from the request)
if (!defined('DIR_WS_ADMIN'))     define('DIR_WS_ADMIN', '/');
if (!defined('HTTP_SERVER'))      define('HTTP_SERVER', 'http://localhost');
if (!defined('HTTPS_SERVER'))     define('HTTPS_SERVER', 'http://localhost');
if (!defined('DIR_WS_FULL_PATH')) define('DIR_WS_FULL_PATH', HTTP_SERVER . DIR_WS_ADMIN);
if (!defined('DIR_WS_THEMES'))    define('DIR_WS_THEMES', 'themes/default/');
if (!defined('DIR_WS_IMAGES'))    define('DIR_WS_IMAGES', 'themes/default/images/');

$messageStack = new messageStack();
gen_pull_language('phreedom',   'menu');
gen_pull_language('phreebooks', 'menu');
require_once(DIR_FS_MODULES . 'phreedom/config.php');
$loaded_modules = array();                       // find_special_class() reads this global
foreach (scandir(DIR_FS_MODULES) as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    if (is_dir(DIR_FS_MODULES . $dir)) gen_pull_language($dir, 'menu');
    if (defined('MODULE_' . strtoupper($dir) . '_STATUS')) {
        $loaded_modules[] = $dir;
        require_once(DIR_FS_MODULES . $dir . '/config.php');
    }
}
$currencies = new currencies();
gen_pull_language('phreeform');                  // PF_FONT_* etc.
gen_pull_language('phreebooks');                 // special-class strings
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');

if (!is_dir($OUT) && !@mkdir($OUT, 0777, true)) {
    fwrite(STDERR, "Cannot create $OUT\n");
    exit(1);
}

/** Snapshot the template elements plus whatever the data layer resolved into them. */
function snapshot_elements($report) {
    $out = [];
    foreach ((array)$report->fieldlist as $i => $f) {
        $e = ['idx' => $i, 'type' => $f->type ?? null, 'description' => $f->description ?? null];
        // PhreeForm stuffs resolved values back into the definition (TBlk/CBlk/CDta/Ttl)
        if (isset($f->text) && $f->text !== '') $e['resolved_text'] = $f->text;
        if (isset($f->data))                    $e['table_data']    = $f->data;
        if (isset($f->boxfield) && is_array($f->boxfield)) {
            $e['boxfields'] = array_map(fn($b) => [
                'fieldname'  => $b->fieldname  ?? null,
                'processing' => $b->processing ?? null,
            ], $f->boxfield);
        }
        $out[] = $e;
    }
    return $out;
}

$exit = 0;
foreach ($RECORDS as $recId) {
    $before = $db->Execute("SELECT printed, purchase_invoice_id, currencies_code, currencies_value
                            FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)$recId)->fields;
    if (!$before) { echo "  rec $recId: NOT FOUND\n"; $exit = 1; continue; }

    $report     = get_report_details($REPORT_ID);
    $suppressed = $report->setprintedfield ?? '';
    $report->setprintedfield = '';        // trap 1 - never move the printed counter
    $report->datedefault     = 'a';       // trap 2 - 'c' (Today) selects zero historical rows

    $x = new stdClass();                  // select exactly this record
    $x->fieldname = 'journal_main.id';
    $x->default   = 'EQUAL';
    $x->min_val   = $recId;
    $report->xfilterlist = [$x];

    $GLOBALS['FieldValues'] = [];
    $output = BuildForm($report, 'S');    // 'S' returns bytes instead of exit()ing

    $after = $db->Execute("SELECT printed FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)$recId)->fields;
    $moved = ($before['printed'] !== $after['printed']);

    $ok   = is_array($output) && !empty($output['pdf']);
    $stem = "$OUT/pf{$REPORT_ID}_rec{$recId}";
    if ($ok) file_put_contents("$stem.pdf", $output['pdf']);

    file_put_contents("$stem.json", json_encode([
        'report_id'        => $REPORT_ID,
        'record_id'        => $recId,
        'record_ref'       => $before['purchase_invoice_id'],
        'currency'         => $before['currencies_code'],
        'currencies_value' => $before['currencies_value'],
        'suppressed_field' => $suppressed,
        'printed_before'   => $before['printed'],
        'printed_after'    => $after['printed'],
        'pdf_bytes'        => $ok ? strlen($output['pdf']) : 0,
        'field_values'     => $GLOBALS['FieldValues'],   // the header record
        'elements'         => snapshot_elements($report),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    printf("  pf_%-4s rec %-6s %-12s %-4s pdf=%-10s printed %s->%s %s\n",
        $REPORT_ID, $recId, (string)$before['purchase_invoice_id'], (string)$before['currencies_code'],
        $ok ? strlen($output['pdf']) . 'B' : 'FAILED',
        $before['printed'], $after['printed'], $moved ? '!! COUNTER MOVED' : 'ok');

    if (!$ok || $moved) $exit = 1;
}

echo "captured to $OUT\n";
foreach ((array)($_SESSION['messageToStack'] ?? []) as $m)
    echo "  msg[" . $m['type'] . "]: " . trim(strip_tags($m['text'])) . "\n";

exit($exit);
