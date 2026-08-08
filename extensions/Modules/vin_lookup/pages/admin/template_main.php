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
//  Path: /modules/vin_lookup/pages/admin/template_main.php
//
echo html_form('admin', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
if ($security_level > 1) $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
else                      $toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->add_help('13.02');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<div class="card fb-card mb-3" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header"><?php echo MODULE_VIN_LOOKUP_TITLE; ?></div>
  <div class="card-body py-2 px-3">
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto" style="width:180px"><?php echo VIN_LBL_API_KEY; ?></div>
      <div class="col-auto"><?php echo html_input_field('vin_lookup_api_key',
        defined('VIN_LOOKUP_API_KEY') ? VIN_LOOKUP_API_KEY : '',
        'class="form-control form-control-sm" style="width:340px" maxlength="128"'); ?></div>
    </div>
    <div class="row g-2 align-items-center mb-2">
      <div class="col-auto" style="width:180px"><?php echo VIN_LBL_PACKAGES; ?></div>
      <div class="col-auto"><?php echo html_input_field('vin_lookup_packages',
        defined('VIN_LOOKUP_PACKAGES') ? VIN_LOOKUP_PACKAGES : 'VehicleDetails',
        'class="form-control form-control-sm" style="width:340px" maxlength="255"'); ?></div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-auto" style="width:180px"><?php echo VIN_LBL_ENDPOINT; ?></div>
      <div class="col-auto"><?php echo html_input_field('vin_lookup_endpoint',
        defined('VIN_LOOKUP_ENDPOINT') ? VIN_LOOKUP_ENDPOINT : 'https://uk.api.vehicledataglobal.com/r2/lookup',
        'class="form-control form-control-sm" style="width:340px" maxlength="255"'); ?></div>
    </div>
  </div>
</div>
</form>

<div class="fb-fieldset mb-2" style="width:fit-content">
  <div class="fb-fieldset-legend"><?php echo VIN_TEXT_TEST_CONNECTION; ?></div>
  <div class="row g-2 align-items-center mb-2">
    <div class="col-auto"><?php echo VIN_LBL_REGISTRATION; ?></div>
    <div class="col-auto">
      <input type="text" id="test_vrm" name="test_vrm" class="form-control form-control-sm text-uppercase" style="width:160px" maxlength="16">
    </div>
    <div class="col-auto">
      <button type="button" class="btn btn-secondary btn-sm"
        hx-post="index.php?module=vin_lookup&amp;page=ajax&amp;op=test"
        hx-include="#test_vrm,#vin_lookup_api_key,#vin_lookup_packages,#vin_lookup_endpoint"
        hx-target="#vin_test_result"
        hx-swap="innerHTML">
        <?php echo VIN_BTN_TEST; ?>
      </button>
    </div>
  </div>
  <div id="vin_test_result"></div>
</div>
