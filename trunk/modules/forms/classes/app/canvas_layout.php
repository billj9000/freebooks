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
//  Path: /modules/forms/classes/app/canvas_layout.php
//
/**
 * Forms — a template as boxes the canvas can draw. See §13.2, §13.7 step 3.
 *
 * The overlay needs one thing from the server that it cannot work out for
 * itself: WHERE each element is, in millimetres, and what to call it. This
 * produces exactly that and nothing else — no styling, no bindings resolved, no
 * HTML. The canvas positions the boxes; the PDF underneath is drawn by the same
 * `LayoutPdfRenderer` that prints.
 *
 * ## The conversion the whole approach rests on
 *
 * A `Box` is millimetres from the page's top-left corner, because that is what
 * `LayoutPdfRenderer` passes to TCPDF's `SetXY`/`Rect`, which are absolute page
 * coordinates rather than margin-relative ones. Measured, not assumed: a Rect at
 * (20, 30, 50 × 10) mm on A4 emits
 *
 *     56.692913 756.850630 141.732283 -28.346457 re
 *
 * into the content stream, on a MediaBox of 595.276 × 841.890 pt. That is
 * 20 × 72/25.4 across, 841.890 − 30 × 72/25.4 up from the bottom, and 50 × 10 mm
 * in size — so one millimetre is exactly 72/25.4 points, and y is measured DOWN
 * from the top of the page.
 *
 * pdf.js rasterizes at a scale where one point becomes `scale` pixels, so in the
 * browser one millimetre is `scale × 72/25.4` pixels and the y axis already
 * points down. The overlay therefore needs no page size, no margins and no
 * orientation: it reads the scale off the viewport it was given.
 * `docs/tools/verify_forms_canvas.php` pins that constant against a real render
 * so an upgrade to TCPDF or to pdf.js cannot move it silently.
 *
 * Line is the one element whose box is not a rectangle: `convertLine()` stores
 * the END POINT as width/height offsets, and those are signed — a line drawn up
 * the page has a negative height. The canvas normalises them into a box a
 * browser can position, and keeps the direction separately.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Document\{
    Barcode, Element, Field, FieldBlock, Image, Line, PageNumber, Rect, Scope, StaticText, Table,
    Template, Visibility
};

final class CanvasLayout
{
    /** Millimetres to PDF points, and so to CSS pixels once multiplied by the render scale. */
    public const POINTS_PER_MM = 72.0 / 25.4;

    /** What the overlay calls each element type. */
    private const KINDS = [
        StaticText::class => 'text',
        Field::class      => 'field',
        FieldBlock::class => 'block',
        Table::class      => 'table',
        Line::class       => 'line',
        Rect::class       => 'rect',
        Image::class      => 'image',
        PageNumber::class => 'pagenum',
        Barcode::class    => 'barcode',
    ];

    private const VISIBILITY = [
        'all'       => Visibility::AllPages,
        'first'     => Visibility::FirstPageOnly,
        'last'      => Visibility::LastPageOnly,
        'continued' => Visibility::ContinuedOnOverflow,
    ];

    /**
     * Every element as a positioned box, in millimetres.
     *
     * @return list<array<string,mixed>>
     */
    /**
     * @param ?string $imageDirectory where an Image's file lives, so its drawn
     *   height can be worked out. Omit it and images are marked unmeasured
     *   rather than drawn at the wrong size.
     */
    public static function boxes(Template $template, ?string $imageDirectory = null): array
    {
        $boxes = [];
        foreach ($template->elements as $index => $element) {
            $kind = self::KINDS[$element::class] ?? 'unknown';
            $box  = $element->box;
            $unmeasured = false;

            // A Line's width/height are an OFFSET to its end point and may be
            // negative; every other element's are a size. Normalising here keeps
            // the browser from having to know which is which, and a zero-length
            // side would otherwise be a box with no hit area at all.
            $width  = (float)($box->width  ?? 0);
            $height = (float)($box->height ?? 0);
            $x = $box->x;
            $y = $box->y;
            if ($kind === 'line') {
                if ($width  < 0) { $x += $width;  $width  = -$width; }
                if ($height < 0) { $y += $height; $height = -$height; }
            }

            // An image is stored with a WIDTH and no height — every logo in the
            // shipped set is — because TCPDF's Image() works the missing side
            // out from the file's aspect ratio. Passing that 0 straight to the
            // browser drew the logo as a two-pixel sliver: a box that is not
            // wrong so much as absent, on the one element an author most wants
            // to see the edges of.
            //
            // Unlike a block of text, this really is knowable: it depends on the
            // FILE, not on the record. So it is measured here, and only what
            // cannot be measured — a missing file, or an ImgLink whose filename
            // comes from the data — is reported as unmeasured.
            if ($kind === 'image') {
                [$width, $height, $unmeasured] =
                    self::imageSize($element, $width, $height, $imageDirectory);
            }

            $boxes[] = [
                'index'   => $index,
                'kind'    => $kind,
                // Whether the box is the whole element or only its first line.
                //
                // LayoutPdfRenderer draws every text element through TCPDF's
                // MultiCell, whose height argument is the height of ONE LINE
                // rather than of the cell: the text grows downward from the box
                // and the box does not describe where it ends. An address block
                // stores 4.5 mm and prints six lines.
                //
                // The stored box is still what an author positions, so that is
                // what is drawn — but a canvas that showed it without saying so
                // would state, in the one place an author trusts, that the
                // element ends where it does not. How far it really reaches
                // depends on the DATA and changes with the record, which is
                // exactly why it cannot simply be drawn larger.
                'grows'   => in_array($kind, ['text', 'field', 'block', 'pagenum'], true),
                // The box is a guess: the drawn size could not be worked out.
                'unmeasured' => $unmeasured,
                'x'       => round($x, 3),
                'y'       => round($y, 3),
                'width'   => round($width, 3),
                'height'  => round($height, 3),
                'label'   => self::describe($element),
                'visible' => self::keyFor(self::VISIBILITY, $element->visible) ?? 'all',
            ];
        }
        return $boxes;
    }

    /**
     * How big an Image is actually drawn, in millimetres.
     *
     * TCPDF fills in whichever side is 0 from the file's aspect ratio, and if
     * both are 0 it places the image at its natural size — pixels read as 72 dpi,
     * which is what `pixel_to_unit()` reduces to for a millimetre document.
     * Reproducing that here is the only way the outline matches the ink.
     *
     * @return array{0: float, 1: float, 2: bool} width, height, unmeasured
     */
    private static function imageSize(
        Image $element, float $width, float $height, ?string $directory
    ): array {
        if ($width > 0 && $height > 0) return [$width, $height, false];

        // An ImgLink takes its filename from the record, so which file — and so
        // which shape — changes per document. Nothing to measure.
        if (!is_string($element->src) || $element->src === '' || $directory === null) {
            return [$width ?: 25.0, $height ?: 25.0, true];
        }

        $path = rtrim($directory, '/\\') . '/' . basename($element->src);
        $size = @getimagesize($path);
        if ($size === false || (int)$size[0] <= 0 || (int)$size[1] <= 0) {
            return [$width ?: 25.0, $height ?: 25.0, true];
        }

        $ratio = (float)$size[1] / (float)$size[0];
        if ($width > 0)  return [$width, $width * $ratio, false];
        if ($height > 0) return [$height / $ratio, $height, false];

        // Neither given: TCPDF's natural size, pixels at 72 dpi.
        return [(float)$size[0] * 25.4 / 72.0, (float)$size[1] * 25.4 / 72.0, false];
    }

    /**
     * What to show when the author points at a box.
     *
     * The element's own description where it has one — that is what its author
     * typed — and otherwise what it is bound to, since "journal_main.post_date"
     * identifies a box on a crowded form far better than "field 27".
     */
    private static function describe(Element $element): string
    {
        $label = trim((string)($element->label ?? ''));
        if ($label !== '') return $label;

        if ($element instanceof StaticText) {
            $text = trim($element->text);
            return $text === '' ? 'text' : mb_substr($text, 0, 40);
        }
        if ($element instanceof Field || $element instanceof Barcode) {
            return ($element->bind->scope === Scope::Context ? 'company: ' : '') . $element->bind->name;
        }
        if ($element instanceof FieldBlock) {
            $names = array_map(fn($part) => $part->bind->name, $element->parts);
            return implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? '…' : '');
        }
        if ($element instanceof Table)  return 'table: ' . $element->source;
        if ($element instanceof Image)  return 'image';
        return self::KINDS[$element::class] ?? 'element';
    }

    /** @param array<string,mixed> $map */
    private static function keyFor(array $map, mixed $value): ?string
    {
        $key = array_search($value, $map, true);
        return $key === false ? null : (string)$key;
    }
}
