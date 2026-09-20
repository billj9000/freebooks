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
//  Path: /docs/tools/sweep_field_widths.php
//
/**
 * READ ONLY. Form fields that let someone type more than the column will hold.
 *
 * A server at its defaults does not truncate - it refuses, with error 1406,
 * Data too long. So a field whose maxlength exceeds its column's width is a save
 * that fails on a stock server and silently loses characters on a lax one, which
 * is the worse of the two outcomes and the one running today.
 *
 * READ THE PRECISION WARNING. This is the least conclusive of these sweeps, and
 * it is worth saying why rather than letting someone discover it. A field is
 * matched to a column BY NAME, and a name match does not establish that the page
 * writes that column - or writes to that table at all. Measured on the first pass:
 * five of nine findings were wrong that way. The DHL label form carries ship_*
 * fields 60 characters wide and journal_main.ship_primary_name holds 32, which
 * looks damning until you notice the page only READS journal_main and writes
 * shipping_log, whose recipient_name is varchar(64) and fits.
 *
 * So treat every line as a question to answer by opening the page, not as a
 * defect. Where a name appears at several widths the narrowest is shown and the
 * finding is listed separately, since even the table is then a guess.
 *
 * What it cannot see at all: values the server composes itself. A carrier code
 * joined to a service name, a description built with sprintf, a name imported
 * from a shop - none passes through a form field, so none has a maxlength to
 * check. Those need the page-level tests.
 *
 *   docker exec <c> php /opt/repo/docs/tools/sweep_field_widths.php [company] [--all]
 */

$company  = $argv[1] ?? getenv('FREEBOOKS_COMPANY') ?: 'demo';
if ($company === '--all') $company = 'demo';
$show_all = in_array('--all', $argv, true);
$webroot  = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';

require $webroot . '/my_files/' . $company . '/config.php';
$pdo = new PDO('mysql:host=' . DB_SERVER_HOST . ';dbname=' . $company,
               DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// column name -> the narrowest column of that name, and where it is
$narrowest = array(); $widths = array();
foreach ($pdo->query("select table_name, column_name, character_maximum_length
                      from information_schema.columns
                      where table_schema = database() and character_maximum_length is not null")
              ->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $n = strtolower($c['column_name']);
    $w = (int)$c['character_maximum_length'];
    if (!isset($narrowest[$n]) || $w < $narrowest[$n]['w']) {
        $narrowest[$n] = array('w' => $w, 't' => $c['table_name']);
    }
    // A name used by several tables at different widths cannot be resolved from
    // the field alone: 'description' is 30 characters in one table and 255 in
    // another, and matching a form to the narrowest produces a finding about a
    // table the page never writes to. Those are separated rather than counted.
    $widths[$n][$w] = true;
}

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
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$webroot/modules"));
foreach ($it as $f) if ($f->isFile() && substr($f, -4) === '.php') $files[] = (string)$f;
sort($files);

$over = array(); $ambiguous = array(); $ok = 0; $unmatched = array();
foreach ($files as $file) {
    $src = strip_comments(file_get_contents($file));
    if (stripos($src, 'maxlength') === false) continue;
    $short = str_replace("$webroot/", '', $file);

    // html_input_field('name', $value, '... maxlength="32" ...')  and plain markup
    $pats = array(
        "/html_input_field\s*\(\s*'([a-z_0-9]+)'.{0,120}?maxlength\s*=\s*[\"']?(\d+)/is",
        "/name\s*=\s*[\"']([a-z_0-9]+)[\"'].{0,120}?maxlength\s*=\s*[\"']?(\d+)/is",
        "/maxlength\s*=\s*[\"']?(\d+).{0,120}?name\s*=\s*[\"']([a-z_0-9]+)[\"']/is",
    );
    foreach ($pats as $i => $pat) {
        if (!preg_match_all($pat, $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) continue;
        foreach ($m as $hit) {
            list($field, $len) = $i === 2 ? array($hit[2][0], (int)$hit[1][0])
                                          : array($hit[1][0], (int)$hit[2][0]);
            $field = strtolower($field);
            $line  = substr_count($src, "\n", 0, $hit[0][1]) + 1;
            if (!isset($narrowest[$field])) { $unmatched[$field] = true; continue; }
            if ($len > $narrowest[$field]['w']) {
                $row = array($field, $len, $narrowest[$field]['w'], $narrowest[$field]['t'],
                             $short . ':' . $line);
                if (count($widths[$field]) > 1) $ambiguous[] = $row; else $over[] = $row;
            } else {
                $ok++;
            }
        }
    }
}

printf("\nCANDIDATES: a field wider than a column of the same name (%d)\n"
     . "check each one against the table the page actually writes\n%s\n", count($over), str_repeat('-', 76));
if (!$over) {
    echo "  none\n";
} else {
    usort($over, function ($a, $b) { return ($b[1] - $b[2]) - ($a[1] - $a[2]); });
    $seen = array();
    foreach ($over as $o) {
        $key = $o[0] . $o[1];
        if (isset($seen[$key]) && !$show_all) { $seen[$key]++; continue; }
        $seen[$key] = 1;
        printf("  %-26s form allows %-4d %s.%s holds %-4d  %s\n",
               $o[0], $o[1], $o[3], $o[0], $o[2], $o[4]);
    }
    $extra = array_sum($seen) - count($seen);
    if ($extra && !$show_all) printf("  (%d further occurrences of the same fields; --all to list them)\n", $extra);
}
printf("\nthe same name at several widths, so the table cannot be told from the field (%d)\n%s\n",
       count($ambiguous), str_repeat('-', 76));
$seen2 = array();
foreach ($ambiguous as $o) {
    if (isset($seen2[$o[0] . $o[1]]) && !$show_all) continue;
    $seen2[$o[0] . $o[1]] = true;
    printf("  %-26s form allows %-4d narrowest is %s.%s at %-4d  %s\n", $o[0], $o[1], $o[3], $o[0], $o[2], $o[4]);
}
printf("\n%d fields fit their column. %d field names match no column and were not judged.\n",
       $ok, count($unmatched));
echo "Values the server composes itself never pass a form field and are invisible here.\n";
