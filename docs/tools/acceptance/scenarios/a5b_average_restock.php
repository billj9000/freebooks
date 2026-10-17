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
//  Path: /docs/tools/acceptance/scenarios/a5b_average_restock.php
//
/**
 * A5b: average-cost stock coming back other than by purchase (issue #112). Every
 * cost layer of an average-cost item records the item's average after it, and
 * a sale is costed at the average on the newest layer in stock
 * (gen_ledger::fetch_avg_cost). So each entry that adds stock must record the
 * average too, or the next sale is costed from a wrong one:
 *   step 4   a credit memo restocks part A at its average, 5 - the average stays 5
 *   step 7   a build adds the assembly at its parts' cost, 8 each
 *   step 9   an unbuild returns parts A and B at their averages, 5 and 3
 *   step 11  an adjustment adds 5 of A at 7: (10 x 5 + 5 x 7) / 15 = 5.6667
 * and the sale after each (steps 5, 8, 10, 12) is costed at that average.
 *
 * All items are average-cost; ENABLE_AUTO_ITEM_COST is off. Sales are on
 * account, so receivables, sales, payables, inventory, cost of goods and the
 * adjustment account are the only accounts moved, and net to zero.
 */

return array(
    'title'    => 'A5b average cost restocked: credit memo, build, unbuild, adjustment, each followed by a sale',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY' => '1',
        'ENABLE_AUTO_ITEM_COST'    => '0',
        'ENABLE_MULTI_BRANCH'      => '0',
    ),
    'roles' => array('adjust' => '5900'),
    'items' => array(
        array('sku' => 'ACC-A5B-A', 'description' => 'Acceptance average part A', 'cost_method' => 'a', 'item_cost' => 2),
        array('sku' => 'ACC-A5B-B', 'description' => 'Acceptance average part B', 'cost_method' => 'a', 'item_cost' => 3),
        array('sku' => 'ACC-A5B-ASSY', 'description' => 'Acceptance average assembly', 'type' => 'ma', 'cost_method' => 'a', 'item_cost' => 0,
              'bom' => array('ACC-A5B-A' => 1, 'ACC-A5B-B' => 1)),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'balances' => array(
        'the steps net to zero' => array('ar' => 1, 'sales' => 1, 'ap' => 1, 'inventory' => 1, 'cogs' => 1, 'adjust' => 1),
    ),
    'steps' => array(
        array('seq' => 1,  'day' => 1,  'do' => 'buy',  'label' => 'P1', 'sku' => 'ACC-A5B-A', 'qty' => 10, 'price' => 4,
              'expect' => array('inventory' => 40,  'cogs' => 0,  'ap' => -40)),
        array('seq' => 2,  'day' => 2,  'do' => 'buy',  'label' => 'P2', 'sku' => 'ACC-A5B-A', 'qty' => 10, 'price' => 6,
              'note' => 'average 5',
              'expect' => array('inventory' => 100, 'cogs' => 0,  'ap' => -100)),
        array('seq' => 3,  'day' => 3,  'do' => 'sell', 'label' => 'S3', 'sku' => 'ACC-A5B-A', 'qty' => 4, 'price' => 10,
              'expect' => array('inventory' => 80,  'cogs' => 20, 'doc_cogs' => array('S3' => 20))),
        array('seq' => 4,  'day' => 4,  'do' => 'credit', 'label' => 'C4', 'invoice' => 'S3', 'qty' => 1, 'restock' => true,
              'note' => 'back at the average, 5',
              'expect' => array('inventory' => 85,  'cogs' => 15, 'doc_cogs' => array('C4' => -5))),
        array('seq' => 5,  'day' => 5,  'do' => 'sell', 'label' => 'S5', 'sku' => 'ACC-A5B-A', 'qty' => 2, 'price' => 10,
              'note' => 'costed at the average C4 recorded',
              'expect' => array('inventory' => 75,  'cogs' => 25, 'doc_cogs' => array('S5' => 10))),
        array('seq' => 6,  'day' => 6,  'do' => 'buy',  'label' => 'P6', 'sku' => 'ACC-A5B-B', 'qty' => 10, 'price' => 3,
              'expect' => array('inventory' => 105, 'cogs' => 25, 'ap' => -130)),
        array('seq' => 7,  'day' => 7,  'do' => 'build', 'label' => 'M7', 'sku' => 'ACC-A5B-ASSY', 'qty' => 4,
              'note' => 'A 4 @ 5 + B 4 @ 3 = 32, 8 each',
              'expect' => array('inventory' => 105, 'cogs' => 25,
                                'stock' => array('ACC-A5B-A' => 11, 'ACC-A5B-B' => 6, 'ACC-A5B-ASSY' => 4))),
        array('seq' => 8,  'day' => 8,  'do' => 'sell', 'label' => 'S8', 'sku' => 'ACC-A5B-ASSY', 'qty' => 1, 'price' => 20,
              'note' => 'costed at the average M7 recorded',
              'expect' => array('inventory' => 97,  'cogs' => 33, 'doc_cogs' => array('S8' => 8))),
        array('seq' => 9,  'day' => 9,  'do' => 'unbuild', 'label' => 'U9', 'sku' => 'ACC-A5B-ASSY', 'qty' => 2,
              'note' => 'parts back at A 5 and B 3, the assembly\'s 8 each',
              'expect' => array('inventory' => 97,  'cogs' => 33, 'doc_cogs' => array('U9' => 0),
                                'stock' => array('ACC-A5B-A' => 13, 'ACC-A5B-B' => 8, 'ACC-A5B-ASSY' => 1))),
        array('seq' => 10, 'day' => 10, 'do' => 'sell', 'label' => 'S10', 'sku' => 'ACC-A5B-A', 'qty' => 3, 'price' => 10,
              'note' => 'costed at the average U9 recorded',
              'expect' => array('inventory' => 82,  'cogs' => 48, 'doc_cogs' => array('S10' => 15))),
        array('seq' => 11, 'day' => 11, 'do' => 'adjust', 'label' => 'J11', 'sku' => 'ACC-A5B-A', 'qty' => 5, 'price' => 7,
              'note' => 'average (10 x 5 + 5 x 7) / 15 = 5.6667',
              'expect' => array('inventory' => 117, 'cogs' => 48, 'adjust' => -35)),
        array('seq' => 12, 'day' => 12, 'do' => 'sell', 'label' => 'S12', 'sku' => 'ACC-A5B-A', 'qty' => 3, 'price' => 10,
              'note' => 'costed at the average J11 recorded',
              'expect' => array('inventory' => 100, 'cogs' => 65, 'doc_cogs' => array('S12' => 17),
                                'ar' => 130, 'sales' => -130, 'ap' => -130)),
    ),
);
