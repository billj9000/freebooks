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
//  Path: /docs/tools/acceptance/invariants.php
//
/**
 * Checks that hold for any company's books, whatever has been posted (issue #94).
 *
 *   docker exec <c> php -d display_errors=1 /opt/repo/docs/tools/acceptance/invariants.php <company>
 *
 * READ-ONLY. It changes nothing, so it may be pointed at any company, a copy of
 * a live one included; it reports and never repairs. run_acceptance.php includes
 * it and runs every check after every step of a scenario.
 *
 *   I1  every posted entry balances: its debits equal its credits
 *   I2  every period's trial balance nets to zero
 *   I3  account history agrees with the journal, period by period, and each
 *       period opens where the one before closed, year-end close included
 *   I4  each tracked item's stored quantity on hand equals its stock layers less
 *       what is owed, and no layer, owed row or usage row is negative or orphaned
 *   I5  an inventory account holds the value of its stock layers, wherever that
 *       is decidable (see acc_check_i5)
 *   I6  each receivables and payables account equals what its open documents
 *       are owed, as the receipts and payments pages work it out (see acc_check_i6)
 *
 * Exits 0 when every check holds, 1 when any does not.
 */

/** The journals whose lines reach chart history - gen_ledger::Post_chart_balances(). */
const ACC_POSTING_JOURNALS = '2, 6, 7, 12, 13, 14, 16, 18, 19, 20, 21';

/** Account types closed into retained earnings at year end - gen_ledger::update_chart_history_periods(). */
const ACC_CLOSING_TYPES = array(30, 32, 34, 42, 44);

/** Quantities are compared to this many places; amounts to the currency's own. */
const ACC_QTY_TOLERANCE = 0.0001;

function acc_rows(string $sql): array
{
    global $db;
    $out = array();
    $r = $db->Execute($sql);
    while (!$r->EOF) { $out[] = $r->fields; $r->MoveNext(); }
    return $out;
}

/** One unit of the default currency's last decimal place - the tolerance coa_hist_test uses. */
function acc_tolerance(): float
{
    global $currencies;
    $places = $currencies->currencies[DEFAULT_CURRENCY]['decimal_places'] ?? 2;
    return 1 / pow(10, (int)$places) + 1e-9;
}

function acc_retained_earnings_account(): string
{
    $r = acc_rows("SELECT id FROM " . TABLE_CHART_OF_ACCOUNTS . " WHERE account_type = 44");
    return count($r) === 1 ? (string)$r[0]['id'] : '';
}

/** I1: every posted entry balances. */
function acc_check_i1(): array
{
    $tol = acc_tolerance();
    $bad = array();
    $rows = acc_rows("SELECT m.id, m.journal_id, m.post_date, SUM(i.debit_amount) AS d, SUM(i.credit_amount) AS c
        FROM " . TABLE_JOURNAL_MAIN . " m JOIN " . TABLE_JOURNAL_ITEM . " i ON i.ref_id = m.id
        WHERE m.journal_id IN (" . ACC_POSTING_JOURNALS . ") GROUP BY m.id, m.journal_id, m.post_date");
    foreach ($rows as $r) {
        if (abs($r['d'] - $r['c']) > $tol) {
            $bad[] = sprintf('entry %d (journal %d, %s): debits %.4f, credits %.4f',
                             $r['id'], $r['journal_id'], substr($r['post_date'], 0, 10), $r['d'], $r['c']);
        }
    }
    return array('I1', 'every posted entry balances', count($rows) . ' entries', $bad);
}

/** I2: every period's trial balance nets to zero, opening balances included. */
function acc_check_i2(): array
{
    $tol = acc_tolerance();
    $bad = array();
    $rows = acc_rows("SELECT period, SUM(beginning_balance) AS b, SUM(debit_amount) AS d, SUM(credit_amount) AS c
        FROM " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " GROUP BY period ORDER BY period");
    foreach ($rows as $r) {
        if (abs($r['b']) > $tol)           $bad[] = sprintf('period %d: opening balances net to %.4f', $r['period'], $r['b']);
        if (abs($r['d'] - $r['c']) > $tol) $bad[] = sprintf('period %d: debits %.4f, credits %.4f', $r['period'], $r['d'], $r['c']);
    }
    return array('I2', 'every period\'s trial balance nets to zero', count($rows) . ' periods', $bad);
}

/**
 * I3: account history agrees with the journal, and each period opens where the
 * one before closed.
 *
 * Retained earnings is left out of the journal comparison only. The ledger
 * writes a trial-balance rounding difference of up to two units of the last
 * decimal place straight into its history with no journal line
 * (gen_ledger::validate_balance), so its history may legitimately differ from
 * its journal lines. I2 still covers it, and so does the roll-forward below.
 */
function acc_check_i3(): array
{
    $tol = acc_tolerance();
    $bad = array();
    $re  = acc_retained_earnings_account();
    if ($re === '') $bad[] = 'the chart does not have exactly one retained earnings account (type 44)';

    $hist = array();
    foreach (acc_rows("SELECT period, account_id, beginning_balance, debit_amount, credit_amount
                       FROM " . TABLE_CHART_OF_ACCOUNTS_HISTORY) as $r) {
        $hist[(string)$r['account_id']][(int)$r['period']] = $r;
    }
    $posted = array();
    foreach (acc_rows("SELECT m.period, i.gl_account, SUM(i.debit_amount) AS d, SUM(i.credit_amount) AS c
        FROM " . TABLE_JOURNAL_MAIN . " m JOIN " . TABLE_JOURNAL_ITEM . " i ON i.ref_id = m.id
        WHERE m.journal_id IN (" . ACC_POSTING_JOURNALS . ") GROUP BY m.period, i.gl_account") as $r) {
        $posted[(string)$r['gl_account']][(int)$r['period']] = $r;
    }

    // journal -> history
    // Account ids are numeric strings, which PHP turns into integer keys.
    foreach ($posted as $acct => $periods) {
        $acct = (string)$acct;
        if ($acct === $re) continue;
        foreach ($periods as $p => $j) {
            if (abs($j['d']) <= $tol && abs($j['c']) <= $tol) continue;
            $h = $hist[$acct][$p] ?? null;
            if ($h === null) { $bad[] = "account $acct period $p: journal lines but no history row"; continue; }
            if (abs($h['debit_amount'] - $j['d']) > $tol || abs($h['credit_amount'] - $j['c']) > $tol) {
                $bad[] = sprintf('account %s period %d: history %.4f dr / %.4f cr, journal %.4f dr / %.4f cr',
                                 $acct, $p, $h['debit_amount'], $h['credit_amount'], $j['d'], $j['c']);
            }
        }
    }
    // history -> journal: activity in history that no journal line accounts for
    foreach ($hist as $acct => $periods) {
        $acct = (string)$acct;
        if ($acct === $re) continue;
        foreach ($periods as $p => $h) {
            if (isset($posted[$acct][$p])) continue;
            if (abs($h['debit_amount']) > $tol || abs($h['credit_amount']) > $tol) {
                $bad[] = sprintf('account %s period %d: history %.4f dr / %.4f cr, no journal lines',
                                 $acct, $p, $h['debit_amount'], $h['credit_amount']);
            }
        }
    }

    // roll-forward
    $fy_of = array(); $fy_end = array();
    foreach (acc_rows("SELECT period, fiscal_year FROM " . TABLE_ACCOUNTING_PERIODS . " ORDER BY period") as $r) {
        $fy_of[(int)$r['period']] = (int)$r['fiscal_year'];
        $fy_end[(int)$r['fiscal_year']] = (int)$r['period'];
    }
    $closing = array(); $in_chart = array();
    foreach (acc_rows("SELECT id, account_type FROM " . TABLE_CHART_OF_ACCOUNTS) as $r) {
        $in_chart[(string)$r['id']] = true;
        if (in_array((int)$r['account_type'], ACC_CLOSING_TYPES)) $closing[(string)$r['id']] = true;
    }
    $closed_total = array(); // year-end period => what the closing accounts net to
    foreach ($hist as $acct => $periods) {
        $acct = (string)$acct;
        if (!isset($closing[$acct])) continue;
        foreach ($periods as $p => $h) {
            $closed_total[$p] = ($closed_total[$p] ?? 0) + $h['beginning_balance'] + $h['debit_amount'] - $h['credit_amount'];
        }
    }
    foreach ($hist as $acct => $periods) {
        $acct = (string)$acct;
        if (!isset($in_chart[$acct])) $bad[] = "account $acct has history but is not in the chart of accounts";
        foreach ($periods as $p => $h) {
            if (!isset($fy_of[$p]) || !isset($fy_of[$p + 1])) continue;
            $close  = $h['beginning_balance'] + $h['debit_amount'] - $h['credit_amount'];
            $year_end = ($fy_end[$fy_of[$p]] === $p);
            if ($year_end && $acct === $re)              $want = $closed_total[$p] ?? 0;
            elseif ($year_end && isset($closing[$acct])) $want = 0;
            else                                         $want = $close;
            if (!isset($periods[$p + 1])) {
                if (abs($want) > $tol) $bad[] = sprintf('account %s closes period %d at %.4f and has no history row for period %d to carry it',
                                                        $acct, $p, $want, $p + 1);
                continue;
            }
            $got = $periods[$p + 1]['beginning_balance'];
            if (abs($got - $want) > $tol) {
                $bad[] = sprintf('account %s period %d opens at %.4f, expected %.4f%s',
                                 $acct, $p + 1, $got, $want, $year_end ? ' (year-end close)' : '');
            }
        }
    }
    return array('I3', 'account history agrees with the journal and rolls forward', count($hist) . ' accounts', $bad);
}

/** I4: stored quantity on hand = stock layers remaining - quantity owed; nothing negative or orphaned. */
function acc_check_i4(): array
{
    $bad = array();
    $types = "'" . implode("','", explode(',', COG_ITEM_TYPES)) . "'";
    $layers = array(); $owed = array();
    foreach (acc_rows("SELECT sku, SUM(remaining) AS r FROM " . TABLE_INVENTORY_HISTORY . " GROUP BY sku") as $r) $layers[$r['sku']] = (float)$r['r'];
    foreach (acc_rows("SELECT sku, SUM(qty) AS q FROM " . TABLE_INVENTORY_COGS_OWED . " GROUP BY sku") as $r)     $owed[$r['sku']]   = (float)$r['q'];
    $items = acc_rows("SELECT sku, quantity_on_hand FROM " . TABLE_INVENTORY . " WHERE inventory_type IN ($types)");
    foreach ($items as $r) {
        $want = ($layers[$r['sku']] ?? 0) - ($owed[$r['sku']] ?? 0);
        if (abs($r['quantity_on_hand'] - $want) > ACC_QTY_TOLERANCE) {
            $bad[] = sprintf('%s: stored on hand %s, layers %s less owed %s = %s', $r['sku'], $r['quantity_on_hand'] + 0,
                             ($layers[$r['sku']] ?? 0) + 0, ($owed[$r['sku']] ?? 0) + 0, $want + 0);
        }
    }
    foreach (acc_rows("SELECT id, sku, qty, remaining FROM " . TABLE_INVENTORY_HISTORY . "
                       WHERE remaining < -" . ACC_QTY_TOLERANCE . " OR (qty > 0 AND remaining > qty + " . ACC_QTY_TOLERANCE . ")") as $r) {
        $bad[] = sprintf('stock layer %d (%s): %s remaining of %s', $r['id'], $r['sku'], $r['remaining'] + 0, $r['qty'] + 0);
    }
    foreach (acc_rows("SELECT o.id, o.sku, o.qty FROM " . TABLE_INVENTORY_COGS_OWED . " o
                       LEFT JOIN " . TABLE_JOURNAL_MAIN . " m ON m.id = o.journal_main_id
                       WHERE m.id IS NULL OR o.qty <= 0") as $r) {
        $bad[] = sprintf('owed row %d (%s, qty %s): its entry is gone or the quantity is not positive', $r['id'], $r['sku'], $r['qty'] + 0);
    }
    foreach (acc_rows("SELECT u.id FROM " . TABLE_INVENTORY_COGS_USAGE . " u
                       LEFT JOIN " . TABLE_INVENTORY_HISTORY . " h ON h.id = u.inventory_history_id
                       LEFT JOIN " . TABLE_JOURNAL_MAIN . " m ON m.id = u.journal_main_id
                       WHERE h.id IS NULL OR m.id IS NULL") as $r) {
        $bad[] = sprintf('cost usage row %d points at a stock layer or entry that is gone', $r['id']);
    }
    return array('I4', 'stored quantity on hand = stock layers - owed', count($items) . ' items', $bad);
}

/**
 * The value of one item's stock layers.
 *
 * FIFO and LIFO layers carry their own cost. An average-cost item's layers do
 * not: a sale draws them down oldest first at the running average, so what is
 * left is valued at the average written on the newest layer still holding stock
 * - the figure gen_ledger::fetch_avg_cost() answers for the whole quantity.
 */
function acc_layer_value(string $sku, string $cost_method): float
{
    $sku = db_input($sku);
    if ($cost_method === 'a') {
        $r = acc_rows("SELECT SUM(remaining) AS r FROM " . TABLE_INVENTORY_HISTORY . " WHERE sku = '$sku' AND remaining > 0");
        $qty = (float)($r[0]['r'] ?? 0);
        if ($qty == 0) return 0.0;
        $last = acc_rows("SELECT avg_cost FROM " . TABLE_INVENTORY_HISTORY . " WHERE sku = '$sku' AND remaining > 0
                          ORDER BY post_date DESC, id DESC LIMIT 1");
        return $qty * (float)$last[0]['avg_cost'];
    }
    $r = acc_rows("SELECT SUM(remaining * unit_cost) AS v FROM " . TABLE_INVENTORY_HISTORY . " WHERE sku = '$sku' AND remaining > 0");
    return (float)($r[0]['v'] ?? 0);
}

/**
 * I5: an inventory account's balance equals the value of the stock layers of
 * the items that use it.
 *
 * Compared only where the answer is decidable, and the accounts passed over are
 * named rather than silently skipped:
 *  - while any item on the account has quantity owed, its cost of goods is an
 *    estimate the ledger will correct when stock arrives, so the balance is
 *    provisional by design;
 *  - a general journal line to the account (an opening balance, a write-off)
 *    moves value without moving stock.
 */
function acc_check_i5(): array
{
    $tol = acc_tolerance();
    $bad = array(); $skipped = array(); $compared = 0;
    $types = "'" . implode("','", explode(',', COG_ITEM_TYPES)) . "'";
    $by_acct = array();
    foreach (acc_rows("SELECT sku, cost_method, account_inventory_wage FROM " . TABLE_INVENTORY . "
                       WHERE inventory_type IN ($types)") as $r) {
        $by_acct[(string)$r['account_inventory_wage']][] = $r;
    }
    $owed = array();
    foreach (acc_rows("SELECT DISTINCT sku FROM " . TABLE_INVENTORY_COGS_OWED) as $r) $owed[$r['sku']] = true;
    foreach ($by_acct as $acct => $items) {
        $acct = (string)$acct;
        if ($acct === '') continue;
        $owing = array_filter($items, function ($i) use ($owed) { return isset($owed[$i['sku']]); });
        if ($owing) { $skipped[] = "$acct (quantity owed)"; continue; }
        $gl = acc_rows("SELECT COUNT(*) AS n FROM " . TABLE_JOURNAL_MAIN . " m JOIN " . TABLE_JOURNAL_ITEM . " i ON i.ref_id = m.id
                        WHERE m.journal_id = 2 AND i.gl_account = '" . db_input($acct) . "'");
        if ((int)$gl[0]['n'] > 0) { $skipped[] = "$acct (general journal lines)"; continue; }
        $value = 0.0;
        foreach ($items as $i) $value += acc_layer_value($i['sku'], (string)$i['cost_method']);
        $balance = acc_account_balance($acct);
        $compared++;
        if (abs($balance - $value) > $tol) {
            $bad[] = sprintf('account %s: balance %.4f, stock layers worth %.4f (%d items)', $acct, $balance, $value, count($items));
        }
    }
    $note = "$compared accounts" . ($skipped ? '; not decidable: ' . implode(', ', $skipped) : '');
    return array('I5', 'inventory accounts hold the value of their stock layers', $note, $bad);
}

/**
 * I6: each receivables and payables account equals the balance of its open
 * documents, worked out the way the receipts and payments pages work it out
 * (fill_paid_invoice_array, phreebooks/functions/phreebooks.php): a document is
 * open while its closed flag is 0, and owes its total - negated for a credit
 * memo - less fetch_partially_paid().
 *
 * Whatever reaches the account other than through a document - a general
 * journal opening balance, a receipt applied to nothing, a point-of-sale entry -
 * is added as it stands and counted in the report, so the comparison is between
 * the ledger and the pages' own reckoning of who owes what. A closed document
 * that is not settled, or an open one whose payments the pages miscount, shows
 * as a difference.
 */
function acc_check_i6(): array
{
    $tol = acc_tolerance();
    $bad = array();
    $docs = acc_rows("SELECT id, journal_id, gl_acct_id, total_amount, closed FROM " . TABLE_JOURNAL_MAIN . "
                      WHERE journal_id IN (6, 7, 12, 13)");
    $expected = array(); $open_count = array(); $doc_ids = array();
    foreach ($docs as $d) {
        $doc_ids[(int)$d['id']] = true;
        $acct = (string)$d['gl_acct_id'];
        $expected[$acct] = $expected[$acct] ?? 0;
        if ($d['closed'] != '0') continue;
        $owed = (in_array((int)$d['journal_id'], array(7, 13)) ? -1 : 1) * $d['total_amount'] - fetch_partially_paid((int)$d['id']);
        // What a customer owes is a debit on receivables; what is owed a vendor, a credit on payables.
        $expected[$acct] += in_array((int)$d['journal_id'], array(12, 13)) ? $owed : -$owed;
        $open_count[$acct] = ($open_count[$acct] ?? 0) + 1;
    }
    $other_count = 0;
    foreach (array_keys($expected) as $acct) {
        $rows = acc_rows("SELECT m.id, m.journal_id, i.gl_type, i.so_po_item_ref_id, i.debit_amount, i.credit_amount
            FROM " . TABLE_JOURNAL_MAIN . " m JOIN " . TABLE_JOURNAL_ITEM . " i ON i.ref_id = m.id
            WHERE i.gl_account = '" . db_input($acct) . "' AND m.journal_id IN (" . ACC_POSTING_JOURNALS . ")");
        foreach ($rows as $r) {
            if (isset($doc_ids[(int)$r['id']])) continue;                                   // the document itself
            if (in_array($r['gl_type'], array('pmt', 'chk')) && isset($doc_ids[(int)$r['so_po_item_ref_id']])) continue; // settling one
            $expected[$acct] += $r['debit_amount'] - $r['credit_amount'];
            $other_count++;
        }
        $balance = acc_account_balance($acct);
        if (abs($balance - $expected[$acct]) > $tol) {
            $bad[] = sprintf('account %s: balance %.4f, open documents and other lines come to %.4f (%d open documents)',
                             $acct, $balance, $expected[$acct], $open_count[$acct] ?? 0);
        }
    }
    $extent = count($expected) . ' accounts, ' . array_sum($open_count) . ' open documents'
            . ($other_count ? ", $other_count lines outside any document" : '');
    return array('I6', 'receivables and payables equal their open documents', $extent, $bad);
}

/** An account's balance as of the last period it has history for. */
function acc_account_balance(string $acct, ?int $period = null): float
{
    $where = "account_id = '" . db_input($acct) . "'" . ($period === null ? '' : " AND period <= " . (int)$period);
    $r = acc_rows("SELECT beginning_balance + debit_amount - credit_amount AS bal FROM " . TABLE_CHART_OF_ACCOUNTS_HISTORY . "
                   WHERE $where ORDER BY period DESC LIMIT 1");
    return (float)($r[0]['bal'] ?? 0);
}

/** @return array<int,array{0:string,1:string,2:string,3:array<int,string>}> id, title, extent, failures */
function acc_invariants(): array
{
    return array(acc_check_i1(), acc_check_i2(), acc_check_i3(), acc_check_i4(), acc_check_i5(), acc_check_i6());
}

/** Prints the results; answers true when every check held. */
function acc_report_invariants(array $results, string $indent = '  ', int $limit = 10): bool
{
    $ok = true;
    foreach ($results as list($id, $title, $extent, $bad)) {
        printf("%s%s %-56s %s (%s)\n", $indent, $id, $title, $bad ? 'FAIL' : 'ok', $extent);
        foreach (array_slice($bad, 0, $limit) as $b) printf("%s    %s\n", $indent, $b);
        if (count($bad) > $limit) printf("%s    ... and %d more\n", $indent, count($bad) - $limit);
        if ($bad) $ok = false;
    }
    return $ok;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $company = $argv[1] ?? '';
    if ($company === '') { fwrite(STDERR, "usage: invariants.php <company>\n"); exit(2); }
    require_once(dirname(__DIR__) . '/bootstrap_freebooks.php');
    fb_bootstrap($company, false);
    require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');   // fetch_partially_paid(), for I6
    exit(acc_report_invariants(acc_invariants()) ? 0 : 1);
}
