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
//  Path: /modules/forms/classes/providers/financial_provider.php
//
/**
 * Forms — the Income Statement and Balance Sheet.
 *
 * Ports phreebooks/classes/is_budget.php and balance_sheet.php (with the
 * income_statement.php helper the latter instantiates). See §5 and §11.12.
 *
 * These two are not dataset-shaped, and that is the point of them being here
 * rather than in the catalog. A financial statement's rows are SECTIONS in a
 * fixed order — revenues, cost of sales, gross profit, expenses, net income —
 * not a query result grouped by something. No filter or sort a report author
 * could write would produce a balance sheet, so the structure is code.
 *
 * What they no longer do is format. Both legacy classes returned arrays of
 * ProcessData()-formatted strings, which is why the same definition could not
 * produce a readable PDF and a usable CSV. Every cell here is a TypedValue and
 * the renderer decides.
 *
 * Section headings, blank separators and subtotal captions are emitted as Data
 * rows carrying only their description, exactly as the legacy did. Modelling
 * them as their own RowKind would describe them better, and would let a renderer
 * style a subtotal differently from an account line — but that is a change to the
 * seam, so it is left for a decision of its own rather than smuggled in here.
 *
 * A provider sits ABOVE the seam, so database access here is correct and
 * expected; it is the returned Document that must be free of it.
 */

namespace FreeBooks\Forms\Provider;

use FreeBooks\Forms\Document\{
    Context, Document, DocumentProvider, PageSetup, ReportColumn,
    ReportStyle, Row, RowKind, TableDocument, TypedValue, ValueType
};

/**
 * The four period bounds a year-on-year statement compares.
 *
 * `is_budget` and `income_statement` each derive these inline, from the same
 * three queries, and disagree about nothing — so they are resolved once here.
 * The last-year comparison period is positional: the Nth period of the previous
 * fiscal year, not the same month, which is what makes a 13-period or a
 * part-year calendar work.
 */
final readonly class FiscalWindow
{
    public function __construct(
        public int $period,
        public int $firstPeriod,
        public int $lyPeriod,        // 0 when there is no prior fiscal year
        public int $lyFirstPeriod,   // 0 likewise
    ) {}

    public function hasLastYear(): bool { return $this->lyPeriod > 0; }

    public static function resolve(\queryFactory $db, int $period): self
    {
        $fy = $db->Execute("select fiscal_year from " . TABLE_ACCOUNTING_PERIODS
            . " where period = " . $period)->fields['fiscal_year'] ?? null;
        if ($fy === null) return new self($period, $period, 0, 0);

        $first = (int)($db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
            . " where fiscal_year = " . (int)$fy . " order by period limit 1")->fields['period'] ?? $period);

        $priorFirst = $db->Execute("select period from " . TABLE_ACCOUNTING_PERIODS
            . " where fiscal_year = " . ((int)$fy - 1) . " order by period limit 1")->fields['period'] ?? null;
        if ($priorFirst === null) return new self($period, $first, 0, 0);

        $lyFirst = (int)$priorFirst;
        return new self($period, $first, $period - $first + $lyFirst, $lyFirst);
    }
}

/**
 * Shared machinery: both statements walk the chart of accounts by account type
 * and lay the result out in sections.
 */
abstract class FinancialStatementProvider implements DocumentProvider
{
    public function __construct(
        protected readonly \queryFactory $db,
        /** @var list<ReportColumn> */
        protected readonly array         $columns,
        protected readonly int           $period,
        protected readonly string        $title = 'Report',
        protected readonly Context       $context = new Context(),
        protected readonly PageSetup     $page = new PageSetup(),
        protected readonly ReportStyle   $style = new ReportStyle(),
    ) {}

    /** The key of the column holding row captions — always the first. */
    protected function captionKey(): string
    {
        return $this->columns[0]->key ?? 'description';
    }

    /**
     * A row with a caption and any of the value columns filled.
     *
     * @param array<string,int|float|null> $values keyed by column key
     */
    protected function row(string $caption, array $values = []): Row
    {
        $cells = [];
        foreach ($this->columns as $i => $column) {
            if ($i === 0) {
                $cells[$column->key] = TypedValue::text($caption);
                continue;
            }
            $cells[$column->key] = array_key_exists($column->key, $values)
                ? TypedValue::money($values[$column->key])
                : TypedValue::text('');
        }
        return new Row(RowKind::Data, $cells);
    }

    /** A blank separator, as the legacy emitted between sections. */
    protected function blank(): Row
    {
        return $this->row('');
    }

    protected function document(array $rows): TableDocument
    {
        return new TableDocument(
            title:   $this->title,
            columns: $this->columns,
            rows:    $rows,
            context: $this->context,
            page:    $this->page,
            style:   $this->style,
        );
    }

    /** Language constant if defined, else the fallback — these live in phreebooks. */
    protected static function label(string $constant, string $fallback): string
    {
        return defined($constant) ? (string)constant($constant) : $fallback;
    }
}

/**
 * The Income Statement family: IncomeStatement, IncomeStatementw_Budget and
 * IncomeStmt-2Years, which are one report with different columns shown.
 *
 * Port of is_budget. Six virtual columns — this period, year to date, the same
 * two for last year, and budget for both — of which each definition displays a
 * subset. That is why one class serves three reports, and it stays that way.
 */
final class IncomeStatementDocumentProvider extends FinancialStatementProvider
{
    private const INCOME    = 30;
    private const COGS      = 32;
    private const EXPENSES  = 34;

    /** Column keys this provider computes, in the order is_budget declared them. */
    public const VALUE_KEYS = ['current', 'current_ytd', 'ly_current', 'ly_ytd', 'budget_cur', 'budget_ytd'];

    public function build(array $params): Document
    {
        $window = FiscalWindow::resolve($this->db, $this->period);
        $rows   = [];
        $gross  = $this->zero();

        // Revenues. Income accounts are held on the credit side, so the section
        // is negated to read as earnings rather than as a balance.
        $rows[] = $this->row(self::label('RW_FIN_REVENUES', 'Revenues'));
        $total  = $this->section(self::INCOME, true, $window, $rows);
        $gross  = $this->add($gross, $total);

        // Less cost of sales.
        $rows[] = $this->blank();
        $rows[] = $this->row(self::label('RW_FIN_COST_OF_SALES', 'Cost of Sales'));
        $total  = $this->section(self::COGS, false, $window, $rows);
        $gross  = $this->subtract($gross, $total);

        $rows[] = $this->blank();
        $rows[] = $this->row(self::label('RW_FIN_GROSS_PROFIT', 'Gross Profit'), $gross);

        // Less expenses.
        $rows[] = $this->blank();
        $rows[] = $this->row(self::label('RW_FIN_EXPENSES', 'Expenses'));
        $total  = $this->section(self::EXPENSES, false, $window, $rows);
        $net    = $this->subtract($gross, $total);

        $rows[] = $this->blank();
        $rows[] = $this->row(self::label('RW_FIN_NET_INCOME', 'Net Income'), $net);

        return $this->document($rows);
    }

    /**
     * One account-type section: a line per account, then its total.
     *
     * @param list<Row> $rows appended to
     * @return array<string,float>
     */
    private function section(int $type, bool $negate, FiscalWindow $window, array &$rows): array
    {
        $total  = $this->zero();
        $factor = $negate ? -1 : 1;

        foreach ($this->accounts($type, $window) as $account) {
            $values = [];
            foreach (self::VALUE_KEYS as $key) $values[$key] = $factor * $account[$key];
            $rows[] = $this->row((string)$account['description'], $values);
            $total  = $this->add($total, $values);
        }

        $rows[] = $this->blank();
        $rows[] = $this->row(
            self::label('TEXT_TOTAL', 'Total') . ' ' . $this->typeLabel($type),
            $total,
        );
        return $total;
    }

    /**
     * Every account of a type, with all six figures, in ONE query per figure
     * rather than four cursors advanced in lockstep.
     *
     * is_budget stepped four result sets together with MoveNext(), which is
     * only correct while every one returns the same accounts in the same order.
     * An account with no history row in the prior year drops out of that cursor
     * and every subsequent row reads its neighbour's figures. Keying by account
     * id cannot misalign.
     *
     * @return list<array<string,mixed>>
     */
    private function accounts(int $type, FiscalWindow $window): array
    {
        $rows = [];
        $res  = $this->db->Execute("select c.id, c.description from " . TABLE_CHART_OF_ACCOUNTS . " c
            where c.account_type = " . $type . " order by c.id");
        while (!$res->EOF) {
            $rows[(string)$res->fields['id']] = [
                'id'          => $res->fields['id'],
                'description' => $res->fields['description'],
            ] + array_fill_keys(self::VALUE_KEYS, 0.0);
            $res->MoveNext();
        }
        if (!$rows) return [];

        $spans = [
            'current'     => [$window->period,      $window->period,      'balance'],
            'current_ytd' => [$window->firstPeriod, $window->period,      'balance'],
            'budget_cur'  => [$window->period,      $window->period,      'budget'],
            'budget_ytd'  => [$window->firstPeriod, $window->period,      'budget'],
        ];
        if ($window->hasLastYear()) {
            $spans['ly_current'] = [$window->lyPeriod,      $window->lyPeriod, 'balance'];
            $spans['ly_ytd']     = [$window->lyFirstPeriod, $window->lyPeriod, 'balance'];
        }

        foreach ($spans as $key => [$from, $to, $what]) {
            $sum = $what === 'budget'
                ? 'sum(h.budget)'
                : 'sum(h.debit_amount) - sum(h.credit_amount)';
            $res = $this->db->Execute("select h.account_id, $sum as v
                from " . TABLE_CHART_OF_ACCOUNTS . " c
                inner join " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " h on c.id = h.account_id
                where c.account_type = " . $type . "
                  and h.period between " . (int)$from . " and " . (int)$to . "
                group by h.account_id");
            while (!$res->EOF) {
                $id = (string)$res->fields['account_id'];
                if (isset($rows[$id])) $rows[$id][$key] = (float)$res->fields['v'];
                $res->MoveNext();
            }
        }
        return array_values($rows);
    }

    private function typeLabel(int $type): string
    {
        $types = function_exists('load_coa_types') ? load_coa_types() : [];
        return (string)($types[$type]['text'] ?? $type);
    }

    /** @return array<string,float> */
    private function zero(): array { return array_fill_keys(self::VALUE_KEYS, 0.0); }

    /** @param array<string,float> $a @param array<string,float> $b @return array<string,float> */
    private function add(array $a, array $b): array
    {
        foreach (self::VALUE_KEYS as $k) $a[$k] = ($a[$k] ?? 0) + ($b[$k] ?? 0);
        return $a;
    }

    /** @param array<string,float> $a @param array<string,float> $b @return array<string,float> */
    private function subtract(array $a, array $b): array
    {
        foreach (self::VALUE_KEYS as $k) $a[$k] = ($a[$k] ?? 0) - ($b[$k] ?? 0);
        return $a;
    }
}

/**
 * The Balance Sheet.
 *
 * Port of balance_sheet, including the income_statement instance it created
 * solely to read one number off. Four columns: caption, account balance, account
 * type total, section total — the definition's own field names are placeholders
 * (`journal_main.id` four times over), because the class filled them by position.
 *
 * The legacy read `global $Seq` from inside add_bal_sheet_data() to format its
 * amounts — a variable owned by the caller's scope, which is why the class could
 * not be called from anywhere else. Nothing here is formatted, so the question
 * does not arise.
 */
final class BalanceSheetDocumentProvider extends FinancialStatementProvider
{
    /** Section => [account types, negated]. Order is the statement's order. */
    private const SECTIONS = [
        'assets'      => [[0, 2, 4, 6],  false],
        'property'    => [[8, 10, 12],   false],
        'current_liab'=> [[20, 22],      true],
        'long_term'   => [[24],          true],
        'capital'     => [[40, 42, 44],  true],
    ];

    public function build(array $params): Document
    {
        $rows = [];
        [$amount, $subtotal, $total] = $this->valueKeys();

        // ── assets ───────────────────────────────────────────────────────────
        $rows[] = $this->row(self::label('RW_FIN_CURRENT_ASSETS', 'Current Assets'));
        $section = $this->section(self::SECTIONS['assets'], $rows, $amount, $subtotal);
        $assets  = $section;
        $rows[]  = $this->row(self::label('RW_FIN_TOTAL_CURRENT_ASSETS', 'Total Current Assets'), [$total => $section]);
        $rows[]  = $this->blank();

        $rows[]  = $this->row(self::label('RW_FIN_PROP_EQUIP', 'Property and Equipment'));
        $section = $this->section(self::SECTIONS['property'], $rows, $amount, $subtotal);
        $assets += $section;
        $rows[]  = $this->row(self::label('RW_FIN_TOTAL_PROP_EQUIP', 'Total Property and Equipment'), [$total => $section]);
        $rows[]  = $this->row(self::label('RW_FIN_TOTAL_ASSETS', 'Total Assets'), [$total => $assets]);
        $rows[]  = $this->blank();

        // ── liabilities ──────────────────────────────────────────────────────
        $rows[]  = $this->row(self::label('RW_FIN_CUR_LIABILITIES', 'Current Liabilities'));
        $section = $this->section(self::SECTIONS['current_liab'], $rows, $amount, $subtotal);
        $liabs   = $section;
        $rows[]  = $this->row(self::label('RW_FIN_TOTAL_CUR_LIABILITIES', 'Total Current Liabilities'), [$total => $section]);
        $rows[]  = $this->blank();

        $rows[]  = $this->row(self::label('RW_FIN_LONG_TERM_LIABILITIES', 'Long Term Liabilities'));
        $section = $this->section(self::SECTIONS['long_term'], $rows, $amount, $subtotal);
        $liabs  += $section;
        $rows[]  = $this->row(self::label('RW_FIN_TOTAL_LT_LIABILITIES', 'Total Long Term Liabilities'), [$total => $section]);
        $rows[]  = $this->row(self::label('RW_FIN_TOTAL_LIABILITIES', 'Total Liabilities'), [$total => $liabs]);
        $rows[]  = $this->blank();

        // ── capital ──────────────────────────────────────────────────────────
        $rows[]  = $this->row(self::label('RW_FIN_CAPITAL', 'Capital'));
        $section = $this->section(self::SECTIONS['capital'], $rows, $amount, $subtotal);

        $net      = $this->ytdNetIncome();
        $section += $net;
        $liabs   += $section;
        $rows[]   = $this->row(self::label('RW_FIN_NET_INCOME', 'Net Income'), [$amount => $net]);
        $rows[]   = $this->row(self::label('RW_FIN_TOTAL_CAPITAL', 'Total Capital'), [$total => $section]);
        $rows[]   = $this->row(self::label('RW_FIN_TOTAL_LIABILITIES_CAPITAL', 'Total Liabilities and Capital'),
                               [$total => $liabs]);

        return $this->document($rows);
    }

    /**
     * One section: every account of each type in the list, then a total per type.
     *
     * @param array{0:list<int>,1:bool} $spec
     * @param list<Row>                 $rows appended to
     */
    private function section(array $spec, array &$rows, string $amount, string $subtotal): float
    {
        [$types, $negate] = $spec;
        $running = 0.0;
        foreach ($types as $type) {
            $typeTotal = 0.0;
            $res = $this->db->Execute("select h.beginning_balance + h.debit_amount - h.credit_amount as balance,
                    c.description, c.account_inactive
                from " . TABLE_CHART_OF_ACCOUNTS . " c
                inner join " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " h on c.id = h.account_id
                where h.period = " . $this->period . " and c.account_type = " . (int)$type);
            while (!$res->EOF) {
                $balance = (float)$res->fields['balance'];
                // A closed account with nothing left in it is not news.
                if ($res->fields['account_inactive'] && $balance == 0.0) { $res->MoveNext(); continue; }
                $signed     = $negate ? -$balance : $balance;
                $typeTotal += $signed;
                $rows[]     = $this->row((string)$res->fields['description'], [$amount => $signed]);
                $res->MoveNext();
            }
            $rows[]   = $this->row(self::label('TEXT_TOTAL', 'Total') . ' ' . $this->typeLabel($type),
                                   [$subtotal => $typeTotal]);
            $running += $typeTotal;
        }
        return $running;
    }

    /**
     * Retained earnings for the year to date: income less cost of sales less
     * expenses, over the fiscal year up to this period.
     *
     * balance_sheet got this by constructing an income_statement and building
     * an entire report to read one property off it. It is three sums.
     */
    private function ytdNetIncome(): float
    {
        $window = FiscalWindow::resolve($this->db, $this->period);
        $sum = function (int $type) use ($window): float {
            $r = $this->db->Execute("select sum(h.debit_amount) - sum(h.credit_amount) as v
                from " . TABLE_CHART_OF_ACCOUNTS . " c
                inner join " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " h on c.id = h.account_id
                where c.account_type = " . $type . "
                  and h.period between " . $window->firstPeriod . " and " . $window->period)->fields;
            return (float)($r['v'] ?? 0);
        };
        return -$sum(30) - $sum(32) - $sum(34);
    }

    private function typeLabel(int $type): string
    {
        $types = function_exists('load_coa_types') ? load_coa_types() : [];
        return (string)($types[$type]['text'] ?? $type);
    }

    /** @return array{0:string,1:string,2:string} amount, type subtotal, section total */
    private function valueKeys(): array
    {
        return [
            $this->columns[1]->key ?? 'amount',
            $this->columns[2]->key ?? 'subtotal',
            $this->columns[3]->key ?? 'total',
        ];
    }
}
