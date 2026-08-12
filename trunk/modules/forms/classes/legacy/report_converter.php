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
//  Path: /modules/forms/classes/legacy/report_converter.php
//
/**
 * Forms — converts a PhreeForm REPORT definition into Forms parts.
 *
 * The sibling of TemplateConverter, which handles forms. Reports store a
 * different shape and need different treatment:
 *
 *   - a report's `fieldlist` is a COLUMN list, not positioned elements;
 *   - `visible` decides whether a field is displayed, but an invisible field is
 *     still selected, because it may be the group or sort key;
 *   - `grouplist`, `sortlist` and `filterlist` each mark one entry `default`,
 *     which is the one actually applied — the rest are offered to the user at
 *     run time.
 *
 * `columnbreak` is carried through but not interpreted here: PhreeForm packs
 * several fields into one printed column by marking all but the last with
 * break=0, so they stack vertically inside it. That is a rendering concern, and
 * ReportColumn records it for a renderer that wants to reproduce it.
 */

namespace FreeBooks\Forms\Legacy;

use FreeBooks\Forms\Catalog\{CatalogException, Dataset, FieldExpression, Filter, FilterOperator, Sort, SortDirection};
use FreeBooks\Forms\Document\{Align, Format, ReportColumn};

final class ReportConverter
{
    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string,true> */
    private array $unmapped = [];

    /** @var array<string,string> */
    private array $fieldMap = [];

    /** @var array<string,string> resolved column key => the id field it derives from */
    private array $resolved = [];

    /**
     * Hints the PROVIDER can resolve when the dataset does not.
     *
     * `branch` resolves a contact id to its short name — despite the name, it
     * has nothing to do with branches. The other three (`avg_cost`,
     * `avg_total`, `ordr_qty`) are arithmetic over a table the dataset already
     * reaches, so they belong in the dataset as expressions and are not listed
     * here; a dataset that omits one gets a warning rather than a silent
     * per-row query.
     */
    private const PROVIDER_LOOKUPS = ['branch', 'rep_id'];

    /**
     * Columns the provider must fill in after the query.
     *
     * @return array<string,string> column key => dataset field holding the id
     */
    public function resolvedColumns(): array { return $this->resolved; }

    /** @return list<ReportColumn> the displayed columns, in order */
    public function convertColumns(object $report, Dataset $dataset): array
    {
        $this->warnings = [];
        $this->unmapped = [];
        $this->resolved = [];
        $this->fieldMap = TemplateConverter::mapFor($dataset);

        $columns = [];
        foreach ((array)($report->fieldlist ?? []) as $index => $field) {
            if (!$this->truthy($field->visible ?? null)) continue;   // selected, not shown

            $legacy = $this->scalar($field->fieldname ?? null);
            $key    = $this->mapName($legacy);
            if ($key === null) {
                $this->warnings[] = "[$index] column '" . $this->scalar($field->description ?? null)
                                  . "' references '$legacy', which this dataset does not provide.";
                continue;
            }

            $hint = $this->scalar($field->processing ?? null);
            // A hint the renderer refuses is a provider-side lookup; re-point it
            // exactly as the form converter does (§4.3.1). The re-pointed key
            // has to be checked: a dataset that has the base field but not its
            // resolved companion would otherwise fail deep in the query builder
            // with "no field 'history_id_avg_total'", naming a key nobody wrote.
            // Reporting it here says which column and which hint caused it.
            if ($hint !== '' && isset(TemplateConverter::PROVIDER_RESOLVED[$hint])) {
                $resolved = $key . TemplateConverter::PROVIDER_RESOLVED[$hint];
                if ($dataset->field($resolved) !== null) {
                    // The dataset serves it directly — a computed field beats a
                    // lookup, because it happens in the one query.
                    $key  = $resolved;
                } elseif (in_array($hint, self::PROVIDER_LOOKUPS, true)) {
                    // No dataset field, but the provider knows this lookup.
                    // The column takes the resolved key and the provider is
                    // told which id to derive it from.
                    $this->resolved[$resolved] = $key;
                    $key = $resolved;
                } else {
                    $this->warnings[] = "[$index] column '" . $this->scalar($field->description ?? null)
                                      . "' needs '$hint', which neither this dataset (as '$resolved')"
                                      . " nor the provider can serve; the column shows '$key' unresolved.";
                }
                $hint = '';   // resolved, or refused by the renderer anyway
            }

            $columns[] = new ReportColumn(
                key:       $key,
                heading:   $this->scalar($field->description ?? null),
                width:     $this->number($field->width ?? null),
                align:     $this->align($this->scalar($field->align ?? null)),
                format:    $hint === '' ? null : new Format($hint),
                total:     $this->truthy($field->total ?? null),
                pageBreak: $this->truthy($field->columnbreak ?? null),
            );
        }
        return $columns;
    }

    /** The field key the report groups on, or null. */
    public function convertGroup(object $report, Dataset $dataset): ?string
    {
        foreach ($this->entries($report->grouplist ?? null) as $entry) {
            if (!$this->truthy($entry->default ?? null)) continue;
            return $this->mapName($this->scalar($entry->fieldname ?? null));
        }
        return null;
    }

    /** The formatting applied to the group's label, if any. */
    public function convertGroupFormat(object $report): ?Format
    {
        foreach ($this->entries($report->grouplist ?? null) as $entry) {
            if (!$this->truthy($entry->default ?? null)) continue;
            $hint = $this->scalar($entry->processing ?? null);
            return $hint === '' ? null : new Format($hint);
        }
        return null;
    }

    /** @return list<Sort> */
    public function convertSorts(object $report, Dataset $dataset): array
    {
        $sorts = [];
        foreach ($this->entries($report->sortlist ?? null) as $entry) {
            if (!$this->truthy($entry->default ?? null)) continue;
            $key = $this->mapName($this->scalar($entry->fieldname ?? null));
            if ($key === null) continue;
            $sorts[] = new Sort($key, strtoupper($this->scalar($entry->sortorder ?? null)) === 'DESC'
                ? SortDirection::Descending : SortDirection::Ascending);
        }
        return $sorts;
    }

    /**
     * The default comparison mode for each legacy filter `type`.
     *
     * PhreeForm stores a filter's type, and `$CritChoices[$type]` lists the
     * modes offered for it as "<arity>:<MODE>:<MODE>…". When a definition names
     * no mode, build_criteria() takes the FIRST — so a type-0 filter defaults to
     * ALL and applies nothing at all, even though it carries a min_val.
     *
     * Reading min_val as "equals" instead, which is the obvious guess, silently
     * over-filters: on Sales By SKU it cut 4,707 rows to 636.
     */
    private const CRIT_CHOICES = [
        0  => ['ALL', 'RANGE', 'EQUAL'],
        1  => ['YES', 'NO'],
        2  => ['ALL', 'YES', 'NO'],
        3  => ['ALL', 'ACTIVE', 'INACTIVE'],
        4  => ['ALL', 'PRINTED', 'UNPRINTED'],
        6  => ['EQUAL'],
        7  => ['RANGE'],
        8  => ['NOT_EQUAL'],
        9  => ['IN_LIST'],
        10 => ['LESS_THAN'],
        11 => ['GREATER_THAN'],
        12 => ['NOT_NULL'],
        13 => ['NULL'],
    ];

    /**
     * Filters the definition applies without being asked.
     *
     * Each is converted to its bound-parameter equivalent. A mode this cannot
     * express is reported rather than approximated, because a filter quietly
     * dropped or quietly widened changes which rows a report shows.
     *
     * @return list<Filter>
     */
    public function convertFilters(object $report, Dataset $dataset): array
    {
        $filters = [];
        foreach ($this->entries($report->filterlist ?? null) as $entry) {
            $mode = $this->scalar($entry->default ?? null);
            if ($mode === '') {
                $type = (int)$this->scalar($entry->type ?? null);
                $mode = self::CRIT_CHOICES[$type][0] ?? 'ALL';
            }
            if ($mode === 'ALL') continue;                    // applies nothing

            $legacy = $this->scalar($entry->fieldname ?? null);
            $key    = $this->mapName($legacy);
            if ($key === null) {
                $this->warnings[] = "filter on '$legacy' has no dataset field; NOT applied.";
                continue;
            }

            $min = $this->scalar($entry->min_val ?? null);
            $max = $this->scalar($entry->max_val ?? null);

            // PhreeForm decides between "a value" and "other columns" with
            // testTables(): if min_val mentions one of the report's tables it
            // is spliced in as SQL, otherwise it is quoted as a literal. The
            // same fork happens here, except the column side is parsed into
            // validated field keys rather than concatenated.
            $expression = $this->fieldExpression($min, $dataset);
            if ($expression !== null) {
                $operator = match ($mode) {
                    'EQUAL'        => FilterOperator::Equal,
                    'NOT_EQUAL'    => FilterOperator::NotEqual,
                    'GREATER_THAN' => FilterOperator::GreaterThan,
                    'LESS_THAN'    => FilterOperator::LessThan,
                    default        => null,
                };
                if ($operator === null) {
                    $this->warnings[] = "filter on '$legacy' compares fields with mode '$mode', "
                                      . "which is not supported; NOT applied.";
                    continue;
                }
                $filters[] = Filter::compareFields($key, $operator, $expression);
                continue;
            }

            $filter = match ($mode) {
                'EQUAL'                                   => $min === '' ? null : Filter::equals($key, $min),
                'NOT_EQUAL'                               => $min === '' ? null : new Filter($key, FilterOperator::NotEqual, [$min]),
                'GREATER_THAN'                            => $min === '' ? null : new Filter($key, FilterOperator::GreaterThan, [$min]),
                'LESS_THAN'                               => $min === '' ? null : new Filter($key, FilterOperator::LessThan, [$min]),
                'IN_LIST'                                 => $min === '' ? null : Filter::in($key, array_map('trim', explode(',', $min))),
                'NULL'                                    => new Filter($key, FilterOperator::IsEmpty),
                'NOT_NULL'                                => new Filter($key, FilterOperator::IsNotEmpty),
                'YES', 'TRUE', 'INACTIVE', 'PRINTED'      => Filter::equals($key, '1'),
                'NO', 'FALSE', 'ACTIVE', 'UNPRINTED'      => Filter::equals($key, '0'),
                'RANGE'                                   => $this->rangeFilter($key, $min, $max),
                default                                   => null,
            };

            if ($filter === null) {
                if (!in_array($mode, ['EQUAL','NOT_EQUAL','GREATER_THAN','LESS_THAN','IN_LIST','RANGE'], true)) {
                    $this->warnings[] = "filter mode '$mode' on '$legacy' is not supported; NOT applied.";
                }
                continue;   // a mode needing a value it does not have applies nothing
            }
            $filters[] = $filter;
        }
        return $filters;
    }

    /**
     * Read a legacy filter value as arithmetic over dataset fields, or null.
     *
     * Returns null for an ordinary literal, which is the common case — the test
     * is deliberately strict, because treating a value as an expression would
     * turn a filter into a comparison between two columns. Every identifier
     * must resolve to a field of THIS dataset, and the only other characters
     * allowed are the six expression symbols and whitespace; a digit, a quote
     * or a function call anywhere means it is not one of these.
     */
    private function fieldExpression(string $source, Dataset $dataset): ?FieldExpression
    {
        if ($source === '' || !str_contains($source, '.')) return null;

        $tokens = [];
        $length = strlen($source);
        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            if (ctype_space($char)) continue;
            if (in_array($char, FieldExpression::OPERATORS, true)) {
                $tokens[] = ['kind' => 'op', 'text' => $char];
                continue;
            }
            if (!preg_match('/[A-Za-z_]/', $char)) return null;   // digit, quote, comma…

            $word = '';
            while ($i < $length && preg_match('/[A-Za-z0-9_.]/', $source[$i])) $word .= $source[$i++];
            $i--;

            $key = $this->mapName($word);
            if ($key === null) {
                // Record it, so a dataset missing the field says so rather than
                // this quietly falling back to treating the whole thing as text.
                $this->warnings[] = "filter expression references '$word', which this dataset does not provide.";
                return null;
            }
            $tokens[] = ['kind' => 'field', 'text' => $key];
        }

        try {
            return new FieldExpression($tokens);
        } catch (CatalogException $e) {
            $this->warnings[] = "filter expression '$source' rejected: " . $e->getMessage();
            return null;
        }
    }

    /** RANGE applies whichever bounds are present, which may be one or neither. */
    private function rangeFilter(string $key, string $min, string $max): ?Filter
    {
        if ($min !== '' && $max !== '') return Filter::between($key, $min, $max);
        if ($min !== '')                return new Filter($key, FilterOperator::GreaterEqual, [$min]);
        if ($max !== '')                return new Filter($key, FilterOperator::LessEqual, [$max]);
        return null;
    }

    /** @return list<string> */
    public function warnings(): array { return $this->warnings; }

    /** @return list<string> */
    public function unmappedFields(): array { return array_keys($this->unmapped); }

    // ── internals ────────────────────────────────────────────────────────────

    private function mapName(string $name): ?string
    {
        if ($name === '') return null;
        $key = $this->fieldMap[strtolower($name)]
            ?? $this->fieldMap[strtolower(preg_replace('/\s+/', '', $name))]
            ?? null;
        if ($key !== null) return $key;
        if (in_array($name, $this->fieldMap, true)) return $name;   // already a key
        $this->unmapped[$name] = true;
        return null;
    }

    /** @return list<object> */
    private function entries(mixed $list): array
    {
        if ($list === null)   return [];
        if (is_object($list)) return [$list];
        return array_values((array)$list);
    }

    private function truthy(mixed $value): bool
    {
        $value = $this->scalar($value);
        return $value !== '' && $value !== '0';
    }

    private function align(string $legacy): ?Align
    {
        return match ($legacy) {
            'L'     => Align::Left,
            'C'     => Align::Center,
            'R'     => Align::Right,
            default => null,
        };
    }

    private function number(mixed $value): ?float
    {
        $value = $this->scalar($value);
        return $value === '' ? null : (float)$value;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) return '';
        if (is_array($value)) $value = reset($value);
        if (is_object($value) && !method_exists($value, '__toString')) return '';
        return (string)$value;
    }
}
