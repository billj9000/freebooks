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
//  Path: /modules/forms/classes/app/document_runner.php
//
/**
 * Forms — turning a stored definition into a Document, for the application.
 *
 * Everything below this class had been driven only by `docs/tools/`. This is the
 * wiring the application needs: given a definition and the run dialog's answers,
 * pick the provider, hand it a converted definition, and return a Document. It
 * is deliberately the only place that knows the three routes a definition can
 * take, so a page never has to.
 *
 *   dataset      — the ordinary query-driven path, via ReportDocumentProvider
 *   provider     — a special_class Forms serves in code (§5, §11)
 *   unsupported  — a definition Forms cannot yet serve, named and explained
 *
 * The last case matters. PhreeForm and Forms coexist through the transition, so
 * a page has to be able to say "this one still runs on the old engine" rather
 * than produce an empty report and let the user believe it.
 *
 * This class sits ABOVE the seam and freely reads the database and application
 * settings. What it returns is a Document, which is free of both.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Catalog\{AppFiscalCalendar, Catalog, DateRangeResolver};
use FreeBooks\Forms\Document\Document;
use FreeBooks\Forms\Legacy\{DatasetResolver, ReportConverter};
use FreeBooks\Forms\Provider\{
    AccountReconciliationDocumentProvider, BalanceSheetDocumentProvider,
    IncomeStatementDocumentProvider, ReportDocumentProvider
};

/** How a definition will be served, and by what. */
final readonly class RunPlan
{
    public const DATASET     = 'dataset';
    public const PROVIDER    = 'provider';
    public const UNSUPPORTED = 'unsupported';

    /** @param list<string> $notes */
    public function __construct(
        public string $kind,
        public string $name,
        public array  $notes = [],
        /** Run-dialog answers this definition needs before it can run at all. */
        public bool   $needsPeriod    = false,
        public bool   $needsGlAccount = false,
    ) {}

    public function runnable(): bool { return $this->kind !== self::UNSUPPORTED; }
}

final class DocumentRunner
{
    /** special_class => provider, for the structural reports (§11.12, §11.13). */
    private const STRUCTURAL = [
        'is_budget'     => IncomeStatementDocumentProvider::class,
        'balance_sheet' => BalanceSheetDocumentProvider::class,
        'acct_recon'    => AccountReconciliationDocumentProvider::class,
    ];

    /** Special classes ported to a document provider rather than to a dataset. */
    private const FORM_PROVIDERS = ['entry_builder', 'statement_builder', 'pos_builder', 'wo_builder'];

    /**
     * Special classes that were DELETED rather than ported, and whose reports
     * therefore take the ordinary query-driven route (§11.11).
     *
     * The definitions still name them, because nothing rewrote the stored XML —
     * so a runner that treats "has a special_class" as "needs a provider"
     * declines four working reports. The aged pair's six pseudo-columns are
     * computed fields on the transactions dataset now; there is nothing left to
     * dispatch to.
     */
    private const DATASET_SERVED = ['aged_receivables', 'aged_summary'];

    public function __construct(
        private readonly \queryFactory $db,
        private readonly Catalog       $catalog,
    ) {}

    /**
     * Decide how a definition will be served, without running it.
     *
     * The page calls this for every row it lists, so it must not query anything
     * expensive — it reads the definition and the catalog and nothing else.
     */
    public function plan(object $report): RunPlan
    {
        $special = trim((string)($report->special_class ?? ''));

        if (isset(self::STRUCTURAL[$special])) {
            return new RunPlan(RunPlan::PROVIDER, $special,
                needsPeriod: true,
                needsGlAccount: $special === 'acct_recon');
        }

        if ($special !== '' && !in_array($special, self::DATASET_SERVED, true)) {
            // A form provider exists for these, but the forms path runs through
            // LayoutDocument and its own record selection, which this page does
            // not yet drive. Saying so beats rendering an empty table.
            if (in_array($special, self::FORM_PROVIDERS, true)) {
                return new RunPlan(RunPlan::UNSUPPORTED, $special,
                    ['This is a form. Forms renders it, but printing one is driven from the'
                     . ' document it belongs to rather than from this list.']);
            }
            return new RunPlan(RunPlan::UNSUPPORTED, $special,
                ["No class named '$special' is registered, so this definition cannot run."]);
        }

        $resolver = new DatasetResolver($this->catalog);
        $dataset  = $resolver->resolve($report);
        if ($dataset === null) {
            return new RunPlan(RunPlan::UNSUPPORTED, '', $resolver->notes());
        }
        return new RunPlan(RunPlan::DATASET, $dataset->id, $resolver->notes());
    }

    /**
     * Build the Document.
     *
     * $params carries the run dialog's answers: `dates` (a PhreeForm date
     * preference letter), `period`, `gl_account`.
     *
     * The definition is CLONED first, and that is not defensive habit. The
     * legacy engine consumes a definition as it reads it — `BuildDataArray()`
     * array_shift()s the group list away and `build_criteria()` writes resolved
     * filter modes back onto the object — so a definition object that has been
     * near the old engine, or that will be, must not be shared with this one.
     *
     * @param array<string,mixed> $params
     */
    public function run(object $report, array $params = []): Document
    {
        $report = unserialize(serialize($report));
        $plan   = $this->plan($report);

        if (!$plan->runnable()) {
            throw new \RuntimeException(implode(' ', $plan->notes)
                ?: 'Forms cannot serve this definition.');
        }

        if (isset($params['dates']) && $params['dates'] !== '') {
            $report->datedefault = (string)$params['dates'];
        }
        $period = (int)($params['period'] ?? 0);
        if ($period > 0) $report->period = $period;

        return $plan->kind === RunPlan::PROVIDER
            ? $this->runProvider($report, $plan, $params)
            : $this->runDataset($report, $plan);
    }

    /** @param array<string,mixed> $params */
    private function runProvider(object $report, RunPlan $plan, array $params): Document
    {
        $converter = new ReportConverter();
        $class     = self::STRUCTURAL[$plan->name];

        $arguments = [
            'db'      => $this->db,
            'columns' => $converter->convertStructuralColumns($report),
            'period'  => (int)($report->period ?? 0),
            'title'   => (string)($report->title ?? 'Report'),
        ];
        if ($plan->needsGlAccount) {
            $arguments['glAccount'] = (string)($params['gl_account'] ?? '');
        }
        return (new $class(...$arguments))->build([]);
    }

    private function runDataset(object $report, RunPlan $plan): Document
    {
        $dataset   = $this->catalog->get($plan->name);
        $converter = new ReportConverter();

        $columns = $converter->convertColumns($report, $dataset);
        $group   = $converter->convertGroup($report, $dataset);
        $sorts   = $converter->convertSorts($report, $dataset);
        $filters = $converter->convertFilters($report, $dataset);

        // The stored date preference, resolved against the accounting calendar
        // and appended as ordinary bound filters rather than as SQL text.
        $range   = (new DateRangeResolver(new AppFiscalCalendar($this->db)))
            ->resolve((string)($report->datedefault ?? ''));
        $filters = array_merge($filters, $converter->convertDateFilters($report, $dataset, $range));

        $this->warnings = $converter->warnings();

        return (new ReportDocumentProvider(
            db:              $this->db,
            catalog:         $this->catalog,
            datasetId:       $dataset->id,
            columns:         $columns,
            title:           (string)($report->title ?? 'Report'),
            groupBy:         $group,
            filters:         $filters,
            sorts:           $sorts,
            resolvedColumns: $converter->resolvedColumns(),
            totalsOnly:      $converter->convertTotalsOnly($report),
        ))->build([]);
    }

    /** @var list<string> */
    private array $warnings = [];

    /**
     * Anything the converter could not map on the last run.
     *
     * Shown to the user rather than swallowed: a column the dataset cannot serve
     * is a report that silently prints one column fewer than its author drew.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
