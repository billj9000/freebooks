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
//  Path: /modules/zencart/pages/main/template_main.php
//
echo html_form('zencart', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
echo $toolbar->build_toolbar();
?>
<h1><?php echo BOX_ZENCART_MODULE; ?></h1>
<div class="d-flex justify-content-center">
  <div class="text-end mb-2">
    <img src="<?php echo DIR_WS_ADMIN . 'modules/zencart/images/zen-cart-logo.png'; ?>" alt="ZenCart Logo">
  </div>
</div>
<div class="d-flex justify-content-center">
  <div class="d-inline-flex flex-column gap-2" style="min-width:500px">

    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header"><?php echo ZENCART_BULK_UPLOAD_TITLE; ?></div>
      <div class="card-body py-2">
        <p class="mb-2"><?php echo ZENCART_BULK_UPLOAD_INFO; ?></p>
        <div class="d-flex align-items-center gap-2">
          <label><?php echo ZENCART_BULK_UPLOAD_TEXT; ?></label>
          <?php echo html_button_field('bulkupload', ZENCART_BULK_UPLOAD_BTN, 'onclick="submitToDo(\'bulkupload\')"'); ?>
          <div class="form-check ms-2 mb-0">
            <?php echo html_checkbox_field('include_images', '1', false, 'class="form-check-input"', false); ?>
            <label class="form-check-label"><?php echo ZENCART_INCLUDE_IMAGES; ?></label>
          </div>
        </div>
      </div>
    </div>

    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header"><?php echo ZENCART_PRODUCT_SYNC_TITLE; ?></div>
      <div class="card-body py-2">
        <p class="mb-2"><?php echo ZENCART_PRODUCT_SYNC_INFO; ?></p>
        <div class="d-flex align-items-center gap-2">
          <label><?php echo ZENCART_PRODUCT_SYNC_TEXT; ?></label>
          <?php echo html_button_field('sync', ZENCART_PRODUCT_SYNC_BTN, 'onclick="submitToDo(\'sync\')"'); ?>
        </div>
      </div>
    </div>

    <div class="card fb-card">
      <div class="card-header py-1 fb-widget-header"><?php echo ZENCART_SHIP_CONFIRM_TITLE; ?></div>
      <div class="card-body py-2">
        <p class="mb-2"><?php echo ZENCART_SHIP_CONFIRM_INFO; ?></p>
        <div style="display:grid;grid-template-columns:max-content 1fr;gap:4px 8px;align-items:center">
          <label><?php echo ZENCART_TEXT_CONFIRM_ON; ?></label>
          <div><?php echo html_calendar_field($cal_zc); ?></div>
          <label><?php echo ZENCART_SHIP_CONFIRM_TEXT; ?></label>
          <div><?php echo html_button_field('confirm', ZENCART_SHIP_CONFIRM_BTN, 'onclick="submitToDo(\'confirm\')"'); ?></div>
        </div>
      </div>
    </div>

  </div>
</div>
</form>
