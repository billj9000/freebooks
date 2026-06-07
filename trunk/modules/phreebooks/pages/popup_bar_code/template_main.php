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
//  Path: /modules/phreebooks/pages/popup_bar_code/template_main.php
//
echo html_form('bar_code', FILENAME_DEFAULT) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
?>
<div class="d-flex justify-content-center mt-3">
<div class="d-inline-flex flex-column gap-2">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo POPUP_BAR_CODE_TITLE; ?></div>
  <div class="card-body py-2">
    <p class="mb-2"><?php echo ORD_BAR_CODE_INTRO; ?></p>
    <div style="display:grid; grid-template-columns:max-content 1fr; align-items:center; gap:0.25rem 0.5rem;">
      <label class="fw-normal"><?php echo TEXT_QUANTITY; ?></label>
      <?php echo html_input_field('qty', '1', 'class="form-control form-control-sm" style="width:80px"'); ?>
      <label class="fw-normal"><?php echo TEXT_UPC_CODE; ?></label>
      <div class="d-flex gap-1 align-items-center">
        <?php echo html_input_field('upc', '', 'class="form-control form-control-sm" style="width:160px"'); ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="setReturnItem(true)"><?php echo TEXT_SAVE; ?></button>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</form>
