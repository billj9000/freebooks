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
//  Path: /modules/forms/classes/catalog/date_range.php
//
/**
 * Forms — resolves a report's date preference to a pair of dates.
 *
 * The replacement for `gen_build_sql_date()`, which does three jobs at once:
 * it turns a preference code into a start/end pair, concatenates a SQL
 * fragment, and composes the human "Report Filters:" sentence. Only the first
 * of those belongs anywhere near a query builder, so only the first is here.
 * The caller turns the result into `Filter`s, and composes the sentence where
 * locale and language constants legally live.
 *
 * ## Two details that must survive exactly
 *
 * **The range is half-open.** Legacy emits `>= start AND < end`, where `end` is
 * the day AFTER the last day wanted. It is not a `BETWEEN`, and rewriting it as
 * one would be wrong: `post_date` is a DATE in most tables but a DATETIME in
 * some, and an inclusive upper bound silently drops everything recorded after
 * midnight on the closing day. `$endExclusive` is named for this.
 *
 * **`z` does not filter the date field at all.** "By period" compares
 * `period = N` — a different column, of a different type, on a table that may
 * not even carry the date field. It is modelled as its own thing rather than
 * squeezed into a date range that happens to cover the period's days, because
 * a journal can be posted into a period whose date range it falls outside.
 *
 * ## What it deliberately does not inherit
 *
 * `gen_calculate_fiscal_dates()` returns `false` for an undefined period and
 * pushes an error onto the global `$messageStack`. Legacy then indexes `false`
 * as an array, which under PHP 8 warns and yields null, so the report runs with
 * `>= '' and < ''` and quietly returns nothing. Here an unresolvable period
 * produces a range that reports itself as unresolved, and the caller decides.
 *
 * Nothing in this file reads a global, queries the database or formats a date
 * for display. `today` is injected so the boundary cases are testable at all —
 * a week that straddles a month end, or a fiscal year that does not start in
 * January, cannot be reached by waiting for the calendar.
 */

namespace FreeBooks\Forms\Catalog;

/**
 * The date ranges a report may ask for.
 *
 * The letters are PhreeForm's, stored in `$report->datedefault` and in the URLs
 * of every saved report link, so they are the enum's backing values rather than
 * something tidier. A closed set means a stored string can never become
 * behaviour — the same reason `FilterOperator` is an enum.
 *
 * `m` through `y` are unused: PhreeForm's switch has no cases for them, and the
 * gap is preserved rather than compacted so an old link keeps its meaning.
 */
enum DatePreference: string
{
    case All            = 'a';
    case Range          = 'b';
    case Today          = 'c';
    case ThisWeek       = 'd';
    case WeekToDate     = 'e';
    case ThisMonth      = 'f';
    case MonthToDate    = 'g';
    case ThisQuarter    = 'h';
    case QuarterToDate  = 'i';
    case ThisYear       = 'j';
    case YearToDate     = 'k';
    case ThisPeriod     = 'l';
    case ByPeriod       = 'z';

    /**
     * PhreeForm's `default:` arm falls through to `a`, so an unrecognised code
     * means "all dates" rather than an error. A report saved by a later version,
     * or a hand-edited link, therefore still runs.
     *
     * Matched exactly, with no trimming or case folding, because legacy's
     * `switch` is exact: 'Z' means "all dates" there, and normalising it here
     * would quietly turn one saved link into a period filter.
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? self::All;
    }

    /** True when the preference is measured against the accounting calendar. */
    public function isFiscal(): bool
    {
        return match ($this) {
            self::ThisQuarter, self::QuarterToDate, self::ThisYear,
            self::YearToDate, self::ThisPeriod, self::ByPeriod => true,
            default => false,
        };
    }
}

/**
 * A resolved date preference: two ISO dates, or a period number, or neither.
 *
 * Deliberately not a filter and not a sentence. The same range becomes a pair
 * of `Filter`s for the query builder and a line of text for the report header,
 * and those two jobs need different things from it.
 */
final readonly class DateRange
{
    /**
     * @param ?string $start        first day wanted, ISO, inclusive
     * @param ?string $endExclusive day AFTER the last day wanted, ISO
     * @param ?int    $period       set only for ByPeriod
     * @param ?string $unresolved   why this range is empty, if it is
     */
    public function __construct(
        public DatePreference $preference,
        public ?string        $start        = null,
        public ?string        $endExclusive = null,
        public ?int           $period       = null,
        public ?string        $unresolved   = null,
    ) {}

    /** True when the range constrains nothing — `a`, or a period that would not resolve. */
    public function isOpen(): bool
    {
        return $this->start === null && $this->endExclusive === null && $this->period === null;
    }

    /**
     * The last day wanted, inclusive — for display only.
     *
     * The header says "to 31/07/2026", never "to 01/08/2026", so the exclusive
     * bound has to come back a day before anyone shows it.
     */
    public function endInclusive(): ?string
    {
        if ($this->endExclusive === null) return null;
        return date('Y-m-d', strtotime($this->endExclusive . ' -1 day'));
    }
}

/**
 * A resolved DateRange, applied to a dataset as ordinary bound filters.
 *
 * Shared rather than duplicated, and the sharing is the point. A date
 * preference is resolved at RUN time — "this quarter" depends on today and on
 * the accounting calendar — so it is the one part of a definition that cannot
 * be converted once and stored. Both the legacy converter and the native
 * runner therefore have to turn the same DateRange into the same filters, and
 * two implementations of that would be two different reports.
 *
 * The only thing the legacy side does first is map its stored `datefield` name
 * onto a dataset field key; from there the work is identical.
 */
final class DateRangeFilters
{
    /**
     * The dataset field holding an accounting period, if it has one.
     *
     * Matched on the column rather than the key, because the datasets name it
     * differently — `period` on transactions, `history_period` on general
     * ledger — and both are `<table>.period` underneath.
     */
    public static function periodKey(Dataset $dataset): ?string
    {
        foreach ($dataset->fields as $key => $field) {
            if ($field->column === 'period') return $key;
        }
        return null;
    }

    /**
     * The range as filters, or none.
     *
     * The range is emitted as `>= start` AND `< endExclusive`, never as a
     * BETWEEN — see DateRange. A preference that constrains nothing returns no
     * filters at all rather than a pair of always-true comparisons.
     *
     * The date field arrives as a CALLABLE, not a string, and that is not
     * ceremony: the by-period branch never looks at it, and the legacy caller
     * warns when it cannot map the stored name. Resolving eagerly would emit
     * that warning on reports where the field is irrelevant, and emit it before
     * the unresolved-range note rather than after.
     *
     * @param callable(): ?string $dateFieldKey the dataset field the range
     *   applies to; null means the definition names one this dataset cannot serve
     * @param list<string>       &$warnings     appended to, never replaced
     * @return list<Filter>
     */
    public static function build(Dataset $dataset, callable $dateFieldKey, DateRange $range, array &$warnings): array
    {
        // A partly-resolved range still narrows the report, and narrowing is the
        // safe direction: an unfiltered report whose header claims a quarter is
        // the outcome to avoid. So the note is reported and whatever bounds
        // exist are applied.
        if ($range->unresolved !== null) $warnings[] = "date range: {$range->unresolved}";
        if ($range->isOpen()) return [];

        // "By period" compares a period number, not a date, so it needs the
        // dataset's period field rather than the report's date field. Three of
        // the seven shipped datasets have no period at all; there the range is
        // dropped with a warning, since the alternative is a report that
        // silently covers all time while claiming a period in its header.
        if ($range->period !== null) {
            $periodKey = self::periodKey($dataset);
            if ($periodKey === null) {
                $warnings[] = "the '{$dataset->id}' dataset has no accounting period field,"
                            . " so the by-period range was not applied.";
                return [];
            }
            return [Filter::equals($periodKey, $range->period)];
        }

        $key = $dateFieldKey();
        if ($key === null) return [];

        $filters = [];
        if ($range->start !== null) {
            $filters[] = new Filter($key, FilterOperator::GreaterEqual, [$range->start]);
        }
        if ($range->endExclusive !== null) {
            $filters[] = new Filter($key, FilterOperator::LessThan, [$range->endExclusive]);
        }
        return $filters;
    }
}

/**
 * Where the accounting calendar comes from.
 *
 * An interface because the fiscal cases are the only ones that need the
 * database, and mocking it is what makes "this year" testable on a fiscal year
 * that does not start in January — which no FreeBooks install here uses, and
 * which is exactly why it would otherwise go untested.
 */
interface FiscalCalendar
{
    /** The period the application considers current. */
    public function currentPeriod(): int;

    /**
     * @return ?array{start: string, end: string} ISO dates, or null if the
     *   period is not defined. `end` is the last day IN the period.
     */
    public function periodDates(int $period): ?array;
}

/**
 * Turns a stored preference string into a DateRange.
 *
 * The string is PhreeForm's colon-delimited triple: code, then two operands
 * whose meaning depends on the code. For `b` they are the from/to dates a user
 * typed; for `z` the first is a period number; every other code ignores them.
 */
final class DateRangeResolver
{
    public function __construct(
        private readonly FiscalCalendar $calendar,
        /**
         * The day "today" means. Injected rather than read from the clock so a
         * test can stand on 31 December, and so every range in one report is
         * measured against the same instant — legacy calls `time()` afresh in
         * each case arm.
         */
        private readonly string $today = '',
    ) {}

    private function today(): string
    {
        return $this->today !== '' ? $this->today : date('Y-m-d');
    }

    /**
     * @param string $prefs `code[:operand[:operand]]`, e.g. `b:2026-01-01:2026-03-31`
     */
    public function resolve(string $prefs): DateRange
    {
        $parts      = explode(':', $prefs);
        $preference = DatePreference::fromCode($parts[0] ?? '');
        $today      = $this->today();

        return match ($preference) {
            DatePreference::All => new DateRange($preference),

            DatePreference::Range => new DateRange(
                $preference,
                // Either bound may be blank: "everything up to March" is a
                // legitimate answer, and legacy emits only the half it was
                // given. A blank bound is null here, not the sentinel
                // '0000-00-00'/'2199-00-00' legacy carries around and then
                // does not use.
                start:        $this->isoOrNull($parts[1] ?? ''),
                endExclusive: $this->dayAfter($this->isoOrNull($parts[2] ?? '')),
            ),

            DatePreference::Today => new DateRange(
                $preference, $today, $this->dayAfter($today)),

            DatePreference::ThisWeek => new DateRange(
                $preference, $this->weekStart($today),
                $this->dayAfter(date('Y-m-d', strtotime($this->weekStart($today) . ' +6 days')))),

            DatePreference::WeekToDate => new DateRange(
                $preference, $this->weekStart($today), $this->dayAfter($today)),

            DatePreference::ThisMonth => new DateRange(
                $preference, $this->monthStart($today),
                $this->dayAfter($this->monthEnd($today))),

            DatePreference::MonthToDate => new DateRange(
                $preference, $this->monthStart($today), $this->dayAfter($today)),

            DatePreference::ThisQuarter   => $this->fiscalSpan($preference, 3, false, $today),
            DatePreference::QuarterToDate => $this->fiscalSpan($preference, 3, true,  $today),
            DatePreference::ThisYear      => $this->fiscalSpan($preference, 12, false, $today),
            DatePreference::YearToDate    => $this->fiscalSpan($preference, 12, true,  $today),

            DatePreference::ThisPeriod => $this->onePeriod(
                $preference, $this->calendar->currentPeriod(), false),

            // The period is the filter; the dates it resolves to are carried
            // only so the header can say which days it covers.
            DatePreference::ByPeriod => $this->onePeriod(
                $preference, (int)($parts[1] ?? 0), true),
        };
    }

    // ── the fiscal cases ─────────────────────────────────────────────────────

    /**
     * A run of periods starting at the current quarter or year boundary.
     *
     * The arithmetic is PhreeForm's and it assumes periods are months:
     * `current - ((current - 1) % span)` steps back to the first period of the
     * enclosing quarter or year. That is correct for the 12-period calendar
     * every FreeBooks install uses, and would be wrong for a 13-period one —
     * noted rather than fixed, because changing it would change which rows a
     * shipped report returns.
     */
    private function fiscalSpan(DatePreference $preference, int $span, bool $toDate, string $today): DateRange
    {
        $current = $this->calendar->currentPeriod();
        $first   = $current - (($current - 1) % $span);

        $start = $this->calendar->periodDates($first);
        if ($toDate) {
            // The closing bound is today either way, so a missing opening
            // period costs only the lower bound rather than the whole range.
            return new DateRange($preference, $start['start'] ?? null, $this->dayAfter($today),
                unresolved: $start === null
                    ? "accounting period $first is not defined, so this range has no start." : null);
        }

        $last    = $this->calendar->periodDates($first + $span - 1);
        $missing = [];
        if ($start === null) $missing[] = $first;
        if ($last  === null) $missing[] = $first + $span - 1;

        // Whichever bound resolved is still applied. A half-open range is a
        // narrower answer than no range at all, and narrowing is the safe
        // direction to fail in: an unfiltered report claiming a quarter in its
        // header is the outcome worth avoiding.
        return new DateRange(
            $preference,
            $start['start'] ?? null,
            $last === null ? null : $this->dayAfter($last['end']),
            unresolved: $missing === [] ? null
                : 'accounting period ' . implode(' and ', $missing) . ' not defined.',
        );
    }

    /**
     * One accounting period, as dates and — for `z` — as the period itself.
     *
     * For `z` the period number stands on its own: filtering on `period = 999`
     * is a valid question with the answer "no rows", and it does not need the
     * calendar to ask it. Only the header text does, so an undefined period
     * loses its dates and keeps its filter. Dropping the filter instead would
     * turn "period 999" into "all time", which is the one wrong answer that
     * looks like a working report.
     */
    private function onePeriod(DatePreference $preference, int $period, bool $filterOnPeriod): DateRange
    {
        $dates = $this->calendar->periodDates($period);
        if ($dates === null) {
            return new DateRange(
                $preference,
                period:     $filterOnPeriod ? $period : null,
                unresolved: "accounting period $period is not defined, so its dates are unknown.",
            );
        }
        return new DateRange(
            $preference,
            start:        $dates['start'],
            endExclusive: $this->dayAfter($dates['end']),
            period:       $filterOnPeriod ? $period : null,
        );
    }

    // ── calendar arithmetic ──────────────────────────────────────────────────

    /**
     * The Sunday on or before $date.
     *
     * Sunday because that is what legacy's `date('j') - date('w')` produces, not
     * because it is right for the locale. Changing it would move rows between
     * weeks in every "this week" report ever printed.
     */
    private function weekStart(string $date): string
    {
        $t = strtotime($date);
        return date('Y-m-d', strtotime('-' . (int)date('w', $t) . ' days', $t));
    }

    private function monthStart(string $date): string
    {
        return date('Y-m-01', strtotime($date));
    }

    private function monthEnd(string $date): string
    {
        return date('Y-m-t', strtotime($date));
    }

    private function dayAfter(?string $date): ?string
    {
        if ($date === null) return null;
        return date('Y-m-d', strtotime($date . ' +1 day'));
    }

    /**
     * Accept only a date already in database format.
     *
     * Legacy runs the operands through `gen_db_date()`, which parses the user's
     * locale format and — on a value it cannot parse — pushes an error onto the
     * global message stack and silently substitutes TODAY. Neither the global
     * nor that substitution belongs here: a range nobody asked for is worse
     * than no range. Conversion from locale format happens in the page that
     * reads the form field, so what arrives is ISO or nothing.
     */
    private function isoOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
