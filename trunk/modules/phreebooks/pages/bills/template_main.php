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
//  Path: /modules/phreebooks/pages/bills/template_main.php
//
echo html_form('bills_form', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'oID'))) . chr(10);
echo html_hidden_field('action',            '') . chr(10);
echo html_hidden_field('id',              $order->id) . chr(10);
echo html_hidden_field('bill_acct_id',    $order->bill_acct_id) . chr(10);
echo html_hidden_field('bill_address_id', $order->bill_address_id) . chr(10);
echo html_hidden_field('bill_telephone1', $order->bill_telephone1) . chr(10);
// Set by submitToDo() from fbDocModified(). '1' unless the javascript could
// establish that nothing changed, so a browser that never ran it posts as
// before rather than silently dropping an edit.
echo html_hidden_field('doc_modified',    '1') . chr(10);
// The currency this entry settles in, and the rate. Hidden rather than a
// chooser: an entry does not get to pick, it takes the currency of whatever is
// ticked below, and one entry carries one (issue #63). fillBill() fills both by
// id when a stored record is opened, since journal_main carries the same two
// column names.
echo html_hidden_field('currencies_code',  isset($order->currencies_code)  ? $order->currencies_code  : DEFAULT_CURRENCY) . chr(10);
echo html_hidden_field('currencies_value', isset($order->currencies_value) ? $order->currencies_value : '1') . chr(10);
if (JOURNAL_ID == 18) {
  echo html_hidden_field('acct_balance', '0') . chr(10);
  echo html_hidden_field('end_balance',  '0') . chr(10);
} elseif (JOURNAL_ID == 20) {
  if ($account_type != 'c') echo html_hidden_field('shipper_code', '') . chr(10);
  echo html_hidden_field('refund_payment_id', isset($order->refund_payment_id) ? $order->refund_payment_id : '') . chr(10);
  // snapshot of the payment method refund_payment_id was looked up against; used server-side to
  // detect if the user switched to a different method (manual refund) before submitting
  echo html_hidden_field('refund_orig_shipper_code', !empty($order->refund_payment_id) ? $order->shipper_code : '') . chr(10);
}
if (!ENABLE_MULTI_BRANCH) echo html_hidden_field('store_id', '0') . chr(10);
// payment_id dropdown: invisible, used by fillPayment() JS
echo html_pull_down_menu('payment_id', gen_null_pull_down(), '', 'style="visibility:hidden;position:absolute" onchange="fillPayment()"') . chr(10);
// toolbar
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=status&amp;jID=' . JOURNAL_ID . '&amp;type=' . $account_type . '&amp;list=1', 'SSL') . '\'"';
$toolbar->icon_list['open']['params']   = 'onclick="OpenOrdrList(this)"';
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . TEXT_DELETE_ENTRY . '\')) submitToDo(\'delete\')"';
$toolbar->icon_list['save']['params']   = 'onclick="saveDocument()"';
$toolbar->icon_list['print']['params']  = 'onclick="printDocument()"';
if ($security_level < 4) $toolbar->icon_list['delete']['show'] = false;
if ($security_level < 2) $toolbar->icon_list['save']['show']   = false;
// Print stays for read-only: printDocument() opens the form popup on the stored
// record without posting this form, so it needs no more than sight of it.
$toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')) . 'jID=' . JOURNAL_ID . '&amp;type=' . $type, 'SSL') . '\'"', 2);
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
switch (JOURNAL_ID) {
  case 18: $toolbar->add_help('07.05.02'); break;
  case 20: $toolbar->add_help('07.05.01'); break;
}
echo $toolbar->build_toolbar();

$_title_key = 'ORD_TEXT_' . JOURNAL_ID . '_' . strtoupper($account_type) . '_WINDOW_TITLE';
if (!defined($_title_key)) $_title_key = 'ORD_TEXT_' . JOURNAL_ID . '_WINDOW_TITLE';
?>
<h1><?php echo constant($_title_key); ?></h1>

<div class="row g-2 mb-2">

  <!-- Address card -->
  <div class="col-md-4">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo JOURNAL_ID == 18 ? TEXT_RECEIVE_FROM : TEXT_PAY_TO; ?></div>
      <div class="card-body py-2 px-2">
        <?php echo ($type == 'c') ? GEN_CUSTOMER_ID : GEN_VENDOR_ID; ?>
        <?php echo html_input_field('search', $order->search, 'onfocus="clearField(\'search\', \'' . TEXT_SEARCH . '\')" onblur="setField(\'search\', \'' . TEXT_SEARCH . '\')"'); ?>
        <?php echo html_icon('actions/system-search.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="AccountList()"'); ?>
        <?php echo html_pull_down_menu('bill_to_select', gen_null_pull_down(), '', 'style="width:100%" onchange="fillAddress(\'bill\')"') . chr(10); ?>
        <br /><?php echo html_input_field('bill_primary_name', $order->bill_primary_name, 'size="33" maxlength="32" onfocus="clearField(\'bill_primary_name\', \'' . GEN_PRIMARY_NAME . '\')" onblur="setField(\'bill_primary_name\', \'' . GEN_PRIMARY_NAME . '\')"') . chr(10); ?>
        <br /><?php echo html_input_field('bill_contact', $order->bill_contact, 'size="33" maxlength="32" onfocus="clearField(\'bill_contact\', \'' . GEN_CONTACT . '\')" onblur="setField(\'bill_contact\', \'' . GEN_CONTACT . '\')"') . chr(10); ?>
        <br /><?php echo html_input_field('bill_address1', $order->bill_address1, 'size="33" maxlength="32" onfocus="clearField(\'bill_address1\', \'' . GEN_ADDRESS1 . '\')" onblur="setField(\'bill_address1\', \'' . GEN_ADDRESS1 . '\')"') . chr(10); ?>
        <br /><?php echo html_input_field('bill_address2', $order->bill_address2, 'size="33" maxlength="32" onfocus="clearField(\'bill_address2\', \'' . GEN_ADDRESS2 . '\')" onblur="setField(\'bill_address2\', \'' . GEN_ADDRESS2 . '\')"') . chr(10); ?>
        <br /><?php
        echo html_input_field('bill_city_town',      $order->bill_city_town,      'size="14" maxlength="24" onfocus="clearField(\'bill_city_town\', \''      . GEN_CITY_TOWN     . '\')" onblur="setField(\'bill_city_town\', \''      . GEN_CITY_TOWN     . '\')"');
        echo html_input_field('bill_state_province', $order->bill_state_province, 'size="3"  maxlength="24" onfocus="clearField(\'bill_state_province\', \'' . GEN_STATE_PROVINCE . '\')" onblur="setField(\'bill_state_province\', \'' . GEN_STATE_PROVINCE . '\')"');
        echo html_input_field('bill_postal_code',    $order->bill_postal_code,    'size="8"  maxlength="10" onfocus="clearField(\'bill_postal_code\', \''    . GEN_POSTAL_CODE   . '\')" onblur="setField(\'bill_postal_code\', \''    . GEN_POSTAL_CODE   . '\')"');
        ?>
        <br /><?php echo html_pull_down_menu('bill_country_code', gen_get_countries(), $order->bill_country_code) . chr(10); ?>
        <br /><?php echo html_input_field('bill_email', $order->bill_email, 'size="33" maxlength="64" onfocus="clearField(\'bill_email\', \'' . GEN_EMAIL . '\')" onblur="setField(\'bill_email\', \'' . GEN_EMAIL . '\')"') . chr(10); ?>
      </div>
    </div>
  </div>

  <!-- Transaction details card -->
  <div class="col-md-4">
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header"><?php echo TEXT_TRANSACTION_DETAILS; ?></div>
      <div class="card-body py-2 px-2">
        <?php
        $fc  = 'class="form-control form-control-sm"';
        $fcr = 'class="form-control form-control-sm text-end"';
        $fs  = 'class="form-select form-select-sm"';
        $frow = function($label, $field) {
          echo '<div class="row g-1 align-items-center mb-1">'
             . '<label class="col-5 text-end text-nowrap" style="font-size:11px">' . $label . '</label>'
             . '<div class="col-7">' . $field . '</div>'
             . '</div>' . "\n";
        };
        $frow((JOURNAL_ID == 18) ? BNK_TEXT_DEPOSIT_ID : BNK_TEXT_PAYMENT_ID,
              html_input_field('purchase_invoice_id', $order->purchase_invoice_id, $fcr));
        $frow(TEXT_DATE, html_calendar_field($cal_bills));
        if (ENABLE_MULTI_BRANCH) {
          $frow(GEN_STORE_ID, html_pull_down_menu('store_id', gen_get_store_ids(),
                $order->store_id ? $order->store_id : $_SESSION['admin_prefs']['def_store_id'], $fs));
        }
        $txn_url = null;
        // It names a variable holding a payment object: an installed payment method only.
        $active_pm = isset($order->shipper_code) && in_array($order->shipper_code, array_column($payment_modules, 'id'), true) ? $order->shipper_code : '';
        if ($order->purch_order_id && $active_pm && isset($$active_pm) && method_exists($$active_pm, 'get_transaction_url')) {
          $txn_url = $$active_pm->get_transaction_url($order->purch_order_id);
        }
        $frow(TEXT_REFERENCE, $txn_url
          ? '<a href="' . htmlspecialchars($txn_url, ENT_QUOTES) . '" target="_blank" rel="noopener">'
            . htmlspecialchars($order->purch_order_id, ENT_QUOTES) . '</a>'
            . html_hidden_field('purch_order_id', $order->purch_order_id)
          : html_input_field('purch_order_id', $order->purch_order_id, $fcr));
        $frow(JOURNAL_ID == 20 ? TEXT_BUYER : TEXT_SALES_REP,
              html_pull_down_menu('rep_id', gen_get_rep_ids($account_type),
                $order->rep_id ? $order->rep_id : $default_sales_rep, $fs));
        $frow(BNK_CASH_ACCOUNT,
              html_pull_down_menu('gl_acct_id', $gl_array_list, $order->gl_acct_id, str_replace('">', '" onchange="loadNewBalance()">', $fs)));
        $frow(BNK_DISCOUNT_ACCOUNT,
              html_pull_down_menu('gl_disc_acct_id', $gl_array_list, $order->gl_disc_acct_id, $fs));
        // The heading says which currency the figure is in, and it is the ENTRY's
        // rather than the company's - it used to read (GBP) on a page settling a
        // dollar invoice. `currency_label` is kept in step by the javascript as
        // rows are ticked.
        $frow(TEXT_TOTAL . (ENABLE_MULTI_CURRENCY ? ' (<span id="currency_label">'
                . htmlspecialchars(isset($order->currencies_code) ? $order->currencies_code : DEFAULT_CURRENCY)
                . '</span>)' : ''),
              html_input_field('total', $order->total_amount, $fcr . ' readonly'));
        if (JOURNAL_ID == 20 && $account_type == 'v') {
          $frow(BNK_ACCOUNT_BALANCE,      html_input_field('acct_balance', $currencies->format($acct_balance), $fcr . ' readonly'));
          $frow(BNK_BALANCE_AFTER_CHECKS, html_input_field('end_balance',  $currencies->format($acct_balance), $fcr . ' readonly'));
        }
        ?>
      </div>
    </div>
  </div>

  <!-- Payment method (J18 / J20 customer) -->
  <div class="col-md-4">
<?php if (JOURNAL_ID == 18 || (JOURNAL_ID == 20 && $account_type == 'c')) { ?>
    <div class="card fb-card h-100">
      <div class="card-header py-1 fb-widget-header d-flex justify-content-between align-items-center">
        <span><?php echo TEXT_PAYMENT_METHOD; ?></span>
        <?php if (JOURNAL_ID == 18 && isset($_SESSION['admin_encrypt'])) { ?>
        <span style="font-size:11px;font-weight:normal"><?php echo BNK_TEXT_SAVE_PAYMENT_INFO . html_checkbox_field('save_payment', '1', ($order->save_payment ? true : false), '', ''); ?></span>
        <?php } ?>
      </div>
      <div class="card-body py-2 px-2">
        <?php echo html_pull_down_menu('shipper_code', $payment_modules, $order->shipper_code, 'class="form-select form-select-sm mb-2" onchange="activateFields()"') . chr(10); ?>
        <div style="position:relative;min-height:120px;">
          <?php
          $count = 0;
          foreach ($payment_modules as $pmt_class) {
            $value = $pmt_class['id'];
            echo '<div id="pm_' . $count . '" style="visibility:hidden;position:absolute;top:0;left:0;width:100%">' . chr(10);
            $disp_fields = $$value->selection();
            for ($i = 0; $i < count(is_array($disp_fields['fields']) ? $disp_fields['fields'] : []); $i++) {
              echo $disp_fields['fields'][$i]['title'] . chr(10);
              echo $disp_fields['fields'][$i]['field'] . chr(10);
            }
            echo '</div>' . chr(10);
            $count++;
          }
          ?>
        </div>
      </div>
    </div>
<?php } ?>
  </div>

</div><!-- /.row -->

<div class="table-responsive">
  <table class="table table-borderless table-hover table-sm">
    <thead class="table-dark">
      <tr>
        <th class="text-center"><?php echo BNK_INVOICE_NUM; ?></th>
        <th class="text-center"><?php echo BNK_DUE_DATE; ?></th>
        <?php // Each row says its own currency, because a customer can be owed in
              // two. The three money headings no longer name one: they used to say
              // (GBP) on every page, which was true of what was stored and not of
              // what the customer was billed (issue #63). ?>
        <?php if (ENABLE_MULTI_CURRENCY) { ?>
        <th class="text-center"><?php echo BNK_CURRENCY; ?></th>
        <?php } ?>
        <th class="text-center"><?php echo BNK_AMOUNT_DUE; ?></th>
        <th class="text-center"><?php echo TEXT_NOTES; ?></th>
        <th class="text-center"><?php echo TEXT_DISCOUNT; ?></th>
        <th class="text-center"><?php echo constant('BNK_' . JOURNAL_ID . '_AMOUNT_PAID'); ?></th>
        <th class="text-center"><?php echo TEXT_PAY; ?></th>
      </tr>
    </thead>
    <tbody id="item_table">
      <?php if ($order->id_1) {
        $i = 1;
        while (true) {
          $id_num    = 'id_'    . $i;
          $inv_num   = 'inv_'   . $i;
          $prcnt_num = 'prcnt_' . $i;
          $early_num = 'early_' . $i;
          $acct_num  = 'acct_'  . $i;
          $due_num   = 'due_'   . $i;
          $amt_num   = 'amt_'   . $i;
          $desc_num  = 'desc_'  . $i;
          $dscnt_num = 'dscnt_' . $i;
          $total_num = 'total_' . $i;
          $pay_num   = 'pay_'   . $i;
          $curr_num  = 'curr_'  . $i;
          if (!isset($order->$id_num)) break;
          $extra_params = ($order->$inv_num) ? '' : 'readonly ';
          $row_class    = $extra_params ? ' class="table-danger"' : '';
          echo '<tr' . $row_class . '>' . chr(10);
          echo '<td class="text-center">' . chr(10);
          echo html_input_field($inv_num, $order->$inv_num, 'class="form-control form-control-sm text-center" readonly') . chr(10);
          echo html_hidden_field($id_num,    $order->$id_num)    . chr(10);
          echo html_hidden_field($prcnt_num, $order->$prcnt_num) . chr(10);
          echo html_hidden_field($early_num, $order->$early_num) . chr(10);
          echo html_hidden_field($acct_num,  $order->$acct_num)  . chr(10);
          echo '</td>' . chr(10);
          echo '<td>' . html_input_field($due_num,   gen_locale_date($order->$due_num),                               'class="form-control form-control-sm text-center" readonly') . '</td>' . chr(10);
          // This branch only runs after a FAILED post, so the figures are the ones
          // the person typed and are already in the row's currency. format() is
          // told not to apply a rate - only to punctuate - or a second post would
          // multiply them by it again.
          $row_curr = $order->$curr_num ?: DEFAULT_CURRENCY;
          if (ENABLE_MULTI_CURRENCY) {
            echo '<td class="text-center">' . htmlspecialchars($row_curr)
               . html_hidden_field($curr_num, $row_curr) . '</td>' . chr(10);
          } else {
            echo html_hidden_field($curr_num, $row_curr) . chr(10);
          }
          echo '<td>' . html_input_field($amt_num,   $currencies->format($currencies->clean_value($order->$amt_num,   $row_curr), false, $row_curr), 'class="form-control form-control-sm text-end" readonly')   . '</td>' . chr(10);
          echo '<td>' . html_input_field($desc_num,  $order->$desc_num,                                               $extra_params . 'class="form-control form-control-sm" maxlength="64"') . '</td>' . chr(10);
          echo '<td>' . html_input_field($dscnt_num, $currencies->format($currencies->clean_value($order->$dscnt_num, $row_curr), false, $row_curr), $extra_params . 'class="form-control form-control-sm text-end" onchange="updateRowTotal(' . $i . ')"') . '</td>' . chr(10);
          echo '<td>' . html_input_field($total_num, $currencies->format($currencies->clean_value($order->$total_num, $row_curr), false, $row_curr), $extra_params . 'class="form-control form-control-sm text-end" onchange="updateUnitPrice(' . $i . ')"') . '</td>' . chr(10);
          echo '<td class="text-center">' . (($extra_params) ? '&nbsp;' : html_checkbox_field($pay_num, '1', ($order->$pay_num ? true : false), '', 'onclick="updatePayValues(' . $i . ')"')) . '</td>' . chr(10);
          echo '</tr>' . chr(10);
          $i++;
        }
      } ?>
    </tbody>
  </table>
</div>
</form>
