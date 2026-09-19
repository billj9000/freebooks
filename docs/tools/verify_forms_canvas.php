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
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{CanvasLayout, FormRunner, SampleData};
use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Definition\{Definition, DefinitionCodec, DefinitionException, DefinitionLoader, TemplateForm};
use FreeBooks\Forms\Document\{
    Box, Colour, LayoutDocument, Line, PageSetup, Record, Rect, RenderOptions, StaticText, Style,
    Template
};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}


// ─────────────────────────────────────────────────────────────────────────────
// Before anything is drawn, because a bad face is not a bad result but the end
// of the run: TCPDF's Error() prints and die()s — and die()s with status ZERO,
// so a tool that dies inside it looks to any runner like a tool that finished.
// Checked here, these three fail as checks instead of as silence.
echo "=== the faces a document may be drawn in ===\n";
$offered = array_keys(forms_font_names());
ok('the font list is not empty', $offered !== [], implode(', ', $offered));

// Every offered face must be one TCPDF actually has.
//
// In a CHILD process, and that is the finding rather than a precaution:
// TCPDF::Error() prints "Could not include font definition file" and
// die()s. It does not throw, so there is nothing to catch and no way to
// ask the question in-process without taking the rest of this tool down
// with it. Which is also the answer to why the list exists at all — an
// unknown face is not a fallback, it is a dead page found by whoever
// prints the invoice.
//
// Re-implementing TCPDF's font lookup here would be quicker and would
// prove nothing: a re-implementation can agree with the code while both
// are wrong. This runs the real SetFont().
$vendor = dirname(rtrim(DIR_FS_MODULES, '/\\')) . '/vendor/autoload.php';
foreach ($offered as $face) {
    $probe = 'require ' . var_export($vendor, true) . ';'
           . '$p = new TCPDF(); $p->AddPage(); $p->SetFont('
           . var_export($face, true) . ', "", 10); echo $p->getFontFamily();';
    $lines = []; $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probe) . ' 2>&1', $lines, $status);
    $said = trim(implode('', $lines));
    ok("the face '$face' is one TCPDF can be told to use",
       $status === 0 && $said === $face, $said);
}

ok('the face a document with none of its own gets is on the list',
   in_array(FORMS_DEFAULT_FONT, $offered, true),
   FORMS_DEFAULT_FONT . ' is not offered');

// A face with no label would render as an empty row in the select: not
// a face nobody picks, a face nobody can see is there.
$unlabelled = [];
foreach (forms_font_names() as $face => $label) {
    if ($label === '' || $label === $face) $unlabelled[] = $face;
}
ok('every face reads as a name rather than as its stored spelling',
   $unlabelled === [], implode(', ', $unlabelled));

echo "\n";

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

$renderer = new LayoutPdfRenderer(
    formatter:      new Formatter(
                      currencies:      $currencies,
                      defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                      lookups:         forms_formatter_lookups()),
    defaultFont:    defined('FORMS_DEFAULT_FONT') ? FORMS_DEFAULT_FONT : 'freeserif',
    rowSpacing:     2.0,
    continuedLabel: 'Continued',
    pageOfLabel:    'of',
    imageDirectory: FORMS_DIR_IMAGES,
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
 * Every line width SET in these operators, in points, in the order they are set.
 *
 * TCPDF emits `3.000000 w` on a line of its own ahead of the shape it applies
 * to, so this can be anchored where rectangles() cannot be. A width is what a
 * rule's thickness comes out as, and reading it back out of the file is the
 * only way to tell a control that reaches the paper from one that merely
 * stores a number (§13.49).
 *
 * @param list<string> $lines
 * @return list<float>
 */
function lineWidths(array $lines): array
{
    $found = [];
    foreach ($lines as $line) {
        if (preg_match('/^(-?[\d.]+) w$/', trim($line), $one)) $found[] = (float)$one[1];
    }
    return $found;
}

/**
 * Every DRAW colour set in these operators, as [r, g, b] on 0..1.
 *
 * `RG` is the stroking colour — the one a rule and a frame are drawn in — and
 * is not `rg`, which fills. Matching the wrong case would report the page
 * background as the colour of every line on it.
 *
 * @param list<string> $lines
 * @return list<array{0: float, 1: float, 2: float}>
 */
function drawColours(array $lines): array
{
    $found = [];
    foreach ($lines as $line) {
        if (preg_match_all('/(?:^|\s)([\d.]+) ([\d.]+) ([\d.]+) RG(?=\s|$)/', $line, $all, PREG_SET_ORDER)) {
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

// The two grids reach their controls by ATTRIBUTE, not by id: rows are cloned
// from a <template>, so every row would carry the same id. The names come from
// a `fields:` list in the script and the attributes are written in the markup,
// which is two places to spell the same word — and a mismatch is a control that
// silently never saves rather than one that errors.
//
// verify_forms_pages.php catches the LITERAL selectors. These are built as
// '[data-col=' + field + ']', so the name never appears as a selector at all
// and only the list it is drawn from can be checked.
$markup = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/canvas/template_main.php');
preg_match_all('/\bdata-col\s*=\s*[\'"]([A-Za-z0-9_\-]+)[\'"]/', $markup, $carried);
$carriedNames = array_flip($carried[1]);

preg_match_all("/fields:\s*\[([^\]]*)\]/", $js, $lists);
$declared = [];
foreach ($lists[1] as $list) {
    preg_match_all("/'([A-Za-z0-9_\-]+)'/", $list, $names);
    foreach ($names[1] as $name) $declared[] = $name;
}
$declared = array_values(array_unique($declared));

ok('the grids declare at least one field list to check', $declared !== []);
$unwired = [];
foreach ($declared as $name) if (!isset($carriedNames[$name])) $unwired[] = $name;
ok('every grid field the script names is one the row markup carries',
   $unwired === [], implode(', ', $unwired)
   . ' — the script would find nothing, and that column would never save');
echo '  [--] ' . count($declared) . " grid field(s) declared, all carried\n";

// ── the Delete key ──────────────────────────────────────────────────────────
//
// A keyboard shortcut that removes things is worth pinning, because everything
// about it that can go wrong is invisible: it fails by deleting something the
// author was not looking at, not by throwing.
//
// The dangerous case is not the canvas at all. Both grids are modals full of
// text boxes and per-row buttons, and a focused <button> passes the usual
// INPUT/TEXTAREA/SELECT test — so without the extra guards, pressing Delete
// after clicking a column's own × would offer to remove the whole table.
$deleteKey = (string)(preg_match('/keydown[^}]*?Delete(.|\n)*?formsCanvasDelete\(\);/', $js, $found)
                        ? $found[0] : '');

ok('the canvas binds a key to deleting the selection', $deleteKey !== '',
   'no keydown handler reaches formsCanvasDelete()');

if ($deleteKey !== '') {
    // Bound on a MacBook too, where the key marked "delete" IS Backspace and
    // forward delete needs Fn — bind only 'Delete' there and the feature does
    // not exist.
    ok('and to Backspace as well, which is the only such key a MacBook has',
       str_contains($deleteKey, "'Backspace'"));

    ok('it declines inside a text box, a control or a link',
       (bool)preg_match('/INPUT\|TEXTAREA\|SELECT\|BUTTON\|A/', $deleteKey),
       'a focused row button would otherwise delete the element that owns the grid');

    ok('it declines while a dialog is open',
       str_contains($deleteKey, "classList.contains('modal-open')"),
       'the column and line grids are modals, and their subject is not the element');

    ok('it declines when a modifier is held',
       (bool)preg_match('/ctrlKey \|\| event\.metaKey \|\| event\.altKey/', $deleteKey));

    // Only after every guard has passed. Swallowing the key and THEN declining
    // would take Backspace away from the browser in the very cases the guards
    // exist to protect.
    $bail = strpos($deleteKey, 'preventDefault');
    ok('and it swallows the key only once it has decided to act',
       $bail !== false && $bail > strrpos($deleteKey, 'modal-open'));
}

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
echo "\n=== a rule and a box are drawn at the thickness they carry ===\n";

// Issue #65: "there appears to be no way to specify a thickness for rectangles
// or lines". The renderer had always read one — SetLineWidth(borderSize), for
// both kinds — and the canvas had always posted it. What was missing was the
// NAME: the panel called it *Border width*, which on the two elements that ARE
// an outline reads as a property of something else, and the control an author
// reached for instead was Size, which neither kind has any use for.
//
// So the fault was in a label, and a label is the one thing a suite like this
// cannot see. These pin the two halves it can: that a thickness posted through
// the canvas reaches the paper, and that a rule left without one is drawn at
// the same width as a box left without one — which is what the manual has
// always said and what drawLine's `?? 0` quietly did not do (§13.49).

/** The widths a one-element document sets, read back out of the file. */
$widthsOf = function ($element) use ($renderer): array {
    $document = new LayoutDocument(
        title:    'thickness',
        template: new Template([$element]),
        records:  [new Record()],
        page:     new PageSetup(size: 'A4', orientation: 'P'),
    );
    return lineWidths(operators($renderer->render($document)->bytes));
};

foreach ([['a rule', 'line'], ['a box', 'rect']] as [$what, $kind]) {
    foreach ([0.5, 3.0, 8.0] as $points) {
        $style   = new Style(borderSize: $points);
        $element = $kind === 'line'
            ? new Line(new Box(20.0, 30.0, 60.0, 0.0), 'H', $style)
            : new Rect(new Box(20.0, 30.0, 60.0, 20.0), $style);
        $widths  = $widthsOf($element);
        // NOT $points: the renderer converts to millimetres through PhreeForm's
        // pervasive 0.35, which is 1pt to within 0.8%, and TCPDF converts back
        // with the exact 72/25.4. So a 1pt rule is 0.9921pt in the file. Kept,
        // not corrected — the whole shipped set was measured against it — and
        // written out here so the check pins the constant instead of hiding it
        // behind a tolerance wide enough to swallow a real error.
        $wanted = $points * 0.35 * 72.0 / 25.4;
        ok(sprintf('%s asked for %spt is drawn at %.4fpt', $what, $points, $wanted),
           $widths !== [] && abs(end($widths) - $wanted) < 0.005,
           $widths === [] ? 'no width was set at all' : sprintf('%.4f pt', end($widths)));
    }
}

// The same again, but through the canvas rather than by building the element
// here — the path an author's typing actually takes.
$thicknessForm = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
$posted = $thicknessForm->apply(
    new Definition(type: Definition::FORM, title: 'thickness', template: new Template([])),
    ['title' => 'thickness', 'element' => [
        ['kind' => 'line', 'x' => '20', 'y' => '30', 'w' => '60', 'h' => '0',  'borderSize' => '4'],
        ['kind' => 'rect', 'x' => '20', 'y' => '60', 'w' => '60', 'h' => '20', 'borderSize' => '6'],
    ]],
    []);
$made = $posted->template?->elements ?? [];
ok('a thickness typed into the panel survives the save',
   count($made) === 2
   && ($made[0]->style?->borderSize ?? null) === 4.0
   && ($made[1]->style?->borderSize ?? null) === 6.0,
   sprintf('%s / %s',
       var_export(($made[0] ?? null)?->style?->borderSize, true),
       var_export(($made[1] ?? null)?->style?->borderSize, true)));
ok('and nothing was warned about on the way through',
   $thicknessForm->warnings() === [], implode(' ', $thicknessForm->warnings()));


// ── a text element of more than one line (§13.52) ────────────────────────────
//
// The browser's half of this is not reachable from here — a text input strips
// line feeds on assignment, which is why the control is a textarea and why
// verify_forms_pages checks the tag. What IS reachable is everything after it,
// and every step of that has to leave the breaks alone: the panel's own
// round trip, the binder, and the renderer that draws the result.
//
// The spelling matters and is not ours to choose. A form POST normalises line
// breaks to CRLF, measured in a real browser, which is the spelling the
// migrated definitions already carry — so both are fed through here rather than
// only the tidy one.
$multiForm = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
foreach (["\r\n" => 'as a browser posts it', "\n" => 'as a file may hold it'] as $eol => $how) {
    $body  = 'MARKERONE' . $eol . 'MARKERTWO';
    $built = $multiForm->apply(
        new Definition(type: Definition::FORM, title: 'lines', template: new Template([])),
        ['title' => 'lines', 'element' => [
            ['kind' => 'text', 'x' => '20', 'y' => '30', 'w' => '80', 'h' => '5', 'text' => $body],
        ]],
        []);
    $stored = ($built->template?->elements[0] ?? null)?->text;
    ok("a text element keeps its line break, $how", $stored === $body,
       var_export($stored, true));
}

// And a round trip through the panel, which is the case an author reaches by
// opening a migrated invoice and nudging anything on one of its bank blocks.
$block = new Template([new StaticText("MARKERONE\r\nMARKERTWO", new Box(20, 30, 80, 5))]);
$again = (new TemplateForm(FORMS_DIR_IMAGES, forms_font_names()))->apply(
    new Definition(type: Definition::FORM, title: 'lines', template: $block),
    ['title' => 'lines', 'element' => TemplateForm::toRequest($block)], []);
ok('and keeps it through the panel and back',
   ($again->template?->elements[0] ?? null)?->text === "MARKERONE\r\nMARKERTWO",
   var_export(($again->template?->elements[0] ?? null)?->text, true));

// Drawn as two lines, not one. MultiCell breaks on a line feed, so this is the
// check that the stored break reaches the PAGE rather than merely the file.
//
// Counted as text-positioning operations rather than looked for as text, and
// that is not fastidiousness: this renderer draws in freeserif, a TrueType face
// that TCPDF writes as subset GLYPH INDICES, so searching the content stream
// for a marker finds nothing and every assertion built on it passes vacuously.
// Measured while building verify_forms_conditions — the same string came back
// once in helvetica and not at all in freeserif. How many times the text cursor
// is placed is the same question asked in a way no font can hide.
// The baselines the text was actually written on, which is the same measure —
// and the same operator pattern — the block-separator check further down uses.
// TCPDF writes text as the ARRAY form `[(...)] TJ` preceded by a `Td`, so the
// distinct y coordinates ARE the lines.
$drawnLines = function (string $body) use ($renderer): int {
    $bytes = $renderer->render(new LayoutDocument(
        title: 'lines',
        template: new Template([new StaticText($body, new Box(20, 30, 80, 5))]),
        records: [new Record()], page: new PageSetup('A4', 'P')),
        new RenderOptions(filename: 'lines.pdf'))->bytes;
    $rows = [];
    foreach (operators($bytes) as $line) {
        if (preg_match('/([0-9.]+) ([0-9.]+) Td \[\(/', $line, $at)) $rows[(string)$at[2]] = true;
    }
    return count($rows);
};
$asOne = $drawnLines('MARKERONE MARKERTWO');
$asTwo = $drawnLines("MARKERONE\r\nMARKERTWO");
ok('a two-line text element is drawn on two lines', $asTwo === $asOne + 1,
   "$asOne placement(s) on one line, $asTwo on two");

if (count($made) === 2) {
    $drawn = lineWidths(operators($renderer->render(new LayoutDocument(
        title: 'thickness', template: $posted->template, records: [new Record()],
        page: new PageSetup()))->bytes));
    $scale = 0.35 * 72.0 / 25.4;
    ok('and both widths reach the paper, 4pt and 6pt apart',
       count($drawn) >= 2 && abs($drawn[0] - 4.0 * $scale) < 0.005
                          && abs($drawn[1] - 6.0 * $scale) < 0.005,
       implode(', ', array_map(fn($w) => sprintf('%.3f', $w), $drawn)));
}

// An element with no style of its own. Every converted form carries an explicit
// width — all of PhreeForm's lines store one, which is why this default was
// never seen — so this is about what a NEW element does, and about the manual
// being true where it says 1 is what an unset width draws.
foreach ([
    'a rule with no width set' => new Line(new Box(20.0, 30.0, 60.0, 0.0)),
    'a box with no width set'  => new Rect(new Box(20.0, 60.0, 60.0, 20.0)),
] as $what => $element) {
    $widths = $widthsOf($element);
    ok("$what is drawn at 1pt, as the manual says",
       $widths !== [] && abs(end($widths) - 0.35 * 72.0 / 25.4) < 0.005,
       $widths === [] ? 'no width was set at all' : sprintf('%.4f pt', end($widths)));
}

// ─────────────────────────────────────────────────────────────────────────────
// And the colour half of the same panel, which had the same shape of fault.
//
// The renderer reads `borderColour ?? colour` for both kinds, so the inspector's
// two swatches — *Colour* and *Border colour* — were two ways to say one thing
// with an unstated precedence. On the shipped set that is not a nicety: every
// rectangle in it has `borderColour` set and `colour` unset, so the swatch
// labelled Colour could not change what was drawn on ANY of them. The panel now
// shows one swatch on a line and a box, seeded with whichever value is in force
// and writing to the one that wins.
//
// The precedence itself is what the panel is now promising, so it is pinned
// here rather than assumed from a reading of the renderer.
$colourOf = function ($element) use ($renderer): ?array {
    $drawn = drawColours(operators($renderer->render(new LayoutDocument(
        title: 'colour', template: new Template([$element]), records: [new Record()],
        page: new PageSetup()))->bytes));
    return $drawn === [] ? null : end($drawn);
};
/** PDF stroking colours are 0..1; a Colour is 0..255. */
$isColour = fn(?array $drawn, int $r, int $g, int $b): bool =>
    $drawn !== null && abs($drawn[0] - $r / 255) < 0.004
                    && abs($drawn[1] - $g / 255) < 0.004
                    && abs($drawn[2] - $b / 255) < 0.004;

$silver = new Colour(192, 192, 192);
$red    = new Colour(255, 0, 0);

foreach ([
    'a rule' => fn(?Style $style) => new Line(new Box(20.0, 30.0, 60.0, 0.0), 'H', $style),
    'a box'  => fn(?Style $style) => new Rect(new Box(20.0, 60.0, 60.0, 20.0), $style),
] as $what => $make) {
    // Only `colour` — which is every line the converter wrote before it was
    // given a border colour, and the state an author reaches by typing into the
    // swatch the panel used to show them.
    ok("$what with only a colour is drawn in it",
       $isColour($colourOf($make(new Style(colour: $silver, borderSize: 2.0))), 192, 192, 192),
       json_encode($colourOf($make(new Style(colour: $silver, borderSize: 2.0)))));

    // Only `borderColour` — every rectangle in the shipped set.
    ok("$what with only a border colour is drawn in it",
       $isColour($colourOf($make(new Style(borderColour: $silver, borderSize: 2.0))), 192, 192, 192),
       json_encode($colourOf($make(new Style(borderColour: $silver, borderSize: 2.0)))));

    // Both, disagreeing. This is the check that says which swatch the panel
    // must show and write: the border colour wins, so that is the one it keeps.
    ok("$what with both drawn in the BORDER colour, not the other one",
       $isColour($colourOf($make(new Style(colour: $red, borderColour: $silver, borderSize: 2.0))),
                 192, 192, 192),
       json_encode($colourOf($make(new Style(colour: $red, borderColour: $silver, borderSize: 2.0)))));
}

// The swatch writes to `borderColour`, so a colour chosen in the panel has to
// arrive there and reach the paper — the same end-to-end claim the thickness
// checks above make, for the other control on the same row.
$colourForm = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
$chosen = $colourForm->apply(
    new Definition(type: Definition::FORM, title: 'colour', template: new Template([])),
    ['title' => 'colour', 'element' => [
        ['kind' => 'line', 'x' => '20', 'y' => '30', 'w' => '60', 'h' => '0',
         'borderSize' => '2', 'borderColour' => '#c0c0c0'],
        ['kind' => 'rect', 'x' => '20', 'y' => '60', 'w' => '60', 'h' => '20',
         'borderSize' => '2', 'borderColour' => '#c0c0c0'],
    ]],
    []);
$painted = $chosen->template?->elements ?? [];
ok('a colour chosen on a line or a box is stored as the border colour',
   count($painted) === 2
   && ($painted[0]->style?->borderColour?->r ?? null) === 192
   && ($painted[1]->style?->borderColour?->r ?? null) === 192,
   json_encode(array_map(fn($e) => $e->style?->borderColour, $painted)));
if (count($painted) === 2) {
    $drawn = drawColours(operators($renderer->render(new LayoutDocument(
        title: 'colour', template: $chosen->template, records: [new Record()],
        page: new PageSetup()))->bytes));
    ok('and both are drawn in it',
       count($drawn) >= 2 && $isColour($drawn[0], 192, 192, 192)
                          && $isColour($drawn[1], 192, 192, 192),
       json_encode($drawn));
}

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

// Overlap. Every box is a filled surface and the array order is the drawing
// order, so the UPPERMOST box at a point is the frame drawn around a block, and
// `event.target` is the one answer that can never reach the fields inside it.
ok('a press asks boxAt() rather than the browser',
   str_contains($canvasJs, 'const div = boxAt(event.clientX, event.clientY);')
   && !preg_match('/const div = event\.target\.closest/', $canvasJs));
ok('and boxAt() takes the smallest box containing the point',
   str_contains($canvasJs, 'if (area < bestArea) { best = div; bestArea = area; }'));
ok('a held box still keeps the pointer on its own handles',
   str_contains($canvasJs, 'return held || best;')
   && str_contains($canvasJs, "&& zoneAt(div, clientX, clientY) !== '' && area < heldArea"));
// The cursor SHAPE comes from the box that would be resized; the cursor itself
// has to be written on the topmost box, because that is the one the browser
// consults. Writing it on the box behind leaves the frame's own showing.
ok('the hover cursor is shaped by one box and written on another',
   str_contains($canvasJs, "const top = event.target.closest('.fb-canvas-box') || over;")
   && str_contains($canvasJs,
       "top.style.cursor = CURSORS[zoneAt(over, event.clientX, event.clientY)] || 'move';"));
// mouseover fires on entering a DIV, and inside one frame the pointer crosses a
// dozen fields without entering anything: the highlight would name the frame
// throughout. Driven from pointermove now, guarded so it only redraws on change.
ok('the hover highlight follows the pointer, not the divs it enters',
   !str_contains($canvasJs, "layer.addEventListener('mouseover'")
   && str_contains($canvasJs, 'if (over.dataset.index !== hovering) {'));
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

// ── a receipt is refused HERE, and edited elsewhere ──────────────────────────
// The canvas guard of §13.27. A serial form has no coordinates at all, so
// CanvasLayout would place every element at 0,0 and an author who moved one and
// saved would replace a working receipt with a layout that prints nothing.
// pre_process.php refuses at load, which is also what stops the save — its save
// branch is gated on the definition being non-null.
//
// Refused by the CANVAS is not refused by Forms: the receipt editor edits one
// and SerialRenderer prints one. Both halves are asserted, because the failure
// this guards against is the canvas quietly accepting a receipt, and the
// failure on the other side is the whole document becoming unprintable.
$serialSeen = 0;
$serialRunner = new FormRunner($db, $catalog);
foreach ($forms as $id => $title) {
    try { $definition = $loader->load($id); } catch (DefinitionException) { continue; }
    if (!$definition->isSerial()) continue;
    $serialSeen++;
    ok("pf_$id ($title) has a character-cell layout, not a canvas one",
        $definition->serialTemplate !== null && $definition->template === null,
        'it carries a page template the canvas would draw');
    ok("pf_$id ($title) still runs, for the receipt editor and the printer",
        $serialRunner->planDefinition($definition)->runnable(),
        'planDefinition() refuses it: nothing could print it');
}
if ($serialSeen === 0) {
    // Not a failure: a company without the POS module has no receipt to refuse.
    printf("  [--] no serial form on this company; the canvas guard is untested here\n");
}

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
$images = FORMS_DIR_IMAGES;
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

    $binding = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
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
        $binding = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
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

    // ── the four kinds that used to be unreachable (§13.30) ──────────────────
    //
    // A Table and a FieldBlock were refused for as long as the canvas could not
    // fill them: one created with no columns drew nothing and could not be
    // repaired on the page that made it. Now it can, so it is added EMPTY and
    // filled in the grid beside it — and the assertion below is the one that
    // used to say the opposite.

    [$withTable, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'table', 'x' => '5', 'y' => '5'];
        return $r;
    });
    $drawn = $withTable->template->elements;
    $new   = end($drawn);
    ok('a line-item table can be added', $new instanceof \FreeBooks\Forms\Document\Table
       && $warnings === [], implode(' ', $warnings));
    ok('it arrives on the one source every provider supplies',
       $new instanceof \FreeBooks\Forms\Document\Table && $new->source === 'items'
       && $new->columns === [], var_export($new->source ?? null, true));
    // A table is a REGION, not a line of text. One dropped at the 40x5 mm that
    // suits a value reads as a mistake rather than as a table awaiting columns.
    ok('and big enough to read as a table',
       $new->box->width === 180.0 && $new->box->height === 100.0,
       $new->box->width . 'x' . $new->box->height);

    [$withBlock, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'block', 'x' => '5', 'y' => '5'];
        return $r;
    });
    $drawn = $withBlock->template->elements;
    $new   = end($drawn);
    ok('an address block can be added', $new instanceof \FreeBooks\Forms\Document\FieldBlock
       && $new->parts === [] && $warnings === [], implode(' ', $warnings));

    [$withPage, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'pagenum', 'x' => '5', 'y' => '5'];
        return $r;
    });
    $drawn = $withPage->template->elements;
    $new   = end($drawn);
    ok('a page number can be added, with a pattern that prints something',
       $new instanceof \FreeBooks\Forms\Document\PageNumber
       && $new->pattern === '{page} / {pages}' && $warnings === [],
       var_export($new->pattern ?? null, true));

    // A barcode differs from the two above on purpose: an element bound to
    // nothing cannot be told apart, in the element list, from one whose binding
    // was lost — so it is refused rather than created blank.
    [$bare, $warnings] = $edit(function (array $r): array {
        $r['element'][] = ['index' => '', 'kind' => 'barcode', 'x' => '5', 'y' => '5'];
        return $r;
    });
    ok('a barcode with no value named is refused, not created blank',
       count($bare->template->elements) === count($victim->template->elements)
       && $warnings !== [], implode(' ', $warnings));

    $someField = (string)array_key_first($allowed);
    [$withBar, $warnings] = $edit(function (array $r) use ($someField): array {
        $r['element'][] = ['index' => '', 'kind' => 'barcode', 'x' => '5', 'y' => '5',
                           'bind' => $someField, 'symbology' => 'EAN13'];
        return $r;
    });
    $drawn = $withBar->template->elements;
    $new   = end($drawn);
    ok('a barcode naming a value the form supplies is added',
       $new instanceof \FreeBooks\Forms\Document\Barcode
       && $new->bind->name === $someField && $warnings === [], implode(' ', $warnings));
    ok('and it can be given a symbology other than the legacy default',
       $new instanceof \FreeBooks\Forms\Document\Barcode && $new->symbology === 'EAN13',
       var_export($new->symbology ?? null, true));

    // TCPDF throws on a type it does not know, from inside write1DBarcode()
    // with the document part-drawn. Refused here, where it can be reported.
    [$badCode, $warnings] = $edit(function (array $r) use ($someField): array {
        $r['element'][] = ['index' => '', 'kind' => 'barcode', 'x' => '5', 'y' => '5',
                           'bind' => $someField, 'symbology' => 'NOT-A-SYMBOLOGY'];
        return $r;
    });
    $drawn = $badCode->template->elements;
    $new   = end($drawn);
    ok('a symbology TCPDF cannot draw is refused, and the drawable default kept',
       $new instanceof \FreeBooks\Forms\Document\Barcode && $new->symbology === 'C128'
       && $warnings !== [], var_export($new->symbology ?? null, true));

    // ── a table's columns and a block's parts are edited, not carried ────────
    //
    // These two structures were carried past the canvas untouched for as long
    // as there was no control for them. They now travel through the form as
    // JSON in one field per element, which means every one of them is exposed
    // to being lost by a save — the exact failure the blanket carry prevented.
    // What replaces it is a per-key rule, and these are the checks on it.

    /** The first element of a kind, with the row that carries it. */
    $find = function (string $class) use ($victim): ?array {
        foreach ($victim->template->elements as $index => $element) {
            if ($element instanceof $class) return [$index, $element];
        }
        return null;
    };

    $tableAt = $find(\FreeBooks\Forms\Document\Table::class);
    if ($tableAt === null) {
        echo "  [--] this form has no line-item table; its column checks were skipped\n";
    } else {
        [$tIndex, $table] = $tableAt;
        $columnCount = count($table->columns);

        // The heading is the plainest possible edit, and it has to actually
        // take effect or every refusal below would pass on a binder that
        // ignores the field entirely.
        [$retitled, $warnings] = $edit(function (array $r) use ($tIndex): array {
            $columns = json_decode($r['element'][$tIndex]['columns'], true);
            $columns[0]['heading'] = 'Widget';
            $r['element'][$tIndex]['columns'] = json_encode($columns);
            return $r;
        });
        $edited = $retitled->template->elements[$tIndex];
        ok('a table column can be renamed',
           $edited->columns[0]->heading === 'Widget' && $warnings === [],
           var_export($edited->columns[0]->heading ?? null, true));
        ok('and the other columns are untouched',
           count($edited->columns) === $columnCount, count($edited->columns) . " vs $columnCount");

        // Reordering IS the edit for a table: column order is print order.
        [$swapped, $warnings] = $edit(function (array $r) use ($tIndex): array {
            $columns = json_decode($r['element'][$tIndex]['columns'], true);
            $columns[] = array_shift($columns);
            $r['element'][$tIndex]['columns'] = json_encode($columns);
            return $r;
        });
        $edited = $swapped->template->elements[$tIndex];
        ok('columns can be reordered, which is what changes the printed order',
           count($edited->columns) === $columnCount
           && $edited->columns[0]->bind->name === $table->columns[1]->bind->name
           && $warnings === [], implode(' ', $warnings));

        // The gate. A column is a binding like any other, and the palette is
        // the only list an author may name from.
        [$invented, $warnings] = $edit(function (array $r) use ($tIndex): array {
            $columns = json_decode($r['element'][$tIndex]['columns'], true);
            $columns[0]['bind'] = 'no_such_field_anywhere';
            $r['element'][$tIndex]['columns'] = json_encode($columns);
            return $r;
        });
        $edited = $invented->template->elements[$tIndex];
        ok('a column naming a field the form cannot supply is dropped, not stored',
           count($edited->columns) === $columnCount - 1 && $warnings !== [],
           implode(' ', $warnings));

        // ── the per-key carry rule ──
        //
        // This is the safety that replaced "carry every structure". A post that
        // says NOTHING about the columns is not a post that says none of them,
        // and the difference is a stale page emptying somebody's invoice.
        [$silent, $warnings] = $edit(function (array $r) use ($tIndex): array {
            unset($r['element'][$tIndex]['columns']);
            return $r;
        });
        ok('a post that omits the columns keeps them',
           count($silent->template->elements[$tIndex]->columns) === $columnCount
           && $warnings === [], count($silent->template->elements[$tIndex]->columns) . '');

        // Same for a blank, because a browser that fails to fill the field
        // sends one — and a blank is not an instruction.
        [$blank, ] = $edit(function (array $r) use ($tIndex): array {
            $r['element'][$tIndex]['columns'] = '';
            return $r;
        });
        ok('and a blank one keeps them too',
           count($blank->template->elements[$tIndex]->columns) === $columnCount);

        // Unreadable is carried as well, rather than treated as none.
        [$broken, $warnings] = $edit(function (array $r) use ($tIndex): array {
            $r['element'][$tIndex]['columns'] = '{not json';
            return $r;
        });
        ok('a column list that cannot be read keeps the previous one, and says so',
           count($broken->template->elements[$tIndex]->columns) === $columnCount
           && $warnings !== [], implode(' ', $warnings));

        // The deliberate empty, which has to look different from all three
        // above or deleting the last column would be impossible.
        [$emptied, ] = $edit(function (array $r) use ($tIndex): array {
            $r['element'][$tIndex]['columns'] = '[]';
            return $r;
        });
        ok('an explicit empty list does empty it',
           $emptied->template->elements[$tIndex]->columns === []);

        // A converted column always carries a Style, usually one holding
        // nothing but defaults. Letting those collapse to null would rewrite
        // the stored file of every shipped form on its first save.
        $styled = 0;
        foreach ($table->columns as $i => $column) {
            if ($column->style !== null && $retitled->template->elements[$tIndex]->columns[$i]->style !== null) $styled++;
        }
        ok('a column style survives the round trip rather than collapsing to none',
           $styled === count(array_filter($table->columns, fn($c) => $c->style !== null)),
           "$styled kept");
    }

    // ── a block's parts, and the constant-reading gate ───────────────────────

    $blockAt = $find(\FreeBooks\Forms\Document\FieldBlock::class);
    if ($blockAt === null) {
        echo "  [--] this form has no field block; its part checks were skipped\n";
    } else {
        [$bIndex, $block] = $blockAt;
        $partCount = count($block->parts);

        [$rejoined, $warnings] = $edit(function (array $r) use ($bIndex): array {
            $parts = json_decode($r['element'][$bIndex]['parts'], true);
            $parts[0]['separator'] = 'com-sp';
            $r['element'][$bIndex]['parts'] = json_encode($parts);
            return $r;
        });
        $edited = $rejoined->template->elements[$bIndex];
        ok('a block part can be given a different separator',
           $edited->parts[0]->separator === 'com-sp' && count($edited->parts) === $partCount
           && $warnings === [], var_export($edited->parts[0]->separator ?? null, true));

        // Formatter::separator() falls through its default on a name it does
        // not know and joins with NOTHING — an address block that has silently
        // lost its line breaks. Refused here, where it can be said out loud.
        [$odd, $warnings] = $edit(function (array $r) use ($bIndex): array {
            $parts = json_decode($r['element'][$bIndex]['parts'], true);
            $parts[0]['separator'] = 'sideways';
            $r['element'][$bIndex]['parts'] = json_encode($parts);
            return $r;
        });
        ok('a separator that does not exist is refused rather than silently ignored',
           $odd->template->elements[$bIndex]->parts[0]->separator === ''
           && $warnings !== [], implode(' ', $warnings));

        // THE security check of §13.30.
        //
        // A Context binding resolves through FormRunner::contextFor(), which is
        // `if (defined($name)) constant($name)` over every constant in the
        // process — configure.php's database password included. An author who
        // could type one into a part would have precisely the read primitive
        // §1.1 records as PhreeForm's defect.
        $contextPart = null;
        foreach ($victim->template->elements as $index => $element) {
            if (!$element instanceof \FreeBooks\Forms\Document\FieldBlock) continue;
            foreach ($element->parts as $part) {
                if ($part->bind->scope === \FreeBooks\Forms\Document\Scope::Context) {
                    $contextPart = $index; break 2;
                }
            }
        }
        if ($contextPart === null) {
            echo "  [--] this form has no company-bound block; the constant gate was checked below\n";
        } else {
            [$probe, $warnings] = $edit(function (array $r) use ($contextPart): array {
                $parts = json_decode($r['element'][$contextPart]['parts'], true);
                foreach ($parts as $i => $part) {
                    if (($part['scope'] ?? '') === 'context') {
                        $parts[$i]['bind'] = 'DB_SERVER_PASSWORD';
                        break;
                    }
                }
                $r['element'][$contextPart]['parts'] = json_encode($parts);
                return $r;
            });
            $names = array_map(fn($p) => $p->bind->name,
                               $probe->template->elements[$contextPart]->parts);
            ok('a company-bound part cannot be re-pointed at an arbitrary PHP constant',
               !in_array('DB_SERVER_PASSWORD', $names, true) && $warnings !== [],
               implode(', ', $names));
        }

        // And the same refusal for one invented from nothing, which is the
        // route that does not need an existing context part to exploit.
        [$conjured, $warnings] = $edit(function (array $r) use ($bIndex): array {
            $parts = json_decode($r['element'][$bIndex]['parts'], true);
            $parts[] = ['bind' => 'DB_SERVER_PASSWORD', 'scope' => 'context', 'separator' => 'nl'];
            $r['element'][$bIndex]['parts'] = json_encode($parts);
            return $r;
        });
        $names = array_map(fn($p) => $p->bind->name, $conjured->template->elements[$bIndex]->parts);
        ok('a company-bound part cannot be conjured naming one either',
           !in_array('DB_SERVER_PASSWORD', $names, true) && $warnings !== [],
           implode(' ', $warnings));
    }

    // The separator vocabulary the editor offers has to be the one the renderer
    // performs. A name in one list and not the other is an address block that
    // loses its line breaks with nothing reporting anything.
    $formatter = new \FreeBooks\Forms\Render\Formatter(currencies: $currencies ?? null);
    $performed = [];
    foreach (TemplateForm::SEPARATORS as $separator) {
        if ($separator === '') continue;
        $performed[$separator] = $formatter->separator('x', $separator) !== 'x';
    }
    ok('every separator the editor offers is one the renderer performs',
       !in_array(false, $performed, true),
       implode(', ', array_keys(array_filter($performed, fn($p) => !$p))));

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
    $images = FORMS_DIR_IMAGES;
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
            $saved = (new TemplateForm(FORMS_DIR_IMAGES, forms_font_names()))
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

    // ── the heading row, which has a style of its own ───────────────────────
    //
    // A table draws its headings from `headerStyle ?? style`, so a table has
    // two independent borders: the frame round the column headings and the box
    // round the rows. The canvas offered controls for the second only and
    // carried the first through untouched — so the grey frame on a shipped
    // invoice was drawn on every page and could not be changed from the editor
    // that drew it. §13.30 closed the same gap for the columns themselves.
    //
    // A form of its OWN, not $victim. That one is chosen for having an image,
    // and its table turned out to have no heading style at all — against which
    // "unticking the border turns it off" and "nothing else changed" both pass
    // without exercising a line of this code. Both did, with the feature
    // reverted. A check has to be run against data that can make it fail.
    $headed = $headedAt = null;
    foreach ($forms as $id => $title) {
        try { $candidate = $loader->load($id); } catch (DefinitionException) { continue; }
        if (!$candidate->isForm() || $candidate->template === null) continue;
        foreach ($candidate->template->elements as $index => $element) {
            if (!$element instanceof \FreeBooks\Forms\Document\Table) continue;
            if (!($element->headerStyle?->borderShow)) continue;
            $headed = $candidate; $headedAt = $index; break 2;
        }
    }

    if ($headed === null) {
        // Not a pass. Every shipped invoice has one, so a company with none is
        // a company where this cannot be checked, and that has to be said.
        echo "  [--] no form here has a table with a bordered heading, so the heading\n"
           . "       controls are not exercised on this company\n";
    } else {
        $headedAllowed = $allowedFor($headed);
        $headEdit = function (callable $mutate) use ($headed, $headedAllowed): array {
            $request = ['title' => $headed->title,
                        'element' => TemplateForm::toRequest($headed->template)];
            $request = $mutate($request);
            $binding = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
            return [$binding->apply($headed, $request, $headedAllowed), $binding->warnings()];
        };
        $heading = $headed->template->elements[$headedAt]->headerStyle;
        $encoder = new DefinitionCodec();
        printf("  using a form whose table heading is boxed (%dpt, border %s)\n",
            (int)($heading->size ?? 0), $heading->borderSize === null ? '?' : (string)$heading->borderSize);

        [$same, $warnings] = $headEdit(fn(array $r): array => $r);
        ok('a table survives a save with its heading style unchanged',
           $encoder->encode($same) === $encoder->encode($headed) && $warnings === [],
           'saving a form nobody edited rewrote its table heading');

        // The edit an author makes to get rid of the frame.
        [$off] = $headEdit(function (array $r) use ($headedAt): array {
            $r['element'][$headedAt]['hdrBorderShow'] = '';
            return $r;
        });
        $after = $off->template->elements[$headedAt]->headerStyle;
        ok('unticking the heading border turns it off',
           $after !== null && !$after->borderShow, 'the frame would still be drawn');

        // AND NOTHING ELSE. The heading is 8pt against a 12pt body on the
        // shipped invoice and there is no control for that, so a save that
        // cleared it would silently reflow every column heading on the form.
        ok('and changes nothing else about the heading',
           $after !== null
           && $after->size === $heading->size
           && $after->font === $heading->font
           && $after->borderSize === $heading->borderSize
           && (string)($after->align?->value ?? '') === (string)($heading->align?->value ?? ''),
           'the save altered a heading property that has no control');

        // Blank means KEEP, which is what the note under the control promises.
        // The round trip above cannot prove it: the canvas posts the current
        // size every time, so the fallback branch is never reached and a
        // builder that dropped it would pass unnoticed. It did.
        [$blank] = $headEdit(function (array $r) use ($headedAt): array {
            $r['element'][$headedAt]['hdrSize'] = '';
            return $r;
        });
        $kept = $blank->template->elements[$headedAt]->headerStyle;
        ok('clearing the heading size keeps the size the form already used',
           $kept !== null && $kept->size === $heading->size,
           'expected ' . var_export($heading->size, true) . ', got ' . var_export($kept?->size, true));

        // A page that does not know about these controls must not flatten what
        // it cannot see — the rule the whole binder keeps.
        [$older] = $headEdit(function (array $r) use ($headedAt): array {
            foreach (['hdrSize', 'hdrBorderSize', 'hdrBorderColour', 'hdrBorderShow'] as $key) {
                unset($r['element'][$headedAt][$key]);
            }
            return $r;
        });
        ok('a save with no heading fields at all carries the heading through',
           $encoder->encode($older) === $encoder->encode($headed),
           'an older client would have flattened the table heading');
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
        $saved   = (new TemplateForm(FORMS_DIR_IMAGES, forms_font_names()))
            ->apply($owner, $request, $allowedFor($owner));

        ok('an element keeps a border colour distinct from its text colour',
           differences($owner->template->elements[$index]->style,
                       $saved->template->elements[$index]->style) === []);

        // And the two move independently.
        $request['element'][$index]['colour']       = '#112233';
        $request['element'][$index]['borderColour'] = '#445566';
        $changed = (new TemplateForm(FORMS_DIR_IMAGES, forms_font_names()))
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

        $binding = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
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
// ── a form built from nothing but the four new kinds (§13.30) ───────────────
//
// The checks above prove the binder accepts each kind. This proves the point of
// accepting them: that a form assembled ONLY from what used to be unreachable
// is a form that stores, reloads and prints.
//
// It is the question the change was made to answer. A new blank Form Layout
// could previously be given text, values, rules, boxes and a logo — which is
// not an invoice, because an invoice needs line items and an address block, and
// neither could be added. So the assembly here is deliberately the one that was
// impossible: a table, a block, a page number and a barcode, and nothing else.

echo "\n=== a form built from nothing but the four new kinds ===\n";

$tableForm = null;
foreach ($forms as $id => $title) {
    try { $candidate = $loader->load($id); } catch (DefinitionException) { continue; }
    if (!$candidate->isForm() || $candidate->isSerial() || $candidate->template === null) continue;
    foreach ($candidate->template->elements as $element) {
        if ($element instanceof \FreeBooks\Forms\Document\Table && $element->columns) {
            $tableForm = [$candidate, $element];
            break 2;
        }
    }
}

if ($tableForm === null) {
    echo "  [--] no form with a line-item table on this company; the assembly was not built\n";
} else {
    [$donor, $donorTable] = $tableForm;

    // Line-item keys and record keys are borrowed from a real form rather than
    // invented, because the binder checks every name against what the document
    // can actually supply — inventing them would test the refusal path.
    $lineKeys = [];
    foreach ($donorTable->columns as $column) $lineKeys[] = $column->bind->name;
    $lineKeys = array_values(array_unique($lineKeys));
    $donorAllowed = $allowedFor($donor);
    $recordKeys   = array_slice(array_keys($donorAllowed), 0, 4);

    if (count($lineKeys) < 2 || count($recordKeys) < 2) {
        echo "  [--] too few bindings to assemble from\n";
    } else {
        $scratch = new Definition(
            id: $donor->id, type: $donor->type, title: 'scratch', description: '',
            group: $donor->group, provider: $donor->provider, datasetId: $donor->datasetId,
            page: $donor->page, template: new Template([]), breakField: $donor->breakField,
            recordTable: $donor->recordTable, recordKey: $donor->recordKey);

        $wantColumns = min(3, count($lineKeys));
        $wantParts   = min(3, count($recordKeys));

        $columnRows = [];
        foreach (array_slice($lineKeys, 0, $wantColumns) as $i => $key) {
            $columnRows[] = ['bind' => $key, 'scope' => 'record', 'sumOf' => [],
                             'heading' => 'Col' . ($i + 1), 'width' => (string)(30 + $i * 10),
                             'align' => 'L', 'format' => '', 'formatOptions' => [],
                             'total' => '', 'style' => null];
        }
        $partRows = function (string $separator) use ($recordKeys, $wantParts): array {
            $rows = [];
            foreach (array_slice($recordKeys, 0, $wantParts) as $key) {
                $rows[] = ['bind' => $key, 'scope' => 'record', 'sumOf' => [],
                           'separator' => $separator, 'format' => '', 'formatOptions' => []];
            }
            return $rows;
        };

        $assemble = function (string $separator) use ($scratch, $columnRows, $partRows,
                                                      $recordKeys, $donorAllowed): array {
            $request = ['title' => 'scratch', 'element' => [
                ['index' => '', 'kind' => 'table', 'x' => '10', 'y' => '60',
                 'w' => '180', 'h' => '100', 'columns' => json_encode($columnRows)],
                ['index' => '', 'kind' => 'block', 'x' => '10', 'y' => '20',
                 'w' => '80', 'h' => '5', 'parts' => json_encode($partRows($separator))],
                ['index' => '', 'kind' => 'pagenum', 'x' => '160', 'y' => '10',
                 'w' => '40', 'h' => '5', 'pattern' => '{page} {of} {pages}'],
                ['index' => '', 'kind' => 'barcode', 'x' => '120', 'y' => '30',
                 'w' => '50', 'h' => '15', 'bind' => $recordKeys[0], 'symbology' => 'C39'],
            ]];
            $binder = new TemplateForm(FORMS_DIR_IMAGES, forms_font_names());
            return [$binder->apply($scratch, $request, $donorAllowed), $binder->warnings()];
        };

        [$assembled, $warnings] = $assemble('nl');
        ok('all four kinds are accepted in one form, with nothing to report',
           count($assembled->template->elements) === 4 && $warnings === [],
           count($assembled->template->elements) . ' element(s); ' . implode(' ', $warnings));

        $kinds = array_map(fn($e) => (new \ReflectionClass($e))->getShortName(),
                           $assembled->template->elements);
        ok('and each is the kind that was asked for',
           $kinds === ['Table', 'FieldBlock', 'PageNumber', 'Barcode'], implode(', ', $kinds));

        $builtTable = $assembled->template->elements[0];
        $builtBlock = $assembled->template->elements[1];
        ok('the table arrived with the columns it was given, in order',
           count($builtTable->columns) === $wantColumns
           && $builtTable->columns[0]->heading === 'Col1'
           && $builtTable->columns[0]->bind->name === $lineKeys[0],
           count($builtTable->columns) . ' column(s)');
        ok('and the block with its lines', count($builtBlock->parts) === $wantParts,
           count($builtBlock->parts) . ' part(s)');

        // An element that exists only until the page is reopened is no use.
        $codec  = new DefinitionCodec();
        $stored = $codec->decode($codec->encode($assembled), $assembled->id);
        ok('the whole assembly survives being written and read back',
           count($stored->template->elements) === 4
           && count($stored->template->elements[0]->columns) === $wantColumns
           && count($stored->template->elements[1]->parts) === $wantParts
           && $stored->template->elements[2]->pattern === '{page} {of} {pages}'
           && $stored->template->elements[3]->symbology === 'C39');

        // ── and it prints ──
        $printed = function (Definition $definition) use ($renderer): array {
            $document = new LayoutDocument(
                title: $definition->title, template: $definition->template,
                records: [SampleData::record($definition->template, null)],
                page: $definition->page);
            $bytes = $renderer->render($document, new RenderOptions(filename: 'scratch.pdf'))->bytes;

            // TCPDF writes text as the ARRAY form, `[(...)] TJ`, not `Tj`.
            // Counting the wrong operator reports zero on a page full of words.
            $runs = 0;
            $rows = [];
            foreach (operators($bytes) as $line) {
                $runs += substr_count($line, '] TJ');
                if (preg_match('/([0-9.]+) ([0-9.]+) Td \[\(/', $line, $at)) $rows[(string)$at[2]] = true;
            }
            return [$bytes, $runs, count($rows)];
        };

        [$bytes, $runs, ] = $printed($stored);
        ok('the assembled form renders a PDF', str_starts_with($bytes, '%PDF') && strlen($bytes) > 2000,
           strlen($bytes) . ' bytes');

        // The table is the element that was worth adding, so it is the one held
        // to drawing something: a cell per sample row per column.
        $withoutTable = new Template(array_values(array_filter(
            $stored->template->elements,
            fn($e) => !$e instanceof \FreeBooks\Forms\Document\Table)));
        [, $bare, ] = $printed(new Definition(
            id: $stored->id, type: $stored->type, title: 'x', description: '',
            group: $stored->group, provider: $stored->provider, datasetId: $stored->datasetId,
            page: $stored->page, template: $withoutTable));

        // SampleData::record() invents six lines, so a table of N columns draws
        // at least 6N cells. The headings are extra, and not counted, so an
        // off-by-one in the heading row cannot make this pass by accident.
        ok('the line-item table draws a cell for every sample row and column',
           $runs - $bare >= 6 * $wantColumns,
           ($runs - $bare) . " more text runs than without it, wanted " . (6 * $wantColumns));

        // The separator is the whole of a block's layout, and the one thing
        // about it an author cannot predict. `nl` has to put the parts on
        // SEPARATE lines and no separator has to leave them on one — measured
        // off the y coordinates the renderer actually emitted, because the
        // block is one box of text either way and its box does not change.
        [$joined, ] = $assemble('');
        [, , $linesApart]    = $printed($stored);
        [, , $linesTogether] = $printed($codec->decode($codec->encode($joined), $joined->id));
        ok('a line break separator puts the block on more lines than none does',
           $linesApart > $linesTogether, "$linesApart vs $linesTogether distinct rows");
    }
}

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
