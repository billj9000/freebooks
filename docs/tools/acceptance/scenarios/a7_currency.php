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
//  Path: /docs/tools/acceptance/scenarios/a7_currency.php
//
/**
 * A7: documents in foreign currencies (issue #106), in a company whose home
 * currency is the one every journal amount is stored in. A vendor bills in EUR
 * at 1.17 and is paid; a customer is invoiced in USD at 1.25, pays in two parts,
 * is credited for a return and refunded; then a USD invoice with 20% VAT at 1.17
 * is paid at the total the page shows.
 *
 * Each document records its currency and rate beside home-currency amounts: the
 * order page divides what is typed by the rate. A receipt, payment or refund
 * settles at the document's own rate, and a credit memo raised from an invoice
 * takes the invoice's, so settling in full closes a document exactly. No
 * exchange gain or loss is posted: a rate that has moved is recorded by hand.
 *
 * A foreign document's home figures are not whole pence - USD 12.99 at 1.17 is
 * 11.102564... - and cannot be without making the foreign figures wrong. Tax is
 * rounded in the home currency (issue #105). Paying the total the page shows,
 * USD 15.59, is 13.3248 at home against the invoice's 13.3226: closing compares
 * rounded figures, so the invoice closes and the difference, under half a penny,
 * stays in receivables (step 9). Figures are checked to 0.0001 so that it shows.
 *
 * Amounts below are in the home currency, debit positive.
 */

return array(
    'title'     => 'A7 currency: EUR bill and payment, USD invoice, part receipts, credit memo, refund, VAT at an awkward rate',
    'tolerance' => 0.0001,
    'settings'  => array(
        'ALLOW_NEGATIVE_INVENTORY' => '1',
        'ENABLE_AUTO_ITEM_COST'    => '0',
        'ENABLE_MULTI_BRANCH'      => '0',
        'ENABLE_ORDER_DISCOUNT'    => '0',
    ),
    'payment_methods' => array('cod'),
    'tax_auths' => array('VAT' => array(20, '2310')),
    'tax_rates' => array('VAT-S' => array('c', array('VAT'))),
    'items' => array(
        array('sku' => 'ACC-A7', 'description' => 'Acceptance item A7', 'cost_method' => 'f', 'item_cost' => 10),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'track_open' => array('P1', 'I3', 'C6', 'I8'),
    'balances' => array(
        'the steps net to zero' => array('ar' => 1, 'sales' => 1, 'tax_vat' => 1, 'inventory' => 1, 'cogs' => 1,
                                         'bank' => 1, 'ap' => 1),
    ),
    'steps' => array(
        array('seq' => 1, 'day' => 1, 'do' => 'buy', 'label' => 'P1', 'sku' => 'ACC-A7', 'qty' => 10, 'price' => 11.70,
              'currency' => array('EUR', 1.17), 'note' => 'EUR 117',
              'expect' => array('inventory' => 100, 'ap' => -100, 'vendor_balance' => 100, 'open' => array('P1'))),
        array('seq' => 2, 'day' => 2, 'do' => 'pay', 'label' => 'D2', 'bill' => 'P1', 'amount' => 117,
              'note' => 'EUR 117 at the bill\'s rate',
              'expect' => array('ap' => 0, 'bank' => -100, 'vendor_balance' => 0, 'open' => array())),
        array('seq' => 3, 'day' => 3, 'do' => 'sell', 'label' => 'I3', 'sku' => 'ACC-A7', 'qty' => 4, 'price' => 25,
              'currency' => array('USD', 1.25), 'note' => 'USD 100',
              'expect' => array('sales' => -80, 'ar' => 80, 'inventory' => 60, 'cogs' => 40, 'customer_balance' => 80,
                                'open' => array('I3'))),
        array('seq' => 4, 'day' => 4, 'do' => 'receive', 'label' => 'R4', 'invoice' => 'I3', 'amount' => 50, 'method' => 'cod',
              'note' => 'USD 50',
              'expect' => array('ar' => 40, 'bank' => -60, 'customer_balance' => 40, 'open' => array('I3'))),
        array('seq' => 5, 'day' => 5, 'do' => 'receive', 'label' => 'R5', 'invoice' => 'I3', 'amount' => 50, 'method' => 'cod',
              'note' => 'USD 50: closes exactly',
              'expect' => array('ar' => 0, 'bank' => -20, 'customer_balance' => 0, 'open' => array())),
        array('seq' => 6, 'day' => 6, 'do' => 'credit', 'label' => 'C6', 'invoice' => 'I3', 'qty' => 1, 'restock' => true,
              'note' => 'USD 25 at the invoice\'s rate',
              'expect' => array('sales' => -60, 'ar' => -20, 'inventory' => 70, 'cogs' => 30, 'customer_balance' => -20,
                                'open' => array('C6'))),
        array('seq' => 7, 'day' => 7, 'do' => 'refund', 'label' => 'F7', 'credit' => 'C6', 'amount' => 25, 'method' => 'cod',
              'note' => 'USD 25',
              'expect' => array('ar' => 0, 'bank' => -40, 'customer_balance' => 0, 'open' => array())),
        array('seq' => 8, 'day' => 8, 'do' => 'sell', 'label' => 'I8', 'sku' => 'ACC-A7', 'qty' => 1, 'price' => 12.99, 'tax' => 'VAT-S',
              'currency' => array('USD', 1.17), 'note' => 'USD 12.99 = 11.1026; VAT 2.2205 rounds to 2.22; shown as USD 15.59',
              'expect' => array('sales' => -71.1026, 'tax_vat' => -2.22, 'ar' => 13.3226, 'inventory' => 60, 'cogs' => 40,
                                'customer_balance' => 13.3226, 'open' => array('I8'))),
        array('seq' => 9, 'day' => 9, 'do' => 'receive', 'label' => 'R9', 'invoice' => 'I8', 'amount' => 15.59, 'method' => 'cod',
              'note' => 'USD 15.59 = 13.3248: closes, under half a penny left',
              'expect' => array('ar' => -0.0022, 'bank' => -26.6752, 'tax_vat' => -2.22, 'customer_balance' => 0,
                                'open' => array())),
    ),
);
