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
//  Path: /modules/phreebooks/pages/packing_list/template_main.php
//
$fmt_date = $order['post_date'] ? date('d M Y', strtotime($order['post_date'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars(PKL_PAGE_TITLE . ' — ' . ($order['purchase_invoice_id'] ?? $order_id)); ?></title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 16px; }
h1 { font-size: 18px; margin: 0 0 4px 0; }
h2 { font-size: 13px; margin: 0; }
h3 { font-size: 12px; font-weight: bold; margin: 0 0 6px 0; }
p { margin: 2px 0; }

/* Screen-only controls */
.screen-only { margin-bottom: 16px; }
@media print { .screen-only { display: none; } }

/* Page header */
.page-header { display: flex; justify-content: space-between; align-items: flex-start;
               border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 14px; }
.company-block { max-width: 55%; }
.doc-block { text-align: right; }
.doc-block .doc-title { font-size: 20px; font-weight: bold; text-transform: uppercase;
                        letter-spacing: 2px; color: #333; }
.doc-block .doc-ref { font-size: 15px; font-weight: bold; }

/* Two-column info grid */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.info-card { border: 1px solid #ccc; padding: 8px 10px; }
.info-card-title { font-weight: bold; font-size: 11px; text-transform: uppercase;
                   letter-spacing: 0.5px; color: #555; border-bottom: 1px solid #ddd;
                   margin-bottom: 5px; padding-bottom: 3px; }

/* Package boxes */
.pkg-box { border: 2px solid #444; margin-bottom: 14px; break-inside: avoid; page-break-inside: avoid; }
.pkg-header { background: #e8e8e8; padding: 6px 12px; display: flex;
              justify-content: space-between; align-items: center; border-bottom: 1px solid #aaa; }
.pkg-header-left { font-weight: bold; font-size: 13px; }
.pkg-header-right { font-size: 11px; color: #444; }
.pkg-body { padding: 8px 10px; }

/* Tables */
table { width: 100%; border-collapse: collapse; }
th { background: #f2f2f2; text-align: left; padding: 4px 8px;
     border: 1px solid #ccc; font-size: 11px; }
td { padding: 4px 8px; border: 1px solid #ddd; vertical-align: top; }
.text-end { text-align: right; }

.notice { color: #888; font-style: italic; margin: 8px 0; }
.section-heading { font-weight: bold; font-size: 13px; border-bottom: 1px solid #ccc;
                   margin: 0 0 10px 0; padding-bottom: 4px; }
</style>
</head>
<body>

<!-- Print / Close buttons (hidden on print) -->
<div class="screen-only">
  <button onclick="window.print()" style="padding:6px 16px;margin-right:8px;font-size:13px;cursor:pointer">
    &#x1F5A8; <?php echo htmlspecialchars(PKL_BTN_PRINT); ?>
  </button>
  <button onclick="window.close()" style="padding:6px 16px;font-size:13px;cursor:pointer">
    <?php echo htmlspecialchars(PKL_BTN_CLOSE); ?>
  </button>
</div>

<!-- Page header: company + document title -->
<div class="page-header">
  <div class="company-block">
    <h1><?php echo htmlspecialchars(defined('COMPANY_NAME') ? COMPANY_NAME : ''); ?></h1>
    <?php if (defined('COMPANY_ADDRESS1') && COMPANY_ADDRESS1): ?>
    <p><?php echo htmlspecialchars(COMPANY_ADDRESS1); ?></p>
    <?php endif; ?>
    <?php if (defined('COMPANY_ADDRESS2') && COMPANY_ADDRESS2): ?>
    <p><?php echo htmlspecialchars(COMPANY_ADDRESS2); ?></p>
    <?php endif; ?>
    <?php
      $city_line = implode(', ', array_filter([
          defined('COMPANY_CITY_TOWN') ? COMPANY_CITY_TOWN : '',
          defined('COMPANY_ZONE')      ? COMPANY_ZONE      : '',
          defined('COMPANY_POSTAL_CODE') ? COMPANY_POSTAL_CODE : '',
      ]));
      if ($city_line): ?><p><?php echo htmlspecialchars($city_line); ?></p><?php endif; ?>
    <?php if (defined('COMPANY_TELEPHONE1') && COMPANY_TELEPHONE1): ?>
    <p><?php echo htmlspecialchars(COMPANY_TELEPHONE1); ?></p>
    <?php endif; ?>
  </div>
  <div class="doc-block">
    <div class="doc-title"><?php echo htmlspecialchars(PKL_PAGE_TITLE); ?></div>
    <?php if ($order['purchase_invoice_id']): ?>
    <div class="doc-ref"><?php echo htmlspecialchars($order['purchase_invoice_id']); ?></div>
    <?php endif; ?>
    <?php if ($fmt_date): ?>
    <p><?php echo htmlspecialchars($fmt_date); ?></p>
    <?php endif; ?>
  </div>
</div>

<!-- Ship-to + carrier info -->
<div class="info-grid">
  <div class="info-card">
    <div class="info-card-title"><?php echo htmlspecialchars(PKL_TEXT_SHIP_TO); ?></div>
    <?php if ($order['ship_primary_name']): ?>
    <p><strong><?php echo htmlspecialchars($order['ship_primary_name']); ?></strong></p>
    <?php endif; ?>
    <?php if ($order['ship_contact']): ?>
    <p><?php echo htmlspecialchars($order['ship_contact']); ?></p>
    <?php endif; ?>
    <?php if ($order['ship_address1']): ?>
    <p><?php echo htmlspecialchars($order['ship_address1']); ?></p>
    <?php endif; ?>
    <?php if ($order['ship_address2']): ?>
    <p><?php echo htmlspecialchars($order['ship_address2']); ?></p>
    <?php endif; ?>
    <?php
      $ship_city_line = implode(', ', array_filter([
          $order['ship_city_town']     ?? '',
          $order['ship_state_province'] ?? '',
          $order['ship_postal_code']   ?? '',
      ]));
      if ($ship_city_line): ?><p><?php echo htmlspecialchars($ship_city_line); ?></p><?php endif; ?>
    <?php if ($order['ship_country_code']): ?>
    <p><?php echo htmlspecialchars($order['ship_country_code']); ?></p>
    <?php endif; ?>
  </div>
  <div class="info-card">
    <div class="info-card-title"><?php echo htmlspecialchars(PKL_TEXT_SHIPMENT_DETAILS); ?></div>
    <?php if ($ship_carrier): ?>
    <p><strong><?php echo htmlspecialchars(PKL_TEXT_CARRIER); ?>:</strong>
       <?php echo htmlspecialchars($ship_carrier); ?></p>
    <?php endif; ?>
    <?php if ($service_label): ?>
    <p><strong><?php echo htmlspecialchars(PKL_TEXT_SERVICE); ?>:</strong>
       <?php echo htmlspecialchars($service_label); ?></p>
    <?php endif; ?>
    <?php if (!empty($order['purch_order_id'])): ?>
    <p><strong><?php echo htmlspecialchars(PKL_TEXT_CUSTOMER_REF); ?>:</strong>
       <?php echo htmlspecialchars($order['purch_order_id']); ?></p>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($packages)): ?>
<!-- No packing data — show flat item list -->
<div class="section-heading"><?php echo htmlspecialchars(PKL_TEXT_ITEMS); ?></div>
<?php if (empty($order_items)): ?>
<p class="notice"><?php echo htmlspecialchars(PKL_NO_PACKAGES); ?></p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th style="width:20%"><?php echo htmlspecialchars(PKL_TEXT_SKU); ?></th>
      <th><?php echo htmlspecialchars(PKL_TEXT_DESCRIPTION); ?></th>
      <th class="text-end" style="width:10%"><?php echo htmlspecialchars(PKL_TEXT_QTY); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($order_items as $item): ?>
    <tr>
      <td><?php echo htmlspecialchars($item['sku']); ?></td>
      <td><?php echo htmlspecialchars($item['description']); ?></td>
      <td class="text-end"><?php echo (int)$item['qty']; ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php else: ?>
<!-- Package-by-package breakdown -->
<div class="section-heading"><?php echo htmlspecialchars(PKL_TEXT_PACKAGES . ' (' . count($packages) . ')'); ?></div>
<?php foreach ($packages as $idx => $pkg):
    $box_label = $pkg['box_name'] ?? $pkg['box_code'] ?? ('Box ' . ($idx + 1));

    // Convert dimensions and weight to display units
    $has_dims   = !empty($pkg['length_mm']) && !empty($pkg['width_mm']) && !empty($pkg['depth_mm']);
    $dim_label  = '';
    $wt_label   = '';
    if ($has_dims && class_exists('BoxPackingService')) {
        $l = number_format(BoxPackingService::fromMm((int)$pkg['length_mm']), 1);
        $w = number_format(BoxPackingService::fromMm((int)$pkg['width_mm']),  1);
        $d = number_format(BoxPackingService::fromMm((int)$pkg['depth_mm']),  1);
        $dim_label = $l . ' &times; ' . $w . ' &times; ' . $d . ' ' . strtolower($dim_unit);
    }
    if (!empty($pkg['weight_g']) && class_exists('BoxPackingService')) {
        $wt = number_format(BoxPackingService::fromGrams((int)$pkg['weight_g']), 3);
        $wt_label = $wt . ' ' . strtolower($wt_unit);
    }
?>
<div class="pkg-box">
  <div class="pkg-header">
    <div class="pkg-header-left"><?php echo htmlspecialchars(PKL_TEXT_BOX . ' ' . ($idx + 1) . ': ' . $box_label); ?></div>
    <div class="pkg-header-right">
      <?php if ($dim_label): ?><?php echo $dim_label; ?><?php endif; ?>
      <?php if ($dim_label && $wt_label): ?> &nbsp;|&nbsp; <?php endif; ?>
      <?php if ($wt_label): ?><?php echo htmlspecialchars($wt_label); ?><?php endif; ?>
    </div>
  </div>
  <div class="pkg-body">
    <?php if (!empty($pkg['items'])): ?>
    <table>
      <thead>
        <tr>
          <th style="width:20%"><?php echo htmlspecialchars(PKL_TEXT_SKU); ?></th>
          <th><?php echo htmlspecialchars(PKL_TEXT_DESCRIPTION); ?></th>
          <th class="text-end" style="width:10%"><?php echo htmlspecialchars(PKL_TEXT_QTY); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pkg['items'] as $item):
            $sku  = $item['sku'] ?? '';
            $qty  = $item['qty'] ?? 1;
            $desc = $order_items[$sku]['description'] ?? '';
        ?>
        <tr>
          <td><?php echo htmlspecialchars($sku); ?></td>
          <td><?php echo htmlspecialchars($desc); ?></td>
          <td class="text-end"><?php echo (int)$qty; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="notice"><?php echo htmlspecialchars(PKL_BOX_NO_ITEMS); ?></p>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
