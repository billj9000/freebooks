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
//  Path: /modules/vin_lookup/pages/main/template_main.php
//
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->add_help('13.01');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo VIN_PAGE_TITLE; ?></div>
  <div class="card-body py-2 px-3">
    <div class="row g-2 align-items-center">
      <div class="col-auto"><?php echo VIN_LBL_REGISTRATION; ?></div>
      <div class="col-auto">
        <input type="text" name="vrm" id="vrm" class="form-control form-control-sm text-uppercase"
          style="width:160px" maxlength="16" autofocus
          hx-get="index.php?module=vin_lookup&amp;page=ajax&amp;op=lookup"
          hx-trigger="keyup[key=='Enter']"
          hx-include="[name='vrm']"
          hx-target="#vin_results"
          hx-swap="innerHTML"
          hx-indicator="#vin-spinner">
      </div>
      <div class="col-auto">
        <button type="button" class="btn btn-primary btn-sm"
          hx-get="index.php?module=vin_lookup&amp;page=ajax&amp;op=lookup"
          hx-include="[name='vrm']"
          hx-target="#vin_results"
          hx-swap="innerHTML"
          hx-indicator="#vin-spinner">
          <?php echo VIN_BTN_LOOKUP; ?>
        </button>
      </div>
      <div class="col-auto">
        <span id="vin-spinner" class="htmx-indicator spinner-border spinner-border-sm text-secondary" role="status"></span>
      </div>
    </div>
    <div id="vin_results" class="mt-3"></div>
  </div>
</div>

<div class="card fb-card mb-3">
  <div class="card-header py-1 fb-widget-header"><?php echo VIN_TEXT_RECENT_LOOKUPS; ?></div>
  <div class="card-body p-0">
    <table class="table table-borderless table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo VIN_LBL_REGISTRATION; ?></th>
          <th><?php echo VIN_LBL_VIN; ?></th>
          <th><?php echo VIN_LBL_MAKE; ?></th>
          <th><?php echo VIN_LBL_MODEL; ?></th>
          <th><?php echo VIN_LBL_CACHED; ?></th>
          <th><?php echo VIN_LBL_HITS; ?></th>
          <th><?php echo VIN_LBL_ACTIONS; ?></th>
        </tr>
      </thead>
      <tbody id="vin_recent_tbody">
        <?php require(DIR_FS_MODULES . 'vin_lookup/pages/main/template_recent_rows.php'); ?>
      </tbody>
    </table>
  </div>
</div>
