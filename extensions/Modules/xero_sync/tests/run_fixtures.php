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
//  Path: /modules/xero_sync/tests/run_fixtures.php
//
// Runs every golden fixture in fixtures/ through xero_builder: build the document, write the CSV,
// build the API body with only the shared known item codes and again with every line's code known,
// and list the items to create. Needs no database and no FreeBooks bootstrap:
//
//   php tests/run_fixtures.php [case-name-fragment]
//
// Numbers compare to 2 decimal places and strings exactly, warnings included, since users read
// them. The CSV Quantity column compares as a number: the fixtures write 2.0 where this module
// writes 2, and Xero reads both alike. Exits 1 when any case fails.
//
if (PHP_SAPI !== 'cli') exit;

$base = dirname(__DIR__);
require($base . '/language/en_us/language.php');
require($base . '/classes/xero_builder.php');

const QUANTITY_COLUMN = 17;

// Differences between $expected and $actual, as "path: reason" strings.
function compare($expected, $actual, string $path = ''): array {
  if (is_array($expected)) {
    if (!is_array($actual)) return ["$path: expected an array, got " . json_encode($actual)];
    $diffs = [];
    foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $key) {
      if (!array_key_exists($key, $actual))   { $diffs[] = "$path.$key: missing"; continue; }
      if (!array_key_exists($key, $expected)) { $diffs[] = "$path.$key: unexpected " . json_encode($actual[$key]); continue; }
      $diffs = array_merge($diffs, compare($expected[$key], $actual[$key], "$path.$key"));
    }
    return $diffs;
  }
  if ((is_int($expected) || is_float($expected)) && (is_int($actual) || is_float($actual))) {
    return round((float)$expected, 2) == round((float)$actual, 2) ? [] : ["$path: expected $expected, got $actual"];
  }
  return $expected === $actual ? [] : ["$path: expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', got ' . json_encode($actual, JSON_UNESCAPED_UNICODE)];
}

// A CSV table with its Quantity column read as numbers, so 2.0 and 2 compare equal.
function quantities_as_numbers(array $rows): array {
  foreach ($rows as $i => &$row) {
    if ($i > 0 && isset($row[QUANTITY_COLUMN])) $row[QUANTITY_COLUMN] = (float)$row[QUANTITY_COLUMN];
  }
  return $rows;
}

function parse_csv(string $text): array {
  $handle = fopen('php://memory', 'r+');
  fwrite($handle, $text);
  rewind($handle);
  $rows = [];
  while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) $rows[] = $row;
  fclose($handle);
  return $rows;
}

$shared  = json_decode(file_get_contents(__DIR__ . '/fixtures/_shared.json'), true);
$builder = new xero_builder($shared['mapping']);
$known   = $shared['known_item_codes'];
$filter  = $argv[1] ?? '';

$failed = 0;
$ran = 0;
foreach (glob(__DIR__ . '/fixtures/[0-9]*.json') as $file) {
  $case = json_decode(file_get_contents($file), true);
  if ($filter !== '' && strpos($case['name'], $filter) === false) continue;
  $ran++;
  $expected = $case['expected'];
  $opts = $case['opts'] + $shared['default_opts'];
  $diffs = [];
  try {
    $doc = $builder->build_document($case['record'], $case['doc_type'], $opts);
    $diffs = array_merge($diffs, compare($expected['document'], $doc, 'document'));

    $text = $builder->csv_text([$doc]);
    if (substr($text, -2) !== "\r\n") $diffs[] = 'csv_text: lines do not end in CRLF';
    $diffs = array_merge($diffs, compare(quantities_as_numbers($expected['csv_rows']), quantities_as_numbers(parse_csv($text)), 'csv'));

    $all_codes = $known;
    foreach ($doc['lines'] as $line) if ($line['item_code'] !== '') $all_codes[] = $line['item_code'];
    $diffs = array_merge($diffs, compare($expected['xero_payload_known_items_only'],
      $builder->payload($doc, $shared['tax_name_to_code'], $known), 'payload_known'));
    $diffs = array_merge($diffs, compare($expected['xero_payload_after_item_autocreate'],
      $builder->payload($doc, $shared['tax_name_to_code'], $all_codes), 'payload_all'));
    $diffs = array_merge($diffs, compare($expected['items_to_autocreate'], $builder->items_to_create($doc, $known), 'items'));
  } catch (Throwable $e) {
    $diffs[] = get_class($e) . ': ' . $e->getMessage();
  }

  if ($diffs) {
    $failed++;
    echo "FAIL {$case['name']}\n";
    foreach ($diffs as $diff) echo "     $diff\n";
  } else {
    echo "ok   {$case['name']}\n";
  }
}

echo "\n" . ($ran - $failed) . " of $ran cases passed\n";
exit($failed || !$ran ? 1 : 0);
