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
 * Forms — the period-end financial statements.
 *
 * Ports phreebooks/classes/is_budget.php, balance_sheet.php (with the
 * income_statement.php helper the latter instantiates) and acct_recon.php.
 * See §5, §11.12 and §11.13.
 *
 * These are not dataset-shaped, and that is the point of them being here rather
 * than in the catalog. A financial statement's rows are SECTIONS in a fixed
 * order — revenues, cost of sales, gross profit, expenses, net income; or
 * opening balance, items in transit, items cleared — not a query result grouped
 * by something. No filter or sort a report author could write would produce a
 * balance sheet, so the structure is code.
 *
 * What they no longer do is format. All three legacy classes returned arrays of
 * ProcessData()-formatted strings, which is why the same definition could not
 * produce a readable PDF and a usable CSV. Every cell here is a TypedValue and
 * the renderer decides.
 *
 * Section headings, blank separators and subtotals each carry their own RowKind
 * (added 2026-08-14). The legacy emitted all of them as plain data rows, which
 * is why no renderer could set "Gross Profit" differently from the account line
 * above it. They are still compared against the legacy as data rows, because
 * that is what PhreeForm produced.
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
 * Shared machinery: a statement laid out as captioned rows in sections, over a
 * single accounting period.
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
     * A bare number is taken as money, which is what the two statements deal in
     * throughout. Anything else — the reconciliation's post date and reference —
     * is passed as an already-typed value and used as given, so that a column
     * holding dates is not silently declared to be currency.
     *
     * @param array<string,int|float|TypedValue|null> $values keyed by column key
     */
    protected function row(string $caption, array $values = [], RowKind $kind = RowKind::Data): Row
    {
        $cells = [];
        foreach ($this->columns as $i => $column) {
            if ($i === 0) {
                $cells[$column->key] = TypedValue::text($caption);
                continue;
            }
            if (!array_key_exists($column->key, $values)) {
                $cells[$column->key] = TypedValue::text('');
                continue;
            }
            $value = $values[$column->key];
            $cells[$column->key] = $value instanceof TypedValue ? $value : TypedValue::money($value);
        }
        return new Row($kind, $cells);
    }

    /** The caption that opens a section — "Revenues", "Cleared Transactions". */
    protected function heading(string $caption): Row
    {
        return $this->row($caption, [], RowKind::SectionHeading);
    }

    /**
     * A row summarising the ones above it.
     *
     * Distinct from a GroupTotal: nothing broke, and the caption is in the first
     * column rather than in Row::$label, so a renderer emphasises it without
     * drawing a group band over it.
     *
     * @param array<string,int|float|TypedValue|null> $values
     */
    protected function subtotal(string $caption, array $values = []): Row
    {
        return $this->row($caption, $values, RowKind::Subtotal);
    }

    /** A blank separator, as the legacy emitted between sections. */
    protected function blank(): Row
    {
        return $this->row('', [], RowKind::Separator);
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
        $rows[] = $this->heading(self::label('RW_FIN_REVENUES', 'Revenues'));
        $total  = $this->section(self::INCOME, true, $window, $rows);
        $gross  = $this->add($gross, $total);

        // Less cost of sales.
        $rows[] = $this->blank();
        $rows[] = $this->heading(self::label('RW_FIN_COST_OF_SALES', 'Cost of Sales'));
        $total  = $this->section(self::COGS, false, $window, $rows);
        $gross  = $this->subtract($gross, $total);

        $rows[] = $this->blank();
        $rows[] = $this->subtotal(self::label('RW_FIN_GROSS_PROFIT', 'Gross Profit'), $gross);

        // Less expenses.
        $rows[] = $this->blank();
        $rows[] = $this->heading(self::label('RW_FIN_EXPENSES', 'Expenses'));
        $total  = $this->section(self::EXPENSES, false, $window, $rows);
        $net    = $this->subtract($gross, $total);

        $rows[] = $this->blank();
        $rows[] = $this->subtotal(self::label('RW_FIN_NET_INCOME', 'Net Income'), $net);

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
        $rows[] = $this->subtotal(
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
        $rows[] = $this->heading(self::label('RW_FIN_CURRENT_ASSETS', 'Current Assets'));
        $section = $this->section(self::SECTIONS['assets'], $rows, $amount, $subtotal);
        $assets  = $section;
        $rows[]  = $this->subtotal(self::label('RW_FIN_TOTAL_CURRENT_ASSETS', 'Total Current Assets'), [$total => $section]);
        $rows[]  = $this->blank();

        $rows[]  = $this->heading(self::label('RW_FIN_PROP_EQUIP', 'Property and Equipment'));
        $section = $this->section(self::SECTIONS['property'], $rows, $amount, $subtotal);
        $assets += $section;
        $rows[]  = $this->subtotal(self::label('RW_FIN_TOTAL_PROP_EQUIP', 'Total Property and Equipment'), [$total => $section]);
        $rows[]  = $this->subtotal(self::label('RW_FIN_TOTAL_ASSETS', 'Total Assets'), [$total => $assets]);
        $rows[]  = $this->blank();

        // ── liabilities ──────────────────────────────────────────────────────
        $rows[]  = $this->heading(self::label('RW_FIN_CUR_LIABILITIES', 'Current Liabilities'));
        $section = $this->section(self::SECTIONS['current_liab'], $rows, $amount, $subtotal);
        $liabs   = $section;
        $rows[]  = $this->subtotal(self::label('RW_FIN_TOTAL_CUR_LIABILITIES', 'Total Current Liabilities'), [$total => $section]);
        $rows[]  = $this->blank();

        $rows[]  = $this->heading(self::label('RW_FIN_LONG_TERM_LIABILITIES', 'Long Term Liabilities'));
        $section = $this->section(self::SECTIONS['long_term'], $rows, $amount, $subtotal);
        $liabs  += $section;
        $rows[]  = $this->subtotal(self::label('RW_FIN_TOTAL_LT_LIABILITIES', 'Total Long Term Liabilities'), [$total => $section]);
        $rows[]  = $this->subtotal(self::label('RW_FIN_TOTAL_LIABILITIES', 'Total Liabilities'), [$total => $liabs]);
        $rows[]  = $this->blank();

        // ── capital ──────────────────────────────────────────────────────────
        $rows[]  = $this->heading(self::label('RW_FIN_CAPITAL', 'Capital'));
        $section = $this->section(self::SECTIONS['capital'], $rows, $amount, $subtotal);

        $net      = $this->ytdNetIncome();
        $section += $net;
        $liabs   += $section;
        $rows[]   = $this->row(self::label('RW_FIN_NET_INCOME', 'Net Income'), [$amount => $net]);
        $rows[]   = $this->subtotal(self::label('RW_FIN_TOTAL_CAPITAL', 'Total Capital'), [$total => $section]);
        $rows[]   = $this->subtotal(self::label('RW_FIN_TOTAL_LIABILITIES_CAPITAL', 'Total Liabilities and Capital'),
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
            $rows[]   = $this->subtotal(self::label('TEXT_TOTAL', 'Total') . ' ' . $this->typeLabel($type),
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

/**
 * The Account Reconciliation.
 *
 * Port of acct_recon. A bank reconciliation for one GL account as at the end of
 * one accounting period: the account's movement for the period, the items still
 * in transit that explain the gap to the bank's balance, and the items that
 * cleared during it.
 *
 * Five columns — caption, date, reference, amount, total. Like the Balance
 * Sheet, the definition names none of them: it lists the field `id` five times
 * over because the class filled each row by position.
 *
 * Three things the legacy did that this does not:
 *
 * 1. It read the account out of `$report->filterlist[0]->min_val`, on the
 *    stated assumption that "the gl account is the first criteria". Reordering
 *    the report's filters silently reconciled the wrong account, or none.
 *    The account is a constructor argument here, so the caller says which.
 * 2. It interpolated that value — which arrives from the run dialog — straight
 *    into two SQL statements. Both queries below bind it.
 * 3. It keyed its working list by `journal_main.id`, so where one entry had
 *    several lines against the account, only the last survived to be printed
 *    while every one of them was added to the total. See listItems().
 */
final class AccountReconciliationDocumentProvider extends FinancialStatementProvider
{
    /** A line's two faces: the deposit side and the payment side. */
    private const DEPOSIT = 'deposit';
    private const PAYMENT = 'payment';

    public function __construct(
        \queryFactory $db,
        array         $columns,
        int           $period,
        private readonly string $glAccount = '',
        string        $title = 'Report',
        Context       $context = new Context(),
        PageSetup     $page = new PageSetup(),
        ReportStyle   $style = new ReportStyle(),
    ) {
        parent::__construct($db, $columns, $period, $title, $context, $page, $style);
    }

    public function build(array $params): Document
    {
        $account = (string)($params['gl_account'] ?? $this->glAccount);
        if ($account === '') return $this->document([]);

        $endDate = $this->periodEndDate();
        if ($endDate === null) return $this->document([]);

        [, $date, $reference, $amount, $total] = $this->keys();

        // Items not yet reconciled as at this period. An item reconciled in a
        // LATER period was still outstanding now, which is why the test is not
        // simply `reconciled = 0`.
        $open    = $this->listItems($account,
            '(i.reconciled = 0 or i.reconciled > ?)', [$this->period], $endDate);
        $cleared = $this->listItems($account,
            'i.reconciled = ?', [$this->period], $endDate);

        $depositsInTransit   = $this->sum($open,    self::DEPOSIT);
        $paymentsInTransit   = $this->sum($open,    self::PAYMENT);
        $depositsCleared     = $this->sum($cleared, self::DEPOSIT);
        $paymentsCleared     = $this->sum($cleared, self::PAYMENT);

        // The account's own movement for the period, from its history row.
        [$opening, $receipts, $disbursements] = $this->accountMovement($account);
        $closing = $opening + $receipts - $disbursements;

        $rows   = [];
        $rows[] = $this->row(self::label('RW_RECON_BB', 'Beginning GL Balance'), [$total => $opening]);
        $rows[] = $this->blank();
        $rows[] = $this->row(self::label('RW_RECON_CR', 'Cash Receipts'),        [$total => $receipts]);
        $rows[] = $this->blank();
        $rows[] = $this->row(self::label('RW_RECON_CD', 'Cash Disbursements'),   [$total => -$disbursements]);
        $rows[] = $this->blank();
        $rows[] = $this->subtotal(self::label('RW_RECON_EB', 'Ending GL Balance'), [$total => $closing]);
        $rows[] = $this->blank();

        $rows[] = $this->heading(self::label('RW_RECON_ADD_BACK', 'Add Back Deposits in Transit'));
        $this->appendItems($rows, $open, self::DEPOSIT, 1, $date, $reference, $amount);
        $rows[] = $this->subtotal(self::label('RW_RECON_DIT', 'Total Deposits in Transit'),
                                  [$total => $depositsInTransit]);
        $rows[] = $this->blank();

        $rows[] = $this->heading(self::label('RW_RECON_LOP', 'Less Outstanding Payments'));
        $this->appendItems($rows, $open, self::PAYMENT, -1, $date, $reference, $amount);
        $rows[] = $this->subtotal(self::label('RW_RECON_TOP', 'Total Outstanding Payments'),
                                  [$total => -$paymentsInTransit]);
        $rows[] = $this->blank();

        // The bank's own balance, implied: what the statement must show for the
        // ledger to agree with it once the items above are allowed for. The
        // legacy calls it the unreconciled difference and its author left a
        // "check this" note beside it; the arithmetic is right, and the two
        // lines that follow are the proof, so it keeps its caption.
        $rows[] = $this->subtotal(self::label('RW_RECON_DIFF', 'Unreconciled Difference'),
                                  [$total => $closing - $depositsInTransit + $paymentsInTransit]);
        $rows[] = $this->subtotal(self::label('RW_RECON_EB', 'Ending GL Balance'), [$total => $closing]);

        $rows[] = $this->blank();
        $rows[] = $this->heading(self::label('RW_RECON_CLEARED', 'Cleared Transactions'));

        $rows[] = $this->heading(self::label('RW_RECON_DCLEARED', 'Deposits Cleared'));
        $this->appendItems($rows, $cleared, self::DEPOSIT, 1, $date, $reference, $amount);
        $rows[] = $this->subtotal(self::label('RW_RECON_TDC', 'Total Deposits Cleared'),
                                  [$total => $depositsCleared]);
        $rows[] = $this->blank();

        $rows[] = $this->heading(self::label('RW_RECON_PCLEARED', 'Payments Cleared'));
        $this->appendItems($rows, $cleared, self::PAYMENT, -1, $date, $reference, $amount);
        // Shown as a positive magnitude, as the legacy did, even though the
        // lines above it are negative. Both readings reach the same net below.
        $rows[] = $this->subtotal(self::label('RW_RECON_TPC', 'Total Payments Cleared'),
                                  [$total => $paymentsCleared]);
        $rows[] = $this->blank();

        $rows[] = $this->subtotal(self::label('RW_RECON_NCLEARED', 'Net Cleared'),
                                  [$total => $depositsCleared - $paymentsCleared]);

        return $this->document($rows);
    }

    /**
     * Every journal line against the account matching a reconciliation state.
     *
     * One row per LINE. acct_recon collected these into an array keyed by
     * `journal_main.id`, so a journal entry touching the account more than once
     * — a point-of-sale entry recording the sale, its payment and the till
     * total all against the cash account — kept only whichever line the driver
     * returned last, while the running totals had already counted all of them.
     * The observed effect on demo was a reconciliation listing two of its six
     * cleared transactions underneath totals covering all six: a bank
     * reconciliation that does not show the transactions it is totalling.
     *
     * A line whose debit and credit cancel is neither a deposit nor a payment
     * and is dropped, which is also what the legacy's truth tests did with it.
     *
     * @param list<mixed> $bind values for the placeholders in $condition
     * @return list<array{date:string,reference:string,net:float,side:string}>
     */
    private function listItems(string $account, string $condition, array $bind, string $endDate): array
    {
        $sql = "select m.post_date, m.purchase_invoice_id, i.debit_amount, i.credit_amount
            from " . TABLE_JOURNAL_MAIN . " m
            inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id
            where i.gl_account = ? and $condition and m.post_date <= ?
            order by m.post_date, i.id";

        $result = $this->db->ExecutePrepared($sql, array_merge([$account], $bind, [$endDate]));
        $items  = [];
        while (!$result->EOF) {
            $net = (float)$result->fields['debit_amount'] - (float)$result->fields['credit_amount'];
            if ($net != 0.0) {
                $items[] = [
                    'date'      => (string)$result->fields['post_date'],
                    'reference' => (string)($result->fields['purchase_invoice_id'] ?? ''),
                    'net'       => $net,
                    'side'      => $net > 0 ? self::DEPOSIT : self::PAYMENT,
                ];
            }
            $result->MoveNext();
        }
        return $items;
    }

    /**
     * @param list<array{date:string,reference:string,net:float,side:string}> $items
     * @param list<Row> $rows appended to
     * @param int $sign 1 to print deposits as they stand, -1 to print payments negative
     */
    private function appendItems(array &$rows, array $items, string $side, int $sign,
                                 string $date, string $reference, string $amount): void
    {
        foreach ($items as $item) {
            if ($item['side'] !== $side) continue;
            $rows[] = $this->row('', [
                $date      => TypedValue::date($item['date']),
                $reference => TypedValue::text($item['reference']),
                $amount    => $sign * abs($item['net']),
            ]);
        }
    }

    /** @param list<array{net:float,side:string}> $items */
    private function sum(array $items, string $side): float
    {
        $total = 0.0;
        foreach ($items as $item) if ($item['side'] === $side) $total += abs($item['net']);
        return $total;
    }

    /** @return array{0:float,1:float,2:float} opening balance, receipts, disbursements */
    private function accountMovement(string $account): array
    {
        $fields = $this->db->ExecutePrepared("select beginning_balance, debit_amount, credit_amount
            from " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " where account_id = ? and period = ?",
            [$account, $this->period])->fields;

        return [
            (float)($fields['beginning_balance'] ?? 0),
            (float)($fields['debit_amount']      ?? 0),
            (float)($fields['credit_amount']     ?? 0),
        ];
    }

    private function periodEndDate(): ?string
    {
        $end = $this->db->ExecutePrepared("select end_date from " . TABLE_ACCOUNTING_PERIODS
            . " where period = ?", [$this->period])->fields['end_date'] ?? null;
        return $end === null ? null : (string)$end;
    }

    /** @return array{0:string,1:string,2:string,3:string,4:string} caption, date, reference, amount, total */
    private function keys(): array
    {
        $defaults = ['description', 'date', 'reference', 'amount', 'total'];
        foreach ($defaults as $i => $fallback) $defaults[$i] = $this->columns[$i]->key ?? $fallback;
        return $defaults;
    }
}
