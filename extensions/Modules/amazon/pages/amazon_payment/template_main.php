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
//  Path: /modules/amazon/pages/amazon_payment/template_main.php
//
echo html_form('amazon', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''.html_href_link(FILENAME_DEFAULT, '', 'SSL').'\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) {
  $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'import\')"';
} else {
  $toolbar->icon_list['save']['show']   = false;
}
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
  <div class="card-body py-2 text-center">
    <p><?php echo AMAZON_IMPORT_PAYMENT; ?></p>
    <?php echo html_file_field($upload_name); ?>
  </div>
</div>
</form>
