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
//  Path: /docs/tools/acceptance/scenarios/a4_procure_to_pay.php
//
/**
 * A4: one purchase from request for quote to payment (issue #99) - a request
 * converted to a purchase order, received in two parts at two prices, partly
 * returned on a vendor credit memo, paid early with a discount and through Bulk
 * Pay, the credit refunded by the vendor, and a payment deleted and made again.
 *
 * No sales tax and no freight: those are A6.
 *
 * In FreeBooks a receipt of goods and the vendor's bill are one document
 * (journal 6). With ENABLE_AUTO_ITEM_COST at PR the item's cost follows each
 * receipt.
 *
 * THE COSTING RULE UNDER TEST: a vendor credit memo takes stock out by FIFO,
 * whichever bill it is raised against. It credits inventory at the return price
 * and books the difference from the FIFO cost to cost of goods
 * (gen_ledger::calculate_COGS, journal 7). Step 6 returns 2 bought at 8.50 on
 * bill B5 while B4's 8.00 layer is still in stock, so it takes 2 @ 8.00 out of
 * inventory and books a 1.00 gain.
 *
 * The vendor discount account is set to a discount account of the chart's own;
 * the US charts named payables itself until issue #98.
 *
 * Payables is debit positive: -96 is 96 owed to the vendor. Inventory, cost of
 * goods, payables, bank, purchase discounts and equity are the only accounts
 * the steps move, so together they always net to zero.
 */

return array(
    'title'    => 'A4 procure to pay: RFQ, order, part receipts, vendor credit, early payment, bulk pay, refund',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY'     => '1',
        'ENABLE_AUTO_ITEM_COST'        => 'PR',
        'ENABLE_MULTI_BRANCH'          => '0',
        'AP_DISCOUNT_PURCHASE_ACCOUNT' => '6900',
    ),
    'payment_methods' => array('cod'),
    'items' => array(
        array('sku' => 'ACC-A4', 'description' => 'Acceptance item A4', 'cost_method' => 'f', 'item_cost' => 7),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor',
              'terms' => '3:2:10:30:2500'),   // 2% within 10 days, net 30
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'track_open' => array('RFQ2', 'PO3', 'B4', 'B5', 'C6'),
    'balances' => array(
        'the vendor side nets to zero' => array('inventory' => 1, 'cogs' => 1, 'ap' => 1, 'bank' => 1,
                                                'purchase_discount' => 1, 'equity' => 1),
    ),
    'steps' => array(
        array('seq' => 1,  'day' => 1,  'do' => 'gl', 'label' => 'G1', 'note' => 'bank 500 from equity',
              'rows' => array(array('bank', 500, 0), array('equity', 0, 500)),
              'expect' => array('on_order' => 0,  'qty' => 0,  'inventory' => 0,   'ap' => 0,    'bank' => 500,
                                'net_income' => 0, 'vendor_balance' => 0, 'open' => array())),
        array('seq' => 2,  'day' => 2,  'do' => 'rfq', 'label' => 'RFQ2', 'sku' => 'ACC-A4', 'qty' => 20, 'price' => 8,
              'note' => 'a request for quote touches neither stock nor the ledger',
              'expect' => array('on_order' => 0,  'qty' => 0,  'inventory' => 0,   'ap' => 0,    'bank' => 500,
                                'net_income' => 0, 'vendor_balance' => 0, 'open' => array('RFQ2'))),
        array('seq' => 3,  'day' => 3,  'do' => 'convert', 'label' => 'PO3', 'target' => 'RFQ2',
              'note' => 'the request closes; 20 go on order',
              'expect' => array('on_order' => 20, 'qty' => 0,  'inventory' => 0,   'ap' => 0,    'bank' => 500,
                                'net_income' => 0, 'vendor_balance' => 0, 'open' => array('PO3'))),
        array('seq' => 4,  'day' => 5,  'do' => 'receive_po', 'label' => 'B4', 'order' => 'PO3', 'qty' => 12,
              'note' => 'part receipt at the order price',
              'expect' => array('on_order' => 8,  'qty' => 12, 'inventory' => 96,  'ap' => -96,  'bank' => 500,
                                'net_income' => 0, 'vendor_balance' => 96,  'item_cost' => 8,
                                'open' => array('PO3', 'B4'))),
        array('seq' => 5,  'day' => 9,  'do' => 'receive_po', 'label' => 'B5', 'order' => 'PO3', 'qty' => 8, 'price' => 8.50,
              'note' => 'the rest at a changed price: the order closes',
              'expect' => array('on_order' => 0,  'qty' => 20, 'inventory' => 164, 'ap' => -164, 'bank' => 500,
                                'net_income' => 0, 'vendor_balance' => 164, 'item_cost' => 8.50,
                                'open' => array('B4', 'B5'), 'terms' => array('B4', 0.02, 15))),
        array('seq' => 6,  'day' => 10, 'do' => 'vendor_credit', 'label' => 'C6', 'bill' => 'B5', 'qty' => 2,
              'note' => 'returned at 8.50; FIFO takes 2 @ 8.00 out of stock',
              'expect' => array('on_order' => 0,  'qty' => 18, 'inventory' => 148, 'ap' => -147, 'bank' => 500,
                                'net_income' => 1, 'vendor_balance' => 147, 'item_cost' => 8.50,
                                'open' => array('B4', 'B5', 'C6'))),
        array('seq' => 7,  'day' => 12, 'do' => 'pay', 'label' => 'D7', 'bill' => 'B4', 'amount' => 94.08, 'discount' => 1.92,
              'note' => 'inside the 10 days: 2% off 96',
              'expect' => array('on_order' => 0,  'qty' => 18, 'inventory' => 148, 'ap' => -51,  'bank' => 405.92,
                                'net_income' => 2.92, 'vendor_balance' => 51, 'purchase_discount' => -1.92,
                                'open' => array('B5', 'C6'))),
        array('seq' => 8,  'day' => 15, 'do' => 'bulk_pay', 'label' => 'D8', 'bills' => array(array('B5', 68)),
              'note' => 'B5 paid in full; the credit is still owed by the vendor',
              'expect' => array('on_order' => 0,  'qty' => 18, 'inventory' => 148, 'ap' => 17,   'bank' => 337.92,
                                'net_income' => 2.92, 'vendor_balance' => -17, 'open' => array('C6'))),
        array('seq' => 9,  'day' => 20, 'do' => 'vendor_refund', 'label' => 'R9', 'credit' => 'C6', 'amount' => 17, 'method' => 'cod',
              'expect' => array('on_order' => 0,  'qty' => 18, 'inventory' => 148, 'ap' => 0,    'bank' => 354.92,
                                'net_income' => 2.92, 'vendor_balance' => 0, 'open' => array())),
        array('seq' => 10, 'day' => 22, 'do' => 'delete_payment', 'target' => 'D7',
              'note' => 'B4 reopens and the discount is undone',
              'expect' => array('on_order' => 0,  'qty' => 18, 'inventory' => 148, 'ap' => -96,  'bank' => 449,
                                'net_income' => 1, 'vendor_balance' => 96, 'purchase_discount' => 0,
                                'open' => array('B4'))),
        array('seq' => 11, 'day' => 25, 'do' => 'pay', 'label' => 'D11', 'bill' => 'B4', 'amount' => 96,
              'note' => 'past the 10 days: no discount',
              'expect' => array('on_order' => 0,  'qty' => 18, 'inventory' => 148, 'ap' => 0,    'bank' => 353,
                                'net_income' => 1, 'vendor_balance' => 0, 'open' => array())),
    ),
);
