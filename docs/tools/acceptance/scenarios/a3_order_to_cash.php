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
//  Path: /docs/tools/acceptance/scenarios/a3_order_to_cash.php
//
/**
 * A3: one sale from quote to cash (issue #96) - a quote converted to a sales
 * order, shipped in two parts, paid early with a discount, partly returned on a
 * credit memo, the credit refunded, and a receipt deleted and taken again.
 *
 * No sales tax and no freight: those are A6, so that a fault in one cannot hide
 * a fault in the other.
 *
 * The item is FIFO with a single cost layer. A restocking credit memo credits
 * cost of goods at the item's CURRENT cost (gen_ledger::calculate_COGS, journal
 * 13), not at what the returned goods cost when they were sold; with one layer
 * the two are the same 6.00, so this scenario does not depend on that rule.
 *
 * A credit memo does not reduce its invoice: it is an open document of its own,
 * owing the customer, until a refund or a receipt settles it. So receivables go
 * to -30 at step 8, when invoice 2 is paid in full with the credit still open.
 *
 * Expected after each step: stock on hand and on sales order, the inventory,
 * receivables and bank accounts, net income (sales less discounts and cost of
 * goods), what the receipts page shows the customer owing, and which documents
 * are open. Two identities must hold throughout: inventory plus cost of goods is
 * what was bought, and bank plus receivables plus discounts given is what was
 * sold net of returns.
 */

return array(
    'title'    => 'A3 order to cash: quote, order, part shipments, early payment, credit memo, refund',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY' => '1',
        'ENABLE_MULTI_BRANCH'      => '0',
    ),
    'payment_methods' => array('cod'),
    'items' => array(
        array('sku' => 'ACC-A3', 'description' => 'Acceptance item A3', 'cost_method' => 'f', 'item_cost' => 6),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer',
              'terms' => '3:2:10:30:2500'),   // 2% within 10 days, net 30
    ),
    'track_open' => array('Q2', 'SO3', 'I4', 'I5', 'C7'),
    'balances' => array(
        'payables = inventory + cost of goods'           => array('ap' => 1, 'inventory' => 1, 'cogs' => 1),
        'bank + receivables + discounts = net sales'     => array('bank' => 1, 'ar' => 1, 'discount' => 1, 'sales' => 1),
    ),
    'steps' => array(
        array('seq' => 1,  'day' => 1,  'do' => 'buy', 'label' => 'P1', 'sku' => 'ACC-A3', 'qty' => 20, 'price' => 6,
              'expect' => array('qty' => 20, 'on_so' => 0,  'inventory' => 120, 'ar' => 0,   'bank' => 0,
                                'net_income' => 0,     'customer_balance' => 0,   'open' => array())),
        array('seq' => 2,  'day' => 2,  'do' => 'quote', 'label' => 'Q2', 'sku' => 'ACC-A3', 'qty' => 10, 'price' => 15,
              'note' => 'a quote touches neither stock nor the ledger',
              'expect' => array('qty' => 20, 'on_so' => 0,  'inventory' => 120, 'ar' => 0,   'bank' => 0,
                                'net_income' => 0,     'customer_balance' => 0,   'open' => array('Q2'))),
        array('seq' => 3,  'day' => 3,  'do' => 'convert', 'label' => 'SO3', 'target' => 'Q2',
              'note' => 'the quote closes; 10 go on sales order',
              'expect' => array('qty' => 20, 'on_so' => 10, 'inventory' => 120, 'ar' => 0,   'bank' => 0,
                                'net_income' => 0,     'customer_balance' => 0,   'open' => array('SO3'))),
        array('seq' => 4,  'day' => 5,  'do' => 'ship', 'label' => 'I4', 'order' => 'SO3', 'qty' => 6,
              'note' => 'part shipment: the order stays open for 4',
              'expect' => array('qty' => 14, 'on_so' => 4,  'inventory' => 84,  'ar' => 90,  'bank' => 0,
                                'net_income' => 54,    'customer_balance' => 90,  'open' => array('SO3', 'I4'))),
        array('seq' => 5,  'day' => 8,  'do' => 'ship', 'label' => 'I5', 'order' => 'SO3', 'qty' => 4,
              'note' => 'the rest: the order closes',
              'expect' => array('qty' => 10, 'on_so' => 0,  'inventory' => 60,  'ar' => 150, 'bank' => 0,
                                'net_income' => 90,    'customer_balance' => 150, 'open' => array('I4', 'I5'),
                                'terms' => array('I4', 0.02, 15))),
        array('seq' => 6,  'day' => 12, 'do' => 'receive', 'label' => 'R6', 'invoice' => 'I4', 'amount' => 88.20, 'discount' => 1.80,
              'method' => 'cod', 'note' => 'inside the 10 days: 2% off 90',
              'expect' => array('qty' => 10, 'on_so' => 0,  'inventory' => 60,  'ar' => 60,  'bank' => 88.20,
                                'net_income' => 88.20, 'customer_balance' => 60,  'open' => array('I5'))),
        array('seq' => 7,  'day' => 15, 'do' => 'credit', 'label' => 'C7', 'invoice' => 'I5', 'qty' => 2, 'restock' => true,
              'note' => '2 returned at 15, back into stock at 6',
              'expect' => array('qty' => 12, 'on_so' => 0,  'inventory' => 72,  'ar' => 30,  'bank' => 88.20,
                                'net_income' => 70.20, 'customer_balance' => 30,  'open' => array('I5', 'C7'))),
        array('seq' => 8,  'day' => 20, 'do' => 'receive', 'label' => 'R8', 'invoice' => 'I5', 'amount' => 60, 'method' => 'cod',
              'note' => 'invoice 2 paid in full; the credit is still owed to the customer',
              'expect' => array('qty' => 12, 'on_so' => 0,  'inventory' => 72,  'ar' => -30, 'bank' => 148.20,
                                'net_income' => 70.20, 'customer_balance' => -30, 'open' => array('C7'))),
        array('seq' => 9,  'day' => 22, 'do' => 'refund', 'label' => 'F9', 'credit' => 'C7', 'amount' => 30, 'method' => 'cod',
              'expect' => array('qty' => 12, 'on_so' => 0,  'inventory' => 72,  'ar' => 0,   'bank' => 118.20,
                                'net_income' => 70.20, 'customer_balance' => 0,   'open' => array())),
        array('seq' => 10, 'day' => 23, 'do' => 'delete_payment', 'target' => 'R6',
              'note' => 'invoice 1 reopens and the discount is undone',
              'expect' => array('qty' => 12, 'on_so' => 0,  'inventory' => 72,  'ar' => 90,  'bank' => 30,
                                'net_income' => 72,    'customer_balance' => 90,  'open' => array('I4'))),
        array('seq' => 11, 'day' => 25, 'do' => 'receive', 'label' => 'R11', 'invoice' => 'I4', 'amount' => 90, 'method' => 'cod',
              'note' => 'past the 10 days: no discount',
              'expect' => array('qty' => 12, 'on_so' => 0,  'inventory' => 72,  'ar' => 0,   'bank' => 120,
                                'net_income' => 72,    'customer_balance' => 0,   'open' => array())),
    ),
);
