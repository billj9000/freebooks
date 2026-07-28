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
//  Path: /modules/vin_lookup/ajax/lookup.php
//
// Cache-first VIN/vehicle-detail lookup. Returns an HTML fragment
// (htmx target: #vin_results). Re-emits its own hx-* attributes on the
// Refresh button so the swapped-in content stays interactive.
//
$security_level = validate_ajax_user(SECURITY_ID_VIN_LOOKUP);

gen_pull_language('vin_lookup');
require_once(DIR_FS_MODULES . 'vin_lookup/defaults.php');
require_once(DIR_FS_MODULES . 'vin_lookup/classes/vin_lookup.php');

$vrm   = trim($_GET['vrm'] ?? '');
$force = !empty($_GET['force']);

if ($vrm === '') {
  echo '<p class="text-muted small mb-0">' . htmlspecialchars(VIN_TEXT_PROMPT) . '</p>';
  die;
}
if (!VinLookup::enabled()) {
  echo '<div class="alert alert-danger mb-0">' . htmlspecialchars(VIN_ERROR_NO_KEY) . '</div>';
  die;
}

$result = VinLookup::get($vrm, $force);
$vrmNorm = VinLookup::normalizeVrm($vrm);

if (!$result['ok']) {
  echo '<div class="alert alert-warning mb-0">' . htmlspecialchars($result['error']) . '</div>';
  die;
}

$refreshAttrs = 'hx-get="index.php?module=vin_lookup&page=ajax&op=lookup"'
              . ' hx-vals=\'{"vrm":"' . htmlspecialchars($vrmNorm, ENT_QUOTES) . '","force":"1"}\''
              . ' hx-target="#vin_results" hx-swap="innerHTML" hx-indicator="#vin-spinner"';

// Request/billing metadata is stripped before caching (VinLookup::get()) and skipped
// by flatten() itself, so it never reaches this render.
$sections = VinLookup::flatten($result['data']);
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="small text-muted">
    <?php if ($result['cached']): ?>
      <?php echo sprintf(VIN_TEXT_CACHED, htmlspecialchars($result['date_added']), (int)$result['hit_count']); ?>
    <?php else: ?>
      <?php echo htmlspecialchars(VIN_TEXT_FETCHED); ?>
    <?php endif; ?>
  </div>
  <button type="button" class="btn btn-outline-secondary btn-sm" <?php echo $refreshAttrs; ?>>
    <?php echo htmlspecialchars(VIN_BTN_REFRESH); ?>
  </button>
</div>

<?php
foreach ($sections as $label => $fields):
  if (empty($fields)) continue;
?>
<div class="fb-fieldset mb-2">
  <div class="fb-fieldset-legend"><?php echo htmlspecialchars($label); ?></div>
  <table class="table table-borderless table-sm mb-0">
    <?php foreach ($fields as $rawkey => $fvalue): ?>
    <?php if (strcasecmp((string)$rawkey, 'vin') === 0): ?>
    <tr>
      <th class="text-nowrap pe-3" style="width:1%"><?php echo htmlspecialchars(VIN_LBL_VIN); ?></th>
      <td>
        <?php if (strtoupper((string)$fvalue) === 'LICENSE_RESTRICTED'): ?>
        <span class="text-muted fst-italic"><?php echo htmlspecialchars(VIN_TEXT_VIN_RESTRICTED); ?></span>
        <?php else: ?>
        <div class="input-group input-group-sm" style="max-width:320px">
          <code id="vin-value" class="form-control font-monospace"><?php echo htmlspecialchars((string)$fvalue); ?></code>
          <button type="button" class="btn btn-outline-secondary" onclick="copyVin(this)">
            <?php echo htmlspecialchars(VIN_BTN_COPY); ?>
          </button>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php else: ?>
    <tr>
      <th class="text-nowrap pe-3" style="width:1%"><?php echo htmlspecialchars(VinLookup::labelize($rawkey)); ?></th>
      <td><?php echo htmlspecialchars((string)$fvalue); ?></td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  </table>
</div>
<?php endforeach; ?>

<?php if ($security_level > 3): ?>
<details class="mb-2">
  <summary class="small text-muted"><?php echo htmlspecialchars(VIN_TEXT_RAW_JSON); ?></summary>
  <pre class="small bg-light p-2 border rounded"><?php echo htmlspecialchars(json_encode($result['data'], JSON_PRETTY_PRINT)); ?></pre>
</details>
<?php endif; ?>

<?php
// Out-of-band refresh of the Recent Lookups table on the page beneath, so a
// lookup here shows up there immediately instead of only after a page reload.
// Must be wrapped in a real <table> — a bare <tbody>/<tr>/<td> outside table
// context gets silently dropped by the browser's HTML parser since this
// response doesn't otherwise start with a table tag. Only the id-matched
// <tbody> is actually used for the swap; the wrapping <table> is discarded.
$recent = VinLookup::recent();
?>
<table style="display:none">
<tbody id="vin_recent_tbody" hx-swap-oob="true">
  <?php require(DIR_FS_MODULES . 'vin_lookup/pages/main/template_recent_rows.php'); ?>
</tbody>
</table>
