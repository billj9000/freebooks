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
//  Path: /docs/tools/reindex_help.php
//
/**
 * THIS WRITES TO THE DATABASE. Rebuild a company's Help manual index.
 *
 * The manual's table of contents, index and search all come from the phreehelp
 * table, which synchronize() fills by scanning every module's
 * language/<locale>/manual directory. A new or edited topic file is invisible
 * until that runs.
 *
 * It is PER COMPANY. The manual files are one shared copy under modules/, but
 * each company has its own phreehelp table, so a topic added once has to be
 * indexed once per company - including on production, where the alternative is
 * setting PHREEHELP_FORCE_RELOAD and asking somebody to open Help.
 *
 * A topic that is not indexed does not announce itself: the context-sensitive
 * Help button looks its doc_pos up, finds nothing, and opens the front page of
 * the manual instead.
 *
 *   docker exec <c> php /opt/repo/docs/tools/reindex_help.php <company> [--check=10.01]
 *   docker exec <c> php /opt/repo/docs/tools/reindex_help.php --all [--check=10.01]
 *
 * --check names a doc_pos to confirm afterwards, which is the question actually
 * being asked whenever this is run.
 */

$webroot = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$args    = array_slice($argv, 1);
$all     = in_array('--all', $args, true);
$check   = '';
$company = '';
foreach ($args as $a) {
    if (strpos($a, '--check=') === 0) $check = substr($a, 8);
    elseif ($a !== '--all')           $company = $a;
}
if (!$all && $company === '') {
    fwrite(STDERR, "usage: reindex_help.php <company>|--all [--check=<doc_pos>]\n");
    exit(2);
}

$companies = array();
if ($all) {
    foreach (scandir($webroot . '/my_files') as $d) {
        if ($d[0] === '.' ) continue;
        if (file_exists($webroot . '/my_files/' . $d . '/config.php')) $companies[] = $d;
    }
} else {
    $companies[] = $company;
}

// synchronize() resolves the manual directories through DIR_WS_MODULES, which is
// the relative web path 'modules/'. A CLI process starts wherever it was called
// from, so without this the scan finds no manual at all and quietly indexes
// nothing.
chdir($webroot);

$failed = 0;
foreach ($companies as $co) {
    // One process per company: the constants a company's config defines cannot
    // be redefined for the next one.
    if (count($companies) > 1) {
        $cmd = sprintf('%s %s %s%s', escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__),
                       escapeshellarg($co), $check !== '' ? ' --check=' . escapeshellarg($check) : '');
        passthru($cmd, $rc);
        if ($rc !== 0) $failed++;
        continue;
    }

    require_once('/opt/repo/docs/tools/bootstrap_freebooks.php');
    fb_bootstrap($co, false);
    global $db;
    require_once(DIR_FS_MODULES . 'phreehelp/defaults.php');
    require_once(DIR_FS_MODULES . 'phreehelp/functions/phreehelp.php');
    gen_pull_language('phreehelp');

    $before = $db->Execute("SELECT COUNT(*) AS n FROM " . TABLE_PHREEHELP);
    $before = $before->EOF ? 0 : (int)$before->fields['n'];
    synchronize();
    $after = $db->Execute("SELECT COUNT(*) AS n FROM " . TABLE_PHREEHELP);
    $after = $after->EOF ? 0 : (int)$after->fields['n'];
    // The flag exists to make the next Help open reindex. It just happened.
    write_configure('PHREEHELP_FORCE_RELOAD', '0');

    printf("  %-18s %d topics -> %d", $co, $before, $after);
    if ($check !== '') {
        $r = $db->Execute("SELECT doc_title FROM " . TABLE_PHREEHELP
                        . " WHERE doc_pos = '" . db_input($check) . "'");
        if ($r->EOF) { printf("   %s NOT FOUND\n", $check); $failed++; }
        else         { printf("   %s = %s\n", $check, $r->fields['doc_title']); }
    } else {
        printf("\n");
    }
}
exit($failed ? 1 : 0);
