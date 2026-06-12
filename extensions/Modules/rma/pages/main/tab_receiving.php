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
//  Path: /modules/rma/pages/main/tab_receiving.php
//
?>
<div class="tab-pane" id="tab_receiving">
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo TEXT_DETAILS; ?></div>
  <table style="width:100%">
    <tr>
      <td align="right"><?php echo TEXT_RECEIVE_DATE; ?></td>
      <td><?php echo html_calendar_field($cal_rcv); ?></td>
      <td align="right"><?php echo TEXT_RECEIVE_CARRIER; ?></td>
      <td><?php echo html_input_field('receive_carrier', $cInfo->receive_carrier); ?></td>
    </tr>
    <tr>
      <td align="right" valign="top"><?php echo TEXT_RECEIVED_BY; ?></td>
      <td valign="top"><?php echo html_pull_down_menu('received_by', $user_choices, ($cInfo->received_by ? $cInfo->received_by : '')); ?></td>
      <td align="right"><?php echo TEXT_RECEIVE_TRACKING_NUM; ?></td>
      <td><?php echo html_input_field('receive_tracking', $cInfo->receive_tracking); ?></td>
    </tr>
    <tr>
      <td align="right" valign="top"><?php echo TEXT_NOTES; ?></td>
      <td colspan="3"><?php echo html_textarea_field('receive_notes', 60, 3, $cInfo->receive_notes, '',true); ?></td>
    </tr>
  </table>
  <table class="table table-sm table-bordered mt-2">
    <thead class="table-dark">
      <tr>
        <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
        <th><?php echo TEXT_QUANTITY; ?></th>
        <th><?php echo TEXT_SKU; ?></th>
        <th><?php echo TEXT_DESCRIPTION; ?></th>
        <th><?php echo TEXT_DATE_MANUFACTURE; ?></th>
        <th><?php echo TEXT_DATE_WARRANTY; ?></th>
      </tr>
    </thead>
    <tbody id="rcv_table">
<?php
if (!empty($receive_details)) {
  for ($i=0; $i<count($receive_details); $i++) { ?>
      <tr>
        <td align="center"><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick = "if (confirm(image_delete_msg)) this.closest(\'tr\').remove();"'); ?></td>
        <td align="center"><?php echo html_input_field("rcv_qty[".($i+1)."]", $receive_details[$i]['qty'], 'size="7" maxlength="6" style="text-align:right"'); ?></td>
        <td nowrap="nowrap" align="center"><?php echo html_input_field("rcv_sku[".($i+1)."]", $receive_details[$i]['sku'], 'size="' . (MAX_INVENTORY_SKU_LENGTH + 1) . '" onfocus="activeField(this, \''.TEXT_SEARCH.'\')" onblur="inactiveField(this, \''.TEXT_SEARCH.'\')"'); ?>
        <?php echo '&nbsp;' . html_icon('status/folder-open.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="ItemList(\'rcv_\','.($i+1).')"'); ?>
        </td>
        <td><?php echo html_input_field("rcv_desc[".($i+1)."]",  $receive_details[$i]['desc'], 'size="32"'); ?></td>
        <td><?php echo html_input_field("rcv_mfg[".($i+1)."]",   $receive_details[$i]['mfg'],  'size="32"'); ?></td>
        <td><?php echo html_input_field("rcv_wrnty[".($i+1)."]", $receive_details[$i]['wrnty'],'size="12"'); ?></td>
      </tr>
<?php
  }
} else {
  $hidden_fields .= '  <script type="text/javascript">addRcvRow();</script>' . chr(10);
} ?>
    </tbody>
  </table>
  <div><?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addRcvRow()"'); ?></div>
</div>
</div>
