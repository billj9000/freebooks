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
//  Path: /modules/phreebooks/pages/bulk_bills/template_main.php
//
echo html_form('bulk_bills', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['params']  = 'onclick="submitToDo(\'print\')"';
if ($security_level < 2) $toolbar->icon_list['print']['show'] = false;
$toolbar->add_icon('ship_all', 'onclick="checkShipAll()"', 20);
$toolbar->icon_list['ship_all']['text'] = BNK_TEXT_CHECK_ALL;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.05.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo BOX_BANKING_SELECT_FOR_PAYMENT; ?></h1>

<!-- Settings card -->
<div class="card fb-card mb-2 mx-auto" style="max-width:920px">
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="row g-2 mb-2">
      <!-- Left: payment fields -->
      <div class="col-md-6">
        <div class="row align-items-center mb-1 g-1">
          <div class="col-5 text-end"><?php echo BNK_CHECK_DATE; ?></div>
          <div class="col-7"><?php echo html_calendar_field($cal_bills0); ?></div>
        </div>
        <div class="row align-items-center mb-1 g-1">
          <div class="col-5 text-end"><?php echo BNK_TEXT_FIRST_CHECK_NUM; ?></div>
          <div class="col-7"><?php echo html_input_field('purchase_invoice_id', $purchase_invoice_id, 'class="form-control form-control-sm text-end"'); ?></div>
        </div>
        <div class="row align-items-center g-1">
          <div class="col-5 text-end"><?php echo TEXT_REFERENCE; ?></div>
          <div class="col-7"><?php echo html_input_field('purch_order_id', $purch_order_id, 'class="form-control form-control-sm"'); ?></div>
        </div>
      </div>
      <!-- Right: account fields -->
      <div class="col-md-6">
        <div class="row align-items-center mb-1 g-1">
          <div class="col-5 text-end"><?php echo BNK_CASH_ACCOUNT; ?></div>
          <div class="col-7"><?php echo html_pull_down_menu('gl_acct_id', $gl_array_list, $gl_acct_id, 'style="font-size:11px;width:100%" onchange="loadNewBalance()"'); ?></div>
        </div>
        <div class="row align-items-center g-1">
          <div class="col-5 text-end"><?php echo BNK_DISCOUNT_ACCOUNT; ?></div>
          <div class="col-7"><?php echo html_pull_down_menu('gl_disc_acct_id', $gl_array_list, $gl_disc_acct_id, 'style="font-size:11px;width:100%"'); ?></div>
        </div>
      </div>
    </div>
    <hr class="my-2">
    <div class="d-flex align-items-start gap-4 flex-wrap">
      <!-- Date filters -->
      <div>
        <div class="mb-1"><?php echo BNK_INVOICES_DUE_BY; ?>&nbsp;<?php echo html_calendar_field($cal_bills1); ?></div>
        <div><?php echo BNK_DISCOUNT_LOST_BY; ?>&nbsp;<?php echo html_calendar_field($cal_bills2); ?></div>
      </div>
      <!-- Search button -->
      <div class="pt-1">
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('search')"><?php echo TEXT_SEARCH; ?></button>
      </div>
      <!-- Balances -->
      <div class="ms-auto">
        <div class="d-flex align-items-center gap-2 mb-1 justify-content-end">
          <span><?php echo BNK_ACCOUNT_BALANCE; ?></span>
          <?php echo html_input_field('acct_balance', $currencies->format($acct_balance), 'readonly class="form-control form-control-sm text-end" style="width:130px"'); ?>
          <?php echo ENABLE_MULTI_CURRENCY ? '&nbsp;(' . DEFAULT_CURRENCY . ')' : ''; ?>
        </div>
        <div class="d-flex align-items-center gap-2 mb-1 justify-content-end">
          <span><?php echo BNK_TOTAL_TO_BE_PAID; ?></span>
          <?php echo html_input_field('total', $currencies->format(0), 'readonly class="form-control form-control-sm text-end" style="width:130px"'); ?>
          <?php echo ENABLE_MULTI_CURRENCY ? '&nbsp;(' . DEFAULT_CURRENCY . ')' : ''; ?>
        </div>
        <div class="d-flex align-items-center gap-2 justify-content-end">
          <span><?php echo BNK_BALANCE_AFTER_CHECKS; ?></span>
          <?php echo html_input_field('end_balance', $currencies->format($acct_balance), 'readonly class="form-control form-control-sm text-end" style="width:130px"'); ?>
          <?php echo ENABLE_MULTI_CURRENCY ? '&nbsp;(' . DEFAULT_CURRENCY . ')' : ''; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (ENABLE_MULTI_CURRENCY) echo '<p class="ms-2" style="font-size:11px">' . sprintf(GEN_PRICE_SHEET_CURRENCY_NOTE, $currencies->currencies[DEFAULT_CURRENCY]['title']) . '</p>' . chr(10); ?>

<!-- Invoice list card -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header"><?php echo BOX_BANKING_SELECT_FOR_PAYMENT; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr><?php echo $list_header; ?></tr>
        </thead>
        <tbody id="item_table">
          <?php
          $idx = 1;
          $odd = true;
          while (!$query_result->EOF) {
            $due_dates = calculate_terms_due_dates($query_result->fields['post_date'], $query_result->fields['terms'], 'AP');
            if ($due_dates['net_date'] > $invoice_date && ($due_dates['early_date'] > $discount_date || $due_dates['discount'] == 0)) {
              $query_result->MoveNext();
              continue;
            }
            if ($invoice_date < $due_dates['net_date']) {
              if ($discount_date < $due_dates['early_date']) {
                $query_result->MoveNext();
                continue;
              }
            }
            $already_paid = fetch_partially_paid($query_result->fields['id']);
            $amount_due   = $query_result->fields['total_amount'] - $already_paid;
            if ($query_result->fields['journal_id'] == 7) $amount_due = -$amount_due;
            $discount     = $currencies->format(($query_result->fields['total_amount'] - $already_paid) * $due_dates['discount']);
            if ($post_date > $due_dates['early_date']) $discount = 0;
            $extra_params = $query_result->fields['waiting'] == '1' ? 'readonly="readonly" ' : '';
            $row_class    = $extra_params ? 'row-hold' : ($odd ? 'odd' : 'even');
            $odd          = !$odd;
          ?>
          <tr class="<?php echo $row_class; ?>">
            <td class="text-center">
              <?php echo gen_locale_date($query_result->fields['post_date']); ?>
              <?php echo html_hidden_field('id_'           . $idx, $query_result->fields['id']); ?>
              <?php echo html_hidden_field('bill_acct_id_' . $idx, $query_result->fields['bill_acct_id']); ?>
              <?php echo html_hidden_field('amt_'          . $idx, $amount_due); ?>
              <?php echo html_hidden_field('inv_'          . $idx, $query_result->fields['purchase_invoice_id']); ?>
              <?php echo html_hidden_field('origdisc_'     . $idx, $currencies->clean_value($discount)); ?>
              <?php echo html_hidden_field('discdate_'     . $idx, $due_dates['early_date']); ?>
              <?php echo html_hidden_field('acct_'         . $idx, $query_result->fields['gl_acct_id']); ?>
            </td>
            <td><?php echo htmlspecialchars($query_result->fields['bill_primary_name']); ?></td>
            <td class="text-center"><?php echo $query_result->fields['purchase_invoice_id']; ?></td>
            <td class="text-end"><?php echo $currencies->format($amount_due); ?></td>
            <td><?php echo html_input_field('desc_'  . $idx, $query_result->fields['purch_order_id'], $extra_params . 'class="form-control form-control-sm" style="min-width:140px"'); ?></td>
            <td class="text-center"><?php echo gen_locale_date($due_dates['net_date']); ?></td>
            <td><?php echo html_input_field('dscnt_' . $idx, $discount, $extra_params . 'class="form-control form-control-sm text-end" style="width:90px" onchange="updateDiscTotal(' . $idx . ')"'); ?></td>
            <td><?php echo html_input_field('total_' . $idx, '',        $extra_params . 'class="form-control form-control-sm text-end" style="width:90px" onchange="updateLineTotal(' . $idx . ')"'); ?></td>
            <td class="text-center"><?php echo html_checkbox_field('pay_' . $idx, '1', false, '', ($extra_params ? 'disabled="disabled" ' : '') . 'onclick="bbUpdatePayValues(' . $idx . ')"'); ?></td>
          </tr>
          <?php
            $idx++;
            $query_result->MoveNext();
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</form>
