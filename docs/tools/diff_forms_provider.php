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
//  Path: /docs/tools/diff_forms_provider.php
//
/**
 * Semantic diff: a Forms DocumentProvider against a PhreeForm baseline.
 *
 * This is the regression gate from docs/forms-design.md §8 — a port is not
 * finished until its data matches what PhreeForm produced for the same record.
 * It compares the DATA, not the PDF: TCPDF regenerates file_id and the creation
 * date per run, so rendered bytes never match, while wrong values are exactly
 * what must not slip through.
 *
 * The two sides are shaped differently on purpose, and reconciling them is the
 * point. PhreeForm's special_class returned a POSITIONAL array ordered by the
 * template's Data elements; the provider returns values addressed BY NAME. The
 * baseline's element snapshot carries each Data element's boxfield fieldname,
 * so the positional list can be re-keyed and compared like for like.
 *
 * COVERAGE IS BOUNDED TWICE OVER, and both bounds have bitten:
 *
 *   1. By the template. Only fields the form actually binds are compared, so
 *      a provider field no template displays is unverified. pf_91 (Invoice)
 *      has no tax column; pf_67 (Invoice-VAT) binds invoice_line_tax_rate.
 *      Diff against several templates, not one.
 *   2. By the data. A record whose values are all zero cannot reveal a bug in
 *      the arithmetic producing them. Record 349 is a zero-rated EU export, so
 *      a deliberately doubled tax rate still rendered '0%' and passed; record
 *      110 (20% VAT) caught it immediately. Pick records that exercise the
 *      values, not just the fields.
 *
 * Usage (inside the container, which is where the database lives):
 *   php -d display_errors=1 diff_forms_provider.php <report_id> <ids> <baseline_dir> [company]
 *
 * Exits non-zero if any field or line item differs.
 */

define('PAGE_EXECUTION_START_TIME', microtime(true));
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

$REPORT_ID = isset($argv[1]) ? (int)$argv[1] : 0;
$RECORDS   = isset($argv[2]) ? array_values(array_filter(array_map('intval', explode(',', $argv[2])))) : [];
$BASE      = $argv[3] ?? '/tmp/baseline';
if (!$REPORT_ID || !$RECORDS) {
    fwrite(STDERR, "Usage: php diff_forms_provider.php <report_id> <id,id,...> <baseline_dir> [company]\n");
    exit(1);
}

// ─── Bootstrap (see forms-design.md §8.1 trap 4) ──────────────────────────────
$fs_admin = rtrim(getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html', '/') . '/';
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
    foreach (scandir(DIR_FS_MY_FILES) as $e) {
        if ($e === '.' || $e === '..') continue;
        if (is_dir(DIR_FS_MY_FILES . $e) && file_exists(DIR_FS_MY_FILES . $e . '/config.php')) { $company = $e; break; }
    }
    fwrite(STDERR, "WARNING: company auto-detected as '$company'.\n");
}
if (!file_exists(DIR_FS_MY_FILES . $company . '/config.php')) {
    fwrite(STDERR, "Company '$company' not found.\n"); exit(1);
}
echo "company: $company\n";
$_SESSION['company'] = $company; $_SESSION['language'] = 'en_us'; $_SESSION['admin_id'] = 1;

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
if (!defined('DIR_WS_ADMIN'))     define('DIR_WS_ADMIN', '/');
if (!defined('HTTP_SERVER'))      define('HTTP_SERVER', 'http://localhost');
if (!defined('HTTPS_SERVER'))     define('HTTPS_SERVER', 'http://localhost');
if (!defined('DIR_WS_FULL_PATH')) define('DIR_WS_FULL_PATH', HTTP_SERVER . DIR_WS_ADMIN);
if (!defined('DIR_WS_THEMES'))    define('DIR_WS_THEMES', 'themes/default/');
if (!defined('DIR_WS_IMAGES'))    define('DIR_WS_IMAGES', 'themes/default/images/');

$messageStack = new messageStack();
gen_pull_language('phreedom', 'menu');
gen_pull_language('phreebooks', 'menu');
require_once(DIR_FS_MODULES . 'phreedom/config.php');
$loaded_modules = [];
foreach (scandir(DIR_FS_MODULES) as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    if (is_dir(DIR_FS_MODULES . $dir)) gen_pull_language($dir, 'menu');
    if (defined('MODULE_' . strtoupper($dir) . '_STATUS')) {
        $loaded_modules[] = $dir;
        require_once(DIR_FS_MODULES . $dir . '/config.php');
    }
}
$currencies = new currencies();
gen_pull_language('phreeform');
gen_pull_language('phreebooks');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');

require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/entry_provider.php');

use FreeBooks\Forms\Document\{Context, Template, TypedValue};
use FreeBooks\Forms\Provider\EntryDocumentProvider;

/**
 * Fields to skip, for values PhreeForm only derives at render time.
 * Deliberately EMPTY: the entry provider matches the baseline on every field
 * with no exceptions, and it should stay that way. Adding a name here weakens
 * the gate, so justify it in the commit that does.
 */
const RENDER_TIME_ONLY = [];

/** Loose equality: the legacy returns DB strings, the provider keeps native types. */
function same(mixed $a, mixed $b): bool
{
    if ($a === null) $a = '';
    if ($b === null) $b = '';
    if (is_numeric($a) && is_numeric($b)) return abs((float)$a - (float)$b) < 0.0000001;
    return (string)$a === (string)$b;
}

$totalDiffs = 0;
foreach ($RECORDS as $recId) {
    $file = "$BASE/pf{$REPORT_ID}_rec{$recId}.json";
    if (!file_exists($file)) { echo "  rec $recId: no baseline at $file\n"; $totalDiffs++; continue; }
    $base = json_decode(file_get_contents($file), true);

    echo "\n=== record $recId (" . ($base['record_ref'] ?? '?') . ", " . ($base['currency'] ?? '?') . ") ===\n";

    // Re-key the baseline's positional Data values by their bound fieldname.
    $names = [];
    foreach ($base['elements'] as $el) {
        if (($el['type'] ?? '') === 'Data' && !empty($el['boxfields'][0]['fieldname'])) {
            $names[] = $el['boxfields'][0]['fieldname'];
        }
    }
    $legacyValues = [];
    foreach (array_values($base['field_values']) as $i => $v) {
        if (isset($names[$i])) $legacyValues[$names[$i]] = $v;
    }

    // Legacy line items are keyed r0..rN by column position; map via the Tbl columns.
    $legacyRows = [];
    foreach ($base['elements'] as $el) {
        if (($el['type'] ?? '') !== 'Tbl' || empty($el['table_data'])) continue;
        $cols = array_map(fn($b) => $b['fieldname'] ?? null, $el['boxfields'] ?? []);
        foreach (array_slice($el['table_data'], 1) as $row) {   // row 0 is the headings
            $mapped = [];
            foreach ($cols as $i => $name) {
                if ($name !== null && array_key_exists('r' . $i, $row)) $mapped[$name] = $row['r' . $i];
            }
            $legacyRows[] = $mapped;
        }
        break;
    }

    // Run the provider.
    $provider = new EntryDocumentProvider($db, new Template(), new Context(), 'Invoice');
    $doc      = $provider->build(['record_ids' => [$recId]]);
    $rec      = $doc->records[0] ?? null;
    if ($rec === null) { echo "  provider returned no record\n"; $totalDiffs++; continue; }

    // ── compare header values ────────────────────────────────────────────────
    $diffs = 0; $checked = 0;
    foreach ($legacyValues as $name => $legacy) {
        if (in_array($name, RENDER_TIME_ONLY, true)) continue;
        $checked++;
        $mine = $rec->value($name);
        if ($mine === null) { printf("  MISSING  %-24s legacy=%s\n", $name, var_export($legacy, true)); $diffs++; continue; }
        if (!same($legacy, $mine->raw)) {
            printf("  DIFF     %-24s legacy=%-24s provider=%s\n",
                $name, var_export($legacy, true), var_export($mine->raw, true));
            $diffs++;
        }
    }

    // ── compare line items ───────────────────────────────────────────────────
    $mineRows = $rec->table('items');
    if (count($legacyRows) !== count($mineRows)) {
        printf("  DIFF     line count            legacy=%d provider=%d\n", count($legacyRows), count($mineRows));
        $diffs++;
    }
    foreach ($legacyRows as $i => $lrow) {
        foreach ($lrow as $name => $legacy) {
            $checked++;
            $mine = $mineRows[$i][$name] ?? null;
            if ($mine === null) { printf("  MISSING  row%-2d %-18s legacy=%s\n", $i, $name, var_export($legacy, true)); $diffs++; continue; }
            if (!same($legacy, $mine->raw)) {
                printf("  DIFF     row%-2d %-18s legacy=%-18s provider=%s\n",
                    $i, $name, var_export($legacy, true), var_export($mine->raw, true));
                $diffs++;
            }
        }
    }

    printf("  %d checked, %d differ%s\n", $checked, $diffs, $diffs === 0 ? '  ✓' : '');
    $totalDiffs += $diffs;
}

printf("\n%s\n", $totalDiffs === 0 ? 'PROVIDER MATCHES BASELINE' : "$totalDiffs difference(s)");
exit($totalDiffs === 0 ? 0 : 1);
