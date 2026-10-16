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
//  Path: /docs/tools/acceptance/scenarios/a1_average_cost.php
//
/**
 * A1: one average-cost item through negative stock, a backdated purchase, an
 * edited purchase, a deleted purchase and out-of-order posting (issue #94).
 *
 * The sequence and its expected figures are the "Test J04" sheet of
 * TestProcedures/PB-TEST-J6-J12.ods, re-derived exactly. The sheet rounds the
 * running average to two places, so its step 6 reads 481.21 where the exact
 * figure is 481.25; every figure here is written as the exact fraction.
 *
 * THE COSTING RULE UNDER TEST is the one PhreeBooks implements. A sale larger
 * than the stock on hand is not split: the whole line is queued as cost of goods
 * owed, at an estimate, and is costed when a receipt fills it, at the average
 * after that receipt - even when the receipt is dated later than the sale.
 * Whether that rule is right is a separate question from whether the engine
 * keeps it.
 *
 * Expected after each step, cumulatively: the item's quantity on hand, the
 * inventory account's balance, and net income (income less cost of goods). The
 * payables account must also equal inventory plus cost of goods - every purchase
 * is on account and nothing is paid, so nothing else can hold the money.
 *
 * Day N is day N of the first period of the company's current fiscal year.
 * Accounts are named by role and resolved from the company's own settings.
 */

return array(
    'title'    => 'A1 average cost: negative stock, edit, delete, out-of-order posting',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY' => '1',
        'ENABLE_AUTO_ITEM_COST'    => 'PR',   // the sheet's "unit cost update to Purchase"
        'ENABLE_MULTI_BRANCH'      => '0',
    ),
    'items' => array(
        array('sku' => 'ACC-A1', 'description' => 'Acceptance item A1', 'cost_method' => 'a', 'item_cost' => 7),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'balances' => array(
        'payables = inventory + cost of goods' => array('ap' => 1, 'inventory' => 1, 'cogs' => 1),
    ),
    'steps' => array(
        array('seq' => 1,  'day' => 1,  'do' => 'gl', 'label' => 'G1', 'note' => 'cash 1,000 from equity',
              'rows' => array(array('cash', 1000, 0), array('equity', 0, 1000)),
              'expect' => array('qty' => 0,   'inventory' => 0,          'net_income' => 0, 'cash' => 1000)),
        array('seq' => 2,  'day' => 5,  'do' => 'buy',  'label' => 'P2',  'sku' => 'ACC-A1', 'qty' => 10, 'price' => 10,
              'expect' => array('qty' => 10,  'inventory' => 100,        'net_income' => 0)),
        array('seq' => 3,  'day' => 8,  'do' => 'buy',  'label' => 'P3',  'sku' => 'ACC-A1', 'qty' => 20, 'price' => 20,
              'expect' => array('qty' => 30,  'inventory' => 500,        'net_income' => 0)),
        array('seq' => 4,  'day' => 10, 'do' => 'sell', 'label' => 'S4',  'sku' => 'ACC-A1', 'qty' => 15, 'price' => 40,
              'expect' => array('qty' => 15,  'inventory' => 250,        'net_income' => 350)),
        array('seq' => 5,  'day' => 13, 'do' => 'sell', 'label' => 'S5',  'sku' => 'ACC-A1', 'qty' => 30, 'price' => 20,
              'note' => 'more than is in stock: the whole line is owed',
              'expect' => array('qty' => -15, 'inventory' => -250,       'net_income' => 450)),
        array('seq' => 6,  'day' => 20, 'do' => 'buy',  'label' => 'P6',  'sku' => 'ACC-A1', 'qty' => 25, 'price' => 15,
              'note' => 'fills what step 5 owes',
              'expect' => array('qty' => 10,  'inventory' => 625 / 4,    'net_income' => 1925 / 4)),
        array('seq' => 7,  'day' => 22, 'do' => 'edit', 'target' => 'P3', 'qty' => 30, 'price' => 25,
              'note' => 'step 3 becomes 30 @ 25 and moves to day 22',
              'expect' => array('qty' => 20,  'inventory' => 2860 / 7,   'net_income' => 2685 / 7)),
        array('seq' => 8,  'day' => 25, 'do' => 'sell', 'label' => 'S8',  'sku' => 'ACC-A1', 'qty' => 10, 'price' => 35,
              'expect' => array('qty' => 10,  'inventory' => 1430 / 7,   'net_income' => 3705 / 7)),
        array('seq' => 9,  'day' => 28, 'do' => 'delete', 'target' => 'P3',
              'note' => 'the purchase made in step 3 and edited in step 7',
              'expect' => array('qty' => -20, 'inventory' => -1900 / 7,  'net_income' => 5625 / 7)),
        array('seq' => 10, 'day' => 17, 'do' => 'buy',  'label' => 'P10', 'sku' => 'ACC-A1', 'qty' => 50, 'price' => 5,
              'note' => 'backdated before step 6',
              'expect' => array('qty' => 30,  'inventory' => 2775 / 8,   'net_income' => 9375 / 8)),
        array('seq' => 11, 'day' => 18, 'do' => 'sell', 'label' => 'S11', 'sku' => 'ACC-A1', 'qty' => 20, 'price' => 33,
              'note' => 'backdated before step 6',
              'expect' => array('qty' => 10,  'inventory' => 925 / 8,    'net_income' => 12805 / 8)),
    ),
);
