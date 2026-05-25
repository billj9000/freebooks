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
//  Path: /modules/shipping/ajax/pkg_save.php
//  htmx POST handler: save/update/delete a shipping package.
//  Returns updated #pkg-list-section HTML + OOB #pkg-error div.

define('DIR_FS_WORKING', DIR_FS_MODULES . 'shipping/');
$security_level = validate_user(SECURITY_ID_SHIPPING_PACKAGES);
gen_pull_language('shipping');
require_once(DIR_FS_WORKING . 'functions/shipping.php');

$pkgID          = (int)db_prepare_input($_POST['pkgID']          ?? 0);
$action         = db_prepare_input($_POST['action']              ?? '');
$carrier_filter = db_prepare_input($_POST['carrier_filter']      ?? '');
$list_page      = max(1, (int)($_POST['list'] ?? 1));

$error_msg = '';

switch ($action) {
  case 'save':
  case 'update':
    validate_security($security_level, 2);
    $package_code = db_prepare_input($_POST['package_code'] ?? '');
    $name         = db_prepare_input($_POST['name']         ?? '');
    if ($package_code === '') { $error_msg = SHPKG_ERROR_MISSING_CODE; break; }
    if ($name         === '') { $error_msg = SHPKG_ERROR_MISSING_NAME; break; }

    $sql = [
      'package_code' => $package_code,
      'name'         => $name,
      'description'  => db_prepare_input($_POST['description']  ?? ''),
      'weight'       => (float)str_replace(',', '.', $_POST['weight']     ?? 0),
      'max_weight'   => (float)str_replace(',', '.', $_POST['max_weight'] ?? 0),
      'ext_length'   => (float)str_replace(',', '.', $_POST['ext_length'] ?? 0),
      'ext_width'    => (float)str_replace(',', '.', $_POST['ext_width']  ?? 0),
      'ext_height'   => (float)str_replace(',', '.', $_POST['ext_height'] ?? 0),
      'int_length'   => (float)str_replace(',', '.', $_POST['int_length'] ?? 0),
      'int_width'    => (float)str_replace(',', '.', $_POST['int_width']  ?? 0),
      'int_height'   => (float)str_replace(',', '.', $_POST['int_height'] ?? 0),
      'cost'         => (float)str_replace(',', '.', $_POST['cost']       ?? 0),
      'sort_order'   => (int)($_POST['sort_order']  ?? 0),
      'inactive'     => isset($_POST['inactive']) ? '1' : '0',
      'carrier'      => db_prepare_input($_POST['carrier'] ?? ''),
    ];

    if ($action === 'save') {
      $dup = $db->Execute("SELECT id FROM " . TABLE_SHIPPING_PACKAGES
        . " WHERE package_code = '" . db_input($package_code) . "' LIMIT 1");
      if (!$dup->EOF) { $error_msg = SHPKG_ERROR_DUP_CODE; break; }
      db_perform(TABLE_SHIPPING_PACKAGES, $sql, 'insert');
    } else {
      $dup = $db->Execute("SELECT id FROM " . TABLE_SHIPPING_PACKAGES
        . " WHERE package_code = '" . db_input($package_code) . "' AND id <> $pkgID LIMIT 1");
      if (!$dup->EOF) { $error_msg = SHPKG_ERROR_DUP_CODE; break; }
      db_perform(TABLE_SHIPPING_PACKAGES, $sql, 'update', "id = $pkgID");
    }
    break;

  case 'delete':
    validate_security($security_level, 4);
    $db->Execute("DELETE FROM " . TABLE_SHIPPING_PACKAGES . " WHERE id = $pkgID");
    break;
}

// ── Build carrier lookups ──────────────────────────────────────────
$method_dir  = DIR_FS_WORKING . 'methods/';
$carrier_map = ['' => SHPKG_TEXT_ALL_CARRIERS];
if (is_dir($method_dir)) foreach (scandir($method_dir) as $method) {
  if ($method === '.' || $method === '..') continue;
  load_method_language($method_dir, $method);
  $title_const = 'MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_TITLE';
  $carrier_map[$method] = defined($title_const) ? constant($title_const) : strtoupper($method);
}

// ── Fix $_GET so display_links() generates correct package-page URLs ──
$_GET = ['module' => 'shipping', 'page' => 'packages', 'carrier_filter' => $carrier_filter];

// ── Query list ────────────────────────────────────────────────────
$where     = $carrier_filter !== '' ? " WHERE carrier = '" . db_input($carrier_filter) . "'" : '';
$query_raw = "SELECT SQL_CALC_FOUND_ROWS * FROM " . TABLE_SHIPPING_PACKAGES . $where
           . " ORDER BY sort_order ASC, package_code ASC";
$query_result = $db->Execute($query_raw,
  (MAX_DISPLAY_SEARCH_RESULTS * ($list_page - 1)) . ',' . MAX_DISPLAY_SEARCH_RESULTS);
$query_split  = new splitPageResults($list_page, '');

// ── Unit / currency display ───────────────────────────────────────
$wt_unit  = defined('SHIPPING_DEFAULT_WEIGHT_UNIT')
  ? (['LBS' => 'lb', 'KGS' => 'Kg'][SHIPPING_DEFAULT_WEIGHT_UNIT] ?? SHIPPING_DEFAULT_WEIGHT_UNIT) : '';
$dim_unit = defined('SHIPPING_DEFAULT_PKG_DIM_UNIT')
  ? (['IN'  => 'in', 'CM'  => 'cm'][SHIPPING_DEFAULT_PKG_DIM_UNIT] ?? SHIPPING_DEFAULT_PKG_DIM_UNIT) : '';
$curr_sym = trim($currencies->currencies[DEFAULT_CURRENCY]['symbol_left']
               . $currencies->currencies[DEFAULT_CURRENCY]['symbol_right']);

// ── Response headers ──────────────────────────────────────────────
if (!$error_msg) {
  header('HX-Trigger: closeModal');
}

// ── Render list section (same markup as template_main.php) ────────
?>
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
    $list_base = 'index.php?' . gen_get_all_get_params(['pkgID', 'action', 'set_pkg_theme']);
    $odd = true;
    while (!$query_result->EOF) {
      $row = $query_result->fields;
      $row_class = $odd ? 'odd' : 'even';
      $odd = !$odd;
      $data = 'data-pkg-id="'       . (int)$row['id']                           . '" '
            . 'data-package-code="' . htmlspecialchars($row['package_code'])     . '" '
            . 'data-name="'         . htmlspecialchars($row['name'])             . '" '
            . 'data-description="'  . htmlspecialchars($row['description'] ?? '') . '" '
            . 'data-carrier="'      . htmlspecialchars($row['carrier'])          . '" '
            . 'data-weight="'       . $row['weight']                             . '" '
            . 'data-max-weight="'   . $row['max_weight']                         . '" '
            . 'data-ext-length="'   . $row['ext_length']                         . '" '
            . 'data-ext-width="'    . $row['ext_width']                          . '" '
            . 'data-ext-height="'   . $row['ext_height']                         . '" '
            . 'data-int-length="'   . $row['int_length']                         . '" '
            . 'data-int-width="'    . $row['int_width']                          . '" '
            . 'data-int-height="'   . $row['int_height']                         . '" '
            . 'data-cost="'         . $row['cost']                               . '" '
            . 'data-sort-order="'   . $row['sort_order']                         . '" '
            . 'data-inactive="'     . $row['inactive']                           . '"';
    ?>
      <tr class="<?php echo $row_class; ?>" <?php echo $data; ?> onclick="openPkgDialog(<?php echo (int)$row['id']; ?>)" style="cursor:pointer">
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
    }
    ?>
    </tbody>
  </table>
</div>
<?php /* OOB: always reset error (empty on success, populated on error) */ ?>
<div id="pkg-error" hx-swap-oob="true"><?php echo $error_msg ? '<div class="alert alert-danger py-1 mb-0">' . htmlspecialchars($error_msg) . '</div>' : ''; ?></div>
