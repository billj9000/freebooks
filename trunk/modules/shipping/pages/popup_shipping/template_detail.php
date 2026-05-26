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
//  Path: /modules/shipping/pages/popup_shipping/template_detail.php
//
$rateData = $rates['rates'] ?? [];
ksort($rateData);
$pendingMsgs = $_SESSION['messageToStack'] ?? [];
$messageStack->reset();
?>

<?php if (!empty($pendingMsgs)): ?>
<div class="alert alert-warning mb-2" style="font-size:12px">
  <?php foreach ($pendingMsgs as $msg): ?><div><?php echo $msg['text']; ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($rateData)): ?>
<div class="alert alert-warning mb-3"><?php echo SHIPPING_NO_PACKAGES; ?></div>
<?php else: ?>

<?php foreach ($rateData as $carrier => $services): ?>
<?php if (!is_array($services) || empty($services)) continue; ?>
<div class="mb-3">
  <table class="pkg-table table-bordered">
    <thead>
      <tr>
        <?php
          $logoBase = DIR_WS_MODULES . 'shipping/methods/' . $carrier . '/images/logo';
          $logoFile = false;
          foreach (['.png', '.gif', '.jpg'] as $ext) {
            if (is_file(DIR_FS_MODULES . 'shipping/methods/' . $carrier . '/images/logo' . $ext)) {
              $logoFile = $logoBase . $ext;
              break;
            }
          }
        ?>
        <th style="width:35%">
          <?php if ($logoFile): ?>
            <img src="<?php echo $logoFile; ?>" alt="<?php echo $carrier; ?>" style="max-height:28px;width:auto">
          <?php else: ?>
            <?php echo constant('MODULE_SHIPPING_' . strtoupper($carrier) . '_TITLE_SHORT'); ?>
          <?php endif; ?>
        </th>
        <th class="text-end" style="width:15%"><?php echo SHIPPING_TEXT_FREIGHT_QUOTE; ?></th>
        <th class="text-end" style="width:15%"><?php echo SHIPPING_TEXT_BOOK_PRICE; ?></th>
        <th class="text-end" style="width:15%"><?php echo SHIPPING_TEXT_COST; ?></th>
        <th style="width:20%"><?php echo SHIPPING_TEXT_NOTES; ?></th>
      </tr>
    </thead>
    <tbody>
      <?php $odd = true; foreach ($services as $key => $prices): ?>
      <?php $pkgsJson = htmlspecialchars(json_encode($prices['packages'] ?? []), ENT_QUOTES); ?>
      <?php
        $pkgSummary = '';
        if (!empty($prices['packages'])) {
          $counts = [];
          foreach ($prices['packages'] as $p) {
            $label = $p['box_name'] ?? $p['box_code'] ?? '';
            if ($label !== '') $counts[$label] = ($counts[$label] ?? 0) + ($p['qty'] ?? 1);
          }
          $parts = [];
          foreach ($counts as $label => $qty) $parts[] = $qty . '&times; ' . htmlspecialchars($label);
          $pkgSummary = implode(', ', $parts);
        }
      ?>
      <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer"
          onclick="selectEstimatedRate('<?php echo addslashes($currencies->format($prices['quote'])); ?>','<?php echo addslashes($carrier); ?>','<?php echo addslashes($key); ?>','<?php echo $pkgsJson; ?>')">
        <td><?php echo constant($carrier . '_' . $key); ?></td>
        <td class="text-end"><?php echo $prices['quote'] !== '' ? $currencies->format($prices['quote']) : '&nbsp;'; ?></td>
        <td class="text-end"><?php echo $prices['book']  !== '' ? $currencies->format($prices['book'])  : '&nbsp;'; ?></td>
        <td class="text-end"><?php echo $prices['cost']  !== '' ? $currencies->format($prices['cost'])  : '&nbsp;'; ?></td>
        <td><?php echo htmlspecialchars($prices['note'] ?? ''); ?><?php if ($pkgSummary): ?><br><small class="text-muted"><?php echo $pkgSummary; ?></small><?php endif; ?></td>
      </tr>
      <?php $odd = !$odd; endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php
$back_params = [
  'ship_city_town'      => $pkg->ship_city_town,
  'ship_state_province' => $pkg->ship_state_province,
  'ship_postal_code'    => $pkg->ship_postal_code,
  'ship_country_code'   => $pkg->ship_country_code,
  'ship_to_city'        => $pkg->ship_to_city,
  'ship_to_state'       => $pkg->ship_to_state,
  'ship_to_postal_code' => $pkg->ship_to_postal_code,
  'ship_to_country_code'=> $pkg->ship_to_country_code,
  'pkg_weight'          => $pkg->pkg_weight,
  'pkg_weight_unit'     => $pkg->pkg_weight_unit,
  'pkg_dimension_unit'  => $pkg->pkg_dimension_unit,
  'ship_date'           => $pkg->ship_date ?? date('Y-m-d'),
  'residential_address' => $pkg->residential_address,
  'num_items'           => count($pkg->items),
];
foreach ($pkg->items as $pi => $item) {
  $n = $pi + 1;
  $back_params['sku_' . $n] = $item['sku'];
  $back_params['qty_' . $n] = (int)$item['qty'];
}
$back_qs = http_build_query($back_params);
?>
<!-- Back button reloads the options form with current values -->
<div class="d-flex gap-2 mt-1">
  <button type="button" class="btn btn-secondary btn-sm"
          hx-get="index.php?module=shipping&page=popup_shipping&<?php echo htmlspecialchars($back_qs); ?>"
          hx-target="#shipping-estimator-body"
          hx-swap="innerHTML">
    <?php echo TEXT_BACK; ?>
  </button>
  <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
    <?php echo TEXT_CANCEL; ?>
  </button>
</div>
