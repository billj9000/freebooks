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
 * is deliberately the only place that knows the routes a definition can take, so
 * a page never has to.
 *
 *   dataset      — the ordinary query-driven path, via ReportDocumentProvider
 *   provider     — a special_class Forms serves in code (§5, §11)
 *   form         — served, but by FormRunner and the print page, not here
 *   unsupported  — a definition Forms cannot yet serve, named and explained
 *
 * The last two matter for opposite reasons. PhreeForm and Forms coexist through
 * the transition, so a page has to be able to say "this one still runs on the
 * old engine" rather than produce an empty report and let the user believe it.
 * And a form is not a report: it has no columns and no grouping, so running one
 * here yields a plausible page of rows that is not the document anyone asked
 * for — which is what the document list did until `form` existed.
 *
 * This class sits ABOVE the seam and freely reads the database and application
 * settings. What it returns is a Document, which is free of both.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Catalog\{AppFiscalCalendar, Catalog, Dataset, DateRange, DateRangeFilters, DateRangeResolver};
use FreeBooks\Forms\Definition\Definition;
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
    public const FORM        = 'form';
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

    /** Can DocumentRunner produce a table from this? A form is printed, not run. */
    public function runnable(): bool
    {
        return $this->kind !== self::UNSUPPORTED && $this->kind !== self::FORM;
    }

    /**
     * It is a form: Forms serves it, but through FormRunner and the print page.
     *
     * Distinct from UNSUPPORTED because the two need opposite things said to the
     * user — "Forms cannot do this" against "printing this is over there".
     */
    public function isForm(): bool { return $this->kind === self::FORM; }
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

        // A FORM is not a table, and asking first is what stops it being served
        // as one. Only the four special_class forms were being declined here;
        // the nine query-driven ones carry no special_class at all, so they fell
        // through to the dataset route and the document list offered PDF and CSV
        // buttons that rendered an invoice as rows and columns. Reporttype is
        // the definition's own answer to the question, and it covers both.
        if (($report->reporttype ?? '') === 'frm') {
            return new RunPlan(RunPlan::FORM, $special,
                ['This is a form. Forms prints it, but a form is per record, so printing one'
                 . ' is driven from the document it belongs to rather than from this list.']);
        }

        if (isset(self::STRUCTURAL[$special])) {
            return new RunPlan(RunPlan::PROVIDER, $special,
                needsPeriod: true,
                needsGlAccount: $special === 'acct_recon');
        }

        if ($special !== '' && !in_array($special, self::DATASET_SERVED, true)) {
            // Reached only by a definition that names a FORM's special class
            // while calling itself a report — the reporttype check above takes
            // every well-formed form. Kept because a customised install can hold
            // that combination, and serving it as a table would be worse than
            // saying where it belongs.
            if (in_array($special, self::FORM_PROVIDERS, true)) {
                return new RunPlan(RunPlan::FORM, $special,
                    ['This names a form builder, so it is printed rather than run as a table.']);
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

        if ($plan->isForm()) {
            throw new \RuntimeException('This is a form, not a report. Forms prints it through'
                . ' FormRunner and the print page, which need the records to print.');
        }
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

    // ── the native format ────────────────────────────────────────────────────
    //
    // The same two routes, taken from a Definition instead of from PhreeForm
    // XML. They sit ALONGSIDE the legacy pair rather than replacing it, for the
    // length of one release: that is what lets verify_forms_native.php render
    // the same definition both ways and hold the two to a byte comparison. Step
    // 6 deletes plan(), run() and everything above this line.

    /**
     * How a native definition will be served.
     *
     * Shorter than plan() by everything the conversion already settled. There
     * is no DatasetResolver call, because the definition names its dataset;
     * and no DATASET_SERVED exception list, because a deleted special class
     * leaves no trace in a native file to except.
     */
    public function planDefinition(Definition $definition): RunPlan
    {
        if ($definition->isForm()) {
            return new RunPlan(RunPlan::FORM, (string)$definition->provider,
                ['This is a form. Forms prints it, but a form is per record, so printing one'
                 . ' is driven from the document it belongs to rather than from this list.']);
        }

        if ($definition->hasProvider()) {
            $special = (string)$definition->provider;
            if (!isset(self::STRUCTURAL[$special])) {
                return new RunPlan(RunPlan::UNSUPPORTED, $special,
                    ["No class named '$special' is registered, so this definition cannot run."]);
            }
            return new RunPlan(RunPlan::PROVIDER, $special,
                needsPeriod: true,
                needsGlAccount: $special === 'acct_recon');
        }

        if ($definition->datasetId === null) {
            return new RunPlan(RunPlan::UNSUPPORTED, '',
                ['This definition names no dataset, so nothing can serve it.']);
        }
        return new RunPlan(RunPlan::DATASET, $definition->datasetId);
    }

    /**
     * Build the Document from a native definition.
     *
     * A Definition is immutable, so unlike run() this needs no defensive clone:
     * the run-dialog answers are read as parameters rather than written back
     * onto the definition, which is the mutation that made cloning necessary.
     *
     * @param array<string,mixed> $params `dates`, `period`, `gl_account`
     */
    public function runDefinition(Definition $definition, array $params = []): Document
    {
        $this->warnings = [];
        $plan = $this->planDefinition($definition);

        if ($plan->isForm()) {
            throw new \RuntimeException('This is a form, not a report. Forms prints it through'
                . ' FormRunner and the print page, which need the records to print.');
        }
        if (!$plan->runnable()) {
            throw new \RuntimeException(implode(' ', $plan->notes)
                ?: 'Forms cannot serve this definition.');
        }

        $period = (int)($params['period'] ?? 0) > 0 ? (int)$params['period'] : $definition->period;

        if ($plan->kind === RunPlan::PROVIDER) {
            $arguments = [
                'db'      => $this->db,
                'columns' => $definition->columns,
                'period'  => $period,
                'title'   => $definition->title !== '' ? $definition->title : 'Report',
            ];
            if ($plan->needsGlAccount) $arguments['glAccount'] = (string)($params['gl_account'] ?? '');
            return (new (self::STRUCTURAL[$plan->name])(...$arguments))->build([]);
        }

        $dataset = $this->catalog->get($plan->name);

        // The one part of a definition that cannot be converted in advance:
        // "this quarter" is a question about today. The FIELD it constrains was
        // settled at conversion, so all that happens here is resolving the
        // preference and applying it.
        $preference = (string)($params['dates'] ?? '') !== ''
            ? (string)$params['dates'] : $definition->dateDefault;
        $range = (new DateRangeResolver(new AppFiscalCalendar($this->db)))->resolve($preference);
        $filters = array_merge($definition->filters,
            DateRangeFilters::build($dataset, fn() => $definition->dateField, $range, $this->warnings));

        // Everything the definition carries, and not a subset of it. This used
        // to pass columns, filters and sorts only — matching runDataset(), which
        // was itself incomplete — so a report printed with no company name, no
        // titles, no filter description, on default A4 margins, in the
        // renderer's own fonts. It looked like a report; it was not the report
        // the author had drawn. §13.9 recorded that gap; the editor's preview
        // is what made it impossible to leave.
        return (new ReportDocumentProvider(
            db:              $this->db,
            catalog:         $this->catalog,
            datasetId:       $dataset->id,
            columns:         $definition->columns,
            title:           $definition->title !== '' ? $definition->title : 'Report',
            groupBy:         $definition->groupBy,
            groupFormat:     $definition->groupFormat,
            filters:         $filters,
            sorts:           $definition->sorts,
            totalsOnly:      $definition->totalsOnly,
            context:         forms_report_context(),
            page:            $definition->page,
            filterText:      $this->describe($definition, $range, $dataset),
            resolvedColumns: $definition->resolvedColumns,
            style:           $definition->style,
        ))->build([]);
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

        // Kept in step with runDefinition() deliberately: the two paths are held
        // to a byte comparison by verify_forms_native.php, so a value passed by
        // one and not the other is a failure there rather than a surprise later.
        $definition = new Definition(
            title:   (string)($report->title ?? 'Report'),
            page:    $converter->convertPageSetup($report),
            columns: $columns,          // describe() reads their headings
            groupBy: $group,
            sorts:   $sorts,
        );

        return (new ReportDocumentProvider(
            db:              $this->db,
            catalog:         $this->catalog,
            datasetId:       $dataset->id,
            columns:         $columns,
            title:           (string)($report->title ?? 'Report'),
            groupBy:         $group,
            groupFormat:     $converter->convertGroupFormat($report),
            filters:         $filters,
            sorts:           $sorts,
            totalsOnly:      $converter->convertTotalsOnly($report),
            context:         forms_report_context(),
            page:            $converter->convertPageSetup($report),
            filterText:      $this->describe($definition, $range, $dataset),
            resolvedColumns: $converter->resolvedColumns(),
            style:           $converter->convertStyle($report),
        ))->build([]);
    }

    /**
     * The line under the titles saying what the report was narrowed to.
     *
     * PhreeForm prints "Report Filters:Grouped by: …; Sorted by: …; Date Range:
     * …;" and the band is drawn whether or not there is text for it, so leaving
     * this null prints a rule with nothing above it. Built from the definition
     * rather than from the SQL: what an author wants to read back is the
     * grouping and the period, not a WHERE clause.
     */
    private function describe(Definition $definition, DateRange $range, ?Dataset $dataset = null): ?string
    {
        // A field KEY is how the definition stores it; a heading is what the
        // author called it. PhreeForm printed "Grouped by: Post Date", and
        // "Grouped by: post_date" is the same line written in the wrong
        // language — the storage layer's, not the reader's.
        $label = function (string $key) use ($definition, $dataset): string {
            foreach ($definition->columns as $column) {
                if ($column->key === $key && trim($column->heading) !== '') return trim($column->heading);
            }
            $field = $dataset?->field($key);
            if ($field !== null && trim($field->label) !== '') return trim($field->label);
            return $key;   // a sort on a field the report does not show and the dataset cannot name
        };

        $parts = [];
        if ($definition->groupBy !== null && $definition->groupBy !== '') {
            $parts[] = sprintf(FORMS_FILTER_GROUPED_BY, $label($definition->groupBy));
        }
        if ($definition->sorts) {
            $names = array_map(fn($sort) => $label($sort->fieldKey), $definition->sorts);
            $parts[] = sprintf(FORMS_FILTER_SORTED_BY, implode(', ', $names));
        }
        if ($range->start !== null || $range->endExclusive !== null) {
            $parts[] = sprintf(FORMS_FILTER_DATE_RANGE,
                $range->start !== null ? gen_locale_date($range->start) : '',
                $range->endInclusive() !== null ? gen_locale_date((string)$range->endInclusive()) : '');
        } elseif ($range->period !== null) {
            $parts[] = sprintf(FORMS_FILTER_PERIOD, $range->period);
        }
        return $parts ? FORMS_FILTER_PREFIX . implode('; ', $parts) : null;
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
