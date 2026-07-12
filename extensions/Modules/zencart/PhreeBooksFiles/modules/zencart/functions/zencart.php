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
function zencart_api_call(string $task, array $body = null, array $params = [])
{
    global $messageStack;

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
    curl_close($ch);

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
