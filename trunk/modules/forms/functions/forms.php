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
//  Path: /modules/forms/functions/forms.php
//

/**
 * Lookup tables the Formatter needs for the hints that resolve an id to a name.
 *
 * PhreeForm did these with per-module `pf_process_*` hooks that queried inside
 * the formatting call. The Formatter takes them as data instead, because a
 * renderer that can query is a renderer coupled to the data layer (design §2.1)
 * — so the arrays are gathered here, above the seam, and handed down.
 *
 * An absent label is OMITTED rather than blanked, so an unknown code renders as
 * itself. That matches the legacy hooks' isset() fallbacks: a report showing a
 * raw account type is recoverable, one showing an empty cell is not.
 *
 * @return array<string,array<string,string>>
 */
function forms_formatter_lookups(): array
{
    require(DIR_FS_MODULES . 'phreebooks/defaults.php');   // $coa_types_list
    $arrLocales = $GLOBALS['arrLocales'] ?? [];            // phreeform/config_phreeform.php

    $pluck = function (array $rows, string $field): array {
        $out = [];
        foreach ($rows as $key => $row) {
            $value = (string)($row[$field] ?? '');
            if ($value !== '') $out[$key] = $value;
        }
        return $out;
    };

    return [
        'coa_type'  => $pluck($coa_types_list ?? [], 'text'),
        'cvt_iso2'  => $pluck($arrLocales, 'iso2'),
        'cvt_cntry' => $pluck($arrLocales, 'name'),
    ];
}

/**
 * The document-wide values a report's page furniture is drawn from.
 *
 * PhreeForm read COMPANY_NAME straight from a global inside its render loop.
 * Passing it explicitly is what lets a renderer be tested without booting the
 * application (§4), and it is the reason the company name appears on a report
 * at all: TablePdfRenderer draws the heading band from `context`, so a Document
 * built without one prints a blank line where the company should be.
 *
 * A name that is not defined is left OUT rather than blanked, so the renderer's
 * own missing-value handling decides what to draw.
 */
function forms_report_context(): \FreeBooks\Forms\Document\Context
{
    $values = array();
    foreach (array('COMPANY_NAME', 'COMPANY_ADDRESS1', 'COMPANY_ADDRESS2', 'COMPANY_CITY_TOWN',
                   'COMPANY_STATE_PROVINCE', 'COMPANY_POSTAL_CODE', 'COMPANY_COUNTRY',
                   'COMPANY_TELEPHONE1', 'COMPANY_EMAIL') as $name) {
        if (defined($name) && (string)constant($name) !== '') {
            $values[$name] = \FreeBooks\Forms\Document\TypedValue::text((string)constant($name));
        }
    }
    return new \FreeBooks\Forms\Document\Context($values, $_SESSION['language'] ?? 'en_us');
}

/**
 * The extra recipients, as validate_send_mail() wants them.
 *
 * That function takes To, subject and body as arguments but reads CC and BCC out
 * of its `$block` array — a shape nobody would guess, and getting a key wrong
 * drops a recipient silently rather than failing. It is built in one place, and
 * tested in one place, for exactly that reason.
 *
 * Empty entries are OMITTED rather than passed as '': PHPMailer's addCC('')
 * raises an invalid-address error and the whole message fails to send, so a user
 * who left CC blank would get no email at all.
 *
 * @param array<string,string> $fields cc_email, cc_name, bcc_email, bcc_name
 * @return array<string,string>
 */
function forms_email_block(array $fields): array
{
    $block = array();
    foreach (array('CC' => 'cc', 'BCC' => 'bcc') as $key => $prefix) {
        $address = trim((string)($fields[$prefix . '_email'] ?? ''));
        if ($address === '') continue;
        $block['EMAIL_' . $key . '_ADDRESS'] = $address;
        $name = trim((string)($fields[$prefix . '_name'] ?? ''));
        if ($name !== '') $block['EMAIL_' . $key . '_NAME'] = $name;
    }
    return $block;
}

/**
 * Who the message is from, defaulting to the signed-in user.
 *
 * PhreeForm offers this and it matters: a report emailed by the bookkeeper
 * should come back to the bookkeeper, not to the company's generic address.
 *
 * @return array{0: string, 1: string} name, address
 */
function forms_email_sender(string $address = '', string $name = ''): array
{
    $address = trim($address) !== '' ? trim($address) : (string)($_SESSION['admin_email'] ?? '');
    $name    = trim($name)    !== '' ? trim($name)    : (string)($_SESSION['display_name'] ?? '');
    return array($name, $address);
}
