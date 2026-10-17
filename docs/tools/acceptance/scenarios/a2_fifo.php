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
//  Path: /docs/tools/acceptance/scenarios/a2_fifo.php
//
/**
 * A2 FIFO (issue #100): the steps in shared/a2_layers.php, oldest layer first.
 *
 * Step 7: S6 took 2 @ 6 and 5 @ 7 and owed 3 at 4; costed again with P7 in
 * stock it takes the 3 @ 8. Step 8 restocks at the oldest layer in stock, P7's
 * 8. Step 9: S3, S5 and S6 are costed again in date order against P2 at 6.50.
 * Step 11, dated day 2, takes P2's stock from day 3.
 */
require_once __DIR__ . '/shared/a2_layers.php';

return a2_layers_scenario('f', 'A2 FIFO: layers spanned, short stock filled, restock, edit, delete, backdated sale', array(
    1  => array('qty' => 10, 'inventory' => 50,  'cogs' => 0,   'ap' => -50,  'layers' => array('P1' => 10)),
    2  => array('qty' => 20, 'inventory' => 110, 'cogs' => 0,   'ap' => -110, 'layers' => array('P1' => 10, 'P2' => 10)),
    3  => array('qty' => 8,  'inventory' => 48,  'cogs' => 62,  'ap' => -110, 'layers' => array('P1' => 0, 'P2' => 8),
                'doc_cogs' => array('S3' => 62)),
    4  => array('qty' => 13, 'inventory' => 83,  'cogs' => 62,  'ap' => -145, 'layers' => array('P2' => 8, 'P4' => 5)),
    5  => array('qty' => 7,  'inventory' => 47,  'cogs' => 98,  'ap' => -145, 'layers' => array('P2' => 2, 'P4' => 5),
                'doc_cogs' => array('S5' => 36)),
    6  => array('qty' => -3, 'inventory' => -12, 'cogs' => 157, 'ap' => -145, 'layers' => array('P2' => 0, 'P4' => 0),
                'doc_cogs' => array('S6' => 59)),
    7  => array('qty' => 7,  'inventory' => 56,  'cogs' => 169, 'ap' => -225, 'layers' => array('P2' => 0, 'P4' => 0, 'P7' => 7),
                'doc_cogs' => array('S6' => 71)),
    8  => array('qty' => 8,  'inventory' => 64,  'cogs' => 161, 'ap' => -225, 'layers' => array('P7' => 7, 'C8' => 1),
                'doc_cogs' => array('C8' => -8)),
    9  => array('qty' => 8,  'inventory' => 64,  'cogs' => 166, 'ap' => -230,
                'layers' => array('P1' => 0, 'P2' => 0, 'P4' => 0, 'P7' => 7, 'C8' => 1),
                'doc_cogs' => array('S3' => 63, 'S5' => 39, 'S6' => 72, 'C8' => -8)),
    10 => array('qty' => 14, 'inventory' => 103, 'cogs' => 127, 'ap' => -230, 'layers' => array('P2' => 6, 'P4' => 0, 'P7' => 7, 'C8' => 1),
                'doc_cogs' => array('S6' => 72)),
    11 => array('qty' => 11, 'inventory' => 83.50, 'cogs' => 146.50, 'ap' => -230, 'layers' => array('P2' => 3, 'P7' => 7, 'C8' => 1),
                'doc_cogs' => array('S11' => 19.50)),
));
