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
use Twig\Sandbox\SecurityPolicy;
use Twig\Extension\SandboxExtension;
use Picqer\Barcode\BarcodeGeneratorSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class LabelRenderer {

  private BarcodeGeneratorSVG $barcodeGen;
  private QRCode $qrGen;

  public function __construct() {
    $this->barcodeGen = new BarcodeGeneratorSVG();
    $this->qrGen = new QRCode(new QROptions([
      'scale'           => 3,
      'quietzoneSize'   => 2,
      'svgAddXmlHeader' => false,
      'imageBase64'     => false,
    ]));
  }

  public function buildVars(array $row): array {
    global $currencies;
    $price = isset($currencies) ? $currencies->format($row['full_price'] ?? 0) : number_format((float)($row['full_price'] ?? 0), 2);
    $cost  = isset($currencies) ? $currencies->format($row['item_cost']  ?? 0) : number_format((float)($row['item_cost']  ?? 0), 2);

    $sku         = $row['sku'] ?? '';
    $barcode_svg = '';
    $qrcode_svg  = '';
    if ($sku !== '') {
      try {
        $barcode_svg = $this->barcodeGen->getBarcode($sku, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 50);
      } catch (\Throwable $e) {
        $barcode_svg = '';
      }
      try {
        $qrcode_svg = $this->qrGen->render($sku);
      } catch (\Throwable $e) {
        $qrcode_svg = '';
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
      'qrcode_svg'        => $qrcode_svg,  // raw SVG — caller uses | raw in template
    ];
  }

  // The template *source* itself is user-authored (saved label templates / the live preview
  // textarea), not just the variables — so this is executing untrusted Twig code, not just
  // untrusted data. Without a sandbox, Twig's `map`/`filter`/`reduce` can invoke arbitrary
  // PHP callables (e.g. `{{ ['id']|map('system') }}`), which is RCE. The sandbox below allows
  // only what label templates legitimately need and nothing that can execute code.
  //
  // The `if` tag (which also covers elseif/else/endif) is allowed so templates can do simple
  // conditional layout, e.g. `{% if upc %}...{% endif %}`. This is safe because the sandbox
  // checks tags, filters, functions, methods and property access INDEPENDENTLY: allowing the
  // `if` tag does not loosen the filter/function/method/property allowlists, so an attacker
  // writing `{% if x|map('system') %}` is still stopped by the filter policy. Verified: the
  // RCE gadgets (`map`/`filter` callables, `constant()`, `attribute()` onto an object's
  // method/property) remain blocked identically with or without `if` — the label context
  // holds only strings, and `_self` is the template-name string in Twig 3, not the object.
  //
  // NOTE: `for` is deliberately NOT allowed — Twig range loops (`{% for i in 1..N %}`) are a
  // CPU/memory DoS vector. Only add it if there's a real need, and with a bounded range.
  private function sandboxedTwig(ArrayLoader $loader): Environment {
    $twig   = new Environment($loader, ['autoescape' => false]);
    $policy = new SecurityPolicy(['if'], ['raw'], [], [], []);
    $twig->addExtension(new SandboxExtension($policy, true));
    return $twig;
  }

  public function renderHtml(string $template, array $vars): string {
    $loader = new ArrayLoader(['label' => $template]);
    return $this->sandboxedTwig($loader)->render('label', $vars);
  }

  public function renderZpl(string $template, array $vars): string {
    $loader = new ArrayLoader(['label' => $template]);
    return $this->sandboxedTwig($loader)->render('label', $vars);
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
