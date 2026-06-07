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
//  Path: /modules/inventory/pages/main/template_tab_hist.php
?>
<!-- SKU History -->
<div class="card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo INV_SKU_HISTORY; ?></div>
  <div class="card-body py-2">
    <div class="row g-2">
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_DATE_ACCOUNT_CREATION; ?></label>
        <?php echo html_input_field('creation_date', gen_locale_date($cInfo->creation_date), 'readonly="readonly" class="form-control form-control-sm" style="width:160px"', false); ?>
      </div>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_DATE_LAST_UPDATE; ?></label>
        <?php echo html_input_field('last_update', gen_locale_date($cInfo->last_update), 'readonly="readonly" class="form-control form-control-sm" style="width:160px"', false); ?>
      </div>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_DATE_LAST_JOURNAL_DATE; ?></label>
        <?php echo html_input_field('last_journal_date', gen_locale_date($cInfo->last_journal_date), 'readonly="readonly" class="form-control form-control-sm" style="width:160px"', false); ?>
      </div>
    </div>
  </div>
</div>

<!-- SKU Activity -->
<div class="card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo INV_SKU_ACTIVITY; ?></div>
  <div class="card-body py-2">
    <div class="row g-3">

      <!-- Open POs + Open SOs + Averages (left column) -->
      <div class="col-md-6">

        <?php if (in_array('purchase', $cInfo->posible_transactions)): ?>
        <table class="table table-sm pkg-table mb-2">
          <thead>
            <tr><th colspan="4"><?php echo INV_OPEN_PO; ?></th></tr>
            <tr>
              <th><?php echo INV_PO_NUMBER; ?></th>
              <th><?php echo INV_PO_DATE; ?></th>
              <th><?php echo TEXT_QUANTITY; ?></th>
              <th><?php echo INV_PO_RCV_DATE; ?></th>
            </tr>
          </thead>
          <tbody>
          <?php
          if ($cInfo->history['open_po']) {
            $odd = true;
            foreach ($cInfo->history['open_po'] as $value) {
              echo '<tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
              echo '  <td class="text-center"><a href="' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;action=edit&amp;jID=4&amp;oID=' . $value['id'], 'SSL') . '">' . $value['purchase_invoice_id'] . '</a></td>' . chr(10);
              echo '  <td class="text-center">' . gen_locale_date($value['post_date']) . '</td>' . chr(10);
              echo '  <td class="text-center">' . ($value['qty'] ? $value['qty'] : '&nbsp;') . '</td>' . chr(10);
              echo '  <td class="text-center">' . gen_locale_date($value['date_1']) . '</td>' . chr(10);
              echo '</tr>' . chr(10);
              $odd = !$odd;
            }
          } else {
            echo '<tr><td colspan="4" class="text-center">' . ACT_NO_RESULTS . '</td></tr>' . chr(10);
          }
          ?>
          </tbody>
        </table>
        <?php endif; ?>

        <?php if (in_array('sell', $cInfo->posible_transactions)): ?>
        <table class="table table-sm pkg-table mb-2">
          <thead>
            <tr><th colspan="4"><?php echo INV_OPEN_SO; ?></th></tr>
            <tr>
              <th><?php echo INV_SO_NUMBER; ?></th>
              <th><?php echo INV_SO_DATE; ?></th>
              <th><?php echo TEXT_QUANTITY; ?></th>
              <th><?php echo TEXT_REQUIRED_DATE; ?></th>
            </tr>
          </thead>
          <tbody>
          <?php
          if ($cInfo->history['open_so']) {
            $odd = true;
            foreach ($cInfo->history['open_so'] as $value) {
              echo '<tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
              echo '  <td class="text-center"><a href="' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=orders&amp;action=edit&amp;jID=10&amp;oID=' . $value['id'], 'SSL') . '">' . $value['purchase_invoice_id'] . '</a></td>' . chr(10);
              echo '  <td class="text-center">' . gen_locale_date($value['post_date']) . '</td>' . chr(10);
              echo '  <td class="text-center">' . ($value['qty'] ? $value['qty'] : '&nbsp;') . '</td>' . chr(10);
              echo '  <td class="text-center">' . gen_locale_date($value['date_1']) . '</td>' . chr(10);
              echo '</tr>' . chr(10);
              $odd = !$odd;
            }
          } else {
            echo '<tr><td colspan="4" class="text-center">' . ACT_NO_RESULTS . '</td></tr>' . chr(10);
          }
          ?>
          </tbody>
        </table>
        <?php endif; ?>

        <table class="table table-sm pkg-table mb-0">
          <thead>
            <tr><th colspan="4"><?php echo TEXT_AVERAGE_USE; ?></th></tr>
            <tr>
              <th><?php echo TEXT_LAST_MONTH; ?></th>
              <th><?php echo TEXT_LAST_3_MONTH; ?></th>
              <th><?php echo TEXT_LAST_6_MONTH; ?></th>
              <th><?php echo TEXT_LAST_12_MONTH; ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-center"><?php echo $cInfo->history['averages']['1month']; ?></td>
              <td class="text-center"><?php echo $cInfo->history['averages']['3month']; ?></td>
              <td class="text-center"><?php echo $cInfo->history['averages']['6month']; ?></td>
              <td class="text-center"><?php echo $cInfo->history['averages']['12month']; ?></td>
            </tr>
          </tbody>
        </table>

      </div><!-- /left col -->

      <!-- Purchase + Sales history (right columns) -->
      <div class="col-md-3">
        <?php if (isset($cInfo->purchases_history)): ?>
        <table class="table table-sm pkg-table">
          <thead>
            <tr><th colspan="4"><?php echo INV_PURCH_BY_MONTH; ?></th></tr>
            <tr>
              <th><?php echo TEXT_YEAR; ?></th>
              <th><?php echo TEXT_MONTH; ?></th>
              <th class="text-end"><?php echo TEXT_QUANTITY; ?></th>
              <th class="text-end"><?php echo INV_PURCH_COST; ?></th>
            </tr>
          </thead>
          <tbody>
          <?php
          if ($cInfo->purchases_history) {
            $odd = true;
            foreach ($cInfo->purchases_history as $value) {
              echo '<tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
              echo '  <td class="text-center">' . $value['ThisYear'] . '</td>' . chr(10);
              echo '  <td class="text-center">' . $value['MonthName'] . '</td>' . chr(10);
              echo '  <td class="text-end">' . ($value['qty'] ? $value['qty'] : '&nbsp;') . '</td>' . chr(10);
              echo '  <td class="text-end">' . ($value['total_amount'] ? $currencies->format($value['total_amount']) : '&nbsp;') . '</td>' . chr(10);
              echo '</tr>' . chr(10);
              $odd = !$odd;
            }
          } else {
            echo '<tr><td colspan="4" class="text-center">' . ACT_NO_RESULTS . '</td></tr>' . chr(10);
          }
          ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <div class="col-md-3">
        <?php if (isset($cInfo->sales_history)): ?>
        <table class="table table-sm pkg-table">
          <thead>
            <tr><th colspan="4"><?php echo INV_SALES_BY_MONTH; ?></th></tr>
            <tr>
              <th><?php echo TEXT_YEAR; ?></th>
              <th><?php echo TEXT_MONTH; ?></th>
              <th class="text-end"><?php echo TEXT_QUANTITY; ?></th>
              <th class="text-end"><?php echo INV_SALES_INCOME; ?></th>
            </tr>
          </thead>
          <tbody>
          <?php
          if ($cInfo->sales_history) {
            $odd = true;
            foreach ($cInfo->sales_history as $value) {
              echo '<tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
              echo '  <td class="text-center">' . $value['ThisYear'] . '</td>' . chr(10);
              echo '  <td class="text-center">' . $value['MonthName'] . '</td>' . chr(10);
              echo '  <td class="text-end">' . ($value['qty'] ? $value['qty'] : '&nbsp;') . '</td>' . chr(10);
              echo '  <td class="text-end">' . ($value['total_amount'] ? $currencies->format($value['total_amount']) : '&nbsp;') . '</td>' . chr(10);
              echo '</tr>' . chr(10);
              $odd = !$odd;
            }
          } else {
            echo '<tr><td colspan="4" class="text-center">' . ACT_NO_RESULTS . '</td></tr>' . chr(10);
          }
          ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    </div><!-- /row -->
  </div>
</div>
