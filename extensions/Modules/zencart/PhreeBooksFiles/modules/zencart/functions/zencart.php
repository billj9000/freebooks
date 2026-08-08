<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
// Path: /modules/zencart/functions/zencart.php

// Calls the ZenCart REST API (freebooks-zencart.php).
// $task    — value for the ?task= query parameter
// $body    — if set, JSON-encodes and sends as POST; otherwise GET
// $params  — additional query string parameters (e.g. ['last_order' => 42])
// Returns the decoded 'data' field on success, true if no 'data' field, false on error.
function zencart_api_call(string $task, ?array $body = null, array $params = [])
{
    global $messageStack;

    // Master enable switch — when disabled, no ZenCart traffic (pull or push) occurs.
    if (!defined('ZENCART_ENABLED') || ZENCART_ENABLED !== '1') return false;

    $qs = array_merge(['task' => $task], $params);
    $url = rtrim(ZENCART_URL, '/') . '/freebooks-zencart.php?' . http_build_query($qs);

    $headers = [
        'Authorization: Bearer ' . ZENCART_API_KEY,
        'Accept: application/json',
        'User-Agent: FreeBooks-ZenCart/1.0',
        'Referer: ' . rtrim(ZENCART_URL, '/') . '/',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    if ($body !== null) {
        $json = json_encode($body);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($errno) {
        $messageStack->add('ZenCart API cURL error: ' . $error, 'error');
        return false;
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $messageStack->add('ZenCart API returned invalid JSON (HTTP ' . $http . ')', 'error');
        return false;
    }

    if (empty($decoded['ok'])) {
        $messageStack->add('ZenCart API error: ' . ($decoded['error'] ?? 'unknown'), 'error');
        return false;
    }

    return $decoded['data'] ?? true;
}

// Resolves a ZenCart shipping module code + description to a FreeBooks module + service key.
// Parses ZENCART_SHIPPING_MAP: pipe-separated entries of the form zc_module:keyword=fb_module:fb_service.
// The '=' separates the ZenCart lookup side from the FreeBooks side, allowing ':' in keywords.
// keyword is matched case-insensitively as a substring of $zc_description; blank keyword = catch-all.
// Longest matching keyword wins (prevents "Express Domestic" shadowing "Express Domestic 12").
// Returns ['module' => string, 'service' => string]; shipper_code = "module:service".
function zencart_resolve_shipping(string $zc_module, string $zc_description): array
{
    $fallback = ['module' => $zc_module, 'service' => ''];
    if (!defined('ZENCART_SHIPPING_MAP') || ZENCART_SHIPPING_MAP === '') return $fallback;

    $catch_all   = null;
    $best_match  = null;
    $best_length = -1;
    foreach (explode('|', ZENCART_SHIPPING_MAP) as $entry) {
        [$zc_side, $fb_side]      = array_pad(explode('=', $entry, 2), 2, '');
        [$mod, $keyword]          = array_pad(explode(':', trim($zc_side), 2), 2, '');
        [$fb_module, $fb_service] = array_pad(explode(':', trim($fb_side), 2), 2, '');
        $mod        = trim($mod);
        $keyword    = trim($keyword);
        $fb_module  = trim($fb_module);
        $fb_service = trim($fb_service);
        if ($mod !== $zc_module) continue;
        if ($keyword === '') {
            $catch_all = ['module' => $fb_module, 'service' => $fb_service];
            continue;
        }
        if (stripos($zc_description, $keyword) !== false && strlen($keyword) > $best_length) {
            $best_length = strlen($keyword);
            $best_match  = ['module' => $fb_module, 'service' => $fb_service];
        }
    }
    return $best_match ?? $catch_all ?? $fallback;
}

// Builds the attributes payload for a Master Stock (ms/mb) inventory item.
// Returns an array with 'options' and 'variants' keys, or null if the item is
// not a master or has no attribute definitions.
//
// options[]  — drives Zen Cart product-option dropdowns (vanilla ZenCart 1.5.8+)
// variants[] — superset per-child record; a future POSM branch on the API side
//              can consume this to write per-variant stock without any change here.
function zencart_build_attributes(int $id): ?array
{
    global $db;

    $ms_row = $db->Execute(
        "SELECT attr_name_0, attr_0, attr_name_1, attr_1"
        . " FROM " . TABLE_INVENTORY_MS_LIST
        . " WHERE sku = (SELECT sku FROM " . TABLE_INVENTORY . " WHERE id = " . $id . " LIMIT 1)"
        . " LIMIT 1"
    );
    if ($ms_row->EOF || ($ms_row->fields['attr_0'] === '' && $ms_row->fields['attr_1'] === '')) {
        return null;
    }

    // Parse each attr CSV "code:desc,code:desc," — mirrors ms.php::get_ms_list().
    // Deduplicates by code so a tripled CSV (e.g. from repeated saves) emits each
    // option value exactly once.
    $parse_attr = static function (string $csv): array {
        $entries    = [];
        $seen_codes = [];
        foreach (explode(',', $csv) as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            $pos  = strpos($pair, ':');
            if ($pos === false) continue;
            $code = trim(substr($pair, 0, $pos));
            $desc = trim(substr($pair, $pos + 1));
            if ($code !== '' && !isset($seen_codes[$code])) {
                $entries[]          = ['code' => $code, 'name' => $desc];
                $seen_codes[$code]  = true;
            }
        }
        return $entries;
    };

    $attr0_entries = $parse_attr((string)$ms_row->fields['attr_0']);
    $attr1_entries = $parse_attr((string)$ms_row->fields['attr_1']);

    $options = [];
    $attr_name_0 = trim((string)$ms_row->fields['attr_name_0']);
    $attr_name_1 = trim((string)$ms_row->fields['attr_name_1']);

    if ($attr_name_0 !== '' && !empty($attr0_entries)) {
        $values = [];
        foreach ($attr0_entries as $sort => $e) {
            $values[] = ['name' => $e['name'], 'code' => $e['code'], 'sortOrder' => $sort];
        }
        $options[] = ['name' => $attr_name_0, 'sortOrder' => 0, 'values' => $values];
    }
    if ($attr_name_1 !== '' && !empty($attr1_entries)) {
        $values = [];
        foreach ($attr1_entries as $sort => $e) {
            $values[] = ['name' => $e['name'], 'code' => $e['code'], 'sortOrder' => $sort];
        }
        $options[] = ['name' => $attr_name_1, 'sortOrder' => 1, 'values' => $values];
    }

    if (empty($options)) return null;

    // Build variants: cross-product of attr0 × attr1, same order as ms.php::save()
    // When only one attribute is defined, attr1_entries is empty so we loop once with a null slot.
    $variants  = [];
    $master_sku_row = $db->Execute(
        "SELECT sku FROM " . TABLE_INVENTORY . " WHERE id = " . $id . " LIMIT 1"
    );
    $master_sku = $master_sku_row->EOF ? '' : (string)$master_sku_row->fields['sku'];

    $loop1 = !empty($attr1_entries) ? $attr1_entries : [null];
    foreach ($attr0_entries as $e0) {
        foreach ($loop1 as $e1) {
            $child_sku = $master_sku . '-' . $e0['code'] . ($e1 !== null ? $e1['code'] : '');
            $child_row = $db->Execute(
                "SELECT quantity_on_hand, full_price FROM " . TABLE_INVENTORY
                . " WHERE sku = '" . db_input($child_sku) . "' AND inventory_type = 'mi' LIMIT 1"
            );
            $child_qty   = $child_row->EOF ? 0.0 : (float)$child_row->fields['quantity_on_hand'];
            $child_price = $child_row->EOF ? 0.0 : (float)$child_row->fields['full_price'];

            $combo_options = [['name' => $attr_name_0, 'value' => $e0['name']]];
            if ($e1 !== null) {
                $combo_options[] = ['name' => $attr_name_1, 'value' => $e1['name']];
            }

            $variants[] = [
                'sku'      => $child_sku,
                'quantity' => $child_qty,
                'price'    => $child_price,
                'options'  => $combo_options,
            ];
        }
    }

    return ['options' => $options, 'variants' => $variants];
}

function pull_down_price_sheet_list() {
    global $db;
    $output = [['id' => '0', 'text' => TEXT_NONE]];
    $result = $db->Execute(
        "SELECT DISTINCT sheet_name FROM " . TABLE_PRICE_SHEETS
        . " WHERE '" . date('Y-m-d') . "' >= effective_date AND inactive = '0'"
    );
    while (!$result->EOF) {
        $output[] = ['id' => $result->fields['sheet_name'], 'text' => $result->fields['sheet_name']];
        $result->MoveNext();
    }
    return $output;
}

// Pull-down list of every installed cust:inv PhreeForm form, for the "which form to
// email as the VAT invoice" setting. Listing by doc_group/doc_ext (rather than a fixed
// title) means any custom invoice form built in the PhreeForm designer shows up here
// automatically, with no code change.
function zencart_pull_invoice_forms(): array {
    global $db;
    if (!defined('TABLE_PHREEFORM')) define('TABLE_PHREEFORM', DB_PREFIX . 'phreeform');
    $output = [['id' => '', 'text' => TEXT_SELECT]];
    $result = $db->Execute(
        "SELECT id, doc_title FROM " . TABLE_PHREEFORM
        . " WHERE doc_group = 'cust:inv' AND doc_ext = 'frm' ORDER BY doc_title"
    );
    while (!$result->EOF) {
        $output[] = ['id' => $result->fields['id'], 'text' => $result->fields['doc_title']];
        $result->MoveNext();
    }
    return $output;
}

// Emails a VAT invoice for a just-posted ZenCart sales invoice, when the customer is
// VAT-registered (contacts.vat_number is non-empty) and the feature is enabled.
// Call this only after the invoice AND its payment (if any) have been posted — the
// configured form typically renders a payments section, and posting it earlier would
// email an invoice that looks unpaid.
// Returns true on a successful send; false on any skip or failure (always logged, never
// thrown — a PDF/email failure must not break the import loop).
function zencart_email_vat_invoice(int $invoice_id, int $acct_id, int $zc_id): bool
{
    global $db;

    try {
        if (!defined('ZENCART_VAT_INVOICE') || ZENCART_VAT_INVOICE !== '1') return false;

        $form_id = defined('ZENCART_VAT_INVOICE_FORM') ? (int)ZENCART_VAT_INVOICE_FORM : 0;
        if ($form_id <= 0) {
            echo ts() . " Order $zc_id: VAT invoice email enabled but no form is configured (Company -> ZenCart -> General). Skipping.\n";
            return false;
        }

        $contact = $db->Execute(
            "SELECT vat_number FROM " . TABLE_CONTACTS . " WHERE id = " . (int)$acct_id . " LIMIT 1"
        );
        $vat_number = $contact->EOF ? '' : trim((string)($contact->fields['vat_number'] ?? ''));
        if ($vat_number === '') return false; // not VAT-registered — not an error, just not applicable

        $inv = $db->Execute(
            "SELECT purchase_invoice_id, bill_primary_name, bill_email"
            . " FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)$invoice_id . " LIMIT 1"
        );
        if ($inv->EOF) {
            fwrite(STDERR, ts() . " Order $zc_id: VAT invoice email skipped, invoice $invoice_id not found.\n");
            return false;
        }
        $to_email = trim((string)($inv->fields['bill_email'] ?? ''));
        $to_name  = (string)($inv->fields['bill_primary_name'] ?? '');
        $inv_num  = (string)($inv->fields['purchase_invoice_id'] ?? '');
        if ($to_email === '' || !validate_email($to_email)) {
            fwrite(STDERR, ts() . " Order $zc_id: VAT invoice email skipped, no valid billing email for invoice $inv_num.\n");
            return false;
        }

        // Render the invoice form to PDF (return-as-string, delivery_method 'S').
        // Modelled on modules/payment/ajax/paypal_email_link.php::_paypal_build_invoice_pdf().
        if (!defined('TABLE_PHREEFORM')) define('TABLE_PHREEFORM', DB_PREFIX . 'phreeform');
        gen_pull_language('phreeform');
        if (!defined('PF_DIR_MY_REPORTS')) require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
        require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');

        // Most invoice forms (including the stock Invoice-VAT) declare special_class
        // 'entry_builder', resolved via find_special_class() against $loaded_modules —
        // normally populated by includes/application_top.php on a web request, but this
        // cron script never goes through that bootstrap. Build the same list here so the
        // form's special class (wherever its owning module actually lives) can be found.
        if (empty($GLOBALS['loaded_modules'])) {
            $GLOBALS['loaded_modules'] = [];
            foreach (scandir(DIR_FS_MODULES) as $dir) {
                if ($dir === '.' || $dir === '..' || !is_dir(DIR_FS_MODULES . $dir)) continue;
                if (defined('MODULE_' . strtoupper($dir) . '_STATUS')) $GLOBALS['loaded_modules'][] = $dir;
            }
        }

        $report = get_report_details($form_id);
        if (!$report) {
            fwrite(STDERR, ts() . " Order $zc_id: VAT invoice email skipped, configured form (id $form_id) could not be loaded.\n");
            return false;
        }

        $f = new stdClass();
        $f->fieldname = 'journal_main.id';
        $f->default   = 'EQUAL';
        $f->min_val   = (string)$invoice_id;
        $f->type      = '';
        $report->xfilterlist = [$f];
        $report->datedefault = '';

        // entry_builder::load_query_results() declares `global $report` and iterates
        // $report->fieldlist to build $FieldValues — without this, header/customer
        // fields on the form render blank.
        $GLOBALS['report'] = $report;

        $pdf = BuildForm($report, 'S');
        if (!is_array($pdf) || empty($pdf['pdf'])) {
            fwrite(STDERR, ts() . " Order $zc_id: VAT invoice PDF render failed for invoice $inv_num.\n");
            return false;
        }

        $temp_path = DIR_FS_MY_FILES . $_SESSION['company'] . '/temp/vat_invoice_' . $invoice_id . '_' . time() . '.pdf';
        if (@file_put_contents($temp_path, $pdf['pdf']) === false) {
            fwrite(STDERR, ts() . " Order $zc_id: could not write temp PDF for invoice $inv_num.\n");
            return false;
        }

        $subject_tpl = defined('ZENCART_VAT_INVOICE_SUBJECT') && ZENCART_VAT_INVOICE_SUBJECT !== ''
            ? ZENCART_VAT_INVOICE_SUBJECT : 'VAT Invoice {invoice}';
        $message_tpl = defined('ZENCART_VAT_INVOICE_MESSAGE') && ZENCART_VAT_INVOICE_MESSAGE !== ''
            ? ZENCART_VAT_INVOICE_MESSAGE : ZENCART_VAT_INVOICE_MESSAGE_DEFAULT;
        $placeholders = [
            '{invoice}' => $inv_num,
            '{order}'   => (string)$zc_id,
            '{name}'    => $to_name,
            '{company}' => defined('COMPANY_NAME') ? COMPANY_NAME : '',
            '{date}'    => date('Y-m-d'),
        ];
        $subject = strtr($subject_tpl, $placeholders);
        $message = strtr($message_tpl, $placeholders);

        $from_name    = defined('COMPANY_NAME') ? COMPANY_NAME : 'FreeBooks';
        $from_address = defined('EMAIL_FROM') ? EMAIL_FROM : '';

        $block = [];
        $bcc = defined('ZENCART_VAT_INVOICE_BCC') ? trim(ZENCART_VAT_INVOICE_BCC) : '';
        if ($bcc !== '') $block['EMAIL_BCC_ADDRESS'] = $bcc;

        $attachments_list = ['file' => $temp_path];
        $sent = validate_send_mail($to_name, $to_email, $subject, $message, $from_name, $from_address, $block, $attachments_list);
        @unlink($temp_path);

        if ($sent) {
            echo ts() . " Emailed VAT invoice $inv_num to $to_email (ZenCart order $zc_id, VAT $vat_number)\n";
        } else {
            fwrite(STDERR, ts() . " Order $zc_id: VAT invoice email to $to_email failed for invoice $inv_num.\n");
        }
        return (bool)$sent;
    } catch (\Throwable $e) {
        fwrite(STDERR, ts() . " Order $zc_id: VAT invoice email threw: " . $e->getMessage() . "\n");
        return false;
    }
}
