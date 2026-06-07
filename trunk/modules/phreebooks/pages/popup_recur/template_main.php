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
//  Path: /modules/phreebooks/pages/popup_recur/template_main.php
//
echo html_form('recur', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="setReturnRecur()"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07');
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-3">
<div class="d-inline-flex flex-column gap-2">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
  <div class="card-body py-2">
    <p class="mb-2"><?php echo ORD_RECUR_INTRO; ?></p>
    <div class="mb-2">
      <label class="fw-bold mb-1"><?php echo ORD_RECUR_ENTRIES; ?></label><br>
      <?php echo html_input_field('recur_id', '1', 'class="form-control form-control-sm" style="width:60px"'); ?>
    </div>
    <div>
      <div class="fw-bold mb-1"><?php echo ORD_RECUR_FREQUENCY; ?></div>
      <div class="d-flex flex-column gap-1">
        <label><?php echo html_radio_field('recur_frequency', 1, false); ?> <?php echo ORD_TEXT_WEEKLY; ?></label>
        <label><?php echo html_radio_field('recur_frequency', 2, false); ?> <?php echo ORD_TEXT_BIWEEKLY; ?></label>
        <label><?php echo html_radio_field('recur_frequency', 3, true);  ?> <?php echo ORD_TEXT_MONTHLY; ?></label>
        <label><?php echo html_radio_field('recur_frequency', 4, false); ?> <?php echo ORD_TEXT_QUARTERLY; ?></label>
        <label><?php echo html_radio_field('recur_frequency', 5, false); ?> <?php echo ORD_TEXT_YEARLY; ?></label>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</form>
