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
//  Path: /modules/shipping/methods/royal_mail/label_mgr/pre_process.php
//
$shipping_module = 'royal_mail';
/**************  include page specific files  ***********************/
load_specific_method('shipping', $shipping_module);
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/shipping.php');
require_once(DIR_FS_WORKING . 'classes/shipping.php');
/**************  page specific initialization  **********************/
$error      = false;
$auto_print = false;
$label_data = NULL;
$pdf_list   = array();
$sInfo      = new shipment();
/***************  Act on the action request  ************************/
switch ($_REQUEST['action']) {
  case 'label':
    foreach ($_POST as $key => $value) $sInfo->$key = db_prepare_input($value);
    $sInfo->ship_date = date('Y-m-d', strtotime($sInfo->ship_date));
    // Package rows
    $sInfo->package = array();
    $i = 0;
    while (true) {
      $i++;
      if (!isset($_POST['qty_' . $i])) break;
      if (!$_POST['qty_' . $i] || !$_POST['wt_' . $i]) continue;
      if (!$_POST['len_' . $i]) $_POST['len_' . $i] = SHIPPING_DEFAULT_LENGTH;
      if (!$_POST['wid_' . $i]) $_POST['wid_' . $i] = SHIPPING_DEFAULT_WIDTH;
      if (!$_POST['hgt_' . $i]) $_POST['hgt_' . $i] = SHIPPING_DEFAULT_HEIGHT;
      for ($j = 0; $j < (int)$_POST['qty_' . $i]; $j++) {
        $sInfo->package[] = array(
          'weight' => $_POST['wt_'  . $i],
          'length' => $_POST['len_' . $i],
          'width'  => $_POST['wid_' . $i],
          'height' => $_POST['hgt_' . $i],
          'value'  => $_POST['ins_' . $i],
        );
      }
    }

    if (count($sInfo->package) > 0) {
      $shipment = new $shipping_module;
      if (!$result = $shipment->retrieveLabel($sInfo)) $error = true;
    } else {
      $error = true;
      $messageStack->add(SHIPPING_NO_PACKAGES, 'error');
    }

    if (!$error) {
      $temp = $db->Execute("select next_shipment_num from " . TABLE_CURRENT_STATUS);
      $shipment_num = $temp->fields['next_shipment_num'];
      $labels_array = array();
      foreach ($result as $pkg_result) {
        $sql_array = array(
          'ref_id'       => $pkg_result['ref_id'],
          'shipment_id'  => $shipment_num,
          'carrier'      => $shipping_module,
          'method'       => $sInfo->ship_method,
          'ship_date'    => $sInfo->ship_date . ' ' . date('H:i:s'),
          'deliver_date' => $pkg_result['delivery_date'],
          'tracking_id'  => $pkg_result['tracking'],
          'cost'         => $pkg_result['net_cost'],
        );
        db_perform(TABLE_SHIPPING_LOG, $sql_array, 'insert');
        $labels_array[] = $pkg_result['tracking'];
      }
      $db->Execute("update " . TABLE_CURRENT_STATUS . " set next_shipment_num = next_shipment_num + 1");
      gen_add_audit_log(SHIPPING_LOG_LABEL_PRINTED, $shipment_num . '-' . $sInfo->purchase_invoice_id);
      // All RM labels are PDF — redirect to viewer immediately
      gen_redirect(html_href_link(FILENAME_DEFAULT,
        'module=shipping&page=popup_label_viewer&method=' . $shipping_module .
        '&date=' . $sInfo->ship_date . '&labels=' . implode(':', $labels_array), 'SSL'));
    }
    break;

  case 'view':
    $date         = $_GET['date'];
    $labels_array = explode(':', $_GET['labels']);
    $file_path    = SHIPPING_DEFAULT_LABEL_DIR . $shipping_module . '/' . str_replace('-', '/', $date) . '/';
    foreach ($labels_array as $tracking_num) {
      foreach (glob($file_path . $tracking_num . '*.pdf') as $filename) {
        $pdf_list[] = $tracking_num;
      }
    }
    if (!empty($pdf_list)) {
      gen_redirect(html_href_link(FILENAME_DEFAULT,
        'module=shipping&page=popup_label_viewer&method=' . $shipping_module .
        '&date=' . $date . '&labels=' . implode(':', $labels_array), 'SSL'));
    }
    break;

  case 'delete':
    $shipment_id = db_prepare_input($_GET['sID']);
    $shipments   = $db->Execute("select method, ship_date, tracking_id from " . TABLE_SHIPPING_LOG . " where shipment_id = " . (int)$shipment_id);
    if ($shipments->RecordCount() == 0) {
      $messageStack->add(SHIPPING_DELETE_ERROR, 'error');
      break;
    }
    if ($shipments->fields['ship_date'] < date('Y-m-d')) {
      $messageStack->add(SHIPPING_CANNOT_DELETE, 'error');
      break;
    }
    while (!$shipments->EOF) {
      $tracking = $shipments->fields['tracking_id'];
      $date     = explode('-', substr($shipments->fields['ship_date'], 0, 10));
      $file_path = SHIPPING_DEFAULT_LABEL_DIR . $shipping_module . '/' . $date[0] . '/' . $date[1] . '/' . $date[2] . '/';
      $filename = $file_path . $tracking . '.pdf';
      if (is_file($filename)) @unlink($filename);
      $shipments->MoveNext();
    }
    $db->Execute("delete from " . TABLE_SHIPPING_LOG . " where shipment_id = " . (int)$shipment_id);
    gen_add_audit_log(SHIPPING_LABEL_DELETED, $shipment_id);
    break;

  default:
    $oID = db_prepare_input($_GET['oID']);
    if ($oID) {
      $sql = "select shipper_code, ship_primary_name, ship_contact, ship_address1, ship_address2,
          ship_city_town, ship_state_province, ship_postal_code, ship_country_code, ship_telephone1,
          ship_email, purchase_invoice_id, purch_order_id, total_amount
          from " . TABLE_JOURNAL_MAIN . " where id = " . (int)$oID;
      $result = $db->Execute($sql);
      if (is_array($result->fields)) {
        foreach ($result->fields as $key => $value) $sInfo->$key = $value;
        $temp = explode(':', $result->fields['shipper_code']);
        $sInfo->ship_method = $temp[1] ?? '';
      }
    }
}

/*****************  prepare to display templates  ******************/
$shipping_methods = array();
foreach ($shipping_defaults['service_levels'] as $key => $value) {
  if (defined($shipping_module . '_' . $key)) {
    $shipping_methods[$key] = constant($shipping_module . '_' . $key);
  }
}

// International service codes (JS needs this list to show/hide customs section)
$intl_service_codes = array('RMIT','RMIS','RMITS','RMIE','PFGX','PFGP','PFGV','PFGE');

$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
define('PAGE_TITLE', SHIPPING_POPUP_WINDOW_TITLE);
?>
