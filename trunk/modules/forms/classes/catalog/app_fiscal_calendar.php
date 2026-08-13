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
//  Path: /modules/forms/classes/catalog/app_fiscal_calendar.php
//
/**
 * Forms — the accounting calendar, read from the application.
 *
 * The one class that knows `accounting_periods` exists and that
 * CURRENT_ACCOUNTING_PERIOD is a constant. DateRangeResolver holds the
 * arithmetic and this holds the lookups, so the arithmetic can be tested on a
 * fiscal year that no install here actually uses.
 *
 * It queries the table rather than calling `gen_calculate_fiscal_dates()`, for
 * two reasons. That function pushes onto the global `$messageStack` when a
 * period is undefined — a side effect that would surface a red banner on an
 * unrelated page for what is, here, a question with a legitimate "no" answer.
 * And it queries once per call: a "this year" range asks for two periods and a
 * report header asks again for the same ones, which is four round trips for
 * twelve rows that never change during a request.
 */

namespace FreeBooks\Forms\Catalog;

final class AppFiscalCalendar implements FiscalCalendar
{
    /** @var ?array<int,array{start: string, end: string}> the whole table, once */
    private ?array $periods = null;

    public function __construct(
        private readonly \queryFactory $db,
        /**
         * Overrides CURRENT_ACCOUNTING_PERIOD. A report run for a closed year
         * needs to ask what "this quarter" meant then, not what it means now.
         */
        private readonly ?int $currentPeriod = null,
    ) {}

    public function currentPeriod(): int
    {
        if ($this->currentPeriod !== null) return $this->currentPeriod;
        return defined('CURRENT_ACCOUNTING_PERIOD') ? (int)CURRENT_ACCOUNTING_PERIOD : 0;
    }

    public function periodDates(int $period): ?array
    {
        return $this->load()[$period] ?? null;
    }

    /** @return array<int,array{start: string, end: string}> */
    private function load(): array
    {
        if ($this->periods !== null) return $this->periods;

        $table   = defined('TABLE_ACCOUNTING_PERIODS') ? TABLE_ACCOUNTING_PERIODS : 'accounting_periods';
        $result  = $this->db->Execute("SELECT period, start_date, end_date FROM $table ORDER BY period");
        $periods = [];
        while (!$result->EOF) {
            // A period with no dates is not a period. Legacy would hand the
            // sentinel '0000-00-00' straight into a comparison, which MySQL
            // accepts and then matches nothing; treating it as undefined lets
            // the caller say so instead.
            $start = (string)$result->fields['start_date'];
            $end   = (string)$result->fields['end_date'];
            if ($start !== '' && $start !== '0000-00-00' && $end !== '' && $end !== '0000-00-00') {
                $periods[(int)$result->fields['period']] = ['start' => $start, 'end' => $end];
            }
            $result->MoveNext();
        }
        return $this->periods = $periods;
    }
}
