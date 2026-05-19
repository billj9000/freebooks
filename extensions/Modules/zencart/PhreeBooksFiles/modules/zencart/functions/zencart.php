<?php
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
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
