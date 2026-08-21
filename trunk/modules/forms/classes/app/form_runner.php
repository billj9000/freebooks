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
//  Path: /modules/forms/classes/app/form_runner.php
//
/**
 * Forms — turning a stored form definition into a LayoutDocument (§11.16).
 *
 * DocumentRunner does this for reports. Forms need more, and the difference is
 * the reason this is a separate class rather than another branch there:
 *
 *   - a form is per RECORD, so something has to say which records, and the
 *     answer arrives in PhreeForm's URL vocabulary rather than as a list of ids
 *   - a form carries a positioned Template, which has to be converted and whose
 *     Context constants can only be known by reading the template back
 *   - five providers serve forms, against the three that serve reports
 *
 * All of this existed already, but only inside docs/tools/render_forms_document.php,
 * where the dataset came from an environment variable and the record ids from
 * argv. That tool proved the layers work; this is the same wiring with the
 * answers coming from the application instead, so that a page — and eventually
 * the Print button on an order — can ask for a form and get one.
 *
 * This class sits ABOVE the seam and freely reads the database and application
 * settings. What it returns is a Document, which is free of both.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Catalog\{AppFiscalCalendar, Catalog, DateRangeResolver, Filter};
use FreeBooks\Forms\Definition\Definition;
use FreeBooks\Forms\Document\{
    Context, Document, Field, FieldBlock, LayoutDocument, Scope, SerialDocument,
    Template, TypedValue
};
use FreeBooks\Forms\Legacy\{DatasetResolver, ReportConverter, TemplateConverter};
use FreeBooks\Forms\Provider\{
    EntryDocumentProvider, PosDocumentProvider, QueryDocumentProvider,
    StatementDocumentProvider, WorkOrderDocumentProvider
};

/** How a form will be served, and what it still needs before it can run. */
final readonly class FormPlan
{
    public const PROVIDER    = 'provider';
    public const DATASET     = 'dataset';
    public const UNSUPPORTED = 'unsupported';

    /**
     * @param list<string> $notes
     * @param list<string> $warnings anything the template converter could not map
     */
    public function __construct(
        public string $kind,
        /** special_class for a provider form, dataset id for a query-driven one. */
        public string $name,
        public array  $notes    = [],
        public array  $warnings = [],
        /** The table `record_ids` belong to, for the caller's benefit. */
        public string $recordTable = '',
        /**
         * The COLUMN of that table `record_ids` are values of.
         *
         * Not always the primary key, and getting this wrong produces an empty
         * document rather than an error. The Deposit Slip breaks on
         * `purchase_invoice_id`, so handing it journal_main ids selects nothing
         * — which is precisely what happened before this existed.
         */
        public string $recordKey = 'id',
    ) {}

    public function runnable(): bool { return $this->kind !== self::UNSUPPORTED; }
}

final class FormRunner
{
    /**
     * special_class => provider, for the four forms Forms serves in code (§5).
     *
     * A form with NO special_class is query-driven and takes the dataset route;
     * that is the majority — 9 of the 18 shipped forms.
     */
    private const PROVIDERS = [
        'entry_builder'     => EntryDocumentProvider::class,
        'statement_builder' => StatementDocumentProvider::class,
        'pos_builder'       => PosDocumentProvider::class,
        'wo_builder'        => WorkOrderDocumentProvider::class,
    ];

    /**
     * What a provider's `record_ids` actually identify.
     *
     * Worth stating because they are not all journal entries and a caller that
     * assumes so passes the wrong ids and gets an empty document rather than an
     * error: a statement is per CONTACT, and a work order has its own journal.
     */
    private const RECORD_TABLE = [
        'entry_builder'     => 'journal_main',
        'statement_builder' => 'contacts',
        'pos_builder'       => 'journal_main',
        'wo_builder'        => 'wo_journal_main',
    ];

    /**
     * Table constants a provider needs before it can be instantiated at all.
     *
     * The Work Order lives in an OPTIONAL extension module, and its definition
     * ships in the catalog whether or not that module is installed. Without
     * this the form plans as runnable and then dies on an undefined constant
     * deep inside the provider — so it is declined, with a reason, up front.
     */
    private const REQUIRES = [
        'wo_builder' => ['TABLE_WO_JOURNAL_MAIN', 'TABLE_WO_JOURNAL_ITEM', 'TABLE_WO_MAIN', 'TABLE_WO_TASK'],
    ];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly \queryFactory $db,
        private readonly Catalog       $catalog,
    ) {}

    /**
     * Decide how a form will be served, without running it or reading any data.
     *
     * A page calls this for every row it lists, so it reads the definition and
     * the catalog and nothing else.
     */
    public function plan(object $report): FormPlan
    {
        if (($report->reporttype ?? '') !== 'frm') {
            return new FormPlan(FormPlan::UNSUPPORTED, '',
                ['This is a report, not a form. DocumentRunner serves it.']);
        }

        // A receipt is served through a native Definition and SerialRenderer,
        // not from a raw legacy object: this entry point converts to a page
        // Template, which a character-cell form has no coordinates to fill.
        // Callers that print a receipt go through planDefinition(); the byte
        // comparison against BuildSeq() lives in verify_forms_serial.php.
        if (!empty($report->serialform)) {
            return new FormPlan(FormPlan::UNSUPPORTED, trim((string)($report->special_class ?? '')),
                ['This is a receipt printer form. It is served from a native'
                    . ' definition, not from PhreeForm XML.']);
        }

        $special = trim((string)($report->special_class ?? ''));

        if ($special !== '') {
            if (!isset(self::PROVIDERS[$special])) {
                return new FormPlan(FormPlan::UNSUPPORTED, $special,
                    ["No provider is registered for '$special', so this form cannot run."]);
            }
            foreach (self::REQUIRES[$special] ?? [] as $constant) {
                if (!defined($constant)) {
                    return new FormPlan(FormPlan::UNSUPPORTED, $special,
                        ['This form belongs to a module that is not installed on this company.']);
                }
            }
            return new FormPlan(FormPlan::PROVIDER, $special,
                recordTable: self::RECORD_TABLE[$special] ?? '');
        }

        $resolver = new DatasetResolver($this->catalog);
        $dataset  = $resolver->resolve($report);
        if ($dataset === null) {
            return new FormPlan(FormPlan::UNSUPPORTED, '', $resolver->notes());
        }
        // A query-driven form breaks on whatever formbreakfield names, which is
        // usually the base table's id but need not be.
        $break = (string)($report->formbreakfield ?? '');
        $column = $break !== '' && str_contains($break, '.')
            ? substr($break, strrpos($break, '.') + 1)
            : ($break !== '' ? $break : 'id');

        return new FormPlan(FormPlan::DATASET, $dataset->id, $resolver->notes(),
            recordTable: $dataset->baseTable,
            recordKey:   $column !== '' ? $column : 'id');
    }

    /**
     * Build the LayoutDocument for the given records.
     *
     * $recordIds are the values the provider breaks documents on — see
     * RECORD_TABLE for what they identify, which is NOT always a journal id.
     *
     * $params carries what the run dialog would answer: `dates` (a PhreeForm
     * date preference letter, used by the statement providers).
     *
     * The definition is CLONED for the same reason DocumentRunner clones it:
     * the legacy engine consumes a definition as it reads it, so one that has
     * been near the old engine — or that will be — must not be shared.
     *
     * @param list<int|string>    $recordIds
     * @param array<string,mixed> $params
     */
    public function run(object $report, array $recordIds, array $params = []): Document
    {
        $report = unserialize(serialize($report));
        $plan   = $this->plan($report);

        if (!$plan->runnable()) {
            throw new \RuntimeException(implode(' ', $plan->notes)
                ?: 'Forms cannot serve this form definition.');
        }

        if (isset($params['dates']) && $params['dates'] !== '') {
            $report->datedefault = (string)$params['dates'];
        }

        $dataset   = $plan->kind === FormPlan::DATASET ? $this->catalog->get($plan->name) : null;
        $converter = new TemplateConverter();
        $template  = $converter->convert($report, $dataset);
        $page      = $converter->convertPageSetup($report);
        $this->warnings = array_merge($converter->warnings(), $converter->unmappedFields());

        $context = $this->contextFor($template->elements);
        $title   = (string)($report->title ?? 'Document');

        $provider = $plan->kind === FormPlan::DATASET
            ? $this->queryProvider($report, $plan, $template, $context, $title, $page)
            : $this->codeProvider($report, $plan, $template, $context, $title, $page);

        return $provider->build(['record_ids' => array_values($recordIds)]);
    }

    // ── the native format ────────────────────────────────────────────────────
    //
    // The same two routes taken from a Definition, alongside the legacy pair
    // rather than in place of it, so verify_forms_native.php can render one
    // definition both ways and compare the bytes. Step 6 deletes plan(), run()
    // and everything above this line.

    /**
     * How a native form will be served.
     *
     * The one question a Definition cannot answer, and the reason this is not
     * just a property read: whether the module a provider belongs to is
     * INSTALLED. The Work Order's definition ships in the catalog whether or
     * not the extension does, and without this check it plans as runnable and
     * then dies on an undefined constant deep inside the provider.
     */
    public function planDefinition(Definition $definition): FormPlan
    {
        if (!$definition->isForm()) {
            return new FormPlan(FormPlan::UNSUPPORTED, '',
                ['This is a report, not a form. DocumentRunner serves it.']);
        }

        // A receipt with no serial layout is one the conversion could not read.
        // Everything else about it may look runnable — it names pos_builder,
        // which IS registered — and rendering it as a page would stack every
        // element at 0,0, because a serial form has no coordinates to place
        // them by (§13.27). SerialRenderer serves the ones that DID convert.
        if ($definition->isSerial() && $definition->serialTemplate === null) {
            return new FormPlan(FormPlan::UNSUPPORTED, (string)$definition->provider,
                ['This is a receipt printer form, but no character-cell layout'
                    . ' could be read from it.']);
        }

        if ($definition->hasProvider()) {
            $special = (string)$definition->provider;
            if (!isset(self::PROVIDERS[$special])) {
                return new FormPlan(FormPlan::UNSUPPORTED, $special,
                    ["No provider is registered for '$special', so this form cannot run."]);
            }
            foreach (self::REQUIRES[$special] ?? [] as $constant) {
                if (!defined($constant)) {
                    return new FormPlan(FormPlan::UNSUPPORTED, $special,
                        ['This form belongs to a module that is not installed on this company.']);
                }
            }
            return new FormPlan(FormPlan::PROVIDER, $special,
                recordTable: $definition->recordTable !== ''
                    ? $definition->recordTable : (self::RECORD_TABLE[$special] ?? ''));
        }

        if ($definition->datasetId === null) {
            return new FormPlan(FormPlan::UNSUPPORTED, '',
                ['This form names no dataset, so nothing can serve it.']);
        }
        return new FormPlan(FormPlan::DATASET, $definition->datasetId,
            recordTable: $definition->recordTable,
            recordKey:   $definition->recordKey);
    }

    /**
     * Build the LayoutDocument for the given records, from a native definition.
     *
     * No clone, because a Definition is immutable — the run-dialog answers are
     * parameters here rather than fields written back onto the definition.
     *
     * @param list<int|string>    $recordIds
     * @param array<string,mixed> $params
     */
    public function runDefinition(Definition $definition, array $recordIds, array $params = []): Document
    {
        $this->warnings = [];
        $plan = $this->planDefinition($definition);

        if (!$plan->runnable()) {
            throw new \RuntimeException(implode(' ', $plan->notes)
                ?: 'Forms cannot serve this form definition.');
        }

        $template = $definition->template ?? new Template();
        $serial   = $definition->serialTemplate;
        $context  = $this->contextFor($serial !== null ? $serial->elements() : $template->elements);
        $title    = $definition->title !== '' ? $definition->title : 'Document';
        $page     = $definition->page;

        $printed = $this->printedTarget($definition);

        if ($plan->kind === FormPlan::DATASET) {
            $provider = new QueryDocumentProvider(
                db:         $this->db,
                catalog:    $this->catalog,
                datasetId:  $this->catalog->get($plan->name)->id,
                template:   $template,
                breakField: $definition->breakField,
                context:    $context,
                title:      $title,
                page:       $page,
                filters:    $definition->filters,
                printedField: $printed,
            );
            return $provider->build(['record_ids' => array_values($recordIds)]);
        }

        // A receipt: the same provider builds the same records, and only the
        // shape they are handed back in differs. Building the LayoutDocument
        // first and taking its records off it keeps the provider ignorant of
        // whether a page or a printer is on the other end — which is the point
        // of the seam, and why no provider needed changing for this.
        if ($serial !== null) {
            if ($printed !== null) {
                $this->warnings[] = 'This receipt asks to mark its records printed, but a'
                    . ' till receipt is not counted: they will print uncounted.';
            }
            $built = (new (self::PROVIDERS[$plan->name])(
                db: $this->db, template: $template,
                context: $context, title: $title, page: $page,
            ))->build(['record_ids' => array_values($recordIds)]);

            return new SerialDocument(
                title:    $title,
                template: $serial,
                records:  $built instanceof LayoutDocument ? $built->records : [],
                context:  $context,
                page:     $page,
            );
        }

        $class = self::PROVIDERS[$plan->name];

        // Only the entry builder takes a printed counter: it is the one that
        // serves the two forms that declare one, and the others describe
        // documents nothing counts — a statement is per contact, a receipt is
        // the till roll. A definition that asks anyway is told so rather than
        // quietly printing uncounted, because "the flag never moved" is
        // precisely the fault this whole piece of work exists to end. Said
        // HERE, above the statement branch, because that branch returns.
        if ($printed !== null && $class !== EntryDocumentProvider::class) {
            $this->warnings[] = 'This form asks to mark its records printed, but the'
                . ' builder that serves it cannot: they will print uncounted.';
        }

        // Only the statement providers need a period: a statement covers a date
        // range, where an invoice is simply itself.
        if ($class === StatementDocumentProvider::class) {
            $preference = (string)($params['dates'] ?? '') !== ''
                ? (string)$params['dates'] : $definition->dateDefault;
            $provider = new StatementDocumentProvider(
                db: $this->db, template: $template,
                range: (new DateRangeResolver(new AppFiscalCalendar($this->db)))->resolve($preference),
                context: $context, title: $title, page: $page,
            );
            return $provider->build(['record_ids' => array_values($recordIds)]);
        }

        if ($class === EntryDocumentProvider::class) {
            return (new EntryDocumentProvider(
                db: $this->db, template: $template,
                context: $context, title: $title, page: $page,
                printedField: $printed,
            ))->build(['record_ids' => array_values($recordIds)]);
        }

        return (new $class(
            db: $this->db, template: $template,
            context: $context, title: $title, page: $page,
        ))->build(['record_ids' => array_values($recordIds)]);
    }

    /**
     * What a delivered record increments, ready for a provider, or null.
     *
     * The key column comes from the DEFINITION rather than from the hook's
     * eventual UPDATE, because only the definition knows it: `record_ids` are
     * not always primary keys, and PhreeForm's own statement used
     * formbreakfield as the WHERE column for exactly that reason.
     *
     * @return array{table:string,field:string,key:string}|null
     */
    private function printedTarget(Definition $definition): ?array
    {
        $target = $definition->printedTarget();
        if ($target === null) {
            if ($definition->printedField !== '') {
                $this->warnings[] = "The printed-counter field '{$definition->printedField}' is not"
                    . ' a table.field pair, so these records will print uncounted.';
            }
            return null;
        }
        $key = preg_match('/^[a-z_][a-z0-9_]*$/i', $definition->recordKey)
             ? $definition->recordKey : 'id';
        return $target + ['key' => $key];
    }

    /**
     * The company block, derived from the template rather than from a fixed list.
     *
     * PhreeForm resolved CDta/CBlk elements by calling constant() on the stored
     * field name at render time, so the set of constants a form needs is
     * whatever that form happens to reference — there is no canonical list to
     * hard-code. Reading them back off the converted template is the only way
     * the Context is guaranteed to cover the form, and it keeps constant() on
     * this side of the seam where it belongs.
     *
     * A name that is not defined is left OUT rather than blanked, so the
     * renderer's own missing-binding handling decides what to draw.
     */
    private function contextFor(array $elements): Context
    {
        $values = [];
        $collect = function (string $name) use (&$values): void {
            if ($name === '' || isset($values[$name])) return;
            if (defined($name)) $values[$name] = TypedValue::text((string)constant($name));
        };
        foreach ($elements as $element) {
            if ($element instanceof Field && $element->bind->scope === Scope::Context) {
                $collect($element->bind->name);
            }
            if ($element instanceof FieldBlock) {
                foreach ($element->parts as $part) {
                    if ($part->bind->scope === Scope::Context) $collect($part->bind->name);
                }
            }
        }
        return new Context($values, $_SESSION['language'] ?? 'en_us');
    }

    /**
     * A query-driven form: the dataset route.
     *
     * The definition's own filterlist matters more here than it looks. These
     * are the filters PhreeForm applied invisibly — the journal id that makes a
     * Purchase Order a purchase order, the address-book type that picks the
     * billing address — and without them the query returns every journal and
     * every address on file, which renders a document that is wrong rather than
     * one that is empty.
     */
    private function queryProvider(
        object $report, FormPlan $plan, object $template,
        Context $context, string $title, object $page
    ): QueryDocumentProvider {
        $dataset = $this->catalog->get($plan->name);
        $map     = TemplateConverter::mapFor($dataset);

        // ReportConverter does this, and doing it a second time here was a
        // mistake worth naming: a hand-rolled loop that treated every filter as
        // an equality matched nothing on the Deposit Slip, whose journal filter
        // holds the LIST "2, 18, 19". The legacy modes — IN_LIST, RANGE,
        // NULL, the boolean synonyms, and column-to-column comparison — are all
        // in that one converter, and a form's filters are the same filters.
        $converter = new ReportConverter();
        $filters   = $converter->convertFilters($report, $dataset);
        $this->warnings = array_merge($this->warnings, $converter->warnings());

        return new QueryDocumentProvider(
            db:         $this->db,
            catalog:    $this->catalog,
            datasetId:  $dataset->id,
            template:   $template,
            breakField: (string)($report->formbreakfield ?? '') !== ''
                            ? ($map[strtolower((string)$report->formbreakfield)] ?? 'id')
                            : 'id',
            context:    $context,
            title:      $title,
            page:       $page,
            filters:    $filters,
        );
    }

    /** A form served by one of the four ported special classes. */
    private function codeProvider(
        object $report, FormPlan $plan, object $template,
        Context $context, string $title, object $page
    ): object {
        $class = self::PROVIDERS[$plan->name];

        // The statement providers are the only ones that need a period: a
        // statement covers a date range, where an invoice is simply itself.
        if ($class === StatementDocumentProvider::class) {
            $range = (new DateRangeResolver(new AppFiscalCalendar($this->db)))
                ->resolve((string)($report->datedefault ?? ''));
            return new StatementDocumentProvider(
                db: $this->db, template: $template, range: $range,
                context: $context, title: $title, page: $page,
            );
        }

        return new $class(
            db: $this->db, template: $template,
            context: $context, title: $title, page: $page,
        );
    }

    /**
     * Anything the converter could not map on the last run.
     *
     * Shown to the user rather than swallowed: an element the template cannot
     * bind is a form that silently prints one field fewer than its author drew.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
