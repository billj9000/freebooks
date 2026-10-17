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
//  Path: /docs/tools/verify_forms_admin_tools.php
//
/**
 * The two destructive actions on the Forms settings page actually run. §13.42.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_admin_tools.php [company]
 *
 * ## Why this is not part of verify_forms_pages
 *
 * That tool renders pages and holds them to what they emit. These two actions
 * do not emit anything — they rewrite every definition on disk, or truncate the
 * document list and build it again, and then redirect. Checking that a button
 * NAMES an action the switch handles is what a page harness can do, and it is
 * what it does; it says nothing about whether the action works.
 *
 * The distinction matters here more than usual because both actions came over
 * from a module that has been deleted (§13.42), so nothing has ever run this
 * copy of them. A ported feature that does not work is worse than a missing
 * one: the button is there, it reports success, and the damage is silent.
 *
 * ## How it runs a destructive action safely
 *
 * The whole report directory and the catalogue table are copied aside first,
 * and put back from a shutdown function so it happens on a fatal as well as on
 * success — the same machinery, for the same reason, as
 * verify_forms_legacy_reader.php.
 *
 * The action itself runs in a CHILD process. It ends in gen_redirect(), which
 * exits; in-process that would take this tool with it, and a check that cannot
 * outlive the thing it checks cannot report on it.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$dir = rtrim(FORMS_DIR_DEFINITIONS, '/\\');
if (!is_dir($dir)) { echo "  [--] no report directory on this company\n"; exit(0); }

// ── everything put back, whatever happens ───────────────────────────────────
$snapshot = fb_snapshot_tree($dir);
$rows = [];
$result = $db->Execute("select * from " . TABLE_PHREEFORM . " order by id");
while ($result && !$result->EOF) { $rows[] = $result->fields; $result->MoveNext(); }
// The paper setting too. This tool CHANGES it, to give the re-stamp something
// to do, and a company enabled before that key existed has no row at all — so
// putting it back means deleting the row, not writing the old value into it.
$paperRow = null;
$existing = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION
    . " where configuration_key = 'FORMS_DEFAULT_PAPER'");
if ($existing && !$existing->EOF) $paperRow = (string)$existing->fields['configuration_value'];

register_shutdown_function(function () use ($snapshot, $rows, $paperRow) {
    global $db;
    if ($paperRow === null) {
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key = 'FORMS_DEFAULT_PAPER'");
    } else {
        $db->Execute("update " . TABLE_CONFIGURATION . " set configuration_value = '"
            . $db->prepare_input($paperRow) . "' where configuration_key = 'FORMS_DEFAULT_PAPER'");
    }
    $db->Execute("truncate " . TABLE_PHREEFORM);
    foreach ($rows as $row) {
        $columns = $values = [];
        foreach ($row as $column => $value) {
            $columns[] = $column;
            $values[]  = $value === null ? 'NULL' : "'" . $db->prepare_input((string)$value) . "'";
        }
        $db->Execute("insert into " . TABLE_PHREEFORM . " (" . implode(',', $columns)
            . ") values (" . implode(',', $values) . ")");
    }
    fb_restore_tree($snapshot);
    echo "\n  [--] the document list, the paper setting and every definition were put back\n";
});

/**
 * Run one settings-page action, in a child, as an administrator.
 *
 * The page is required rather than re-implemented: what has to be proved is
 * that the code somebody will press works, not that a copy of it in this file
 * does.
 */
function runAction(string $action, string $company): array
{
    $script = <<<'CHILD'
        require_once(getenv('FB_TOOLS') . '/bootstrap_freebooks.php');
        fb_bootstrap(getenv('FB_COMPANY'));
        $_SESSION['admin_security'] = array_fill(0, 1000, 4);
        $_SESSION['admin_id']       = 1;
        // The page refuses its actions unless they arrive as a POST.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST['action'] = $_POST['action'] = getenv('FB_ACTION');
        $_REQUEST['module'] = 'forms';  $_REQUEST['page'] = 'admin';
        define('DIR_FS_WORKING', DIR_FS_MODULES . 'forms/');
        $module = 'forms'; $page = 'admin';
        require(DIR_FS_WORKING . 'pages/admin/pre_process.php');
CHILD;
    $command = 'FB_TOOLS=' . escapeshellarg(__DIR__)
             . ' FB_COMPANY=' . escapeshellarg($company)
             . ' FB_ACTION=' . escapeshellarg($action)
             . ' ' . escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -r ' . escapeshellarg($script);
    $lines = []; $status = 0;
    exec($command . ' 2>&1', $lines, $status);
    $out = implode("\n", $lines);
    // A refused action dies with a message and exit status 0, so the status
    // alone would report it as having run.
    if (str_contains($out, 'must be submitted from its page')) $status = 1;
    return [$out, $status];
}

/** The page size a native definition carries. */
function paperOf(string $path): ?string
{
    $decoded = json_decode((string)@file_get_contents($path), true);
    return $decoded['page']['size'] ?? null;
}

$definitions = [];
foreach (glob("$dir/pf_*") ?: [] as $path) {
    if (!preg_match('/pf_\d+$/', $path)) continue;   // never a .pre-forms backup
    $definitions[] = $path;
}
printf("company '%s', %d definition(s), default paper %s\n\n",
    $company, count($definitions), FORMS_DEFAULT_PAPER);

// ─────────────────────────────────────────────────────────────────────────────
echo "=== re-stamping puts every definition on the default paper ===\n";

// Something has to CHANGE, or a re-stamp that did nothing would pass. The
// company's own paper is left alone and a different one is asked for.
$target = FORMS_DEFAULT_PAPER === 'A4' ? 'Letter' : 'A4';
// write_configure(), not an UPDATE. A company enabled before this key existed
// has no ROW for it, so an update changes nothing, the child falls back to the
// define() in forms/defaults.php, and the re-stamp dutifully stamps the size
// everything already had — a green run that moved nothing. Which is what the
// first version of this check did.
write_configure('FORMS_DEFAULT_PAPER', $target);

$before = [];
foreach ($definitions as $path) $before[$path] = paperOf($path);
$wrong = count(array_filter($before, fn($p) => $p !== null && $p !== $target));
ok("some definition is not already on $target ($wrong of " . count($definitions) . ")", $wrong > 0,
   'nothing would change, so this would prove nothing');

[$out, $status] = runAction('restamp', $company);
ok('the action runs', $status === 0, $out);

$moved = $unmoved = 0;
foreach ($definitions as $path) {
    $now = paperOf($path);
    if ($now === null) continue;                 // not native; the xml branch
    if ($now === $target) $moved++; else $unmoved++;
}
ok("every native definition now says $target ($moved moved)", $unmoved === 0,
   "$unmoved still on another size");

// And nothing beyond the page size: a re-stamp that re-serialised the file
// would rewrite parts of somebody's document it was not asked about.
$kept = 0;
foreach ($definitions as $path) {
    $decoded = json_decode((string)@file_get_contents($path), true);
    if (isset($decoded['title'])) $kept++;
}
ok('and the definitions are still readable, titles intact', $kept === $moved,
   "$kept of $moved decoded");

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== rebuilding restores the document list from the files ===\n";

$titlesBefore = [];
$result = $db->Execute("select doc_title from " . TABLE_PHREEFORM . " where doc_type <> '0' order by doc_title");
while ($result && !$result->EOF) { $titlesBefore[] = (string)$result->fields['doc_title']; $result->MoveNext(); }

[$out, $status] = runAction('rebuild', $company);
ok('the action runs', $status === 0, $out);

$titlesAfter = [];
$result = $db->Execute("select doc_title from " . TABLE_PHREEFORM . " where doc_type <> '0' order by doc_title");
while ($result && !$result->EOF) { $titlesAfter[] = (string)$result->fields['doc_title']; $result->MoveNext(); }

ok('the list is not empty afterwards', $titlesAfter !== [], 'the rebuild emptied it');
ok('and it holds the same documents it held before',
   $titlesAfter === $titlesBefore,
   sprintf('%d before, %d after', count($titlesBefore), count($titlesAfter)));

// The failure this rebuild was rewritten for: reading a native definition with
// the xml reader gives NULL for every field, so the list comes back full of
// nameless documents and reports success.
$nameless = count(array_filter($titlesAfter, fn($t) => trim($t) === ''));
ok('no document came back without a name', $nameless === 0, "$nameless nameless");

// Every file must still be named pf_<id> for an id in the list: the rebuild
// renames as it re-catalogues, through a temporary name, and a collision there
// leaves a definition nothing can open.
$orphans = [];
foreach (glob("$dir/pf_*") ?: [] as $path) {
    if (!preg_match('/pf_(\d+)$/', $path, $found)) continue;
    $row = $db->Execute("select id from " . TABLE_PHREEFORM . " where id = " . (int)$found[1]);
    if (!$row || $row->EOF) $orphans[] = basename($path);
}
ok('every definition file has a row in the rebuilt list', $orphans === [],
   implode(', ', array_slice($orphans, 0, 4)));
$strays = glob("$dir/tmp_*") ?: [];
ok('and no file was left under its temporary name', $strays === [],
   implode(', ', array_map('basename', array_slice($strays, 0, 4))));

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
