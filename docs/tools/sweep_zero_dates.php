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
//  Path: /docs/tools/sweep_zero_dates.php
//
/**
 * READ ONLY. Every 0000-00-00 in the source, sorted by what the migration will
 * have to do to it.
 *
 * The zero date is legal on MariaDB, which omits NO_ZERO_DATE from its defaults,
 * and refused by MySQL 8, which does not. The plan is to make those columns
 * nullable and be rid of it - and the code half of that job is not one change
 * repeated, it is four different changes:
 *
 *   SCHEMA    a column declared DEFAULT '0000-00-00'   -> the migration alters it
 *   WRITE     code that stores a zero date             -> store NULL instead
 *   SQL TEST  a WHERE comparing against one            -> IS NULL
 *   PHP TEST  a comparison in PHP, usually before      -> === null, and mind that
 *             displaying or skipping a field              '' and null differ
 *
 * Counting them as one number hides that. This sorts them, shows the line, and
 * leaves anything it cannot place in an UNCLASSIFIED list rather than guessing.
 *
 *   docker exec <c> php /opt/repo/docs/tools/sweep_zero_dates.php [--all]
 */

$webroot  = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$show_all = in_array('--all', $argv, true);

/** Blank comments, keeping byte positions and line breaks. */
function strip_comments($src) {
    $out = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t) && ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
            $out .= preg_replace('/[^\n]/', ' ', $t[1]);
        } else {
            $out .= is_array($t) ? $t[1] : $t;
        }
    }
    return $out;
}

$files = array();
foreach (array('modules', 'includes', 'install') as $dir) {
    if (!is_dir("$webroot/$dir")) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$webroot/$dir"));
    foreach ($it as $f) if ($f->isFile() && substr($f, -4) === '.php') $files[] = (string)$f;
}
sort($files);

$buckets = array('SCHEMA' => array(), 'WRITE' => array(), 'SQL TEST' => array(),
                 'PHP TEST' => array(), 'UNCLASSIFIED' => array());

foreach ($files as $file) {
    $raw = file_get_contents($file);
    if (strpos($raw, '0000-00-00') === false) continue;
    $src   = strip_comments($raw);
    $short = str_replace("$webroot/", '', $file);
    $lines = explode("\n", $src);

    foreach ($lines as $n => $text) {
        if (strpos($text, '0000-00-00') === false) continue;
        $line = trim($text);
        $flat = preg_replace('/\s+/', ' ', $line);
        $where = $short . ':' . ($n + 1);

        // Order matters, and each test is asked in the order a reader would:
        // is this the schema, is it a test, or is it a value going somewhere?
        if (preg_match('/\bdefault\s+.?0000-00-00/i', $line)
            || preg_match('/\b(date|datetime|timestamp)\b.*0000-00-00/i', $line)) {
            $bucket = 'SCHEMA';
        } elseif (preg_match('/(==|!=|<>|===|!==)/', $line)
               || preg_match('/\b(strncmp|strcmp|strpos|substr)\s*\(/i', $line)) {
            $bucket = 'PHP TEST';
        } elseif (preg_match('/\b(where|and|or|having)\b[^;]*0000-00-00/i', $line)) {
            $bucket = 'SQL TEST';
        } elseif (preg_match('/=>|=|\?:|\breturn\b/', $line)) {
            // Includes the ternary forms - ($closed) ? $date : '0000-00-00' and
            // gen_db_date(...) ?: '0000-00-00' - which an assignment pattern
            // alone misses, and which were the bulk of what went unclassified.
            $bucket = 'WRITE';
        } else {
            $bucket = 'UNCLASSIFIED';
        }
        $buckets[$bucket][] = array($where, substr($flat, 0, 118));
    }
}

$total = 0;
$note = array(
  'SCHEMA'       => 'the migration alters these columns',
  'WRITE'        => 'these must store NULL instead',
  'SQL TEST'     => 'these become IS NULL',
  'PHP TEST'     => "these become === null - and note that '' and null are not the same test",
  'UNCLASSIFIED' => 'read these by hand; the tool would be guessing',
);
foreach ($buckets as $name => $rows) {
    $total += count($rows);
    printf("\n%s (%d) - %s\n%s\n", $name, count($rows), $note[$name], str_repeat('-', 72));
    if (!$rows) { echo "  none\n"; continue; }
    $show = ($show_all || $name === 'UNCLASSIFIED') ? $rows : array_slice($rows, 0, 12);
    foreach ($show as $r) printf("  %-46s %s\n", $r[0], $r[1]);
    if (count($rows) > count($show)) printf("  ... and %d more (--all to list them)\n", count($rows) - count($show));
}
printf("\n%d occurrences in %d files.\n", $total, count($files));
echo "SCHEMA and WRITE are the migration's work; the two TEST buckets are what\n";
echo "breaks quietly afterwards if they are not changed with it.\n";
