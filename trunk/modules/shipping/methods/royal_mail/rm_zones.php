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
//  Path: /modules/shipping/methods/royal_mail/rm_zones.php
//
// The Royal Mail and Parcelforce zone of each country. A price band names a zone
// rather than a country, so a destination is priced through its zone.
//
// rm_zones.csv beside this file is the list FreeBooks ships, from Royal Mail's
// and Parcelforce's published zones; a company starts with it and can replace it
// with a corrected file. Its zone names are the ones the price list uses. Rows
// marked needs_confirmation are countries the published lists do not settle.
// No country is in PF Zone 13: Parcelforce's price list prices it ("Asia East
// Coast"), but none of its country pages places a country there.

class rm_zones {

  const RM_ZONES = array('UK', 'Europe Zone 1', 'Europe Zone 2', 'Europe Zone 3',
                         'World Zone 1', 'World Zone 2', 'World Zone 3');
  const PF_ZONES = array('UK Zones 1-3', 'PF Zone 4', 'PF Zone 5', 'PF Zone 6', 'PF Zone 7', 'PF Zone 8',
                         'PF Zone 9 EU', 'PF Zone 9 Non-EU', 'PF Zone 10', 'PF Zone 11', 'PF Zone 12', 'PF Zone 13');
  const MAX_ERRORS = 50;

  public $errors      = array();
  public $error_count = 0;

  static function shipped_file() {
    return __DIR__ . '/rm_zones.csv';
  }

  /**
   * Reads and checks a zone file: a CSV with iso2, rm_zone and pf_zone columns,
   * and optionally needs_confirmation (Y/N) and notes; other columns, such as the
   * country name, are ignored. Returns the rows keyed by iso2, or false with the
   * problems in $this->errors.
   */
  function read_file($path) {
    $this->errors      = array();
    $this->error_count = 0;
    $fh = @fopen($path, 'r');
    if (!$fh) { $this->error(RM_ZONES_ERR_READ); return false; }
    $header = fgetcsv($fh, 0, ',', '"', '');
    if (!$header) { fclose($fh); $this->error(RM_ZONES_ERR_EMPTY); return false; }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $cols = array();
    foreach ($header as $i => $name) $cols[strtolower(trim($name))] = $i;
    $missing = array_diff(array('iso2', 'rm_zone', 'pf_zone'), array_keys($cols));
    if ($missing) { fclose($fh); $this->error(sprintf(RM_ZONES_ERR_COLUMNS, implode(', ', $missing))); return false; }

    $known = self::country_names();
    $rows  = array();
    $line  = 1;
    while (($data = fgetcsv($fh, 0, ',', '"', '')) !== false) {
      $line++;
      if ($data === array(null) || trim(implode('', $data)) === '') continue;
      $get  = function ($name) use ($cols, $data) { return isset($cols[$name]) ? trim((string)($data[$cols[$name]] ?? '')) : ''; };
      $iso2 = strtoupper($get('iso2'));
      $row  = array('iso2' => $iso2, 'rm_zone' => $get('rm_zone'), 'pf_zone' => $get('pf_zone'),
        'needs_confirmation' => strtoupper($get('needs_confirmation')) === 'Y' ? '1' : '0',
        'notes' => mb_substr($get('notes'), 0, 255));
      $ok = true;
      if (!isset($known[$iso2]))                              { $this->error(sprintf(RM_ZONES_ERR_COUNTRY, $line, $iso2)); $ok = false; }
      elseif (isset($rows[$iso2]))                           { $this->error(sprintf(RM_ZONES_ERR_DUPLICATE, $line, $iso2)); $ok = false; }
      if (!in_array($row['rm_zone'], self::RM_ZONES, true)) { $this->error(sprintf(RM_ZONES_ERR_ZONE, $line, 'rm_zone', $row['rm_zone'])); $ok = false; }
      if (!in_array($row['pf_zone'], self::PF_ZONES, true)) { $this->error(sprintf(RM_ZONES_ERR_ZONE, $line, 'pf_zone', $row['pf_zone'])); $ok = false; }
      if ($ok) $rows[$iso2] = $row;
    }
    fclose($fh);
    if (!$rows && !$this->error_count) $this->error(RM_ZONES_ERR_EMPTY);
    return $this->error_count ? false : $rows;
  }

  private function error($text) {
    $this->error_count++;
    if (count($this->errors) < self::MAX_ERRORS) $this->errors[] = $text;
  }

  // FreeBooks' countries, iso2 => name, in the user's language.
  static function country_names() {
    $names = array();
    $countries = gen_pull_countries();
    foreach ($countries->country as $c) $names[(string)$c->iso2] = (string)$c->name;
    return $names;
  }

  /**
   * Loads the shipped list into a company that has no zones yet.
   */
  static function ensure_loaded() {
    global $db;
    static $checked = false;
    if ($checked) return;
    $checked = true;
    shipping_ensure_schema();
    $result = $db->Execute("SELECT COUNT(*) AS n FROM " . TABLE_SHIPPING_RM_ZONES);
    if ((int)$result->fields['n'] > 0) return;
    $reader = new rm_zones();
    $rows   = $reader->read_file(self::shipped_file());
    if ($rows) self::replace_all($rows);
  }

  // Stores $rows in place of every zone held.
  static function replace_all($rows) {
    global $db;
    shipping_ensure_schema();
    $db->transStart();
    $db->Execute("DELETE FROM " . TABLE_SHIPPING_RM_ZONES);
    $values = array();
    foreach ($rows as $row) {
      $values[] = "('" . db_input($row['iso2']) . "','" . db_input($row['rm_zone']) . "','" . db_input($row['pf_zone'])
        . "','" . db_input($row['needs_confirmation']) . "','" . db_input($row['notes']) . "')";
    }
    if ($values) {
      $db->Execute("INSERT INTO " . TABLE_SHIPPING_RM_ZONES . " (iso2, rm_zone, pf_zone, needs_confirmation, notes) VALUES "
        . implode(',', $values));
    }
    $db->transCommit();
  }

  /**
   * Every country's zones, iso2 => row with the country's name added, in name order.
   */
  static function all() {
    global $db;
    self::ensure_loaded();
    $names = self::country_names();
    $rows  = array();
    $result = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_RM_ZONES);
    while (!$result->EOF) {
      $row = $result->fields;
      $row['country'] = $names[$row['iso2']] ?? $row['iso2'];
      $rows[$row['iso2']] = $row;
      $result->MoveNext();
    }
    uasort($rows, function ($a, $b) { return strcasecmp($a['country'], $b['country']); });
    return $rows;
  }

  /**
   * A country's zones: array('rm_zone' => ..., 'pf_zone' => ...), or false for a
   * country with none.
   */
  static function zones_for($iso2) {
    global $db;
    self::ensure_loaded();
    $result = $db->Execute("SELECT rm_zone, pf_zone FROM " . TABLE_SHIPPING_RM_ZONES
      . " WHERE iso2 = '" . db_input(strtoupper((string)$iso2)) . "'");
    return $result->EOF ? false : $result->fields;
  }

  /**
   * Sends the zones held as a CSV download, in the shape read_file() reads.
   */
  static function export() {
    $rows = self::all();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rm_zones.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('iso2', 'country', 'rm_zone', 'pf_zone', 'needs_confirmation', 'notes'), ',', '"', '');
    foreach ($rows as $row) {
      fputcsv($out, array($row['iso2'], $row['country'], $row['rm_zone'], $row['pf_zone'],
        $row['needs_confirmation'] === '1' ? 'Y' : 'N', $row['notes']), ',', '"', '');
    }
    fclose($out);
  }
}
?>
