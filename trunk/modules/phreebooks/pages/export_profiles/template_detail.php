<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /modules/phreebooks/pages/export_profiles/template_detail.php
//
echo html_form('export_profiles', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('id', $id ?? '');
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) {
  $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'' . (($_REQUEST['action'] == 'new') ? 'save' : 'update') . '\')"';
} else {
  $toolbar->icon_list['save']['show']   = false;
}
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<div class="card fb-card mb-2 mx-auto" style="max-width:700px">
  <div class="card-header py-1 fb-widget-header"><?php echo PAGE_TITLE; ?></div>
  <div class="card-body py-2 px-3">
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto"><?php echo PO_EXPORT_COL_NAME; ?></div>
      <div class="col-auto"><?php echo html_input_field('profile_name', $profile_name ?? '', 'class="form-control form-control-sm" size="30" maxlength="64"'); ?></div>
      <div class="col-auto"><?php echo PO_EXPORT_COL_EXTENSION; ?></div>
      <div class="col-auto"><?php echo html_input_field('file_extension', $file_extension ?? 'txt', 'class="form-control form-control-sm" size="6" maxlength="8"'); ?></div>
      <div class="col-auto"><?php echo TEXT_INACTIVE; ?>&nbsp;<?php echo html_checkbox_field('inactive', '1', (isset($inactive) && $inactive) ? ' checked' : ''); ?></div>
    </div>
    <div class="row g-2 align-items-center mb-3">
      <div class="col-auto"><?php echo PO_EXPORT_COL_LINE_ENDING; ?></div>
      <div class="col-auto">
        <?php echo html_pull_down_menu('line_ending', array(array('id' => 'crlf', 'text' => PO_EXPORT_LINE_ENDING_CRLF), array('id' => 'lf', 'text' => PO_EXPORT_LINE_ENDING_LF)), $line_ending ?? 'crlf', 'class="form-select form-select-sm"'); ?>
      </div>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo PO_EXPORT_COL_HEADER_TEMPLATE; ?></div>
      <?php echo html_textarea_field('header_template', 70, 2, $header_template ?? '', 'class="form-control form-control-sm font-monospace"'); ?>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo PO_EXPORT_COL_LINE_TEMPLATE; ?></div>
      <?php echo html_textarea_field('line_template', 70, 2, $line_template ?? '{vendor_sku};{qty}', 'class="form-control form-control-sm font-monospace"'); ?>
      <div class="form-text"><?php echo PO_EXPORT_PLACEHOLDER_HELP_LINE; ?></div>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo PO_EXPORT_COL_FOOTER_TEMPLATE; ?></div>
      <?php echo html_textarea_field('footer_template', 70, 2, $footer_template ?? '', 'class="form-control form-control-sm font-monospace"'); ?>
      <div class="form-text"><?php echo PO_EXPORT_PLACEHOLDER_HELP_HEADER; ?></div>
    </div>
  </div>
</div>
</form>
