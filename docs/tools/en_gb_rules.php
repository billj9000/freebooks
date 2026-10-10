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
//  Path: /docs/tools/en_gb_rules.php
//
/**
 * What British English (en_gb) changes from US English (en_us). Read by build_en_gb.php.
 *
 * British English is spelling plus a few form labels. Every name that appears in a menu, tab,
 * page title or button stays as in US English (Vendor, Inventory, Customer, Ship, Sales Tax as a
 * feature name), because the one English Help manual is written in those terms. Renaming one of
 * them is a product-wide change to US English, British English and the Help together.
 *
 * 'spelling' is applied to the visible text of every phrase: never inside HTML tags, URLs, file
 * names or the 'protect' list. Each entry is a pattern matched case-insensitively on whole words;
 * the replacement keeps the case of the original (word, Word, WORD).
 * 'phrases' are changes that depend on meaning, made to one phrase each: NAME => [US text => UK
 * text]. The US text must still be in that phrase, or the build stops - so a reworded US phrase is
 * noticed rather than silently left in US English.
 */
return [
  'locale' => [
    'LANGUAGE'    => "'English (UK)'",
    'HTML_PARAMS' => "'lang=\"en-GB\" xml:lang=\"en-GB\"'",
  ],

  // Made to the whole text of every en_gb file. The installer builds its language list by reading
  // a commented-out LANGUAGE line in its language file as text (install/functions/install.php).
  'file_text' => [
    "define('LANGUAGE','English (US)')" => "define('LANGUAGE','English (UK)')",
  ],

  'protect' => [
    '/https?:\/\/[^\s\'"<]+/i',                 // URLs
    '/\b[\w.\/-]+\.(php|net|com|org|html?|js|css|sql|csv|zip|gz|bz2)\b/i', // file names, domains
    '/\b\w+\(\)/',                               // function names
    '/Authorize\.?Net/i', '/Dial-A-Zip/i',       // product names
    '/GNU (Lesser |Affero )?General Public License/i',
    '/\{[a-z_]+\}/i', '/%[a-z_]+%/i',            // placeholders
    '/\bfield named \w+/i',                     // a field's name, as in "the checkbox field named catalog"
  ],

  'spelling' => [
    // -ise rather than -ize; the stems listed are the ones FreeBooks uses
    '(organ|author|custom|synchron|recogn|initial|final|priorit|categor|summar|optim|normal|serial|minim|maxim|util|real|special|standard|item|capital|memor|emphas|apolog|character|visual|personal|local|central|legal|stabil|sanit|token|equal)iz(e|es|ed|ing|ation|ations|er|ers)' => '$1is$2',
    'analyz(e|es|ed|ing|er|ers)' => 'analys$1',
    'colo(r|rs|red|ring)'        => 'colou$1',
    'cent(er|ers|ered|ering)'    => ['center' => 'centre', 'centers' => 'centres', 'centered' => 'centred', 'centering' => 'centring'],
    'cancel(ed|ing)'             => 'cancell$1',
    'cancelation(s?)'            => 'cancellation$1',
    'label(ed|ing)'              => 'labell$1',
    'total(ed|ing)'              => 'totall$1',
    'model(ed|ing)'              => 'modell$1',
    'travel(ed|ing)'             => 'travell$1',
    'labor(s|ed|ing)?'           => 'labour$1',
    'catalog(s?)'                => ['catalog' => 'catalogue', 'catalogs' => 'catalogues'],
    'favor(s|ed|ing|ite|ites)?'  => 'favour$1',
    'honor(s|ed|ing)?'           => 'honour$1',
    'behavior(s?)'               => 'behaviour$1',
    'neighbor(s?)'               => 'neighbour$1',
    'gray'                       => 'grey',
    'license(s?)'                => 'licence$1',          // the noun; licensed / licensing are the verb in both
    'fulfill(s?)'                => 'fulfil$1',
    'fulfillment(s?)'            => 'fulfilment$1',
    'enroll(s?)'                 => 'enrol$1',
    'enrollment(s?)'             => 'enrolment$1',
    'jewelry'                    => 'jewellery',
    'aluminum'                   => 'aluminium',
    'defense'                    => 'defence',
    'offense'                    => 'offence',
    'expiration date(s?)'        => 'expiry date$1',
    'check(s?)(?= (number|date|register|run|printing|amount)s?\b)' => 'cheque$1',
  ],

  'phrases' => [
    'ACT_E_ID_NUMBER'               => ['Social Security Number' => 'National Insurance Number'],
    'BNK_CHECK_PAYMENT'             => ['Check/Payment' => 'Cheque/Payment'],
    'BNK_OPEN_CHECKS'               => ['Outstanding Checks' => 'Outstanding Cheques'],
    'BOX_BANKING_VOID_CHECKS'       => ['Void Checks' => 'Void Cheques'],
    'PB_PF_BANK_CHECK'              => ['Bank Checks' => 'Bank Cheques'],
    'MODULE_PAYMENT_MONEYORDER_TEXT_TITLE'        => ['Check/Money Order' => 'Cheque/Postal Order'],
    'MODULE_PAYMENT_MONEYORDER_TEXT_DESCRIPTION'  => ['via check, money order' => 'via cheque, postal order'],
    'MODULE_PAYMENT_MONEYORDER_TEXT_INTRODUCTION' => ['your check or money order' => 'your cheque or postal order'],
    'SHIPPING_CHECK'                => ["'Check'" => "'Cheque'"],
    'SHIPPING_CASHIERS'             => ["Cashier\\'s Check" => "Banker\\'s Draft"],
    'CD_01_07_DESC'                 => ['The state or region' => 'The county or region'],
    'CD_01_08_DESC'                 => ['Postal or Zip code' => 'Postcode'],
    'CD_01_09_DESC'                 => ['company state or' => 'company county or'],
    'GEN_COMP_ZIP'                  => ["' Zip'" => "' Postcode'"],
    'GEN_STATE_PROVINCE'            => ["'State'" => "'County'"],
    'RW_AR_BILL_STATE'              => ['Bill State/Province' => 'Bill County/Region'],
    'RW_AR_SHIP_STATE'              => ['Ship State/Province' => 'Ship County/Region'],
    'RW_BO_BILL_STATE'              => ['Bill State/Province' => 'Bill County/Region'],
    'RW_EB_BILL_STATE'              => ['Bill State/Province' => 'Bill County/Region'],
    'RW_EB_SHIP_STATE'              => ['Ship State/Province' => 'Ship County/Region'],
    'RW_SB_BILL_STATE'              => ['Bill State/Province' => 'Bill County/Region'],
    'SHIPPING_TEXT_SHIP_FROM_STATE' => ['Ship From State' => 'Ship From County'],
    'SHIPPING_TEXT_SHIP_TO_STATE'   => ['Ship To State' => 'Ship To County'],
  ],
];
