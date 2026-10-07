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
 * Creates the collections table, and gives the shipping log its package_data
 * column, on an installation that has neither. Both come from the module's own
 * declarations, the ones the installer uses, so there is a single definition.
 */
function shipping_ensure_pickup_schema() {
  global $db;
  static $checked = false;
  if ($checked) return;
  $checked = true;
  if (!db_field_exists(TABLE_SHIPPING_LOG, 'package_data')) {
    $db->Execute("ALTER TABLE " . TABLE_SHIPPING_LOG . " ADD package_data mediumtext NULL AFTER carrier_order_id");
  }
  if (!db_table_exists(TABLE_SHIPPING_PICKUPS)) {
    require_once(DIR_FS_MODULES . 'shipping/classes/install.php');
    $install = new shipping_admin();
    $db->Execute($install->tables[TABLE_SHIPPING_PICKUPS]);
  }
}

/**
 * The contents lines a label form posted. Each line carries 'ordered', the quantity on the
 * order line it came from, read from the order rather than from the form when the line
 * belongs to order $order_id. A line ships anything from 0 (removed) to that quantity, and at
 * least one line must ship. Returns array('lines' => ..., 'ok' => bool); the lines come back
 * either way, so the form can be shown again, and each refusal is on the message stack.
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
  $lines = array();
  $ok    = true;
  for ($i = 1; $i <= $num; $i++) {
    $sku = db_prepare_input($_POST['li_sku_' . $i] ?? '');
    if (!$sku) continue;
    $id     = (int)($_POST['li_id_' . $i] ?? 0);
    $posted = trim((string)($_POST['li_qty_' . $i] ?? ''));
    $qty    = is_numeric($posted) ? (float)$posted : -1;
    $max    = $ordered[$id] ?? null;
    if ($qty < 0 || ($max !== null && $qty > $max + 0.000001)) {
      $messageStack->add(sprintf(SHIPPING_ERROR_LINE_QTY, $sku, $max === null ? '-' : (string)$max), 'error');
      $ok  = false;
      $qty = max(0, $qty);
    }
    $lines[] = array(
      'id'          => $id,
      'sku'         => $sku,
      'description' => db_prepare_input($_POST['li_desc_'   . $i] ?? ''),
      'origin'      => db_prepare_input($_POST['li_origin_' . $i] ?? ''),
      'hs_code'     => db_prepare_input($_POST['li_hs_'     . $i] ?? ''),
      'qty'         => $qty,
      'ordered'     => $max ?? max($qty, (float)($_POST['li_ord_' . $i] ?? 0)),
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