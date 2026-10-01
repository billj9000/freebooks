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
//  Path: /modules/contacts/pages/main/template_i_general.php
//
$dept_rep_display = $cInfo->dept_rep_id ? gen_get_contact_name($cInfo->dept_rep_id) . ' [' . (int)$cInfo->dept_rep_id . ']' : '';
// *********************** Display account information ****************************** ?>
<div id="tab_general" class="tab-pane">
  <fieldset>
    <legend><?php echo ACT_CATEGORY_CONTACT; ?></legend>
    <table>
      <tr>
       <td align="right"><?php echo ACT_SHORT_NAME; ?></td>
       <td>
	     <?php echo html_input_field('short_name', $cInfo->short_name, 'size="21" maxlength="64"', true); ?>
         <?php echo ' ' . TEXT_INACTIVE . ' '; ?>
         <?php echo html_checkbox_field('inactive', '1', $cInfo->inactive); ?>
	   </td>
       <td align="right"><?php echo TEXT_TITLE; ?></td>
       <td><?php echo html_input_field('contact_middle', $cInfo->contact_middle, 'size="33" maxlength="64"', false); ?></td>
      </tr>
      <tr>
        <td align="right"><?php echo GEN_FIRST_NAME; ?></td>
        <td><?php echo html_input_field('contact_first', $cInfo->contact_first, 'size="33" maxlength="64"', false); ?></td>
        <td align="right"><?php echo GEN_LAST_NAME; ?></td>
        <td><?php echo html_input_field('contact_last', $cInfo->contact_last, 'size="33" maxlength="64"', false); ?></td>
      </tr>
      <tr>
        <td align="right"><?php echo ACT_ACCOUNT_NUMBER; ?></td>
        <td><?php echo html_input_field('account_number', $cInfo->account_number, 'size="17" maxlength="16"'); ?></td>
        <td align="right"><?php echo ACT_ID_NUMBER; ?></td>
        <td><?php echo html_input_field('gov_id_number', $cInfo->gov_id_number, 'size="17" maxlength="32"'); ?></td>
      </tr>
      <tr>
        <td align="right"><?php echo TEXT_LINK_TO . ' '; ?></td>
        <td>
          <input type="text" id="dept_rep_search" list="dept_rep_list" autocomplete="off"
                 value="<?php echo htmlspecialchars($dept_rep_display); ?>"
                 onkeyup="loadContacts()" onchange="selectContact(this)">
          <input type="hidden" name="dept_rep_id" id="dept_rep_id" value="<?php echo (int)$cInfo->dept_rep_id; ?>">
          <datalist id="dept_rep_list"></datalist>
        </td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
    </table>
  </fieldset>

<?php // *********************** Mailing/Main Address (only one allowed) ****************************** ?>
  <fieldset>
    <legend><?php echo ACT_CATEGORY_M_ADDRESS; ?></legend>
    <table id="<?php echo $type; ?>m_address_form" class="pkg-table">
      <?php echo draw_address_fields($cInfo, $type.'m', false, true, false); ?>
    </table>
  </fieldset>
<?php // *********************** Attachments  ************************************* ?>
  <div>
   <fieldset>
   <legend><?php echo TEXT_ATTACHMENTS; ?></legend>
   <table class="pkg-table" style="margin-left:auto;margin-right:auto;">
    <thead>
     <tr><th colspan="3"><?php echo TEXT_ATTACHMENTS; ?></th></tr>
     <tr>
      <th><?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?></th>
      <th><?php echo TEXT_FILENAME; ?></th>
      <th><?php echo TEXT_ACTION; ?></th>
     </tr>
    </thead>
    <tbody>
     <tr><td colspan="3"><?php echo TEXT_SELECT_FILE_TO_ATTACH . ' ' . html_file_field('file_name'); ?></td></tr>
<?php
if (!empty($cInfo->attachments)) {
  foreach ($cInfo->attachments as $key => $value) {
    echo '<tr>';
    echo ' <td>' . html_checkbox_field('rm_attach_'.$key, '1', false) . '</td>' . chr(10);
    echo ' <td>' . $value . '</td>' . chr(10);
    echo ' <td><button type="button" class="btn btn-primary btn-sm" onclick="submitSeq(' . $key . ', \'download\', true)">' . TEXT_DOWNLOAD . '</button></td>';
    echo '</tr>' . chr(10);
  }
} else {
  echo '<tr><td colspan="3">' . TEXT_NO_DOCUMENTS . '</td></tr>';
} ?>
    </tbody>
   </table>
   </fieldset>
  </div>
</div>