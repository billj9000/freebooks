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
//  Path: /docs/tools/repair_supplier_cost_currency.php
//
/**
 * Report, and with --apply repair, supplier purchase rows whose cost is in the
 * default currency although the row names another currency (issue #127).
 *
 * Posting a purchase order or purchase wrote the line's unit cost - a journal
 * amount, so always in the default currency - into the supplier's row without
 * converting it. A row is taken to have been written that way when its cost equals,
 * to within rounding noise, the default-currency unit cost of a purchase order (J4)
 * or purchase (J6) line from that supplier for that SKU. The latest such line is
 * the one that wrote it.
 *
 * The repaired cost is the line's own price in the row's currency when the order
 * was in that currency (unit cost x the order's exchange rate), and otherwise the
 * unit cost at the row currency's current rate.
 *
 * An order whose exchange rate is far from today's (more than --spread, default
 * 0.25 = 25%) is listed as SUSPECT. Orders imported from another system can carry
 * the rate the other way up with their amounts already in the order's currency, and
 * then the row's cost is right as it stands. Suspect rows are only repaired with
 * --include-suspect.
 *
 * Dry run by default; prints one tab-separated line per affected row.
 *
 *   docker exec <c> php -d display_errors=1 /opt/repo/docs/tools/repair_supplier_cost_currency.php <company> [--apply] [--include-suspect] [--spread=0.25]
 *
 * On Fly the tools are at /opt/freebooks/tools.
 */

$args     = array_slice($argv, 1);
$apply    = in_array('--apply', $args, true);
$suspects = in_array('--include-suspect', $args, true);
$spread   = 0.25;
$company  = '';
foreach ($args as $a) {
    if (strpos($a, '--spread=') === 0) $spread = (float)substr($a, 9);
    elseif ($a[0] !== '-')             $company = $a;
}
if ($company === '') {
    fwrite(STDERR, "usage: repair_supplier_cost_currency.php <company> [--apply] [--include-suspect] [--spread=0.25]\n");
    exit(2);
}

require_once(__DIR__ . '/bootstrap_freebooks.php');
fb_bootstrap($company, false);
global $db, $currencies;
if (!defined('TABLE_INVENTORY_PURCHASE')) require_once(DIR_FS_MODULES . 'inventory/config.php');

$rates = array();
foreach ($currencies->currencies as $code => $c) $rates[$code] = (float)$c['value'];

$rows = $db->Execute("SELECT id, sku, vendor_id, item_cost, currency_code FROM " . TABLE_INVENTORY_PURCHASE
    . " WHERE currency_code <> '' AND currency_code <> '" . db_input(DEFAULT_CURRENCY) . "' AND item_cost > 0"
    . " ORDER BY sku, vendor_id");

echo implode("\t", array('status', 'row_id', 'sku', 'vendor_id', 'currency', 'stored_cost', 'journal',
    'journal_id', 'post_date', 'order_currency', 'order_rate', 'current_rate', 'repaired_cost')) . "\n";
$counts = array('ok' => 0, 'suspect' => 0, 'no_rate' => 0, 'repaired' => 0, 'rows_checked' => 0);
while (!$rows->EOF) {
    $r = $rows->fields;
    $rows->MoveNext();
    $counts['rows_checked']++;
    $cost = (float)$r['item_cost'];
    $tol  = max(1e-6, abs($cost) * 1e-9);
    $line = $db->Execute("SELECT m.id, m.journal_id, m.post_date, m.currencies_code, m.currencies_value,"
        . " i.debit_amount / i.qty AS unit FROM " . TABLE_JOURNAL_ITEM . " i JOIN " . TABLE_JOURNAL_MAIN . " m ON m.id = i.ref_id"
        . " WHERE i.sku = '" . db_input($r['sku']) . "' AND m.bill_acct_id = " . (int)$r['vendor_id']
        . " AND m.journal_id IN (4, 6) AND i.qty <> 0 AND i.debit_amount > 0"
        . " AND ABS(i.debit_amount / i.qty - " . $cost . ") <= " . $tol
        . " ORDER BY m.post_date DESC, m.id DESC LIMIT 1");
    if ($line->EOF) continue;
    $j       = $line->fields;
    $current = $rates[$r['currency_code']] ?? 0;
    if ($current <= 0) {
        $status = 'NO_RATE';
        $new    = null;
        $counts['no_rate']++;
    } elseif ($j['currencies_code'] == $r['currency_code'] && (float)$j['currencies_value'] > 0) {
        $new    = (float)$j['unit'] * (float)$j['currencies_value'];
        $off    = abs((float)$j['currencies_value'] / $current - 1);
        $status = $off > $spread ? 'SUSPECT' : 'OK';
    } else {
        $new    = (float)$j['unit'] * $current;
        $status = 'OK';
    }
    if ($status === 'OK')      $counts['ok']++;
    if ($status === 'SUSPECT') $counts['suspect']++;
    echo implode("\t", array($status, $r['id'], $r['sku'], $r['vendor_id'], $r['currency_code'], $cost,
        'J' . $j['journal_id'], $j['id'], substr($j['post_date'], 0, 10), $j['currencies_code'],
        $j['currencies_value'], $current, $new === null ? '' : round($new, 6))) . "\n";
    if ($apply && $new !== null && ($status === 'OK' || ($status === 'SUSPECT' && $suspects))) {
        $db->Execute("UPDATE " . TABLE_INVENTORY_PURCHASE . " SET item_cost = " . round($new, 6)
            . " WHERE id = " . (int)$r['id'] . " AND ABS(item_cost - " . $cost . ") <= " . $tol);
        $counts['repaired']++;
    }
}
fwrite(STDERR, sprintf("%s: %d foreign-currency rows checked; %d written in %s by a posting (%d ok, %d suspect, %d no rate); %d repaired%s\n",
    $company, $counts['rows_checked'], $counts['ok'] + $counts['suspect'] + $counts['no_rate'], DEFAULT_CURRENCY,
    $counts['ok'], $counts['suspect'], $counts['no_rate'], $counts['repaired'], $apply ? '' : ' (dry run)'));
