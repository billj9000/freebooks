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
//  Path: /modules/phreebooks/pages/reconciliation/template_main.php
//
echo html_form('reconciliation', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params']   = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']       = false;
if ($security_level > 1) {
    $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
} else {
    $toolbar->icon_list['save']['show']   = false;
}
$toolbar->icon_list['delete']['show']     = false;
$toolbar->icon_list['print']['show']      = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.05.04');
echo $toolbar->build_toolbar($add_search = false, $add_period = false);
?>
<h1><?php echo BANKING_HEADING_RECONCILIATION; ?></h1>

<!-- Filter bar -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-body py-1 px-2">
    <div class="d-flex align-items-center gap-3" style="font-size:11px">
      <span><?php echo TEXT_CASH_ACCOUNT; ?></span>
      <?php echo html_pull_down_menu('gl_account', $account_array, $gl_account, 'style="font-size:11px" onchange="submit();"'); ?>
      <span><?php echo TEXT_INFO_SEARCH_PERIOD_FILTER; ?></span>
      <?php echo html_pull_down_menu('search_period', gen_get_period_pull_down(false), $period, 'style="font-size:11px" onchange="submit();"'); ?>
    </div>
  </div>
</div>
<?php if (ENABLE_MULTI_CURRENCY) echo '<p class="ms-2" style="font-size:11px">' . sprintf(GEN_PRICE_SHEET_CURRENCY_NOTE, $currencies->currencies[DEFAULT_CURRENCY]['title']) . '</p>' . chr(10); ?>

<!-- Reconciliation card -->
<div class="card fb-card mb-2 mx-auto" style="max-width:960px">
  <div class="card-header py-1 fb-widget-header"><?php echo BOX_BANKING_ACCOUNT_RECONCILIATION; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <?php
            $heading_array = array(
              'reference'  => TEXT_REFERENCE,
              'post_date'  => TEXT_DATE,
              'dep_amount' => BNK_DEPOSIT_CREDIT,
              'pmt_amount' => BNK_CHECK_PAYMENT,
            );
            $result      = html_heading_bar($heading_array, array(TEXT_SOURCE, TEXT_CLEAR, '&nbsp;'));
            echo $result['html_code'];
            ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 0;
          if (!empty($combined_list)) {
            $odd = true;
            foreach ($combined_list as $values) {
              $row_class    = $odd ? 'odd' : 'even';
              $disabled     = ($values['cleared'] <> $period && $values['cleared'] > 0) ? 'disabled="disabled" ' : '';
              $cleared      = ($values['cleared'] <> $period && $values['cleared'] > 0) ? $values['cleared'] . ' ' : '';
              $detail_count = count(is_array($values['detail']) ? $values['detail'] : []);
          ?>
          <tr class="<?php echo $row_class; ?>">
            <td><?php echo $values['reference']; ?></td>
            <td><?php echo gen_locale_date($values['post_date']); ?></td>
            <td class="text-end"><?php echo $values['dep_amount'] ? $currencies->format($values['dep_amount']) : '&nbsp;'; ?></td>
            <td class="text-end"><?php echo $values['pmt_amount'] ? $currencies->format($values['pmt_amount']) : '&nbsp;'; ?></td>
            <td><?php echo htmlspecialchars($values['name']); ?></td>
            <td class="text-center">
              <?php if ($detail_count == 1) { ?>
                <?php echo $cleared . html_checkbox_field('chk[' . $i . ']', '1', ($values['cleared'] == $period), '', $disabled . 'id="chk_' . $i . '" onclick="updateBalance()"') . chr(10); ?>
                <?php echo html_hidden_field('id[' . $i . ']', $values['detail'][0]['id']) . chr(10); ?>
                <?php echo html_hidden_field('pmt_' . $i, $values['detail'][0]['payment']) . chr(10); ?>
              <?php } else { ?>
                <?php echo $cleared . html_checkbox_field('sum_' . $i, '1', ($values['cleared'] == $period), '', $disabled . 'onclick="updateSummary(' . $i . ')"') . chr(10); ?>
              <?php } ?>
            </td>
            <?php if ($detail_count > 1) { ?>
            <td class="text-center" id="disp_<?php echo $i; ?>" style="cursor:pointer<?php if (!empty($values['partial'])) echo ';background-color:yellow'; ?>" onclick="showDetails('<?php echo $i; ?>')"><?php echo TEXT_DETAILS; ?></td>
            <?php } else { ?>
            <td>&nbsp;</td>
            <?php } ?>
          </tr>
          <?php
              if ($detail_count > 1) {
                $j    = 0;
                $ref  = $i;
                $even = true;
                echo '<tr id="detail_' . $i . '" style="display:none"><td colspan="7" class="p-0">' . chr(10);
                echo '<table class="table table-borderless table-sm mb-0" style="font-size:11px">' . chr(10);
                foreach ($values['detail'] as $detail) {
                  $det_disabled = ($detail['cleared'] <> $period && $detail['cleared'] > 0) ? 'disabled="disabled" ' : '';
                  $det_cleared  = ($detail['cleared'] <> $period && $detail['cleared'] > 0) ? $detail['cleared'] . ' ' : '';
                  $det_class    = $even ? 'even' : 'odd';
                  echo '<tr class="' . $det_class . '">' . chr(10);
                  echo '<td style="width:16%">&nbsp;</td>' . chr(10);
                  echo '<td style="width:10%">' . gen_locale_date($detail['post_date']) . '</td>' . chr(10);
                  echo '<td style="width:15%" class="text-end">' . ($detail['dep_amount'] ? $currencies->format($detail['dep_amount']) : '&nbsp;') . '</td>' . chr(10);
                  echo '<td style="width:15%" class="text-end">' . ($detail['pmt_amount'] ? $currencies->format($detail['pmt_amount']) : '&nbsp;') . '</td>' . chr(10);
                  echo '<td style="width:30%">' . htmlspecialchars($detail['name']) . '</td>' . chr(10);
                  echo '<td style="width:7%" class="text-center">' . chr(10);
                  echo $det_cleared . html_checkbox_field('chk[' . $i . ']', '1', ($detail['cleared'] == $period), '', $det_disabled . 'id="chk_' . $i . '" onclick="updateDetail(' . $ref . ')"') . chr(10);
                  echo html_hidden_field('id[' . $i . ']', $detail['id']) . chr(10);
                  echo html_hidden_field('pmt_' . $i, $detail['payment']) . chr(10);
                  echo '</td>' . chr(10);
                  echo '<td id="disp_' . $ref . '_' . $j . '" style="width:7%">&nbsp;</td>' . chr(10);
                  echo '</tr>' . chr(10);
                  $i++;
                  $j++;
                  $even = !$even;
                }
                echo '</table></td></tr>' . chr(10);
              } else {
                $i++;
              }
              $odd = !$odd;
            }
          } else {
            echo '<tr><td colspan="7">' . BNK_NO_GL_ENTRIES . '</td></tr>';
          }
          ?>
        </tbody>
        <tfoot>
          <tr class="tfoot-entry">
            <td colspan="5" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo BNK_START_BALANCE; ?></td>
            <td colspan="2"><?php echo html_input_field('start_balance', $statement_balance, 'class="form-control form-control-sm text-end" onchange="updateBalance()"'); ?></td>
          </tr>
          <tr>
            <td colspan="5" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo BNK_OPEN_CHECKS; ?></td>
            <td colspan="2"><?php echo html_input_field('open_checks', '0', 'disabled class="form-control form-control-sm text-end"'); ?></td>
          </tr>
          <tr>
            <td colspan="5" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo BNK_OPEN_DEPOSITS; ?></td>
            <td colspan="2"><?php echo html_input_field('open_deposits', '0', 'disabled class="form-control form-control-sm text-end"'); ?></td>
          </tr>
          <tr>
            <td colspan="5" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo BNK_GL_BALANCE; ?></td>
            <td colspan="2"><?php echo html_input_field('gl_balance', $gl_balance, 'disabled class="form-control form-control-sm text-end"'); ?></td>
          </tr>
          <tr>
            <td colspan="5" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo BNK_END_BALANCE; ?></td>
            <td colspan="2"><?php echo html_input_field('balance', '0', 'readonly class="form-control form-control-sm text-end"'); ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
</form>
