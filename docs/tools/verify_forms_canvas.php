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
// +-----------------------------------------------------------------+
//  Path: /docs/tools/verify_forms_canvas.php
//
/**
 * A box drawn at 140 mm lands on the text rendered at 140 mm (design §13.8
 * open risk 1, settled by §13.7 step 3).
 *
 * The canvas puts element outlines over a rasterized PDF, and the whole
 * approach is worth nothing if the two do not line up. The overlay converts
 * with one line —
 *
 *     pixels = millimetres × scale × 72 / 25.4
 *
 * — and that line rests on three claims about what the renderer emits, none of
 * which a browser could tell you were wrong: it would simply look slightly off.
 *
 *   1. the page is EXACTLY the declared size, so pdf.js reports a viewport whose
 *      scale is the only unknown;
 *   2. an element at (x, y) mm is drawn at (x × 72/25.4) points from the left
 *      and (x × 72/25.4) points DOWN from the top — absolute page coordinates,
 *      not margin-relative ones;
 *   3. that holds across page sizes and both orientations.
 *
 * So this renders real documents and reads the coordinates back out of the PDF
 * content stream, which is the only place the truth is. It is a pinning test:
 * an upgrade to TCPDF that changed the unit, or a change to LayoutPdfRenderer
 * that made positions margin-relative, would move every box on every form and
 * nothing else in the suite would notice.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_canvas.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{CanvasLayout, FormRunner, SampleData};
use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Definition\DefinitionLoader;
use FreeBooks\Forms\Document\{
    Box, LayoutDocument, Line, PageSetup, Record, Rect, RenderOptions, Template
};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

$renderer = new LayoutPdfRenderer(
    formatter:      new Formatter(
                      currencies:      $currencies,
                      defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                      lookups:         forms_formatter_lookups()),
    defaultFont:    defined('PDF_DEFAULT_FONT') ? PDF_DEFAULT_FONT : 'helvetica',
    rowSpacing:     2.0,
    continuedLabel: 'Continued',
    pageOfLabel:    'of',
    imageDirectory: PF_DIR_MY_REPORTS . 'images',
);

/** The page box, in points. */
function mediaBox(string $pdf): ?array
{
    if (!preg_match('/\/MediaBox\s*\[([^\]]*)\]/', $pdf, $found)) return null;
    $numbers = preg_split('/\s+/', trim($found[1]));
    return count($numbers) === 4 ? array_map('floatval', $numbers) : null;
}

/**
 * The drawing operators, with the page streams inflated.
 *
 * TCPDF compresses them when zlib is available, so reading the bytes as text
 * finds nothing — which would make every assertion below vacuously true. The
 * count is returned so "no operators found" can fail loudly instead.
 *
 * @return list<string>
 */
function operators(string $pdf): array
{
    $lines = [];
    if (!preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams)) return $lines;
    foreach ($streams[1] as $raw) {
        $plain = @gzuncompress($raw);
        if ($plain === false) $plain = @gzinflate($raw);
        if ($plain === false) $plain = $raw;            // uncompressed already
        if (!preg_match('//u', $plain)) continue;       // a font or an image
        foreach (preg_split('/\r?\n/', $plain) as $line) {
            $line = trim($line);
            if ($line !== '') $lines[] = $line;
        }
    }
    return $lines;
}

/** Millimetres to points, the constant the overlay divides the scale by. */
function pt(float $mm): float { return $mm * 72.0 / 25.4; }

/** Within a twentieth of a millimetre — tighter than any renderer's rounding. */
function near(float $a, float $b): bool { return abs($a - $b) < 0.15; }

// ─────────────────────────────────────────────────────────────────────────────
echo "=== the constant the overlay converts with ===\n";

ok('CanvasLayout publishes 72/25.4 points per millimetre',
   abs(CanvasLayout::POINTS_PER_MM - (72.0 / 25.4)) < 1e-12,
   (string)CanvasLayout::POINTS_PER_MM);

// The overlay hard-codes the same number in JavaScript. Two copies of a constant
// are two chances to change one of them.
$js = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/canvas/js_include.php');
ok('the canvas script converts with the same constant',
   str_contains($js, 'const PT_PER_MM = 72 / 25.4'),
   'the overlay and the server must agree on what a millimetre is');
ok('the canvas script positions boxes with scale × PT_PER_MM',
   (bool)preg_match('/function px\(mm\)\s*\{\s*return mm \* scale \* PT_PER_MM;/', $js));

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a box is drawn where the canvas will put it ===\n";

// Every combination that could hide a mistake: portrait and landscape, a small
// page and a large one. A wrong axis passes on a square page and on nothing else.
$cases = [
    ['A4',     'P', 210.0, 297.0],
    ['A4',     'L', 297.0, 210.0],
    ['Letter', 'P', 215.9, 279.4],
    ['A5',     'L', 210.0, 148.0],
    // Mixed case on purpose. TCPDF's format table is keyed in UPPERCASE and it
    // answers an unknown name with A4 rather than an error, so "Letter" — which
    // is what every shipped definition stores — printed on A4 until
    // PdfCanvas::paperFormat() was put in front of it.
    ['Legal',  'P', 215.9, 355.6],
];

foreach ($cases as [$size, $orientation, $widthMm, $heightMm]) {
    $x = 20.0; $y = 30.0; $w = 50.0; $h = 10.0;
    $document = new LayoutDocument(
        title:    'ruler',
        template: new Template([new Rect(new Box($x, $y, $w, $h))]),
        records:  [new Record()],
        page:     new PageSetup(size: $size, orientation: $orientation),
    );
    $pdf  = $renderer->render($document, new RenderOptions(filename: 'ruler.pdf'))->bytes;
    $what = "$size $orientation";

    $media = mediaBox($pdf);
    if ($media === null) { ok("$what: the page declares a size", false); continue; }
    $pageWidth  = $media[2] - $media[0];
    $pageHeight = $media[3] - $media[1];

    // Claim 1. If the page is not the size it says, pdf.js's viewport carries
    // the error and every box inherits it.
    ok("$what: the page is $widthMm × $heightMm mm",
       near($pageWidth, pt($widthMm)) && near($pageHeight, pt($heightMm)),
       sprintf('%.3f × %.3f pt', $pageWidth, $pageHeight));

    $lines = operators($pdf);
    if ($lines === []) { ok("$what: the content stream can be read", false, 'no operators found'); continue; }

    $rectangle = null;
    foreach ($lines as $line) {
        if (preg_match('/^(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) re/', $line, $found)) {
            $rectangle = array_map('floatval', array_slice($found, 1));
            break;
        }
    }
    if ($rectangle === null) { ok("$what: the rectangle is drawn", false); continue; }

    // Claims 2 and 3, read straight out of the file.
    ok("$what: x = $x mm from the left edge",   near($rectangle[0], pt($x)),
       sprintf('%.3f pt, wanted %.3f', $rectangle[0], pt($x)));
    ok("$what: y = $y mm DOWN from the top",    near($pageHeight - $rectangle[1], pt($y)),
       sprintf('%.3f pt down, wanted %.3f', $pageHeight - $rectangle[1], pt($y)));
    ok("$what: it is $w × $h mm",
       near($rectangle[2], pt($w)) && near(abs($rectangle[3]), pt($h)),
       sprintf('%.3f × %.3f pt', $rectangle[2], $rectangle[3]));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== margins do not move an element ===\n";

// The failure this exists to catch: SetXY is absolute, but a renderer that
// switched to margin-relative placement would still produce a plausible page —
// every element shifted by the margin, which looks like a form drawn slightly
// low rather than like a defect.
$positions = [];
foreach ([0.0, 25.0] as $margin) {
    $document = new LayoutDocument(
        title:    'margins',
        template: new Template([new Rect(new Box(40.0, 60.0, 20.0, 20.0))]),
        records:  [new Record()],
        page:     new PageSetup(size: 'A4', orientation: 'P',
                    marginTop: $margin, marginBottom: $margin,
                    marginLeft: $margin, marginRight: $margin),
    );
    foreach (operators($renderer->render($document)->bytes) as $line) {
        if (preg_match('/^(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) re/', $line, $found)) {
            $positions[] = [(float)$found[1], (float)$found[2]];
            break;
        }
    }
}
ok('a 25 mm margin leaves the box where it was',
   count($positions) === 2 && near($positions[0][0], $positions[1][0])
   && near($positions[0][1], $positions[1][1]),
   count($positions) === 2 ? sprintf('%.2f,%.2f vs %.2f,%.2f',
       $positions[0][0], $positions[0][1], $positions[1][0], $positions[1][1]) : 'not rendered');
ok('and that box is at 40 mm, not at the margin',
   count($positions) === 2 && near($positions[0][0], pt(40.0)));

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the boxes the canvas is given ===\n";

// A Line stores its END POINT as a signed offset, so a line drawn up or to the
// left has a negative side. A browser cannot position a negative-width box: it
// renders nothing, silently, and only for the elements that happen to point
// that way. 64 of the shipped elements are lines.
$upward = CanvasLayout::boxes(new Template([
    new Line(new Box(100.0, 200.0, 0.0, -50.0), 'V'),
    new Line(new Box(100.0, 200.0, -30.0, 0.0), 'H'),
]));
// A text element's stored height is ONE LINE — TCPDF's MultiCell grows the cell
// downward — so the box says where the text starts, not where it ends. The
// canvas draws those with a dashed lower edge; a flag that stopped being set
// would turn that into a silent claim that an address block is 4.5 mm tall.
$kinds = [];
foreach (CanvasLayout::boxes(new Template([
    new \FreeBooks\Forms\Document\StaticText('x', new Box(1, 1, 10, 4)),
    new \FreeBooks\Forms\Document\Field(
        \FreeBooks\Forms\Document\Binding::record('a'), new Box(1, 1, 10, 4)),
    new \FreeBooks\Forms\Document\FieldBlock(
        [new \FreeBooks\Forms\Document\BlockPart(
            \FreeBooks\Forms\Document\Binding::record('a'), 'nl')], new Box(1, 1, 10, 4)),
    new \FreeBooks\Forms\Document\PageNumber(new Box(1, 1, 10, 4)),
    new Rect(new Box(1, 1, 10, 4)),
    new Line(new Box(1, 1, 10, 0)),
])) as $box) $kinds[$box['kind']] = $box['grows'];

ok('a text element is marked as growing downward',
   ($kinds['text'] ?? false) && ($kinds['field'] ?? false)
   && ($kinds['block'] ?? false) && ($kinds['pagenum'] ?? false));
ok('a rule and a rectangle are not', ($kinds['rect'] ?? true) === false
   && ($kinds['line'] ?? true) === false);

$canvasJs = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/canvas/js_include.php');
ok('the canvas draws a growing box with a dashed lower edge',
   str_contains($canvasJs, "border-bottom:1px dashed"));

ok('a line drawn upward becomes a positive box', $upward[0]['height'] === 50.0
   && $upward[0]['y'] === 150.0, json_encode($upward[0]));
ok('a line drawn leftward becomes a positive box', $upward[1]['width'] === 30.0
   && $upward[1]['x'] === 70.0, json_encode($upward[1]));

$every = [];
foreach (['text','field','block','table','line','rect','image','pagenum','barcode'] as $kind) $every[$kind] = 0;

$loader = new DefinitionLoader($catalog);
$forms  = [];
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_type <> '0' and doc_ext = 'frm' order by id");
while (!$result->EOF) { $forms[(int)$result->fields['id']] = (string)$result->fields['doc_title']; $result->MoveNext(); }

$boxed = $unknown = $offPage = 0;
$strays = [];
foreach ($forms as $id => $title) {
    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    if (!$definition->isForm() || $definition->template === null) continue;

    $page  = $definition->page;
    $wide  = strtoupper($page->orientation) === 'L';
    foreach (CanvasLayout::boxes($definition->template) as $box) {
        $boxed++;
        if ($box['kind'] === 'unknown') { $unknown++; continue; }
        $every[$box['kind']] = ($every[$box['kind']] ?? 0) + 1;
        // Negative coordinates would place a box outside the canvas, where it
        // cannot be pointed at and cannot be seen.
        if ($box['x'] < 0 || $box['y'] < 0 || $box['width'] < 0 || $box['height'] < 0) {
            $strays[] = "pf_$id $box[kind] at $box[x],$box[y] $box[width]×$box[height]";
        }
    }
}
ok("every element on every form becomes a box ($boxed across " . count($forms) . " forms)", $boxed > 0);
ok('no element type is unrecognised', $unknown === 0, "$unknown unknown");
ok('no box has a negative position or size', $strays === [],
   implode('; ', array_slice($strays, 0, 3)));
echo '  [--] by type: ' . implode(', ', array_map(
    fn($k, $n) => "$k $n", array_keys(array_filter($every)), array_filter($every))) . "\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== an image's outline is the size it is actually drawn ===\n";

// An Image stores a WIDTH and no height — every logo in the shipped set does —
// because TCPDF works the other side out from the file's aspect ratio. The
// canvas has to do the same arithmetic or the logo gets a two-pixel sliver
// where its outline should be. Unlike a block of text this is knowable, so it
// is measured; and the measurement is checked against the ink.
$images = PF_DIR_MY_REPORTS . 'images';
$file   = null;
foreach (glob($images . '/*.{png,jpg,jpeg,gif}', GLOB_BRACE) ?: [] as $candidate) {
    $size = @getimagesize($candidate);
    if ($size !== false && (int)$size[0] > 0 && (int)$size[1] > 0) { $file = $candidate; break; }
}
if ($file === null) {
    echo "  [--] no usable image in $images; nothing to measure\n";
} else {
    $pixels = getimagesize($file);
    $wanted = 80.0;
    $ratio  = (float)$pixels[1] / (float)$pixels[0];

    $boxes = CanvasLayout::boxes(new Template([
        new \FreeBooks\Forms\Document\Image(basename($file), new Box(8.0, 8.0, $wanted, null)),
    ]), $images);
    ok('a width-only image gets a height from the file',
       near((float)$boxes[0]['height'], $wanted * $ratio),
       sprintf('%.2f mm, wanted %.2f', $boxes[0]['height'], $wanted * $ratio));
    ok('and is not reported as unmeasured', $boxes[0]['unmeasured'] === false);

    // Against the ink: TCPDF draws an image by concatenating a matrix whose
    // first and fourth entries ARE its width and height in points.
    $document = new LayoutDocument(
        title: 'logo', records: [new Record()], page: new PageSetup(size: 'A4', orientation: 'P'),
        template: new Template([
            new \FreeBooks\Forms\Document\Image(basename($file), new Box(8.0, 8.0, $wanted, null)),
        ]));
    $drawn = null;
    foreach (operators($renderer->render($document)->bytes) as $line) {
        // TCPDF writes the whole thing on one line: `q <a> 0 0 <d> <e> <f> cm /I0 Do Q`,
        // so this cannot anchor to the start of the line — which is how it found
        // nothing the first time and reported a defect that was the tool's.
        if (preg_match('/([\d.]+) 0 0 ([\d.]+) [\d.]+ [\d.]+ cm/', $line, $found)) {
            $drawn = [(float)$found[1], (float)$found[2]];
            break;
        }
    }
    if ($drawn === null) {
        ok('the image is drawn with a measurable transform', false, 'no cm operator found');
    } else {
        ok('the outline matches the width the renderer draws',
           near($drawn[0], pt($wanted)), sprintf('%.2f pt, wanted %.2f', $drawn[0], pt($wanted)));
        ok('the outline matches the HEIGHT the renderer draws',
           near($drawn[1], pt($wanted * $ratio)),
           sprintf('%.2f pt, wanted %.2f', $drawn[1], pt($wanted * $ratio)));
    }

    // What cannot be measured is said to be unmeasured rather than guessed at
    // silently: a file that is not there, and an ImgLink whose name is data.
    $missing = CanvasLayout::boxes(new Template([
        new \FreeBooks\Forms\Document\Image('no-such-file.png', new Box(8.0, 8.0, 40.0, null)),
    ]), $images);
    ok('a missing image file is marked unmeasured', $missing[0]['unmeasured'] === true);

    $bound = CanvasLayout::boxes(new Template([
        new \FreeBooks\Forms\Document\Image(
            \FreeBooks\Forms\Document\Binding::record('logo'), new Box(8.0, 8.0, 40.0, null)),
    ]), $images);
    ok('an image whose filename comes from the record is marked unmeasured',
       $bound[0]['unmeasured'] === true);
    ok('no measured box is left with a zero height',
       $boxes[0]['height'] > 0 && $missing[0]['height'] > 0 && $bound[0]['height'] > 0);

    $canvasJs2 = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/canvas/js_include.php');
    ok('the canvas dashes an unmeasured box all round',
       str_contains($canvasJs2, "box.unmeasured"));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a form with no record still renders something to align against ===\n";

$sampleForm = null;
foreach ($forms as $id => $title) {
    try { $definition = $loader->load($id); } catch (\Throwable) { continue; }
    if ($definition->isForm() && $definition->template !== null
        && count($definition->template->elements) > 10) { $sampleForm = $definition; break; }
}
if ($sampleForm === null) {
    ok('a form is available to sample', false);
} else {
    $dataset = $sampleForm->datasetId !== null && $catalog->has($sampleForm->datasetId)
        ? $catalog->get($sampleForm->datasetId) : null;
    $record  = SampleData::record($sampleForm->template, $dataset);

    ok('the sample record carries values', count($record->values) > 0,
       count($record->values) . ' value(s)');
    // A form whose line-item table is empty renders a heading and a void, which
    // is the middle of the page missing — exactly where an author is looking.
    $hasTable = false;
    foreach ($sampleForm->template->elements as $element) {
        if ($element instanceof \FreeBooks\Forms\Document\Table) { $hasTable = true; break; }
    }
    if ($hasTable) {
        ok('the sample fills the line-item table', count($record->tables) > 0);
    }
    ok('it is stable between calls',
       json_encode($record->values) === json_encode(
           SampleData::record($sampleForm->template, $dataset)->values));

    $document = new LayoutDocument(
        title: $sampleForm->title, template: $sampleForm->template,
        records: [$record], page: $sampleForm->page);
    $out = $renderer->render($document, new RenderOptions(filename: 'sample.pdf'));
    ok('and it renders as a PDF', str_starts_with($out->bytes, '%PDF') && $out->size() > 1000,
       $out->size() . ' bytes');
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== pdf.js is vendored where the page loads it from ===\n";

$vendor = dirname(rtrim(DIR_FS_MODULES, '/')) . '/ext/pdfjs/';
foreach (['pdf.min.mjs', 'pdf.worker.min.mjs', 'LICENSE'] as $file) {
    ok("ext/pdfjs/$file is present", is_file($vendor . $file));
}
// Both are loaded by URL from the same directory; a page that finds the library
// and not its worker fails at the first render with a confusing message.
ok('the page loads the library and the worker from that directory',
   str_contains($js, 'ext/pdfjs/pdf.min.mjs') && str_contains($js, 'ext/pdfjs/pdf.worker.min.mjs'));
ok('the vendored files keep their own licence banner',
   str_contains((string)file_get_contents($vendor . 'pdf.min.mjs'), '@licstart')
   && !str_contains((string)file_get_contents($vendor . 'pdf.min.mjs'), 'FreeBooks ERP'),
   'third-party code must not carry the FreeBooks header');

echo "\n" . ($failures ? "$failures check(s) FAILED\n" : "all checks passed\n");
exit($failures ? 1 : 0);
