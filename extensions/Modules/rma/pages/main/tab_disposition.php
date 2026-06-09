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
//  Path: /modules/rma/pages/main/tab_disposition.php
//
?>
<div class="tab-pane" id="tab_disposition">
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo TEXT_DETAILS; ?></div>
  <p><?php echo RMA_DISPOSITION_DESC; ?></p>
  <div class="mb-2">
    <label><?php echo TEXT_NOTES; ?></label>
    <?php echo html_textarea_field('close_notes', 60, 3, $cInfo->close_notes, '', true); ?>
  </div>
  <table class="table table-sm table-bordered">
    <thead class="table-dark">
      <tr>
        <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
        <th><?php echo TEXT_QUANTITY; ?></th>
        <th><?php echo TEXT_SKU; ?></th>
        <th><?php echo TEXT_NOTES; ?></th>
        <th><?php echo TEXT_ACTION; ?></th>
      </tr>
    </thead>
    <tbody id="item_table">
<?php
if (!empty($close_details)) {
  for ($i=0; $i<count(is_array($close_details) ? $close_details : []); $i++) { ?>
      <tr>
        <td><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick = "if (confirm(image_delete_msg)) $(this).parent().parent().remove();"'); ?></td>
        <td><?php echo html_input_field("dis_qty[".($i+1)."]", $close_details[$i]['qty'], 'size="7" maxlength="6" style="text-align:right"'); ?></td>
        <td nowrap="nowrap"><?php echo html_input_field("dis_sku[".($i+1)."]", $close_details[$i]['sku'], 'size="24" onfocus="activeField(this, \''.TEXT_SEARCH.'\')" onblur="inactiveField(this, \''.TEXT_SEARCH.'\')"'); ?>
        <?php echo '&nbsp;' . html_icon('status/folder-open.png', TEXT_SEARCH, 'small', 'align="top" style="cursor:pointer" onclick="ItemList(\'dis_\','.($i+1).')"'); ?>
        </td>
        <td><?php echo html_input_field("dis_notes[".($i+1)."]", $close_details[$i]['notes'], 'size="48"'); ?></td>
        <td><?php echo html_pull_down_menu("dis_action[".($i+1)."]", gen_build_pull_down($action_codes), $close_details[$i]['action']); ?></td>
      </tr>
<?php
  }
} else {
  $hidden_fields .= '  <script type="text/javascript">addItemRow();</script>' . chr(10);
} ?>
    </tbody>
  </table>
  <div><?php echo html_icon('actions/list-add.png', TEXT_ADD, 'medium', 'onclick="addItemRow()"'); ?></div>
</div>
</div>
