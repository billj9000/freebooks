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
//  Path: /modules/labels/classes/label_renderer.php

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Picqer\Barcode\BarcodeGeneratorSVG;

class LabelRenderer {

  private BarcodeGeneratorSVG $barcodeGen;

  public function __construct() {
    $this->barcodeGen = new BarcodeGeneratorSVG();
  }

  public function buildVars(array $row): array {
    global $currencies;
    $price = isset($currencies) ? $currencies->format($row['full_price'] ?? 0) : number_format((float)($row['full_price'] ?? 0), 2);
    $cost  = isset($currencies) ? $currencies->format($row['item_cost']  ?? 0) : number_format((float)($row['item_cost']  ?? 0), 2);

    $sku         = $row['sku'] ?? '';
    $barcode_svg = '';
    if ($sku !== '') {
      try {
        $barcode_svg = $this->barcodeGen->getBarcode($sku, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 50);
      } catch (\Throwable $e) {
        $barcode_svg = '';
      }
    }

    return [
      'sku'               => htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'),
      'description'       => htmlspecialchars($row['description_sales']    ?? $row['description_short'] ?? '', ENT_QUOTES, 'UTF-8'),
      'short_description' => htmlspecialchars($row['description_short']    ?? '', ENT_QUOTES, 'UTF-8'),
      'price'             => htmlspecialchars($price, ENT_QUOTES, 'UTF-8'),
      'cost'              => htmlspecialchars($cost,  ENT_QUOTES, 'UTF-8'),
      'upc'               => htmlspecialchars($row['upc_code'] ?? '', ENT_QUOTES, 'UTF-8'),
      'weight'            => htmlspecialchars((string)($row['item_weight'] ?? ''), ENT_QUOTES, 'UTF-8'),
      'barcode_svg'       => $barcode_svg, // raw SVG — caller uses | raw in template
    ];
  }

  public function renderHtml(string $template, array $vars): string {
    $loader = new ArrayLoader(['label' => $template]);
    $twig   = new Environment($loader, ['autoescape' => false]);
    return $twig->render('label', $vars);
  }

  public function renderZpl(string $template, array $vars): string {
    $loader = new ArrayLoader(['label' => $template]);
    $twig   = new Environment($loader, ['autoescape' => false]);
    return $twig->render('label', $vars);
  }

  public function buildPrintPage(array $labelHtmlArray, float $widthMm, float $heightMm): string {
    $w = number_format($widthMm,  2, '.', '');
    $h = number_format($heightMm, 2, '.', '');
    $labels_html = '';
    $last = count($labelHtmlArray) - 1;
    foreach ($labelHtmlArray as $i => $html) {
      $pb = ($i < $last) ? 'page-break-after:always;' : '';
      $labels_html .= '<div class="label-item" style="width:' . $w . 'mm;height:' . $h . 'mm;overflow:hidden;box-sizing:border-box;' . $pb . '">' . $html . '</div>' . "\n";
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
      . '<title>Labels</title>'
      . '<style>'
      . '@page{size:' . $w . 'mm ' . $h . 'mm;margin:0}'
      . 'body{margin:0;padding:0}'
      . '.label-item{font-family:sans-serif;font-size:10pt}'
      . '</style>'
      . '</head><body>'
      . $labels_html
      . '<script>window.onload=function(){window.print();}</script>'
      . '</body></html>';
  }
}
