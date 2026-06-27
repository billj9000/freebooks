<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/ajax/label_preview.php

validate_ajax_user(SECURITY_ID_LABELS_MANAGE);

require_once(DIR_FS_MODULES . 'labels/defaults.php');
require_once(DIR_FS_MODULES . 'labels/classes/label_renderer.php');

$html_template = db_prepare_input($_POST['html_template'] ?? '');
$width_mm      = max(1.0, (float)($_POST['width_mm']      ?? LABELS_DEFAULT_WIDTH_MM));
$height_mm     = max(1.0, (float)($_POST['height_mm']     ?? LABELS_DEFAULT_HEIGHT_MM));

// Use first inventory item as sample, or fall back to demo data
$sample = array(
  'sku'               => 'SAMPLE-001',
  'description_short' => 'Sample Item',
  'description_sales' => 'Sample Product Description',
  'full_price'        => 9.99,
  'item_cost'         => 5.00,
  'upc_code'          => '012345678901',
  'item_weight'       => 0.5,
);
$result = $db->Execute("SELECT sku, description_short, description_sales, full_price, item_cost, upc_code, item_weight FROM " . TABLE_INVENTORY . " LIMIT 1");
if (!$result->EOF) {
  $sample = $result->fields;
}

$renderer = new LabelRenderer();
$vars     = $renderer->buildVars($sample);

$w = number_format($width_mm,  2, '.', '');
$h = number_format($height_mm, 2, '.', '');

$rendered = '';
if ($html_template !== '') {
  try {
    $rendered = $renderer->renderHtml($html_template, $vars);
  } catch (\Throwable $e) {
    $rendered = '<span class="text-danger" style="font-size:0.8em">Template error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span>';
  }
} else {
  $rendered = '<span class="text-muted" style="font-size:0.8em">(enter a template above)</span>';
}

echo '<div style="width:' . $w . 'mm;height:' . $h . 'mm;overflow:hidden;box-sizing:border-box;border:1px solid #ccc;font-family:sans-serif;font-size:10pt">'
   . $rendered
   . '</div>';
