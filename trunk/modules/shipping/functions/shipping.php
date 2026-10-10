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
//  Path: /modules/shipping/functions/shipping.php
//

/**
 * True for a shipping method that exists (methods/<name>/<name>.php) and, unless
 * $installed is false, is installed. A method name from the request or from a
 * stored record is required and instantiated as a class, so nothing else may pass.
 */
function shipping_method_is_valid($name, $installed = true) {
  if (!is_string($name) || !preg_match('/^[a-z0-9_]+$/', $name)) return false;
  if (!file_exists(DIR_FS_MODULES . 'shipping/methods/' . $name . '/' . $name . '.php')) return false;
  return !$installed || defined('MODULE_SHIPPING_' . strtoupper($name) . '_STATUS');
}

/**
 * The company as the sender of a shipment, as sender_* fields: its name, address and contact
 * details from the company settings, and its VAT and EORI numbers from the first active branch
 * contact, else from the shipping settings.
 */
function shipping_company_sender() {
  global $db;
  $c = function ($name) { return defined($name) ? constant($name) : ''; };
  $branch = $db->Execute("SELECT vat_number, eori_number FROM " . TABLE_CONTACTS
    . " WHERE type = 'b' AND inactive = '0' ORDER BY id ASC LIMIT 1");
  $vat  = $branch->EOF ? '' : (string)($branch->fields['vat_number']  ?? '');
  $eori = $branch->EOF ? '' : (string)($branch->fields['eori_number'] ?? '');
  return array(
    'sender_primary_name'   => $c('COMPANY_NAME'),
    'sender_contact'        => $c('AR_CONTACT_NAME'),
    'sender_address1'       => $c('COMPANY_ADDRESS1'),
    'sender_address2'       => $c('COMPANY_ADDRESS2'),
    'sender_city_town'      => $c('COMPANY_CITY_TOWN'),
    'sender_state_province' => $c('COMPANY_ZONE'),
    'sender_postal_code'    => $c('COMPANY_POSTAL_CODE'),
    'sender_country_code'   => $c('COMPANY_COUNTRY'),
    'sender_telephone1'     => $c('COMPANY_TELEPHONE1'),
    'sender_email'          => $c('COMPANY_EMAIL'),
    'sender_vat_number'     => $vat  ?: $c('SHIPPING_SHIPPER_VAT'),
    'sender_eori_number'    => $eori ?: $c('SHIPPING_SHIPPER_EORI'),
  );
}

/**
 * The carrier's tracking page for a tracking number, or '' for a carrier that has
 * none. A shipping method offers one by defining <method>_TRACKING_URL (or the same
 * name in capitals), to which the number is appended. Spaces are dropped, as a
 * number typed by hand often has them and no carrier's page accepts them.
 */
function shipping_tracking_url($carrier, $tracking) {
  $tracking = str_replace(' ', '', trim((string)$tracking));
  if ($tracking === '' || !shipping_method_is_valid($carrier, false)) return '';
  foreach (array($carrier . '_TRACKING_URL', strtoupper($carrier) . '_TRACKING_URL') as $name) {
    if (!defined($name)) load_specific_method('shipping', $carrier);
    if (defined($name)) return html_entity_decode(constant($name), ENT_QUOTES, 'UTF-8') . rawurlencode($tracking);
  }
  return '';
}

/**
 * Creates the collections and shipment contents tables, and gives the shipping log
 * its package_data and order_id columns, on an installation that lacks them. The
 * tables come from the module's own declarations, the ones the installer uses, so
 * there is a single definition.
 */
function shipping_ensure_schema() {
  global $db;
  static $checked = false;
  if ($checked) return;
  $checked = true;
  if (!db_field_exists(TABLE_SHIPPING_LOG, 'package_data')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG . " ADD package_data mediumtext NULL AFTER carrier_order_id");
  }
  if (!db_field_exists(TABLE_SHIPPING_LOG, 'order_id')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG . " ADD order_id int(11) NOT NULL default '0' AFTER package_data, ADD KEY order_id (order_id)");
  }
  $install = null;
  foreach (array(TABLE_SHIPPING_PICKUPS, TABLE_SHIPPING_LOG_ITEMS) as $table) {
    if (db_table_exists($table)) continue;
    if (!$install) {
      require_once(DIR_FS_MODULES . 'shipping/classes/install.php');
      $install = new shipping_admin();
    }
    $db->Execute($install->tables[$table]);
  }
}

/**
 * What earlier shipments of order $order_id have recorded for each of its lines:
 * array(journal_item id => array('shipped' => qty, 'cancelled' => qty)).
 */
function shipping_line_progress($order_id) {
  global $db;
  $progress = array();
  if ((int)$order_id <= 0) return $progress;
  shipping_ensure_schema();
  $rows = $db->Execute("SELECT item_ref_id, status, SUM(qty) AS qty FROM " . TABLE_SHIPPING_LOG_ITEMS
    . " WHERE order_id = " . (int)$order_id . " GROUP BY item_ref_id, status");
  while (!$rows->EOF) {
    $id = (int)$rows->fields['item_ref_id'];
    if (!isset($progress[$id])) $progress[$id] = array('shipped' => 0.0, 'cancelled' => 0.0);
    $progress[$id][$rows->fields['status']] = (float)$rows->fields['qty'];
    $rows->MoveNext();
  }
  return $progress;
}

/**
 * Adds to each of an order's label lines (as the label form loads them, each with
 * 'id' and the order line's quantity in 'ordered') what earlier shipments recorded:
 * 'shipped', 'cancelled' and 'remaining', and sets 'qty' to what remains to send.
 */
function shipping_apply_progress($lines, $order_id) {
  $progress = shipping_line_progress($order_id);
  foreach ($lines as $i => $line) {
    $done = $progress[(int)($line['id'] ?? 0)] ?? array('shipped' => 0.0, 'cancelled' => 0.0);
    $lines[$i]['shipped']   = $done['shipped'];
    $lines[$i]['cancelled'] = $done['cancelled'];
    $lines[$i]['remaining'] = max(0, (float)$line['ordered'] - $done['shipped'] - $done['cancelled']);
    $lines[$i]['qty']       = $lines[$i]['remaining'];
  }
  return $lines;
}

/**
 * The contents lines a label form posted. Each line carries 'ordered', the quantity on
 * the order line it came from, and 'remaining', that less what earlier shipments sent or
 * cancelled; both are read from the order and its shipments, not from the form, when the
 * line belongs to order $order_id. A line ships anything from 0 (removed) to what remains,
 * and at least one line must ship. 'short' says what happens to the rest of a line sent
 * short: 'later' leaves it to follow, 'cancel' records it as never to be shipped; a line of
 * an order sent short must say which, as the form offers no default.
 * Returns array('lines' => ..., 'ok' => bool); the lines come back either way, so the
 * form can be shown again, and each refusal is on the message stack.
 */
function shipping_label_lines_from_post($order_id) {
  global $db, $messageStack;
  $num     = max(0, (int)($_POST['num_line_items'] ?? 0));
  $ids     = array();
  for ($i = 1; $i <= $num; $i++) if ((int)($_POST['li_id_' . $i] ?? 0) > 0) $ids[] = (int)$_POST['li_id_' . $i];
  $ordered = array();
  if ($ids && (int)$order_id > 0) {
    $rows = $db->Execute("SELECT id, qty FROM " . TABLE_JOURNAL_ITEM . " WHERE ref_id = " . (int)$order_id
      . " AND id IN (" . implode(',', $ids) . ")");
    while (!$rows->EOF) { $ordered[(int)$rows->fields['id']] = (float)$rows->fields['qty']; $rows->MoveNext(); }
  }
  $progress = $ordered ? shipping_line_progress($order_id) : array();
  $lines = array();
  $ok    = true;
  for ($i = 1; $i <= $num; $i++) {
    $sku = db_prepare_input($_POST['li_sku_' . $i] ?? '');
    if (!$sku) continue;
    $id     = (int)($_POST['li_id_' . $i] ?? 0);
    $posted = trim((string)($_POST['li_qty_' . $i] ?? ''));
    $qty    = is_numeric($posted) ? (float)$posted : -1;
    $done   = $progress[$id] ?? array('shipped' => 0.0, 'cancelled' => 0.0);
    if (isset($ordered[$id])) {
      $max = max(0, $ordered[$id] - $done['shipped'] - $done['cancelled']);
    } else {
      $max = null;
    }
    $bad_qty = $qty < 0 || ($max !== null && $qty > $max + 0.000001);
    if ($bad_qty) {
      $messageStack->add(sprintf(SHIPPING_ERROR_LINE_QTY, $sku, $max === null ? '-' : (string)$max), 'error');
      $ok  = false;
      $qty = max(0, $qty);
    }
    $remaining = $max ?? max($qty, (float)($_POST['li_ord_' . $i] ?? 0));
    $short     = in_array($_POST['li_short_' . $i] ?? '', array('later', 'cancel'), true) ? $_POST['li_short_' . $i] : '';
    if (!$bad_qty && $max !== null && $qty < $max - 0.000001 && $short === '') {
      $messageStack->add(sprintf(SHIPPING_ERROR_SHORT_CHOICE, $sku), 'error');
      $ok = false;
    }
    $lines[] = array(
      'id'          => $id,
      'sku'         => $sku,
      'description' => db_prepare_input($_POST['li_desc_'   . $i] ?? ''),
      'origin'      => db_prepare_input($_POST['li_origin_' . $i] ?? ''),
      'hs_code'     => db_prepare_input($_POST['li_hs_'     . $i] ?? ''),
      'part_number' => mb_substr(trim(db_prepare_input($_POST['li_mpn_'  . $i] ?? '')), 0, 35),
      'gtin'        => mb_substr(trim(db_prepare_input($_POST['li_gtin_' . $i] ?? '')), 0, 14),
      'qty'         => $qty,
      'ordered'     => $ordered[$id] ?? $remaining,
      'remaining'   => $remaining,
      'shipped'     => $done['shipped'],
      'cancelled'   => $done['cancelled'],
      'short'       => $short,
      'weight'      => (float)($_POST['li_wt_'  . $i] ?? 0),
      'value'       => (float)($_POST['li_val_' . $i] ?? 0),
    );
  }
  if ($ok && $lines && !array_filter($lines, function ($line) { return $line['qty'] > 0; })) {
    $messageStack->add(SHIPPING_ERROR_NOTHING_TO_SHIP, 'error');
    $ok = false;
  }
  return array('lines' => $lines, 'ok' => $ok);
}

/**
 * Records what shipment $shipment_id of order $order_id contained: a 'shipped' row for
 * each line sent, and a 'cancelled' row for the rest of a line sent short as never to be
 * shipped. A line left to follow records nothing, so it stays outstanding.
 */
function shipping_record_contents($shipment_id, $carrier, $order_id, $lines) {
  if ((int)$order_id <= 0) return;
  shipping_ensure_schema();
  $now = date('Y-m-d H:i:s');
  foreach ($lines as $line) {
    if ((int)($line['id'] ?? 0) <= 0) continue;
    $row = array('shipment_id' => (int)$shipment_id, 'carrier' => $carrier, 'order_id' => (int)$order_id,
      'item_ref_id' => (int)$line['id'], 'sku' => $line['sku'], 'created_at' => $now);
    if ($line['qty'] > 0) db_perform(TABLE_SHIPPING_LOG_ITEMS, $row + array('qty' => $line['qty'], 'status' => 'shipped'), 'insert');
    $short = (float)($line['remaining'] ?? $line['qty']) - $line['qty'];
    if ($short > 0.000001 && ($line['short'] ?? 'later') === 'cancel') {
      db_perform(TABLE_SHIPPING_LOG_ITEMS, $row + array('qty' => $short, 'status' => 'cancelled'), 'insert');
    }
  }
}

/**
 * Forgets what shipment $shipment_id contained once none of its labels is left in the
 * shipping log, so its lines are outstanding again.
 */
function shipping_forget_contents($shipment_id) {
  global $db;
  shipping_ensure_schema();
  $left = $db->Execute("SELECT COUNT(*) AS n FROM " . TABLE_SHIPPING_LOG . " WHERE shipment_id = " . (int)$shipment_id);
  if ((int)$left->fields['n'] === 0) {
    $db->Execute("DELETE FROM " . TABLE_SHIPPING_LOG_ITEMS . " WHERE shipment_id = " . (int)$shipment_id);
  }
}

/**
 * Where order $order_id, invoice or purchase number $ref, stands on shipping:
 * 'first_log_id', its earliest shipping log row, or 0 when it has no shipment;
 * 'recorded', whether any shipment recorded its contents; and 'outstanding', the
 * quantity still to send on lines that go in a parcel. A shipment that recorded no
 * contents (an older label, or a carrier whose label form does not record them)
 * counts as sending everything, so 'outstanding' is 0 unless 'recorded' is true.
 */
function shipping_order_state($order_id, $ref) {
  global $db;
  shipping_ensure_schema();
  $ref   = db_input((string)$ref);
  $first = $db->Execute("SELECT MIN(id) AS id FROM " . TABLE_SHIPPING_LOG . " WHERE order_id = " . (int)$order_id
    . ($ref !== '' ? " OR ref_id = '" . $ref . "' OR ref_id LIKE '" . $ref . "-%'" : ''));
  $state = array('first_log_id' => (int)($first->fields['id'] ?? 0), 'recorded' => false, 'outstanding' => 0.0);
  if (!$state['first_log_id']) return $state;
  $progress = shipping_line_progress($order_id);
  if (!$progress) return $state;
  $state['recorded'] = true;
  $lines = $db->Execute("SELECT ji.id, ji.qty FROM " . TABLE_JOURNAL_ITEM . " ji"
    . " LEFT JOIN " . TABLE_INVENTORY . " i ON i.sku = ji.sku"
    . " WHERE ji.ref_id = " . (int)$order_id . " AND ji.sku IS NOT NULL AND ji.sku <> ''"
    . " AND (i.inventory_type IS NULL OR i.inventory_type NOT IN ('" . implode("','", shipping_non_parcel_types()) . "'))");
  while (!$lines->EOF) {
    $done = $progress[(int)$lines->fields['id']] ?? array('shipped' => 0.0, 'cancelled' => 0.0);
    $state['outstanding'] += max(0, (float)$lines->fields['qty'] - $done['shipped'] - $done['cancelled']);
    $lines->MoveNext();
  }
  return $state;
}

// Inventory types that never go in a parcel: service, labour, charge, activity and
// description items. They are left out of what an order has still to send.
function shipping_non_parcel_types() {
  return array('sv', 'lb', 'ci', 'ai', 'ds');
}

function build_js_methods($methods) {
  global $shipping_defaults;
  $choices         = array_keys($shipping_defaults['service_levels']);
  $service_levels  = 'var freightLevels   = new Array();' . chr(10);
  $carriers        = 'var freightCarriers = new Array();' . chr(10);
  $carrier_details = 'var freightDetails  = new Array();' . chr(10);
  for ($i = 0; $i < count(is_array($choices) ? $choices : []); $i++) $service_levels .= "freightLevels[".$i."]='".$choices[$i]."'; " . chr(10);
  $i = 0;
  if (!empty($methods)) foreach ($methods as $method) {
    $carriers          .= "freightCarriers[".$i."]='" . $method['id'] . "';" . chr(10);
    $carrier_details   .= 'freightDetails['.$i.'] = new Array();' . chr(10);
    for ($j = 0; $j < count(is_array($choices) ? $choices : []); $j++) {
	  $carrier_details .= "freightDetails[".$i."][".$j."]='" . (defined($method['id'] . '_' . $choices[$j]) ? constant($method['id'] . '_' . $choices[$j]) : "") . "'; " . chr(10);
    }
    $i++;
  }
  return $service_levels . $carriers . $carrier_details;
}

function GetXMLString($y, $SubmitURL, $GetPost) {
  global $cURLpath;
  $output = array();
  $ch = curl_init(); /// initialize a cURL session 
  curl_setopt($ch, CURLOPT_URL, $SubmitURL); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // times out after 10 seconds 
  curl_setopt($ch, CURLOPT_HEADER, 0); 
  if ($GetPost=="POST") { curl_setopt($ch, CURLOPT_POST, 1); }	
  curl_setopt($ch, CURLOPT_POSTFIELDS, "$y"); 
  $xyz = curl_exec($ch); 
  // Check for errors
  $curlerrornum = curl_errno($ch);
  $curlerror    = curl_error($ch);
  if ($curlerrornum) { 
	$output['result'] = 'error';
	$output['message'] = 'XML Read Error (cURL) #'.$curlerrornum.'. Description='.$curlerror.'.<br />';
  } else {
	$output['result'] = 'success';
	$output['xmlString'] = $xyz;
  }
  return $output;
}

?>