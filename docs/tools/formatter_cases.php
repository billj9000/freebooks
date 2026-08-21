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
//  Path: /docs/tools/formatter_cases.php
//
/**
 * Every value the Formatter is held to, in one place. See §13.35.
 *
 * Shared by the tool that CAPTURES PhreeForm's answers and the tool that CHECKS
 * Forms against them, because a baseline is only worth having if both sides
 * agree on the questions. Two lists that drift apart produce a baseline with
 * entries nothing asks for and questions nothing has an answer to, and both
 * failures are quiet.
 *
 * ## Environment-bound and environment-free
 *
 * Some answers depend on the company: what DEFAULT_CURRENCY is, what the
 * currency table holds, which language is loaded. `dlr` is the same everywhere;
 * `def_cur` is not. The split is recorded here so a baseline captured on one
 * company can still be used on another for the half that cannot vary — rather
 * than the whole file being useless, or worse, quietly wrong.
 */

return [
    /** Hints whose answer is the same on every installation. */
    'free' => [
        'hints'  => ['', 'blank', 'uc', 'lc', 'neg', 'yesBno', 'printed',
                     'rnd2d', 'dlr', 'null-dlr', 'euro'],
        'values' => [1234.5678, 0, -42.5, '2026-07-14', 'mixed Case Text', '', null, '7.91'],
    ],

    /** Hints that read the company's currencies or its language. */
    'bound' => [
        'hints'  => ['rnd_dec', 'rnd_pre', 'def_cur', 'null_dcur',
                     'posted_cur', 'null_pcur', 'terms'],
        'values' => [1234.5678, 0, -42.5, '2026-07-14', 'mixed Case Text', '', null, '7.91'],
    ],

    /**
     * `date`, and only ever on things that ARE dates.
     *
     * Given anything else, gen_locale_date() answers with TODAY — so those
     * cases cannot be captured at all: the baseline is right on the day it is
     * taken and wrong the next morning. Which is exactly how this was found,
     * by the drift check, four hours after the capture and one minute after
     * midnight. A golden value has to be a value that does not move.
     *
     * The behaviour itself is unchanged and both engines still share it; what
     * is gone is the pretence that it could be written down.
     */
    'dates' => [
        'hints'  => ['date'],
        'values' => ['2026-07-14', '2026-01-01', '2026-12-31', '1999-06-30'],
    ],

    /**
     * The amount in words. Chosen for the JOINS, which is where a
     * number-to-words gets it wrong: the teens (their own words, not
     * tens-plus-units), the hyphen that appears only when a tens word precedes
     * it, a zero hundreds column inside a larger number, and the carry at each
     * thousand boundary.
     */
    'amounts' => [0, 0.5, 1, 1.05, 7, 10, 11, 15, 19, 20, 21, 42, 99, 100, 101,
                  110, 115, 199, 200, 999, 1000, 1001, 1100, 12345.67, 100000,
                  999999.99, 1000000, 1234567.89, 1000000000, 1234567890.12,
                  -5, 3.005],

    /** Separators. '' proves del-nl collapses the line rather than emitting one. */
    'separators' => [
        'names'  => ['sp', '2sp', 'comma', 'com-sp', 'nl', 'semi-sp', 'del-nl', '(unknown)'],
        'values' => ['Acme Ltd', ''],
    ],

    /**
     * Code-to-label hints. Environment-bound to a one: every answer is a
     * language string, and `NewUsed` needs a module that may not be installed.
     */
    'codes' => [
        'j_desc'  => [2, 6, 12, 18, 99],      // 99 = no such journal
        'NewUsed' => ['u', 'n', ''],
    ],
];
