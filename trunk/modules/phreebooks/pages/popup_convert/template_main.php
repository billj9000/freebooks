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
//  Path: /modules/phreebooks/pages/popup_convert/template_main.php
//
echo html_form('popup_convert', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('id',  $id) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
switch ($account_type) {
  case 'c': $toolbar->add_help('07.03.02.04'); break;
  case 'v': $toolbar->add_help('07.02.02.04'); break;
}
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-3">
<div class="d-inline-flex flex-column gap-2">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
  <div class="card-body py-2">
    <div style="display:grid; grid-template-columns:max-content 1fr; align-items:center; gap:0.4rem 1rem;">
      <label><?php echo html_radio_field('conv_type', 'so', true, '', '') . ' ' . ($jID == 3 ? ORD_CONVERT_TO_PO : ORD_CONVERT_TO_SO); ?></label>
      <div><?php echo ($jID == 3 ? ORD_HEADING_NUMBER_4 : ORD_HEADING_NUMBER_10); ?> <?php echo html_input_field('so_num', $so_num, 'class="form-control form-control-sm d-inline-block" style="width:120px"'); ?></div>
      <?php if ($jID != 3) { ?>
      <label><?php echo html_radio_field('conv_type', 'inv', false, '', '') . ' ' . ORD_CONVERT_TO_INV; ?></label>
      <div><?php echo ORD_HEADING_NUMBER_12; ?> <?php echo html_input_field('inv_num', $inv_num, 'class="form-control form-control-sm d-inline-block" style="width:120px"'); ?></div>
      <?php } ?>
    </div>
    <p class="mt-2 mb-0 text-muted small"><?php echo ORD_SO_INV_MESSAGE; ?></p>
  </div>
</div>
</div>
</div>
</form>
