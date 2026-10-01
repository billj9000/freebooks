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
//  Path: /modules/phreebooks/pages/status/template_main.php
//
// Invoices can be sent a request for payment, and each shows where its latest
// request stands; see modules/payment/functions/request_dialog.php.
$pay_request  = false;
$pay_statuses = $pay_methods = [];
if (JOURNAL_ID == 12 && defined('MODULE_PAYMENT_STATUS')) {
  require_once(DIR_FS_MODULES . 'payment/functions/request_dialog.php');
  $pay_request  = payment_request_allowed();
  $pay_statuses = payment_latest_requests(array_column($query_result->result, 'id'));
  if ($pay_statuses) $pay_methods = payment_request_methods();
}
echo html_form('status', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;

$toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;jID=' . JOURNAL_ID, 'SSL') . '\'"', 2);

if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
switch (JOURNAL_ID) {
  case  2:
    $toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=journal', 'SSL') . '\'"', 2);
    $toolbar->add_help('');
    break;
  case  3: $toolbar->add_help('07.02.04'); break;
  case  4: $toolbar->add_help('07.02.03.04'); break;
  case  6: $toolbar->add_help('07.02.05'); break;
  case  7: $toolbar->add_help('07.02.07'); break;
  case  9: $toolbar->add_help('07.03.04'); break;
  case 10: $toolbar->add_help('07.03.03.04'); break;
  case 12: $toolbar->add_help('07.03.05.04'); break;
  case 13: $toolbar->add_help('07.03.07'); break;
  case 18:
  case 20:
    $toolbar->add_icon('new', 'onclick="location.href=\''.html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=bills&amp;type='.$account_type.'&amp;jID='.JOURNAL_ID, 'SSL') . '\'"', 2);
    $toolbar->add_help('');
    break;
}
$toolbar->search_period = $_REQUEST['search_period'];
echo $toolbar->build_toolbar($add_search = true, $add_periods = true);
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<?php if (ENABLE_MULTI_BRANCH) { ?>
<div id="filter_bar" class="mb-2">
  <?php echo GEN_STORE_ID . '&nbsp;' . html_pull_down_menu('store_id', gen_get_store_ids(), isset($_REQUEST['store_id']) ? $_REQUEST['store_id'] : $_SESSION['admin_prefs']['def_store_id'], 'onchange="submitToDo(\'filter\')"'); ?>
</div>
<?php } ?>
<?php if (JOURNAL_ID == 12) { ?>
<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllUnprinted()"><?php echo STATUS_SELECT_ALL_UNPRINTED; ?></button>
  <button type="button" class="btn btn-primary btn-sm" id="btn_print_selected" onclick="printSelectedInvoices()" disabled><?php echo STATUS_PRINT_SELECTED; ?></button>
  <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_clear_selection" onclick="clearInvoiceSelection()" style="display:none;"><?php echo STATUS_CLEAR_SELECTION; ?></button>
  <span id="selection_count" class="text-muted small"></span>
</div>
<?php } ?>
<div class="d-flex justify-content-between mb-1">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . $page_title); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
<table class="pkg-table table table-borderless table-hover">
  <thead class="table-dark">
    <tr><?php if (JOURNAL_ID == 12) { ?><th style="width:2em"></th><?php } ?><?php echo $list_header; ?></tr>
  </thead>
  <tbody>
<?php
  $odd = true;
  while (!$query_result->EOF) {
    $oID            = $query_result->fields['id'];
    $post_date      = gen_locale_date($query_result->fields['post_date']);
    $reference_id   = htmlspecialchars($query_result->fields['purchase_invoice_id']);
    $primary_name   = htmlspecialchars($query_result->fields['bill_primary_name']);
    $purch_order_id = htmlspecialchars($query_result->fields['purch_order_id']);
    $closed         = $query_result->fields['closed'] ? TEXT_YES : '';
    $total_amount   = $currencies->format_full($query_result->fields['total_amount'], true, $query_result->fields['currencies_code'], $query_result->fields['currencies_value']);
    if (ENABLE_MULTI_CURRENCY) $total_amount .= ' (' . $query_result->fields['currencies_code'] . ')';
    $row_class      = ($query_result->fields['waiting'] ? 'table-info ' : '') . ($odd ? 'odd' : 'even');
    $attach_exists  = file_exists(PHREEBOOKS_DIR_MY_ORDERS . 'order_' . $oID . '.zip') ? true : false;
    switch (JOURNAL_ID) {
      case  2:
        $link_page = html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=journal&amp;oID=' . $oID . '&amp;jID=' . JOURNAL_ID . '&amp;action=edit', 'SSL');
        break;
      case  3:
      case  4:
      case  6:
      case  7:
      case  9:
      case 10:
      case 12:
      case 13:
        $link_page = html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;oID=' . $oID . '&amp;jID=' . JOURNAL_ID . '&amp;action=edit', 'SSL');
        break;
      case 18:
      case 20:
        $link_page = html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=bills&amp;oID=' . $oID . '&amp;jID=' . JOURNAL_ID . '&amp;type=' . $account_type . '&amp;action=edit', 'SSL');
        break;
    }
?>
    <tr class="<?php echo $row_class; ?>" style="cursor:pointer">
<?php if (JOURNAL_ID == 12) { ?>
      <td class="text-center"><input type="checkbox" class="row-select" value="<?php echo $oID; ?>" onchange="toggleInvoiceSelect(this)"></td>
<?php } ?>
      <td onclick="location.href='<?php echo $link_page; ?>'"><?php echo $post_date; ?></td>
      <td onclick="location.href='<?php echo $link_page; ?>'"><?php echo $reference_id; ?><?php if (isset($pay_statuses[$oID])) echo ' ' . payment_request_badge($pay_statuses[$oID], $pay_methods); ?></td>
      <td onclick="location.href='<?php echo $link_page; ?>'"><?php echo $primary_name; ?></td>
      <td onclick="location.href='<?php echo $link_page; ?>'"><?php echo $purch_order_id; ?></td>
      <td onclick="location.href='<?php echo $link_page; ?>'"><?php echo $closed; ?></td>
      <td class="text-end" onclick="location.href='<?php echo $link_page; ?>'"><?php echo $total_amount; ?></td>
<?php if (defined('MODULE_SHIPPING_STATUS') && in_array(JOURNAL_ID, [4, 12])) {
      $sID          = 0;
      $shipped      = false;
      $result = $db->Execute("select id, shipment_id, ship_date from " . TABLE_SHIPPING_LOG . " where ref_id like '" . db_input($query_result->fields['purchase_invoice_id']) . "%'");
      if ($result->RecordCount() > 0) {
        $sID          = $result->fields['id'];
        $shipped      = $result->fields['shipment_id'];
        $date_shipped = substr($result->fields['ship_date'], 0, 10);
      }
      $temp         = explode(':', $query_result->fields['shipper_code']);
      $shipper_code = preg_replace('/[^a-z0-9_]/', '', strtolower($temp[0]));
      $ship_meth    = defined('MODULE_SHIPPING_' . strtoupper($temp[0]) . '_TITLE_SHORT') ? constant('MODULE_SHIPPING_' . strtoupper($temp[0]) . '_TITLE_SHORT') : $temp[0];
      $ship_srv     = defined($temp[0].'_'.$temp[1]) ? constant($temp[0].'_'.$temp[1]) : $temp[1];
      $order_link   = html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;oID=' . $oID . '&amp;jID=' . JOURNAL_ID . '&amp;action=edit', 'SSL');
?>
      <td class="text-center" onclick="location.href='<?php echo $order_link; ?>'"><?php echo $ship_meth ? (htmlspecialchars($ship_meth, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($ship_srv, ENT_QUOTES, 'UTF-8')) : '&nbsp;'; ?></td>
<?php } ?>
<?php if (JOURNAL_ID == 12) { ?>
      <td onclick="location.href='<?php echo $link_page; ?>'"><?php echo $query_result->fields['printed'] ? TEXT_YES : ''; ?></td>
<?php } ?>
      <td class="text-end text-nowrap">
<?php
    if (function_exists('add_extra_action_bar_buttons')) echo add_extra_action_bar_buttons($query_result->fields);
    switch (JOURNAL_ID) {
      case  2:
        break;
      case  3:
        echo html_icon('actions/system-shutdown.png', TEXT_TOGGLE, 'small', 'onclick="submitSeq(' . $oID . ', \'toggle\')"') . chr(10);
        echo html_icon('actions/document-print.png',  TEXT_PRINT,  'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case  4:
        if (defined('MODULE_SHIPPING_STATUS') && $shipper_code) {
          if ($sID) {
            echo html_icon('phreebooks/stock_id.png', TEXT_VIEW_SHIP_LOG, 'small', 'onclick="loadPopUp(\'' . $shipper_code . '\', \'edit\', ' . $sID . ')"') . chr(10);
          } elseif (!$shipped) {
            echo html_icon('phreebooks/truck-icon.png', TEXT_SHIP, 'small', 'onclick="shipList(' . $oID . ', \'' . $shipper_code . '\')"') . chr(10);
          }
        }
        if (!$query_result->fields['closed']) echo '<button type="button" class="btn btn-primary btn-sm" onclick="window.open(\'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;oID=' . $oID . '&amp;jID=6&amp;action=prc_so', 'SSL') . '\',\'_blank\')">' . TEXT_RECEIVE . '</button>' . chr(10);
        echo '<button type="button" class="btn btn-primary btn-sm" onclick="deliveryList(' . $oID . ')">' . ORD_DELIVERY_DATES . '</button>' . chr(10);
        echo html_icon('actions/system-shutdown.png', TEXT_TOGGLE, 'small', 'onclick="submitSeq(' . $oID . ', \'toggle\')"') . chr(10);
        echo html_icon('actions/document-print.png',  TEXT_PRINT,  'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case  6:
        break;
      case  7:
        echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case  9:
        echo html_icon('actions/system-shutdown.png', TEXT_TOGGLE, 'small', 'onclick="submitSeq(' . $oID . ', \'toggle\')"') . chr(10);
        echo html_icon('actions/document-print.png',  TEXT_PRINT,  'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case 10:
        if (!$query_result->fields['closed']) echo '<button type="button" class="btn btn-primary btn-sm" onclick="window.open(\'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;oID=' . $oID . '&amp;jID=12&amp;action=prc_so', 'SSL') . '\',\'_blank\')">' . TEXT_INVOICE . '</button>' . chr(10);
        echo '<button type="button" class="btn btn-primary btn-sm" onclick="deliveryList(' . $oID . ')">' . ORD_DELIVERY_DATES . '</button>' . chr(10);
        echo html_icon('actions/system-shutdown.png', TEXT_TOGGLE, 'small', 'onclick="submitSeq(' . $oID . ', \'toggle\')"') . chr(10);
        echo html_icon('actions/document-print.png',  TEXT_PRINT,  'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case 12:
        if (defined('MODULE_SHIPPING_STATUS') && $shipper_code) {
          if ($sID) {
            echo html_icon('phreebooks/stock_id.png', TEXT_VIEW_SHIP_LOG, 'small', 'onclick="loadPopUp(\'' . $shipper_code . '\', \'edit\', ' . $sID . ')"') . chr(10);
          } elseif (!$shipped) {
            echo html_icon('phreebooks/truck-icon.png', TEXT_SHIP, 'small', 'onclick="shipList(' . $oID . ', \'' . $shipper_code . '\')"') . chr(10);
          }
        }
        if (!$closed) echo html_icon('apps/accessories-calculator.png', TEXT_PAYMENT, 'small', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=bills&amp;type=c&amp;jID=18&amp;oID=' . $oID . '&amp;action=pmt', 'SSL') . '\';"') . chr(10);
        if (!$closed && $pay_request) echo html_icon('actions/mail-send-receive.png', PAY_REQUEST_TITLE, 'small', 'onclick="payRequestOpen(' . (int)$oID . ')"') . chr(10);
        echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case 13:
        if (!$closed) echo html_icon('apps/accessories-calculator.png', TEXT_PAYMENT, 'small', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=bills&amp;type=c&amp;jID=20&amp;oID=' . $oID . '&amp;action=pmt', 'SSL') . '\';"') . chr(10);
        echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case 18:
        echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
      case 20:
        echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder('. $oID . ')"') . chr(10);
        break;
    }
    if ($attach_exists) {
      echo html_icon('status/mail-attachment.png', TEXT_DOWNLOAD_ATTACHMENT, 'small', 'onclick="submitSeq(' . $oID . ', \'dn_attach\', true)"') . chr(10);
    }
    echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 'onclick="location.href=\'' . $link_page . '\'"') . chr(10);
?>
      </td>
    </tr>
<?php
    $query_result->MoveNext();
    $odd = !$odd;
  }
?>
  </tbody>
</table>
<div class="d-flex justify-content-between mt-1">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . $page_title); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
</form>
<?php if ($pay_request) echo payment_request_dialog(true); ?>
