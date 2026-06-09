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
//  Path: /modules/shipping/pages/ship_mgr/template_main.php
//
echo html_form('ship_mgr', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action',    '') . chr(10);
echo html_hidden_field('rowSeq',    '') . chr(10);
echo html_hidden_field('module_id', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('09');
echo $toolbar->build_toolbar(false, false, $cal_ship);
?>
<h1><?php echo BOX_SHIPPING_MANAGER; ?></h1>

<!-- Tab nav -->
<nav>
  <div class="nav nav-tabs fb-tabs" id="shippingtabs" role="tablist">
<?php
$image_types = array('gif', 'png', 'jpg', 'jpeg');
$path        = DIR_WS_MODULES . 'shipping/methods/';
$first       = true;
foreach ($installed_modules as $value):
  $image_file = DIR_WS_MODULES . 'shipping/images/no_logo.png';
  foreach ($image_types as $ext) {
    if (file_exists($path . $value['id'] . '/images/logo.' . $ext)) {
      $image_file = $path . $value['id'] . '/images/logo.' . $ext;
      break;
    }
  }
?>
    <button class="nav-link<?php echo $first ? ' active' : ''; ?>"
            id="tab-btn-<?php echo $value['id']; ?>"
            data-bs-toggle="tab"
            data-bs-target="#tab_<?php echo $value['id']; ?>"
            type="button" role="tab">
      <?php echo html_image($image_file, $value['text'], 0, 30); ?>
    </button>
<?php $first = false; endforeach; ?>
  </div>
</nav>

<!-- Tab content -->
<div class="tab-content fb-tab-content" id="shippingtabsContent">
<?php
$first = true;
foreach ($installed_modules as $value):
  $method_id = $value['id'];
?>
  <div class="tab-pane<?php echo $first ? ' show active' : ''; ?>"
       id="tab_<?php echo $method_id; ?>"
       role="tabpanel">
    <?php include_once(DIR_FS_MODULES . 'shipping/methods/' . $method_id . '/ship_mgr.php'); ?>
  </div>
<?php $first = false; endforeach; ?>
</div>

<!-- Modal for getDialog (endicia passphrase etc.) -->
<div class="modal fade" id="shipping_modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-1 fb-widget-header">
        <h5 class="modal-title text-white"><?php echo BOX_SHIPPING_MANAGER; ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="shipping_dialog"></div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-primary btn-sm" onclick="submitModalAction()"><?php echo TEXT_SUBMIT; ?></button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo TEXT_CANCEL; ?></button>
      </div>
    </div>
  </div>
</div>
</form>
