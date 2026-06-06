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
//  Path: /modules/contacts/pages/main/template_addbook.php
//
?>
<div id="tab_addbook" class="tab-pane fade" role="tabpanel">
<?php if (defined('MODULE_SHIPPING_STATUS')): ?>
<div class="fb-fieldset mt-2 mb-2">
  <div class="fb-fieldset-legend"><?php echo ACT_CATEGORY_S_ADDRESS; ?></div>
  <table id="<?php echo $type; ?>s_address_form" style="width:100%">
    <tr><td><?php echo ACT_SHIPPING_MESSAGE; ?></td></tr>
    <?php echo draw_address_fields($cInfo, $type.'s', true); ?>
  </table>
</div>
<?php endif; ?>
<div class="fb-fieldset mt-2 mb-2">
  <div class="fb-fieldset-legend"><?php echo ACT_CATEGORY_B_ADDRESS; ?></div>
  <table id="<?php echo $type; ?>b_address_form" style="width:100%">
    <tr><td><?php echo ACT_BILLING_MESSAGE; ?></td></tr>
    <?php echo draw_address_fields($cInfo, $type.'b', true); ?>
  </table>
</div>
</div>
