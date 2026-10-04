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

use FreeBooks\Forms\Provider\IncomeStatementDocumentProvider;
use FreeBooks\Forms\Catalog\{
    CatalogException, Dataset, DateRange, DateRangeFilters, FieldExpression, Filter, FilterOperator,
    Sort, SortDirection
};
use FreeBooks\Forms\Document\{Align, Colour, Format, PageSetup, ReportColumn, ReportStyle, Style};

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

    /** Built on demand, for the parts reports and forms share. */
    private ?TemplateConverter $templates = null;

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
            $key  = $this->repoint($key, $hint, $dataset,
                "[$index] column '" . $this->scalar($field->description ?? null) . "'");

            $columns[] = new ReportColumn(
                key:       $key,
                heading:   $this->scalar($field->description ?? null),
                // Reports size columns with `columnwidth`; `width` is the box
                // width a LayoutDocument element uses, and is usually absent
                // here. Reading the wrong one silently gives every column the
                // renderer's default and wraps the data.
                width:     $this->number($field->columnwidth ?? $field->width ?? null),
                align:     $this->align($this->scalar($field->align ?? null)),
                format:    $hint === '' ? null : new Format($hint),
                total:     $this->truthy($field->total ?? null),
                pageBreak: $this->truthy($field->columnbreak ?? null),
            );
        }
        return $columns;
    }

    /**
     * Columns for a STRUCTURAL report — one whose rows come from a provider
     * rather than from a dataset query.
     *
     * The Income Statement family names its columns meaningfully, so those
     * names are kept and the provider fills them. The Balance Sheet does not:
     * its definition lists `journal_main.id` four times over, because the class
     * filled the row by position and never read the names. Anything unrecognised
     * therefore falls back to its position, which is the only thing the legacy
     * definition actually carried.
     *
     * @return list<ReportColumn>
     */
    public function convertStructuralColumns(object $report): array
    {
        $this->warnings = [];
        $this->unmapped = [];
        $this->resolved = [];

        $named   = array_merge(['description'], IncomeStatementDocumentProvider::VALUE_KEYS);
        $columns = [];
        $index   = 0;
        foreach ((array)($report->fieldlist ?? []) as $field) {
            if (!$this->truthy($field->visible ?? null)) continue;
            $legacy = $this->scalar($field->fieldname ?? null);
            $hint   = $this->scalar($field->processing ?? null);
            $columns[] = new ReportColumn(
                key:       in_array($legacy, $named, true) ? $legacy : 'c' . $index,
                heading:   $this->scalar($field->description ?? null),
                width:     $this->number($field->columnwidth ?? $field->width ?? null),
                align:     $this->align($this->scalar($field->align ?? null)),
                format:    $hint === '' ? null : new Format($hint),
                // A structural report's columns break and total exactly as any
                // other report's do — the provider decides where the ROWS come
                // from, not how the page is laid out. Omitted, both would
                // default to false, stacking every column of every
                // provider-backed report into a single band down the left of
                // the page with no column carrying a total.
                total:     $this->truthy($field->total ?? null),
                pageBreak: $this->truthy($field->columnbreak ?? null),
            );
            $index++;
        }
        return $columns;
    }

    /**
     * Re-point a key whose hint the renderer refuses onto something that can
     * serve it (§4.3.1), exactly as the form converter does.
     *
     * The re-pointed key has to be checked: a dataset that has the base field
     * but not its resolved companion would otherwise fail deep in the query
     * builder with "no field 'history_id_avg_total'", naming a key nobody
     * wrote. Reporting it here says which field and which hint caused it.
     *
     * $hint is emptied once it has been dealt with, so the caller does not also
     * hand the renderer a format that no longer applies — the value it would
     * format is a name by then, not the id the hint described.
     *
     * @param string $what how to name this field in a warning
     */
    private function repoint(string $key, string &$hint, Dataset $dataset, string $what): string
    {
        if ($hint === '' || !isset(TemplateConverter::PROVIDER_RESOLVED[$hint])) return $key;

        $resolved = $key . TemplateConverter::PROVIDER_RESOLVED[$hint];
        if ($dataset->field($resolved) !== null) {
            // The dataset serves it directly — a computed field beats a lookup,
            // because it happens in the one query.
            $key = $resolved;
        } elseif (in_array($hint, self::PROVIDER_LOOKUPS, true)) {
            // No dataset field, but the provider knows this lookup. The field
            // takes the resolved key and the provider is told which id to
            // derive it from.
            $this->resolved[$resolved] = $key;
            $key = $resolved;
        } else {
            $this->warnings[] = "$what needs '$hint', which neither this dataset"
                              . " (as '$resolved') nor the provider can serve;"
                              . " it shows '$key' unresolved.";
        }
        $hint = '';   // resolved, or refused by the renderer anyway
        return $key;
    }

    /**
     * The field key the report groups on, or null.
     *
     * A group key carries a `processing` hint like any other field, and shipped
     * reports group by a contact id with the `branch` hint. Without the same
     * re-pointing the columns get, the break is correct but the heading reads
     * "Group Total For: 128" where the name belongs. Call this AFTER
     * convertColumns(), which resets the resolved-column list this adds to.
     */
    /**
     * Behaviour a special_class supplied that its definition does not state.
     *
     * `aged_summary` rolls its rows up per account in PHP and emits one row per
     * account, whatever the definition's grouplist says — the XML marks its
     * `bill_acct_id` group `default=0`, so nothing in the file asks for it. The
     * roll-up IS a group break with the data rows hidden, so converting the
     * report means saying so explicitly rather than reproducing the class.
     *
     * Keyed on the class being replaced, because that is where the behaviour
     * actually lived.
     */
    private const SPECIAL_CLASS_PROFILES = [
        'aged_summary' => ['group' => 'bill_acct_id', 'totalsOnly' => true],
    ];

    /** Whether this report shows only its group breaks, never the rows beneath. */
    public function convertTotalsOnly(object $report): bool
    {
        $profile = self::SPECIAL_CLASS_PROFILES[$this->scalar($report->special_class ?? null)] ?? null;
        return $this->truthy($report->totalonly ?? null) || ($profile['totalsOnly'] ?? false);
    }

    public function convertGroup(object $report, Dataset $dataset): ?string
    {
        $profile = self::SPECIAL_CLASS_PROFILES[$this->scalar($report->special_class ?? null)] ?? null;
        if ($profile !== null && isset($profile['group'])) {
            if ($dataset->field($profile['group']) !== null) return $profile['group'];
            $this->warnings[] = "the '{$this->scalar($report->special_class)}' roll-up needs"
                              . " '{$profile['group']}', which this dataset does not provide;"
                              . " the report is not grouped.";
            return null;
        }
        foreach ($this->entries($report->grouplist ?? null) as $entry) {
            if (!$this->truthy($entry->default ?? null)) continue;

            $legacy = $this->scalar($entry->fieldname ?? null);
            $key    = $this->mapName($legacy);
            if ($key === null) {
                $this->warnings[] = "group references '$legacy', which this dataset"
                                  . " does not provide; the report is not grouped.";
                return null;
            }
            $hint = $this->scalar($entry->processing ?? null);
            return $this->repoint($key, $hint, $dataset, "group '$legacy'");
        }
        return null;
    }

    /**
     * The formatting applied to the group's label, if any.
     *
     * Hints convertGroup() re-points are excluded: by the time the label
     * reaches a renderer it holds the resolved name, and formatting that as
     * though it were still an id would undo the lookup.
     */
    public function convertGroupFormat(object $report): ?Format
    {
        foreach ($this->entries($report->grouplist ?? null) as $entry) {
            if (!$this->truthy($entry->default ?? null)) continue;
            $hint = $this->scalar($entry->processing ?? null);
            if ($hint === '' || isset(TemplateConverter::PROVIDER_RESOLVED[$hint])) return null;
            return new Format($hint);
        }
        return null;
    }

    /**
     * Carry the report's page furniture across the seam.
     *
     * PhreeForm keeps a font/size/colour/align per band under `$report->page`,
     * and the values really do differ between reports — see ReportStyle. The
     * legacy engine read them straight out of the global `$report` inside
     * Header(); here they become document data so the renderer needs no globals.
     *
     * Anything absent stays null, which leaves the renderer's default in force
     * rather than inventing a value the report never specified.
     */
    /**
     * Page size, orientation and margins.
     *
     * Delegated, because reports and forms share one `<page>` block and one
     * meaning for it. Skipping this is not cosmetic: a report left on the
     * PageSetup defaults gets 10 mm margins all round, which moves the page
     * bottom and changes where every page breaks.
     */
    public function convertPageSetup(object $report): PageSetup
    {
        return ($this->templates ??= new TemplateConverter())->convertPageSetup($report);
    }

    public function convertStyle(object $report): ReportStyle
    {
        $page = $report->page ?? null;
        if (!is_object($page)) return new ReportStyle();

        $band = function (?object $b): ?Style {
            if (!is_object($b)) return null;
            $font   = $this->scalar($b->font ?? null);
            $size   = $this->number($b->size ?? null);
            $colour = $this->scalar($b->color ?? null);
            $align  = $this->align($this->scalar($b->align ?? null));
            // An entirely empty band carries no intent; leave it to the renderer.
            if ($font === '' && $size === null && $colour === '' && $align === null) return null;
            return new Style(
                font:   $font === '' ? null : $font,
                size:   $size,
                colour: $colour === '' ? null : Colour::fromLegacy($colour),
                align:  $align,
            );
        };

        // `show` absent means shown: PhreeForm only ever writes the flag when a
        // band is switched off, so treating "missing" as false would blank the
        // company name on most of the library.
        $shown = function (?object $b): bool {
            if (!is_object($b) || !property_exists($b, 'show')) return true;
            return $this->truthy($b->show);
        };

        $text = function (?object $b): ?string {
            if (!is_object($b)) return null;
            $t = $this->scalar($b->text ?? null);
            return $t === '' ? null : $t;
        };

        return new ReportStyle(
            heading:        $band($page->heading ?? null),
            showHeading:    $shown($page->heading ?? null),
            title1:         $band($page->title1 ?? null),
            showTitle1:     $shown($page->title1 ?? null),
            title1Text:     $text($page->title1 ?? null),
            title2:         $band($page->title2 ?? null),
            showTitle2:     $shown($page->title2 ?? null),
            title2Text:     $text($page->title2 ?? null),
            filter:         $band($page->filter ?? null),
            data:           $band($page->data ?? null),
            totals:         $band($page->totals ?? null),
            truncate:       $this->truthy($report->truncate ?? null),
            groupPageBreak: $this->truthy($report->grpbreak ?? null),
        );
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
     * over-filters.
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
     * The report's date range, as filters on its date field.
     *
     * `$report->datefield` names the column the range applies to, and it is not
     * always a date: a definition may store `description` there, harmless only
     * while its preference is "all dates", or a bare `post_date` with no table.
     * Both go through the same `mapName()` as every other field, so both either
     * map or produce a warning.
     *
     * The range is emitted as `>= start` AND `< endExclusive`, never as a
     * BETWEEN — see DateRange. A preference that constrains nothing returns no
     * filters at all rather than a pair of always-true comparisons.
     *
     * Call this AFTER convertColumns(), which builds the field map.
     *
     * @return list<Filter>
     */
    public function convertDateFilters(object $report, Dataset $dataset, DateRange $range): array
    {
        return DateRangeFilters::build($dataset, function () use ($report): ?string {
            $legacy = $this->scalar($report->datefield ?? null);
            $key    = $this->mapName($legacy);
            if ($key === null) {
                $this->warnings[] = "the date range applies to '$legacy', which this dataset does not"
                                  . " provide; the report is not date-filtered.";
            }
            return $key;
        }, $range, $this->warnings);
    }

    /**
     * The dataset field key the date range applies to, for a native definition.
     *
     * Same mapping as convertDateFilters() performs, but stored rather than
     * applied: the range itself depends on today's date and so must stay a run
     * time question, while WHICH field it constrains is settled at conversion.
     */
    public function convertDateField(object $report, Dataset $dataset): ?string
    {
        if ($this->fieldMap === []) $this->fieldMap = TemplateConverter::mapFor($dataset);
        return $this->mapName($this->scalar($report->datefield ?? null));
    }

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
        // Usable on its own, not only after convertColumns().
        //
        // The field map is built there because that is where a report starts,
        // but a FORM never calls it — a form's elements are converted by
        // TemplateConverter — and its invisible filters are exactly the ones
        // that make a Purchase Order a purchase order. Without this the map is
        // empty, every filter reports "no dataset field", and the form prints
        // against every journal row on file. Set only when empty, so a report's
        // existing warnings and resolved columns are untouched.
        if ($this->fieldMap === []) $this->fieldMap = TemplateConverter::mapFor($dataset);

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
