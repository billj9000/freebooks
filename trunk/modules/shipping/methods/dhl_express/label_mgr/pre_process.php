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
  case 'save_items':
    $num_li = max(0, (int)($_POST['num_line_items'] ?? 0));
    for ($i = 1; $i <= $num_li; $i++) {
      $sku = db_prepare_input($_POST['li_sku_' . $i] ?? '');
      if (!$sku) continue;
      $db->Execute("UPDATE " . TABLE_INVENTORY . " SET
        customs_description = '" . db_input(db_prepare_input($_POST['li_desc_'   . $i] ?? '')) . "',
        country_of_origin   = '" . db_input(db_prepare_input($_POST['li_origin_' . $i] ?? '')) . "',
        hs_code             = '" . db_input(db_prepare_input($_POST['li_hs_'     . $i] ?? '')) . "',
        item_weight         = "  . (float)($_POST['li_wt_' . $i] ?? 0) . "
        WHERE sku = '" . db_input($sku) . "'");
    }
    $messageStack->add(DHL_ITEMS_SAVED, 'success');
    gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array()), 'SSL'));
    break;

  case 'label':
    foreach ($_POST as $key => $value) $sInfo->$key = db_prepare_input($value);
    // <input type="date"> submits Y-m-d already; gen_db_date() would mangle it on non-Y-m-d locales
    $sInfo->ship_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sInfo->ship_date)
      ? $sInfo->ship_date : gen_db_date($sInfo->ship_date);
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
    // Line items for customs declaration
    $sInfo->line_items = array();
    $num_li = max(0, (int)($_POST['num_line_items'] ?? 0));
    for ($i = 1; $i <= $num_li; $i++) {
      $sku = db_prepare_input($_POST['li_sku_' . $i] ?? '');
      if (!$sku) continue;
      $sInfo->line_items[] = array(
        'sku'         => $sku,
        'description' => db_prepare_input($_POST['li_desc_'   . $i] ?? ''),
        'origin'      => db_prepare_input($_POST['li_origin_' . $i] ?? ''),
        'hs_code'     => db_prepare_input($_POST['li_hs_'     . $i] ?? ''),
        'qty'         => (float)($_POST['li_qty_' . $i] ?? 1),
        'weight'      => (float)($_POST['li_wt_'  . $i] ?? 0),
        'value'       => (float)($_POST['li_val_' . $i] ?? 0),
      );
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
          'ref_id'          => $pkg_result['ref_id'],
          'shipment_id'     => $shipment_num,
          'carrier'         => $shipping_module,
          'method'          => $sInfo->ship_method,
          'ship_date'       => $sInfo->ship_date . ' ' . date('H:i:s'),
          'deliver_date'    => $pkg_result['delivery_date'],
          'tracking_id'     => $pkg_result['tracking'],
          'cost'            => $pkg_result['net_cost'],
          'recipient_name'  => $sInfo->sender_primary_name ?? '',
          'recipient_email' => $sInfo->sender_email ?? '',
        );
        db_perform(TABLE_SHIPPING_LOG, $sql_array, 'insert');
        $labels_array[] = $pkg_result['tracking'];
      }
      $db->Execute("update " . TABLE_CURRENT_STATUS . " set next_shipment_num = next_shipment_num + 1");
      gen_add_audit_log(SHIPPING_LOG_LABEL_PRINTED, $shipment_num . '-' . $sInfo->purchase_invoice_id);
      // DHL labels are always PDF — redirect to viewer, passing email address hints
      gen_redirect(html_href_link(FILENAME_DEFAULT,
        'module=shipping&page=popup_label_viewer&method=' . $shipping_module .
        '&date='       . $sInfo->ship_date .
        '&labels='     . implode(':', $labels_array) .
        '&to_name='    . urlencode($sInfo->sender_primary_name ?? '') .
        '&to_email='   . urlencode($sInfo->sender_email ?? '') .
        '&from_name='  . urlencode(defined('COMPANY_NAME')  ? COMPANY_NAME  : '') .
        '&from_email=' . urlencode(defined('COMPANY_EMAIL') ? COMPANY_EMAIL : ''), 'SSL'));
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
      $sql = "select journal_id, bill_acct_id, shipper_code, ship_primary_name, ship_contact, ship_address1, ship_address2,
          ship_city_town, ship_state_province, ship_postal_code, ship_country_code, ship_telephone1,
          ship_email, bill_primary_name, bill_contact, bill_address1, bill_address2,
          bill_city_town, bill_state_province, bill_postal_code, bill_country_code, bill_telephone1, bill_email,
          purchase_invoice_id, purch_order_id, total_amount, ship_packages,
          currencies_code, currencies_value
          from " . TABLE_JOURNAL_MAIN . " where id = " . (int)$oID;
      $result = $db->Execute($sql);
      if (is_array($result->fields)) {
        foreach ($result->fields as $key => $value) $sInfo->$key = $value;
        $journal_id = (int)($result->fields['journal_id'] ?? $_GET['jID'] ?? 0);
        // Look up EORI / VAT from the customer or vendor contact record
        $contact_vat  = '';
        $contact_eori = '';
        $bill_acct_id = (int)($result->fields['bill_acct_id'] ?? 0);
        if ($bill_acct_id) {
          $cReg = $db->Execute("SELECT vat_number, eori_number FROM " . TABLE_CONTACTS
              . " WHERE id = " . $bill_acct_id . " LIMIT 1");
          if (!$cReg->EOF) {
            $contact_vat  = $cReg->fields['vat_number']  ?? '';
            $contact_eori = $cReg->fields['eori_number'] ?? '';
          }
        }
        if (in_array($journal_id, [3, 4, 6, 7])) {
          $sInfo->is_purchase          = true;
          $sInfo->vendor_primary_name  = $result->fields['bill_primary_name']   ?? '';
          $sInfo->vendor_contact       = $result->fields['bill_contact']        ?? '';
          $sInfo->vendor_address1      = $result->fields['bill_address1']       ?? '';
          $sInfo->vendor_address2      = $result->fields['bill_address2']       ?? '';
          $sInfo->vendor_city_town     = $result->fields['bill_city_town']      ?? '';
          $sInfo->vendor_state_province= $result->fields['bill_state_province'] ?? '';
          $sInfo->vendor_postal_code   = $result->fields['bill_postal_code']    ?? '';
          $sInfo->vendor_country_code  = $result->fields['bill_country_code']   ?? '';
          $sInfo->vendor_telephone1    = $result->fields['bill_telephone1']     ?? '';
          $sInfo->vendor_email         = $result->fields['bill_email']          ?? '';
          // For purchases, ship_* = our receiving address; fall back to company constants if blank
          if (!$sInfo->ship_primary_name) $sInfo->ship_primary_name = defined('COMPANY_NAME')         ? COMPANY_NAME         : '';
          if (!$sInfo->ship_city_town)    $sInfo->ship_city_town    = defined('COMPANY_CITY_TOWN')    ? COMPANY_CITY_TOWN    : '';
          if (!$sInfo->ship_postal_code)  $sInfo->ship_postal_code  = defined('COMPANY_POSTAL_CODE')  ? COMPANY_POSTAL_CODE  : '';
          if (!$sInfo->ship_country_code) $sInfo->ship_country_code = defined('COMPANY_COUNTRY')      ? COMPANY_COUNTRY      : '';
          // Fill in the full receiving address from company constants where blank
          if (!$sInfo->ship_address1)      $sInfo->ship_address1      = defined('COMPANY_ADDRESS1')    ? COMPANY_ADDRESS1    : '';
          if (!($sInfo->ship_address2??''))$sInfo->ship_address2      = defined('COMPANY_ADDRESS2')    ? COMPANY_ADDRESS2    : '';
          if (!$sInfo->ship_state_province)$sInfo->ship_state_province= defined('COMPANY_ZONE')        ? COMPANY_ZONE        : '';
          if (!($sInfo->ship_telephone1??''))$sInfo->ship_telephone1  = defined('COMPANY_TELEPHONE1')  ? COMPANY_TELEPHONE1  : '';
          if (!($sInfo->ship_email??''))   $sInfo->ship_email         = defined('COMPANY_EMAIL')       ? COMPANY_EMAIL       : '';
          // Sender = vendor
          $sInfo->sender_primary_name   = $sInfo->vendor_primary_name   ?? '';
          $sInfo->sender_contact        = $sInfo->vendor_contact        ?? '';
          $sInfo->sender_address1       = $sInfo->vendor_address1       ?? '';
          $sInfo->sender_address2       = $sInfo->vendor_address2       ?? '';
          $sInfo->sender_city_town      = $sInfo->vendor_city_town      ?? '';
          $sInfo->sender_state_province = $sInfo->vendor_state_province ?? '';
          $sInfo->sender_postal_code    = $sInfo->vendor_postal_code    ?? '';
          $sInfo->sender_country_code   = $sInfo->vendor_country_code   ?? '';
          $sInfo->sender_telephone1     = $sInfo->vendor_telephone1     ?? '';
          $sInfo->sender_email          = $sInfo->vendor_email          ?? '';
          // Vendor EORI/VAT → sender registration numbers
          $sInfo->sender_vat_number     = $contact_vat;
          $sInfo->sender_eori_number    = $contact_eori;
        } else {
          // Sender = company
          $sInfo->sender_primary_name   = defined('COMPANY_NAME')        ? COMPANY_NAME        : '';
          $sInfo->sender_contact        = defined('AR_CONTACT_NAME')     ? AR_CONTACT_NAME     : '';
          $sInfo->sender_address1       = defined('COMPANY_ADDRESS1')    ? COMPANY_ADDRESS1    : '';
          $sInfo->sender_address2       = defined('COMPANY_ADDRESS2')    ? COMPANY_ADDRESS2    : '';
          $sInfo->sender_city_town      = defined('COMPANY_CITY_TOWN')   ? COMPANY_CITY_TOWN   : '';
          $sInfo->sender_state_province = defined('COMPANY_ZONE')        ? COMPANY_ZONE        : '';
          $sInfo->sender_postal_code    = defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : '';
          $sInfo->sender_country_code   = defined('COMPANY_COUNTRY')     ? COMPANY_COUNTRY     : '';
          $sInfo->sender_telephone1     = defined('COMPANY_TELEPHONE1')  ? COMPANY_TELEPHONE1  : '';
          $sInfo->sender_email          = defined('COMPANY_EMAIL')       ? COMPANY_EMAIL       : '';
          // Company EORI/VAT → sender registration numbers from branch contact; fall back to admin constant
          $branchReg = $db->Execute("SELECT vat_number, eori_number FROM " . TABLE_CONTACTS
              . " WHERE type = 'b' AND inactive = '0' ORDER BY id ASC LIMIT 1");
          $branch_vat  = (!$branchReg->EOF) ? ($branchReg->fields['vat_number']  ?? '') : '';
          $branch_eori = (!$branchReg->EOF) ? ($branchReg->fields['eori_number'] ?? '') : '';
          if (!$branch_eori && defined('SHIPPING_SHIPPER_EORI')) $branch_eori = SHIPPING_SHIPPER_EORI;
          if (!$branch_vat  && defined('SHIPPING_SHIPPER_VAT'))  $branch_vat  = SHIPPING_SHIPPER_VAT;
          $sInfo->sender_vat_number     = $branch_vat;
          $sInfo->sender_eori_number    = $branch_eori;
          // Customer EORI/VAT → recipient registration numbers
          $sInfo->ship_vat_number       = $contact_vat;
          $sInfo->ship_eori_number      = $contact_eori;
        }
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
        // Load inventory customs data for each line item
        $li_sql = "SELECT ji.sku, ji.qty, ji.description, ji.full_price, ji.debit_amount, ji.credit_amount,
                     i.customs_description, i.country_of_origin, i.hs_code, i.item_weight
                   FROM " . TABLE_JOURNAL_ITEM . " ji
                   LEFT JOIN " . TABLE_INVENTORY . " i ON i.sku = ji.sku
                   WHERE ji.ref_id = " . (int)$oID . "
                     AND ji.sku IS NOT NULL AND ji.sku != ''
                   ORDER BY ji.item_cnt";
        $li_result = $db->Execute($li_sql);
        $sInfo->line_items = array();
        while (!$li_result->EOF) {
          $f   = $li_result->fields;
          $qty = (float)$f['qty'];
          // Purchases use debit_amount; sales use credit_amount. full_price (unit) takes priority when set.
          $line_total = !empty($sInfo->is_purchase) ? (float)$f['debit_amount'] : (float)$f['credit_amount'];
          $unit_price = (float)$f['full_price'] > 0
            ? (float)$f['full_price']
            : ($qty > 0 ? $line_total / $qty : 0);
          $sInfo->line_items[] = array(
            'sku'         => $f['sku'],
            'description' => $f['customs_description'] ?: ($f['description'] ?: ''),
            'origin'      => $f['country_of_origin'] ?: '',
            'hs_code'     => $f['hs_code'] ?: '',
            'qty'         => $qty,
            'weight'      => (float)$f['item_weight'],
            'value'       => $unit_price,
          );
          $li_result->MoveNext();
        }
        // Use goods-only total for the declared value display (excludes shipping/VAT line items)
        if (!empty($sInfo->line_items)) {
          $goods_sum = 0;
          foreach ($sInfo->line_items as $_li) $goods_sum += (float)$_li['value'] * (float)$_li['qty'];
          $sInfo->total_value = $goods_sum;
        }
      }
    }
}

/*****************  prepare to display templates  ******************/
$available_packages = [];
if (defined('TABLE_SHIPPING_PACKAGES')) {
  $pkgQ = $db->Execute("SELECT name, ext_length, ext_width, ext_height
    FROM " . TABLE_SHIPPING_PACKAGES . "
    WHERE (carrier = '' OR carrier = 'dhl_express') AND inactive = '0'
    ORDER BY sort_order ASC, name ASC");
  while (!$pkgQ->EOF) {
    $available_packages[] = $pkgQ->fields;
    $pkgQ->MoveNext();
  }
}
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
