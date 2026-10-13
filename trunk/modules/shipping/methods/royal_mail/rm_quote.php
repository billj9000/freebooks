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
//  Path: /modules/shipping/methods/royal_mail/rm_quote.php
//
// Prices a shipment from the imported price list. Each parcel is priced on its
// own and the shipment's price is their sum:
//   1. the format is the smallest one the parcel's size fits that has a band for
//      its weight, so a light parcel too big for a Small Parcel is a Medium Parcel;
//   2. a band for the destination country itself is used before one for its zone;
//   3. the band covers weights above weight_from_g up to weight_to_g, and where
//      several bands match (Special Delivery cover levels, service codes) the
//      cheapest is taken;
//   4. a service with no band for every parcel is not offered - a price is never
//      extrapolated.
// Cost is the price before VAT plus the configured surcharges, which apply to
// account and contract prices only; the quote is the cost plus the handling charge.

class rm_quote {

  const RM = 'Royal Mail';
  const PF = 'Parcelforce Worldwide';

  // Click & Drop service => the price list services that price it, in the order
  // tried: carrier, service, price channel.
  const SERVICES = array(
    'CRL24' => array(array(self::RM, 'Royal Mail 24', 'account')),
    'CRL48' => array(array(self::RM, 'Royal Mail 48', 'account')),
    'RM24'  => array(array(self::RM, 'Royal Mail Tracked 24', 'online')),
    'RM48'  => array(array(self::RM, 'Royal Mail Tracked 48', 'online')),
    'RMSD9' => array(array(self::RM, 'Special Delivery Guaranteed by 9am', 'account')),
    'RMSD1' => array(array(self::RM, 'Special Delivery Guaranteed by 1pm', 'account'),
                     array(self::RM, 'Special Delivery Guaranteed by 1pm', 'online')),
    'RM1CL' => array(array(self::RM, 'Signed For 1st Class', 'account'),
                     array(self::RM, 'Signed For 1st Class', 'online')),
    'RM2CL' => array(array(self::RM, 'Signed For 2nd Class', 'account'),
                     array(self::RM, 'Signed For 2nd Class', 'online')),
    'NDA'   => array(array(self::PF, 'express24', 'contract'), array(self::PF, 'express24', 'online')),
    'FE0'   => array(array(self::PF, 'express48', 'contract'), array(self::PF, 'express48', 'online')),
    'FEM'   => array(array(self::PF, 'express48 Large', 'contract'), array(self::PF, 'express48large', 'online')),
    'RMIT'  => array(array(self::RM, 'International Tracked', 'account'),
                     array(self::RM, 'International Tracked', 'online')),
    'RMITS' => array(array(self::RM, 'International Tracked & Signed', 'account'),
                     array(self::RM, 'International Tracked & Signed', 'online')),
    'RMIE'  => array(array(self::RM, 'International Standard', 'account'),
                     array(self::RM, 'International Standard', 'online')),
    'RMIPT' => array(array(self::RM, 'International Tracked', 'account'),
                     array(self::RM, 'International Tracked', 'online')),
    'ER1'   => array(array(self::PF, 'Global Priority Europe', 'contract'),
                     array(self::PF, 'globalpriority Europe', 'online')),
    'ECA'   => array(array(self::PF, 'Global Priority RoW', 'contract'),
                     array(self::PF, 'globalpriority ROW', 'online')),
  );

  // Size limits in mm (longest, middle, shortest side), the largest of a format.
  // An international Royal Mail parcel is limited by its length and its three
  // sides together instead; a Parcelforce parcel by its length and its length
  // plus girth. Weight limits come from the bands.
  const RM_SIZES = array(
    'Letter'        => array(240, 165, 5),
    'Large Letter'  => array(353, 250, 25),
    'Small Parcel'  => array(450, 350, 160),
    'Medium Parcel' => array(610, 460, 460),
  );
  const RM_INTL_PARCEL = array(600, 900);   // longest side, sum of sides
  const PF_PARCEL      = array(1500, 3000); // longest side, length plus girth
  const PF_LARGE       = array(2500, 5000); // express48 Large
  // Formats tried from the smallest. "Parcel" is a band for any parcel size the
  // service takes, and "Any" a band for any item.
  const FORMAT_ORDER = array('Letter', 'Large Letter', 'Small Parcel', 'Medium Parcel', 'Parcel', 'Any');
  // A price list format => the Click & Drop package formats it can be sent as.
  const CD_FORMATS = array(
    'Letter' => array('letter'), 'Large Letter' => array('largeLetter'), 'Small Parcel' => array('smallParcel'),
    'Medium Parcel' => array('mediumParcel'), 'Parcel' => array('smallParcel', 'mediumParcel', 'largeParcel', 'parcel'),
  );
  // Destination names in the price list that are not FreeBooks country names.
  const ALIASES = array(
    'usa' => 'US', 'united states of america' => 'US', 'south korea' => 'KR', 'ireland' => 'IE',
    'republic of ireland' => 'IE', 'czechia' => 'CZ', 'türkiye' => 'TR', 'turkey' => 'TR',
    'russia' => 'RU', 'vietnam' => 'VN', 'laos' => 'LA', 'taiwan' => 'TW', 'macau' => 'MO',
  );

  public $effective_from = '';
  public $notices        = array();
  private $iso2          = '';
  private $zones         = false;
  private $names         = null;
  private $rows          = array();

  /**
   * Prices $parcels (each array('weight_g', 'dims_mm' => [l, w, d] or null)) to
   * country $iso2 for every service. Returns code => array('cost', 'cost_inc_vat',
   * 'note') for the services that can carry them all.
   */
  function price_all($parcels, $iso2) {
    $this->iso2  = strtoupper((string)$iso2);
    $this->zones = rm_zones::zones_for($this->iso2);
    $this->effective_from = rm_rates::current_version();
    if ($this->effective_from === '') { $this->notices[] = RM_QUOTE_NO_PRICE_LIST; return array(); }
    if (!$this->zones)                { $this->notices[] = sprintf(RM_QUOTE_NO_ZONE, $this->iso2); return array(); }
    $out = array();
    foreach (self::SERVICES as $code => $choices) {
      $priced = $this->price_service($code, $choices, $parcels);
      if ($priced) $out[$code] = $priced;
    }
    return $out;
  }

  // The first price channel that prices every parcel, with its surcharge added.
  private function price_service($code, $choices, $parcels) {
    foreach ($choices as $choice) {
      list($carrier, $service, $channel) = $choice;
      $total = 0; $total_inc = 0; $formats = array(); $confirm = false;
      foreach ($parcels as $parcel) {
        $band = $this->find_band($code, $carrier, $service, $channel, $parcel);
        if (!$band) continue 2;
        $total     += (float)$band['price_ex_vat'];
        $total_inc += (float)$band['price_inc_vat'];
        $formats[]  = $band['format'];
        if ($band['needs_confirmation'] === '1') $confirm = true;
      }
      $pct = self::surcharge_pct($carrier, $channel, $this->zones['rm_zone'] !== 'UK');
      $rate = 1 + $pct / 100;
      $note = implode(', ', array_unique($formats)) . ' - ' . $channel;
      if ($pct)     $note .= ' + ' . (float)$pct . '%';
      if ($confirm) $note .= ' - ' . RM_QUOTE_TO_CONFIRM;
      return array('cost' => round($total * $rate, 2), 'cost_inc_vat' => round($total_inc * $rate, 2), 'note' => $note);
    }
    return false;
  }

  /**
   * Account and contract prices carry the surcharges; online prices include them.
   * Each is a percentage setting, so a change to a surcharge or a peak surcharge
   * is a setting change.
   */
  static function surcharge_pct($carrier, $channel, $international) {
    if (!in_array($channel, array('account', 'contract'), true)) return 0;
    $get = function ($key) { return defined($key) && is_numeric(constant($key)) ? (float)constant($key) : 0; };
    if ($carrier === self::PF) return $get('MODULE_SHIPPING_ROYAL_MAIL_PF_SURCHARGE');
    return $get('MODULE_SHIPPING_ROYAL_MAIL_SURCHARGE') + ($international ? $get('MODULE_SHIPPING_ROYAL_MAIL_INTL_SURCHARGE') : 0);
  }

  private function find_band($code, $carrier, $service, $channel, $parcel) {
    $rows = $this->service_rows($carrier, $service, $channel);
    if (!$rows) return false;
    $allowed = royal_mail::allowedFormats($code);
    foreach (self::FORMAT_ORDER as $format) {
      if (isset(self::CD_FORMATS[$format]) && !array_intersect(self::CD_FORMATS[$format], $allowed)) continue;
      if (!$this->fits($format, $carrier, $service, $parcel)) continue;
      $candidates = array_filter($rows, function ($r) use ($format) { return $r['format'] === $format; });
      $country = array_filter($candidates, function ($r) { return $r['dest_iso2'] === $this->iso2; });
      $usable  = $country ?: array_filter($candidates, function ($r) { return $r['dest_iso2'] === '' && $this->zone_matches($r['zone']); });
      $best = false;
      foreach ($usable as $r) {
        if (!self::weight_in_band($parcel['weight_g'], $r)) continue;
        if (!$best || (float)$r['price_ex_vat'] < (float)$best['price_ex_vat']) $best = $r;
      }
      if ($best) return $best;
    }
    return false;
  }

  static function weight_in_band($weight_g, $row) {
    switch ($row['rate_type']) {
      case 'band': return $weight_g > (int)$row['weight_from_g'] && $weight_g <= (int)$row['weight_to_g'];
      case 'flat': return $weight_g > 0 && $weight_g <= (int)$row['weight_to_g'];
      default:     return false; // increment rows price Printed Papers only
    }
  }

  // Whether a parcel can go as $format. A parcel whose size is not known can go
  // as any parcel format but not as a letter.
  private function fits($format, $carrier, $service, $parcel) {
    $dims = $parcel['dims_mm'];
    if ($format === 'Any') return true;
    if (!$dims) return !in_array($format, array('Letter', 'Large Letter'), true);
    rsort($dims);
    if ($carrier === self::PF) {
      list($max_len, $max_girth) = (stripos($service, 'large') !== false) ? self::PF_LARGE : self::PF_PARCEL;
      return $dims[0] <= $max_len && $dims[0] + 2 * ($dims[1] + $dims[2]) <= $max_girth;
    }
    $international = $this->zones['rm_zone'] !== 'UK';
    if ($international && in_array($format, array('Small Parcel', 'Parcel'), true)) {
      return $dims[0] <= self::RM_INTL_PARCEL[0] && array_sum($dims) <= self::RM_INTL_PARCEL[1];
    }
    $limit = self::RM_SIZES[$format === 'Parcel' ? 'Medium Parcel' : $format] ?? null;
    return $limit && $dims[0] <= $limit[0] && $dims[1] <= $limit[1] && $dims[2] <= $limit[2];
  }

  // Whether a band for zone $zone covers the destination country.
  private function zone_matches($zone) {
    $rm = $this->zones['rm_zone'];
    if ($zone === $rm || $zone === $this->zones['pf_zone']) return true;
    if ($zone === 'Europe Zones 1/2/3') return strpos($rm, 'Europe Zone') === 0;
    if ($zone === 'World Zones 1/2/3' || $zone === 'Rest of World (outside Europe)') return strpos($rm, 'World Zone') === 0;
    return false;
  }

  // The bands of one service and channel in the list in force, each with the
  // country its destination names ('' for a zone or a group of countries).
  private function service_rows($carrier, $service, $channel) {
    global $db;
    $key = "$carrier|$service|$channel";
    if (isset($this->rows[$key])) return $this->rows[$key];
    $rows = array();
    $result = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_RM_RATES
      . " WHERE effective_from = '" . db_input($this->effective_from) . "' AND carrier = '" . db_input($carrier)
      . "' AND service = '" . db_input($service) . "' AND price_channel = '" . db_input($channel) . "'");
    while (!$result->EOF) {
      $row = $result->fields;
      $row['dest_iso2'] = $this->country_of($row['destination']);
      $rows[] = $row;
      $result->MoveNext();
    }
    return $this->rows[$key] = $rows;
  }

  private function country_of($destination) {
    if ($this->names === null) {
      $this->names = array();
      foreach (rm_zones::country_names() as $iso2 => $name) $this->names[mb_strtolower($name)] = $iso2;
    }
    $name = mb_strtolower(trim($destination));
    return self::ALIASES[$name] ?? $this->names[$name] ?? '';
  }
}
?>
