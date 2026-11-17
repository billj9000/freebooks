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
//  Path: /modules/shipping/pages/popup_tracking/pre_process.php
//
$security_level = validate_user(0, true);
/**************  include page specific files  *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/shipping.php');

/**************   page specific initialization  *************************/
$close_popup = false;
$methods     = load_all_methods('shipping');
// sID goes into SQL unquoted: a whole number, or '' for a new entry. A method or
// carrier name is instantiated as a class: an installed shipping method only.
$sID         = ctype_digit((string)($_GET['sID'] ?? '')) ? (string)$_GET['sID'] : '';
$method      = (string)($_GET['method'] ?? '');
if ($method !== '' && !shipping_method_is_valid($method)) $method = '';
$ship_date   = date('Y-m-d');
if ($method) $subject_module = new $method();
if (!isset($_REQUEST['action'])) $_REQUEST['action'] = ''; // a plain page open
if (in_array($_REQUEST['action'], array('delete', 'save', 'restore_line', 'settle_line'), true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  $_REQUEST['action'] = ''; // they change state; the form posts them (CSRF-checked)
}

// The order the shipment belongs to: its order id, or one recorded on another shipment of
// the same order number where this one has none.
$order_id = 0;
$order_shipment = null;
if ($sID) {
  shipping_ensure_schema();
  $order_shipment = $db->Execute("select shipment_id, carrier, ref_id, order_id from " . TABLE_SHIPPING_LOG . " where id = " . (int)$sID);
  if ($order_shipment->EOF) {
    $order_shipment = null;
  } else {
    $order_id = (int)$order_shipment->fields['order_id'];
    $base_ref = db_input((string)$order_shipment->fields['ref_id']);
    if (!$order_id && $base_ref !== '') {
      $other = $db->Execute("select max(order_id) as order_id from " . TABLE_SHIPPING_LOG
        . " where ref_id = '" . $base_ref . "' or ref_id like '" . $base_ref . "-%'");
      $order_id = (int)($other->fields['order_id'] ?? 0);
    }
  }
}

/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_MODULES . 'shipping/custom/pages/popup_tracking/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }

/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'delete':
    $row = $db->Execute("select shipment_id, carrier, ship_date, tracking_id, carrier_order_id from " . TABLE_SHIPPING_LOG . " where id = " . (int)$sID);
    if ($row->RecordCount() == 0) { $messageStack->add(SHIPPING_DELETE_ERROR, 'error'); break; }
    $del_carrier  = $row->fields['carrier'];
    if (!shipping_method_is_valid($del_carrier, false)) { $messageStack->add(SHIPPING_DELETE_ERROR, 'error'); break; }
    $del_tracking = $row->fields['tracking_id'];
    // Some carriers (e.g. Royal Mail Click & Drop) cancel by a different id than
    // the tracking number shown to the user — use it when one was recorded.
    $del_cancel_id = $row->fields['carrier_order_id'] ?: $del_tracking;
    $del_date     = substr($row->fields['ship_date'], 0, 10);
    load_specific_method('shipping', $del_carrier);
    $carrier_obj = new $del_carrier();
    if (method_exists($carrier_obj, 'cancelShipment')) {
      if (!$carrier_obj->cancelShipment($del_cancel_id)) break; // error already on messageStack
    }
    $label_path = SHIPPING_DEFAULT_LABEL_DIR . $del_carrier . '/' . str_replace('-', '/', $del_date) . '/';
    foreach (glob($label_path . $del_tracking . '*.pdf') ?: array() as $f) @unlink($f);
    $db->Execute("delete from " . TABLE_SHIPPING_LOG . " where id = " . (int)$sID);
    shipping_forget_contents($row->fields['shipment_id']);
    gen_add_audit_log(SHIPPING_LABEL_DELETED, $sID);
    $sID         = '';
    $close_popup = true;
    break;
  case 'restore_line':
    // A line recorded as never to be shipped, or as sent with another order's shipment,
    // becomes outstanding again. A line a shipment of its own order sent is not touched
    // here: deleting that shipment restores it.
    shipping_ensure_schema();
    $item = $db->Execute("select id, shipment_id, order_id, sku, qty, status from " . TABLE_SHIPPING_LOG_ITEMS
      . " where id = " . (int)($_POST['item_id'] ?? 0));
    if ($item->EOF) break;
    if ($item->fields['status'] !== 'cancelled') {
      $own = $db->Execute("select count(*) as n from " . TABLE_SHIPPING_LOG . " where shipment_id = "
        . (int)$item->fields['shipment_id'] . " and order_id = " . (int)$item->fields['order_id']);
      if ((int)$own->fields['n'] > 0) break;
    }
    $db->Execute("delete from " . TABLE_SHIPPING_LOG_ITEMS . " where id = " . (int)$item->fields['id']);
    gen_add_audit_log(SHIPPING_CANCELLED_RESTORED_LOG, $item->fields['sku']);
    $messageStack->add(sprintf(SHIPPING_CANCELLED_RESTORED, (float)$item->fields['qty'], $item->fields['sku']), 'success');
    break;
  case 'settle_line':
    // Records an outstanding line of the order as sent with a shipment of another of the
    // customer's orders, under that shipment, or as never to be shipped, under the shipment
    // shown. Either way deleting that shipment makes the line outstanding again.
    if (!$order_id || !$order_shipment) break;
    $line_id     = (int)($_POST['settle_line_id'] ?? 0);
    $outstanding = shipping_outstanding_lines($order_id);
    if (!isset($outstanding[$line_id])) { $messageStack->add(SHIPPING_SETTLE_NOTHING, 'error'); break; }
    $line = $outstanding[$line_id];
    $qty  = (float)$currencies->clean_value($_POST['settle_qty'] ?? '');
    if ($qty <= 0 || $qty > $line['remaining'] + 0.000001) {
      $messageStack->add(sprintf(SHIPPING_SETTLE_QTY, $line['sku'], (float)$line['remaining']), 'error');
      break;
    }
    $target = (string)($_POST['settle_target'] ?? '');
    $row    = array('order_id' => $order_id, 'item_ref_id' => $line_id, 'sku' => $line['sku'], 'qty' => $qty,
      'created_at' => date('Y-m-d H:i:s'));
    if ($target === 'cancel') {
      $row += array('shipment_id' => (int)$order_shipment->fields['shipment_id'], 'carrier' => $order_shipment->fields['carrier'],
        'status' => 'cancelled');
      $done_msg = sprintf(SHIPPING_SETTLE_CANCELLED, $qty, $line['sku']);
    } elseif (ctype_digit($target)) {
      $with = shipping_contact_shipments($order_id);
      if (!isset($with[(int)$target])) { $messageStack->add(SHIPPING_SETTLE_NO_SHIPMENT, 'error'); break; }
      $row += array('shipment_id' => (int)$target, 'carrier' => $with[(int)$target]['carrier'], 'status' => 'shipped');
      $done_msg = sprintf(SHIPPING_SETTLE_SENT_WITH, $qty, $line['sku'], (int)$target, $with[(int)$target]['ref']);
    } else {
      $messageStack->add(SHIPPING_SETTLE_CHOOSE, 'error');
      break;
    }
    db_perform(TABLE_SHIPPING_LOG_ITEMS, $row, 'insert');
    gen_add_audit_log(SHIPPING_SETTLE_LOG, $line['sku']);
    $messageStack->add($done_msg, 'success');
    break;
  case 'save':
    if (!$method) break;
    if (!shipping_method_is_valid(db_prepare_input($_POST['carrier'] ?? ''), false)) { $messageStack->add(ERROR_NO_PERMISSION, 'error'); break; }
	$sql_data_array = array(
		'carrier'      => db_prepare_input($_POST['carrier']),
		'ref_id'       => db_prepare_input($_POST['ref_id']),
		'method'       => db_prepare_input($_POST['method']),
		'ship_date'    => gen_db_date($_POST['ship_date']) ?: 'null',
		'deliver_date' => gen_db_date($_POST['deliver_date']) ?: 'null',
		'tracking_id'  => db_prepare_input($_POST['tracking_id']),
		'cost'         => $currencies->clean_value($_POST['cost']),
	);
	if (!$sID) { // it's a new entry
	  $result = $db->Execute("select next_shipment_num from " . TABLE_CURRENT_STATUS);
	  $sql_data_array['shipment_id'] = $result->fields['next_shipment_num'];
	  db_perform(TABLE_SHIPPING_LOG, $sql_data_array, 'insert');
	  $db->Execute("update " . TABLE_CURRENT_STATUS . " set next_shipment_num = next_shipment_num + 1");
      gen_add_audit_log(SHIPPING_SHIPMENT_DETAILS . ' - ' . TEXT_INSERT, $sID);
	} else { // update
	  db_perform(TABLE_SHIPPING_LOG, $sql_data_array, 'update', "id = " . (int)$sID);
      gen_add_audit_log(SHIPPING_SHIPMENT_DETAILS . ' - ' . TEXT_UPDATE, $sID);
	}
	$close_popup = true;
    break;
  default:
}

/*****************   prepare to display templates  *************************/
$js_methods = build_js_methods($methods);

if ($sID) {
  $sql = "select id, shipment_id, carrier, ref_id, method, ship_date, deliver_date, tracking_id, cost,
    recipient_name, recipient_email
	from " . TABLE_SHIPPING_LOG . " where id = " . (int)$sID;
  $result = $db->Execute($sql);
  $cInfo = new objectInfo($result->fields);
  $label_viewer_url  = '';
  $has_carrier_cancel = false;
  $ship_date_only    = substr($cInfo->ship_date, 0, 10);
  $label_file_path   = SHIPPING_DEFAULT_LABEL_DIR . $cInfo->carrier . '/' . str_replace('-', '/', $ship_date_only) . '/';
  if (!empty(glob($label_file_path . $cInfo->tracking_id . '*.pdf'))) {
    // Paperless Trade commercial invoice (DHL) is saved alongside the label as
    // "{tracking}-invoice.pdf" but is not a shipping_log row of its own, so it has
    // to be picked back up from disk when re-opening a shipment already booked.
    $label_ids = array($cInfo->tracking_id);
    if (is_file($label_file_path . $cInfo->tracking_id . '-invoice.pdf')) {
      $label_ids[] = $cInfo->tracking_id . '-invoice';
    }
    $label_viewer_url = html_href_link(FILENAME_DEFAULT,
      'module=shipping&page=popup_label_viewer&method=' . $cInfo->carrier .
      '&date='       . $ship_date_only .
      '&labels='     . implode(':', $label_ids) .
      '&to_name='    . urlencode($cInfo->recipient_name  ?? '') .
      '&to_email='   . urlencode($cInfo->recipient_email ?? '') .
      '&from_name='  . urlencode(defined('COMPANY_NAME')  ? COMPANY_NAME  : '') .
      '&from_email=' . urlencode(defined('COMPANY_EMAIL') ? COMPANY_EMAIL : ''), 'SSL');
  }
  if (shipping_method_is_valid((string)$cInfo->carrier, false)) {
    load_specific_method('shipping', $cInfo->carrier);
    $has_carrier_cancel = method_exists(new $cInfo->carrier(), 'cancelShipment');
  } else {
    $label_viewer_url = '';
  }
  // Every shipment of the same order, with what each contained, oldest first. A log row is
  // matched by its order id, or by the order's number where it has no order id. A shipment
  // of another order that carried a line of this one is listed too, with only that line.
  $ref = db_input((string)($order_shipment->fields['ref_id'] ?? ''));
  $order_shipments = array();
  $carried = array();
  if ($order_id) {
    $rows = $db->Execute("select distinct shipment_id from " . TABLE_SHIPPING_LOG_ITEMS . " where order_id = " . $order_id);
    while (!$rows->EOF) { $carried[] = (int)$rows->fields['shipment_id']; $rows->MoveNext(); }
  }
  $rows = $db->Execute("select l.id, l.shipment_id, l.carrier, l.ship_date, l.tracking_id, l.order_id, o.purchase_invoice_id as order_ref"
    . " from " . TABLE_SHIPPING_LOG . " l left join " . TABLE_JOURNAL_MAIN . " o on o.id = l.order_id"
    . " where " . ($order_id ? "l.order_id = " . $order_id . " or " : '') . "l.ref_id = '" . $ref . "' or l.ref_id like '" . $ref . "-%'"
    . ($carried ? " or l.shipment_id in (" . implode(',', $carried) . ")" : '')
    . " order by l.ship_date, l.id");
  while (!$rows->EOF) {
    $f = $rows->fields;
    $k = (int)$f['shipment_id'];
    if (!isset($order_shipments[$k])) {
      $order_shipments[$k] = array('log_id' => (int)$f['id'], 'carrier' => $f['carrier'],
        'ship_date' => $f['ship_date'], 'tracking' => array(), 'items' => array(), 'other_ref' => '');
    }
    if ($order_id && (int)$f['order_id'] && (int)$f['order_id'] !== $order_id) $order_shipments[$k]['other_ref'] = (string)$f['order_ref'];
    $order_shipments[$k]['tracking'][] = $f['tracking_id'];
    $rows->MoveNext();
  }
  if ($order_shipments) {
    $rows = $db->Execute("select i.id, i.shipment_id, i.order_id, i.sku, i.qty, i.status, o.purchase_invoice_id as order_ref"
      . " from " . TABLE_SHIPPING_LOG_ITEMS . " i left join " . TABLE_JOURNAL_MAIN . " o on o.id = i.order_id"
      . " where i.shipment_id in (" . implode(',', array_keys($order_shipments)) . ") order by i.id");
    while (!$rows->EOF) {
      $f = $rows->fields;
      $k = (int)$f['shipment_id'];
      $foreign_item = $order_id && (int)$f['order_id'] !== $order_id;
      // A shipment of another order shows only this order's lines; one of this order shows a
      // line it carried for another order with that order's number.
      if (!($order_shipments[$k]['other_ref'] !== '' && $foreign_item)) {
        $f['for_ref']  = $foreign_item ? (string)$f['order_ref'] : '';
        $f['undoable'] = $f['status'] === 'cancelled' || $order_shipments[$k]['other_ref'] !== '' || $f['for_ref'] !== '';
        $order_shipments[$k]['items'][] = $f;
      }
      $rows->MoveNext();
    }
  }
  // What the order still has to send, once a shipment has recorded its contents, and the
  // customer's other shipments any of it can be recorded as having gone with.
  $outstanding_lines = array();
  $contact_shipments = array();
  if ($order_id && shipping_line_progress($order_id)) {
    $outstanding_lines = shipping_outstanding_lines($order_id);
    if ($outstanding_lines) $contact_shipments = shipping_contact_shipments($order_id);
  }
  // need to build the methods pull down
  $carrier_methods = array();
  foreach ($shipping_defaults['service_levels'] as $key => $value) {
    if (defined($cInfo->carrier . '_' . $key)) {
	  $carrier_methods[] = array(
	    'id'   => $key,
		'text' => constant($cInfo->carrier . '_' . $key),
	  );
	}
  }
} else {
  // No shipment named: an empty entry, for a new one or for the moment after a delete
  // before the window closes. The method in the request names the carrier.
  $cInfo = new objectInfo(array(
	'shipment_id'  => $sID,
	'carrier'      => $method,
	'method'       => '',
	'ship_date'    => $ship_date,
	'deliver_date' => '',
	'ref_id'       => '',
	'tracking_id'  => '',
	'cost'         => '',
  ));
  $carrier_methods = array();
}

$cal_ship = array(
  'name'      => 'ship_cal',
  'form'      => 'popup_tracking',
  'fieldname' => 'ship_date',
  'imagename' => 'btn_date_1',
  'default'   => gen_locale_date($cInfo->ship_date),
  'params'    => array('align' => 'left'),
);
$cal_del = array(
  'name'      => 'cal',
  'form'      => 'popup_tracking',
  'fieldname' => 'deliver_date',
  'imagename' => 'btn_date_2',
  'default'   => gen_locale_date($cInfo->deliver_date),
  'params'    => array('align' => 'left'),
);

$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
define('PAGE_TITLE', $subject_module->title ?? SHIPPING_SHIPMENT_DETAILS);

?>