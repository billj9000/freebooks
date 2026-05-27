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
//  Path: /modules/shipping/methods/dhl_express/label_mgr/pre_process.php
//
$shipping_module = 'dhl_express';
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
    $sInfo->ship_date = gen_db_date($sInfo->ship_date);
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
      // DHL labels are always PDF — redirect to viewer
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

  default:
    $oID = db_prepare_input($_GET['oID']);
    if ($oID) {
      $sql = "select shipper_code, ship_primary_name, ship_contact, ship_address1, ship_address2,
          ship_city_town, ship_state_province, ship_postal_code, ship_country_code, ship_telephone1,
          ship_email, purchase_invoice_id, purch_order_id, total_amount, ship_packages
          from " . TABLE_JOURNAL_MAIN . " where id = " . (int)$oID;
      $result = $db->Execute($sql);
      if (is_array($result->fields)) {
        foreach ($result->fields as $key => $value) $sInfo->$key = $value;
        $temp = explode(':', $result->fields['shipper_code']);
        $sInfo->ship_method  = $temp[1] ?? '';
        $sInfo->total_value  = $result->fields['total_amount'];
        // Pre-populate package rows from ship_packages JSON
        if (!empty($result->fields['ship_packages'])) {
          if (!class_exists('BoxPackingService')) {
            require_once(DIR_FS_MODULES . 'shipping/classes/box_packer.php');
          }
          $raw_pkgs = json_decode($result->fields['ship_packages'], true);
          if (is_array($raw_pkgs)) {
            $sInfo->package      = [];
            $weight_total        = 0;
            foreach ($raw_pkgs as $pkg) {
              $qty = max(1, (int)($pkg['qty'] ?? 1));
              // Support new (weight_g / *_mm) and legacy (weight / length / width / height) formats
              if (isset($pkg['weight_g'])) {
                $w  = round(BoxPackingService::fromGrams((int)$pkg['weight_g']),   3);
                $l  = round(BoxPackingService::fromMm((int)($pkg['length_mm'] ?? 0)), 1);
                $wd = round(BoxPackingService::fromMm((int)($pkg['width_mm']  ?? 0)), 1);
                $h  = round(BoxPackingService::fromMm((int)($pkg['depth_mm']  ?? 0)), 1);
              } else {
                $w  = (float)($pkg['weight'] ?? 0);
                $l  = (float)($pkg['length'] ?? 0);
                $wd = (float)($pkg['width']  ?? 0);
                $h  = (float)($pkg['height'] ?? 0);
              }
              for ($j = 0; $j < $qty; $j++) {
                $sInfo->package[] = ['weight' => $w, 'length' => $l, 'width' => $wd, 'height' => $h, 'value' => 0];
                $weight_total += $w;
              }
            }
            $sInfo->total_weight = round($weight_total, 3);
          }
        }
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

$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
define('PAGE_TITLE', SHIPPING_POPUP_WINDOW_TITLE);
?>
