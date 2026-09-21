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
//  Path: /docs/tools/journal_driver.php
//
/**
 * THIS WRITES TO THE DATABASE. Post an entry of every journal type through the
 * real ledger classes, under both SQL modes, and say what the server refused.
 *
 * The form robot reaches the save forms. It does not reach the posting engine:
 * gen_ledger unposts, re-posts, rewrites chart balances, inventory layers,
 * account history and cost of goods, and almost none of that is on a form's
 * path. That is where the write faults the static sweep cannot settle actually
 * live - 137 db_perform candidates, most of them in gen_ledger and the classes
 * it calls.
 *
 * WHAT IT DOES. For each journal id present in journal_main it loads the most
 * recent entry with `new journal($id)` - the application's own reader - and
 * calls Post('edit') on it, which is exactly what saving an edited invoice does:
 * unpost the old rows, post them again. Nothing synthetic is constructed, so
 * what runs is a shape the application itself produced.
 *
 * BOTH PROFILES. Each journal is driven twice: once under the server's own
 * sql_mode, and once with NO_ZERO_DATE, NO_ZERO_IN_DATE and ONLY_FULL_GROUP_BY
 * added - what MySQL 8 ships. A zero date that MariaDB accepts is refused there,
 * and the posting engine writes plenty of dates.
 *
 * WHAT COUNTS AS A FAILURE. A thrown Throwable, an error on the messageStack, or
 * a new line in sql_errors.log. Post() itself answers false through
 * fail_message(), which rolls back and adds to the stack, so the return value
 * alone understates what went wrong.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/journal_driver.php <company> [--go] [--only=N]
 *
 * Without --go it says which journals it would drive and changes nothing. With
 * --go it refuses any company whose database is not on the throwaway server:
 * re-posting rewrites ledger rows, and 24 of the 58 tables are MyISAM, so a
 * failure part way through does not roll back.
 */

$company = $argv[1] ?? '';
$go      = in_array('--go', $argv, true);
$only    = '';
foreach ($argv as $a) if (strpos($a, '--only=') === 0) $only = substr($a, 7);
$webroot = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$TEST_DB_HOST = getenv('FREEBOOKS_TEST_DB_HOST') ?: 'fb-testdb';

if ($company === '') { fwrite(STDERR, "usage: journal_driver.php <company> [--go] [--only=N]\n"); exit(2); }
$cfg = $webroot . '/my_files/' . $company . '/config.php';
if (!file_exists($cfg)) { fwrite(STDERR, "no such company: $company\n"); exit(2); }
preg_match("/define\('DB_SERVER_HOST','([^']*)'/", file_get_contents($cfg), $h);
if ($go && ($h[1] ?? '') !== $TEST_DB_HOST) {
    fwrite(STDERR, "REFUSING to write: '$company' is on '" . ($h[1] ?? '?') . "', not the throwaway"
                 . " server '$TEST_DB_HOST'.\nMake a copy with docs/tools/test_company.sh.\n");
    exit(2);
}

require_once('/opt/repo/docs/tools/bootstrap_freebooks.php');
fb_bootstrap($company, false);
global $db, $messageStack;
require_once(DIR_FS_MODULES . 'phreebooks/classes/gen_ledger.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
// gen_add_audit_log() and the posting path read this; a CLI run has no login.
if (empty($_SESSION['admin_id'])) $_SESSION['admin_id'] = 1;

// One entry per journal type - the most recent, because it is the most likely to
// reference rows that still exist.
$subjects = array();
$r = $db->Execute("select journal_id, max(id) as id, count(*) as n from " . TABLE_JOURNAL_MAIN
                . " group by journal_id order by journal_id");
while (!$r->EOF) { $subjects[(int)$r->fields['journal_id']] = array((int)$r->fields['id'], (int)$r->fields['n']); $r->MoveNext(); }

$MODES = array(
    'stock'  => null,   // whatever the server already runs
    'mysql8' => 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,'
              . 'NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY',
);

if (!$go) {
    printf("%s has entries in %d journal(s):\n", $company, count($subjects));
    foreach ($subjects as $jID => $s) printf("  journal %-3d %6d entries, would re-post id %d\n", $jID, $s[1], $s[0]);
    printf("\nnothing changed. Pass --go to post them (both sql_modes, %d posts).\n",
           count($subjects) * count($MODES));
    exit(0);
}

$sql_log = "$webroot/my_files/sql_errors.log";
$size    = function ($f) { clearstatcache(true, $f); return file_exists($f) ? filesize($f) : 0; };
$tail    = function ($f, $from) use ($size) {
    if ($size($f) <= $from) return array();
    $fh = fopen($f, 'rb'); fseek($fh, $from);
    $new = fread($fh, min($size($f) - $from, 1048576)); fclose($fh);
    return explode("\n", rtrim($new, "\n"));
};
/** Errors the ledger raised, read off the stack the application uses. */
$stack_errors = function () {
    $out = array();
    foreach ($_SESSION['messageToStack'] ?? array() as $m) {
        if (($m['type'] ?? '') !== 'error') continue;
        $out[] = trim(preg_replace('/\s+/', ' ', strip_tags($m['text'])));
    }
    $_SESSION['messageToStack'] = array();
    return $out;
};

// ONE PROCESS PER POST. queryFactory answers a failed statement with die(), so a
// refusal ends the whole run and every journal after it goes unmeasured - the
// first version of this reported one line and exited 0. A child per post means a
// die() costs that post only, and the parent reads the outcome off the child's
// exit code and output.
if ($only === '' || strpos($only, ':') === false) {
    $failed = 0; $ran = 0;
    foreach ($subjects as $jID => $s) {
        if ($only !== '' && (string)$jID !== $only) continue;
        foreach (array_keys($MODES) as $mode) {
            $cmd = sprintf('%s -d display_errors=1 %s %s --go --only=%s', escapeshellarg(PHP_BINARY),
                           escapeshellarg(__FILE__), escapeshellarg($company), escapeshellarg("$jID:$mode"));
            $out = array(); $rc = 0;
            exec($cmd . ' 2>&1', $out, $rc);
            $said = array_values(array_filter($out, function ($l) {
                return strpos($l, 'company: ') !== 0 && trim($l) !== ''; }));
            $ran++;
            $clean = ($rc === 0 && $said && trim(end($said)) === 'CLEAN');
            if ($clean) { printf("  journal %-3s %-7s posted\n", $jID, $mode); continue; }
            $failed++;
            printf("  journal %-3s %-7s FAILED\n", $jID, $mode);
            foreach ($said as $l) if (trim($l) !== 'CLEAN') printf("      %s\n", substr(trim($l), 0, 170));
            if (!$said) printf("      died with no output (exit %d) - the statement is in sql_errors.log\n", $rc);
        }
    }
    printf("\n%d posts, %d clean, %d failed\n", $ran, $ran - $failed, $failed);
    exit($failed ? 1 : 0);
}
list($only_jID, $only_mode) = explode(':', $only, 2);

$results = array();
foreach ($subjects as $jID => $s) {
    if ((string)$jID !== $only_jID) continue;
    foreach ($MODES as $mode => $sql_mode) {
        if ($mode !== $only_mode) continue;
        if ($sql_mode !== null) $db->Execute("SET SESSION sql_mode = '" . $sql_mode . "'");
        else                    $db->Execute("SET SESSION sql_mode = @@global.sql_mode");
        $stack_errors();                       // discard anything left from before
        $before = $size($sql_log);
        $why = array(); $ok = null;
        try {
            $entry = new journal($s[0]);
            // A journal that failed to load has no id; posting it would write a
            // new row rather than re-post the one being tested.
            if (!isset($entry->id)) { $why[] = 'could not load entry ' . $s[0]; }
            else { $ok = $entry->Post('edit'); }
        } catch (Throwable $e) {
            $why[] = get_class($e) . ': ' . $e->getMessage()
                   . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
        }
        foreach ($stack_errors() as $m) $why[] = 'ledger: ' . (strlen($m) > 150 ? substr($m, 0, 150) . '...' : $m);
        foreach ($tail($sql_log, $before) as $l) {
            $l = trim($l);
            if ($l !== '') $why[] = 'REFUSED ' . (strlen($l) > 170 ? substr($l, 0, 170) . '...' : $l);
        }
        if ($ok === false && !$why) $why[] = 'Post() answered false and said nothing';
        $results[] = array($jID, $mode, $s[0], $why);
    }
}
$db->Execute("SET SESSION sql_mode = @@global.sql_mode");

// The child says nothing when the post was clean, prints the reasons when it was
// not, and puts the verdict in its exit code - so a die() partway through, which
// prints nothing and exits 0, is still distinguishable by the missing output.
$bad = array_filter($results, function ($r) { return (bool)$r[3]; });
foreach ($bad as $r) foreach ($r[3] as $w) printf("%s\n", $w);
if (!$bad) echo "CLEAN\n";
exit($bad ? 1 : 0);
