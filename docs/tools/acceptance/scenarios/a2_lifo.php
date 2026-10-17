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
//  Path: /docs/tools/acceptance/scenarios/a2_lifo.php
//
/**
 * A2 LIFO (issue #100): the steps in shared/a2_layers.php, newest layer first.
 *
 * Step 7 (D1): S6 took P1's 7 @ 5 and owed 3 at 4; costed again with P7 in
 * stock it takes all 10 from P7, the newest layer, and P1's 7 come back -
 * textbook perpetual LIFO would take the 7 on hand and 3 from P7. Step 8
 * restocks at the newest layer in stock, P1's 5. Step 9: S3 is costed again
 * and takes C8's layer from day 15 first, then P2 at 6.50 and one of P1.
 * Step 11, dated day 2, takes P4's stock from day 7.
 */
require_once __DIR__ . '/shared/a2_layers.php';

return a2_layers_scenario('l', 'A2 LIFO: layers spanned, short stock filled, restock, edit, delete, backdated sale', array(
    1  => array('qty' => 10, 'inventory' => 50,  'cogs' => 0,   'ap' => -50,  'layers' => array('P1' => 10)),
    2  => array('qty' => 20, 'inventory' => 110, 'cogs' => 0,   'ap' => -110, 'layers' => array('P1' => 10, 'P2' => 10)),
    3  => array('qty' => 8,  'inventory' => 40,  'cogs' => 70,  'ap' => -110, 'layers' => array('P1' => 8, 'P2' => 0),
                'doc_cogs' => array('S3' => 70)),
    4  => array('qty' => 13, 'inventory' => 75,  'cogs' => 70,  'ap' => -145, 'layers' => array('P1' => 8, 'P4' => 5)),
    5  => array('qty' => 7,  'inventory' => 35,  'cogs' => 110, 'ap' => -145, 'layers' => array('P1' => 7, 'P4' => 0),
                'doc_cogs' => array('S5' => 40)),
    6  => array('qty' => -3, 'inventory' => -12, 'cogs' => 157, 'ap' => -145, 'layers' => array('P1' => 0, 'P4' => 0),
                'doc_cogs' => array('S6' => 47)),
    7  => array('qty' => 7,  'inventory' => 35,  'cogs' => 190, 'ap' => -225, 'layers' => array('P1' => 7, 'P7' => 0),
                'doc_cogs' => array('S6' => 80)),
    8  => array('qty' => 8,  'inventory' => 40,  'cogs' => 185, 'ap' => -225, 'layers' => array('P1' => 7, 'C8' => 1),
                'doc_cogs' => array('C8' => -5)),
    9  => array('qty' => 8,  'inventory' => 40,  'cogs' => 190, 'ap' => -230,
                'layers' => array('P1' => 8, 'P2' => 0, 'P4' => 0, 'P7' => 0, 'C8' => 0),
                'doc_cogs' => array('S3' => 75, 'S5' => 40, 'S6' => 80, 'C8' => -5)),
    10 => array('qty' => 14, 'inventory' => 80,  'cogs' => 150, 'ap' => -230, 'layers' => array('P1' => 9, 'P4' => 5, 'C8' => 0),
                'doc_cogs' => array('S6' => 80)),
    11 => array('qty' => 11, 'inventory' => 59,  'cogs' => 171, 'ap' => -230, 'layers' => array('P1' => 9, 'P4' => 2),
                'doc_cogs' => array('S11' => 21)),
));
