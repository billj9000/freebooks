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
