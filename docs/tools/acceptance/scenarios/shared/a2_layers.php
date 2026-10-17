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
//  Path: /docs/tools/acceptance/scenarios/shared/a2_layers.php
//
/**
 * A2: the steps a2_fifo and a2_lifo share (issue #100). Each scenario names its
 * cost method and its own expected figures for the same eleven steps: receipts
 * at rising prices, sales spanning layers, a sale bigger than stock, the
 * receipt that fills it, a restocking credit memo, a price edit to a receipt
 * sales have consumed, a deleted sale and a backdated sale.
 *
 * Stock short of a sale is costed at the item's cost, 4, queued in
 * inventory_cogs_owed and costed again when a receipt fills it.
 * ENABLE_AUTO_ITEM_COST is off so that the item cost stays 4.
 *
 * An edit is posted on its step's day, and the edited document takes that date.
 * Step 9 keeps P2's own day 3, as the order page does unless the date is
 * changed: dated later, P2 would be costed after the sales it fed.
 *
 * THE COSTING RULES UNDER TEST are the engine's (gen_ledger::calculate_COGS).
 * Three of them differ from textbook perpetual costing:
 *   D1  which layers a sale takes ignores the sale's date: it takes whatever
 *       remains when it is costed, later receipts included. A short sale
 *       costed again takes from the receipt that filled it first under LIFO,
 *       and a backdated sale takes stock received after its date.
 *   D2  deleting a sale returns its own layers and costs no later sale again.
 *   D3  a restocked return is costed at the current cost - the oldest layer in
 *       stock for FIFO, the newest for LIFO (calculateCost) - not at what the
 *       sale booked.
 * Each keeps inventory plus cost of goods equal to what was bought, and the
 * layers' value equal to the inventory account.
 *
 * Every expected figure is given per step: stock on hand, the inventory, cost
 * of goods and payables accounts, the quantity left in each layer, and the cost
 * of goods booked on the documents the step costs or costs again.
 */

function a2_layers_scenario(string $method, string $title, array $expect): array
{
    $sku = $method === 'l' ? 'ACC-A2L' : 'ACC-A2F';
    $steps = array(
        array('seq' => 1,  'day' => 1,  'do' => 'buy',  'label' => 'P1', 'qty' => 10, 'price' => 5),
        array('seq' => 2,  'day' => 3,  'do' => 'buy',  'label' => 'P2', 'qty' => 10, 'price' => 6),
        array('seq' => 3,  'day' => 5,  'do' => 'sell', 'label' => 'S3', 'qty' => 12, 'price' => 20,
              'note' => 'spans two layers'),
        array('seq' => 4,  'day' => 7,  'do' => 'buy',  'label' => 'P4', 'qty' => 5,  'price' => 7),
        array('seq' => 5,  'day' => 9,  'do' => 'sell', 'label' => 'S5', 'qty' => 6,  'price' => 20),
        array('seq' => 6,  'day' => 11, 'do' => 'sell', 'label' => 'S6', 'qty' => 10, 'price' => 20,
              'note' => '3 more than in stock: owed at the item cost'),
        array('seq' => 7,  'day' => 13, 'do' => 'buy',  'label' => 'P7', 'qty' => 10, 'price' => 8,
              'note' => 'fills the owed 3: S6 is costed again'),
        array('seq' => 8,  'day' => 15, 'do' => 'credit', 'label' => 'C8', 'invoice' => 'S6', 'qty' => 1, 'restock' => true,
              'note' => 'back into stock at the current cost (D3)'),
        array('seq' => 9,  'day' => 3,  'do' => 'edit', 'target' => 'P2', 'qty' => 10, 'price' => 6.50,
              'note' => 'P2 keeps its date; the sales that took it are costed again'),
        array('seq' => 10, 'day' => 19, 'do' => 'delete', 'target' => 'S5',
              'note' => 'its layers come back; no later sale is costed again (D2)'),
        array('seq' => 11, 'day' => 2,  'do' => 'sell', 'label' => 'S11', 'qty' => 3, 'price' => 20,
              'note' => 'dated day 2, posted last: takes later stock (D1)'),
    );
    foreach ($steps as &$s) {
        if (in_array($s['do'], array('buy', 'sell'))) $s['sku'] = $sku;
        $s['expect'] = $expect[$s['seq']];
    }
    unset($s);
    return array(
        'title'    => $title,
        'settings' => array(
            'ALLOW_NEGATIVE_INVENTORY' => '1',
            'ENABLE_AUTO_ITEM_COST'    => '0',
            'ENABLE_MULTI_BRANCH'      => '0',
        ),
        'items' => array(
            array('sku' => $sku, 'description' => 'Acceptance item A2 ' . ($method === 'l' ? 'LIFO' : 'FIFO'),
                  'cost_method' => $method, 'item_cost' => 4),
        ),
        'contacts' => array(
            array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
            array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
        ),
        'balances' => array(
            'payables = inventory + cost of goods' => array('ap' => 1, 'inventory' => 1, 'cogs' => 1),
        ),
        'steps' => $steps,
    );
}
