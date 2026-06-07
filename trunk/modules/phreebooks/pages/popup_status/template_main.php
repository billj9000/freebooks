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
//  Path: /modules/phreebooks/pages/popup_status/template_main.php
//
echo html_form('status', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<div class="alert <?php echo $status_class; ?> text-center py-1 mb-2 rounded-0"><?php echo $status_text; ?></div>

<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo ACT_POPUP_TERMS_WINDOW_TITLE; ?></div>
  <div class="card-body py-2">
    <div><?php echo ACT_TERMS_DUE . ': ' . $new_data['terms_lang'] . ACT_TERMS_CREDIT_LIMIT . $currencies->format($new_data['credit_limit']); ?></div>
    <?php if ($new_data['past_due'] != 0) { ?>
    <div><?php echo ACT_AMT_PAST_DUE . $currencies->format($new_data['past_due']); ?></div>
    <?php } ?>
  </div>
</div>

<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo ACT_ACT_HISTORY; ?></div>
  <div class="card-body p-0">
    <table class="pkg-table table table-borderless table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th class="text-center"><?php echo ($type == 'AP') ? AP_AGING_HEADING_1 : AR_AGING_HEADING_1; ?></th>
          <th class="text-center"><?php echo ($type == 'AP') ? AP_AGING_HEADING_2 : AR_AGING_HEADING_2; ?></th>
          <th class="text-center"><?php echo ($type == 'AP') ? AP_AGING_HEADING_3 : AR_AGING_HEADING_3; ?></th>
          <th class="text-center"><?php echo ($type == 'AP') ? AP_AGING_HEADING_4 : AR_AGING_HEADING_4; ?></th>
          <th class="text-center"><?php echo TEXT_TOTAL; ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="text-center"><?php echo $currencies->format($new_data['balance_0']); ?></td>
          <td class="text-center"><?php echo $currencies->format($new_data['balance_30']); ?></td>
          <td class="text-center"><?php echo $currencies->format($new_data['balance_60']); ?></td>
          <td class="text-center"><?php echo $currencies->format($new_data['balance_90']); ?></td>
          <td class="text-center"><?php echo $currencies->format($new_data['total']); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_NOTES; ?></div>
  <div class="card-body py-2"><?php echo $notes; ?></div>
</div>
</form>
