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
//  Path: /modules/forms/classes/definition/template_form.php
//
/**
 * Forms — a canvas of positioned boxes, back into a Template. See §13.7 step 4.
 *
 * The sibling of DefinitionForm, which does the same job for a report's column
 * grid, and it keeps the same two rules for the same two reasons.
 *
 * **The base matters.** An author moves, resizes, restyles, adds and deletes.
 * Everything else — a FieldBlock's parts, a Table's columns and source, an
 * Image's bound filename, a Line's type, a Barcode's symbology — is carried
 * across from the element it came from, untouched. Those are structures the
 * canvas cannot draw controls for yet, and a form that can only submit what it
 * can draw would delete them on the first save. Silently, which is the way this
 * kind of editor usually loses things.
 *
 * **An author names only what exists.** Every binding submitted is checked
 * against the list of names the document can actually supply. For a
 * query-driven form that is the dataset's fields; for one of the nine built by
 * a provider it is what the provider WAS OBSERVED to supply, since a provider
 * declares nothing — §13.8 risk 3, answered by running the form once and
 * reading the keys back off the Record. `entry_builder` supplies 76,
 * `statement_builder` 26, `wo_builder` 25.
 *
 * ## Geometry is submitted as STORED, not as drawn
 *
 * Two element types have a stored box that is not the box on screen, and both
 * were found by looking at the canvas rather than at the code (§13.11):
 *
 *   - a `Line`'s width and height are a SIGNED offset to its end point, so a
 *     line drawn upward has a negative height;
 *   - an `Image` usually stores a width and NO height, because TCPDF derives
 *     the other side from the file.
 *
 * The canvas therefore carries both: the drawn box, which it positions with,
 * and the stored box, which it edits by the same delta and submits. An
 * untouched save then returns exactly what was loaded — nulls included —
 * rather than a normalised approximation of it, which is what
 * `verify_forms_canvas.php` holds it to.
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Document\{
    Align, Barcode, Binding, Box, Colour, Element, Field, FieldBlock, Image, Line, PageNumber,
    Rect, Scope, StaticText, Style, Table, Template, Visibility
};

final class TemplateForm
{
    /**
     * What an author may add.
     *
     * The kinds whose whole content the canvas can supply. A Table created here
     * would arrive with no columns and a FieldBlock with no parts, and neither
     * could be repaired on that page — so they are not offered.
     *
     * An image was excluded for the same reason until the canvas could put a
     * file in the images folder; its "sub-structure" is one filename, and there
     * was no way to set it. There is now (§13.13), so it joins the list. A form
     * that ships without a logo can be given one.
     */
    public const ADDABLE = ['text', 'field', 'line', 'rect', 'image'];

    private const VISIBILITY = [
        'all'       => Visibility::AllPages,
        'first'     => Visibility::FirstPageOnly,
        'last'      => Visibility::LastPageOnly,
        'continued' => Visibility::ContinuedOnOverflow,
    ];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param ?string $imageDirectory where a fixed image's file must live for
     *   the name to be accepted. Null means image files cannot be changed here,
     *   which is safer than accepting a name nothing can check.
     */
    /**
     * @param ?array<string,string> $fonts the faces that may be named, keyed by
     *   stored name. Null means "do not check", which is right for a caller
     *   that has no list; passing a list is what makes an unknown font a
     *   refusal rather than an error thrown from inside TCPDF.
     */
    public function __construct(
        private readonly ?string $imageDirectory = null,
        private readonly ?array  $fonts = null,
    ) {}

    /**
     * Apply a submitted canvas to the definition it was opened from.
     *
     * Row ORDER is z-order: elements are drawn in the order they appear, so
     * moving a row is how an author puts a box in front of another.
     *
     * @param array<string,mixed>   $request
     * @param array<string,true>    $allowed binding names this document can supply
     * @throws DefinitionException when the definition is not a form at all
     */
    public function apply(Definition $base, array $request, array $allowed): Definition
    {
        $this->warnings = [];

        if (!$base->isForm()) {
            throw new DefinitionException('This is a report. The report editor edits it, not the canvas.');
        }

        $original = $base->template?->elements ?? [];
        $elements = [];

        foreach (array_values((array)($request['element'] ?? [])) as $position => $row) {
            $row = (array)$row;
            $index = ($row['index'] ?? '') === '' ? null : (int)$row['index'];

            // An index that names nothing is not an element the author moved; it
            // is a row that would silently become a NEW element of the wrong
            // kind. Refused rather than guessed at.
            if ($index !== null && !isset($original[$index])) {
                $this->warnings[] = 'An element that is no longer part of this form was submitted; it was ignored.';
                continue;
            }

            $element = $index === null
                ? $this->create($row, $position, $allowed)
                : $this->rebuild($original[$index], $row, $position, $allowed);

            if ($element !== null) $elements[] = $element;
        }

        return new Definition(
            id:          $base->id,
            type:        $base->type,
            title:       trim((string)($request['title'] ?? $base->title)) ?: $base->title,
            description: $base->description,
            group:       $base->group,
            filenamePrefix: $base->filenamePrefix,
            emailMessage:   $base->emailMessage,
            provider:    $base->provider,
            datasetId:   $base->datasetId,
            page:        $base->page,
            dateDefault: $base->dateDefault,
            dateField:   $base->dateField,
            period:      $base->period,
            filters:     $base->filters,
            sorts:       $base->sorts,
            columns:     $base->columns,
            groupBy:     $base->groupBy,
            groupFormat: $base->groupFormat,
            totalsOnly:  $base->totalsOnly,
            resolvedColumns: $base->resolvedColumns,
            style:       $base->style,
            template:    new Template($elements),
            breakField:  $base->breakField,
            recordTable: $base->recordTable,
            recordKey:   $base->recordKey,
            // The canvas refuses a receipt before it gets here, so these are
            // carried rather than used. Dropping them would mean that if it
            // ever DID get here, the save would quietly turn a receipt into a
            // page — which is the exact loss the canvas guard exists to stop.
            serial:         $base->serial,
            serialTemplate: $base->serialTemplate,
        );
    }

    /**
     * The template as the canvas's own field values.
     *
     * The inverse of apply(). `verify_forms_canvas.php` requires that applying
     * this returns the template unchanged, which is the property that stops
     * opening a form and saving it from quietly rewriting it.
     *
     * @return list<array<string,mixed>>
     */
    public static function toRequest(Template $template): array
    {
        $rows = [];
        foreach ($template->elements as $index => $element) {
            $box   = $element->box;
            $style = $element->style;

            $rows[] = [
                'index'      => (string)$index,
                'x'          => self::number($box->x),
                'y'          => self::number($box->y),
                'w'          => $box->width  === null ? '' : self::number($box->width),
                'h'          => $box->height === null ? '' : self::number($box->height),
                'bind'       => self::bindingOf($element),
                'src'        => $element instanceof Image && is_string($element->src)
                                    ? $element->src : '',
                'text'       => $element instanceof StaticText ? $element->text : '',
                'visible'    => self::keyFor(self::VISIBILITY, $element->visible) ?? 'all',
                'label'      => (string)($element->label ?? ''),
                'font'       => (string)($style?->font ?? ''),
                'size'       => $style?->size === null ? '' : self::number($style->size),
                'colour'     => self::colourText($style?->colour),
                'borderColour' => self::colourText($style?->borderColour),
                'align'      => $style?->align?->value ?? '',
                'borderSize' => $style?->borderSize === null ? '' : self::number($style->borderSize),
                'borderShow' => $style?->borderShow ? '1' : '',
                'truncate'   => $style?->truncate   ? '1' : '',
                'bold'       => $style?->bold       ? '1' : '',
                'italic'     => $style?->italic     ? '1' : '',
            ];
        }
        return $rows;
    }

    /** @return list<string> */
    public function warnings(): array { return $this->warnings; }

    // ── rebuilding ───────────────────────────────────────────────────────────

    /**
     * The same element, moved, restyled, and possibly re-bound.
     *
     * Everything the canvas cannot yet draw a control for is taken from the
     * ORIGINAL, which is why this receives it rather than only the row.
     *
     * @param array<string,mixed> $row
     * @param array<string,true>  $allowed
     */
    private function rebuild(Element $original, array $row, int $position, array $allowed): ?Element
    {
        $box     = $this->box($row, $original->box);
        $style   = $this->style($row, $original->style);
        $visible = $this->visibility($row, $original->visible);
        $label   = trim((string)($row['label'] ?? '')) ?: null;
        $what    = 'element ' . ($position + 1);

        return match (true) {
            $original instanceof StaticText =>
                new StaticText((string)($row['text'] ?? $original->text), $box, $style, $visible, $label),

            $original instanceof Field =>
                new Field($this->binding($row, $original->bind, $allowed, $what),
                          $box, $original->format, $style, $visible, $label),

            $original instanceof Barcode =>
                new Barcode($this->binding($row, $original->bind, $allowed, $what),
                            $box, $original->symbology, $style, $visible, $label),

            // Parts, columns, sources and bound filenames are structures with no
            // control on the canvas. Carried, not rebuilt.
            $original instanceof FieldBlock =>
                new FieldBlock($original->parts, $box, $style, $visible, $label),

            $original instanceof Table =>
                new Table($original->source, $original->columns, $box,
                          $original->headerStyle, $style, $visible, $label),

            // Two different edits behind one element type: a bound image takes
            // a VALUE from the record, a fixed one names a FILE. Both go
            // through a gate — a value the form can supply, or a file that is
            // actually there — because a path typed by hand is a path to
            // anywhere.
            $original instanceof Image =>
                new Image($original->src instanceof Binding
                            ? $this->binding($row, $original->src, $allowed, $what)
                            : $this->imageFile($row, (string)$original->src, $what),
                          $box, $style, $visible, $label, $original->pathPrefix),

            $original instanceof PageNumber =>
                new PageNumber($box, $original->pattern, $style, $visible, $label),

            $original instanceof Line =>
                new Line($box, $original->lineType, $style, $visible, $label),

            $original instanceof Rect =>
                new Rect($box, $style, $visible, $label),

            default => null,
        };
    }

    /**
     * A brand-new element.
     *
     * Only the four kinds that need no sub-structure. A Table or a FieldBlock
     * created here would arrive with no columns and no parts, which is a form
     * element that draws nothing and cannot be fixed on this page.
     *
     * @param array<string,mixed> $row
     * @param array<string,true>  $allowed
     */
    private function create(array $row, int $position, array $allowed): ?Element
    {
        $kind = (string)($row['kind'] ?? '');
        if (!in_array($kind, self::ADDABLE, true)) {
            $this->warnings[] = "A new element of type '$kind' cannot be added here; it was not saved.";
            return null;
        }

        // An image's fallback height is NULL, not 5 mm: TCPDF works the missing
        // side out from the file, which is how every logo in the shipped set is
        // stored. Giving a new one a height would fix its shape to whatever the
        // default happened to be and squash the first picture put in it.
        $box     = $this->box($row, $kind === 'image'
                                      ? new Box(10.0, 10.0, 40.0, null)
                                      : new Box(10.0, 10.0, 40.0, 5.0));
        $style   = $this->style($row, null);
        $visible = $this->visibility($row, Visibility::AllPages);
        $label   = trim((string)($row['label'] ?? '')) ?: null;
        $what    = 'new element ' . ($position + 1);

        return match ($kind) {
            'text'  => new StaticText((string)($row['text'] ?? ''), $box, $style, $visible, $label),
            'rect'  => new Rect($box, $style, $visible, $label),
            'line'  => new Line($box, 'H', $style, $visible, $label),

            // Kept even with no file chosen yet. The renderer skips an image
            // whose src is empty or missing — deliberately, so that a missing
            // picture cannot abort a document — so an element added now and
            // filled in a moment later draws nothing in between rather than
            // breaking the page it is being placed on.
            'image' => new Image($this->imageFile($row, '', $what), $box, $style, $visible, $label),
            'field' => (function () use ($row, $allowed, $what, $box, $style, $visible, $label): ?Element {
                $name = trim((string)($row['bind'] ?? ''));
                if ($name === '' || !isset($allowed[$name])) {
                    $this->warnings[] = "$what names no field this form can supply; it was not saved.";
                    return null;
                }
                return new Field(Binding::record($name), $box, null, $style, $visible, $label);
            })(),
            default => null,
        };
    }

    // ── pieces ───────────────────────────────────────────────────────────────

    /**
     * The stored box, with nulls preserved.
     *
     * A blank width or height is NOT zero — it is "let the renderer decide",
     * which is how every logo stores its height. Reading a blank as 0 would
     * pin an image to nothing on the first save of a form nobody meant to
     * change.
     *
     * @param array<string,mixed> $row
     */
    private function box(array $row, Box $fallback): Box
    {
        $optional = function (mixed $value, ?float $fallback): ?float {
            $value = trim((string)$value);
            if ($value === '')      return null;
            if (!is_numeric($value)) return $fallback;
            return (float)$value;
        };
        $required = function (mixed $value, float $fallback): float {
            $value = trim((string)$value);
            return $value === '' || !is_numeric($value) ? $fallback : (float)$value;
        };

        return new Box(
            x:      $required($row['x'] ?? '', $fallback->x),
            y:      $required($row['y'] ?? '', $fallback->y),
            width:  array_key_exists('w', $row) ? $optional($row['w'], $fallback->width)  : $fallback->width,
            height: array_key_exists('h', $row) ? $optional($row['h'], $fallback->height) : $fallback->height,
        );
    }

    /** @param array<string,mixed> $row */
    private function style(array $row, ?Style $fallback): ?Style
    {
        $font = $this->font(trim((string)($row['font'] ?? '')), $fallback?->font);
        $size = trim((string)($row['size'] ?? ''));
        $colour = self::colourFrom((string)($row['colour'] ?? ''));
        $align  = Align::tryFrom((string)($row['align'] ?? ''));
        $border = trim((string)($row['borderSize'] ?? ''));
        // Independent of the text colour, and used: 16 shipped elements draw
        // black text inside a silver rule. The renderer reads
        // `borderColour ?? colour`, so leaving this unset makes the rule follow
        // the text — which is a default, not a conflation.
        $borderColour = array_key_exists('borderColour', $row)
            ? self::colourFrom((string)$row['borderColour'])
            : $fallback?->borderColour;

        $style = new Style(
            font:         $font !== '' ? $font : null,
            size:         $size !== '' && is_numeric($size) ? (float)$size : null,
            colour:       $colour,
            align:        $align,
            borderSize:   $border !== '' && is_numeric($border) ? (float)$border : null,
            borderColour: $borderColour,
            borderShow:   !empty($row['borderShow']),
            truncate:     !empty($row['truncate']),
            bold:         !empty($row['bold']),
            italic:       !empty($row['italic']),
        );

        // An element that had NO style keeps none unless something was set: an
        // empty Style is not the same as no Style, and writing one where none
        // was would change how a Table's heading row is drawn (§13.9).
        if ($fallback === null && $font === '' && $size === '' && $colour === null && $align === null
            && $border === '' && $borderColour === null
            && empty($row['borderShow']) && empty($row['truncate'])
            && empty($row['bold']) && empty($row['italic'])) {
            return null;
        }
        return $style;
    }

    /**
     * A submitted font name, or the one it had.
     *
     * TCPDF does not fall back on an unknown family — it tries to include a
     * font definition file and errors out, with the document half built. So an
     * unknown name is refused here, where it can be reported, rather than
     * discovered at render time.
     *
     * A name already IN the definition is always kept, listed or not: a
     * customised install may use a face this list does not carry, and silently
     * restyling someone's invoice because a select had no option for it is the
     * loss this whole class exists to prevent.
     */
    private function font(string $submitted, ?string $current): string
    {
        if ($submitted === '' || $submitted === $current) return $submitted;
        if ($this->fonts === null || isset($this->fonts[$submitted])) return $submitted;

        $this->warnings[] = "'$submitted' is not a font this document can be drawn in;"
                          . ' the previous one was kept.';
        return (string)$current;
    }

    /** @param array<string,mixed> $row */
    private function visibility(array $row, Visibility $fallback): Visibility
    {
        $key = (string)($row['visible'] ?? '');
        return self::VISIBILITY[$key] ?? $fallback;
    }

    /**
     * A submitted binding, or the one it had.
     *
     * The single gate every author-supplied name passes through. A Context
     * binding is left alone: those are company constants, not fields of the
     * record, and they are not in the palette.
     *
     * @param array<string,mixed> $row
     * @param array<string,true>  $allowed
     */
    private function binding(array $row, Binding $current, array $allowed, string $what): Binding
    {
        $name = trim((string)($row['bind'] ?? ''));
        if ($name === '' || $name === $current->name) return $current;
        // Refused, and SAID so. This used to return quietly, which was safe and
        // silent — the canvas sends nothing for a context element, so the only
        // way here is a stale page or a hand-made post, and both deserve an
        // answer rather than an edit that appears to work and does not.
        if ($current->scope === Scope::Context) {
            $this->warnings[] = "$what is one of the company's own details, so it cannot be pointed"
                              . " at '$name'; it was left as it was.";
            return $current;
        }

        if (!isset($allowed[$name])) {
            $this->warnings[] = "$what names '$name', which this form cannot supply; it was left as it was.";
            return $current;
        }
        return new Binding($name, $current->scope, $current->sumOf);
    }

    /**
     * A fixed image filename, checked against the files that exist.
     *
     * The canvas offers a list of what is in the images directory, and this is
     * where that list is enforced: a filename is a path, and a path submitted
     * by a browser is whatever the browser chose to send. basename() alone
     * would still accept a name that is simply not there, which prints a form
     * with a hole in it and no error.
     *
     * @param array<string,mixed> $row
     */
    private function imageFile(array $row, string $current, string $what): string
    {
        $name = trim((string)($row['src'] ?? ''));
        if ($name === '' || $name === $current) return $current;

        $name = basename($name);
        if ($this->imageDirectory === null) {
            $this->warnings[] = "$what cannot change its image here; it was left as it was.";
            return $current;
        }
        if (!is_file(rtrim($this->imageDirectory, '/\\') . '/' . $name)) {
            $this->warnings[] = "$what names an image that is not in the images folder;"
                              . ' it was left as it was.';
            return $current;
        }
        return $name;
    }

    private static function bindingOf(Element $element): string
    {
        if ($element instanceof Field || $element instanceof Barcode) return $element->bind->name;
        if ($element instanceof Image && $element->src instanceof Binding) return $element->src->name;
        return '';
    }

    /** Trailing zeros are noise in a form field, and 8.0 and 8 are the same box. */
    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
    }

    private static function colourText(?Colour $colour): string
    {
        return $colour === null ? '' : sprintf('#%02x%02x%02x', $colour->r, $colour->g, $colour->b);
    }

    private static function colourFrom(string $value): ?Colour
    {
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', trim($value), $parts)) return null;
        return new Colour(hexdec($parts[1]), hexdec($parts[2]), hexdec($parts[3]));
    }

    /** @param array<string,mixed> $map */
    private static function keyFor(array $map, mixed $value): ?string
    {
        $key = array_search($value, $map, true);
        return $key === false ? null : (string)$key;
    }
}
