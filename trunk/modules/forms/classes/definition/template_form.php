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
 * Anything the canvas draws no control for is carried across from the element
 * it came from, untouched — because a form that can only submit what it can
 * draw would delete the rest on the first save. Silently, which is the way this
 * kind of editor usually loses things.
 *
 * What is carried has shrunk as the canvas has grown. A Table's columns and a
 * FieldBlock's parts were on that list until §13.30 gave them editors; a
 * Table's source, a Table's heading style, an Image's folder prefix and a
 * Line's type are still on it.
 *
 * The rule that replaced "carry everything structural" is narrower and applies
 * per key: **an ABSENT key is carried, a PRESENT one is rebuilt.** So a post
 * from a page that knows nothing of columns leaves a table's columns exactly as
 * they were, while a post that names them replaces them. That is what keeps an
 * older cached page, or a partial submission, from emptying a table — the
 * failure the blanket carry existed to prevent, kept without freezing the
 * structures it protected.
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
    Align, Barcode, Binding, BlockPart, Box, Colour, Column, Element, Field, FieldBlock, Format,
    Image, Line, PageNumber, Rect, Scope, StaticText, Style, Table, Template, Visibility
};

final class TemplateForm
{
    /**
     * What an author may add: all nine kinds (§13.30).
     *
     * The list used to be five. The rule behind it was never "these are the
     * important ones" but "these are the ones the canvas can COMPLETE" — a
     * Table created with no columns and a FieldBlock created with no parts were
     * elements that drew nothing and could not be repaired on the page that
     * made them, so offering them would have been offering a dead end.
     *
     * An image left the exclusion first, when the canvas learnt to put a file
     * in the images folder (§13.13): its whole sub-structure is one filename,
     * and once there was a control for it there was no reason to withhold it.
     *
     * The other four leave it for the same reason and nothing else. A Table's
     * columns and a FieldBlock's parts are now edited here rather than carried
     * past, a PageNumber's pattern is one string, and a Barcode needs a binding
     * — which this class already gates — plus a symbology. The test has not
     * moved; the canvas has caught up with it.
     *
     * This matters most for the kinds it was previously impossible to obtain: a
     * form created from scratch could be given text, values, rules, boxes and a
     * logo, which is not an invoice. Line items and an address block were
     * reachable only by copying a form that already had them.
     */
    public const ADDABLE = ['text', 'field', 'block', 'table', 'line', 'rect',
                            'image', 'pagenum', 'barcode'];

    /**
     * How a block part joins to the one after it.
     *
     * Formatter::separator()'s vocabulary, and it has to stay in step with it:
     * a name that class does not know falls through its `default` and joins the
     * next part with nothing, which reads as an address block that has lost its
     * line breaks. `verify_forms_canvas.php` holds the two together.
     *
     * 'del-nl' is the one worth knowing: a line break UNLESS the value is
     * empty, which is how an address block closes up around a missing second
     * line instead of printing a gap.
     */
    public const SEPARATORS = ['', 'nl', 'del-nl', 'sp', '2sp', 'comma', 'com-sp', 'semi-sp'];

    /**
     * The barcode symbologies on offer.
     *
     * A curated subset of TCPDF's 1D list, not all thirty: every one of these
     * is a symbology a business document plausibly carries, and the rest
     * (POSTNET, KIX, IMB, PHARMA) are national postal and pharmaceutical codes
     * that would be noise in a select an author reads once.
     *
     * The list is a gate as well as a menu — TCPDF throws on a type it does not
     * know, from inside the renderer with the document half drawn, so an
     * unknown name is refused here where it can be reported. A symbology
     * already IN the definition is kept regardless, for the same reason an
     * unlisted font is: a customised install may use one this list does not
     * carry, and silently re-coding someone's barcode is the loss this class
     * exists to prevent.
     */
    public const SYMBOLOGIES = ['C128', 'C128A', 'C128B', 'C128C', 'C39', 'C39E', 'C93',
                                'I25', 'S25', 'EAN8', 'EAN13', 'UPCA', 'UPCE',
                                'CODABAR', 'CODE11', 'MSI'];

    /** Every provider supplies its line items under this one name. */
    private const DEFAULT_SOURCE = 'items';

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
            $box    = $element->box;
            $style  = $element->style;
            $header = $element instanceof Table ? $element->headerStyle : null;

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

                // The two sub-structures, as ONE value each rather than as a
                // nested array of fields.
                //
                // The canvas submits `element[i][key]` and nothing deeper: the
                // save writes those hidden fields from a flat object, and the
                // live preview POSTs the same object through FormData. A nested
                // `element[i][column][j][bind]` would mean teaching both of
                // those to walk a tree, and teaching undo — which snapshots the
                // same object — to survive the walk.
                //
                // So a column list travels as JSON in a single field. It is
                // opaque to the transport and structured at both ends, which is
                // the same trade the report editor makes for a filter
                // expression, for the same reason.
                'columns'    => $element instanceof Table
                                    ? self::columnsToValue($element->columns) : '',
                'parts'      => $element instanceof FieldBlock
                                    ? self::partsToValue($element->parts) : '',
                'pattern'    => $element instanceof PageNumber ? $element->pattern : '',
                'symbology'  => $element instanceof Barcode ? $element->symbology : '',
                'visible'    => self::keyFor(self::VISIBILITY, $element->visible) ?? 'all',
                'label'      => (string)($element->label ?? ''),
                'font'       => (string)($style?->font ?? ''),
                'size'       => $style?->size === null ? '' : self::number($style->size),
                'colour'     => self::colourText($style?->colour),
                'borderColour' => self::colourText($style?->borderColour),
                'align'      => $style?->align?->value ?? '',
                'borderSize' => $style?->borderSize === null ? '' : self::number($style->borderSize),
                'borderShow' => $style?->borderShow ? '1' : '',

                // The heading row's own style, which a table has and nothing
                // else does. The renderer reads `headerStyle ?? style`, so
                // these four are a second, independent set — the grey frame
                // round an invoice's column headings is this borderShow, not
                // the one above it, and unticking the wrong one leaves the
                // heading boxed and the body not.
                //
                // Only the four the canvas offers. Font, colour, alignment and
                // weight are carried through untouched by headerStyleFrom(),
                // because a control that is not on screen must not be able to
                // clear the value behind it.
                'hdrSize'         => $header?->size === null ? '' : self::number($header->size),
                'hdrBorderSize'   => $header?->borderSize === null ? '' : self::number($header->borderSize),
                'hdrBorderColour' => self::colourText($header?->borderColour),
                'hdrBorderShow'   => $header?->borderShow ? '1' : '',
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
                            $box, $this->symbology($row, $original->symbology, $what),
                            $style, $visible, $label),

            // Parts and columns are edited here now (§13.30) — but only when
            // the post actually carries them. Absent, they are carried, which
            // is what stops a stale page from emptying a table.
            $original instanceof FieldBlock =>
                new FieldBlock($this->parts($row, $original->parts, $allowed, $what),
                               $box, $style, $visible, $label),

            // The source and the heading style stay carried: every provider
            // supplies its line items under one name, so a source picker would
            // offer a list of one, and the heading row has no control of its own
            // on the canvas.
            $original instanceof Table =>
                new Table($original->source,
                          $this->columns($row, $original->columns, $allowed, $what), $box,
                          $this->headerStyleFrom($row, $original->headerStyle),
                          $style, $visible, $label),

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
                new PageNumber($box, $this->pattern($row, $original->pattern), $style, $visible, $label),

            $original instanceof Line =>
                new Line($box, $original->lineType, $style, $visible, $label),

            $original instanceof Rect =>
                new Rect($box, $style, $visible, $label),

            default => null,
        };
    }

    /**
     * A brand-new element. Any of the nine kinds (§13.30).
     *
     * A Table and a FieldBlock may be created EMPTY — with no columns and no
     * parts. That was the objection to offering them at all, and it stopped
     * being one when the inspector gained the grids that fill them: an empty
     * table draws nothing for as long as it takes to add the first column,
     * which is a state the author is passing through rather than one they are
     * stuck in. The renderer already tolerates it — an empty column list draws
     * no cells, it does not fail — so the intermediate state costs a blank
     * outline and nothing else.
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
        //
        // A table's is deeper and wider than the rest for a plainer reason: it
        // is the one element whose box is a REGION rather than a line of text,
        // and one dropped at 40x5 mm reads as a mistake rather than as a table
        // waiting for columns.
        $box     = $this->box($row, match ($kind) {
                                      'image' => new Box(10.0, 10.0, 40.0, null),
                                      'table' => new Box(10.0, 10.0, 180.0, 100.0),
                                      default => new Box(10.0, 10.0, 40.0, 5.0),
                                    });
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

            // Created empty unless the row already carries a structure — which
            // it does when the canvas seeds one, and does not when the element
            // is added bare and filled in afterwards.
            'block' => new FieldBlock($this->parts($row, [], $allowed, $what),
                                      $box, $style, $visible, $label),
            'table' => new Table(self::DEFAULT_SOURCE,
                                 $this->columns($row, [], $allowed, $what), $box,
                                 null, $style, $visible, $label),

            // The default pattern is the ELEMENT's, not this class's: a page
            // number added with nothing typed should read the way every
            // converted one does.
            'pagenum' => new PageNumber($box, $this->pattern($row, '{page} / {pages}'),
                                        $style, $visible, $label),

            'barcode' => (function () use ($row, $allowed, $what, $box, $style, $visible, $label): ?Element {
                $bind = $this->newBinding($row, $allowed, $what);
                return $bind === null ? null
                     : new Barcode($bind, $box, $this->symbology($row, 'C128', $what),
                                   $style, $visible, $label);
            })(),

            'field' => (function () use ($row, $allowed, $what, $box, $style, $visible, $label): ?Element {
                $bind = $this->newBinding($row, $allowed, $what);
                return $bind === null ? null
                     : new Field($bind, $box, null, $style, $visible, $label);
            })(),
            default => null,
        };
    }

    /**
     * The binding a newly added element names, or null if it names nothing real.
     *
     * A value and a barcode both need one before they can exist — an element
     * bound to nothing draws nothing and reads, in the element list, as an
     * element that is simply broken. Refused rather than created blank, which
     * is the one place this differs from a table: a table with no columns can
     * be repaired in the grid beside it, an element with no binding cannot be
     * told apart from one whose binding was lost.
     *
     * @param array<string,mixed> $row
     * @param array<string,true>  $allowed
     */
    private function newBinding(array $row, array $allowed, string $what): ?Binding
    {
        $name = trim((string)($row['bind'] ?? ''));
        if ($name === '' || !isset($allowed[$name])) {
            $this->warnings[] = "$what names no field this form can supply; it was not saved.";
            return null;
        }
        return Binding::record($name);
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
    /**
     * The heading row's style, from the four controls the canvas offers.
     *
     * NOT style() with a renamed row. That method builds a whole Style out of
     * what was posted and lets everything absent fall to a default, which is
     * right for an element whose every property has a control. The heading has
     * four, so the other six have to be carried — a size the author never saw
     * must not be cleared by saving a border they did.
     *
     * Two rules from §13.30 apply here unchanged:
     *
     *   - A row with NO heading keys at all is a client that does not know
     *     about them, and the stored heading survives untouched. That is what
     *     lets the report editor, the receipt editor and any older page save a
     *     form without flattening its table headings.
     *   - Null is not an empty Style. The renderer reads
     *     `headerStyle ?? style`, so a table with no heading style draws its
     *     headings in the BODY's style; writing an empty one instead switches
     *     them to defaults, which is a visible change to a document nobody
     *     asked to change (§13.9).
     */
    private function headerStyleFrom(array $row, ?Style $fallback): ?Style
    {
        $keys = ['hdrSize', 'hdrBorderSize', 'hdrBorderColour', 'hdrBorderShow'];
        $sent = false;
        foreach ($keys as $key) if (array_key_exists($key, $row)) { $sent = true; break; }
        if (!$sent) return $fallback;

        $size   = trim((string)($row['hdrSize'] ?? ''));
        $border = trim((string)($row['hdrBorderSize'] ?? ''));
        $colour = array_key_exists('hdrBorderColour', $row)
            ? self::colourFrom((string)$row['hdrBorderColour'])
            : $fallback?->borderColour;
        $show   = !empty($row['hdrBorderShow']);

        // Nothing set, and nothing there before: still nothing. Anything else
        // and the heading has a style of its own from here on.
        if ($fallback === null && $size === '' && $border === '' && $colour === null && !$show) {
            return null;
        }

        return new Style(
            font:         $fallback?->font,
            size:         $size   !== '' && is_numeric($size)   ? (float)$size   : $fallback?->size,
            colour:       $fallback?->colour,
            align:        $fallback?->align,
            borderSize:   $border !== '' && is_numeric($border) ? (float)$border : $fallback?->borderSize,
            borderColour: $colour,
            borderShow:   $show,
            truncate:     (bool)$fallback?->truncate,
            bold:         (bool)$fallback?->bold,
            italic:       (bool)$fallback?->italic,
        );
    }

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

    // ── sub-structures: a table's columns, a block's parts ───────────────────

    /**
     * The submitted rows of a sub-structure, or null meaning "carry".
     *
     * The three answers are deliberately distinct, and the distinction is the
     * whole safety of §13.30. An ABSENT key is a post that says nothing about
     * the structure — an older page, a partial submission — and says nothing is
     * not the same as says none. A BLANK one is treated the same way for the
     * same reason: a blank is not an instruction, and the canvas writes `[]`
     * rather than `''` when an author really has deleted every row, so nothing
     * legitimate arrives here empty.
     *
     * Only a well-formed array replaces what is there. That is what makes the
     * worst case — a browser that fails to fill the field — a table that keeps
     * its columns rather than one that quietly loses them.
     *
     * @param array<string,mixed> $row
     * @return ?list<mixed>
     */
    private function subRows(array $row, string $key, string $what): ?array
    {
        if (!array_key_exists($key, $row)) return null;

        $value = $row[$key];
        if (is_array($value)) return array_values($value);

        $value = trim((string)$value);
        if ($value === '') return null;

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            $this->warnings[] = "$what submitted a $key list that could not be read;"
                              . ' the previous one was kept.';
            return null;
        }
        return array_values($decoded);
    }

    /**
     * A table's columns.
     *
     * @param list<Column>       $current
     * @param array<string,true> $allowed
     * @param array<string,mixed> $row
     * @return list<Column>
     */
    private function columns(array $row, array $current, array $allowed, string $what): array
    {
        $rows = $this->subRows($row, 'columns', $what);
        if ($rows === null) return $current;

        $kept    = self::keptBindings(array_map(fn(Column $c) => $c->bind, $current));
        $columns = [];

        foreach ($rows as $position => $entry) {
            $entry = (array)$entry;
            $where = "$what, column " . ((int)$position + 1);

            $bind = $this->subBinding($entry, $kept, $allowed, $where);
            if ($bind === null) continue;

            $width = trim((string)($entry['width'] ?? ''));
            $columns[] = new Column(
                bind:    $bind,
                heading: (string)($entry['heading'] ?? ''),
                width:   $width === '' || !is_numeric($width) ? null : (float)$width,
                align:   Align::tryFrom((string)($entry['align'] ?? '')),
                format:  self::formatFrom($entry),
                // Stored, and no control offers it. No renderer reads a form
                // table column's `total` — an Aggregate binding sums by FIELD
                // NAME across the line items and never consults the column list
                // (LayoutPdfRenderer::aggregate) — so a tick box for it would
                // be a control that does nothing. It is carried because the
                // format can express it and a definition is not ours to edit
                // down to what we happen to use.
                total:   !empty($entry['total']),
                style:   $this->subStyle($entry),
            );
        }
        return $columns;
    }

    /**
     * A field block's parts.
     *
     * @param list<BlockPart>    $current
     * @param array<string,true> $allowed
     * @param array<string,mixed> $row
     * @return list<BlockPart>
     */
    private function parts(array $row, array $current, array $allowed, string $what): array
    {
        $rows = $this->subRows($row, 'parts', $what);
        if ($rows === null) return $current;

        $kept  = self::keptBindings(array_map(fn(BlockPart $p) => $p->bind, $current));
        $parts = [];

        foreach ($rows as $position => $entry) {
            $entry = (array)$entry;
            $where = "$what, line " . ((int)$position + 1);

            $bind = $this->subBinding($entry, $kept, $allowed, $where);
            if ($bind === null) continue;

            $separator = (string)($entry['separator'] ?? '');
            if (!in_array($separator, self::SEPARATORS, true)) {
                // Not silently accepted: Formatter::separator() falls through
                // its `default` on a name it does not know and joins the next
                // part with NOTHING, which reads as an address block that has
                // lost its line breaks rather than as a setting that was
                // refused.
                $this->warnings[] = "$where asked to be joined by '$separator', which is not"
                                  . ' a way of joining two lines; nothing was used.';
                $separator = '';
            }

            $parts[] = new BlockPart(
                bind:      $bind,
                separator: $separator,
                format:    self::formatFrom($entry),
            );
        }
        return $parts;
    }

    /**
     * A binding inside a column or a part.
     *
     * **This is a security gate, not a validation nicety.** A Context binding
     * resolves through `FormRunner::contextFor()`, which does
     * `if (defined($name)) constant($name)` — every PHP constant in the
     * process, `configure.php`'s database password among them. An author who
     * could name one would have exactly the database-wide read primitive that
     * §1.1 records as PhreeForm's defect and that this whole layer exists to
     * remove.
     *
     * So an author never NAMES a non-record binding. They may keep one:
     * a company block's parts are Context bindings (PhreeForm's CBlk), and they
     * must survive being reordered, reformatted and rejoined.
     *
     * Hence the two tests, in this order:
     *
     *   1. **Was it already here?** Scope and name together, against the
     *      element's own bindings. This is the rule `font()` keeps and for the
     *      identical reason — *what is already in the definition survives a
     *      save* — and it is checked first so that an existing binding is
     *      handed back as ITSELF, with whatever `sumOf` it carries, rather than
     *      rebuilt into an equal-looking one.
     *
     *   2. **Otherwise, is it a record value this form supplies?** The ordinary
     *      palette check, the same one `binding()` applies to a whole element.
     *
     * Test 1 is not a convenience. A form's own address block binds to names
     * that are perfectly real and that the palette may not list — and dropping
     * them would empty the block on the first save of a form nobody edited,
     * which is exactly what happened to eight parts of the company block on
     * three shipped documents when this was written with only test 2.
     *
     * @param array<string,mixed>   $entry
     * @param array<string,Binding> $kept  bindings this element already has
     * @param array<string,true>    $allowed
     */
    private function subBinding(array $entry, array $kept, array $allowed, string $where): ?Binding
    {
        $name  = trim((string)($entry['bind'] ?? ''));
        $scope = (string)($entry['scope'] ?? 'record');

        if ($name === '') {
            // A cleared row, which is how the grid says "I am not finished with
            // this one" — dropped without comment, exactly as the report
            // editor drops a column whose field is blank.
            return null;
        }

        $kept_ = $kept[$scope . '|' . $name] ?? null;
        if ($kept_ !== null) return $kept_;

        if ($scope === 'record') {
            if (isset($allowed[$name])) return Binding::record($name);
            $this->warnings[] = "$where names '$name', which this form cannot supply;"
                              . ' it was not saved.';
            return null;
        }

        $this->warnings[] = "$where asked for '$name', which is not one of this element's"
                          . ' own values; it was not saved.';
        return null;
    }

    /**
     * The bindings an element already carries, by scope and name.
     *
     * @param list<Binding> $bindings
     * @return array<string,Binding>
     */
    private static function keptBindings(array $bindings): array
    {
        $kept = [];
        foreach ($bindings as $binding) {
            $kept[self::scopeName($binding->scope) . '|' . $binding->name] = $binding;
        }
        return $kept;
    }

    private static function scopeName(Scope $scope): string
    {
        return match ($scope) {
            Scope::Record    => 'record',
            Scope::Context   => 'context',
            Scope::Aggregate => 'aggregate',
        };
    }

    /**
     * A column's own style.
     *
     * A column that HAD a style keeps one even when every setting in it is
     * blank, which is not pedantry: `TemplateConverter::style()` returns a
     * Style unconditionally, so every column converted from a PhreeForm table
     * carries one, and most of them carry nothing but defaults. Letting those
     * collapse to null would rewrite the stored file of every shipped form the
     * first time anybody opened and saved it — a diff on 14 documents that
     * nobody asked for and that the byte-identity check (§13.24) would then
     * report as a difference between us and the master.
     *
     * The non-null fallback is what forces the Style back: `style()` returns
     * null only when there is nothing set AND nothing to fall back to.
     *
     * @param array<string,mixed> $entry
     */
    private function subStyle(array $entry): ?Style
    {
        $style = $entry['style'] ?? null;
        return is_array($style) ? $this->style($style, new Style()) : null;
    }

    /**
     * A format hint, with its options carried past.
     *
     * The grid edits the NAME, which is the whole of the hint in every
     * definition there is — nothing writes options and no renderer reads them.
     * They are carried anyway, because the format can express them and a field
     * that is invisible in the editor is exactly the kind that gets deleted by
     * a save nobody thought was destructive.
     *
     * @param array<string,mixed> $entry
     */
    private static function formatFrom(array $entry): ?Format
    {
        $name = trim((string)($entry['format'] ?? ''));
        $options = is_array($entry['formatOptions'] ?? null) ? $entry['formatOptions'] : [];
        if ($name === '' && !$options) return null;
        return new Format($name, $options);
    }

    /**
     * A page number's pattern.
     *
     * Absent means carry, for the reason subRows() gives. A blank one is
     * carried too — a page number that prints nothing is not something anybody
     * asks for on purpose, and the author who cleared the box by accident gets
     * their pattern back rather than an element that has silently stopped
     * printing.
     *
     * @param array<string,mixed> $row
     */
    private function pattern(array $row, string $current): string
    {
        if (!array_key_exists('pattern', $row)) return $current;
        $pattern = trim((string)$row['pattern']);
        return $pattern === '' ? $current : $pattern;
    }

    /**
     * A barcode's symbology, checked against the list that can be drawn.
     *
     * TCPDF throws on a type it does not know — from inside write1DBarcode(),
     * with the document part-built — so this is the same shape of gate as
     * `font()`, and for the same reason: an unknown name refused here can be
     * reported, and one discovered at render time cannot.
     *
     * @param array<string,mixed> $row
     */
    private function symbology(array $row, string $current, string $what): string
    {
        if (!array_key_exists('symbology', $row)) return $current;

        $name = trim((string)$row['symbology']);
        if ($name === '' || $name === $current) return $current;
        if (in_array($name, self::SYMBOLOGIES, true)) return $name;

        $this->warnings[] = "$what asked for a '$name' barcode, which cannot be drawn;"
                          . ' the previous kind was kept.';
        return $current;
    }

    // ── sub-structures, the other way ────────────────────────────────────────

    /**
     * A table's columns as the canvas's own value. @param list<Column> $columns
     */
    private static function columnsToValue(array $columns): string
    {
        $out = [];
        foreach ($columns as $column) {
            $out[] = array_merge(
                self::bindingToValue($column->bind),
                [
                    'heading' => $column->heading,
                    'width'   => $column->width === null ? '' : self::number($column->width),
                    'align'   => $column->align?->value ?? '',
                    'total'   => $column->total ? '1' : '',
                    'style'   => self::styleToValue($column->style),
                ],
                self::formatToValue($column->format),
            );
        }
        return self::encode($out);
    }

    /** A block's parts as the canvas's own value. @param list<BlockPart> $parts */
    private static function partsToValue(array $parts): string
    {
        $out = [];
        foreach ($parts as $part) {
            $out[] = array_merge(
                self::bindingToValue($part->bind),
                ['separator' => $part->separator],
                self::formatToValue($part->format),
            );
        }
        return self::encode($out);
    }

    /**
     * An empty list is `[]`, never `""`.
     *
     * subRows() reads a blank as "say nothing" so that a browser which fails to
     * fill the field cannot empty a table. That only holds if the deliberate
     * empty looks different from the accidental one, which is this line.
     *
     * @param list<array<string,mixed>> $rows
     */
    private static function encode(array $rows): string
    {
        return (string)json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string,mixed> */
    private static function bindingToValue(Binding $binding): array
    {
        return [
            'bind'  => $binding->name,
            'scope' => self::scopeName($binding->scope),
            // Which fields an aggregate adds up. No control edits it and
            // nothing in the shipped set puts an aggregate in a column or a
            // part — but subBinding() hands back the ORIGINAL Binding for a
            // non-record scope, so this rides along for the reader rather than
            // for the round trip.
            'sumOf' => $binding->sumOf,
        ];
    }

    /** @return array<string,mixed> */
    private static function formatToValue(?Format $format): array
    {
        return [
            'format'        => $format?->name ?? '',
            'formatOptions' => $format?->options ?? [],
        ];
    }

    /**
     * A column's style in the same flat shape the inspector uses for an
     * element's, so `style()` can read it back with no second vocabulary.
     *
     * @return ?array<string,string>
     */
    private static function styleToValue(?Style $style): ?array
    {
        if ($style === null) return null;
        return [
            'font'         => (string)($style->font ?? ''),
            'size'         => $style->size === null ? '' : self::number($style->size),
            'colour'       => self::colourText($style->colour),
            'borderColour' => self::colourText($style->borderColour),
            'align'        => $style->align?->value ?? '',
            'borderSize'   => $style->borderSize === null ? '' : self::number($style->borderSize),
            'borderShow'   => $style->borderShow ? '1' : '',
            'truncate'     => $style->truncate   ? '1' : '',
            'bold'         => $style->bold       ? '1' : '',
            'italic'       => $style->italic     ? '1' : '',
        ];
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
