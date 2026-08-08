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
//  Path: /modules/vin_lookup/classes/vin_lookup.php
//
// Vehicle Data Global lookup by UK registration mark, with a local cache
// (TABLE_VIN_LOOKUP_CACHE) so repeat lookups never re-hit the paid provider.
//

class VinLookup {

  /** Uppercase and strip everything but letters/digits: "ab12 cde" -> "AB12CDE" */
  public static function normalizeVrm($vrm) {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$vrm));
  }

  /** Is the module configured with an API key? */
  public static function enabled() {
    return defined('VIN_LOOKUP_API_KEY') && VIN_LOOKUP_API_KEY !== '';
  }

  /**
   * Call the provider directly (no cache). Returns:
   *   ['ok' => bool, 'data' => array|null, 'error' => string, 'http' => int]
   */
  public static function fetch($vrm, $apiKey = null, $packages = null, $endpoint = null) {
    $apiKey   = $apiKey   ?? (defined('VIN_LOOKUP_API_KEY')  ? VIN_LOOKUP_API_KEY  : '');
    $packages = $packages ?? (defined('VIN_LOOKUP_PACKAGES') ? VIN_LOOKUP_PACKAGES : 'VehicleDetails');
    $endpoint = $endpoint ?? (defined('VIN_LOOKUP_ENDPOINT') ? VIN_LOOKUP_ENDPOINT : 'https://uk.api.vehicledataglobal.com/r2/lookup');

    if ($apiKey === '') {
      return array('ok' => false, 'data' => null, 'error' => VIN_ERROR_NO_KEY, 'http' => 0);
    }
    $vrm = self::normalizeVrm($vrm);
    if ($vrm === '') {
      return array('ok' => false, 'data' => null, 'error' => VIN_ERROR_NO_VRM, 'http' => 0);
    }

    $url = rtrim($endpoint, '?&')
         . '?packageName=' . urlencode($packages)
         . '&vrm='         . urlencode($vrm);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $apiKey));
    $raw      = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($raw === false || $raw === '') {
      $msg = $curlErr !== '' ? $curlErr : VIN_ERROR_NO_RESPONSE;
      return array('ok' => false, 'data' => null, 'error' => $msg, 'http' => $httpCode);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      return array('ok' => false, 'data' => null, 'error' => VIN_ERROR_BAD_RESPONSE, 'http' => $httpCode);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
      $msg = $data['ResponseInformation']['StatusMessage'] ?? sprintf(VIN_ERROR_HTTP_STATUS, $httpCode);
      return array('ok' => false, 'data' => $data, 'error' => $msg, 'http' => $httpCode);
    }
    if (isset($data['ResponseInformation']['IsSuccessStatusCode']) && !$data['ResponseInformation']['IsSuccessStatusCode']) {
      $msg = $data['ResponseInformation']['StatusMessage'] ?? VIN_ERROR_NOT_FOUND;
      return array('ok' => false, 'data' => $data, 'error' => $msg, 'http' => $httpCode);
    }
    return array('ok' => true, 'data' => $data, 'error' => '', 'http' => $httpCode);
  }

  /**
   * Cache-first lookup. Returns:
   *   ['ok' => bool, 'data' => array|null, 'error' => string, 'cached' => bool,
   *    'date_added' => string, 'hit_count' => int]
   * Failures are never written to the cache.
   */
  public static function get($vrm, $force = false) {
    global $db;
    $vrm = self::normalizeVrm($vrm);
    if ($vrm === '') {
      return array('ok' => false, 'data' => null, 'error' => VIN_ERROR_NO_VRM, 'cached' => false, 'date_added' => '', 'hit_count' => 0);
    }

    if (!$force) {
      $result = $db->Execute("SELECT * FROM " . TABLE_VIN_LOOKUP_CACHE . " WHERE vrm = '" . db_input($vrm) . "'");
      if (!$result->EOF) {
        $row  = $result->fields;
        $data = json_decode($row['response'], true);
        db_perform(TABLE_VIN_LOOKUP_CACHE, array(
          'date_last_used' => 'now()',
          'hit_count'      => (int)$row['hit_count'] + 1,
        ), 'update', "id = " . (int)$row['id']);
        return array(
          'ok'         => true,
          'data'       => is_array($data) ? $data : array(),
          'error'      => '',
          'cached'     => true,
          'date_added' => $row['date_added'],
          'hit_count'  => (int)$row['hit_count'] + 1,
        );
      }
    }

    $fetched = self::fetch($vrm);
    if (!$fetched['ok']) {
      return array(
        'ok' => false, 'data' => $fetched['data'], 'error' => $fetched['error'],
        'cached' => false, 'date_added' => '', 'hit_count' => 0,
      );
    }
    // Request/billing metadata describes this one API call, not the vehicle — it's
    // meaningless (and misleading) once served back out of the cache, so never store it.
    $fetched['data'] = self::stripEnvelope($fetched['data']);

    $now      = date('Y-m-d H:i:s');
    $vin      = self::extractVin($fetched['data']);
    $make     = self::extractField($fetched['data'], array('make', 'dvlamake'));
    $model    = self::extractField($fetched['data'], array('model', 'dvlamodel'));
    $packages = defined('VIN_LOOKUP_PACKAGES') ? VIN_LOOKUP_PACKAGES : 'VehicleDetails';
    $adminId  = (int)($_SESSION['admin_id'] ?? 0);

    $existing = $db->Execute("SELECT id FROM " . TABLE_VIN_LOOKUP_CACHE . " WHERE vrm = '" . db_input($vrm) . "'");
    // A fresh fetch always overwrites date_added — either this is a brand-new cache row,
    // or it's a forced refresh of an existing one, and either way the data is as-of now.
    $row_data = array(
      'vrm'            => $vrm,
      'vin'            => (string)$vin,
      'make'           => (string)$make,
      'model'          => (string)$model,
      'packages'       => $packages,
      'response'       => json_encode($fetched['data']),
      'date_added'     => 'now()',
      'date_last_used' => 'now()',
      'hit_count'      => 0,
      'admin_id'       => $adminId,
    );
    if (!$existing->EOF) {
      db_perform(TABLE_VIN_LOOKUP_CACHE, $row_data, 'update', "id = " . (int)$existing->fields['id']);
    } else {
      db_perform(TABLE_VIN_LOOKUP_CACHE, $row_data, 'insert');
    }

    return array(
      'ok' => true, 'data' => $fetched['data'], 'error' => '',
      'cached' => false, 'date_added' => $now, 'hit_count' => 0,
    );
  }

  /** Cached lookups for the "Recent Lookups" list, most recently used first. */
  public static function recent($limit = 50) {
    global $db;
    $rows = array();
    $result = $db->Execute(
      "SELECT id, vrm, vin, make, model, date_added, date_last_used, hit_count
       FROM " . TABLE_VIN_LOOKUP_CACHE . " ORDER BY date_last_used DESC LIMIT " . (int)$limit
    );
    while (!$result->EOF) {
      $rows[] = $result->fields;
      $result->MoveNext();
    }
    return $rows;
  }

  /** Recursively find the VIN field; treats the "LICENSE_RESTRICTED" sentinel as absent. */
  public static function extractVin(array $data) {
    $found = self::findKey($data, '/^vin$/i');
    if ($found === null) return '';
    if (strtoupper((string)$found) === 'LICENSE_RESTRICTED') return '';
    return (string)$found;
  }

  /** Recursively find the first of several candidate key names (case-insensitive). */
  public static function extractField(array $data, array $names) {
    foreach ($names as $name) {
      $found = self::findKey($data, '/^' . preg_quote($name, '/') . '$/i');
      if ($found !== null && $found !== '') return (string)$found;
    }
    return '';
  }

  /** Drop per-request envelope fields (request/billing metadata) that are meaningless once cached. */
  private static function stripEnvelope(array $data) {
    static $dropKeys = array('requestinformation', 'billinginformation');
    foreach (array_keys($data) as $key) {
      if (is_string($key) && in_array(strtolower($key), $dropKeys, true)) unset($data[$key]);
    }
    return $data;
  }

  private static function findKey($data, $pattern) {
    foreach ($data as $key => $value) {
      if (is_string($key) && preg_match($pattern, $key) && !is_array($value) && $value !== null && $value !== '') {
        return $value;
      }
    }
    foreach ($data as $value) {
      if (is_array($value)) {
        $found = self::findKey($value, $pattern);
        if ($found !== null) return $found;
      }
    }
    return null;
  }

  /**
   * Flatten the decoded provider response into display sections:
   *   ['Section Label' => ['raw_field_key' => 'value', ...], ...]
   * Field keys are left un-labelized (the caller runs them through
   * labelize() at render time) so a field like "Vin" can still be
   * recognised by name regardless of display formatting.
   * Nested objects become their own section (named after the parent path);
   * a list of objects becomes one numbered section per entry.
   * Null/empty scalars are dropped. Status/envelope noise is skipped.
   */
  public static function flatten(array $data, $path = '') {
    static $skipKeys = array(
      'responseinformation', 'statuscode', 'statusmessage', 'documentversion', 'requestguid',
      'requestinformation', 'billinginformation',
    );
    $sections = array();
    $scalars  = array();

    foreach ($data as $key => $value) {
      if (is_string($key) && in_array(strtolower($key), $skipKeys, true)) continue;

      if (is_array($value)) {
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
          foreach ($value as $i => $item) {
            $label = self::labelize($key) . ' ' . ($i + 1);
            if (is_array($item)) {
              $sections += self::flatten($item, $label);
            } else if ($item !== null && $item !== '') {
              $scalars[$label] = $item;
            }
          }
        } else {
          $childLabel = $path === '' ? self::labelize($key) : $path . ' — ' . self::labelize($key);
          $sections += self::flatten($value, $childLabel);
        }
      } else if ($value !== null && $value !== '') {
        $scalars[$key] = $value;
      }
    }

    if (!empty($scalars)) {
      $sectionName = $path === '' ? VIN_TEXT_VEHICLE_DETAILS : $path;
      $sections[$sectionName] = $scalars;
    }
    return $sections;
  }

  /** "DateOfFirstRegistration" -> "Date Of First Registration" */
  public static function labelize($key) {
    $spaced = preg_replace('/(?<!^)([A-Z])/', ' $1', (string)$key);
    return ucwords(str_replace('_', ' ', $spaced));
  }
}
