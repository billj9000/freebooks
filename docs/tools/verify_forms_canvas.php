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
use FreeBooks\Forms\Definition\{DefinitionCodec, DefinitionException, DefinitionLoader, TemplateForm};
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
  linkedImageDirectory: DIR_FS_MY_FILES . $_SESSION['company'] . '/',
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
        // No UTF-8 filter. It was here to skip fonts and images, and it skipped
        // PAGES: a content stream carries encoded glyph bytes, so one form's
        // page failed the test and every rectangle on it vanished — silently,
        // reporting zero rather than failing. The patterns below match ASCII
        // digits and two-letter operators, which binary noise cannot forge.
        foreach (preg_split('/\r?\n/', $plain) as $line) {
            $line = trim($line);
            if ($line !== '') $lines[] = $line;
        }
    }
    return $lines;
}

/** Millimetres to points, the constant the overlay divides the scale by. */
function pt(float $mm): float { return $mm * 72.0 / 25.4; }

/**
 * TCPDF's own nondeterminism removed — see verify_forms_native.php, where the
 * full list of six varying fields is set out and the control that proves this
 * complete lives.
 */
function normalisePdf(string $bytes): string
{
    $bytes = preg_replace('/\/(?:CreationDate|ModDate|LastModified|M)\s*\(D:[^)]*\)/', '/Date()', $bytes);
    $bytes = preg_replace('/\/ID\s*\[[^\]]*\]/', '/ID[]', $bytes);
    $bytes = preg_replace('/uuid:[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}/i', 'uuid:', $bytes);
    return preg_replace('/<(xmp:(?:Create|Modify|Metadata)Date)>[^<]*<\/\1>/', '<$1/>', $bytes);
}

/** Within a twentieth of a millimetre — tighter than any renderer's rounding. */
function near(float $a, float $b): bool { return abs($a - $b) < 0.15; }

/**
 * Every rectangle drawn in these operators, as [x, y, w, h] in points.
 *
 * Deliberately NOT anchored to the start of the line. TCPDF emits a rectangle
 * on the same line as the colour and line-width operators that precede it —
 * `0.35 w 0 0 0 RG 22.68 226.77 566.93 45.35 re S` — so `^` matches only in a
 * document with nothing else in it. The ruler cases below are exactly that,
 * which is why an anchored version passed them and found zero rectangles on
 * every real form.
 *
 * @param list<string> $lines
 * @return list<array{0: float, 1: float, 2: float, 3: float}>
 */
function rectangles(array $lines): array
{
    $found = [];
    foreach ($lines as $line) {
        if (preg_match_all('/(?:^|\s)(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) re(?=\s|$)/',
                $line, $all, PREG_SET_ORDER)) {
            foreach ($all as $one) $found[] = array_map('floatval', array_slice($one, 1));
        }
    }
    return $found;
}

/**
 * Where two value graphs first differ, or none.
 *
 * NOT serialize(): that encodes object identity as well as value, so two
 * structurally identical templates compare unequal because one shared a Style
 * instance between elements where the other built two.
 *
 * @return list<string>
 */
function differences(mixed $a, mixed $b, string $path = 'template', int $limit = 6): array
{
    if (is_object($a) && is_object($b)) {
        if ($a::class !== $b::class) return ["$path is " . $a::class . ' vs ' . $b::class];
        if ($a instanceof \UnitEnum) return $a === $b ? [] : ["$path: different case"];
        return differences((array)$a, (array)$b, $path, $limit);
    }
    if (is_object($a) !== is_object($b)) {
        return [sprintf('%s is %s vs %s', $path, is_object($a) ? $a::class : var_export($a, true),
            is_object($b) ? $b::class : var_export($b, true))];
    }
    if (is_array($a) && is_array($b)) {
        if (array_keys($a) !== array_keys($b)) return ["$path has " . count($a) . ' vs ' . count($b) . ' entries'];
        $found = [];
        foreach ($a as $key => $value) {
            $found = array_merge($found, differences($value, $b[$key], "$path.$key", $limit));
            if (count($found) >= $limit) break;
        }
        return array_slice($found, 0, $limit);
    }
    return $a === $b ? [] : ["$path: " . var_export($a, true) . ' vs ' . var_export($b, true)];
}

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

    $drawn     = rectangles($lines);
    $rectangle = $drawn[0] ?? null;
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
    $drawn = rectangles(operators($renderer->render($document)->bytes));
    if ($drawn !== []) $positions[] = [$drawn[0][0], $drawn[0][1]];
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

// ── the gestures (§13.17) ────────────────────────────────────────────────────
//
// There is no JavaScript engine here or in the container, so these can only
// assert that what the browser needs is PRESENT. They are worth having anyway:
// each names a thing whose absence is silent — a cursor that never changes, a
// selection that quietly collapses to one — rather than a thing that throws.
// docs/tools/render_canvas_js.php is what proves the file parses at all.

foreach (['nwse-resize'  => 'a corner offers the diagonal double arrow',
          'nesw-resize'  => 'and the other diagonal on the other pair',
          'ns-resize'    => 'a top or bottom side offers the vertical double arrow',
          'ew-resize'    => 'a left or right side offers the horizontal one'] as $cursor => $what) {
    ok($what, str_contains($canvasJs, "'" . $cursor . "'"));
}
ok('all four sides and all four corners are hit-tested',
   preg_match_all("/zone \\+= '[nsew]'/", $canvasJs) === 4);

ok('the selection is a set rather than one index', str_contains($canvasJs, 'const chosen = new Set()'));
ok('and the anchor is kept apart from it, for the inspector',
   str_contains($canvasJs, 'let selected = null'));
// The point of the whole feature: one delta, applied to every member. Snapping
// each member separately is what would bend the block being kept together.
ok('a group drag snaps once and applies the same delta to every member',
   str_contains($canvasJs, 'const byX = rounded(from.x + dx) - from.x')
   && str_contains($canvasJs, 'boxes[index].x = fine(start.x + byX)'));
ok('a drag is measured from where it began, not from the last event',
   str_contains($canvasJs, 'toMm(event.clientX - dragging.startX)'));
// The band ENCLOSES. Touching was the first answer and it swept up the rules
// above and below anything drawn across the middle of a form; four >= / <=
// against the band's own edges is what makes the answer exact.
ok('the band selects only what it wholly encloses',
   str_contains($canvasJs,
       'if (bx >= x1 && bx + bw <= x2 && by >= y1 && by + bh <= y2) chosen.add(index);'));
ok('undo restores the whole selection, not just the anchor',
   str_contains($canvasJs, 'chosen: Array.from(chosen)'));
ok('delete splices from the highest index down',
   str_contains($canvasJs, 'sort(function (a, b) { return b - a; })'));
// A shift-click's own meaning is "extend the text selection". Left alone it
// paints a blue swathe over the canvas and hides the selection that DID happen,
// which is exactly how the feature reads as broken.
// Asserted by ORDER rather than by a character window: the branch is mostly
// comment, and a window wide enough to clear it is wide enough to match the
// next handler down — the same fragility that has now broken two checks here.
$addAt     = strpos($canvasJs, 'if (add) {');
$preventAt = $addAt === false ? false : strpos($canvasJs, 'event.preventDefault();', $addAt);
$selectAt  = $addAt === false ? false : strpos($canvasJs, 'select(index, true);', $addAt);
ok('a modified click refuses the browser\'s own text selection',
   $addAt !== false && $preventAt !== false && $selectAt !== false && $preventAt < $selectAt);
ok('only the primary button begins a gesture',
   str_contains($canvasJs, 'if (event.button !== 0) return;'));
// Being selected must not change which element a click lands on, or on a form
// with overlapping elements the second shift-click keeps hitting the first.
ok('selection does not raise a box above its neighbours',
   str_contains($canvasJs, "div.style.zIndex = hovered ? '2' : '1';"));

ok('a line drawn upward becomes a positive box', $upward[0]['height'] === 50.0
   && $upward[0]['y'] === 150.0, json_encode($upward[0]));
ok('a line drawn leftward becomes a positive box', $upward[1]['width'] === 30.0
   && $upward[1]['x'] === 70.0, json_encode($upward[1]));

$every = [];
foreach (['text','field','block','table','line','rect','image','pagenum','barcode'] as $kind) $every[$kind] = 0;

$loader = new DefinitionLoader($catalog);

/**
 * The names a form can bind to, exactly as the page works them out.
 *
 * A dataset declares its fields. A provider declares nothing — §13.8 risk 3 —
 * so it is asked the only way it can be: run the form once and read the keys
 * back off the Record. The template's own bindings are always allowed, or a
 * form would lose a field the moment its record table went empty.
 *
 * @return array<string,true>
 */
$allowedFor = function ($definition) use ($catalog, $db): array {
    $allowed = [];
    foreach ($definition->template?->elements ?? [] as $element) {
        if (($element instanceof \FreeBooks\Forms\Document\Field
             || $element instanceof \FreeBooks\Forms\Document\Barcode)
            && $element->bind->scope !== \FreeBooks\Forms\Document\Scope::Context) {
            $allowed[$element->bind->name] = true;
        }
        if ($element instanceof \FreeBooks\Forms\Document\Table) {
            foreach ($element->columns as $column) $allowed[$column->bind->name] = true;
        }
    }
    if ($definition->datasetId !== null && $catalog->has($definition->datasetId)) {
        foreach (array_keys($catalog->get($definition->datasetId)->fields) as $key) $allowed[$key] = true;
    }
    return $allowed;
};

$forms  = [];
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_type <> '0' and doc_ext = 'frm' order by id");
while (!$result->EOF) { $forms[(int)$result->fields['id']] = (string)$result->fields['doc_title']; $result->MoveNext(); }

$boxed = $unknown = $offPage = 0;
$strays = [];
foreach ($forms as $id => $title) {
    try { $definition = $loader->load($id); } catch (DefinitionException) { continue; }
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
    try { $definition = $loader->load($id); } catch (DefinitionException) { continue; }
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
echo "\n=== opening a form and saving it unchanged changes nothing ===\n";

// The property an editor loses first, and the reason step 4 has this check at
// all: anything the canvas cannot represent disappears on the first save, in
// silence. A form carries structures the canvas draws no control for — a
// block's parts, a table's columns, a bound image filename — and those have to
// survive being loaded, drawn and written back.
$survived = $identicalBytes = $forms_seen = 0;
$lost = [];

foreach ($forms as $id => $title) {
    try { $definition = $loader->load($id); } catch (DefinitionException) { continue; }
    if (!$definition->isForm() || $definition->template === null) continue;
    $forms_seen++;

    // Exactly what the browser posts back when nothing was touched.
    $request = ['title' => $definition->title,
                'element' => TemplateForm::toRequest($definition->template)];

    $binding = new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names());
    try {
        $again = $binding->apply($definition, $request, $allowedFor($definition));
    } catch (\Throwable $e) {
        $lost[] = "pf_$id ($title): " . $e->getMessage();
        continue;
    }
    $where = differences($definition->template, $again->template, "pf_$id");
    if ($where) { $lost[] = implode('; ', $where); continue; }
    if ($binding->warnings()) { $lost[] = "pf_$id: " . implode(' ', $binding->warnings()); continue; }
    $survived++;

    // And it must still PRINT the same. A template that compares equal and
    // renders differently would mean the comparison is looking at the wrong
    // thing.
    $document = new LayoutDocument(
        title: $definition->title, template: $definition->template,
        records: [SampleData::record($definition->template, null)], page: $definition->page);
    $after = new LayoutDocument(
        title: $again->title, template: $again->template,
        records: [SampleData::record($again->template, null)], page: $again->page);
    if (normalisePdf($renderer->render($document)->bytes)
        === normalisePdf($renderer->render($after)->bytes)) $identicalBytes++;
}

ok("all $forms_seen form(s) survive an untouched save", $forms_seen > 0 && $survived === $forms_seen,
   implode(' | ', array_slice($lost, 0, 3)));
ok('and print identically afterwards', $identicalBytes === $survived,
   "$identicalBytes of $survived");

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== an author moves what exists, and names what exists ===\n";

// Preferably a form that carries an IMAGE, so the source checks below are not
// silently skipped — a section that quietly tests nothing is the failure mode
// this suite keeps finding in itself.
$victim = $fallbackVictim = null;
foreach ($forms as $id => $title) {
    try { $candidate = $loader->load($id); } catch (DefinitionException) { continue; }
    if (!$candidate->isForm() || $candidate->template === null
        || count($candidate->template->elements) <= 5) continue;
    $fallbackVictim ??= $candidate;
    foreach ($candidate->template->elements as $element) {
        if ($element instanceof \FreeBooks\Forms\Document\Image) { $victim = $candidate; break 2; }
    }
}
$victim ??= $fallbackVictim;

if ($victim === null) {
    ok('a form is available to edit', false);
} else {
    $allowed = $allowedFor($victim);
    $edit = function (callable $mutate) use ($victim, $allowed): array {
        $request = ['title' => $victim->title,
                    'element' => TemplateForm::toRequest($victim->template)];
        $request = $mutate($request);
        $binding = new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names());
        return [$binding->apply($victim, $request, $allowed), $binding->warnings()];
    };

    // Moving is the ordinary case, and it has to actually take effect —
    // otherwise every refusal below would pass on an editor that ignores input.
    [$moved, $warnings] = $edit(function (array $r): array {
        $r['element'][0]['x'] = '123.5';
        $r['element'][0]['y'] = '45.25';
        return $r;
    });
    ok('a moved element moves', $moved->template->elements[0]->box->x === 123.5
       && $moved->template->elements[0]->box->y === 45.25 && $warnings === []);

    // Dragging the NORTH or WEST side moves the origin and changes the size in
    // the same gesture — the two arrive together or the element grows out of
    // the wrong corner.
    [$grown, $warnings] = $edit(function (array $r): array {
        $r['element'][0]['x'] = '30';
        $r['element'][0]['y'] = '40';
        $r['element'][0]['w'] = '55';
        $r['element'][0]['h'] = '12';
        return $r;
    });
    ok('a side drag moves the origin and the size at once',
       $grown->template->elements[0]->box->x === 30.0
       && $grown->template->elements[0]->box->y === 40.0
       && $grown->template->elements[0]->box->width  === 55.0
       && $grown->template->elements[0]->box->height === 12.0 && $warnings === []);

    // A Line's sides are a SIGNED offset to its end point (§13.11), so a line
    // drawn leftward or upward stores a negative one. The canvas keeps the sign
    // when it resizes; a save that clamped it at zero would flatten the line to
    // nothing, and the author would see it vanish rather than be refused.
    [$signed, $warnings] = $edit(function (array $r): array {
        $r['element'][0]['w'] = '-40';
        $r['element'][0]['h'] = '-15';
        return $r;
    });
    ok('a negative side survives a save, so a line keeps its direction',
       $signed->template->elements[0]->box->width  === -40.0
       && $signed->template->elements[0]->box->height === -15.0 && $warnings === []);

    // A group drag moves every member by ONE delta. What the save has to
    // guarantee is only that it takes them all in a single post — the canvas
    // computes the delta once precisely so the numbers arrive already agreeing.
    if (count($victim->template->elements) >= 3) {
        $before = [];
        foreach ($victim->template->elements as $index => $element) {
            $before[$index] = [$element->box->x, $element->box->y];
        }
        [$together, $warnings] = $edit(function (array $r) use ($before): array {
            foreach (array_slice(array_keys($before), 0, 3) as $index) {
                $r['element'][$index]['x'] = (string)($before[$index][0] + 7.5);
                $r['element'][$index]['y'] = (string)($before[$index][1] + 7.5);
            }
            return $r;
        });
        $rigid = true;
        foreach (array_slice(array_keys($before), 0, 3) as $index) {
            $box = $together->template->elements[$index]->box;
            if (abs($box->x - ($before[$index][0] + 7.5)) > 0.0005
                || abs($box->y - ($before[$index][1] + 7.5)) > 0.0005) $rigid = false;
        }
        ok('three elements moved by one delta keep their spacing exactly',
           $rigid && $warnings === []);
    }

    // A blank side is "let the renderer decide", not zero. Every logo stores a
    // null height, and reading blank as 0 would pin it to nothing on the first
    // save of a form nobody meant to change.
    $nullHeight = null;
    foreach ($victim->template->elements as $index => $element) {
        if ($element->box->height === null) { $nullHeight = $index; break; }
    }
    if ($nullHeight !== null) {
        [$kept, ] = $edit(fn(array $r) => $r);
        ok('a blank height stays blank rather than becoming zero',
           $kept->template->elements[$nullHeight]->box->height === null);
    }

    // §13.4 for a form: an author names a value the document can supply, or
    // nothing. The select is a convenience; the POST is whatever is sent.
    $bindable = null;
    foreach ($victim->template->elements as $index => $element) {
        if ($element instanceof \FreeBooks\Forms\Document\Field
            && $element->bind->scope !== \FreeBooks\Forms\Document\Scope::Record) continue;
        if ($element instanceof \FreeBooks\Forms\Document\Field) { $bindable = $index; break; }
    }
    if ($bindable !== null) {
        $was = $victim->template->elements[$bindable]->bind->name;
        [$attacked, $warnings] = $edit(function (array $r) use ($bindable): array {
            $r['element'][$bindable]['bind'] = 'contacts.tax_id';
            return $r;
        });
        ok('a binding this form cannot supply is refused',
           $attacked->template->elements[$bindable]->bind->name === $was && $warnings !== []);

        [$attacked, $warnings] = $edit(function (array $r) use ($bindable): array {
            $r['element'][$bindable]['bind'] = "id` from users -- ";
            return $r;
        });
        ok('SQL in a binding is refused', $warnings !== []
           && $attacked->template->elements[$bindable]->bind->name === $was);
    }

    // Deleting is a row not being submitted; adding is a row with no index.
    [$fewer, ] = $edit(function (array $r): array { array_shift($r['element']); return $r; });
    ok('an element left out of the post is deleted',
       count($fewer->template->elements) === count($victim->template->elements) - 1);

    [$more, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'rect', 'x' => '5', 'y' => '5',
                           'w' => '20', 'h' => '10'];
        return $r;
    });
    $added = $more->template->elements;   // end() takes a reference; the property is readonly
    ok('a new element with no index is added',
       count($added) === count($victim->template->elements) + 1
       && end($added) instanceof Rect && $warnings === []);

    // A name of the author's own. Without one an element is described by what
    // it is, which reads well for text and for a value and not at all for a
    // picture — two images on one form are both "image".
    [$named, $warnings] = $edit(function (array $r): array {
        $r['element'][0]['label'] = 'Ship to';
        return $r;
    });
    ok('an element can be named', $named->template->elements[0]->label === 'Ship to'
       && $warnings === [], var_export($named->template->elements[0]->label, true));

    $codec  = new DefinitionCodec();
    $reread = $codec->decode($codec->encode($named), $named->id);
    ok('and the name survives being saved', $reread->template->elements[0]->label === 'Ship to');

    // Blank is NO name, not an empty one: describe() falls back on null, and an
    // empty string would leave the row in the list with nothing in it at all.
    [$cleared, ] = $edit(function (array $r): array {
        $r['element'][0]['label'] = '   ';
        return $r;
    });
    ok('a blank name is no name rather than an empty one',
       $cleared->template->elements[0]->label === null,
       var_export($cleared->template->elements[0]->label, true));
    ok('and such an element is described by what it is',
       CanvasLayout::boxes(new Template([$cleared->template->elements[0]]), null)[0]['label'] !== '',
       CanvasLayout::boxes(new Template([$cleared->template->elements[0]]), null)[0]['label']);

    // A new element can be named as it is added — the same field, on the create
    // path rather than the rebuild one.
    [$freshName, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'text', 'x' => '5', 'y' => '5',
                           'w' => '20', 'h' => '10', 'text' => 'Paid', 'label' => 'Paid stamp'];
        return $r;
    });
    $drawn = $freshName->template->elements;
    ok('a new element can be named as it is added', end($drawn)->label === 'Paid stamp'
       && $warnings === [], var_export(end($drawn)->label, true));

    // An image IS addable, now that the canvas can put a file in the folder
    // (§13.13). Added with nothing chosen yet, because the file is picked or
    // uploaded a moment later — and the renderer skips an empty src rather than
    // failing, so the page it is being placed on keeps drawing meanwhile.
    [$withImage, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'image', 'x' => '12', 'y' => '9',
                           'w' => '40', 'h' => ''];
        return $r;
    });
    $drawn = $withImage->template->elements;
    $new   = end($drawn);
    ok('an image element can be added', $new instanceof \FreeBooks\Forms\Document\Image
       && $warnings === [], implode(' ', $warnings));
    ok('it is added with no file yet', $new instanceof \FreeBooks\Forms\Document\Image
       && $new->src === '', var_export($new->src ?? null, true));
    // The whole point of a null height: the shape comes from whatever picture
    // is chosen. A 5 mm default here would squash the first logo put in it.
    ok('and with no height, so the file decides its shape',
       $new->box->height === null && $new->box->width === 40.0,
       var_export($new->box->height, true));

    // It has to survive being written and read back, or it would be an element
    // that exists until the page is reopened.
    $codec    = new DefinitionCodec();
    $reloaded = $codec->decode($codec->encode($withImage), $withImage->id);
    $back     = $reloaded->template->elements[count($drawn) - 1];
    ok('an added image survives the file it is saved in',
       $back instanceof \FreeBooks\Forms\Document\Image && $back->src === ''
       && $back->box->height === null && $back->box->x === 12.0);

    // Same gate as an existing image: a filename is a path, and one that names
    // nothing prints a form with a hole in it and no error.
    [$invented, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'image', 'x' => '12', 'y' => '9',
                           'w' => '40', 'h' => '', 'src' => '../../../etc/passwd'];
        return $r;
    });
    $drawn = $invented->template->elements;
    $new   = end($drawn);
    ok('a new image naming a file that is not in the images folder keeps no name',
       $new instanceof \FreeBooks\Forms\Document\Image && $new->src === '' && $warnings !== [],
       var_export($new->src ?? null, true));

    // And the other half: a name that IS there is kept, or the check above
    // would pass on an editor that refused every filename.
    if ($file !== null) {
        [$named, $warnings] = $edit(function (array $r) use ($file): array {
            $r['element'][] = ['index' => '', 'kind' => 'image', 'x' => '12', 'y' => '9',
                               'w' => '40', 'h' => '', 'src' => basename($file)];
            return $r;
        });
        $drawn = $named->template->elements;
        $new   = end($drawn);
        ok('a new image naming a file that is there keeps it',
           $new instanceof \FreeBooks\Forms\Document\Image && $new->src === basename($file)
           && $warnings === [], var_export($new->src ?? null, true));
    }

    // A kind with sub-structure cannot be conjured: a Table with no columns
    // draws nothing and cannot be repaired on this page.
    [$refused, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'table', 'x' => '5', 'y' => '5'];
        return $r;
    });
    ok('a new element of a kind that needs sub-structure is refused',
       count($refused->template->elements) === count($victim->template->elements)
       && $warnings !== []);

    // An index naming nothing is a row that would otherwise become a NEW
    // element of the wrong kind.
    [$stale, $warnings] = $edit(function (array $r): array {
        $r['element'][0]['index'] = '9999';
        return $r;
    });
    ok('a row naming an element that no longer exists is dropped, not guessed at',
       count($stale->template->elements) === count($victim->template->elements) - 1
       && $warnings !== []);

    // Row order IS drawing order, which is how z-order is expressed.
    [$reordered, ] = $edit(function (array $r): array {
        $first = array_shift($r['element']);
        $r['element'][] = $first;
        return $r;
    });
    $shuffled = $reordered->template->elements;
    ok('reordering the rows reorders the drawing',
       $shuffled[0]::class === $victim->template->elements[1]::class
       && end($shuffled)::class === $victim->template->elements[0]::class);

    // A barcode's ink must land where its box says. TCPDF reads a 'position'
    // of 'L' as "align to the left MARGIN" and discards the x it was given, so
    // the only barcode in the shipped set printed at 8 mm while its box said
    // 118. Measured from the content stream, because a barcode's DATA is right
    // wherever the ink lands and nothing else would notice.
    $barcode = null;
    foreach ($forms as $id => $title) {
        try { $candidate = $loader->load($id); } catch (DefinitionException) { continue; }
        if (!$candidate->isForm() || $candidate->template === null) continue;
        foreach ($candidate->template->elements as $element) {
            if ($element instanceof \FreeBooks\Forms\Document\Barcode) {
                $barcode = [$candidate, $element];
                break 2;
            }
        }
    }
    if ($barcode === null) {
        echo "  [--] no barcode in the shipped set on this company\n";
    } else {
        [$definition, $element] = $barcode;
        $document = new LayoutDocument(
            title: $definition->title, template: $definition->template,
            records: [SampleData::record($definition->template, null)], page: $definition->page);
        $bytes = $renderer->render($document)->bytes;

        // Bars are many narrow filled rectangles; the leftmost is where the
        // barcode starts.
        // Bars are many NARROW rectangles; the leftmost is where the barcode
        // starts. The width filter is what tells a bar from the box around it.
        $left = null;
        foreach (rectangles(operators($bytes)) as $rectangle) {
            if ($rectangle[2] > 0 && $rectangle[2] < 3) {
                $left = $left === null ? $rectangle[0] : min($left, $rectangle[0]);
            }
        }
        if ($left === null) {
            ok('the barcode is drawn', false, 'no bars found in the content stream');
        } else {
            ok('the barcode is drawn at the x its box declares',
               near($left, pt($element->box->x)),
               sprintf('%.1f mm, wanted %.1f', $left * 25.4 / 72, $element->box->x));
        }
    }

    // A BOUND image resolves against the company's files plus the folder the
    // element carries, not against the report images. The Work Order shows the
    // product photo of the SKU being built: "inventory/images/" on the element,
    // the filename in the record. The port dropped that folder, so the renderer
    // looked for a product photo among the logos, found nothing, and drew
    // nothing — an element present, a binding resolving, and no picture.
    $linked = null;
    foreach ($forms as $id => $title) {
        try { $candidate = $loader->load($id); } catch (DefinitionException) { continue; }
        if (!$candidate->isForm() || $candidate->template === null) continue;
        foreach ($candidate->template->elements as $element) {
            if ($element instanceof \FreeBooks\Forms\Document\Image
                && $element->src instanceof \FreeBooks\Forms\Document\Binding) {
                $linked = [$candidate, $element];
                break 2;
            }
        }
    }
    if ($linked === null) {
        echo "  [--] no linked image on any form here\n";
    } else {
        [$definition, $element] = $linked;
        ok('a linked image keeps the folder it is linked from',
           $element->pathPrefix !== '',
           'the folder is stored on the element and the filename comes from the record');

        // And it must actually draw. Counted from the inflated content stream:
        // the raw bytes are compressed, so counting there reports zero for a
        // page full of pictures.
        $runner   = new FormRunner($db, $catalog);
        $plan     = $runner->planDefinition($definition);
        $document = null;
        if ($plan->runnable() && $plan->recordTable !== ''
            && preg_match('/^[a-z_][a-z0-9_]*$/i', $plan->recordKey)) {
            $rows = $db->Execute("select distinct `" . $plan->recordKey . "` from `"
                . (defined('DB_PREFIX') ? DB_PREFIX : '') . $plan->recordTable . "`
                order by `" . $plan->recordKey . "` desc limit 8");
            while ($rows && !$rows->EOF) {
                $key = (string)$rows->fields[$plan->recordKey];
                $rows->MoveNext();
                try {
                    $built = $runner->runDefinition($definition, [$key],
                        ['dates' => $definition->dateDefault]);
                } catch (\Throwable) { continue; }
                if ($built instanceof LayoutDocument && count($built->records) > 0) {
                    $path = (string)($built->records[0]->value($element->src->name)?->raw ?? '');
                    if ($path !== '' && is_file(DIR_FS_MY_FILES . $_SESSION['company'] . '/'
                        . trim($element->pathPrefix, '/') . '/' . basename($path))) {
                        $document = $built;
                        break;
                    }
                }
            }
        }
        if ($document === null) {
            echo "  [--] no record on this company has a linked image file present\n";
        } else {
            $drawn = 0;
            foreach (operators($renderer->render($document)->bytes) as $line) {
                $drawn += preg_match_all('/\/I\d+ Do/', $line);
            }
            ok('a linked image is actually drawn on the page', $drawn > 0,
               'the element resolves and the file exists, but nothing was placed');
        }
    }

    // An image's source is editable, and gated. A fixed image names a FILE and
    // a bound one names a VALUE; a path submitted by a browser is whatever the
    // browser chose to send.
    $images = PF_DIR_MY_REPORTS . 'images';
    $fixed = $boundImage = null;
    foreach ($victim->template->elements as $index => $element) {
        if (!($element instanceof \FreeBooks\Forms\Document\Image)) continue;
        if (is_string($element->src)) $fixed = $index; else $boundImage = $index;
    }
    if ($fixed !== null) {
        [$attacked, $warnings] = $edit(function (array $r) use ($fixed): array {
            $r['element'][$fixed]['src'] = '../../../../etc/passwd';
            return $r;
        });
        ok('an image file outside the images folder is refused',
           $attacked->template->elements[$fixed]->src
             === $victim->template->elements[$fixed]->src && $warnings !== []);

        $present = glob($images . '/*.{png,jpg,jpeg,gif}', GLOB_BRACE) ?: [];
        if ($present !== []) {
            $wanted = basename($present[0]);
            [$changed, $warnings] = $edit(function (array $r) use ($fixed, $wanted): array {
                $r['element'][$fixed]['src'] = $wanted;
                return $r;
            });
            ok('an image that IS in the folder is accepted',
               $changed->template->elements[$fixed]->src === $wanted && $warnings === []);
        }
    }
    ok('a form carrying an image was available to test the source gate',
       $fixed !== null || $boundImage !== null,
       'no image element on any form here; the source checks did not run');
    if ($boundImage !== null) {
        $was = $victim->template->elements[$boundImage]->src->name;
        [$attacked, $warnings] = $edit(function (array $r) use ($boundImage): array {
            $r['element'][$boundImage]['bind'] = 'users.admin_pass';
            return $r;
        });
        ok('a bound image cannot be pointed at a value the form has no business supplying',
           $attacked->template->elements[$boundImage]->src->name === $was && $warnings !== []);
    }

    // A font is a stored TOKEN, not a name anyone would type — the default face
    // is spelled "freeserif" — so the canvas offers a list. TCPDF does not fall
    // back on an unknown family: it tries to include a font definition file and
    // errors out with the document half drawn, so an unknown name has to be
    // refused where it can be reported.
    $styled = null;
    foreach ($victim->template->elements as $index => $element) {
        if ($element->style?->font !== null && $element->style->font !== '') { $styled = $index; break; }
    }
    if ($styled === null) {
        echo "  [--] no element on this form names a font\n";
    } else {
        $was = $victim->template->elements[$styled]->style->font;

        [$attacked, $warnings] = $edit(function (array $r) use ($styled): array {
            $r['element'][$styled]['font'] = 'no-such-face';
            return $r;
        });
        ok('a font the document cannot be drawn in is refused',
           $attacked->template->elements[$styled]->style?->font === $was && $warnings !== []);

        $offered = array_keys(forms_font_names());
        ok('the font list is not empty', $offered !== [], implode(', ', $offered));
        if ($offered !== []) {
            $wanted = $offered[0];
            [$changed, $warnings] = $edit(function (array $r) use ($styled, $wanted): array {
                $r['element'][$styled]['font'] = $wanted;
                return $r;
            });
            ok('a font that IS offered is accepted',
               $changed->template->elements[$styled]->style?->font === $wanted && $warnings === []);
        }

        // The one that matters for an install nobody here has seen: a face
        // already in the definition survives, listed or not. Otherwise a select
        // with no option for it restyles the element on the first save.
        $survivors = 0;
        $checked   = 0;
        foreach ($forms as $id => $title) {
            try { $each = $loader->load($id); } catch (DefinitionException) { continue; }
            if (!$each->isForm() || $each->template === null) continue;
            $request = ['title' => $each->title, 'element' => TemplateForm::toRequest($each->template)];
            $saved = (new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names()))
                ->apply($each, $request, $allowedFor($each));
            foreach ($each->template->elements as $index => $element) {
                if ($element->style?->font === null || $element->style->font === '') continue;
                $checked++;
                if (($saved->template->elements[$index]->style?->font ?? null) === $element->style->font) {
                    $survivors++;
                }
            }
        }
        ok("every font already in a definition survives a save ($checked checked)",
           $checked > 0 && $survivors === $checked, "$survivors of $checked");
    }

    // The border's colour is its OWN, and the data uses that: 16 shipped
    // elements draw black text inside a silver rule. The renderer reads
    // `borderColour ?? colour`, so a rule with no colour of its own follows the
    // text — a default, not a conflation, and an editor that offered one
    // control would quietly flatten the sixteen.
    $twoColours = null;
    foreach ($forms as $id => $title) {
        try { $each = $loader->load($id); } catch (DefinitionException) { continue; }
        if (!$each->isForm() || $each->template === null) continue;
        foreach ($each->template->elements as $index => $element) {
            $style = $element->style;
            if ($style?->colour === null || $style->borderColour === null) continue;
            if ($style->colour == $style->borderColour) continue;
            $twoColours = [$each, $index];
            break 2;
        }
    }
    if ($twoColours === null) {
        echo "  [--] no element here draws a border in a colour of its own\n";
    } else {
        [$owner, $index] = $twoColours;
        $request = ['title' => $owner->title, 'element' => TemplateForm::toRequest($owner->template)];
        $saved   = (new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names()))
            ->apply($owner, $request, $allowedFor($owner));

        ok('an element keeps a border colour distinct from its text colour',
           differences($owner->template->elements[$index]->style,
                       $saved->template->elements[$index]->style) === []);

        // And the two move independently.
        $request['element'][$index]['colour']       = '#112233';
        $request['element'][$index]['borderColour'] = '#445566';
        $changed = (new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names()))
            ->apply($owner, $request, $allowedFor($owner));
        $style = $changed->template->elements[$index]->style;
        ok('the two colours can be set to different values',
           $style?->colour?->r === 0x11 && $style->colour->b === 0x33
           && $style->borderColour?->r === 0x44 && $style->borderColour->b === 0x66);
    }

    // A border width of 0 is a HAIRLINE, not "no border": the PDF spec defines
    // a zero line width as the thinnest the device can render. Nothing in the
    // renderer treats 0 as off, and this pins that — if it ever started
    // skipping the stroke, every rule drawn at 0 across the shipped set would
    // vanish, and 64 of those elements are lines.
    $hairline = new LayoutDocument(
        title: 'hairline', records: [new Record()],
        page: new PageSetup(size: 'A4', orientation: 'P'),
        template: new Template([new Rect(new Box(20, 30, 50, 10),
            new \FreeBooks\Forms\Document\Style(borderSize: 0.0))]));
    ok('a border width of 0 still draws a rectangle',
       rectangles(operators($renderer->render($hairline)->bytes)) !== [],
       'zero width is the thinnest line, not the absence of one');

    // And the width that reaches the page is the size in POINTS, through
    // PhreeForm's 0.35 mm-per-point conversion.
    $thick = new LayoutDocument(
        title: 'thick', records: [new Record()],
        page: new PageSetup(size: 'A4', orientation: 'P'),
        template: new Template([new Rect(new Box(20, 30, 50, 10),
            new \FreeBooks\Forms\Document\Style(borderSize: 2.0))]));
    $widths = [];
    foreach (operators($renderer->render($thick)->bytes) as $line) {
        if (preg_match_all('/(?:^|\s)([\d.]+) w(?=\s|$)/', $line, $all)) {
            foreach ($all[1] as $width) $widths[] = (float)$width;
        }
    }
    $wanted = pt(2.0 * 0.35);
    $seen   = false;
    foreach ($widths as $width) if (near($width, $wanted)) { $seen = true; break; }
    ok('a border of 2 reaches the page twice as wide as the default', $seen,
       sprintf('wanted %.3f pt; saw %s', $wanted,
           implode(', ', array_unique(array_map(fn($w) => sprintf('%.3f', $w), $widths)))));

    // Drawing order is the ARRAY order — for everything except the line-item
    // table, which is drawn after the rest whatever its position: the other
    // elements repeat per page through the header, and the table is the body
    // that decides where pages break. Pinned because the canvas tells an author
    // exactly that, and a change to the passes would make the page lie.
    ok('the header pass draws everything except the table',
       str_contains((string)file_get_contents(
           DIR_FS_MODULES . 'forms/classes/render/layout_pdf_renderer.php'),
           'if ($element instanceof Table) continue;'),
       'the note on the canvas depends on this');
    ok('and the canvas says so when a table is selected',
       str_contains((string)file_get_contents(
           DIR_FS_MODULES . 'forms/pages/canvas/template_main.php'), 'ins_table_note'));

    // Bold and italic are NEW: PhreeForm passed '' for the font style on every
    // SetFont it ever made, and no shipped definition carries a tag for either.
    // So this is the first thing the native format exists to allow (§13.3), and
    // the check is that it survives storage and reaches the page.
    if ($styled !== null) {
        [$weighted, $warnings] = $edit(function (array $r) use ($styled): array {
            $r['element'][$styled]['bold']   = '1';
            $r['element'][$styled]['italic'] = '1';
            return $r;
        });
        $style = $weighted->template->elements[$styled]->style;
        ok('an element can be made bold and italic',
           $style?->bold === true && $style->italic === true && $warnings === []);
        ok('and that reaches TCPDF as its font style', $style?->fontStyle() === 'BI');

        // Through the file and back, or it is a setting that lasts until the
        // next save.
        $codec  = new DefinitionCodec();
        $reread = $codec->decode($codec->encode($weighted), $weighted->id);
        ok('it round-trips through the stored format',
           $reread->template->elements[$styled]->style?->bold === true
           && $reread->template->elements[$styled]->style->italic === true);

        // And it changes the ink. A weight that stored but did not print would
        // be the most convincing kind of nothing.
        $plain = new LayoutDocument(title: 'plain', template: $victim->template,
            records: [SampleData::record($victim->template, null)], page: $victim->page);
        $heavy = new LayoutDocument(title: 'heavy', template: $weighted->template,
            records: [SampleData::record($weighted->template, null)], page: $weighted->page);
        ok('and the page is drawn differently because of it',
           normalisePdf($renderer->render($plain)->bytes)
             !== normalisePdf($renderer->render($heavy)->bytes));
    }

    // Structures with no control on the canvas are carried, not rebuilt.
    $block = null;
    foreach ($victim->template->elements as $index => $element) {
        if ($element instanceof \FreeBooks\Forms\Document\FieldBlock) { $block = $index; break; }
    }
    if ($block !== null) {
        [$kept, ] = $edit(function (array $r) use ($block): array {
            $r['element'][$block]['x'] = '77';
            return $r;
        });
        ok("a moved block keeps its parts",
           count($kept->template->elements[$block]->parts)
             === count($victim->template->elements[$block]->parts)
           && $kept->template->elements[$block]->box->x === 77.0);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a redraw shows the edit, not the stored form ===\n";

// The endpoint applies the posted canvas through TemplateForm — the same class
// the save uses — and renders THAT. If it rendered the stored definition
// instead, the button would appear to work and change nothing, which is worse
// than not having it.
if ($victim === null) {
    ok('a form is available to redraw', false);
} else {
    $movable = null;
    foreach ($victim->template->elements as $index => $element) {
        if ($element instanceof \FreeBooks\Forms\Document\Rect) { $movable = $index; break; }
    }
    if ($movable === null) {
        echo "  [--] no rectangle on this form to move measurably\n";
    } else {
        $request = ['title' => $victim->title,
                    'element' => TemplateForm::toRequest($victim->template)];
        $request['element'][$movable]['x'] = '150';
        $request['element'][$movable]['y'] = '95';

        $binding = new TemplateForm(PF_DIR_MY_REPORTS . 'images', forms_font_names());
        $edited  = $binding->apply($victim, $request, $allowedFor($victim));

        $before = new LayoutDocument(title: $victim->title, template: $victim->template,
            records: [SampleData::record($victim->template, null)], page: $victim->page);
        $after  = new LayoutDocument(title: $edited->title, template: $edited->template,
            records: [SampleData::record($edited->template, null)], page: $edited->page);

        $moved   = rectangles(operators($renderer->render($after)->bytes));
        $wantedX = pt(150.0);
        $landed  = false;
        foreach ($moved as $rectangle) if (near($rectangle[0], $wantedX)) { $landed = true; break; }

        ok('a moved element is drawn at its new position', $landed,
           'the edit reached the renderer');
        ok('and the picture is not simply the stored one',
           normalisePdf($renderer->render($before)->bytes)
             !== normalisePdf($renderer->render($after)->bytes));
    }
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
