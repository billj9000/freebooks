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
//  Path: /modules/forms/classes/definition/codec.php
//
/**
 * Forms — the native definition format, read and written. See §13.3.
 *
 * JSON, pretty-printed, one file per definition, in the same
 * `my_files/{co}/phreeform/pf_<id>` slot the legacy XML occupies. Choosing the
 * same slot is deliberate: the id is the catalog's, the DB row already carries
 * the title, group and type, and inventing a second location would mean two
 * places to keep in step during a migration that has to be atomic.
 *
 * Three rules the encoder keeps, because this format is edited by a designer
 * and read by a human diffing two versions of a form:
 *
 * 1. **Defaults are omitted.** A `Style` with nothing set writes nothing at
 *    all. Elements average 42 per form, and a file where every one of them
 *    spells out eight nulls is a file nobody reads.
 * 2. **Key order is fixed**, so re-saving a definition nobody edited produces
 *    a byte-identical file and an empty diff. verify_forms_native.php asserts
 *    that as `encode(decode(encode(d))) === encode(d)`.
 * 3. **Decoding is strict.** An element type this does not know is an
 *    exception, never a skip. The whole point of §8.2 is that a definition
 *    which loses an element renders one field short of what its author drew,
 *    and says nothing.
 *
 * `version` is the format's, not the definition's. It exists so a future
 * reader can refuse what it cannot understand rather than half-read it.
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Catalog\{FieldExpression, Filter, FilterOperator, Sort, SortDirection};
use FreeBooks\Forms\Document\{
    Align, Barcode, Binding, BlockPart, Box, Colour, Column, Element, Field, FieldBlock, Format,
    Image, Line, PageNumber, PageSetup, Rect, ReportColumn, ReportStyle, Scope, StaticText, Style,
    SerialCell, SerialField, SerialTemplate, Table, Template, Visibility
};

final class DefinitionCodec
{
    public const FORMAT  = 'freebooks/forms-definition';
    public const VERSION = 1;

    /** Element class => the tag written to the file, and back. */
    private const ELEMENTS = [
        'text'    => StaticText::class,
        'field'   => Field::class,
        'block'   => FieldBlock::class,
        'table'   => Table::class,
        'line'    => Line::class,
        'rect'    => Rect::class,
        'image'   => Image::class,
        'pagenum' => PageNumber::class,
        'barcode' => Barcode::class,
    ];

    private const VISIBILITY = [
        'all'       => Visibility::AllPages,
        'first'     => Visibility::FirstPageOnly,
        'last'      => Visibility::LastPageOnly,
        'continued' => Visibility::ContinuedOnOverflow,
    ];

    private const SCOPES = [
        'record'    => Scope::Record,
        'context'   => Scope::Context,
        'aggregate' => Scope::Aggregate,
    ];

    // ── encode ───────────────────────────────────────────────────────────────

    public function encode(Definition $definition): string
    {
        $json = json_encode($this->definitionToArray($definition),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new DefinitionException('This definition could not be encoded: ' . json_last_error_msg());
        }
        return $json . "\n";
    }

    /** @return array<string,mixed> */
    public function definitionToArray(Definition $definition): array
    {
        $out = [
            'format'  => self::FORMAT,
            'version' => self::VERSION,
            'type'    => $definition->type,
            'title'   => $definition->title,
        ];
        $this->put($out, 'description', $definition->description, '');
        $this->put($out, 'group',       $definition->group,       '');
        $this->put($out, 'filenamePrefix', $definition->filenamePrefix, '');
        $this->put($out, 'emailMessage',   $definition->emailMessage,   '');
        $this->put($out, 'provider',    $definition->provider,    null);
        $this->put($out, 'dataset',     $definition->datasetId,   null);

        $out['page'] = $this->pageToArray($definition->page);

        $dates = [];
        $this->put($dates, 'default', $definition->dateDefault, '');
        $this->put($dates, 'field',   $definition->dateField,   null);
        $this->put($dates, 'period',  $definition->period,      0);
        if ($dates) $out['dates'] = $dates;

        if ($definition->filters) {
            $out['filters'] = array_map([$this, 'filterToArray'], $definition->filters);
        }
        if ($definition->sorts) {
            $out['sorts'] = array_map(
                fn(Sort $s) => ['field' => $s->fieldKey, 'direction' => $s->direction->value],
                $definition->sorts);
        }

        if ($definition->isReport()) {
            $report = ['columns' => array_map([$this, 'reportColumnToArray'], $definition->columns)];
            $this->put($report, 'groupBy',     $definition->groupBy, null);
            $this->put($report, 'groupFormat', $this->formatToValue($definition->groupFormat), null);
            $this->put($report, 'totalsOnly',  $definition->totalsOnly, false);
            $this->put($report, 'resolved',    $definition->resolvedColumns, []);
            $this->put($report, 'style',       $this->reportStyleToArray($definition->style), []);
            $out['report'] = $report;
        } else {
            $form = ['elements' => array_map([$this, 'elementToArray'],
                $definition->template?->elements ?? [])];
            $this->put($form, 'breakField',  $definition->breakField,  'id');
            $this->put($form, 'recordTable', $definition->recordTable, '');
            $this->put($form, 'recordKey',   $definition->recordKey,   'id');
            // Omitted by put()'s default for every form that counts nothing,
            // which is all but two of the shipped set — so adding the key
            // changes no stored byte of the rest (§13.45).
            $this->put($form, 'printedField', $definition->printedField, '');
            // Omitted for every ordinary form by put()'s default, so adding it
            // does not change a single stored byte of the 61 that are not
            // receipts — which the byte-identity check depends on.
            $this->put($form, 'serial',      $definition->serial,      false);
            // A receipt's layout replaces the element list rather than joining
            // it: the two describe the same document in incompatible terms, and
            // a file carrying both would not say which one prints.
            if ($definition->serialTemplate !== null) {
                unset($form['elements']);
                $form['serialLayout'] = [
                    'width'  => $definition->serialTemplate->width,
                    'fields' => array_map([$this, 'serialFieldToArray'],
                                          $definition->serialTemplate->fields),
                ];
            }
            $out['form'] = $form;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function pageToArray(PageSetup $page): array
    {
        return [
            'size'        => $page->size,
            'orientation' => $page->orientation,
            'margins'     => [
                'top'    => $page->marginTop,
                'bottom' => $page->marginBottom,
                'left'   => $page->marginLeft,
                'right'  => $page->marginRight,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function filterToArray(Filter $filter): array
    {
        $out = ['field' => $filter->fieldKey, 'operator' => $filter->operator->value];
        if ($filter->expression !== null) {
            $out['expression'] = $filter->expression->tokens;
        } elseif ($filter->values) {
            $out['values'] = array_values($filter->values);
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function reportColumnToArray(ReportColumn $column): array
    {
        $out = ['key' => $column->key, 'heading' => $column->heading];
        $this->put($out, 'width',     $column->width, null);
        $this->put($out, 'align',     $column->align?->value, null);
        $this->put($out, 'format',    $this->formatToValue($column->format), null);
        $this->put($out, 'total',     $column->total, false);
        $this->put($out, 'pageBreak', $column->pageBreak, false);
        return $out;
    }

    /** @return array<string,mixed> */
    private function reportStyleToArray(ReportStyle $style): array
    {
        $out = [];
        foreach (['heading', 'title1', 'title2', 'filter', 'data', 'totals'] as $band) {
            $this->put($out, $band, $this->styleToArray($style->$band), null);
        }
        $this->put($out, 'showHeading',    $style->showHeading, true);
        $this->put($out, 'showTitle1',     $style->showTitle1,  true);
        $this->put($out, 'showTitle2',     $style->showTitle2,  true);
        $this->put($out, 'title1Text',     $style->title1Text,  null);
        $this->put($out, 'title2Text',     $style->title2Text,  null);
        $this->put($out, 'truncate',       $style->truncate,       false);
        $this->put($out, 'groupPageBreak', $style->groupPageBreak, false);
        return $out;
    }

    /**
     * @return array<string,mixed>|\stdClass|null null only when there is NO style
     *
     * The empty object is not a tidiness question. `Table` resolves its heading
     * row as `$element->headerStyle ?? $element->style`, so a headerStyle that
     * specifies nothing still WINS over the element's own style, while a null
     * one falls back to it. Collapsing the two prints the heading row of every
     * converted table in a different font from the one PhreeForm used — which
     * is exactly what the byte comparison in verify_forms_native.php caught,
     * and what no amount of reading the object graph would have shown.
     */
    private function styleToArray(?Style $style): array|\stdClass|null
    {
        if ($style === null) return null;
        $out = [];
        $this->put($out, 'font',         $style->font, null);
        $this->put($out, 'size',         $style->size, null);
        $this->put($out, 'colour',       $this->colourToArray($style->colour), null);
        $this->put($out, 'align',        $style->align?->value, null);
        $this->put($out, 'borderSize',   $style->borderSize, null);
        $this->put($out, 'borderColour', $this->colourToArray($style->borderColour), null);
        $this->put($out, 'borderShow',   $style->borderShow, false);
        $this->put($out, 'truncate',     $style->truncate,   false);
        $this->put($out, 'bold',         $style->bold,       false);
        $this->put($out, 'italic',       $style->italic,     false);
        return $out ?: new \stdClass();
    }

    /** @return list<int>|null */
    private function colourToArray(?Colour $colour): ?array
    {
        return $colour === null ? null : [$colour->r, $colour->g, $colour->b];
    }

    /** A hint with no options is written as the bare string it almost always is. */
    private function formatToValue(?Format $format): string|array|null
    {
        if ($format === null) return null;
        return $format->options ? ['name' => $format->name, 'options' => $format->options]
                                : $format->name;
    }

    /** @return array<string,mixed> */
    private function bindingToArray(Binding $binding): array
    {
        $out = ['name' => $binding->name];
        $this->put($out, 'scope', $this->keyFor(self::SCOPES, $binding->scope), 'record');
        $this->put($out, 'sumOf', $binding->sumOf, []);
        return $out;
    }

    /** @return array<string,mixed> */
    private function elementToArray(Element $element): array
    {
        $tag = $this->keyFor(self::ELEMENTS, $element::class);
        if ($tag === null) {
            throw new DefinitionException('Cannot encode element type ' . $element::class . '.');
        }

        $out = ['type' => $tag, 'box' => $this->boxToArray($element->box)];

        // Per-type payload, before the shared trailer, so a reader meets the
        // element's own content next to its type rather than after its styling.
        switch (true) {
            case $element instanceof StaticText:
                $out['text'] = $element->text;
                break;

            case $element instanceof Field:
                $out['bind'] = $this->bindingToArray($element->bind);
                $this->put($out, 'format', $this->formatToValue($element->format), null);
                break;

            case $element instanceof FieldBlock:
                $out['parts'] = array_map(function (BlockPart $part): array {
                    $p = ['bind' => $this->bindingToArray($part->bind)];
                    $this->put($p, 'separator', $part->separator, '');
                    $this->put($p, 'format',    $this->formatToValue($part->format), null);
                    return $p;
                }, $element->parts);
                break;

            case $element instanceof Table:
                $out['source']  = $element->source;
                $out['columns'] = array_map(function (Column $column): array {
                    $c = ['bind' => $this->bindingToArray($column->bind), 'heading' => $column->heading];
                    $this->put($c, 'width',  $column->width, null);
                    $this->put($c, 'align',  $column->align?->value, null);
                    $this->put($c, 'format', $this->formatToValue($column->format), null);
                    $this->put($c, 'total',  $column->total, false);
                    $this->put($c, 'style',  $this->styleToArray($column->style), null);
                    return $c;
                }, $element->columns);
                $this->put($out, 'headerStyle', $this->styleToArray($element->headerStyle), null);
                break;

            case $element instanceof Line:
                $this->put($out, 'lineType', $element->lineType, 'solid');
                break;

            case $element instanceof Image:
                $out['src'] = $element->src instanceof Binding
                    ? ['bind' => $this->bindingToArray($element->src)]
                    : $element->src;
                $this->put($out, 'pathPrefix', $element->pathPrefix, '');
                break;

            case $element instanceof PageNumber:
                $this->put($out, 'pattern', $element->pattern, '{page} / {pages}');
                break;

            case $element instanceof Barcode:
                $out['bind'] = $this->bindingToArray($element->bind);
                $this->put($out, 'symbology', $element->symbology, 'C128');
                break;
        }

        $this->put($out, 'style',   $this->styleToArray($element->style), null);
        $this->put($out, 'visible', $this->keyFor(self::VISIBILITY, $element->visible), 'all');
        $this->put($out, 'label',   $element->label, null);
        return $out;
    }

    /** @return array<string,float> */
    private function boxToArray(Box $box): array
    {
        $out = ['x' => $box->x, 'y' => $box->y];
        $this->put($out, 'width',  $box->width,  null);
        $this->put($out, 'height', $box->height, null);
        return $out;
    }

    // ── decode ───────────────────────────────────────────────────────────────

    public function decode(string $json, string $id = ''): Definition
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new DefinitionException('This is not a Forms definition: ' . json_last_error_msg());
        }
        try {
            return $this->definitionFromArray($data, $id);
        } catch (\FreeBooks\Forms\Catalog\CatalogException $e) {
            // The catalog validates what it is handed — a filter expression's
            // field keys are checked as identifiers, which is the guard that
            // keeps this format from becoming a way to smuggle SQL back in.
            // A caller reading a file should not have to know that, so its
            // refusal is reported as what it is: an unreadable definition.
            throw new DefinitionException("Definition 'pf_$id' is not valid: " . $e->getMessage(), 0, $e);
        }
    }

    /** @param array<string,mixed> $data */
    public function definitionFromArray(array $data, string $id = ''): Definition
    {
        if (($data['format'] ?? '') !== self::FORMAT) {
            throw new DefinitionException("This file is not a Forms definition (format '"
                . (string)($data['format'] ?? '') . "').");
        }
        // Refused rather than half-read: a definition from a later FreeBooks
        // carries elements this one has no code for, and rendering it without
        // them is a document that is wrong rather than one that is missing.
        $version = (int)($data['version'] ?? 0);
        if ($version > self::VERSION) {
            throw new DefinitionException("This definition is version $version; this copy of Forms"
                . ' reads version ' . self::VERSION . ' and older.');
        }

        $type = (string)($data['type'] ?? Definition::REPORT);
        if ($type !== Definition::REPORT && $type !== Definition::FORM) {
            throw new DefinitionException("Unknown definition type '$type'.");
        }

        $dates  = (array)($data['dates']  ?? []);
        $report = (array)($data['report'] ?? []);
        $form   = (array)($data['form']   ?? []);

        return new Definition(
            id:          $id,
            type:        $type,
            title:       (string)($data['title']       ?? ''),
            description: (string)($data['description'] ?? ''),
            group:       (string)($data['group']       ?? ''),
            filenamePrefix: (string)($data['filenamePrefix'] ?? ''),
            emailMessage:   (string)($data['emailMessage']   ?? ''),
            provider:    isset($data['provider']) ? (string)$data['provider']  : null,
            datasetId:   isset($data['dataset'])  ? (string)$data['dataset']   : null,
            page:        $this->pageFromArray((array)($data['page'] ?? [])),

            dateDefault: (string)($dates['default'] ?? ''),
            dateField:   isset($dates['field']) ? (string)$dates['field'] : null,
            period:      (int)($dates['period'] ?? 0),

            filters:     array_map([$this, 'filterFromArray'], (array)($data['filters'] ?? [])),
            sorts:       array_map([$this, 'sortFromArray'],   (array)($data['sorts']   ?? [])),

            columns:         array_map([$this, 'reportColumnFromArray'], (array)($report['columns'] ?? [])),
            groupBy:         isset($report['groupBy']) ? (string)$report['groupBy'] : null,
            groupFormat:     $this->formatFromValue($report['groupFormat'] ?? null),
            totalsOnly:      (bool)($report['totalsOnly'] ?? false),
            resolvedColumns: array_map('strval', (array)($report['resolved'] ?? [])),
            style:           $this->reportStyleFromArray((array)($report['style'] ?? [])),

            // Null for a receipt, not an empty Template. Building one anyway
            // gave a saved receipt BOTH layouts — harmless to print, because
            // the serial one wins, but it is the contradiction Definition's
            // own docblock rules out, and anything asking "which kind of
            // document is this?" by looking at $template got the wrong answer.
            template:    $type === Definition::FORM && !isset($form['serialLayout'])
                            ? new Template(array_map([$this, 'elementFromArray'],
                                (array)($form['elements'] ?? [])))
                            : null,
            breakField:  (string)($form['breakField']  ?? 'id'),
            recordTable: (string)($form['recordTable'] ?? ''),
            recordKey:   (string)($form['recordKey']   ?? 'id'),
            printedField: (string)($form['printedField'] ?? ''),
            serial:      (bool)($form['serial'] ?? false),
            serialTemplate: isset($form['serialLayout'])
                ? $this->serialTemplateFromArray((array)$form['serialLayout'])
                : null,
        );
    }

    /** @param array<string,mixed> $data */
    private function pageFromArray(array $data): PageSetup
    {
        $margins  = (array)($data['margins'] ?? []);
        $defaults = new PageSetup();
        return new PageSetup(
            size:         (string)($data['size']        ?? $defaults->size),
            orientation:  (string)($data['orientation'] ?? $defaults->orientation),
            marginTop:    (float)($margins['top']    ?? $defaults->marginTop),
            marginBottom: (float)($margins['bottom'] ?? $defaults->marginBottom),
            marginLeft:   (float)($margins['left']   ?? $defaults->marginLeft),
            marginRight:  (float)($margins['right']  ?? $defaults->marginRight),
        );
    }

    /** @param array<string,mixed> $data */
    private function filterFromArray(array $data): Filter
    {
        $field    = (string)($data['field'] ?? '');
        $operator = FilterOperator::tryFrom((string)($data['operator'] ?? ''));
        if ($operator === null) {
            throw new DefinitionException("Filter on '$field' has an unknown operator '"
                . (string)($data['operator'] ?? '') . "'.");
        }
        if (isset($data['expression'])) {
            // FieldExpression validates its own tokens, including that every
            // field key is an identifier — which is the guard that keeps this
            // format from becoming a way to smuggle SQL back in.
            return Filter::compareFields($field, $operator, new FieldExpression(
                array_map(fn($t) => ['kind' => (string)($t['kind'] ?? ''), 'text' => (string)($t['text'] ?? '')],
                    (array)$data['expression'])));
        }
        return new Filter($field, $operator, array_values((array)($data['values'] ?? [])));
    }

    /** @param array<string,mixed> $data */
    private function sortFromArray(array $data): Sort
    {
        $direction = SortDirection::tryFrom(strtoupper((string)($data['direction'] ?? 'ASC')));
        if ($direction === null) {
            throw new DefinitionException("Sort on '" . (string)($data['field'] ?? '')
                . "' has an unknown direction '" . (string)($data['direction'] ?? '') . "'.");
        }
        return new Sort((string)($data['field'] ?? ''), $direction);
    }

    /** @param array<string,mixed> $data */
    private function reportColumnFromArray(array $data): ReportColumn
    {
        return new ReportColumn(
            key:       (string)($data['key']     ?? ''),
            heading:   (string)($data['heading'] ?? ''),
            width:     isset($data['width']) ? (float)$data['width'] : null,
            align:     $this->alignFromValue($data['align'] ?? null),
            format:    $this->formatFromValue($data['format'] ?? null),
            total:     (bool)($data['total']     ?? false),
            pageBreak: (bool)($data['pageBreak'] ?? false),
        );
    }

    /** @param array<string,mixed> $data */
    private function reportStyleFromArray(array $data): ReportStyle
    {
        $defaults = new ReportStyle();
        return new ReportStyle(
            heading:        $this->styleFromArray($data['heading'] ?? null),
            showHeading:    (bool)($data['showHeading'] ?? $defaults->showHeading),
            title1:         $this->styleFromArray($data['title1'] ?? null),
            showTitle1:     (bool)($data['showTitle1'] ?? $defaults->showTitle1),
            title1Text:     isset($data['title1Text']) ? (string)$data['title1Text'] : null,
            title2:         $this->styleFromArray($data['title2'] ?? null),
            showTitle2:     (bool)($data['showTitle2'] ?? $defaults->showTitle2),
            title2Text:     isset($data['title2Text']) ? (string)$data['title2Text'] : null,
            filter:         $this->styleFromArray($data['filter'] ?? null),
            data:           $this->styleFromArray($data['data']   ?? null),
            totals:         $this->styleFromArray($data['totals'] ?? null),
            truncate:       (bool)($data['truncate']       ?? $defaults->truncate),
            groupPageBreak: (bool)($data['groupPageBreak'] ?? $defaults->groupPageBreak),
        );
    }

    private function styleFromArray(mixed $data): ?Style
    {
        // `{}` survives json_decode() as an empty array, but a caller handing
        // over a decoded object should get the same answer.
        if (is_object($data)) $data = (array)$data;
        if (!is_array($data)) return null;
        return new Style(
            font:         isset($data['font']) ? (string)$data['font'] : null,
            size:         isset($data['size']) ? (float)$data['size']  : null,
            colour:       $this->colourFromArray($data['colour'] ?? null),
            align:        $this->alignFromValue($data['align'] ?? null),
            borderSize:   isset($data['borderSize']) ? (float)$data['borderSize'] : null,
            borderColour: $this->colourFromArray($data['borderColour'] ?? null),
            borderShow:   (bool)($data['borderShow'] ?? false),
            truncate:     (bool)($data['truncate']   ?? false),
            bold:         (bool)($data['bold']       ?? false),
            italic:       (bool)($data['italic']     ?? false),
        );
    }

    private function colourFromArray(mixed $data): ?Colour
    {
        if (!is_array($data) || count($data) !== 3) return null;
        return new Colour((int)$data[0], (int)$data[1], (int)$data[2]);
    }

    private function alignFromValue(mixed $value): ?Align
    {
        return is_string($value) ? Align::tryFrom($value) : null;
    }

    private function formatFromValue(mixed $value): ?Format
    {
        if (is_string($value)) return new Format($value);
        if (is_array($value))  return new Format((string)($value['name'] ?? ''), (array)($value['options'] ?? []));
        return null;
    }

    /** @param array<string,mixed> $data */
    private function bindingFromArray(mixed $data): Binding
    {
        if (!is_array($data)) {
            throw new DefinitionException('A binding must be an object.');
        }
        $scopeKey = (string)($data['scope'] ?? 'record');
        if (!isset(self::SCOPES[$scopeKey])) {
            throw new DefinitionException("Unknown binding scope '$scopeKey'.");
        }
        return new Binding(
            name:  (string)($data['name'] ?? ''),
            scope: self::SCOPES[$scopeKey],
            sumOf: array_map('strval', (array)($data['sumOf'] ?? [])),
        );
    }

    /** @param array<string,mixed> $data */
    private function elementFromArray(array $data): Element
    {
        $tag = (string)($data['type'] ?? '');
        if (!isset(self::ELEMENTS[$tag])) {
            throw new DefinitionException("Unknown element type '$tag'.");
        }

        $box     = $this->boxFromArray((array)($data['box'] ?? []));
        $style   = $this->styleFromArray($data['style'] ?? null);
        $label   = isset($data['label']) ? (string)$data['label'] : null;

        $visibleKey = (string)($data['visible'] ?? 'all');
        if (!isset(self::VISIBILITY[$visibleKey])) {
            throw new DefinitionException("Unknown visibility '$visibleKey' on a '$tag' element.");
        }
        $visible = self::VISIBILITY[$visibleKey];

        switch ($tag) {
            case 'text':
                return new StaticText((string)($data['text'] ?? ''), $box, $style, $visible, $label);

            case 'field':
                return new Field($this->bindingFromArray($data['bind'] ?? null), $box,
                    $this->formatFromValue($data['format'] ?? null), $style, $visible, $label);

            case 'block':
                $parts = [];
                foreach ((array)($data['parts'] ?? []) as $part) {
                    $parts[] = new BlockPart(
                        bind:      $this->bindingFromArray($part['bind'] ?? null),
                        separator: (string)($part['separator'] ?? ''),
                        format:    $this->formatFromValue($part['format'] ?? null),
                    );
                }
                return new FieldBlock($parts, $box, $style, $visible, $label);

            case 'table':
                $columns = [];
                foreach ((array)($data['columns'] ?? []) as $column) {
                    $columns[] = new Column(
                        bind:    $this->bindingFromArray($column['bind'] ?? null),
                        heading: (string)($column['heading'] ?? ''),
                        width:   isset($column['width']) ? (float)$column['width'] : null,
                        align:   $this->alignFromValue($column['align'] ?? null),
                        format:  $this->formatFromValue($column['format'] ?? null),
                        total:   (bool)($column['total'] ?? false),
                        style:   $this->styleFromArray($column['style'] ?? null),
                    );
                }
                return new Table((string)($data['source'] ?? 'items'), $columns, $box,
                    $this->styleFromArray($data['headerStyle'] ?? null), $style, $visible, $label);

            case 'line':
                return new Line($box, (string)($data['lineType'] ?? 'solid'), $style, $visible, $label);

            case 'rect':
                return new Rect($box, $style, $visible, $label);

            case 'image':
                $src = $data['src'] ?? '';
                return new Image(
                    is_array($src) ? $this->bindingFromArray($src['bind'] ?? null) : (string)$src,
                    $box, $style, $visible, $label, (string)($data['pathPrefix'] ?? ''));

            case 'pagenum':
                return new PageNumber($box, (string)($data['pattern'] ?? '{page} / {pages}'),
                    $style, $visible, $label);

            case 'barcode':
                return new Barcode($this->bindingFromArray($data['bind'] ?? null), $box,
                    (string)($data['symbology'] ?? 'C128'), $style, $visible, $label);
        }

        // Unreachable: the ELEMENTS check above covers every tag. Kept so that
        // adding a tag without adding its case is a loud failure, not a null.
        throw new DefinitionException("Element type '$tag' is declared but not decoded.");
    }

    /** @param array<string,mixed> $data */
    private function boxFromArray(array $data): Box
    {
        return new Box(
            x:      (float)($data['x'] ?? 0),
            y:      (float)($data['y'] ?? 0),
            width:  isset($data['width'])  ? (float)$data['width']  : null,
            height: isset($data['height']) ? (float)$data['height'] : null,
        );
    }

    // ── shared ───────────────────────────────────────────────────────────────

    /**
     * Write a key only when it differs from the value a reader would assume.
     *
     * @param array<string,mixed> $target
     */
    /** @return array<string,mixed> */
    private function serialFieldToArray(SerialField $field): array
    {
        $out = ['element' => $this->elementToArray($field->element)];
        // The cell, flat beside the element rather than nested under a key of
        // its own: it is three small numbers and reads better in a file that a
        // person may well have to look at.
        $this->put($out, 'width',     $field->cell->width,     40);
        $this->put($out, 'align',     $field->cell->align->value, Align::Left->value);
        $this->put($out, 'rowBreak',  $field->cell->rowBreak,  false);
        $this->put($out, 'wholeLine', $field->cell->wholeLine, false);
        return $out;
    }

    /** @param array<string,mixed> $data */
    private function serialTemplateFromArray(array $data): SerialTemplate
    {
        $fields = [];
        foreach ((array)($data['fields'] ?? []) as $entry) {
            $entry = (array)$entry;
            if (!isset($entry['element'])) continue;
            $fields[] = new SerialField(
                $this->elementFromArray((array)$entry['element']),
                new SerialCell(
                    width:     (int)($entry['width'] ?? 40),
                    align:     $this->alignFromValue($entry['align'] ?? null) ?? Align::Left,
                    rowBreak:  (bool)($entry['rowBreak']  ?? false),
                    wholeLine: (bool)($entry['wholeLine'] ?? false),
                ),
            );
        }
        return new SerialTemplate($fields, (int)($data['width'] ?? 40));
    }

    private function put(array &$target, string $key, mixed $value, mixed $default): void
    {
        if ($value === $default) return;
        if ($value === null)     return;
        $target[$key] = $value;
    }

    /** @param array<string,mixed> $map */
    private function keyFor(array $map, mixed $value): ?string
    {
        $key = array_search($value, $map, true);
        return $key === false ? null : (string)$key;
    }
}
