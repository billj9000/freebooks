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
//  Path: /modules/shipping/pages/popup_shipping/template_main.php
//
$country_list = gen_get_countries();
$sel_sm = 'class="form-select form-select-sm"';
?>
<?php if (!empty($_SESSION['messageToStack'])): ?>
<div class="alert alert-warning mb-3" style="font-size:12px">
  <?php foreach ($_SESSION['messageToStack'] as $msg): ?>
  <div><?php echo $msg['text']; ?></div>
  <?php endforeach; ?>
</div>
<?php $messageStack->reset(); ?>
<?php endif; ?>
<form hx-post="index.php?module=shipping&page=popup_shipping"
      hx-target="#shipping-estimator-body"
      hx-swap="innerHTML"
      hx-indicator="#est-spinner">

  <input type="hidden" name="action"        value="rates">
  <input type="hidden" name="pkg_total"     id="est-pkg-total"  value="<?php echo htmlspecialchars($pkg->pkg_total); ?>">
  <input type="hidden" name="pkg_item_count" id="est-pkg-count" value="<?php echo htmlspecialchars($pkg->pkg_item_count); ?>">

  <!-- Ship date -->
  <div class="row g-2 mb-3">
    <div class="col-auto">
      <label class="form-label"><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></label>
      <input type="date" class="form-control form-control-sm" name="ship_date"
             value="<?php echo htmlspecialchars($pkg->ship_date ?? date('Y-m-d')); ?>">
    </div>
  </div>

  <!-- From / To -->
  <div class="row g-2 mb-3">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_TEXT_SHIP_FROM_CITY; ?></div>
        <div class="card-body py-2">
          <div class="row g-1">
            <div class="col-7">
              <label class="form-label">City</label>
              <input type="text" class="form-control form-control-sm" name="ship_city_town"
                     value="<?php echo htmlspecialchars($pkg->ship_city_town); ?>">
            </div>
            <div class="col-5">
              <label class="form-label">State</label>
              <input type="text" class="form-control form-control-sm" name="ship_state_province"
                     value="<?php echo htmlspecialchars($pkg->ship_state_province); ?>">
            </div>
            <div class="col-5">
              <label class="form-label"><?php echo SHIPPING_TEXT_SHIP_FROM_ZIP; ?></label>
              <input type="text" class="form-control form-control-sm" name="ship_postal_code"
                     value="<?php echo htmlspecialchars($pkg->ship_postal_code); ?>">
            </div>
            <div class="col-7">
              <label class="form-label"><?php echo SHIPPING_TEXT_SHIP_FROM_COUNTRY; ?></label>
              <?php echo html_pull_down_menu('ship_country_code', $country_list, $pkg->ship_country_code, 'id="est-from-country" ' . $sel_sm); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header py-1 fb-widget-header"><?php echo SHIPPING_TEXT_SHIP_TO_CITY; ?></div>
        <div class="card-body py-2">
          <div class="row g-1">
            <div class="col-7">
              <label class="form-label">City</label>
              <input type="text" class="form-control form-control-sm" name="ship_to_city" id="est-to-city"
                     value="<?php echo htmlspecialchars($pkg->ship_to_city); ?>">
            </div>
            <div class="col-5">
              <label class="form-label">State</label>
              <input type="text" class="form-control form-control-sm" name="ship_to_state" id="est-to-state"
                     value="<?php echo htmlspecialchars($pkg->ship_to_state); ?>">
            </div>
            <div class="col-5">
              <label class="form-label"><?php echo SHIPPING_TEXT_SHIP_TO_ZIP; ?></label>
              <input type="text" class="form-control form-control-sm" name="ship_to_postal_code" id="est-to-postal"
                     value="<?php echo htmlspecialchars($pkg->ship_to_postal_code); ?>">
            </div>
            <div class="col-7">
              <label class="form-label"><?php echo SHIPPING_TEXT_SHIP_TO_COUNTRY; ?></label>
              <?php echo html_pull_down_menu('ship_to_country_code', $country_list, $pkg->ship_to_country_code, 'id="est-to-country" ' . $sel_sm); ?>
            </div>
            <div class="col-12 d-flex align-items-center gap-1 pt-1">
              <input type="checkbox" class="form-check-input mt-0" name="residential_address" value="1"
                     <?php echo ($pkg->residential_address ? 'checked' : ''); ?>>
              <label class="form-check-label"><?php echo SHIPPING_RESIDENTIAL_ADDRESS; ?></label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Package -->
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <label class="form-label"><?php echo TEXT_WEIGHT; ?></label>
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" name="pkg_weight" id="est-pkg-weight"
               value="<?php echo $pkg->pkg_weight; ?>">
        <?php echo html_pull_down_menu('pkg_weight_unit', gen_build_pull_down($shipping_defaults['weight_unit']), $pkg->pkg_weight_unit, $sel_sm); ?>
      </div>
    </div>
    <div class="col-md-8">
      <label class="form-label"><?php echo SHIPPING_TEXT_DIMENSIONS; ?></label>
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" name="pkg_length" placeholder="<?php echo TEXT_LENGTH; ?>"
               value="<?php echo $pkg->pkg_length; ?>" style="max-width:4rem">
        <span class="input-group-text px-1">×</span>
        <input type="text" class="form-control" name="pkg_width" placeholder="<?php echo TEXT_WIDTH; ?>"
               value="<?php echo $pkg->pkg_width; ?>" style="max-width:4rem">
        <span class="input-group-text px-1">×</span>
        <input type="text" class="form-control" name="pkg_height" placeholder="<?php echo TEXT_HEIGHT; ?>"
               value="<?php echo $pkg->pkg_height; ?>" style="max-width:4rem">
        <?php echo html_pull_down_menu('pkg_dimension_unit', gen_build_pull_down($shipping_defaults['dimension_unit']), $pkg->pkg_dimension_unit, $sel_sm); ?>
      </div>
    </div>
  </div>

  <!-- Methods -->
  <div class="mb-3">
    <div class="fb-widget-header px-2 py-1 mb-1"><?php echo SHIPPING_TEXT_METHODS; ?></div>
    <div class="d-flex flex-wrap gap-3">
      <?php foreach ($methods as $method): ?>
      <div class="form-check form-check-inline mb-0">
        <input type="checkbox" class="form-check-input" name="ship_method_<?php echo $method; ?>" value="1" checked>
        <label class="form-check-label"><?php echo constant('MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_TITLE'); ?></label>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Buttons -->
  <div class="d-flex align-items-center gap-2">
    <button type="submit" class="btn btn-primary btn-sm"><?php echo TEXT_CONTINUE; ?></button>
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo TEXT_CANCEL; ?></button>
    <span id="est-spinner" class="htmx-indicator ms-2 text-muted" style="font-size:11px"><?php echo TEXT_PLEASE_WAIT; ?></span>
  </div>

</form>
