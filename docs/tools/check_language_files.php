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
//  Path: /docs/tools/check_language_files.php
//
/**
 * Reports every phrase a translated language file lacks.
 *
 *   php docs/tools/check_language_files.php          all languages
 *   php docs/tools/check_language_files.php nl_nl    one language
 *
 * FreeBooks loads a module's language file for the user's language if it exists, and the en_us
 * one only if it does not - per file, not per phrase. So a phrase added to an en_us file and not to
 * a translation of that file is an undefined constant in that language, and PHP stops the page.
 * Exits 1 if any phrase is missing, listing each by file and name. Covers trunk/ and extensions/,
 * but not Zen Cart's own files (ZenCartFiles), the Help manual or vendor code.
 *
 * British English (en_gb) is generated: if it is reported here, run build_en_gb.php --write.
 */
$repo = realpath(__DIR__ . '/../..');
$only = $argv[1] ?? '';

// The constants a PHP file defines, skipping commented-out lines.
function lc_defines(string $path): array {
  $names = []; $toks = token_get_all(file_get_contents($path)); $n = count($toks);
  for ($i = 0; $i < $n; $i++) {
    if (!is_array($toks[$i]) || $toks[$i][0] !== T_STRING || strtolower($toks[$i][1]) !== 'define') continue;
    for ($j = $i + 1; $j < $n && is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE; $j++);
    if ($j >= $n || $toks[$j] !== '(') continue;
    for ($k = $j + 1; $k < $n && is_array($toks[$k]) && $toks[$k][0] === T_WHITESPACE; $k++);
    if (is_array($toks[$k]) && $toks[$k][0] === T_CONSTANT_ENCAPSED_STRING) $names[trim($toks[$k][1], "'\"")] = true;
  }
  return $names;
}

$missing = []; $checked = [];
foreach (['trunk', 'extensions'] as $top) {
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$repo/$top", FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || preg_match('#/(manual|vendor)/|ZenCartFiles#', $p)) continue;
    if (!preg_match('#/language/([a-z]{2}_[a-z]{2})/#', $p, $m) || $m[1] === 'en_us') continue;
    if ($only !== '' && $m[1] !== $only) continue;
    $en = str_replace("/{$m[1]}/", '/en_us/', $p);
    if (!is_file($en)) continue; // a file only the translation has: nothing to compare with
    $checked[$m[1]] = ($checked[$m[1]] ?? 0) + 1;
    foreach (array_diff_key(lc_defines($en), lc_defines($p)) as $name => $_) $missing[] = [$m[1], substr($p, strlen($repo) + 1), $name];
  }
}
foreach ($missing as [$loc, $file, $name]) echo "$loc  $file  $name\n";
ksort($checked);
foreach ($checked as $loc => $n) {
  $c = count(array_filter($missing, fn($r) => $r[0] === $loc));
  echo "$loc: $n files checked, " . ($c ? "$c phrases MISSING" : 'complete') . "\n";
}
exit($missing ? 1 : 0);
