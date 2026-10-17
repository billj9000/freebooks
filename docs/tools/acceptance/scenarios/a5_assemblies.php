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
//  Path: /docs/tools/acceptance/scenarios/a5_assemblies.php
//
/**
 * A5: assemblies and product bundles (issue #102). Two parts, an assembly built
 * from them ahead of sale (journal 14) and a product bundle of the same parts,
 * which has no stock of its own and takes its components out when it is sold.
 * All FIFO, on one inventory and one cost of goods account.
 *
 * A build takes its parts out at their FIFO cost and adds one layer for the
 * assembly at that total; the inventory account does not move. A build short
 * of a part is refused even with negative stock allowed
 * (gen_ledger::calculate_assembly_list). An unbuild puts the parts back at the
 * cost the journal posts for them, their current cost (calculateCost), and
 * books the difference from the assembly's own layer to cost of goods.
 *
 * A bundle sale costs each component as a sale of that component
 * (calculate_bundle_cogs): a component short of stock is owed under the
 * component and costed again when a receipt of it arrives. A restocking credit
 * memo on a bundle puts the components back at their current cost, as A2's D3.
 *
 * Step 11 edits a part's receipt price, keeping its date: the build that used it
 * is costed again, which costs the assembly's own sale again, and so is the
 * bundle sale that used it.
 *
 * ENABLE_AUTO_ITEM_COST is off, so a part's item cost stays as created (A 2,
 * B 3) and is what a short component is owed at. A build sets the assembly's
 * item cost to its unit cost.
 */

return array(
    'title'    => 'A5 assemblies and bundles: build, refused build, bundle sale, short component, restock, edit, unbuild',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY' => '1',
        'ENABLE_AUTO_ITEM_COST'    => '0',
        'ENABLE_MULTI_BRANCH'      => '0',
    ),
    'items' => array(
        array('sku' => 'ACC-A5-A', 'description' => 'Acceptance part A', 'cost_method' => 'f', 'item_cost' => 2),
        array('sku' => 'ACC-A5-B', 'description' => 'Acceptance part B', 'cost_method' => 'f', 'item_cost' => 3),
        array('sku' => 'ACC-A5-ASSY', 'description' => 'Acceptance assembly', 'type' => 'ma', 'cost_method' => 'f', 'item_cost' => 0,
              'bom' => array('ACC-A5-A' => 2, 'ACC-A5-B' => 1)),
        array('sku' => 'ACC-A5-BUNDLE', 'description' => 'Acceptance bundle', 'type' => 'pb', 'cost_method' => 'f', 'item_cost' => 0,
              'bom' => array('ACC-A5-A' => 1, 'ACC-A5-B' => 2)),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'balances' => array(
        'payables = inventory + cost of goods' => array('ap' => 1, 'inventory' => 1, 'cogs' => 1),
    ),
    'steps' => array(
        array('seq' => 1,  'day' => 1,  'do' => 'buy', 'label' => 'P1', 'sku' => 'ACC-A5-A', 'qty' => 20, 'price' => 2.50,
              'expect' => array('inventory' => 50,  'cogs' => 0, 'ap' => -50,
                                'stock' => array('ACC-A5-A' => 20), 'layers' => array('P1' => 20))),
        array('seq' => 2,  'day' => 2,  'do' => 'buy', 'label' => 'P2', 'sku' => 'ACC-A5-B', 'qty' => 10, 'price' => 4,
              'expect' => array('inventory' => 90,  'cogs' => 0, 'ap' => -90,
                                'stock' => array('ACC-A5-A' => 20, 'ACC-A5-B' => 10), 'layers' => array('P2' => 10))),
        array('seq' => 3,  'day' => 3,  'do' => 'buy', 'label' => 'P3', 'sku' => 'ACC-A5-A', 'qty' => 10, 'price' => 3,
              'expect' => array('inventory' => 120, 'cogs' => 0, 'ap' => -120,
                                'stock' => array('ACC-A5-A' => 30, 'ACC-A5-B' => 10), 'layers' => array('P1' => 20, 'P3' => 10))),
        array('seq' => 4,  'day' => 5,  'do' => 'build', 'label' => 'M4', 'sku' => 'ACC-A5-ASSY', 'qty' => 6,
              'note' => 'A 12 @ 2.50 + B 6 @ 4 = 54, 9 each',
              'expect' => array('inventory' => 120, 'cogs' => 0, 'ap' => -120,
                                'stock' => array('ACC-A5-A' => 18, 'ACC-A5-B' => 4, 'ACC-A5-ASSY' => 6),
                                'item_costs' => array('ACC-A5-ASSY' => 9),
                                'layers' => array('P1' => 8, 'P3' => 10, 'P2' => 4, 'M4' => 6))),
        array('seq' => 5,  'day' => 6,  'do' => 'build', 'sku' => 'ACC-A5-ASSY', 'qty' => 5,
              'refused' => array('GL_ERROR_NOT_ENOUGH_PARTS', 'ACC-A5-B'),
              'note' => 'needs 5 of B, 4 in stock',
              'expect' => array('inventory' => 120, 'cogs' => 0, 'ap' => -120,
                                'stock' => array('ACC-A5-A' => 18, 'ACC-A5-B' => 4, 'ACC-A5-ASSY' => 6),
                                'layers' => array('P1' => 8, 'P3' => 10, 'P2' => 4, 'M4' => 6))),
        array('seq' => 6,  'day' => 7,  'do' => 'sell', 'label' => 'S6', 'sku' => 'ACC-A5-ASSY', 'qty' => 4, 'price' => 25,
              'expect' => array('inventory' => 84,  'cogs' => 36, 'ap' => -120,
                                'stock' => array('ACC-A5-ASSY' => 2), 'layers' => array('M4' => 2), 'doc_cogs' => array('S6' => 36))),
        array('seq' => 7,  'day' => 8,  'do' => 'sell', 'label' => 'S7', 'sku' => 'ACC-A5-BUNDLE', 'qty' => 2, 'price' => 30,
              'note' => 'A 2 @ 2.50 + B 4 @ 4',
              'expect' => array('inventory' => 63,  'cogs' => 57, 'ap' => -120,
                                'stock' => array('ACC-A5-A' => 16, 'ACC-A5-B' => 0), 'layers' => array('P1' => 6, 'P2' => 0),
                                'doc_cogs' => array('S7' => 21))),
        array('seq' => 8,  'day' => 9,  'do' => 'sell', 'label' => 'S8', 'sku' => 'ACC-A5-BUNDLE', 'qty' => 1, 'price' => 30,
              'note' => 'A 1 @ 2.50; B 2 owed at its item cost 3',
              'expect' => array('inventory' => 54.50, 'cogs' => 65.50, 'ap' => -120,
                                'stock' => array('ACC-A5-A' => 15, 'ACC-A5-B' => -2), 'layers' => array('P1' => 5),
                                'doc_cogs' => array('S8' => 8.50))),
        array('seq' => 9,  'day' => 10, 'do' => 'buy', 'label' => 'P9', 'sku' => 'ACC-A5-B', 'qty' => 10, 'price' => 5,
              'note' => 'fills the owed 2: S8 is costed again',
              'expect' => array('inventory' => 100.50, 'cogs' => 69.50, 'ap' => -170,
                                'stock' => array('ACC-A5-A' => 15, 'ACC-A5-B' => 8), 'layers' => array('P1' => 5, 'P9' => 8),
                                'doc_cogs' => array('S8' => 12.50))),
        array('seq' => 10, 'day' => 12, 'do' => 'credit', 'label' => 'C10', 'invoice' => 'S7', 'qty' => 1, 'restock' => true,
              'note' => 'components back at their current cost: A 2.50, B 5',
              'expect' => array('inventory' => 113, 'cogs' => 57, 'ap' => -170,
                                'stock' => array('ACC-A5-A' => 16, 'ACC-A5-B' => 10),
                                'layers' => array('C10 ACC-A5-A' => 1, 'C10 ACC-A5-B' => 2), 'doc_cogs' => array('C10' => -12.50))),
        array('seq' => 11, 'day' => 2,  'do' => 'edit', 'target' => 'P2', 'qty' => 10, 'price' => 4.50,
              'note' => 'P2 keeps its date; M4, then S6, and S7 are costed again',
              'expect' => array('inventory' => 114, 'cogs' => 61, 'ap' => -175,
                                'stock' => array('ACC-A5-A' => 16, 'ACC-A5-B' => 10, 'ACC-A5-ASSY' => 2),
                                'item_costs' => array('ACC-A5-ASSY' => 9.50),
                                'layers' => array('P1' => 5, 'P2' => 0, 'P3' => 10, 'M4' => 2, 'P9' => 8),
                                'doc_cogs' => array('S6' => 38, 'S7' => 23, 'S8' => 12.50))),
        array('seq' => 12, 'day' => 14, 'do' => 'unbuild', 'label' => 'U12', 'sku' => 'ACC-A5-ASSY', 'qty' => 2,
              'note' => 'parts back at A 2.50, B 5 = 20 against the assembly\'s 19',
              'expect' => array('inventory' => 115, 'cogs' => 60, 'ap' => -175,
                                'stock' => array('ACC-A5-A' => 20, 'ACC-A5-B' => 12, 'ACC-A5-ASSY' => 0),
                                'layers' => array('U12 ACC-A5-A' => 4, 'U12 ACC-A5-B' => 2, 'M4' => 0),
                                'doc_cogs' => array('U12' => -1))),
        array('seq' => 13, 'day' => 15, 'do' => 'delete', 'target' => 'U12',
              'expect' => array('inventory' => 114, 'cogs' => 61, 'ap' => -175,
                                'stock' => array('ACC-A5-A' => 16, 'ACC-A5-B' => 10, 'ACC-A5-ASSY' => 2),
                                'layers' => array('M4' => 2))),
    ),
);
