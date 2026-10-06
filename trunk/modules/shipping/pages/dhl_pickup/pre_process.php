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
//  Path: /modules/shipping/pages/dhl_pickup/pre_process.php
//
$security_level = validate_user(SECURITY_ID_SHIPPING_MANAGER);
load_method_language(DIR_FS_MODULES . 'shipping/methods/', 'dhl_express');
load_specific_method('shipping', 'dhl_express');
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/shipping.php');
shipping_ensure_pickup_schema();

// The day shown: its labels are the ones offered for collection, and a booking
// is made for it. DHL takes bookings up to 10 days ahead.
$today    = date('Y-m-d');
$last_day = date('Y-m-d', mktime(0, 0, 0, (int)date('n'), (int)date('j') + 10, (int)date('Y')));
$date     = (string)($_POST['pickup_date'] ?? $_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = $today;
$page_url = html_href_link(FILENAME_DEFAULT, 'module=shipping&page=dhl_pickup&date=' . $date, 'SSL');

$setting = function ($key, $fallback = '') {
  $name = 'MODULE_SHIPPING_DHL_EXPRESS_PICKUP_' . $key;
  return (defined($name) && constant($name) !== '') ? constant($name) : $fallback;
};
$form = array(
  'ready'         => $setting('READY', '12:00'),
  'close'         => $setting('CLOSE', '17:00'),
  'location'      => $setting('LOCATION'),
  'location_type' => 'business',
  'contact'       => defined('AR_CONTACT_NAME') ? AR_CONTACT_NAME : '',
  'phone'         => $setting('PHONE', defined('COMPANY_TELEPHONE1') ? COMPANY_TELEPHONE1 : ''),
  'instructions'  => '',
);
$cancel_reasons = array(
  'not_needed' => DHL_PICKUP_REASON_NOT_NEEDED,
  'error'      => DHL_PICKUP_REASON_ERROR,
  'rebook'     => DHL_PICKUP_REASON_REBOOK,
);

// The day's DHL shipments, one entry per shipment. The package list is stored on
// the shipment's first log row; labels printed without one have it typed in here.
$shipments = array();
$rows = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_LOG . " WHERE carrier = 'dhl_express'"
  . " AND DATE(ship_date) = '" . db_input($date) . "' ORDER BY shipment_id, id");
while (!$rows->EOF) {
  $f   = $rows->fields;
  $sid = (int)$f['shipment_id'];
  if (!isset($shipments[$sid])) {
    $data = json_decode((string)($f['package_data'] ?? ''), true);
    $shipments[$sid] = array(
      'log_id'   => (int)$f['id'],
      'ref_id'   => $f['ref_id'],
      'method'   => $f['method'],
      'tracking' => $f['tracking_id'],
      'data'     => (is_array($data) && !empty($data['packages'])) ? $data : null,
      'booking'  => '',
    );
  }
  $rows->MoveNext();
}
$company_postcode = strtoupper(str_replace(' ', '', defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : ''));
foreach ($shipments as $sid => $s) {
  $from = strtoupper(str_replace(' ', '', (string)($s['data']['shipper_postcode'] ?? '')));
  $shipments[$sid]['elsewhere'] = ($from !== '' && $company_postcode !== '' && $from !== $company_postcode)
    ? $s['data']['shipper_postcode'] : '';
}

// Collections for the day; a shipment already in a live booking says so.
$pickups = array();
$rows = $db->Execute("SELECT * FROM " . TABLE_SHIPPING_PICKUPS . " WHERE carrier = 'dhl_express'"
  . " AND pickup_date = '" . db_input($date) . "' ORDER BY id");
while (!$rows->EOF) {
  $pickups[] = $rows->fields;
  if ($rows->fields['status'] === 'booked') {
    foreach (explode(',', $rows->fields['shipment_ids']) as $sid) {
      if (isset($shipments[(int)$sid])) $shipments[(int)$sid]['booking'] = $rows->fields['confirmation'];
    }
  }
  $rows->MoveNext();
}

$ticked = array();
foreach ($shipments as $sid => $s) {
  if ($s['booking'] === '' && $s['elsewhere'] === '') $ticked[$sid] = true;
}

switch ($_REQUEST['action'] ?? '') {
  case 'book':
    validate_security($security_level, 2);
    foreach (array_keys($form) as $key) $form[$key] = trim((string)($_POST[$key] ?? ''));
    $ticked = array();
    foreach ((array)($_POST['ship'] ?? array()) as $sid) {
      if (isset($shipments[(int)$sid])) $ticked[(int)$sid] = true;
    }
    $time_ok = function ($t) { return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t); };
    $lines   = array_values(array_filter(array_map('trim', preg_split('/\R/', $form['instructions'])), 'strlen'));
    $errors  = array();
    if ($date < $today || $date > $last_day) $errors[] = DHL_PICKUP_ERR_DATE;
    if (!$time_ok($form['ready']) || ($form['close'] !== '' && (!$time_ok($form['close']) || $form['close'] <= $form['ready']))) {
      $errors[] = DHL_PICKUP_ERR_TIME;
    }
    if ($form['contact'] === '' || $form['phone'] === '') $errors[] = DHL_PICKUP_ERR_CONTACT;
    $too_long = mb_strlen($form['location']) > 80 || count($lines) > 4;
    foreach ($lines as $line) if (mb_strlen($line) > 80) $too_long = true;
    if ($too_long) $errors[] = DHL_PICKUP_ERR_TEXT;
    if (!in_array($form['location_type'], array('business', 'residence'), true)) $form['location_type'] = 'business';
    if (!$ticked) $errors[] = DHL_PICKUP_ERR_NONE;

    $request = array();
    $typed   = array();
    foreach (array_keys($ticked) as $sid) {
      $s = $shipments[$sid];
      if ($s['data']) {
        $data = $s['data'];
      } else {
        $count = (int)($_POST['n_' . $sid] ?? 0);
        $pkg   = array(
          'weight' => round((float)($_POST['wt_'  . $sid] ?? 0), 3),
          'length' => (int)($_POST['len_' . $sid] ?? 0),
          'width'  => (int)($_POST['wid_' . $sid] ?? 0),
          'height' => (int)($_POST['hgt_' . $sid] ?? 0),
        );
        $data = array(
          'customs'  => !empty($_POST['cust_' . $sid]),
          'packages' => ($count > 0 && $count <= 99) ? array_fill(0, $count, $pkg) : array(),
        );
        $typed[$s['log_id']] = $data;
      }
      $complete = !empty($data['packages']);
      foreach ($data['packages'] as $pkg) {
        if ($pkg['weight'] <= 0 || $pkg['length'] <= 0 || $pkg['width'] <= 0 || $pkg['height'] <= 0) $complete = false;
      }
      if (!$complete) { $errors[] = sprintf(DHL_PICKUP_ERR_PARCELS, $sid); continue; }
      $request[] = array(
        'product'  => (strncmp($s['method'], 'DHL_', 4) === 0) ? substr($s['method'], 4) : $s['method'],
        'customs'  => !empty($data['customs']),
        'tracking' => (string)$s['tracking'],
        'packages' => $data['packages'],
      );
    }
    if ($errors) {
      foreach ($errors as $msg) $messageStack->add($msg, 'error');
      break;
    }

    $dhl    = new dhl_express();
    $result = $dhl->bookPickup(array(
      'date'          => $date,
      'ready'         => $form['ready'],
      'close'         => $form['close'],
      'location'      => $form['location'],
      'location_type' => $form['location_type'],
      'instructions'  => $lines,
      'contact'       => $form['contact'],
      'phone'         => $form['phone'],
      'shipments'     => $request,
    ));
    if ($result === false) break;
    // Figures typed for a label that had none are kept, so the next booking has them.
    foreach ($typed as $log_id => $data) {
      db_perform(TABLE_SHIPPING_LOG, array('package_data' => json_encode($data)), 'update', 'id = ' . (int)$log_id);
    }
    $confirmation = implode(',', $result['numbers']);
    db_perform(TABLE_SHIPPING_PICKUPS, array(
      'carrier'      => 'dhl_express',
      'confirmation' => $confirmation,
      'pickup_date'  => $date,
      'ready_time'   => $form['ready'],
      'close_time'   => $form['close'],
      'location'     => $form['location'],
      'shipment_ids' => implode(',', array_keys($ticked)),
      'status'       => 'booked',
      'booked_by'    => (string)($_SESSION['display_name'] ?? ''),
      'booked_at'    => date('Y-m-d H:i:s'),
    ), 'insert');
    gen_add_audit_log(DHL_PICKUP_LOG_BOOKED, $confirmation);
    $messageStack->add(sprintf(DHL_PICKUP_BOOKED, $confirmation), 'success');
    foreach ($result['warnings'] as $warning) $messageStack->add(sprintf(DHL_PICKUP_WARNING, $warning), 'caution');
    gen_redirect($page_url);
    break;

  case 'cancel':
    validate_security($security_level, 2);
    $pickup = null;
    foreach ($pickups as $p) {
      if ((int)$p['id'] === (int)($_POST['pickup_id'] ?? 0) && $p['status'] === 'booked') $pickup = $p;
    }
    if (!$pickup) break;
    $reason    = $cancel_reasons[$_POST['reason_' . $pickup['id']] ?? ''] ?? DHL_PICKUP_REASON_NOT_NEEDED;
    $requestor = (string)($_SESSION['display_name'] ?? '') ?: (defined('AR_CONTACT_NAME') ? AR_CONTACT_NAME : COMPANY_NAME);
    $dhl       = new dhl_express();
    foreach (explode(',', $pickup['confirmation']) as $number) {
      if (!$dhl->cancelPickup($number, $requestor, $reason)) break 2;
    }
    db_perform(TABLE_SHIPPING_PICKUPS, array('status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s')),
      'update', 'id = ' . (int)$pickup['id']);
    gen_add_audit_log(DHL_PICKUP_LOG_CANCELLED, $pickup['confirmation']);
    $messageStack->add(sprintf(DHL_PICKUP_CANCELLED, $pickup['confirmation']), 'success');
    gen_redirect($page_url);
    break;
}

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', DHL_PICKUP_TITLE);
?>
