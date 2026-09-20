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
//  Path: /docs/tools/canary.php
//
/**
 * Prove the two detectors are alive, by committing faults they must catch.
 *
 * A suite that reports nothing is indistinguishable from a suite whose net has
 * a hole in it, and this net has had three: enum columns were skipped entirely,
 * null and false were invisible, and for a month there was no log file at all on
 * the development machine while everyone assumed there was.
 *
 * So before any run: write '' into a double, and execute a statement that cannot
 * succeed. Both must appear in their logs. If they do not, the run that follows
 * proves nothing and should not be trusted.
 *
 * Read-only as far as any company's data is concerned: it works on a scratch
 * table of its own, which it drops again. A TEMPORARY table would not do -
 * information_schema does not list them, so the check under test would simply
 * no-op and the canary would pass while proving nothing.
 *
 *   docker exec <c> php /opt/repo/docs/tools/canary.php [company]
 */

$company = $argv[1] ?? getenv('FREEBOOKS_COMPANY') ?: 'demo';
$webroot = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';

if (!defined('DIR_FS_MY_FILES')) define('DIR_FS_MY_FILES', $webroot . '/my_files/');
if (!defined('DIR_FS_ADMIN'))    define('DIR_FS_ADMIN',    $webroot . '/');

require $webroot . '/my_files/' . $company . '/config.php';
require $webroot . '/vendor/autoload.php';            // common_functions declares a PHPMailer interface
require $webroot . '/includes/db/mysql/query_factory.php';
require $webroot . '/includes/common_functions.php';

$db = new queryFactory();
$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, $company);
$GLOBALS['db'] = $db;

$empty_log = DIR_FS_MY_FILES . 'empty_column_writes.log';
$sql_log   = DIR_FS_MY_FILES . 'sql_errors.log';
$lines = function ($f) { return file_exists($f) ? count(file($f)) : 0; };
$before_empty = $lines($empty_log);
$before_sql   = $lines($sql_log);

$T = 'zz_canary';
$db->Execute("DROP TABLE IF EXISTS $T");
$db->Execute("CREATE TABLE $T (id int NOT NULL auto_increment, n double NOT NULL DEFAULT 0,
              e enum('0','1') NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB");

// Fault 1: a blank into a column that is not a string, through the real helper.
db_perform_empty_check($T, array('n' => '', 'e' => ''));

// Fault 2 is provoked last, because Execute() ends the process on its way out -
// a redirect that is a no-op in a CLI, then exit. So the verdict is written by a
// shutdown hook, which runs either way.
register_shutdown_function(function () use ($lines, $empty_log, $sql_log, $before_empty, $before_sql, $company, $T, $webroot) {
    $got_empty = $lines($empty_log) - $before_empty;
    $got_sql   = $lines($sql_log)   - $before_sql;

    // Two lines expected from fault 1: the double, and the enum that used to be
    // skipped. One from fault 2.
    $checks = array(
        'empty-value detector' => array($got_empty, 2),
        'sql error log'        => array($got_sql,   1),
    );
    $ok = true;
    foreach ($checks as $what => $c) {
        list($got, $want) = $c;
        if ($got < $want) $ok = false;
        printf("  %-24s %s\n", $what,
            $got >= $want ? "alive ($got new line" . ($got == 1 ? '' : 's') . ")"
                          : "SILENT - expected $want line(s), got $got");
    }

    // The connection that provoked the error died with it, so tidying up needs a
    // fresh one.
    $tidy = new queryFactory();
    $tidy->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, $company);
    $tidy->Execute("DROP TABLE IF EXISTS $T");

    echo $ok ? "CANARY PASS\n" : "CANARY FAIL - a run after this would prove nothing\n";
});

$db->Execute("select * from a_table_that_cannot_exist_$T");
