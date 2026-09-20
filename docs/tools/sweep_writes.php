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
//  Path: /docs/tools/sweep_writes.php
//
/**
 * READ ONLY. Every place the application writes a value into a column that is
 * not a string, checked against what that column will actually accept.
 *
 * Two write paths, and only one of them has ever been examined. db_perform()
 * accounts for 181 call sites and carries a runtime net; raw INSERT and UPDATE
 * statements executed straight through queryFactory account for about 400 and
 * carry nothing at all. A server at its defaults refuses '' for a date or a
 * number on both.
 *
 * A static sweep cannot know what a variable holds at runtime, so it does not
 * pretend to: every finding is a CANDIDATE, and the run reports how much of the
 * source it could not parse, because a sweep that quietly skips what it does not
 * understand reports a clean bill of health it has not earned.
 *
 *   docker exec <c> php /opt/repo/docs/tools/sweep_writes.php [company] [--all]
 *
 * --all also lists the call sites judged safe, for auditing the judgement.
 */

$company = $argv[1] ?? getenv('FREEBOOKS_COMPANY') ?: 'demo';
if ($company === '--all') $company = 'demo';
$show_safe = in_array('--all', $argv, true);
$webroot   = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';

require $webroot . '/my_files/' . $company . '/config.php';
$pdo = new PDO('mysql:host=' . DB_SERVER_HOST . ';dbname=' . $company,
               DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- what each column will accept -----------------------------------------
$STRINGY = array('varchar','char','text','tinytext','mediumtext','longtext',
                 'blob','mediumblob','longblob');   // enum/set are NOT strings here
$cols = array();
foreach ($pdo->query("select table_name, column_name, data_type, column_type
                      from information_schema.columns where table_schema = database()")
              ->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cols[strtolower($c['table_name'])][strtolower($c['column_name'])] = $c;
}

// TABLE_* constants, so a statement naming TABLE_JOURNAL_MAIN can be resolved.
$tables = array();
foreach (glob("$webroot/modules/*/config.php") as $cfg) {
    if (preg_match_all("/define\('(TABLE_[A-Z0-9_]+)',\s*DB_PREFIX\s*\.\s*'([a-z0-9_]+)'/",
                       file_get_contents($cfg), $m, PREG_SET_ORDER)) {
        foreach ($m as $one) $tables[$one[1]] = $one[2];
    }
}

/**
 * Can this PHP expression put a blank into the column?
 *
 * The previous sweep treated ANY ternary as guarded, which is how the two
 * point-of-sale rounding rows went unseen: they are ternaries whose other branch
 * is '' exactly. A ternary is only safe if neither branch can be blank.
 */
function verdict($expr) {
    $e = trim($expr, " \t\n\r,");
    if ($e === '') return array('suspect', 'empty expression');
    if (strpos($e, 'column_empty') !== false)            return array('safe', 'column_empty()');
    // clean_value() ends with `if ($value == null || $value == '') $value = 0;`
    // so it cannot hand a blank to anything. Checked in common_classes.php before
    // being trusted here - the whole point of this tool is not to take such a
    // thing on faith.
    if (strpos($e, 'clean_value') !== false)             return array('safe', 'clean_value() returns 0 when blank');
    if (preg_match("/^'?-?[0-9.]+'?$/", $e))             return array('safe', 'numeric literal');
    if (preg_match('/^(now\(\)|\'now\(\)\'|\'null\'|null)$/i', $e)) return array('safe', 'sql keyword');
    if (preg_match('/^\'\'$/', $e) || preg_match('/^""$/', $e))    return array('suspect', "writes '' outright");
    // a branch that yields blank, however it is spelled
    if (preg_match("/\?\?\s*(''|\"\")/", $e))            return array('suspect', "?? '' yields blank");
    if (preg_match("/\?:\s*(''|\"\")/", $e))             return array('suspect', "?: '' yields blank");
    if (preg_match("/:\s*(''|\"\")\s*$/", $e))           return array('suspect', "ternary else-branch is ''");
    if (preg_match("/\?\s*(''|\"\")\s*:/", $e))          return array('suspect', "ternary then-branch is ''");
    if (preg_match('/^\(?\s*(int|float|double|bool)\)/i', $e)) return array('safe', 'cast to a number');
    if (preg_match('/^(intval|floatval|round|abs|count|strtotime|time)\s*\(/i', $e)) return array('safe', 'numeric function');
    if (preg_match('/date\s*\(/i', $e))                  return array('safe', 'date()');
    return array('suspect', 'cannot be proven non-blank');
}

/**
 * The argument of an Execute( ... ) call, spliced into something SQL-shaped:
 * quotes dropped and the PHP concatenation joins turned back into spaces, so
 * "update " . TABLE_X . " set a = '" . $b . "'" reads as
 * update TABLE_X set a = ' $b '.
 */
function sql_of_call($src, $open) {
    $i = strpos($src, '(', $open);
    if ($i === false) return null;
    $depth = 0; $end = null; $len = strlen($src); $q = null;
    for ($j = $i; $j < $len && $j < $i + 6000; $j++) {
        $ch = $src[$j];
        if ($q !== null) {                       // inside a quoted run
            if ($ch === '\\') { $j++; continue; }
            if ($ch === $q) $q = null;
            continue;
        }
        if ($ch === '"' || $ch === "'") { $q = $ch; continue; }
        if ($ch === '(') $depth++;
        elseif ($ch === ')') { $depth--; if ($depth === 0) { $end = $j; break; } }
    }
    if ($end === null) return null;
    $arg = substr($src, $i + 1, $end - $i - 1);
    if (!preg_match('/^\s*"?\s*update\s/i', $arg)) return false;  // a SELECT or similar: out of scope, not a failure
    $arg = str_replace('"', '', $arg);
    return preg_replace('/\s*\.\s*/', ' ', $arg);
}

$findings = array('db_perform' => array(), 'raw' => array());
$stats = array('files' => 0, 'db_perform_calls' => 0, 'raw_calls' => 0,
               'checked' => 0, 'unresolved_table' => 0, 'unparsed_raw' => 0);

$files = array();
foreach (array('modules', 'includes') as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$webroot/$dir"));
    foreach ($it as $f) if ($f->isFile() && substr($f, -4) === '.php') $files[] = (string)$f;
}
sort($files);

foreach ($files as $file) {
    $src = file_get_contents($file);
    $short = str_replace("$webroot/", '', $file);
    $stats['files']++;
    $line_of = function ($pos) use ($src) { return substr_count($src, "\n", 0, $pos) + 1; };

    // ---------- 1. db_perform(TABLE_X, array(...)|$var, ...) ----------------
    if (preg_match_all("/db_perform\(\s*(TABLE_[A-Z0-9_]+)\s*,\s*(\\\$[A-Za-z_0-9]+|array\()/",
                       $src, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($calls as $call) {
            $stats['db_perform_calls']++;
            $tbl = $tables[$call[1][0]] ?? null;
            if (!$tbl || !isset($cols[$tbl])) { $stats['unresolved_table']++; continue; }
            if ($call[2][0] === 'array(') {
                $body = substr($src, $call[0][1], 4000);
            } else {
                $var = preg_quote($call[2][0], '/');
                if (!preg_match_all("/$var\s*=\s*array\((.*?)\n\s*\);/s", $src, $mm)) continue;
                $body = end($mm[1]);
            }
            if (!preg_match_all("/'([a-z_0-9]+)'\s*=>\s*([^,\n]+)/", $body, $pairs, PREG_SET_ORDER)) continue;
            foreach ($pairs as $pair) {
                list(, $col, $expr) = $pair;
                if (!isset($cols[$tbl][$col])) continue;
                if (in_array($cols[$tbl][$col]['data_type'], $GLOBALS['STRINGY'])) continue;
                $stats['checked']++;
                list($v, $why) = verdict($expr);
                if ($v === 'suspect' || $show_safe) {
                    $findings['db_perform'][] = array($tbl, $col, $cols[$tbl][$col]['column_type'],
                        trim($expr), $why, $short . ':' . $line_of($call[0][1]), $v);
                }
            }
        }
    }

    // ---------- 2. raw UPDATE ... SET col = '$expr' -------------------------
    // The statement is usually built by concatenation, so the source text is
    // normalised into something SQL-shaped first: constants resolved, the PHP
    // string joins removed.
    if (preg_match_all('/Execute\s*\(/', $src, $stmts, PREG_OFFSET_CAPTURE)) {
        foreach ($stmts[0] as $st) {
            // The statement is an expression, not a string: the table name lives
            // OUTSIDE the quotes, as "update " . TABLE_X . " set ...". Taking only
            // the first quoted run finds "update " and nothing else - which is why
            // the first version of this parsed none of the 173 it found.
            $sql = sql_of_call($src, $st[1]);
            if ($sql === false) continue;                       // not an UPDATE
            if ($sql === null) { $stats['unparsed_raw']++; continue; }
            $stats['raw_calls']++;
            $line = $line_of($st[1]);
            // which table
            if (!preg_match('/update\s+"?\s*\.?\s*(TABLE_[A-Z0-9_]+)/i', $sql, $tm)) {
                $stats['unparsed_raw']++; continue;
            }
            $tbl = $tables[$tm[1]] ?? null;
            if (!$tbl || !isset($cols[$tbl])) { $stats['unresolved_table']++; continue; }
            if (!preg_match('/\sset\s+(.*?)(\swhere\s|$)/is', $sql, $sm)) { $stats['unparsed_raw']++; continue; }
            foreach (preg_split('/,(?![^(]*\))/', $sm[1]) as $assign) {
                if (!preg_match("/([a-z_0-9]+)\s*=\s*(.+)/is", trim($assign), $am)) continue;
                $col = strtolower($am[1]);
                if (!isset($cols[$tbl][$col])) continue;
                if (in_array($cols[$tbl][$col]['data_type'], $GLOBALS['STRINGY'])) continue;
                $val = trim($am[2]);
                // only an interpolated value can be blank; a literal cannot
                if (!preg_match('/[$]|\.\s*\$/', $val)) continue;
                $stats['checked']++;
                // The value arrives here as ' $x ' - the quotes are the SQL's, and
                // an empty $x makes them '' exactly. Same judgement as a
                // db_perform array, so the same function decides it.
                list($v, $why) = verdict(trim($val, " '\""));
                if ($v === 'suspect' || $show_safe) {
                    $findings['raw'][] = array($tbl, $col, $cols[$tbl][$col]['column_type'],
                        $val, $why, $short . ':' . $line, $v);
                }
            }
        }
    }
}

// --- report ---------------------------------------------------------------
function report($title, $rows, $show_safe) {
    printf("\n%s\n%s\n", $title, str_repeat('-', strlen($title)));
    if (!$rows) { echo "  nothing\n"; return; }
    $by = array();
    foreach ($rows as $r) if ($r[6] === 'suspect' || $show_safe) $by[$r[0] . '.' . $r[1]][] = $r;
    ksort($by);
    foreach ($by as $what => $rs) {
        printf("  %-42s %s\n", $what, $rs[0][2]);
        foreach ($rs as $r) printf("      %-58s %s\n", substr($r[3], 0, 56), $r[5]);
        printf("      -> %s\n", $rs[0][4]);
    }
    printf("  %d column/table combinations\n", count($by));
}

report('db_perform() writes that cannot be proven non-blank', $findings['db_perform'], $show_safe);
report('raw UPDATE writes into non-string columns', $findings['raw'], $show_safe);

printf("\nscanned %d files: %d db_perform calls, %d raw UPDATE statements, %d column writes checked\n",
    $stats['files'], $stats['db_perform_calls'], $stats['raw_calls'], $stats['checked']);
printf("could NOT resolve: %d statements whose table was unknown, %d raw statements not parsed\n",
    $stats['unresolved_table'], $stats['unparsed_raw']);
echo "Every finding is a candidate: a static sweep cannot know what a variable holds.\n";
