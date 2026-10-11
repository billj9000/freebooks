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
//  Path: /modules/quickbooks_sync/tests/run_fixtures.php
//
// Runs every fixture in fixtures/ through quickbooks_builder: build the document, then the import
// file rows, the body that creates its customer, and the API body. Needs no database and no FreeBooks bootstrap:
//
//   php tests/run_fixtures.php [case-name-fragment]
//
// Numbers compare to 2 decimal places and strings exactly, warnings included, since users read
// them. Every case is also checked for what QuickBooks itself enforces or FreeBooks relies on,
// whatever the fixture expects:
//   - each sales line's amount is its quantity times its unit price, to the penny;
//   - the body's lines, less the discount, plus its stated tax come to the FreeBooks total;
//   - TotalTax is the sum of the tax lines;
//   - a document with no warnings carries exactly the VAT FreeBooks saved;
//   - the file rows have no negative amount, each row's amount is its quantity times its rate, the
//     amounts and VAT add up to the document's total and VAT, and each code's VAT to its tax line.
// Exits 1 when any case fails.
//
if (PHP_SAPI !== 'cli') exit;

$base = dirname(__DIR__);
require($base . '/language/en_us/language.php');
require($base . '/classes/quickbooks_builder.php');

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

// The rules in the header that hold for every document and its body.
function invariants(array $record, array $doc, array $payload): array {
  $diffs = [];
  $lines = 0.0;
  foreach ($payload['Line'] as $i => $line) {
    if ($line['DetailType'] === 'DiscountLineDetail') { $lines -= $line['Amount']; continue; }
    $detail = $line['SalesItemLineDetail'];
    if (round($detail['Qty'] * $detail['UnitPrice'], 2) != round($line['Amount'], 2)) {
      $diffs[] = "invariant: line $i amount {$line['Amount']} is not {$detail['Qty']} x {$detail['UnitPrice']}";
    }
    $lines += $line['Amount'];
  }
  $tax = $payload['TxnTaxDetail']['TotalTax'];
  if (round($lines + $tax, 2) != round($doc['source_total'], 2)) {
    $diffs[] = 'invariant: lines ' . round($lines, 2) . " plus tax $tax do not come to {$doc['source_total']}";
  }
  if (round(array_sum(array_column($payload['TxnTaxDetail']['TaxLine'], 'Amount')), 2) != round($tax, 2)) {
    $diffs[] = "invariant: the tax lines do not add up to TotalTax $tax";
  }
  $saved_tax = round((float)$record['tax'] * ((float)($record['currency_rate'] ?? 0) ?: 1.0), 2);
  if (!$doc['warnings'] && round($tax, 2) != $saved_tax) {
    $diffs[] = "invariant: tax $tax is not the saved VAT $saved_tax";
  }
  return $diffs;
}

// The rules in the header that hold for every document's import file rows.
function file_invariants(array $doc, array $rows): array {
  $diffs = [];
  $by_code = [];
  foreach ($rows as $i => $row) {
    if ((float)$row['amount'] < 0 || (float)$row['tax_amount'] < 0) $diffs[] = "file invariant: row $i has a negative amount";
    if (round((float)$row['quantity'] * (float)$row['rate'], 2) != round((float)$row['amount'], 2)) {
      $diffs[] = "file invariant: row $i amount {$row['amount']} is not {$row['quantity']} x {$row['rate']}";
    }
    $by_code[$row['tax_code']] = ($by_code[$row['tax_code']] ?? 0) + (float)$row['tax_amount'];
  }
  $amounts = array_sum(array_map('floatval', array_column($rows, 'amount')));
  $tax     = array_sum(array_map('floatval', array_column($rows, 'tax_amount')));
  if (round($amounts + $tax, 2) != round($doc['source_total'], 2)) {
    $diffs[] = 'file invariant: rows come to ' . round($amounts + $tax, 2) . ", not {$doc['source_total']}";
  }
  if (round($tax, 2) != round($doc['total_tax'], 2)) $diffs[] = "file invariant: row VAT $tax is not {$doc['total_tax']}";
  foreach ($doc['tax_lines'] as $tax_line) {
    if (round($by_code[$tax_line['tax_code']] ?? 0, 2) != round($tax_line['amount'], 2)) {
      $diffs[] = "file invariant: rows for {$tax_line['tax_code']} do not carry its VAT {$tax_line['amount']}";
    }
  }
  return $diffs;
}

$shared  = json_decode(file_get_contents(__DIR__ . '/fixtures/_shared.json'), true);
$builder = new quickbooks_builder($shared['mapping']);
$filter  = $argv[1] ?? '';

$failed = 0;
$ran = 0;
foreach (glob(__DIR__ . '/fixtures/[0-9]*.json') as $file) {
  $case = json_decode(file_get_contents($file), true);
  if (!is_array($case)) {
    $ran++;
    $failed++;
    echo 'FAIL ' . basename($file) . "\n     not valid JSON: " . json_last_error_msg() . "\n";
    continue;
  }
  if ($filter !== '' && strpos($case['name'], $filter) === false) continue;
  $ran++;
  $expected = $case['expected'];
  $opts = $case['opts'] + $shared['default_opts'];
  $diffs = [];
  try {
    $doc = $builder->build_document($case['record'], $case['doc_type'], $opts);
    $diffs = array_merge($diffs, compare($expected['document'], $doc, 'document'));
    $refusal = $builder->file_refusal($doc);
    $diffs = array_merge($diffs, compare($expected['file_refusal'], $refusal, 'file_refusal'));
    if ($refusal === null) {
      $rows = $builder->file_rows($doc);
      $diffs = array_merge($diffs, compare($expected['file_rows'], $rows, 'file_rows'));
      $diffs = array_merge($diffs, file_invariants($doc, $rows));
      $text = $builder->file_text($case['doc_type'], [$doc]);
      if (substr($text, -2) !== "\r\n") $diffs[] = 'file_text: lines do not end in CRLF';
      if (substr_count($text, "\r\n") !== count($rows) + 1) $diffs[] = 'file_text: not one line per row plus the headers';
      $columns = count(quickbooks_builder::FILE_HEADERS[$case['doc_type']]);
      foreach (explode("\r\n", rtrim($text, "\r\n")) as $n => $line) {
        if (count(str_getcsv($line, ',', '"', '')) !== $columns) $diffs[] = "file_text: line $n does not have $columns columns";
      }
    }
    $diffs = array_merge($diffs, compare($expected['customer'], $builder->customer_payload($doc), 'customer'));
    $payload = $builder->payload($doc, $shared['refs']);
    $diffs = array_merge($diffs, compare($expected['payload'], $payload, 'payload'));
    $diffs = array_merge($diffs, invariants($case['record'], $doc, $payload));
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
