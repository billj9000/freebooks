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
//  Path: /modules/phreepos/pages/admin/template_tab_general.php
//
?>
<div class="tab-pane fade show active" id="tab_general" role="tabpanel">
  <div class="fb-fieldset mb-3 mt-2">
    <div class="fb-fieldset-legend"><?php echo MENU_HEADING_CONFIG; ?></div>
    <table class="table table-borderless table-sm mx-auto" style="width:auto">
      <tbody>
        <tr>
          <td><?php echo PHREEPOS_REQUIRE_ADDRESS_DESC; ?></td>
          <td><?php echo html_pull_down_menu('phreepos_require_address', $sel_yes_no, $_POST['phreepos_require_address'] ? $_POST['phreepos_require_address'] : PHREEPOS_REQUIRE_ADDRESS, 'class="form-select form-select-sm"'); ?></td>
        </tr>
        <tr>
          <td><?php echo PHREEPOS_DISPLAY_WITH_TAX_DESC; ?></td>
          <td><?php echo html_pull_down_menu('phreepos_display_with_tax', $sel_yes_no, $_POST['phreepos_display_with_tax'] ? $_POST['phreepos_display_with_tax'] : PHREEPOS_DISPLAY_WITH_TAX, 'class="form-select form-select-sm"'); ?></td>
        </tr>
        <tr>
          <td><?php echo PHREEPOS_DISCOUNT_OF_DESC; ?></td>
          <td><?php echo html_pull_down_menu('phreepos_discount_of', $sel_yes_no, $_POST['phreepos_discount_of'] ? $_POST['phreepos_discount_of'] : PHREEPOS_DISCOUNT_OF, 'class="form-select form-select-sm"'); ?></td>
        </tr>
        <tr>
          <td><?php echo PHREEPOS_ROUNDING_DESC; ?></td>
          <td><?php echo html_pull_down_menu('phreepos_rounding', $sel_rounding, $_POST['phreepos_rounding'] ? $_POST['phreepos_rounding'] : PHREEPOS_ROUNDING, 'class="form-select form-select-sm"'); ?></td>
        </tr>
        <tr>
          <td><?php echo PHREEPOS_ENABLE_DIRECT_PRINTING_DESC; ?></td>
          <td><?php echo html_pull_down_menu('phreepos_enable_direct_printing', $sel_yes_no, $_POST['phreepos_enable_direct_printing'] ? $_POST['phreepos_enable_direct_printing'] : PHREEPOS_ENABLE_DIRECT_PRINTING, 'class="form-select form-select-sm"'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
