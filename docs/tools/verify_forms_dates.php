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
//  Path: /docs/tools/verify_forms_dates.php
//
/**
 * Prove DateRangeResolver against gen_build_sql_date() (design §11 step 9).
 *
 * The comparison is made on what actually reaches the WHERE clause, not on the
 * legacy function's return array: `gen_build_sql_date()` reports `start_date`
 * '0000-00-00' and `end_date` '2199-00-00' for ranges it then does not
 * constrain, so its own fields say a bound exists where the SQL says otherwise.
 * The bounds are read back out of the emitted SQL instead.
 *
 * Verification is in three parts, because the legacy function reads the clock
 * directly and cannot be told what day it is:
 *
 *  1. **Every code, today.** All thirteen preferences resolved by both, on
 *     whatever day this runs. Authoritative, but only at one instant.
 *  2. **Clock-independent codes, exhaustively.** `b` (explicit range) over a
 *     grid of bounds including month ends, leap day, year end and one-sided
 *     ranges; `z` over every defined accounting period. These do not consult
 *     the clock, so they compare properly at many points.
 *  3. **Boundary arithmetic, pinned.** The resolver alone against literal
 *     expected dates on days chosen to straddle a week, month, quarter and
 *     fiscal-year edge, with a stub calendar. The expectations are written out
 *     by hand — computing them would only re-run the code under test.
 *
 * NOT part of the application. A dev aid, run inside the container.
 *
 * Usage:
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_dates.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);

require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');

use FreeBooks\Forms\Catalog\{AppFiscalCalendar, DatePreference, DateRange, DateRangeResolver, FiscalCalendar};

$failures = 0;
$checks   = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures, $checks;
    $checks++;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/**
 * Pull the effective bounds back out of a legacy SQL fragment.
 *
 * @return array{start: ?string, end: ?string, period: ?int}
 */
function legacyBounds(string $sql): array
{
    $out = ['start' => null, 'end' => null, 'period' => null];
    if (preg_match("/>=\s*'([^']*)'/", $sql, $m)) $out['start'] = $m[1];
    if (preg_match("/<\s*'([^']*)'/",  $sql, $m)) $out['end']   = $m[1];
    if (preg_match('/period\s*=\s*(\d+)/', $sql, $m)) $out['period'] = (int)$m[1];
    return $out;
}

function describe(?string $s, ?string $e, ?int $p): string
{
    if ($p !== null) return "period=$p";
    return '[' . ($s ?? '-') . ' .. ' . ($e ?? '-') . ')';
}

/** Compare one preference string through both implementations. */
function compare(DateRangeResolver $resolver, string $prefs, string $field = 'journal_main.post_date'): void
{
    $legacy = gen_build_sql_date($prefs, $field);
    $l      = legacyBounds((string)$legacy['sql']);
    $range  = $resolver->resolve($prefs);

    $mine = describe($range->start, $range->endExclusive, $range->period);
    $his  = describe($l['start'], $l['end'], $l['period']);
    ok("'$prefs' -> $mine", $mine === $his, "legacy says $his");
}

echo "company: $company\n";
echo "today:   " . date('Y-m-d') . " (period " . (defined('CURRENT_ACCOUNTING_PERIOD') ? CURRENT_ACCOUNTING_PERIOD : '?') . ")\n\n";

$calendar = new AppFiscalCalendar($db);
$resolver = new DateRangeResolver($calendar);

// ── 1. every code, on today's clock ──────────────────────────────────────────
echo "=== every preference, resolved by both engines today ===\n";
foreach (DatePreference::cases() as $preference) {
    $prefs = match ($preference) {
        DatePreference::Range    => 'b:2026-03-01:2026-03-31',
        DatePreference::ByPeriod => 'z:' . (defined('CURRENT_ACCOUNTING_PERIOD') ? CURRENT_ACCOUNTING_PERIOD : 1),
        default                  => $preference->value,
    };
    compare($resolver, $prefs);
}

// An unknown code must mean "all dates", as legacy's default: arm does.
$range = $resolver->resolve('Q');
ok("an unrecognised code falls back to All", $range->preference === DatePreference::All && $range->isOpen());

// ── 2. the codes that ignore the clock ───────────────────────────────────────
echo "\n=== explicit ranges (clock-independent, so comparable at any date) ===\n";
$grid = [
    '2026-01-01:2026-12-31',   // whole year
    '2026-01-31:2026-02-01',   // month end into the next
    '2024-02-28:2024-02-29',   // leap day as the closing bound
    '2023-02-28:2023-03-01',   // the same days in a non-leap year
    '2026-12-31:2027-01-01',   // year end
    '2026-06-15:2026-06-15',   // a single day
    '2026-06-15:',             // open-ended
    ':2026-06-15',             // open-started
    ':',                       // both blank - constrains nothing
];
foreach ($grid as $g) compare($resolver, "b:$g");

echo "\n=== by period, over every defined period ===\n";
$result  = $db->Execute("SELECT period FROM " . TABLE_ACCOUNTING_PERIODS . " ORDER BY period");
$periods = [];
while (!$result->EOF) { $periods[] = (int)$result->fields['period']; $result->MoveNext(); }
foreach ($periods as $p) compare($resolver, "z:$p");
printf("  (%d periods defined)\n", count($periods));

// An undefined period still filters on the period. Dropping the filter would
// turn "period 999" into "all time" — the one wrong answer that looks like a
// working report. Legacy emits `period = 999` too, so this compares rather than
// diverges; what it loses is only the dates the header would have shown.
compare($resolver, 'z:999');
$range = $resolver->resolve('z:999');
ok("an undefined period keeps its filter and says why it has no dates",
    $range->period === 999 && $range->start === null && $range->unresolved !== null,
    $range->unresolved ?? 'no reason given');

// ── 3. boundary arithmetic on pinned days ────────────────────────────────────
echo "\n=== boundary arithmetic, on days chosen to straddle an edge ===\n";

/**
 * A fiscal year of twelve calendar months starting in April — deliberately not
 * a January year, because every install here uses one and the quarter/year
 * arithmetic would otherwise never be exercised off-calendar.
 */
final class StubCalendar implements FiscalCalendar
{
    public function __construct(private int $current) {}
    public function currentPeriod(): int { return $this->current; }
    public function periodDates(int $period): ?array
    {
        if ($period < 1 || $period > 24) return null;
        $month = 3 + $period;                       // period 1 = April
        $year  = 2026 + intdiv($month - 1, 12);
        $month = (($month - 1) % 12) + 1;
        return [
            'start' => sprintf('%04d-%02d-01', $year, $month),
            'end'   => date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)),
        ];
    }
}

/** @param array{0:string,1:?string,2:?string} ...$cases code, expected start, expected end */
$pinned = [
    // 2026-08-01 is a Saturday, so its week began on Sunday 2026-07-26.
    ['2026-08-01', 1, 'd', '2026-07-26', '2026-08-02'],
    ['2026-08-01', 1, 'e', '2026-07-26', '2026-08-02'],
    // 2026-08-02 is itself a Sunday: the week starts on the day.
    ['2026-08-02', 1, 'd', '2026-08-02', '2026-08-09'],
    // A Saturday at the very end of a month, so the week spans two months.
    ['2026-02-28', 1, 'd', '2026-02-22', '2026-03-01'],
    // February in a leap year: the month must end on the 29th.
    ['2024-02-10', 1, 'f', '2024-02-01', '2024-03-01'],
    ['2023-02-10', 1, 'f', '2023-02-01', '2023-03-01'],
    ['2026-12-31', 1, 'f', '2026-12-01', '2027-01-01'],
    ['2026-12-31', 1, 'g', '2026-12-01', '2027-01-01'],
    // Fiscal quarters on an April year: period 5 is August, whose quarter is
    // periods 4-6 (July, August, September).
    ['2026-08-15', 5, 'h', '2026-07-01', '2026-10-01'],
    ['2026-08-15', 5, 'i', '2026-07-01', '2026-08-16'],
    // Period 4 begins a quarter, so it is its own quarter's first period.
    ['2026-07-15', 4, 'h', '2026-07-01', '2026-10-01'],
    // The fiscal year runs April to March, crossing the calendar year end.
    ['2026-08-15', 5, 'j', '2026-04-01', '2027-04-01'],
    ['2027-02-15', 11, 'j', '2026-04-01', '2027-04-01'],
    ['2027-02-15', 11, 'k', '2026-04-01', '2027-02-16'],
    ['2026-08-15', 5, 'l', '2026-08-01', '2026-09-01'],
    ['2026-08-15', 5, 'c', '2026-08-15', '2026-08-16'],
];
foreach ($pinned as [$today, $period, $code, $expectStart, $expectEnd]) {
    $r = (new DateRangeResolver(new StubCalendar($period), $today))->resolve($code);
    $got = describe($r->start, $r->endExclusive, $r->period);
    ok("on $today (period $period), '$code' -> [$expectStart .. $expectEnd)",
        $r->start === $expectStart && $r->endExclusive === $expectEnd, "got $got");
}

// The end shown to a user is the last day IN the range, not the exclusive bound.
$r = (new DateRangeResolver(new StubCalendar(5), '2026-08-15'))->resolve('f');
ok("endInclusive() steps back from the exclusive bound",
    $r->endInclusive() === '2026-08-31', 'got ' . var_export($r->endInclusive(), true));

// A locale-formatted bound must not be guessed at. Legacy substitutes TODAY for
// anything it cannot parse, which silently reports the wrong month.
$r = $resolver->resolve('b:15/06/2026:');
ok("a non-ISO bound is dropped, not guessed", $r->start === null);

// A code is matched exactly. Legacy's switch is case-sensitive, so 'Z' means
// "all dates" there; folding it here would turn one saved link into a filter.
$r = $resolver->resolve('Z:14');
ok("an upper-case code is not folded into a preference", $r->preference === DatePreference::All);

// A fiscal range with one end missing keeps the end it has.
$partial = new class implements FiscalCalendar {
    public function currentPeriod(): int { return 5; }
    public function periodDates(int $period): ?array
    {
        return $period === 4 ? ['start' => '2026-07-01', 'end' => '2026-07-31'] : null;
    }
};
$r = (new DateRangeResolver($partial, '2026-08-15'))->resolve('h');
ok("a half-resolvable quarter keeps the bound it has",
    $r->start === '2026-07-01' && $r->endExclusive === null && $r->unresolved !== null,
    describe($r->start, $r->endExclusive, $r->period));

// ── 4. no globals below the resolver ─────────────────────────────────────────
echo "\n=== the resolver reaches nothing ===\n";
// Comments are stripped first: this file explains at length what it does NOT
// reach, and a naive grep would fail on its own documentation.
$code = '';
foreach (token_get_all(file_get_contents(DIR_FS_MODULES . 'forms/classes/catalog/date_range.php')) as $token) {
    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $code .= is_array($token) ? $token[1] : $token;
}
foreach (['global ', '$GLOBALS', '$db', 'messageStack', 'Execute(', 'CURRENT_ACCOUNTING_PERIOD'] as $forbidden) {
    ok("date_range.php contains no '$forbidden' outside its comments", !str_contains($code, $forbidden));
}

printf("\n%s - %d checks, %d failed.\n", $failures ? 'FAIL' : 'PASS', $checks, $failures);
exit($failures ? 1 : 0);
