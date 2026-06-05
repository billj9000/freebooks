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
//  Path: /modules/phreebooks/pages/register/template_main.php
//
echo html_form('register', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.05.04');
$toolbar->search_period = $period;
$toolbar->period_strict = false;
echo $toolbar->build_toolbar($add_search = false, $add_period = true);
?>
<h1><?php echo BANKING_HEADING_REGISTER; ?></h1>

<!-- Account selector -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-body py-2 px-3" style="font-size:11px">
    <span><?php echo TEXT_CASH_ACCOUNT; ?>&nbsp;</span>
    <?php echo html_pull_down_menu('gl_account', $account_array, $gl_account, 'style="font-size:11px;width:320px" onchange="submit();"'); ?>
  </div>
</div>

<?php if (ENABLE_MULTI_CURRENCY) echo '<p class="ms-2" style="font-size:11px">' . sprintf(GEN_PRICE_SHEET_CURRENCY_NOTE, $currencies->currencies[DEFAULT_CURRENCY]['title']) . '</p>' . chr(10); ?>

<!-- Register table -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header"><?php echo BANKING_HEADING_REGISTER; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_DATE; ?></th>
            <th><?php echo TEXT_REFERENCE; ?></th>
            <th><?php echo TEXT_TYPE; ?></th>
            <th><?php echo TEXT_DESCRIPTION; ?></th>
            <th class="text-end"><?php echo TEXT_DEPOSIT; ?></th>
            <th class="text-end"><?php echo TEXT_PAYMENT; ?></th>
            <th class="text-end"><?php echo TEXT_BALANCE; ?></th>
          </tr>
        </thead>
        <tbody>
          <tr class="odd">
            <td></td>
            <td></td>
            <td></td>
            <td><?php echo TEXT_BEGINNING_BALANCE; ?></td>
            <td></td>
            <td></td>
            <td class="text-end"><?php echo $currencies->format($beginning_balance); ?></td>
          </tr>
          <?php
          $odd = false;
          if (is_array($bank_list)) foreach ($bank_list as $values) {
            $beginning_balance += $values['dep_amount'] - $values['pmt_amount'];
            $odd = !$odd;
          ?>
          <tr class="<?php echo $odd ? 'odd' : 'even'; ?>">
            <td class="text-center"><?php echo gen_locale_date($values['post_date']); ?></td>
            <td><?php echo $values['reference']; ?></td>
            <td><?php echo $values['pmt_amount'] ? BNK_TEXT_WITHDRAWAL : TEXT_DEPOSIT; ?></td>
            <td><?php echo htmlspecialchars($values['name']); ?></td>
            <td class="text-end"><?php echo $values['dep_amount'] ? $currencies->format($values['dep_amount']) : ''; ?></td>
            <td class="text-end"><?php echo $values['pmt_amount'] ? $currencies->format($values['pmt_amount']) : ''; ?></td>
            <td class="text-end"><?php echo $currencies->format($beginning_balance); ?></td>
          </tr>
          <?php } ?>
        </tbody>
        <tfoot class="table-dark">
          <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><?php echo TEXT_ENDING_BALANCE; ?></td>
            <td></td>
            <td></td>
            <td class="text-end"><?php echo $currencies->format($beginning_balance); ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
</form>
