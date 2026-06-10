<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /modules/contacts/pages/main/template_main.php
//
echo html_form('contacts', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'list'))) . chr(10);
// include hidden fields
echo html_hidden_field('action', '')   . chr(10);
echo html_hidden_field('rowSeq', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 1) $toolbar->add_icon('new', 'onclick="submitToDo(\'new\')"', $order = 10);
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
if (!$cInfo->help == '') $toolbar->add_help($cInfo->help);
echo $toolbar->build_toolbar($add_search = true);
// Build the page
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div id="filter_bar" class="d-flex align-items-center gap-2 mb-2">
  <span><?php echo TEXT_FILTERS; ?></span>
  <label><?php echo TEXT_SHOW_INACTIVE . '&nbsp;' . html_checkbox_field('f0', '1', $_SESSION['f0']); ?></label>
  <button type="button" class="btn btn-primary btn-sm" onclick="document.forms[0].submit()"><?php echo TEXT_APPLY; ?></button>
</div>
<div style="height:19px"><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . constant('ACT_' . strtoupper($type) . '_TYPE_NAME')); ?>
<div style="float:right"><?php echo $query_split->display_links(); ?></div>
</div>
<table class="pkg-table">
 <thead>
  <tr><?php echo $list_header; ?></tr>
 </thead>
 <tbody>
  <?php
  $odd = true;
    while (!$query_result->EOF) {
	  $bkgnd          = ($query_result->fields['inactive']) ? ' style="background-color:pink"' : '';
	  $attach_exists  = $query_result->fields['attachments'] ? true : false;
  ?>
  <tr class="<?php echo $odd?'odd':'even'; ?>" style="cursor:pointer">
    <td<?php echo $bkgnd; ?> onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['short_name']); ?></td>
    <td<?php echo $bkgnd; ?> onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($type == 'e' ? $query_result->fields['contact_first'] . ' ' . $query_result->fields['contact_last'] : $query_result->fields['primary_name']); ?></td>
	<td onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['address1']); ?></td>
	<td onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['city_town']); ?></td>
	<td onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['state_province']); ?></td>
	<td onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['postal_code']); ?></td>
	<td onclick="submitSeq(<?php echo $query_result->fields['id']; ?>, 'edit')"><?php echo htmlspecialchars($query_result->fields['telephone1']); ?></td>
	<td align="right">
<?php
// build the action toolbar
	  // first pull in any extra buttons, this is dynamic since each row can have different buttons
	  if (function_exists('add_extra_action_bar_buttons')) echo add_extra_action_bar_buttons($query_result->fields);
	  if ($security_level > 1) echo html_icon('mimetypes/x-office-presentation.png', TEXT_SALES, 'small', 'onclick="contactChart(\'annual_sales\', '.$query_result->fields['id'].')"') . chr(10);
	  if ($security_level > 1) echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 'onclick="submitSeq(' . $query_result->fields['id'] . ', \'edit\')"') . chr(10);
	  if ($attach_exists) {
	    echo html_icon('status/mail-attachment.png', TEXT_DOWNLOAD_ATTACHMENT,'small', 'onclick="submitSeq(' . $query_result->fields['id'] . ', \'dn_attach\', true)"') . chr(10);
	  }
	  if ($security_level > 3) echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . ACT_WARN_DELETE_ACCOUNT . '\')) submitSeq(' . $query_result->fields['id'] . ', \'delete\')"') . chr(10);
?>
	</td>
  </tr>
<?php
      $query_result->MoveNext();
      $odd = !$odd;
    }
?>
 </tbody>
</table>
<div style="float:right"><?php echo $query_split->display_links(); ?></div>
<div><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . constant('ACT_' . strtoupper($type) . '_TYPE_NAME')); ?></div>

<div class="modal fade" id="contact_chart_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="contact_chart_modal_title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div id="contact_chart"></div>
      </div>
    </div>
  </div>
</div>

</form>
