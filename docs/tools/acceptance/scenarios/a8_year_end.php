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
//  Path: /docs/tools/acceptance/scenarios/a8_year_end.php
//
/**
 * A8: bank reconciliation and the year end (issue #107), across two fiscal
 * years. Day 1 is the first day of the year before the current one; day 366 is
 * the first day of the current one.
 *
 * THE YEAR END. There is no closing step: every post carries balances forward
 * (gen_ledger::update_chart_history_periods), and at a fiscal year boundary the
 * income, cost of sales, expense and closing equity accounts (types 30, 32, 34,
 * 42 and 44) start the next year at zero, their total opening retained
 * earnings. The equity account here is type 42, so the owner's 1,000 is closed
 * into retained earnings with the year's net income of 155. No period is
 * locked: step 10 posts into the earlier year after the later one has activity,
 * and the later year's opening retained earnings follow it.
 *
 * THE RECONCILIATION is saved through the reconciliation page itself, over
 * HTTP, which needs a throwaway login in FREEBOOKS_ACC_LOGIN (user:password).
 * Saving marks each ticked bank line with the period and closes the entry it
 * belongs to; a closed receipt cannot be deleted (banking::delete_payment).
 * Unticking clears the mark and reopens the entry.
 *
 * Re-posting every journal, as Admin Tools does, must change nothing (step 11).
 *
 * 'at' figures are balances at the end of the period holding that day: day 365
 * is the earlier year's last period, day 370 the later year's first.
 */

$year1_after_6 = array('bank' => 1075, 'ar' => 0, 'ap' => 0, 'inventory' => 80, 'sales' => -300, 'cogs' => 120,
                       'expense' => 25, 'equity' => -1000, 're' => 0);
$year1_after_10 = array('bank' => 1075, 'ar' => 50, 'inventory' => 60, 'sales' => -350, 'cogs' => 140, 'expense' => 25,
                        'equity' => -1000, 're' => 0);
$year2_after_9  = array('bank' => 1075, 'ar' => 100, 'inventory' => 40, 'sales' => -100, 'cogs' => 40, 'expense' => 0,
                        'equity' => 0, 're' => -1155);
$year2_after_10 = array('bank' => 1075, 'ar' => 150, 'inventory' => 20, 'sales' => -100, 'cogs' => 40, 'expense' => 0,
                        'equity' => 0, 're' => -1185);
$reconciled = array('reconciled' => array('G1' => 40, 'D3' => 40, 'R5' => 40, 'G6' => 0),
                    'closed'     => array('G1' => true, 'D3' => true, 'R5' => true, 'G6' => false));

return array(
    'title'    => 'A8 reconciliation and year end: reconcile, refused delete, year-end close, prior-year post, re-post',
    'start'    => 'previous year',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY' => '1',
        'ENABLE_AUTO_ITEM_COST'    => '0',
        'ENABLE_MULTI_BRANCH'      => '0',
    ),
    'payment_methods' => array('cod'),
    'items' => array(
        array('sku' => 'ACC-A8', 'description' => 'Acceptance item A8', 'cost_method' => 'f', 'item_cost' => 20),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'balances' => array(
        'the steps net to zero' => array('bank' => 1, 'ar' => 1, 'ap' => 1, 'inventory' => 1, 'sales' => 1, 'cogs' => 1,
                                         'expense' => 1, 'equity' => 1, 're' => 1),
    ),
    'steps' => array(
        array('seq' => 1,  'day' => 1,  'do' => 'gl', 'label' => 'G1', 'note' => 'bank 1,000 from equity',
              'rows' => array(array('bank', 1000, 0), array('equity', 0, 1000)),
              'expect' => array('at' => array(1 => array('bank' => 1000, 'equity' => -1000)))),
        array('seq' => 2,  'day' => 10, 'do' => 'buy', 'label' => 'P2', 'sku' => 'ACC-A8', 'qty' => 10, 'price' => 20,
              'expect' => array('at' => array(10 => array('inventory' => 200, 'ap' => -200)))),
        array('seq' => 3,  'day' => 20, 'do' => 'pay', 'label' => 'D3', 'bill' => 'P2', 'amount' => 200,
              'expect' => array('at' => array(20 => array('bank' => 800, 'ap' => 0)))),
        array('seq' => 4,  'day' => 30, 'do' => 'sell', 'label' => 'I4', 'sku' => 'ACC-A8', 'qty' => 6, 'price' => 50,
              'expect' => array('at' => array(30 => array('ar' => 300, 'sales' => -300, 'cogs' => 120)))),
        array('seq' => 5,  'day' => 40, 'do' => 'receive', 'label' => 'R5', 'invoice' => 'I4', 'amount' => 300, 'method' => 'cod',
              'expect' => array('at' => array(40 => array('bank' => 1100, 'ar' => 0)))),
        array('seq' => 6,  'day' => 50, 'do' => 'gl', 'label' => 'G6', 'note' => 'bank fee',
              'rows' => array(array('expense', 25, 0), array('bank', 0, 25)),
              'expect' => array('at' => array(365 => $year1_after_6,
                                              366 => array('bank' => 1075, 'inventory' => 80, 'sales' => 0, 'cogs' => 0,
                                                           'expense' => 0, 'equity' => 0, 're' => -1155)))),
        array('seq' => 7,  'day' => 51, 'do' => 'reconcile', 'period_day' => 40, 'tick' => array('G1', 'D3', 'R5'), 'statement' => 1100,
              'note' => 'G6 left unticked',
              'expect' => $reconciled),
        array('seq' => 8,  'day' => 52, 'do' => 'delete_payment', 'target' => 'R5',
              'refused' => array('GENERAL_JOURNAL_18_ERROR_6', ''),
              'expect' => $reconciled + array('at' => array(40 => array('bank' => 1075, 'ar' => 0)))),   // G6 is in the same period
        array('seq' => 9,  'day' => 370, 'do' => 'sell', 'label' => 'I9', 'sku' => 'ACC-A8', 'qty' => 2, 'price' => 50,
              'note' => 'the later year',
              'expect' => array('at' => array(365 => $year1_after_6, 370 => $year2_after_9))),
        array('seq' => 10, 'day' => 200, 'do' => 'sell', 'label' => 'I10', 'sku' => 'ACC-A8', 'qty' => 1, 'price' => 50,
              'note' => 'dated in the earlier year, posted after the later year\'s sale',
              'expect' => array('at' => array(365 => $year1_after_10, 370 => $year2_after_10))),
        array('seq' => 11, 'day' => 371, 'do' => 'repost', 'to_day' => 374,
              'note' => 'changes nothing',
              'expect' => $reconciled + array('at' => array(365 => $year1_after_10, 370 => $year2_after_10))),
        array('seq' => 12, 'day' => 372, 'do' => 'delete', 'target' => 'I10',
              'expect' => array('at' => array(365 => $year1_after_6, 370 => $year2_after_9))),
        array('seq' => 13, 'day' => 373, 'do' => 'reconcile', 'period_day' => 40, 'tick' => array('G1', 'D3'), 'statement' => 800,
              'note' => 'R5 unticked',
              'expect' => array('reconciled' => array('G1' => 40, 'D3' => 40, 'R5' => 0),
                                'closed'     => array('G1' => true, 'D3' => true, 'R5' => false))),
        array('seq' => 14, 'day' => 374, 'do' => 'delete_payment', 'target' => 'R5',
              'expect' => array('at' => array(365 => array('bank' => 775, 'ar' => 300) + $year1_after_6,
                                              370 => array('bank' => 775, 'ar' => 400) + $year2_after_9))),
    ),
);
