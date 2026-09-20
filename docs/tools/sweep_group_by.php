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
//  Path: /docs/tools/sweep_group_by.php
//
/**
 * READ ONLY. Which GROUP BY queries does ONLY_FULL_GROUP_BY refuse?
 *
 * MySQL 8 has that mode on by default and MariaDB does not, so every one of
 * these is a page that works here and fails there. There is no way to settle it
 * by reading: whether a selected column is allowed depends on whether the server
 * can prove it functionally dependent on the grouped one, and it can - an
 * aggregate-only query ordering by a column the join equates to the grouped one
 * is accepted, which no reasonable person would predict from the manual.
 *
 * So each statement is reconstructed from the source, its variables replaced
 * with literals, and handed to the server as an EXPLAIN. The server's answer is
 * the verdict. Queries that cannot be reconstructed are reported as such and
 * counted, never quietly dropped.
 *
 * EXPLAIN is enough: 1055 is raised while the statement is being resolved, so
 * nothing is executed and no row is read.
 *
 *   docker exec <c> php /opt/repo/docs/tools/sweep_group_by.php [company]
 */

$company = $argv[1] ?? getenv('FREEBOOKS_COMPANY') ?: 'demo';
$webroot = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';

require $webroot . '/my_files/' . $company . '/config.php';
$pdo = new PDO('mysql:host=' . DB_SERVER_HOST . ';dbname=' . $company,
               DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET SESSION sql_mode = CONCAT(@@session.sql_mode, ',ONLY_FULL_GROUP_BY')");
printf("session mode: %s\n", $pdo->query('select @@session.sql_mode')->fetchColumn());

// TABLE_* constants -> real names.
$tables = array();
foreach (glob("$webroot/modules/*/config.php") as $cfg) {
    if (preg_match_all("/define\('(TABLE_[A-Z0-9_]+)',\s*DB_PREFIX\s*\.\s*'([a-z0-9_]+)'/",
                       file_get_contents($cfg), $m, PREG_SET_ORDER)) {
        foreach ($m as $one) $tables[$one[1]] = $one[2];
    }
}
// Longest name first: TABLE_INVENTORY is a prefix of TABLE_INVENTORY_PURCHASE,
// and replacing the short one first leaves inventory_PURCHASE behind.
uksort($tables, function ($a, $b) { return strlen($b) - strlen($a); });

/**
 * From the start of a SELECT to the end of the PHP expression that holds it:
 * the terminating ; or ) at depth zero, outside quotes. Quotes and the
 * concatenation joins are then removed, leaving something SQL-shaped.
 */
function statement_at($src, $start) {
    // $start is the OPENING quote of the PHP string, not the text inside it.
    // Starting one character later leaves the scan believing it is outside a
    // string, so the closing quote reads as an opening one and every quote after
    // it is inverted - which found one statement out of eighteen.
    $delim = $src[$start];
    if ($delim !== '"' && $delim !== "'") return null;
    $len = strlen($src); $in = false; $depth = 0; $end = null;
    for ($j = $start; $j < $len && $j < $start + 8000; $j++) {
        $ch = $src[$j];
        if ($in) { if ($ch === '\\') { $j++; continue; } if ($ch === $delim) $in = false; continue; }
        if ($ch === $delim) { $in = true; continue; }
        if ($ch === '(') $depth++;
        elseif ($ch === ')') { if ($depth === 0) { $end = $j; break; } $depth--; }
        elseif ($ch === ';' && $depth === 0) { $end = $j; break; }
    }
    if ($end === null) return null;
    $arg = substr($src, $start, $end - $start);
    // Collapse only the dots that join PHP strings - those sit against a quote.
    // A blanket /\s*\.\s*/ also eats SQL's own qualifiers, turning
    // h.account_id into "h account_id" and every statement into a syntax error.
    $arg = preg_replace('/' . preg_quote($delim, '/') . '\s*\.\s*/', '', $arg);
    $arg = preg_replace('/\s*\.\s*' . preg_quote($delim, '/') . '/', '', $arg);
    return str_replace($delim, '', $arg);
}

/** The Execute( ... ) argument, spliced back into something SQL-shaped. */
function sql_of_call($src, $open) {
    $i = strpos($src, '(', $open);
    if ($i === false) return null;
    $depth = 0; $end = null; $len = strlen($src); $q = null;
    for ($j = $i; $j < $len && $j < $i + 8000; $j++) {
        $ch = $src[$j];
        if ($q !== null) { if ($ch === '\\') { $j++; continue; } if ($ch === $q) $q = null; continue; }
        if ($ch === '"') { $q = $ch; continue; }          // ' is SQL's, keep it
        if ($ch === '(') $depth++;
        elseif ($ch === ')') { $depth--; if ($depth === 0) { $end = $j; break; } }
    }
    if ($end === null) return null;
    $arg = str_replace('"', '', substr($src, $i + 1, $end - $i - 1));
    return preg_replace('/\s*\.\s*/', ' ', $arg);
}

$rows = array(); $n_skip = 0;
$files = array();
foreach (array('modules', 'includes') as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$webroot/$dir"));
    foreach ($it as $f) if ($f->isFile() && substr($f, -4) === '.php') $files[] = (string)$f;
}
sort($files);

foreach ($files as $file) {
    $src = file_get_contents($file);
    if (stripos($src, 'group by') === false) continue;
    $short = str_replace("$webroot/", '', $file);
    // Anchor on the statement itself. Most of these are assembled into a
    // variable and executed later - $sql = "select ..."; $db->Execute($sql) -
    // so anchoring on Execute( finds a bare variable and reads nothing. Walking
    // back from each `group by` to the SELECT that owns it finds both shapes.
    if (!preg_match_all('/\bgroup\s+by\b/i', $src, $hits, PREG_OFFSET_CAPTURE)) continue;

    foreach ($hits[0] as $hit) {
        $before = substr($src, max(0, $hit[1] - 4000), min(4000, $hit[1]));
        if (!preg_match_all('/(?:"|\')\s*select\s/i', $before, $sm, PREG_OFFSET_CAPTURE)) continue;
        $start  = max(0, $hit[1] - 4000) + end($sm[0])[1];   // the quote itself
        $sql    = statement_at($src, $start);
        if ($sql === null || !preg_match('/\bgroup\s+by\b/i', $sql)) continue;
        $line   = substr_count($src, "\n", 0, $start) + 1;

        $q = $sql;
        foreach ($tables as $const => $real) $q = str_replace($const, $real, $q);
        // Anything still PHP becomes a literal. The query only has to resolve,
        // not to mean anything: 1055 is decided before a row is read.
        $q = preg_replace('/\$[A-Za-z_][A-Za-z0-9_]*(?:\s*(?:->|\[)[^\s,()]*\]?)*/', '1', $q);
        $q = preg_replace('/\blimit\s+1\b.*$/i', '', $q);      // a mangled LIMIT helps nobody
        $q = trim(preg_replace('/\s+/', ' ', $q), " ;\t\n");

        if (!preg_match('/^select\s/i', $q)) { $n_skip++; continue; }
        try {
            $pdo->query('EXPLAIN ' . $q);
            $rows[] = array('OK', $short . ':' . $line, '');
        } catch (Throwable $e) {
            $msg = preg_replace('/\s+/', ' ', $e->getMessage());
            if (strpos($msg, '1055') !== false) {
                preg_match("/'([^']+)' isn't in GROUP BY/", $msg, $cm);
                $rows[] = array('REFUSED', $short . ':' . $line, $cm[1] ?? 'a selected column');
            } else {
                // A statement this tool failed to rebuild, not a statement the
                // server dislikes. Reported, because a silent drop here would
                // read as a clean bill of health.
                $rows[] = array('UNREADABLE', $short . ':' . $line,
                    substr(preg_replace('/^SQLSTATE\[\w+\]: [^:]+: /', '', $msg), 0, 60), $q);
            }
        }
    }
}

$by = array('REFUSED' => array(), 'OK' => array(), 'UNREADABLE' => array());
foreach ($rows as $r) $by[$r[0]][] = $r;

foreach (array('REFUSED', 'UNREADABLE', 'OK') as $verdict) {
    $title = array('REFUSED'    => 'REFUSED by ONLY_FULL_GROUP_BY - these fail on MySQL 8',
                   'UNREADABLE' => 'could not be reconstructed - judge these by hand',
                   'OK'         => 'accepted');
    printf("\n%s (%d)\n%s\n", $title[$verdict], count($by[$verdict]), str_repeat('-', 60));
    foreach ($by[$verdict] as $r) {
        printf("  %-58s %s\n", $r[1], $r[2]);
        // The reconstruction, for the ones a person now has to judge: "judge by
        // hand" without showing the text is not an instruction anyone can follow.
        if (isset($r[3])) printf("      %s\n", substr($r[3], 0, 150));
    }
}
printf("\n%d GROUP BY statements found; %d were not SELECTs and were skipped.\n", count($rows), $n_skip);
echo "Measured on this server with the mode switched on. MySQL 8's own dependency\n";
echo "detection may differ, so a REFUSED verdict is certain and an OK is likely.\n";
