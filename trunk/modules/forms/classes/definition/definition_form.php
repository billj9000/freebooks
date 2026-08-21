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
//  Path: /modules/forms/classes/definition/definition_form.php
//
/**
 * Forms — a report definition as an editable form, and back. See §13.4, §13.7 step 2.
 *
 * The report editor posts a flat array of strings; this turns that into a
 * Definition, and a Definition into the values the editor renders. Kept out of
 * the page for two reasons: the preview endpoint needs exactly the same
 * conversion as the save does — a preview built differently from the save is a
 * preview of something else — and a class can be held to a round trip by
 * docs/tools, where a page cannot.
 *
 * **The rule this class exists to keep, and the whole of §13.4: an author picks
 * declared identifiers. They never supply a name.** Every column, group, sort
 * and filter key submitted is checked against the resolved dataset before it
 * reaches a Definition, and one that is not there is refused rather than
 * stored. PhreeForm's designer opened with a bare SHOW TABLES and let an author
 * put SQL in a field NAME, which is why any report author was a database-wide
 * read primitive (§1.1). An author who can only name what the dataset declares
 * cannot reach a table it does not.
 *
 * That check is not a formality here. The editor offers a `<select>` of the
 * dataset's fields, and a select is a suggestion: the browser is under the
 * author's control, and the POST is whatever they choose to send.
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Catalog\{Dataset, FieldExpression, Filter, FilterOperator, Sort, SortDirection};
use FreeBooks\Forms\Document\{Align, Colour, Format, PageSetup, ReportColumn, ReportStyle, Style};

final class DefinitionForm
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * The keys an author may name, beyond the dataset's own fields.
     *
     * A resolved column has NO dataset field — `rep_id_name` is filled in by the
     * provider after the query from the id it derives from (§4.3.1) — so
     * checking it against the dataset would delete it on the first save. They
     * are carried from the definition being edited rather than invented, which
     * keeps the rule intact: still no free text, just a second declared list.
     *
     * @return array<string,true>
     */
    private static function allowedKeys(Definition $base, ?Dataset $dataset): array
    {
        $allowed = [];
        foreach (array_keys($base->resolvedColumns) as $key) $allowed[$key] = true;

        if ($dataset === null) {
            // A structural report's rows come from a class, so its column keys
            // are the provider's contract and there is no palette to check
            // against. What it already has is what it may keep.
            foreach ($base->columns as $column) $allowed[$column->key] = true;
            return $allowed;
        }
        foreach (array_keys($dataset->fields) as $key) $allowed[$key] = true;
        return $allowed;
    }

    /**
     * Apply a submitted form to the definition it was opened from.
     *
     * The BASE matters: an author edits columns, filters and page setup, and
     * everything else — the dataset, the provider, the resolved-column map, the
     * record table a form breaks on — is carried across untouched. A form that
     * could restate those would be a form that could repoint a definition at
     * another dataset by hand.
     *
     * @param array<string,mixed> $request
     * @throws DefinitionException when the definition cannot be edited here at all
     */
    public function apply(Definition $base, array $request, ?Dataset $dataset): Definition
    {
        $this->warnings = [];

        if ($base->isForm()) {
            throw new DefinitionException('This is a form. The form canvas edits it, not the report editor.');
        }

        $allowed = self::allowedKeys($base, $dataset);

        return new Definition(
            id:          $base->id,
            type:        $base->type,
            title:       trim((string)($request['title'] ?? $base->title)),
            description: trim((string)($request['description'] ?? $base->description)),
            group:       $base->group,
            // Carried, not edited. The editor draws no control for either, and
            // anything the form cannot show has to be copied across explicitly
            // or it is deleted by the first save — which is precisely what the
            // round-trip check in verify_forms_pages.php exists to catch, and
            // did, the moment these two were added to the format.
            filenamePrefix: $base->filenamePrefix,
            emailMessage:   $base->emailMessage,
            provider:    $base->provider,
            datasetId:   $base->datasetId,
            page:        $this->page((array)($request['paper'] ?? []), $base->page),

            dateDefault: (string)($request['dates']['default'] ?? $base->dateDefault),
            dateField:   $this->key((string)($request['dates']['field'] ?? (string)$base->dateField),
                            $allowed, 'the date range field') ?? null,
            period:      $base->period,

            filters:     $this->filters((array)($request['filter'] ?? []), $allowed),
            sorts:       $this->sorts((array)($request['sort'] ?? []), $allowed),

            columns:         $this->columns((array)($request['column'] ?? []), $allowed),
            groupBy:         $this->key((string)($request['group']['by'] ?? ''), $allowed, 'the group field'),
            groupFormat:     $this->format((string)($request['group']['format'] ?? '')),
            totalsOnly:      !empty($request['totalsOnly']),
            resolvedColumns: $base->resolvedColumns,
            style:           $this->style((array)($request['style'] ?? []), $base->style),

            template:    $base->template,
            breakField:  $base->breakField,
            recordTable: $base->recordTable,
            recordKey:   $base->recordKey,
            printedField: $base->printedField,
        );
    }

    /**
     * The definition as the editor's own field values.
     *
     * The inverse of apply(), and it has to be exact: `verify_forms_designer.php`
     * asserts that applying what this produces gives the definition back
     * unchanged, which is the property that stops opening and saving a report
     * nobody edited from quietly rewriting it.
     *
     * @return array<string,mixed>
     */
    public static function toRequest(Definition $definition): array
    {
        $columns = [];
        foreach ($definition->columns as $column) {
            $columns[] = [
                'key'       => $column->key,
                'heading'   => $column->heading,
                'width'     => $column->width === null ? '' : (string)$column->width,
                'align'     => $column->align?->value ?? '',
                'format'    => $column->format?->name ?? '',
                'total'     => $column->total     ? '1' : '',
                'pageBreak' => $column->pageBreak ? '1' : '',
            ];
        }

        $sorts = [];
        foreach ($definition->sorts as $sort) {
            $sorts[] = ['field' => $sort->fieldKey, 'direction' => $sort->direction->value];
        }

        $filters = [];
        foreach ($definition->filters as $filter) {
            $entry = [
                'field'    => $filter->fieldKey,
                'operator' => $filter->operator->value,
                'value1'   => '',
                'value2'   => '',
                'expression' => '',
            ];
            if ($filter->expression !== null) {
                $entry['expression'] = (string)json_encode($filter->expression->tokens);
            } elseif ($filter->operator === FilterOperator::In) {
                $entry['value1'] = implode(', ', array_map('strval', $filter->values));
            } else {
                $entry['value1'] = (string)($filter->values[0] ?? '');
                $entry['value2'] = (string)($filter->values[1] ?? '');
            }
            $filters[] = $entry;
        }

        $style = $definition->style;
        return [
            'title'       => $definition->title,
            'description' => $definition->description,
            'paper'       => [
                'size'        => $definition->page->size,
                'orientation' => $definition->page->orientation,
                'margin'      => [
                    'top'    => (string)$definition->page->marginTop,
                    'bottom' => (string)$definition->page->marginBottom,
                    'left'   => (string)$definition->page->marginLeft,
                    'right'  => (string)$definition->page->marginRight,
                ],
            ],
            'dates'      => ['default' => $definition->dateDefault,
                             'field'   => (string)$definition->dateField],
            'column'     => $columns,
            'sort'       => $sorts,
            'filter'     => $filters,
            'group'      => ['by'     => (string)$definition->groupBy,
                             'format' => $definition->groupFormat?->name ?? ''],
            'totalsOnly' => $definition->totalsOnly ? '1' : '',
            'style'      => [
                'showHeading'    => $style->showHeading ? '1' : '',
                'showTitle1'     => $style->showTitle1  ? '1' : '',
                'title1Text'     => (string)$style->title1Text,
                'showTitle2'     => $style->showTitle2  ? '1' : '',
                'title2Text'     => (string)$style->title2Text,
                'truncate'       => $style->truncate       ? '1' : '',
                'groupPageBreak' => $style->groupPageBreak ? '1' : '',
                'band'           => [
                    'heading' => self::bandToRequest($style->heading),
                    'title1'  => self::bandToRequest($style->title1),
                    'title2'  => self::bandToRequest($style->title2),
                    'filter'  => self::bandToRequest($style->filter),
                    'data'    => self::bandToRequest($style->data),
                    'totals'  => self::bandToRequest($style->totals),
                ],
            ],
        ];
    }

    /** @return array<string,string> */
    private static function bandToRequest(?Style $band): array
    {
        return [
            // A band that was never styled is not the same as one styled to the
            // renderer's defaults — see DefinitionCodec::styleToArray(). The
            // flag is what carries the difference through the form.
            'set'    => $band === null ? '' : '1',
            'font'   => (string)($band?->font ?? ''),
            'size'   => $band?->size === null ? '' : (string)$band->size,
            'colour' => $band?->colour === null ? ''
                        : sprintf('#%02x%02x%02x', $band->colour->r, $band->colour->g, $band->colour->b),
            'align'  => $band?->align?->value ?? '',
            // No control draws these on the report editor, so they travel
            // through the form rather than being rebuilt from nothing — the
            // same reason the band's border colour does.
            'bold'   => $band?->bold   ? '1' : '',
            'italic' => $band?->italic ? '1' : '',
        ];
    }

    /** @return list<string> field names the author submitted that the dataset does not declare */
    public function warnings(): array { return $this->warnings; }

    // ── pieces ───────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $request */
    private function page(array $request, PageSetup $current): PageSetup
    {
        $margin = (array)($request['margin'] ?? []);
        $number = function (mixed $value, float $fallback): float {
            $value = trim((string)$value);
            // A blank margin means "leave it", not "zero" — an author clearing
            // the box to retype it must not be able to lose the page edge.
            return $value === '' || !is_numeric($value) ? $fallback : (float)$value;
        };

        return new PageSetup(
            size:         trim((string)($request['size'] ?? '')) ?: $current->size,
            orientation:  ($request['orientation'] ?? '') === 'L' ? 'L' : 'P',
            marginTop:    $number($margin['top']    ?? '', $current->marginTop),
            marginBottom: $number($margin['bottom'] ?? '', $current->marginBottom),
            marginLeft:   $number($margin['left']   ?? '', $current->marginLeft),
            marginRight:  $number($margin['right']  ?? '', $current->marginRight),
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,true>        $allowed
     * @return list<ReportColumn>
     */
    private function columns(array $rows, array $allowed): array
    {
        $columns = [];
        foreach ($rows as $index => $row) {
            $key = $this->key((string)($row['key'] ?? ''), $allowed, "column " . ($index + 1));
            if ($key === null) continue;

            $width = trim((string)($row['width'] ?? ''));
            $columns[] = new ReportColumn(
                key:       $key,
                heading:   trim((string)($row['heading'] ?? '')),
                width:     $width === '' || !is_numeric($width) ? null : (float)$width,
                align:     Align::tryFrom((string)($row['align'] ?? '')),
                format:    $this->format((string)($row['format'] ?? '')),
                total:     !empty($row['total']),
                pageBreak: !empty($row['pageBreak']),
            );
        }
        return $columns;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,true>        $allowed
     * @return list<Sort>
     */
    private function sorts(array $rows, array $allowed): array
    {
        $sorts = [];
        foreach ($rows as $index => $row) {
            $key = $this->key((string)($row['field'] ?? ''), $allowed, 'sort ' . ($index + 1));
            if ($key === null) continue;
            $sorts[] = new Sort($key,
                strtoupper((string)($row['direction'] ?? '')) === 'DESC'
                    ? SortDirection::Descending : SortDirection::Ascending);
        }
        return $sorts;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,true>        $allowed
     * @return list<Filter>
     */
    private function filters(array $rows, array $allowed): array
    {
        $filters = [];
        foreach ($rows as $index => $row) {
            $what = 'filter ' . ($index + 1);
            $key  = $this->key((string)($row['field'] ?? ''), $allowed, $what);
            if ($key === null) continue;

            $operator = FilterOperator::tryFrom((string)($row['operator'] ?? ''));
            if ($operator === null) {
                $this->warnings[] = "$what names an unknown comparison; it was not applied.";
                continue;
            }

            // An expression filter compares a column to OTHER COLUMNS — the
            // Re-order Worksheet asks for stock below (minimum + on order). It
            // is carried through the form rather than edited, because an
            // expression editor is not step 2; FieldExpression re-checks every
            // identifier on the way back in, so a hand-edited one is refused
            // rather than trusted.
            $expression = trim((string)($row['expression'] ?? ''));
            if ($expression !== '') {
                $tokens = json_decode($expression, true);
                if (!is_array($tokens)) {
                    $this->warnings[] = "$what has an unreadable field expression; it was not applied.";
                    continue;
                }
                try {
                    $filters[] = Filter::compareFields($key, $operator, new FieldExpression(
                        array_map(fn($t) => ['kind' => (string)($t['kind'] ?? ''),
                                             'text' => (string)($t['text'] ?? '')], $tokens)));
                } catch (\Throwable $e) {
                    $this->warnings[] = "$what has a field expression this dataset cannot serve: "
                                      . $e->getMessage();
                }
                continue;
            }

            $one = trim((string)($row['value1'] ?? ''));
            $two = trim((string)($row['value2'] ?? ''));

            $values = match ($operator->arity()) {
                0       => [],
                2       => [$one, $two],
                -1      => array_values(array_filter(array_map('trim', explode(',', $one)),
                               fn($v) => $v !== '')),
                default => [$one],
            };
            // A comparison that needs a value and has none applies nothing, and
            // silently matching everything is the wrong way to fail.
            if ($operator->arity() !== 0 && ($values === [] || ($operator->arity() >= 1 && $one === ''))) {
                $this->warnings[] = "$what has no value, so it was not applied.";
                continue;
            }

            try {
                $filters[] = new Filter($key, $operator, $values);
            } catch (\Throwable $e) {
                $this->warnings[] = "$what was refused: " . $e->getMessage();
            }
        }
        return $filters;
    }

    /** @param array<string,mixed> $request */
    private function style(array $request, ReportStyle $current): ReportStyle
    {
        $bands = (array)($request['band'] ?? []);
        $band  = function (string $name) use ($bands): ?Style {
            $values = (array)($bands[$name] ?? []);
            if (empty($values['set'])) return null;

            $size   = trim((string)($values['size'] ?? ''));
            $colour = trim((string)($values['colour'] ?? ''));
            return new Style(
                font:   trim((string)($values['font'] ?? '')) ?: null,
                size:   $size === '' || !is_numeric($size) ? null : (float)$size,
                colour: self::colour($colour),
                align:  Align::tryFrom((string)($values['align'] ?? '')),
                bold:   !empty($values['bold']),
                italic: !empty($values['italic']),
            );
        };

        $text = function (mixed $value): ?string {
            $value = trim((string)$value);
            return $value === '' ? null : $value;
        };

        return new ReportStyle(
            heading:        $band('heading'),
            showHeading:    !empty($request['showHeading']),
            title1:         $band('title1'),
            showTitle1:     !empty($request['showTitle1']),
            title1Text:     $text($request['title1Text'] ?? ''),
            title2:         $band('title2'),
            showTitle2:     !empty($request['showTitle2']),
            title2Text:     $text($request['title2Text'] ?? ''),
            filter:         $band('filter'),
            data:           $band('data'),
            totals:         $band('totals'),
            truncate:       !empty($request['truncate']),
            groupPageBreak: !empty($request['groupPageBreak']),
        );
    }

    /** `#rrggbb` from the browser's colour input, or nothing. */
    private static function colour(string $value): ?Colour
    {
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $value, $parts)) return null;
        return new Colour(hexdec($parts[1]), hexdec($parts[2]), hexdec($parts[3]));
    }

    private function format(string $name): ?Format
    {
        $name = trim($name);
        return $name === '' ? null : new Format($name);
    }

    /**
     * A submitted field key, or null.
     *
     * The single gate every author-supplied name passes through. An empty name
     * is a cleared row and says nothing; a name the dataset does not declare is
     * reported and dropped, because storing it would produce a report that
     * either renders a blank column or fails deep inside the query builder
     * naming a key nobody wrote.
     *
     * @param array<string,true> $allowed
     */
    private function key(string $name, array $allowed, string $what): ?string
    {
        $name = trim($name);
        if ($name === '') return null;
        if (isset($allowed[$name])) return $name;

        $this->warnings[] = "$what names '$name', which this dataset does not provide;"
                          . ' it was not saved.';
        return null;
    }
}
