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
//  Path: /modules/contacts/pages/main/template_general.php
//
?>
<div id="tab_general" class="tab-pane">
  <fieldset>
    <legend><?php echo ACT_CATEGORY_CONTACT; ?></legend>
    <table>
      <tr>
        <td align="right"><?php echo constant('ACT_' . strtoupper($type) . '_SHORT_NAME') . ($cInfo->auto_type == false ? '' : ' ' . ACT_ID_AUTO_FILL ); ?></td>
        <td><?php echo html_input_field('short_name', $cInfo->short_name, 'size="21" maxlength="64"', $cInfo->auto_type == false ? true : false); ?></td>
<?php if ($type == 'c') { ?>
        <td align="right"><?php echo CONTACT_LEVEL; ?></td>
        <td><?php echo html_pull_down_menu('contacts_level', gen_build_pull_down($contacts_levels), $cInfo->contacts_level ? $cInfo->contacts_level : '0'); ?></td>
        <td align="right"><?php echo constant('ACT_' . strtoupper($type) . '_REP_ID'); ?></td>
        <td><?php echo html_pull_down_menu('dept_rep_id', $sales_rep_array, $cInfo->dept_rep_id ? $cInfo->dept_rep_id : 'r'); ?></td>
<?php } else { ?>
        <td align="right"><?php echo constant('ACT_' . strtoupper($type) . '_REP_ID'); ?></td>
        <td><?php echo html_pull_down_menu('dept_rep_id', $sales_rep_array, $cInfo->dept_rep_id ? $cInfo->dept_rep_id : 'r'); ?></td>
        
<?php } ?>
      </tr>
      <tr>
        <td align="right"><?php echo TEXT_INACTIVE; ?></td>
        <td><?php echo html_checkbox_field('inactive', '1', $cInfo->inactive); ?></td>
      </tr>
      <tr>
      <td align="right"><?php echo GEN_FIRST_NAME; ?></td>
        <td><?php echo html_input_field('contact_first', $cInfo->contact_first, 'size="33" maxlength="64"', false); ?></td>
        <td align="right"><?php echo GEN_MIDDLE_NAME; ?></td>
        <td><?php echo html_input_field('contact_middle', $cInfo->contact_middle, 'size="33" maxlength="64"', false); ?></td>
        <td align="right"><?php echo GEN_LAST_NAME; ?></td>
        <td><?php echo html_input_field('contact_last', $cInfo->contact_last, 'size="33" maxlength="64"', false); ?></td>
      </tr>
      <tr>
       <td align="right"><?php echo constant('ACT_' . strtoupper($type) . '_GL_ACCOUNT_TYPE'); ?></td>
       <td><?php echo html_pull_down_menu('gl_type_account', gen_coa_pull_down(), $_REQUEST['action']=='new' ? AR_DEF_GL_SALES_ACCT : $cInfo->gl_type_account); ?></td>
       <td align="right"><?php echo constant('ACT_' . strtoupper($type) . '_ACCOUNT_NUMBER'); ?></td>
       <td><?php echo html_input_field('account_number', $cInfo->account_number, 'size="17" maxlength="16"'); ?></td>
       <td align="right"><?php echo TEXT_DEFAULT_PRICE_SHEET; ?></td>
       <td><?php echo html_pull_down_menu('price_sheet', get_price_sheet_data(), $cInfo->price_sheet); ?></td>
      </tr>
      <tr>
       <td align="right"><?php echo constant('ACT_' . strtoupper($type) . '_ID_NUMBER'); ?></td>
       <td><?php echo html_input_field('gov_id_number', $cInfo->gov_id_number, 'size="17" maxlength="32"'); ?></td>
	   <td align="right"><?php echo INV_ENTRY_ITEM_TAXABLE; ?></td>
       <td><?php echo html_pull_down_menu('tax_id', $tax_rates, $cInfo->tax_id); ?></td>
       <td><?php  echo ACT_CATEGORY_PAYMENT_TERMS; ?></td>
	   <td><?php
    	  echo html_hidden_field('terms', $cInfo->special_terms) . chr(10);
	      echo html_input_field('terms_text', gen_terms_to_language($cInfo->special_terms, true, $cInfo->terms_type), 'readonly="readonly" size="20"') . '&nbsp;' . chr(10);
    	  echo html_icon('apps/accessories-text-editor.png', ACT_TERMS_DUE, 'small', 'style="cursor:pointer" onclick="TermsList()"'); ?>
	   </td>
      </tr>
      <tr>
       <td align="right"><?php echo ACT_VAT_NUMBER; ?></td>
       <td><?php echo html_input_field('vat_number', $cInfo->vat_number ?? '', 'size="21" maxlength="20"'); ?></td>
       <td align="right"><?php echo ACT_EORI_NUMBER; ?></td>
       <td><?php echo html_input_field('eori_number', $cInfo->eori_number ?? '', 'size="21" maxlength="20"'); ?></td>
       <td align="right"><?php echo ACT_DEFAULT_CURRENCY; ?></td>
       <td><?php echo html_pull_down_menu('currency_code', gen_get_pull_down(TABLE_CURRENCIES, false, false, 'code', 'title'), $cInfo->currency_code ?: DEFAULT_CURRENCY); ?></td>
      </tr>
<?php if ($type == 'v' && defined('MODULE_PHREEBOOKS_STATUS')): ?>
      <tr>
       <td align="right"><?php echo ACT_PO_EXPORT_PROFILE; ?></td>
       <td><?php echo html_pull_down_menu('export_profile_id', gen_get_pull_down(TABLE_PURCHASE_EXPORT_PROFILE, true, 1, 'id', 'profile_name'), $cInfo->export_profile_id ?? ''); ?></td>
      </tr>
<?php endif; ?>
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