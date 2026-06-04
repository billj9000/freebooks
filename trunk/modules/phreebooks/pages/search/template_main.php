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
//  Path: /modules/phreebooks/pages/search/template_main.php
//
echo html_form('site_search', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
// toolbar
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
$fs = 'style="font-size:11px"';
$fc = 'style="font-size:11px" size="16"';
?>
<h1><?php echo HEADING_TITLE_SEARCH_INFORMATION; ?></h1>

<!-- Filter card -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
    <span><?php echo TEXT_FILTER; ?></span>
    <span>
      <button type="button" class="btn btn-secondary btn-sm me-1" onclick="submitToDo('reset')"><?php echo TEXT_RESET; ?></button>
      <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('search')"><?php echo TEXT_SEARCH; ?></button>
    </span>
  </div>
  <div class="card-body py-1 px-2">
    <table class="table table-sm table-borderless mb-0" style="font-size:11px;width:auto">
      <thead>
        <tr>
          <th style="font-size:11px"><?php echo TEXT_FILTER; ?></th>
          <th style="font-size:11px"><?php echo TEXT_TYPE; ?></th>
          <th style="font-size:11px"><?php echo TEXT_FROM; ?></th>
          <th style="font-size:11px"><?php echo TEXT_TO; ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?php echo TEXT_TRANSACTION_TYPE; ?></td>
          <td colspan="3"><?php echo html_pull_down_menu('search_journal_id', gen_build_pull_down($journal_choices), $_SESSION['search_journal_id'], $fs); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_TRANSACTION_DATE; ?></td>
          <td><?php echo html_pull_down_menu('search_date_id', gen_build_pull_down($DateChoices), $_SESSION['search_date_id'], $fs); ?></td>
          <td><?php echo html_calendar_field($cal_from); ?></td>
          <td><?php echo html_calendar_field($cal_to); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_REFERENCE_NUMBER; ?></td>
          <td><?php echo html_pull_down_menu('search_ref_id', gen_build_pull_down($choices), $_SESSION['search_ref_id'], $fs); ?></td>
          <td><?php echo html_input_field('search_ref_from', $_SESSION['search_ref_from'], $fc); ?></td>
          <td><?php echo html_input_field('search_ref_to',   $_SESSION['search_ref_to'],   $fc); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_CUST_VEND_ACCT; ?></td>
          <td><?php echo html_pull_down_menu('search_account_id', gen_build_pull_down($choices), $_SESSION['search_account_id'], $fs); ?></td>
          <td><?php echo html_input_field('search_account_from', $_SESSION['search_account_from'], $fc); ?></td>
          <td><?php echo html_input_field('search_account_to',   $_SESSION['search_account_to'],   $fc); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_INVENTORY_ITEM; ?></td>
          <td><?php echo html_pull_down_menu('search_sku_id', gen_build_pull_down($choices), $_SESSION['search_sku_id'], $fs); ?></td>
          <td><?php echo html_input_field('search_sku_from', $_SESSION['search_sku_from'], $fc); ?></td>
          <td><?php echo html_input_field('search_sku_to',   $_SESSION['search_sku_to'],   $fc); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_TRANSACTION_AMOUNT; ?></td>
          <td><?php echo html_pull_down_menu('search_amount_id', gen_build_pull_down($choices), $_SESSION['search_amount_id'], $fs); ?></td>
          <td><?php echo html_input_field('search_amount_from', $_SESSION['search_amount_from'] ? $currencies->precise($_SESSION['search_amount_from']) : '', $fc); ?></td>
          <td><?php echo html_input_field('search_amount_to',   $_SESSION['search_amount_to']   ? $currencies->precise($_SESSION['search_amount_to'])   : '', $fc); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_GENERAL_LEDGER_ACCOUNT; ?></td>
          <td><?php echo html_pull_down_menu('search_gl_acct_id', gen_build_pull_down($choices), $_SESSION['search_gl_acct_id'], $fs); ?></td>
          <td><?php echo html_pull_down_menu('search_gl_acct_from', $gl_array_list, $_SESSION['search_gl_acct_from'], $fs); ?></td>
          <td><?php echo html_pull_down_menu('search_gl_acct_to',   $gl_array_list, $_SESSION['search_gl_acct_to'],   $fs); ?></td>
        </tr>
        <tr>
          <td><?php echo TEXT_JOURNAL_RECORD_ID; ?></td>
          <td><?php echo html_pull_down_menu('search_main_id', gen_build_pull_down($choices), $_SESSION['search_main_id'], $fs); ?></td>
          <td><?php echo html_input_field('search_main_from', $_SESSION['search_main_from'], $fc); ?></td>
          <td><?php echo html_input_field('search_main_to',   $_SESSION['search_main_to'],   $fc); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Results -->
<div class="d-flex justify-content-between align-items-center mb-1" style="font-size:11px">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_RESULTS); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>

<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_RESULTS; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr><?php echo $list_header; ?></tr>
        </thead>
        <tbody>
          <?php
          $odd = true;
          while (!$query_result->EOF) {
            $jID  = (int)$query_result->fields['journal_id'];
            $type = null;
            switch ($jID) {
              case  2: $security_level = $_SESSION['admin_security'][SECURITY_ID_JOURNAL_ENTRY];      $module = 'phreebooks'; $mod = 'journal';   break;
              case  3: $security_level = $_SESSION['admin_security'][SECURITY_ID_PURCHASE_QUOTE];     $module = 'phreebooks'; $mod = 'orders';    break;
              case  4: $security_level = $_SESSION['admin_security'][SECURITY_ID_PURCHASE_ORDER];     $module = 'phreebooks'; $mod = 'orders';    break;
              case  6: $security_level = $_SESSION['admin_security'][SECURITY_ID_PURCHASE_INVENTORY]; $module = 'phreebooks'; $mod = 'orders';    break;
              case  7: $security_level = $_SESSION['admin_security'][SECURITY_ID_PURCHASE_CREDIT];    $module = 'phreebooks'; $mod = 'orders';    break;
              case  9: $security_level = $_SESSION['admin_security'][SECURITY_ID_SALES_QUOTE];        $module = 'orders';     $mod = 'orders';    break;
              case 10: $security_level = $_SESSION['admin_security'][SECURITY_ID_SALES_ORDER];        $module = 'phreebooks'; $mod = 'orders';    break;
              case 12: $security_level = $_SESSION['admin_security'][SECURITY_ID_SALES_INVOICE];      $module = 'phreebooks'; $mod = 'orders';    break;
              case 13: $security_level = $_SESSION['admin_security'][SECURITY_ID_SALES_CREDIT];       $module = 'phreebooks'; $mod = 'orders';    break;
              case 14: $security_level = $_SESSION['admin_security'][SECURITY_ID_ASSEMBLE_INVENTORY]; $module = 'inventory';  $mod = 'assemblies'; break;
              case 16: $security_level = $_SESSION['admin_security'][SECURITY_ID_ADJUST_INVENTORY];   $module = 'inventory';  $mod = 'adjustments'; break;
              case 18: $security_level = $_SESSION['admin_security'][SECURITY_ID_CUSTOMER_RECEIPTS];  $module = 'phreebooks'; $mod = 'bills';
                       $type = gen_get_contact_type($query_result->fields['bill_acct_id']); break;
              case 19: $security_level = $_SESSION['admin_security'][SECURITY_ID_POS_MGR];            $module = 'phreepos';   $mod = 'pos_mgr';   break;
              case 20: $security_level = $_SESSION['admin_security'][SECURITY_ID_PAY_BILLS];          $module = 'phreebooks'; $mod = 'bills';
                       $type = gen_get_contact_type($query_result->fields['bill_acct_id']); break;
              case 21: $security_level = $_SESSION['admin_security'][SECURITY_ID_POS_MGR];            $module = 'phreepos';   $mod = 'pos_mgr';   break;
              default: $security_level = $_SESSION['admin_security'][SECURITY_ID_SEARCH];             $module = 'phreebooks'; $mod = 'orders';    break;
            }
            if ($security_level > 0) {
              $params = 'module=' . $module . '&amp;page=' . $mod . '&amp;oID=' . $query_result->fields['id'] . '&amp;jID=' . $jID . '&amp;action=edit';
              if ($type) $params .= '&amp;type=' . $type;
              $url = html_href_link(FILENAME_DEFAULT, $params, 'SSL');
              $onclick = 'onclick="window.open(\'' . $url . '\',\'_blank\')"';
              $row_class = $odd ? 'odd' : 'even';
          ?>
          <tr class="<?php echo $row_class; ?>" style="cursor:pointer">
            <td <?php echo $onclick; ?>><?php echo $query_result->fields['id']; ?></td>
            <td <?php echo $onclick; ?>><?php echo $query_result->fields['description']; ?></td>
            <td <?php echo $onclick; ?>><?php echo $query_result->fields['bill_primary_name']; ?></td>
            <td <?php echo $onclick; ?>><?php echo gen_locale_date($query_result->fields['post_date']); ?></td>
            <td <?php echo $onclick; ?>><?php echo $query_result->fields['purchase_invoice_id']; ?></td>
            <td class="text-end" <?php echo $onclick; ?>><?php echo $currencies->format($query_result->fields['total_amount']); ?></td>
            <td class="text-end">
              <?php
              if (function_exists('add_extra_action_bar_buttons')) echo add_extra_action_bar_buttons($query_result->fields);
              echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 'onclick="window.open(\'' . $url . '\',\'_blank\')"');
              ?>
            </td>
          </tr>
          <?php
            } else {
          ?>
          <tr><td colspan="7"><?php echo $query_result->fields['description'] . ' - ' . ERROR_NO_SEARCH_PERMISSION; ?></td></tr>
          <?php
            }
            $query_result->MoveNext();
            $odd = !$odd;
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-1" style="font-size:11px">
  <div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . TEXT_RESULTS); ?></div>
  <div><?php echo $query_split->display_links(); ?></div>
</div>
</form>
