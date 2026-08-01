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
//  Path: /modules/labels/ajax/render.php

validate_ajax_user(SECURITY_ID_LABELS_PRINT);

require_once(DIR_FS_MODULES . 'labels/defaults.php');
require_once(DIR_FS_MODULES . 'labels/classes/label_renderer.php');

header('Content-Type: application/json');

$template_id  = (int)($_POST['template_id'] ?? 0);
$skus_json    = $_POST['skus'] ?? '[]';
$skus         = json_decode($skus_json, true);

if ($template_id <= 0 || !is_array($skus) || empty($skus)) {
  echo json_encode(['error' => 'Invalid request']);
  exit;
}

$tmpl = $db->Execute("SELECT * FROM " . TABLE_LABEL_TEMPLATES . " WHERE id = " . $template_id);
if ($tmpl->EOF) {
  echo json_encode(['error' => 'Template not found']);
  exit;
}
$template = $tmpl->fields;

$renderer    = new LabelRenderer();
$label_items = array();

foreach ($skus as $item) {
  $sku = db_input($item['sku'] ?? '');
  if ($sku === '') continue;
  $qty   = max(1, (int)($item['qty'] ?? 1));   // label content — printed on the label, does not affect copy count
  $count = max(1, (int)($item['count'] ?? 1)); // how many label copies to actually print
  $row = $db->Execute("SELECT sku, description_short, description_sales, full_price, item_cost, upc_code, item_weight FROM " . TABLE_INVENTORY . " WHERE sku = '" . $sku . "'");
  if ($row->EOF) continue;
  $vars = $renderer->buildVars($row->fields);
  $vars['pack_qty'] = (string)$qty;
  for ($i = 0; $i < $count; $i++) {
    $label_items[] = $vars;
  }
}

if (empty($label_items)) {
  echo json_encode(['error' => 'No matching inventory items']);
  exit;
}

$printer_type = $template['printer_type'];

if ($printer_type === 'zpl' && !empty($template['zpl_template'])) {
  $zpl_labels = array();
  foreach ($label_items as $vars) {
    try {
      $zpl_labels[] = $renderer->renderZpl($template['zpl_template'], $vars);
    } catch (\Throwable $e) {
      $zpl_labels[] = '^XA^FO20,20^A0N,20,20^FDError^FS^XZ';
    }
  }
  echo json_encode(['type' => 'zpl', 'labels' => $zpl_labels]);
} else {
  // HTML path (also fallback when zpl_template is empty)
  $html_labels = array();
  $html_tpl    = $template['html_template'] ?? '';
  foreach ($label_items as $vars) {
    try {
      $html_labels[] = $renderer->renderHtml($html_tpl, $vars);
    } catch (\Throwable $e) {
      $html_labels[] = '<span style="color:red">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span>';
    }
  }
  $page_html = $renderer->buildPrintPage($html_labels, (float)$template['width_mm'], (float)$template['height_mm']);
  echo json_encode(['type' => 'html', 'html' => $page_html]);
}
