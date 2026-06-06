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
//  Path: /modules/phreeform/pages/main/template_main.php
//
echo html_form('phreeform', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
echo html_hidden_field('id',     $id) . chr(10);
echo html_hidden_field('action',    '') . chr(10);
echo html_hidden_field('rowSeq',  '') . chr(10);
echo html_hidden_field('newName', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 1) $toolbar->add_icon('import', 'onclick="ReportPopup(\'import\')"', $order = 50);
$toolbar->icon_list['home'] = array(
	'show'   => true, 
	'icon'   => 'actions/go-home.png',
	'params' => 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"', 
	'text'   => TEXT_HOME, 
	'order'  => '25',
);
if ($security_level > 1) {
  $toolbar->icon_list['new_rpt'] = array(
	'show'   => true, 
	'icon'   => 'mimetypes/text-x-generic.png',
	'params' => 'onclick="ReportPopup(\'new_rpt\')"', 
	'text'   => TEXT_NEW_REPORT, 
	'order'  => '30',
  );
  $toolbar->icon_list['new_frm'] = array(
	'show'   => true, 
	'icon'   => 'mimetypes/text-html.png',
	'params' => 'onclick="ReportPopup(\'new_frm\')"', 
	'text'   => TEXT_NEW_FORM, 
	'order'  => '35',
  );
}
$toolbar->add_help();
echo $toolbar->build_toolbar($add_search = true);

?>
<h1><?php echo TEXT_REPORTS; ?></h1>
<div class="row">
  <div class="col-md-4">
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo TEXT_DOCUMENTS; ?></div>
      <?php echo '<a href="javascript:Expand(\'dc_\');">' . TEXT_EXPAND_ALL . '</a> - <a href="javascript:Collapse(\'dc_\');">' . TEXT_COLLAPSE_ALL . '</a><br />' . chr(10); ?>
      <?php echo build_dir_html('dir_tree', $toc_array); ?>
    </div>
  </div>
  <div class="col-md-8">
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo TEXT_DETAILS; ?></div>
      <div id="rightColumn">
        <?php if (file_exists($div_template)) {
          include($div_template);
          echo $fieldset_content;
        } ?>
      </div>
    </div>
  </div>
</div>
</form>
