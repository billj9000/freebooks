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
//  Path: /modules/shipping/pages/packages/template_main.php

// ── Unit / currency display ────────────────────────────────────────
$wt_unit  = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')
  ? (['LBS' => 'lb', 'KGS' => 'Kg'][SHIPPING_DEFAULT_WEIGHT_UNIT] ?? SHIPPING_DEFAULT_WEIGHT_UNIT) : '';
$dim_unit = defined('SHIPPING_DEFAULT_PKG_DIM_UNIT')
  ? (['IN'  => 'in', 'CM'  => 'cm'][SHIPPING_DEFAULT_PKG_DIM_UNIT] ?? SHIPPING_DEFAULT_PKG_DIM_UNIT) : '';
$curr_sym = trim($currencies->currencies[DEFAULT_CURRENCY]['symbol_left']
               . $currencies->currencies[DEFAULT_CURRENCY]['symbol_right']);

// ── Toolbar ────────────────────────────────────────────────────────
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=shipping&page=packages&list=1', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['print']['show']    = false;
if ($security_level > 1) {
  $toolbar->add_icon('new', 'onclick="openPkgDialog(0)"', $order = 10);
}
if ($security_level > 3 && $pkgID) {
  $toolbar->icon_list['delete']['params'] = 'onclick="if(confirm(\'' . addslashes(TEXT_DELETE_ENTRY) . '\')){deletePkg(' . $pkgID . ')}"';
} else {
  $toolbar->icon_list['delete']['show'] = false;
}
$toolbar->add_help('09.00.00');
echo $toolbar->build_toolbar();

?>

<div class="pkg-wrap">
<h1><?php echo SHPKG_HEADING_TITLE; ?></h1>

<!-- ── Carrier filter ──────────────────────────────────────────── -->
<?php $filter_base = html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['carrier_filter', 'list']), 'SSL'); ?>
<div class="mb-2 pkg-theme-bar">
  <?php echo SHPKG_TEXT_CARRIER; ?>:&nbsp;
  <select onchange="location.href='<?php echo $filter_base; ?>&list=1&carrier_filter='+encodeURIComponent(this.value)">
    <?php foreach ($carrier_list as $c): ?>
    <option value="<?php echo htmlspecialchars($c['id']); ?>"<?php echo ($carrier_filter === $c['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($c['text']); ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- ── List section (htmx swap target) ────────────────────────── -->
<div id="pkg-list-section">
  <div class="d-flex justify-content-between align-items-center mb-1" style="min-height:20px">
    <small class="text-muted"><?php echo $query_split->display_count(TEXT_DISPLAY_NUMBER . SHPKG_HEADING_TITLE); ?></small>
    <span><?php echo $query_split->display_links(); ?></span>
  </div>

  <table class="table table-sm pkg-table">
    <thead>
      <tr>
        <th><?php echo SHPKG_TEXT_CODE; ?></th>
        <th><?php echo SHPKG_TEXT_NAME; ?></th>
        <th><?php echo SHPKG_TEXT_CARRIER; ?></th>
        <th class="text-end"><?php echo SHPKG_TEXT_WEIGHT; ?><?php echo $wt_unit ? ' (' . $wt_unit . ')' : ''; ?></th>
        <th class="text-end"><?php echo SHPKG_TEXT_MAX_WEIGHT; ?><?php echo $wt_unit ? ' (' . $wt_unit . ')' : ''; ?></th>
        <th class="text-center"><?php echo SHPKG_TEXT_EXT_DIMS; ?><?php echo $dim_unit ? ' (' . $dim_unit . ')' : ''; ?></th>
        <th class="text-end"><?php echo SHPKG_TEXT_COST; ?><?php echo $curr_sym ? ' (' . $curr_sym . ')' : ''; ?></th>
        <th class="text-center"><?php echo TEXT_INACTIVE; ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php
    $list_base = 'index.php?' . gen_get_all_get_params(['pkgID', 'action']);
    $odd = true;
    while (!$query_result->EOF):
      $row = $query_result->fields;
      $row_class = $odd ? 'odd' : 'even';
      $odd = !$odd;
    ?>
      <tr class="<?php echo $row_class; ?>" data-pkg-id="<?php echo (int)$row['id']; ?>"
          data-package-code="<?php echo htmlspecialchars($row['package_code']); ?>"
          data-name="<?php echo htmlspecialchars($row['name']); ?>"
          data-description="<?php echo htmlspecialchars($row['description'] ?? ''); ?>"
          data-carrier="<?php echo htmlspecialchars($row['carrier']); ?>"
          data-weight="<?php echo $row['weight']; ?>"
          data-max-weight="<?php echo $row['max_weight']; ?>"
          data-ext-length="<?php echo $row['ext_length']; ?>"
          data-ext-width="<?php echo $row['ext_width']; ?>"
          data-ext-height="<?php echo $row['ext_height']; ?>"
          data-int-length="<?php echo $row['int_length']; ?>"
          data-int-width="<?php echo $row['int_width']; ?>"
          data-int-height="<?php echo $row['int_height']; ?>"
          data-cost="<?php echo $row['cost']; ?>"
          data-sort-order="<?php echo $row['sort_order']; ?>"
          data-inactive="<?php echo $row['inactive']; ?>"
          onclick="openPkgDialog(<?php echo (int)$row['id']; ?>)"
          style="cursor:pointer">
        <td><?php echo htmlspecialchars($row['package_code']); ?></td>
        <td><?php echo htmlspecialchars($row['name']); ?></td>
        <td><?php echo htmlspecialchars($carrier_map[$row['carrier']] ?? $row['carrier']); ?></td>
        <td class="text-end"><?php echo $row['weight'] > 0 ? number_format((float)$row['weight'], 1) : ''; ?></td>
        <td class="text-end"><?php echo $row['max_weight'] > 0 ? number_format((float)$row['max_weight'], 1) : ''; ?></td>
        <td class="text-center"><?php
          echo number_format((float)$row['ext_length'], 1) . ' &times; '
             . number_format((float)$row['ext_width'],  1) . ' &times; '
             . number_format((float)$row['ext_height'], 1);
        ?></td>
        <td class="text-end"><?php echo $currencies->format($row['cost'], false); ?></td>
        <td class="text-center"><?php echo $row['inactive'] == '1' ? TEXT_YES : ''; ?></td>
        <td class="text-center" onclick="event.stopPropagation()">
          <?php if ($security_level > 3): ?>
          <button type="button" class="btn btn-sm btn-link p-0 text-danger"
            hx-post="index.php?module=shipping&page=ajax&op=pkg_save"
            hx-vals='{"action":"delete","pkgID":"<?php echo (int)$row['id']; ?>","carrier_filter":"<?php echo htmlspecialchars($carrier_filter); ?>","list":"1"}'
            hx-target="#pkg-list-section" hx-swap="outerHTML"
            hx-confirm="<?php echo addslashes(TEXT_DELETE_ENTRY); ?>"
            title="<?php echo TEXT_DELETE; ?>">
            <?php echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small'); ?>
          </button>
          <?php endif; ?>
        </td>
      </tr>
    <?php
      $query_result->MoveNext();
    endwhile;
    ?>
    </tbody>
  </table>
</div><!-- /pkg-list-section -->

<!-- ── Edit / New modal ────────────────────────────────────────── -->
<div class="modal fade pkg-modal" id="pkgModal" tabindex="-1" aria-labelledby="pkgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pkgModalLabel"><?php echo TEXT_NEW; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form hx-post="index.php?module=shipping&page=ajax&op=pkg_save"
            hx-target="#pkg-list-section"
            hx-swap="outerHTML">
        <div class="modal-body">
          <div id="pkg-error"></div>

          <input type="hidden" id="fld_action"        name="action"         value="save">
          <input type="hidden" id="fld_pkgID"         name="pkgID"          value="">
          <input type="hidden" name="carrier_filter" value="<?php echo htmlspecialchars($carrier_filter); ?>">
          <input type="hidden" name="list"           value="<?php echo (int)($_REQUEST['list'] ?? 1); ?>">

          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label" for="fld_packageCode"><?php echo SHPKG_TEXT_CODE; ?> *</label>
              <input type="text" class="form-control form-control-sm" id="fld_packageCode" name="package_code" maxlength="20">
            </div>
            <div class="col-md-8">
              <label class="form-label" for="fld_name"><?php echo SHPKG_TEXT_NAME; ?> *</label>
              <input type="text" class="form-control form-control-sm" id="fld_name" name="name" maxlength="64">
            </div>
            <div class="col-12">
              <label class="form-label" for="fld_description"><?php echo SHPKG_TEXT_DESCRIPTION; ?></label>
              <input type="text" class="form-control form-control-sm" id="fld_description" name="description" maxlength="255">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fld_carrier"><?php echo SHPKG_TEXT_CARRIER; ?></label>
              <select class="form-select form-select-sm" id="fld_carrier" name="carrier">
                <?php foreach ($carrier_list as $c): ?>
                <option value="<?php echo htmlspecialchars($c['id']); ?>"><?php echo htmlspecialchars($c['text']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fld_weight"><?php echo SHPKG_TEXT_WEIGHT; ?><?php echo $wt_unit ? ' (' . $wt_unit . ')' : ''; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_weight" name="weight" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fld_maxWeight"><?php echo SHPKG_TEXT_MAX_WEIGHT; ?><?php echo $wt_unit ? ' (' . $wt_unit . ')' : ''; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_maxWeight" name="max_weight" value="0">
            </div>
          </div>

          <hr class="my-2">

          <div class="row g-2">
            <div class="col-12"><strong><?php echo SHPKG_TEXT_EXTERNAL; ?> (<?php echo $dim_unit; ?>)</strong></div>
            <div class="col-md-4">
              <label class="form-label" for="fld_extLength"><?php echo SHPKG_TEXT_EXT_LENGTH; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_extLength" name="ext_length" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fld_extWidth"><?php echo SHPKG_TEXT_EXT_WIDTH; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_extWidth" name="ext_width" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fld_extHeight"><?php echo SHPKG_TEXT_EXT_HEIGHT; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_extHeight" name="ext_height" value="0">
            </div>

            <div class="col-12"><strong><?php echo SHPKG_TEXT_INTERNAL; ?> (<?php echo $dim_unit; ?>)</strong></div>
            <div class="col-md-4">
              <label class="form-label" for="int_length"><?php echo SHPKG_TEXT_INT_LENGTH; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="int_length" name="int_length" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="int_width"><?php echo SHPKG_TEXT_INT_WIDTH; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="int_width" name="int_width" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="int_height"><?php echo SHPKG_TEXT_INT_HEIGHT; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="int_height" name="int_height" value="0">
            </div>

            <div class="col-md-4">
              <label class="form-label"><?php echo SHPKG_TEXT_VOL_WEIGHT; ?> (<?php echo $wt_unit; ?>)</label>
              <div class="form-control form-control-sm text-end bg-light" id="vol_weight">—</div>
            </div>
          </div>

          <hr class="my-2">

          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label" for="fld_cost"><?php echo SHPKG_TEXT_COST; ?><?php echo $curr_sym ? ' (' . $curr_sym . ')' : ''; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_cost" name="cost" value="0.00">
            </div>
            <div class="col-md-3">
              <label class="form-label" for="fld_sortOrder"><?php echo SHPKG_TEXT_SORT_ORDER; ?></label>
              <input type="text" class="form-control form-control-sm text-end" id="fld_sortOrder" name="sort_order" value="0">
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" id="fld_inactive" name="inactive" value="1">
                <label class="form-check-label" for="fld_inactive"><?php echo SHPKG_TEXT_INACTIVE; ?></label>
              </div>
            </div>
          </div>

        </div><!-- /modal-body -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo TEXT_CANCEL; ?></button>
          <button type="submit" class="btn btn-primary btn-sm" id="pkgSaveBtn"><?php echo TEXT_SAVE; ?></button>
        </div>
      </form>
    </div>
  </div>
</div><!-- /pkgModal -->

</div><!-- /pkg-wrap -->
