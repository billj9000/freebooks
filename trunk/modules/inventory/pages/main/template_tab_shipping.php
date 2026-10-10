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
//  Path: /modules/inventory/pages/main/template_tab_shipping.php

$wt_unit  = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')
  ? (['LBS' => 'lb', 'KGS' => 'kg'][SHIPPING_DEFAULT_WEIGHT_UNIT] ?? SHIPPING_DEFAULT_WEIGHT_UNIT) : '';
$dim_unit = defined('SHIPPING_DEFAULT_PKG_DIM_UNIT')
  ? (['IN' => 'in', 'CM' => 'cm'][SHIPPING_DEFAULT_PKG_DIM_UNIT] ?? SHIPPING_DEFAULT_PKG_DIM_UNIT) : '';

// A bundle that ships as its separate parts has no weight of its own to type: it is the
// sum of what is in it, recalculated from the Bill of Materials on every save (see
// pb::save()). Showing the field read-only says so without hiding the figure. Ticking
// "ships complete" hands the field back, because the parcel's real weight - packaging
// and all - is then a fact only the user knows.
$wt_derived = isset($cInfo->ship_complete) && empty($cInfo->ship_complete);
?>

<?php if (isset($cInfo->item_weight) || isset($cInfo->item_length)): ?>

<!-- ── Package Dimensions ─────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-header py-1 fb-widget-header text-center">
    <?php echo INV_SHIPPING_DIMENSIONS; ?>
    <?php if ($dim_unit): ?>
    <small class="ms-2">(<?php echo htmlspecialchars($dim_unit); ?>)</small>
    <?php endif; ?>
    <?php if (INV_SHIPPING_EXT_DIMS_HELP): ?>
    <small class="ms-2"><?php echo INV_SHIPPING_EXT_DIMS_HELP; ?></small>
    <?php endif; ?>
  </div>
  <div class="card-body py-2">
    <div class="row g-3">

      <?php if (isset($cInfo->item_weight)): ?>
      <div class="col-auto">
        <label class="form-label mb-0">
          <?php echo INV_ENTRY_ITEM_WEIGHT; ?>
          <?php if ($wt_unit): ?><small class="text-muted">(<?php echo htmlspecialchars($wt_unit); ?>)</small><?php endif; ?>
        </label>
        <?php echo html_input_field('item_weight', $currencies->precise($cInfo->item_weight),
          'class="form-control form-control-sm text-end" style="width:100px" maxlength="9"' . ($wt_derived ? ' readonly="readonly"' : ''), false); ?>
        <?php if ($wt_derived): ?>
        <small class="text-muted d-block"><?php echo INV_ITEM_WEIGHT_DERIVED_HELP; ?></small>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->item_length)): ?>
      <div class="col-auto">
        <label class="form-label mb-0">
          <?php echo INV_ENTRY_ITEM_LENGTH; ?>
          <?php if ($dim_unit): ?><small class="text-muted">(<?php echo htmlspecialchars($dim_unit); ?>)</small><?php endif; ?>
        </label>
        <?php echo html_input_field('item_length', $currencies->precise($cInfo->item_length), 'class="form-control form-control-sm text-end" style="width:100px"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->item_width)): ?>
      <div class="col-auto">
        <label class="form-label mb-0">
          <?php echo INV_ENTRY_ITEM_WIDTH; ?>
          <?php if ($dim_unit): ?><small class="text-muted">(<?php echo htmlspecialchars($dim_unit); ?>)</small><?php endif; ?>
        </label>
        <?php echo html_input_field('item_width', $currencies->precise($cInfo->item_width), 'class="form-control form-control-sm text-end" style="width:100px"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->item_height)): ?>
      <div class="col-auto">
        <label class="form-label mb-0">
          <?php echo INV_ENTRY_ITEM_HEIGHT; ?>
          <?php if ($dim_unit): ?><small class="text-muted">(<?php echo htmlspecialchars($dim_unit); ?>)</small><?php endif; ?>
        </label>
        <?php echo html_input_field('item_height', $currencies->precise($cInfo->item_height), 'class="form-control form-control-sm text-end" style="width:100px"', false); ?>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->ships_in_own_box)): ?>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <?php echo html_checkbox_field('ships_in_own_box', '1', !empty($cInfo->ships_in_own_box), '', 'class="form-check-input"'); ?>
          <label class="form-check-label"><?php echo INV_ENTRY_SHIPS_IN_OWN_BOX; ?></label>
        </div>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->ship_complete)): ?>
      <div class="col-auto d-flex align-items-end pb-1">
        <div class="form-check">
          <?php echo html_checkbox_field('ship_complete', '1', !empty($cInfo->ship_complete), '', 'class="form-check-input" onclick="shipCompleteToggle()"'); ?>
          <label class="form-check-label"><?php echo INV_ENTRY_SHIP_COMPLETE; ?></label>
        </div>
        <small class="text-muted ms-2"><?php echo INV_SHIP_COMPLETE_HELP; ?></small>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ── Customs / Export ───────────────────────────────────────────── -->
<?php if (isset($cInfo->country_of_origin) || isset($cInfo->hs_code) || isset($cInfo->customs_description) || isset($cInfo->mfr_part_number)): ?>
<div class="card mb-2">
  <div class="card-header py-1 fb-widget-header text-center"><?php echo INV_SHIPPING_CUSTOMS; ?></div>
  <div class="card-body py-2">
    <div class="row g-3">

      <?php if (isset($cInfo->country_of_origin)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_ENTRY_COUNTRY_OF_ORIGIN; ?></label>
        <?php echo html_input_field('country_of_origin', $cInfo->country_of_origin, 'class="form-control form-control-sm" style="width:80px" maxlength="2" placeholder="GB"', false); ?>
        <small class="text-muted d-block"><?php echo INV_COUNTRY_OF_ORIGIN_HELP; ?></small>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->hs_code)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_ENTRY_HS_CODE; ?></label>
        <?php echo html_input_field('hs_code', $cInfo->hs_code, 'class="form-control form-control-sm" style="width:160px" maxlength="20"', false); ?>
        <small class="text-muted d-block"><?php echo INV_HS_CODE_HELP; ?></small>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->mfr_part_number)): ?>
      <div class="col-auto">
        <label class="form-label mb-0"><?php echo INV_HEADING_MFR_PART_NUMBER; ?></label>
        <?php echo html_input_field('mfr_part_number', $cInfo->mfr_part_number, 'class="form-control form-control-sm" style="width:220px" maxlength="35"', false); ?>
        <small class="text-muted d-block"><?php echo INV_HELP_MFR_PART_NUMBER; ?></small>
      </div>
      <?php endif; ?>

      <?php if (isset($cInfo->customs_description)): ?>
      <div class="col-md-6">
        <label class="form-label mb-0"><?php echo INV_ENTRY_CUSTOMS_DESCRIPTION; ?></label>
        <?php echo html_input_field('customs_description', $cInfo->customs_description, 'class="form-control form-control-sm" maxlength="255"', false); ?>
        <small class="text-muted d-block"><?php echo INV_CUSTOMS_DESCRIPTION_HELP; ?></small>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<p class="text-muted mt-3"><?php echo TEXT_NOT_USED; ?></p>
<?php endif; ?>
