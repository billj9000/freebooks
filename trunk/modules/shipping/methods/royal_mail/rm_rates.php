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
//  Path: /modules/shipping/methods/royal_mail/rm_rates.php
//
// The Royal Mail and Parcelforce price list: reading a rate file, checking it,
// and keeping one set of bands per effective_from date. Click & Drop has no rates
// API, so the estimator prices from these bands.
//
// A rate file is a CSV with a header row, one row per price band, in GBP. Columns
// are matched by name, so their order does not matter and extra columns are ignored.
// Each company imports its own file: it can hold contract prices, so none is shipped.

class rm_rates {

  const CHANNELS   = array('contract', 'account', 'online', 'mailmark_franking', 'stamp');
  const RATE_TYPES = array('band', 'flat', 'increment');
  const VAT_TYPES  = array('exempt', 'plus_vat_20', 'inc_vat_20', 'zero_rated');
  const REQUIRED   = array('carrier', 'service', 'price_channel', 'format', 'destination', 'zone',
                           'weight_from_g', 'weight_to_g', 'rate_type', 'price_gbp', 'vat_treatment',
                           'effective_from');
  // The columns kept as text, named alike in the file and the table, with their widths.
  const TEXT_COLUMNS = array(
    'carrier' => 32, 'service_group' => 64, 'service' => 96, 'service_code' => 32,
    'price_channel' => 20, 'format' => 20, 'destination' => 96, 'zone' => 48,
    'rate_type' => 12, 'vat_treatment' => 12, 'source' => 128, 'source_page' => 16, 'notes' => 255,
  );
  // The bands of one key must not overlap; weight_to_g completes the natural key.
  const BAND_KEY = array('carrier', 'service', 'service_code', 'price_channel', 'format',
                         'destination', 'zone', 'compensation', 'rate_type');
  // Most problems listed for one file; the rest are counted.
  const MAX_ERRORS = 50;

  public $errors      = array();
  public $error_count = 0;

  /**
   * Reads and checks a rate file. Returns array('effective_from' => Y-m-d, 'rows' => [...])
   * with every row ready to store, or false with the problems in $this->errors.
   */
  function read_file($path) {
    $this->errors      = array();
    $this->error_count = 0;
    $fh = @fopen($path, 'r');
    if (!$fh) { $this->error(RM_RATES_ERR_READ); return false; }
    $header = fgetcsv($fh, 0, ',', '"', '');
    if (!$header) { fclose($fh); $this->error(RM_RATES_ERR_EMPTY); return false; }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $cols = array();
    foreach ($header as $i => $name) $cols[strtolower(trim($name))] = $i;
    $missing = array_diff(self::REQUIRED, array_keys($cols));
    if ($missing) { fclose($fh); $this->error(sprintf(RM_RATES_ERR_COLUMNS, implode(', ', $missing))); return false; }

    $rows  = array();
    $dates = array();
    $line  = 1;
    while (($data = fgetcsv($fh, 0, ',', '"', '')) !== false) {
      $line++;
      if ($data === array(null) || trim(implode('', $data)) === '') continue;
      $get = function ($name) use ($cols, $data) { return isset($cols[$name]) ? trim((string)($data[$cols[$name]] ?? '')) : ''; };
      $row = $this->read_row($get, $line);
      if ($row === false) continue;
      $rows[] = $row;
      $dates[$row['effective_from']] = true;
    }
    fclose($fh);
    if (!$rows && !$this->error_count) $this->error(RM_RATES_ERR_EMPTY);
    if (count($dates) > 1) $this->error(sprintf(RM_RATES_ERR_DATES, implode(', ', array_keys($dates))));
    $this->check_overlaps($rows);
    if ($this->error_count) return false;
    return array('effective_from' => array_key_first($dates), 'rows' => $rows);
  }

  // One file row as a table row, or false when it has a problem.
  private function read_row($get, $line) {
    $row = array();
    $ok  = true;
    foreach (self::TEXT_COLUMNS as $col => $len) {
      $row[$col] = $get($col);
      if (mb_strlen($row[$col]) > $len) { $this->error(sprintf(RM_RATES_ERR_LONG, $line, $col, $len)); $ok = false; }
    }
    foreach (array('carrier', 'service', 'format', 'destination') as $col) {
      if ($row[$col] === '') { $this->error(sprintf(RM_RATES_ERR_BLANK, $line, $col)); $ok = false; }
    }
    if (!in_array($row['price_channel'], self::CHANNELS, true)) { $this->error(sprintf(RM_RATES_ERR_VALUE, $line, 'price_channel', $row['price_channel'])); $ok = false; }
    if (!in_array($row['rate_type'], self::RATE_TYPES, true))   { $this->error(sprintf(RM_RATES_ERR_VALUE, $line, 'rate_type', $row['rate_type'])); $ok = false; }
    if (!in_array($row['vat_treatment'], self::VAT_TYPES, true)) { $this->error(sprintf(RM_RATES_ERR_VALUE, $line, 'vat_treatment', $row['vat_treatment'])); $ok = false; }

    $date = $get('effective_from');
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) { $this->error(sprintf(RM_RATES_ERR_VALUE, $line, 'effective_from', $date)); $ok = false; }
    $row['effective_from'] = $date;

    $num = array();
    foreach (array('weight_from_g', 'weight_to_g', 'increment_g', 'price_gbp', 'compensation_gbp', 'base_price_gbp', 'discount_pct') as $col) {
      $v = $get($col);
      if ($v === '' && !in_array($col, self::REQUIRED, true)) { $num[$col] = 0; continue; }
      if (!is_numeric($v) || (float)$v < 0) { $this->error(sprintf(RM_RATES_ERR_NUMBER, $line, $col, $v)); $ok = false; $num[$col] = 0; continue; }
      $num[$col] = (float)$v;
    }
    foreach (array('weight_from_g', 'weight_to_g', 'increment_g') as $col) {
      if ($num[$col] != floor($num[$col])) { $this->error(sprintf(RM_RATES_ERR_NUMBER, $line, $col, $get($col))); $ok = false; }
    }
    if ($num['weight_from_g'] >= $num['weight_to_g']) { $this->error(sprintf(RM_RATES_ERR_WEIGHTS, $line)); $ok = false; }
    if ($num['price_gbp'] <= 0) { $this->error(sprintf(RM_RATES_ERR_PRICE, $line)); $ok = false; }
    if ($row['rate_type'] === 'increment' && $num['increment_g'] <= 0) { $this->error(sprintf(RM_RATES_ERR_INCREMENT, $line)); $ok = false; }
    if ($num['discount_pct'] >= 1) { $this->error(sprintf(RM_RATES_ERR_NUMBER, $line, 'discount_pct', $get('discount_pct'))); $ok = false; }
    if (!$ok) return false;

    list($ex, $inc) = self::vat_prices($num['price_gbp'], $row['vat_treatment']);
    // The file's own ex- and inc-VAT columns, where present, must agree with its price.
    foreach (array('price_ex_vat_gbp' => $ex, 'price_inc_vat_gbp' => $inc) as $col => $expect) {
      $v = $get($col);
      if ($v !== '' && (!is_numeric($v) || abs((float)$v - $expect) > 0.005)) {
        $this->error(sprintf(RM_RATES_ERR_VAT, $line, $col, $v, number_format($expect, 2, '.', '')));
        return false;
      }
    }
    $row['weight_from_g']      = (int)$num['weight_from_g'];
    $row['weight_to_g']        = (int)$num['weight_to_g'];
    $row['increment_g']        = (int)$num['increment_g'];
    $row['price']              = round($num['price_gbp'], 2);
    $row['price_ex_vat']       = $ex;
    $row['price_inc_vat']      = $inc;
    $row['compensation']       = round($num['compensation_gbp'], 2);
    $row['base_price']         = round($num['base_price_gbp'], 2);
    $row['discount_pct']       = round($num['discount_pct'], 4);
    $row['is_contract_rate']   = strtoupper($get('is_contract_rate')) === 'Y' ? '1' : '0';
    $row['needs_confirmation'] = strtoupper($get('needs_confirmation')) === 'Y' ? '1' : '0';
    return $row;
  }

  /**
   * A price's ex-VAT and inc-VAT figures, from the price as published and how VAT
   * applies to it. UK VAT on postage is 20% or nothing.
   */
  static function vat_prices($price, $vat_treatment) {
    $price = round((float)$price, 2);
    switch ($vat_treatment) {
      case 'plus_vat_20': return array($price, round($price * 1.2, 2));
      case 'inc_vat_20':  return array(round($price / 1.2, 2), $price);
      default:            return array($price, $price);
    }
  }

  // A band covers weights above weight_from_g up to and including weight_to_g.
  private function check_overlaps($rows) {
    $groups = array();
    foreach ($rows as $row) {
      $key = array();
      foreach (self::BAND_KEY as $col) $key[] = $row[$col];
      $groups[implode("\x1F", $key)][] = $row;
    }
    foreach ($groups as $bands) {
      usort($bands, function ($a, $b) { return $a['weight_from_g'] <=> $b['weight_from_g'] ?: $a['weight_to_g'] <=> $b['weight_to_g']; });
      for ($i = 1; $i < count($bands); $i++) {
        if ($bands[$i]['weight_from_g'] < $bands[$i - 1]['weight_to_g']) {
          $b = $bands[$i];
          $this->error(sprintf(RM_RATES_ERR_OVERLAP, $b['service'], $b['price_channel'], $b['format'], $b['destination'],
            $bands[$i - 1]['weight_from_g'], $bands[$i - 1]['weight_to_g'], $b['weight_from_g'], $b['weight_to_g']));
        }
      }
    }
  }

  private function error($text) {
    $this->error_count++;
    if (count($this->errors) < self::MAX_ERRORS) $this->errors[] = $text;
  }

  /**
   * Rows per carrier, service and price channel, for showing what a file holds.
   */
  static function summarise($rows) {
    $summary = array();
    foreach ($rows as $row) {
      $key = $row['carrier'] . "\x1F" . $row['service'] . "\x1F" . $row['price_channel'];
      if (!isset($summary[$key])) {
        $summary[$key] = array('carrier' => $row['carrier'], 'service' => $row['service'],
          'price_channel' => $row['price_channel'], 'rows' => 0, 'confirm' => 0);
      }
      $summary[$key]['rows']++;
      if ($row['needs_confirmation'] === '1') $summary[$key]['confirm']++;
    }
    ksort($summary);
    return array_values($summary);
  }

  /**
   * Stores a file's rows as the set for its effective_from date, in place of any
   * set already stored for that date. Other dates are kept.
   */
  static function replace_version($effective_from, $rows) {
    global $db;
    shipping_ensure_schema();
    $cols = array_merge(array('effective_from'), array_keys(self::TEXT_COLUMNS),
      array('weight_from_g', 'weight_to_g', 'increment_g', 'price', 'price_ex_vat', 'price_inc_vat',
            'compensation', 'base_price', 'discount_pct', 'is_contract_rate', 'needs_confirmation'));
    $db->transStart();
    $db->Execute("DELETE FROM " . TABLE_SHIPPING_RM_RATES . " WHERE effective_from = '" . db_input($effective_from) . "'");
    foreach (array_chunk($rows, 200) as $chunk) {
      $values = array();
      foreach ($chunk as $row) {
        $vals = array();
        foreach ($cols as $col) $vals[] = "'" . db_input((string)$row[$col]) . "'";
        $values[] = '(' . implode(',', $vals) . ')';
      }
      $db->Execute("INSERT INTO " . TABLE_SHIPPING_RM_RATES . " (" . implode(',', $cols) . ") VALUES " . implode(',', $values));
    }
    $db->transCommit();
  }

  /**
   * The stored sets, newest first: effective_from, row count and how many rows
   * are flagged for confirmation.
   */
  static function versions() {
    global $db;
    shipping_ensure_schema();
    $versions = array();
    $result = $db->Execute("SELECT effective_from, COUNT(*) AS num_rows, SUM(needs_confirmation = '1') AS confirm
      FROM " . TABLE_SHIPPING_RM_RATES . " GROUP BY effective_from ORDER BY effective_from DESC");
    while (!$result->EOF) {
      $versions[] = $result->fields;
      $result->MoveNext();
    }
    return $versions;
  }

  /**
   * The set in force on $date (Y-m-d): the newest one that has taken effect, or
   * '' when none has.
   */
  static function current_version($date = '') {
    global $db;
    shipping_ensure_schema();
    if ($date === '') $date = date('Y-m-d');
    $result = $db->Execute("SELECT MAX(effective_from) AS effective_from FROM " . TABLE_SHIPPING_RM_RATES
      . " WHERE effective_from <= '" . db_input($date) . "'");
    return (string)($result->fields['effective_from'] ?? '');
  }

  static function delete_version($effective_from) {
    global $db;
    shipping_ensure_schema();
    $db->Execute("DELETE FROM " . TABLE_SHIPPING_RM_RATES . " WHERE effective_from = '" . db_input($effective_from) . "'");
  }
}
?>
