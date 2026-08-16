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

/**
 * The binding names a form can actually supply.
 *
 * A query-driven form has a dataset, and the dataset declares its fields. The
 * nine served by a provider declare NOTHING — design §13.8 risk 3 — so the
 * provider is asked the only way it can be: the form is run once and the keys
 * are read back off the Record it produced. Measured rather than assumed, and
 * it is the same contract the renderer will resolve against.
 *
 * Shared by the canvas page and by the render endpoint deliberately. They must
 * agree on what an author may name, or the picture would accept an edit the
 * save then refuses.
 *
 * The template's own bindings are always allowed, whatever the answer, or a
 * form would lose a field the moment its record table went empty.
 *
 * @return array<string,true>
 */
function forms_binding_names($definition, $runner, $catalog, $db): array
{
    $allowed = array();

    foreach ($definition->template?->elements ?? array() as $element) {
        if (($element instanceof \FreeBooks\Forms\Document\Field
             || $element instanceof \FreeBooks\Forms\Document\Barcode)
            && $element->bind->scope !== \FreeBooks\Forms\Document\Scope::Context) {
            $allowed[$element->bind->name] = true;
        }
        if ($element instanceof \FreeBooks\Forms\Document\Image
            && $element->src instanceof \FreeBooks\Forms\Document\Binding) {
            $allowed[$element->src->name] = true;
        }
        if ($element instanceof \FreeBooks\Forms\Document\Table) {
            foreach ($element->columns as $column) $allowed[$column->bind->name] = true;
        }
    }

    if ($definition->datasetId !== null && $catalog->has($definition->datasetId)) {
        foreach (array_keys($catalog->get($definition->datasetId)->fields) as $key) $allowed[$key] = true;
        return $allowed;
    }

    $plan = $runner->planDefinition($definition);
    if (!$plan->runnable() || $plan->recordTable === ''
        || !preg_match('/^[a-z_][a-z0-9_]*$/i', $plan->recordKey)) {
        return $allowed;
    }

    $rows = $db->Execute("select distinct `" . $plan->recordKey . "` from `"
        . (defined('DB_PREFIX') ? DB_PREFIX : '') . $plan->recordTable . "`
        order by `" . $plan->recordKey . "` desc limit 5");
    while ($rows && !$rows->EOF) {
        $id = (string)$rows->fields[$plan->recordKey];
        $rows->MoveNext();
        try {
            $document = $runner->runDefinition($definition, array($id),
                array('dates' => $definition->dateDefault));
        } catch (\Throwable $e) { continue; }
        if (!($document instanceof \FreeBooks\Forms\Document\LayoutDocument)
            || count($document->records) === 0) continue;

        $record = $document->records[0];
        foreach (array_keys($record->values) as $key) $allowed[$key] = true;
        foreach ($record->tables as $lines) {
            foreach ($lines as $line) foreach (array_keys($line) as $key) $allowed[$key] = true;
            break;
        }
        break;
    }
    return $allowed;
}

/**
 * The fonts a document may be drawn in.
 *
 * PhreeForm's own list, from its defaults.php — helvetica, courier, times and,
 * on TCPDF, freeserif. It is short because these are the faces the PDF layer
 * can be relied on to have; a name TCPDF cannot find is not a fallback but an
 * error, raised from inside the renderer with the document half built.
 *
 * The keys are what a definition stores and the values are what a person reads.
 * They are not the same thing, which is the whole reason the canvas offers a
 * list: nobody would guess that the default face is spelled "freeserif".
 *
 * @return array<string,string> stored name => label
 */
function forms_font_names(): array
{
    $fonts = $GLOBALS['Fonts'] ?? array();
    return is_array($fonts) ? $fonts : array();
}

/**
 * The folder names the catalogue already carries.
 *
 * The document list used to sort by `doc_group` and print it — `cust:inv`,
 * `vend:po`, `bnk:chk` — which is an internal code, so the page looked
 * unordered when in fact it was ordered by something nobody can read.
 *
 * The names exist, in the same table, as rows with `doc_type = '0'` that the
 * list deliberately excludes. There are three kinds of them:
 *
 *   - `doc_ext = '0'`  a category:      `cust` => "Customers"
 *   - `doc_ext = 'fr'` its reports:     `cust` => "Reports"
 *   - `doc_ext = 'ff'` a forms folder:  `cust:inv` => "Invoices/Packing Slips"
 *
 * A REPORT is filed under its category, because every report in a category
 * shares one folder called "Reports" and a page full of headers reading
 * "Reports" would say nothing. A FORM is filed under its own folder, whose name
 * already identifies it. That asymmetry is in the data, not invented here.
 *
 * @return array{category: array<string,string>, form: array<string,string>}
 *   both keyed by doc_group
 */
function forms_folder_titles($db): array
{
    $titles = array('category' => array(), 'form' => array());
    $result = $db->Execute("select doc_group, doc_ext, doc_title from " . TABLE_PHREEFORM . "
      where doc_type = '0' and doc_ext in ('0', 'ff')");
    while ($result && !$result->EOF) {
        $group = (string)$result->fields['doc_group'];
        $where = $result->fields['doc_ext'] === '0' ? 'category' : 'form';
        $titles[$where][$group] = (string)$result->fields['doc_title'];
        $result->MoveNext();
    }
    return $titles;
}

/**
 * Which folder heading a document belongs under.
 *
 * Falls back to the raw group when a folder row is missing — a company can have
 * documents in a group nobody named, and a heading reading `cust:inv` is poor
 * but truthful, where dropping the row would hide a document completely.
 *
 * @param array{category: array<string,string>, form: array<string,string>} $titles
 */
function forms_folder_for(string $group, bool $isForm, array $titles): string
{
    if ($isForm) return $titles['form'][$group] ?? $group;
    // A report's group IS its category; a form's is the category plus a folder.
    return $titles['category'][$group] ?? $titles['category'][explode(':', $group)[0]] ?? $group;
}
