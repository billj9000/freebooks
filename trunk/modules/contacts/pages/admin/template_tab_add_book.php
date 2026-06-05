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
//  Path: /modules/contacts/pages/admin/template_tab_add_book.php
//
?>
<div class="tab-pane fade" id="tab_add_book" role="tabpanel">
  <div class="card fb-card mt-2 mb-2">
    <div class="card-header py-1"><?php echo TEXT_BILLING_PREFS; ?></div>
    <div class="card-body py-2 px-3" style="font-size:11px">
<?php
$add_book_fields = array(
  'address_book_contact_required'        => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_CONTACT),        'const' => 'ADDRESS_BOOK_CONTACT_REQUIRED'),
  'address_book_address1_required'       => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_ADDRESS1),       'const' => 'ADDRESS_BOOK_ADDRESS1_REQUIRED'),
  'address_book_address2_required'       => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_ADDRESS2),       'const' => 'ADDRESS_BOOK_ADDRESS2_REQUIRED'),
  'address_book_city_town_required'      => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_CITY_TOWN),      'const' => 'ADDRESS_BOOK_CITY_TOWN_REQUIRED'),
  'address_book_state_province_required' => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_STATE_PROVINCE), 'const' => 'ADDRESS_BOOK_STATE_PROVINCE_REQUIRED'),
  'address_book_postal_code_required'    => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_POSTAL_CODE),    'const' => 'ADDRESS_BOOK_POSTAL_CODE_REQUIRED'),
  'address_book_telephone1_required'     => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_TELEPHONE1),     'const' => 'ADDRESS_BOOK_TELEPHONE1_REQUIRED'),
  'address_book_email_required'          => array('label' => sprintf(CONTACT_BILL_FIELD_REQ, GEN_EMAIL),          'const' => 'ADDRESS_BOOK_EMAIL_REQUIRED'),
);
?>
      <div style="display:table">
<?php foreach ($add_book_fields as $name => $info):
  $val = $_POST[$name] ? $_POST[$name] : (defined($info['const']) ? constant($info['const']) : '0');
?>
        <div style="display:table-row">
          <div style="display:table-cell; padding-right:16px; padding-bottom:3px; white-space:nowrap; vertical-align:middle"><?php echo $info['label']; ?></div>
          <div style="display:table-cell; padding-bottom:3px; vertical-align:middle"><?php echo html_pull_down_menu($name, $sel_yes_no, $val, 'class="form-select form-select-sm"'); ?></div>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
