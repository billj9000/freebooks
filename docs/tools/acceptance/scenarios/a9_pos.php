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
//  Path: /docs/tools/acceptance/scenarios/a9_pos.php
//
/**
 * A9: the point of sale (issue #109). POS sales posted as the POS page saves
 * them (journal_19::post_ordr): change given, a split tender, and cash rounding;
 * then a sale deleted as the POS manager does it, and a sale with an order
 * discount, taxed after the discount (issue #121).
 *
 * A sale's lines credit sales and the ledger's own VAT (rounded per authority,
 * issue #105); a cash rounding line goes to the till's rounding account. Each
 * payment debits its method's POS account, and tendering more than the total
 * gives change: the payment posted is the total, not the amount handed over.
 * The total and the payments meet on the till's own account, which nets to
 * zero. Cost of goods is worked out as for an invoice.
 *
 * Every POS entry carries balances forward to later periods and the next fiscal
 * year (issue #108). Plain figures below are balances in the company's latest
 * period, which only include a sale that was carried forward; 'at' figures are
 * the sale's own period.
 */

return array(
    'title'    => 'A9 point of sale: change, split tender, cash rounding, delete',
    'settings' => array(
        'ALLOW_NEGATIVE_INVENTORY'              => '1',
        'ENABLE_AUTO_ITEM_COST'                 => '0',
        'ENABLE_MULTI_BRANCH'                   => '0',
        'ENABLE_ORDER_DISCOUNT'                 => '1',
        'AR_TAX_BEFORE_DISCOUNT'                => '0',
        'MODULE_PAYMENT_COD_POS_GL_ACCT'        => '1025',
        'MODULE_PAYMENT_MONEYORDER_POS_GL_ACCT' => '1234',
    ),
    'payment_methods' => array('cod', 'moneyorder'),
    'roles' => array('till' => '1000', 'rounding' => '4100', 'pos_cod' => '1025', 'pos_mo' => '1234'),
    'tax_auths' => array('VAT' => array(20, '2310')),
    'tax_rates' => array('VAT-S' => array('c', array('VAT'))),
    'items' => array(
        array('sku' => 'ACC-A9', 'description' => 'Acceptance item A9', 'cost_method' => 'f', 'item_cost' => 5),
    ),
    'contacts' => array(
        array('type' => 'v', 'short_name' => 'ACC-VENDOR',   'name' => 'Acceptance Vendor'),
        array('type' => 'c', 'short_name' => 'ACC-CUSTOMER', 'name' => 'Acceptance Customer'),
    ),
    'balances' => array(
        'the steps net to zero' => array('till' => 1, 'pos_cod' => 1, 'pos_mo' => 1, 'rounding' => 1, 'sales' => 1,
                                         'discount' => 1, 'tax_vat' => 1, 'inventory' => 1, 'cogs' => 1, 'ap' => 1),
    ),
    'steps' => array(
        array('seq' => 1, 'day' => 1, 'do' => 'buy', 'label' => 'P1', 'sku' => 'ACC-A9', 'qty' => 20, 'price' => 5,
              'expect' => array('inventory' => 100, 'ap' => -100)),
        array('seq' => 2, 'day' => 2, 'do' => 'pos_sale', 'label' => 'S2', 'sku' => 'ACC-A9',
              'lines' => array(array(2, 12, 'VAT-S')), 'total' => 28.80, 'payments' => array(array('cod', 30)),
              'note' => '24 + VAT 4.80; 30 tendered, 1.20 change',
              'expect' => array('sales' => -24, 'tax_vat' => -4.80, 'pos_cod' => 28.80, 'till' => 0, 'cogs' => 10, 'inventory' => 90,
                                'at' => array(2 => array('sales' => -24, 'pos_cod' => 28.80, 'till' => 0)))),
        array('seq' => 3, 'day' => 3, 'do' => 'pos_sale', 'label' => 'S3', 'sku' => 'ACC-A9',
              'lines' => array(array(1, 12, 'VAT-S')), 'total' => 14.40, 'payments' => array(array('cod', 10), array('moneyorder', 4.40)),
              'note' => 'split tender',
              'expect' => array('sales' => -36, 'tax_vat' => -7.20, 'pos_cod' => 38.80, 'pos_mo' => 4.40, 'till' => 0,
                                'cogs' => 15, 'inventory' => 85)),
        array('seq' => 4, 'day' => 4, 'do' => 'pos_sale', 'label' => 'S4', 'sku' => 'ACC-A9',
              'lines' => array(array(1, 9.99, 'VAT-S')), 'total' => 12.00, 'rounding' => 0.01, 'payments' => array(array('cod', 12)),
              'note' => '9.99 + VAT 2.00 (1.998) = 11.99, rounded to 12.00',
              'expect' => array('sales' => -45.99, 'tax_vat' => -9.20, 'rounding' => -0.01, 'pos_cod' => 50.80, 'pos_mo' => 4.40,
                                'till' => 0, 'cogs' => 20, 'inventory' => 80,
                                'at' => array(4 => array('sales' => -45.99, 'rounding' => -0.01, 'pos_cod' => 50.80)))),
        array('seq' => 5, 'day' => 5, 'do' => 'pos_delete', 'target' => 'S3',
              'expect' => array('sales' => -33.99, 'tax_vat' => -6.80, 'rounding' => -0.01, 'pos_cod' => 40.80, 'pos_mo' => 0,
                                'till' => 0, 'cogs' => 15, 'inventory' => 85)),
        array('seq' => 6, 'day' => 6, 'do' => 'pos_sale', 'label' => 'S6', 'sku' => 'ACC-A9',
              'lines' => array(array(1, 100, 'VAT-S')), 'discount' => 10, 'total' => 108.00, 'payments' => array(array('cod', 108)),
              'note' => '10 off 100, VAT on the 90 left: 18.00, so 108.00 and no rounding line',
              'expect' => array('sales' => -133.99, 'discount' => 10, 'tax_vat' => -24.80, 'rounding' => -0.01, 'pos_cod' => 148.80,
                                'pos_mo' => 0, 'till' => 0, 'cogs' => 20, 'inventory' => 80)),
    ),
);
