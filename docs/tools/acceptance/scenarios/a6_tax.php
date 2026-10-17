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
//  Path: /docs/tools/acceptance/scenarios/a6_tax.php
//
/**
 * A6: sales tax and VAT (issue #104). A US-style rate shared by three authorities (state
 * 6.25%, county 1.5%, city 0.95%), each with its own payable account, a
 * state-only rate charged on freight too, a credit memo that reverses tax by
 * authority, tax worked out after an order discount, and 20% VAT on a purchase
 * and on a sale, both to one VAT account.
 *
 * The ledger works the tax out itself from each line's rate
 * (orders::add_tax_journal_rows): per authority, summed over the document's
 * lines and its freight, then rounded to the currency's places once per
 * authority (issue #105). A line's tax after an order discount is on the
 * discounted amount (AR_TAX_BEFORE_DISCOUNT off). A document's freight is taxed
 * at the rate the document names for it.
 *
 * Tax on a purchase is debited to the authority's account, so the VAT account
 * nets VAT paid against VAT charged.
 *
 * Expected after each step: every account the steps move, debit positive, what
 * the receipts page shows the customer owing, and which documents are open. Those
 * accounts are the only ones moved, so together they net to zero.
 */

return array(
    'title'    => 'A6 tax: multi-authority sales tax, freight tax, credit memo, discount, VAT on purchases and sales',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY'     => '1',
        'ENABLE_AUTO_ITEM_COST'        => '0',
        'ENABLE_MULTI_BRANCH'          => '0',
        'ENABLE_ORDER_DISCOUNT'        => '1',
        'AR_TAX_BEFORE_DISCOUNT'       => '0',
        'AR_ADD_SALES_TAX_TO_SHIPPING' => '0',
    ),
    'payment_methods' => array('cod'),
    'tax_auths' => array(          // rate %, payable account
        'STATE'  => array(6.25, '2316'),
        'COUNTY' => array(1.5,  '2314'),
        'CITY'   => array(0.95, '2312'),
        'VAT'    => array(20,   '2310'),
    ),
    'tax_rates' => array(          // customer or vendor rate, its authorities
        'COMBO'     => array('c', array('STATE', 'COUNTY', 'CITY')),
        'STATEONLY' => array('c', array('STATE')),
        'VAT-S'     => array('c', array('VAT')),
        'VAT-P'     => array('v', array('VAT')),
    ),
    'items' => array(
        array('sku' => 'ACC-A6', 'description' => 'Acceptance item A6', 'cost_method' => 'f', 'item_cost' => 10),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'track_open' => array('I2', 'I3', 'C5', 'I6', 'I8'),
    'balances' => array(
        'the steps net to zero' => array('ar' => 1, 'sales' => 1, 'freight' => 1, 'discount' => 1, 'tax_state' => 1,
            'tax_county' => 1, 'tax_city' => 1, 'tax_vat' => 1, 'inventory' => 1, 'cogs' => 1, 'bank' => 1, 'ap' => 1),
    ),
    'steps' => array(
        array('seq' => 1, 'day' => 1,  'do' => 'buy', 'label' => 'P1', 'sku' => 'ACC-A6', 'qty' => 100, 'price' => 10,
              'expect' => array('inventory' => 1000, 'ap' => -1000, 'ar' => 0, 'open' => array())),
        array('seq' => 2, 'day' => 2,  'do' => 'sell', 'label' => 'I2', 'sku' => 'ACC-A6', 'qty' => 3, 'price' => 25.99, 'tax' => 'COMBO',
              'note' => '77.97: state 4.873125, county 1.16955, city 0.740715',
              'expect' => array('sales' => -77.97, 'tax_state' => -4.87, 'tax_county' => -1.17, 'tax_city' => -0.74,
                                'ar' => 84.75, 'cogs' => 30, 'customer_balance' => 84.75, 'open' => array('I2'))),
        array('seq' => 3, 'day' => 3,  'do' => 'sell', 'label' => 'I3', 'sku' => 'ACC-A6', 'qty' => 2, 'price' => 10, 'tax' => 'STATEONLY',
              'freight' => 7.50, 'freight_tax' => 'STATEONLY',
              'note' => 'state on 20 + 7.50 freight = 1.71875',
              'expect' => array('sales' => -97.97, 'freight' => -7.50, 'tax_state' => -6.59, 'tax_county' => -1.17,
                                'tax_city' => -0.74, 'ar' => 113.97, 'cogs' => 50, 'customer_balance' => 113.97,
                                'open' => array('I2', 'I3'))),
        array('seq' => 4, 'day' => 4,  'do' => 'receive', 'label' => 'R4', 'invoice' => 'I2', 'amount' => 84.75, 'method' => 'cod',
              'expect' => array('ar' => 29.22, 'bank' => 84.75, 'customer_balance' => 29.22, 'open' => array('I3'))),
        array('seq' => 5, 'day' => 5,  'do' => 'credit', 'label' => 'C5', 'invoice' => 'I2', 'qty' => 1, 'restock' => true,
              'note' => 'the invoice line\'s rate: state 1.624375, county 0.38985, city 0.246905',
              'expect' => array('sales' => -71.98, 'tax_state' => -4.97, 'tax_county' => -0.78, 'tax_city' => -0.49,
                                'ar' => 0.97, 'inventory' => 960, 'cogs' => 40, 'customer_balance' => 0.97,
                                'open' => array('I3', 'C5'))),
        array('seq' => 6, 'day' => 6,  'do' => 'sell', 'label' => 'I6', 'sku' => 'ACC-A6', 'qty' => 4, 'price' => 25, 'tax' => 'COMBO',
              'order_discount' => 10,
              'note' => '10 off 100: tax on 90 - state 5.625, county 1.35, city 0.855, each rounding up',
              'expect' => array('sales' => -171.98, 'discount' => 10, 'tax_state' => -10.60, 'tax_county' => -2.13,
                                'tax_city' => -1.35, 'ar' => 98.81, 'cogs' => 80, 'customer_balance' => 98.81,
                                'open' => array('I3', 'C5', 'I6'))),
        array('seq' => 7, 'day' => 7,  'do' => 'buy', 'label' => 'P7', 'sku' => 'ACC-A6', 'qty' => 10, 'price' => 10, 'tax' => 'VAT-P',
              'note' => 'VAT paid is debited to the VAT account',
              'expect' => array('inventory' => 1020, 'tax_vat' => 20, 'ap' => -1120)),
        array('seq' => 8, 'day' => 8,  'do' => 'sell', 'label' => 'I8', 'sku' => 'ACC-A6', 'qty' => 1, 'price' => 12.99, 'tax' => 'VAT-S',
              'note' => '20% of 12.99 = 2.598',
              'expect' => array('sales' => -184.97, 'tax_vat' => 17.40, 'ar' => 114.40, 'inventory' => 1010, 'cogs' => 90,
                                'customer_balance' => 114.40, 'open' => array('I3', 'C5', 'I6', 'I8'))),
        array('seq' => 9, 'day' => 9,  'do' => 'receive', 'label' => 'R9', 'invoice' => 'I6', 'amount' => 97.84, 'method' => 'cod',
              'expect' => array('ar' => 16.56, 'bank' => 182.59, 'tax_state' => -10.60, 'tax_county' => -2.13,
                                'tax_city' => -1.35, 'tax_vat' => 17.40, 'customer_balance' => 16.56,
                                'open' => array('I3', 'C5', 'I8'))),
    ),
);
