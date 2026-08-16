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
//  Path: /modules/forms/classes/legacy/template_converter.php
//
/**
 * Forms — converts a PhreeForm form definition into a Forms Template.
 *
 * The first half of the conversion utility (docs/forms-design.md §8.2, decision
 * B): it maps the stored geometry, so existing forms render through the new
 * engine without being redrawn by hand. Datasets and filters are the other half
 * and are not handled here.
 *
 * Conversion is a MAPPING problem, not a translation one — the legacy fieldlist
 * is already a positioned-element list. Three things about that list are traps,
 * and each is why this class exists rather than a few inline array reads:
 *
 * 1. `processing` means two different things depending on the element.
 *    On Data/CDta and on table columns it is the FORMAT hint (posted_cur, date).
 *    On TBlk/CBlk boxfields it is the SEPARATOR (nl, del-nl, com-sp), and the
 *    format hint lives in `formatting` instead. Reading it uniformly silently
 *    produces address blocks with no line breaks and unformatted currency.
 *
 * 2. Block boxfield styling is junk data. In real definitions the per-boxfield
 *    font/size/align/color of a TBlk hold one character each of the parent's
 *    font name — "helvetica" spread across eight boxfields as h,e,l,v,e,t,i,c.
 *    The legacy renderer never read them (FormText uses the parent's style), so
 *    neither do we. Reading them yields a font called "h".
 *
 * 3. `display` encodes visibility as 0/1/2 = all pages / first page only / last
 *    page only, and `Ttl` is implicitly last-page-only with a "Continued"
 *    placeholder before then — which is Visibility::ContinuedOnOverflow.
 *
 * Anything unrecognised is reported through warnings() rather than dropped
 * silently, so a conversion run can be reviewed before it is trusted.
 */

namespace FreeBooks\Forms\Legacy;

use FreeBooks\Forms\Catalog\Dataset;
use FreeBooks\Forms\Document\{
    Align, Barcode, Binding, BlockPart, Box, Colour, Column, Element, Field,
    FieldBlock, Image, Line, PageNumber, PageSetup, Rect, StaticText, Style,
    Table, Template, Visibility
};

final class TemplateConverter
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * Processing hints the PROVIDER resolves, and the suffix of the field it
     * supplies the answer in (§4.3.1).
     *
     * These look like formatting but are database lookups — rep_id reads the
     * `users` table — so the renderer refuses them and a query-driven provider
     * emits a resolved companion field instead. Converting a form means
     * re-pointing the binding at that companion and dropping the hint;
     * otherwise the Buyer box on a Purchase Order prints "1".
     *
     * Must stay in step with Formatter::RESOLUTION_HINTS, which
     * verify_forms_catalog.php asserts.
     */
    public const PROVIDER_RESOLVED = [
        'rep_id'    => '_name',
        'branch'    => '_name',
        'avg_cost'  => '_avg_cost',
        'avg_total' => '_avg_total',
        'ordr_qty'  => '_ordered',
    ];

    /** @var array<string,string> legacy "table.column" => dataset field key */
    private array $fieldMap = [];

    /** @var array<string,true> legacy names no dataset field covers */
    private array $unmapped = [];

    /**
     * @param object   $report  a legacy definition as returned by get_report_details()
     * @param ?Dataset $dataset when given, legacy `table.column` names are
     *   rewritten to that dataset's field keys. Names it cannot cover are
     *   reported by unmappedFields() rather than silently emitted, because a
     *   binding that matches nothing renders as blank — the failure mode §8.2
     *   insists the converter must not have.
     */
    public function convert(object $report, ?Dataset $dataset = null): Template
    {
        $this->warnings = [];
        $this->unmapped = [];
        $this->fieldMap = $dataset === null ? [] : self::mapFor($dataset);

        $elements = [];
        foreach ((array)($report->fieldlist ?? []) as $index => $field) {
            $element = $this->convertField($field, $index);
            if ($element !== null) $elements[] = $element;
        }

        return new Template($elements);
    }

    /**
     * Legacy `table.column` => field key, for one dataset.
     *
     * @return array<string,string>
     */
    public static function mapFor(Dataset $dataset): array
    {
        $map = [];
        foreach ($dataset->fields as $key => $field) {
            if ($field->table !== null && $field->column !== null) {
                $map[strtolower($field->table . '.' . $field->column)] = $key;
            }
            // Whitespace in a stored expression is not meaningful, and the
            // definitions are inconsistent about it ("debit_amount /qty").
            foreach ($field->legacyNames as $legacy) {
                $map[self::normalise($legacy)] = $key;
            }
        }
        return $map;
    }

    /**
     * Re-point a binding whose hint is really a provider-side lookup.
     *
     * @return array{0: string, 1: string} the binding name and remaining hint
     */
    private function applyProviderResolution(string $bindName, string $hint, bool $isContext): array
    {
        if ($isContext || $hint === '' || !isset(self::PROVIDER_RESOLVED[$hint])) {
            return [$bindName, $hint];
        }
        // The hint is consumed: the provider has already done the lookup, so
        // the value arriving is a name and needs no further processing.
        return [$bindName . self::PROVIDER_RESOLVED[$hint], ''];
    }

    /** Compare stored SQL fragments ignoring case and whitespace. */
    private static function normalise(string $name): string
    {
        return strtolower(preg_replace('/\s+/', '', $name));
    }

    /**
     * Legacy field names that no dataset field covers.
     *
     * Expect entries here for a customised install: the live Purchase Order
     * selects `IF(vendor_sku <> '', vendor_sku, SUBSTRING_INDEX(...))`, which is
     * raw SQL and therefore belongs in a shipped dataset as a named computed
     * field (§3.2) — not in a form definition.
     *
     * @return list<string>
     */
    public function unmappedFields(): array
    {
        return array_keys($this->unmapped);
    }

    /**
     * Rewrite one legacy field name to its dataset key.
     *
     * Names are left untouched when no dataset was supplied, so a provider that
     * addresses values by their legacy name (EntryDocumentProvider) is
     * unaffected.
     */
    private function mapName(string $name): string
    {
        if ($name === '' || !$this->fieldMap) return $name;

        $key = $this->fieldMap[strtolower($name)] ?? $this->fieldMap[self::normalise($name)] ?? null;
        if ($key !== null) return $key;

        // Already a dataset key, or a Context constant: not a failure.
        if (in_array($name, $this->fieldMap, true))     return $name;
        if (!str_contains($name, '.'))                  return $name;

        $this->unmapped[$name] = true;
        return $name;
    }

    /** Page size, orientation and margins from the legacy <page> block. */
    public function convertPageSetup(object $report): PageSetup
    {
        $page = $report->page ?? null;
        // Stored as "A4:210:297" — name, width, height.
        $size = explode(':', (string)($page->size ?? 'A4'));

        return new PageSetup(
            size:         $size[0] !== '' ? $size[0] : 'A4',
            orientation:  (string)($page->orientation ?? 'P'),
            marginTop:    (float)($page->margin->top    ?? 10),
            marginBottom: (float)($page->margin->bottom ?? 10),
            marginLeft:   (float)($page->margin->left   ?? 10),
            marginRight:  (float)($page->margin->right  ?? 10),
        );
    }

    /** @return list<string> anything the conversion could not map faithfully */
    public function warnings(): array
    {
        return $this->warnings;
    }

    // ── per-element ──────────────────────────────────────────────────────────

    private function convertField(object $f, int|string $index): ?Element
    {
        $type    = $this->scalar($f->type ?? null);
        $box     = $this->box($f);
        $style   = $this->style($f);
        $visible = $this->visibility($f, $type);
        $label   = $this->scalar($f->description ?? null) ?: null;

        switch ($type) {
            case 'Text':
                return new StaticText($this->scalar($f->text ?? null), $box, $style, $visible, $label);

            case 'Data':
            case 'CDta':
                $boxfield = $this->firstBoxfield($f);
                if ($boxfield === null) {
                    $this->warnings[] = "[$index] $type '$label' has no boxfield; skipped.";
                    return null;
                }
                $isContext = $type === 'CDta';
                $rawName   = $this->scalar($boxfield->fieldname ?? null);
                $bindName  = $isContext ? $rawName : $this->mapName($rawName);
                // On Data/CDta, `processing` IS the format hint (trap 1).
                $hint      = $this->scalar($boxfield->processing ?? null);
                [$bindName, $hint] = $this->applyProviderResolution($bindName, $hint, $isContext);
                return new Field(
                    bind:    $isContext ? Binding::context($bindName) : Binding::record($bindName),
                    box:     $box,
                    format:  $this->format($hint),
                    style:   $style,
                    visible: $visible,
                    label:   $label,
                );

            case 'TBlk':
            case 'CBlk':
                $parts = [];
                foreach ($this->boxfields($f) as $boxfield) {
                    $name = $this->scalar($boxfield->fieldname ?? null);
                    if ($name === '') continue;
                    $parts[] = new BlockPart(
                        bind: $type === 'CBlk' ? Binding::context($name) : Binding::record($this->mapName($name)),
                        // On blocks, `processing` is the SEPARATOR and the format
                        // hint lives in `formatting` (trap 1).
                        separator: $this->scalar($boxfield->processing ?? null),
                        format:    $this->format($this->scalar($boxfield->formatting ?? null)),
                    );
                }
                if (!$parts) {
                    $this->warnings[] = "[$index] $type '$label' has no usable boxfields; skipped.";
                    return null;
                }
                // Block boxfield styling is junk (trap 2) — the parent style applies.
                return new FieldBlock($parts, $box, $style, $visible, $label);

            case 'Tbl':
                $columns = [];
                foreach ($this->boxfields($f) as $boxfield) {
                    $columns[] = new Column(
                        bind:    Binding::record($this->mapName($this->scalar($boxfield->fieldname ?? null))),
                        heading: $this->scalar($boxfield->description ?? null),
                        width:   $this->number($boxfield->width ?? null),
                        align:   $this->align($this->scalar($boxfield->align ?? null)),
                        format:  $this->format($this->scalar($boxfield->processing ?? null)),
                        // Unlike TBlk, a Tbl boxfield's styling is real (trap 2).
                        style:   $this->style($boxfield),
                    );
                }
                if (!$columns) {
                    $this->warnings[] = "[$index] Tbl '$label' has no columns; skipped.";
                    return null;
                }
                return new Table(
                    source:      'items',
                    columns:     $columns,
                    box:         $box,
                    headerStyle: $this->headerStyle($f),
                    style:       $style,
                    visible:     $visible,
                    label:       $label,
                );

            case 'Ttl':
                $boxfield = $this->firstBoxfield($f);
                if ($boxfield === null) {
                    $this->warnings[] = "[$index] Ttl '$label' has no boxfield; skipped.";
                    return null;
                }
                // A Ttl sums every boxfield it names, not just the first.
                $summed = [];
                foreach ($this->boxfields($f) as $bf) {
                    $n = $this->scalar($bf->fieldname ?? null);
                    if ($n !== '') $summed[] = $this->mapName($n);
                }
                return new Field(
                    bind:    Binding::aggregate($summed[0] ?? '', $summed),
                    box:     $box,
                    format:  $this->format($this->scalar($boxfield->processing ?? null)),
                    style:   $style,
                    // A total shows "Continued" until the final page (trap 3).
                    visible: Visibility::ContinuedOnOverflow,
                    label:   $label,
                );

            case 'Img':
                return new Image($this->scalar($f->filename ?? null), $box, $style, $visible, $label);

            case 'ImgLink':
                $boxfield = $this->firstBoxfield($f);
                if ($boxfield === null) {
                    $this->warnings[] = "[$index] ImgLink '$label' has no boxfield; skipped.";
                    return null;
                }
                // `text` is the FOLDER the linked file lives in, relative to the
                // company's files — "inventory/images/" on the Work Order — and
                // the boxfield supplies the filename. FormImgLink() concatenates
                // the two; dropping the folder leaves a path that resolves to
                // nothing and an image that never draws.
                return new Image(Binding::record($this->scalar($boxfield->fieldname ?? null)),
                    $box, $style, $visible, $label, $this->scalar($f->text ?? null));

            case 'Line':
                return $this->convertLine($f, $visible, $label);

            case 'Rect':
                return new Rect($box, $style, $visible, $label);

            case 'PgNum':
                return new PageNumber($box, '{page} {of} {pages}', $style, $visible, $label);

            case 'BarCode':
                $boxfield = $this->firstBoxfield($f);
                if ($boxfield === null) {
                    $this->warnings[] = "[$index] BarCode '$label' has no boxfield; skipped.";
                    return null;
                }
                return new Barcode(Binding::record($this->scalar($boxfield->fieldname ?? null)), $box, 'C128', $style, $visible, $label);

            case 'TDup':
                // A second copy of the table at new coordinates. The model drops
                // the concept (§4.3) — it is a second Table on the same source —
                // but only one definition in the whole library uses it, so it is
                // reported rather than silently converted.
                $this->warnings[] = "[$index] TDup '$label' needs a second Table on the same source; not converted.";
                return null;

            case 'LtrTpl':
            case 'LtrData':
                // letter_generator.php is never loaded anywhere in trunk/ (§1.3).
                $this->warnings[] = "[$index] letter element '$type' is vestigial; skipped.";
                return null;

            default:
                $this->warnings[] = "[$index] unknown element type '$type' ('$label'); skipped.";
                return null;
        }
    }

    /**
     * A rule. Lines do not use width/height at all.
     *
     * The geometry is `linetype` plus `length`: H extends along x, V along y,
     * and C ("custom") ends at an explicit endabscissa/endordinate. The Box
     * carries that as an offset from the start point, which is what the
     * renderer draws to.
     *
     * The line WEIGHT comes from `size`, not `bordersize` — reproducing a
     * legacy quirk rather than inventing one. The designer writes bordersize
     * (this element has bordersize=1 and no size at all), but FormLine() reads
     * $Params->size, so in practice every line in every stored form is drawn at
     * width 0, which TCPDF renders as its thinnest possible rule. Honouring
     * bordersize here would silently thicken every rule on every converted
     * form, so the quirk is kept and flagged instead.
     */
    private function convertLine(object $f, Visibility $visible, ?string $label): Line
    {
        $x        = (float)$this->scalar($f->abscissa ?? null);
        $y        = (float)$this->scalar($f->ordinate ?? null);
        $length   = (float)$this->scalar($f->length ?? null);
        $lineType = $this->scalar($f->linetype ?? null) ?: 'H';

        [$dx, $dy] = match ($lineType) {
            'V'     => [0.0, $length],
            'C'     => [(float)$this->scalar($f->endabscissa ?? null) - $x,
                        (float)$this->scalar($f->endordinate ?? null) - $y],
            default => [$length, 0.0],       // 'H'
        };

        $style = new Style(
            colour:       isset($f->bordercolor) ? Colour::fromLegacy($this->scalar($f->bordercolor)) : null,
            borderSize:   $this->number($f->size ?? null) ?? 0.0,
            borderColour: isset($f->bordercolor) ? Colour::fromLegacy($this->scalar($f->bordercolor)) : null,
        );

        return new Line(new Box($x, $y, $dx, $dy), $lineType, $style, $visible, $label);
    }

    // ── shared attributes ────────────────────────────────────────────────────

    private function box(object $f): Box
    {
        return new Box(
            x:      (float)$this->scalar($f->abscissa ?? null),
            y:      (float)$this->scalar($f->ordinate ?? null),
            width:  $this->number($f->width  ?? null),
            height: $this->number($f->height ?? null),
        );
    }

    private function style(object $f): Style
    {
        return new Style(
            font:         $this->scalar($f->font ?? null) ?: null,
            size:         $this->number($f->size ?? null),
            colour:       isset($f->color) ? Colour::fromLegacy($this->scalar($f->color)) : null,
            align:        $this->align($this->scalar($f->align ?? null)),
            borderSize:   $this->number($f->bordersize ?? null),
            borderColour: isset($f->bordercolor) ? Colour::fromLegacy($this->scalar($f->bordercolor)) : null,
            borderShow:   $this->scalar($f->bordershow ?? null) === '1',
        );
    }

    /** Tables carry a parallel h* set of attributes for the heading row. */
    private function headerStyle(object $f): Style
    {
        return new Style(
            font:         $this->scalar($f->hfont ?? null) ?: null,
            size:         $this->number($f->hsize ?? null),
            colour:       isset($f->hcolor) ? Colour::fromLegacy($this->scalar($f->hcolor)) : null,
            align:        $this->align($this->scalar($f->halign ?? null)),
            borderSize:   $this->number($f->hbordersize ?? null),
            borderColour: isset($f->hbordercolor) ? Colour::fromLegacy($this->scalar($f->hbordercolor)) : null,
            borderShow:   $this->scalar($f->hbordershow ?? null) === '1',
        );
    }

    /**
     * 0/absent = every page, 1 = first page only, 2 = last page only (trap 3).
     *
     * ONLY `Data` honours the flag. The legacy Header() draws Text, TBlk, CBlk,
     * CDta, Img, Line, Rect, PgNum and BarCode unconditionally on every page and
     * applies the display test solely inside `case "Data":`. Definitions
     * nonetheless carry display=2 on decorative elements — pf_91 puts it on the
     * boxes around the totals — so honouring it everywhere silently drops those
     * boxes from every page but the last.
     */
    private function visibility(object $f, string $type): Visibility
    {
        if ($type !== 'Data') return Visibility::AllPages;

        return match ($this->scalar($f->display ?? null)) {
            '1'     => Visibility::FirstPageOnly,
            '2'     => Visibility::LastPageOnly,
            default => Visibility::AllPages,
        };
    }

    private function align(string $legacy): ?Align
    {
        // 'A' is the designer's "auto", which resolves to the column's own
        // alignment; it is not an alignment in its own right.
        return match ($legacy) {
            'L'     => Align::Left,
            'C'     => Align::Center,
            'R'     => Align::Right,
            default => null,
        };
    }

    private function format(string $hint): ?\FreeBooks\Forms\Document\Format
    {
        return $hint === '' ? null : new \FreeBooks\Forms\Document\Format($hint);
    }

    private function number(mixed $value): ?float
    {
        $value = $this->scalar($value);
        return $value === '' ? null : (float)$value;
    }

    /**
     * Collapse a possibly-repeated XML tag to a single value.
     *
     * A Tbl element repeats <font>/<size>/<align>/<color> once per column, so
     * the parser hands back an array where other elements give a string. The
     * legacy renderer read the per-column boxfield values instead and ignored
     * these, so taking the first is equivalent and keeps a stray array out of
     * SetFont(), which otherwise fails with "font definition file: array".
     */
    private function scalar(mixed $value): string
    {
        if ($value === null) return '';
        if (is_array($value))  $value = reset($value);
        if (is_object($value) && !method_exists($value, '__toString')) return '';
        return (string)$value;
    }

    /** @return list<object> */
    private function boxfields(object $f): array
    {
        $boxfield = $f->boxfield ?? null;
        if ($boxfield === null)   return [];
        if (is_object($boxfield)) return [$boxfield];
        return array_values((array)$boxfield);
    }

    private function firstBoxfield(object $f): ?object
    {
        $boxfields = $this->boxfields($f);
        return $boxfields[0] ?? null;
    }
}
