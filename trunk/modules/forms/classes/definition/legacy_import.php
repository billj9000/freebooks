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
//  Path: /modules/forms/classes/definition/legacy_import.php
//
/**
 * Forms — a PhreeForm definition, converted once into a native one. See §13.3.
 *
 * A legacy definition's conversion, performed once. Nothing here is separate
 * logic: it is the same DatasetResolver, ReportConverter and TemplateConverter
 * calls the runners make, in the same order, with the answers kept rather than
 * thrown away after the render.
 *
 * The order genuinely matters and is not a tidiness point. ReportConverter is
 * stateful across the call sequence — convertColumns() builds the field map and
 * resets the resolved-column list, convertGroup() adds to it, and
 * resolvedColumns() is only complete once both have run. Calling them in a
 * different order produces a definition that is subtly less than the one the
 * runner would have built, and nothing errors.
 *
 * This class is how a PhreeForm XML definition becomes a native one: the
 * loader's legacy branch uses it, and decision L keeps it as the designer's
 * "Import a PhreeForm definition". That is why it is here in the definition
 * layer rather than in legacy/: it is a feature of the new format, not a part
 * of the old one.
 *
 * What it does not decide: whether a definition is fit to migrate. It converts
 * and it reports, through warnings(); the migration reads those warnings and
 * blocks on anything unconvertible rather than skipping it (§13.3 condition
 * 1).
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Catalog\Catalog;
use FreeBooks\Forms\Legacy\{DatasetResolver, ReportConverter, TemplateConverter};

final class DefinitionBuilder
{
    /**
     * special_class => the form provider that serves it, mirroring
     * FormRunner::PROVIDERS. Listed by name rather than by class because a
     * Definition stores the name; which class serves it is the runner's
     * business and may change without rewriting every stored file.
     */
    private const FORM_PROVIDERS = ['entry_builder', 'statement_builder', 'pos_builder', 'wo_builder'];

    /** Mirrors FormRunner::RECORD_TABLE — what a provider's record ids identify. */
    private const RECORD_TABLE = [
        'entry_builder'     => 'journal_main',
        'statement_builder' => 'contacts',
        'pos_builder'       => 'journal_main',
        'wo_builder'        => 'wo_journal_main',
    ];

    /** Mirrors DocumentRunner::STRUCTURAL — reports whose rows a class computes. */
    private const STRUCTURAL = ['is_budget', 'balance_sheet', 'acct_recon'];

    /**
     * Special classes DELETED rather than ported, whose reports take the
     * ordinary dataset route (DocumentRunner::DATASET_SERVED).
     *
     * A native definition records the route it takes, and the behaviour these
     * classes supplied — the aged summary's per-account roll-up — is baked into
     * the converted group and totalsOnly by ReportConverter's
     * SPECIAL_CLASS_PROFILES, so a runner of native definitions has nothing to
     * except.
     */
    private const DATASET_SERVED = ['aged_receivables', 'aged_summary'];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(private readonly Catalog $catalog) {}

    /**
     * @param object $report a legacy definition as returned by get_report_details()
     * @param string $id     the catalog id it was loaded from
     */
    public function fromLegacy(object $report, string $id = ''): Definition
    {
        $this->warnings = [];

        // Parts of the conversion consume the object as they read it, so it works
        // on a copy: a caller that still holds the object must get it back intact.
        $report = unserialize(serialize($report));

        $special = trim((string)($report->special_class ?? ''));
        $common  = [
            'id'          => $id,
            'title'       => (string)($report->title       ?? ''),
            'description' => (string)($report->description ?? ''),
            'group'       => (string)($report->groupname   ?? ''),
            'dateDefault' => (string)($report->datedefault ?? ''),
            'period'      => (int)($report->period ?? 0),
            // Read by the print page rather than by any renderer, which is
            // exactly why they are easy to lose in a format change.
            'filenamePrefix' => (string)($report->filenameprefix ?? ''),
            'emailMessage'   => (string)($report->emailmessage   ?? ''),
        ];

        return ($report->reporttype ?? '') === 'frm'
            ? $this->form($report, $special, $common)
            : $this->reportOf($report, $special, $common);
    }

    /** @param array<string,mixed> $common */
    private function form(object $report, string $special, array $common): Definition
    {
        $converter = new TemplateConverter();

        // Carried rather than dropped. The coordinates the conversion produces
        // for a serial form are meaningless — every field of a receipt stores
        // an EMPTY abscissa and ordinate, because a receipt lays out by
        // sequence and alignment instead — and without the flag a receipt would
        // read as an ordinary form with everything at 0,0 (§13.27).
        $serial = !empty($report->serialform);

        if ($special !== '') {
            if (!in_array($special, self::FORM_PROVIDERS, true)) {
                throw new DefinitionException("No provider is registered for '$special',"
                    . ' so this form cannot be converted.');
            }
            // A provider builds its own records, so there is no dataset to map
            // bindings onto: the provider addresses values by their legacy name
            // and TemplateConverter leaves them alone when given no dataset.
            $template = $serial ? null : $converter->convert($report, null);
            $serialTemplate = $serial ? $converter->convertSerial($report, null) : null;
            $this->warnings = array_merge($converter->warnings(), $converter->unmappedFields());

            return new Definition(...$common,
                type:        Definition::FORM,
                provider:    $special,
                page:        $converter->convertPageSetup($report),
                template:    $template,
                recordTable: self::RECORD_TABLE[$special] ?? '',
                printedField: $this->printedField($report),
                serial:      $serial,
                serialTemplate: $serialTemplate,
            );
        }

        $resolver = new DatasetResolver($this->catalog);
        $dataset  = $resolver->resolve($report);
        if ($dataset === null) {
            throw new DefinitionException(implode(' ', $resolver->notes())
                ?: 'No dataset can serve this form.');
        }

        $template = $serial ? null : $converter->convert($report, $dataset);
        $serialTemplate = $serial ? $converter->convertSerial($report, $dataset) : null;
        $this->warnings = array_merge($converter->warnings(), $converter->unmappedFields());

        // The definition's own filterlist is what makes a Purchase Order a
        // purchase order rather than every journal row on file, so a form needs
        // the report converter too — see FormRunner::queryProvider().
        $filters   = new ReportConverter();
        $converted = $filters->convertFilters($report, $dataset);
        $this->warnings = array_merge($this->warnings, $filters->warnings());

        $map   = TemplateConverter::mapFor($dataset);
        $break = (string)($report->formbreakfield ?? '');

        return new Definition(...$common,
            type:        Definition::FORM,
            datasetId:   $dataset->id,
            page:        $converter->convertPageSetup($report),
            filters:     $converted,
            template:    $template,
            breakField:  $break !== '' ? ($map[strtolower($break)] ?? 'id') : 'id',
            recordTable: $dataset->baseTable,
            recordKey:   $this->recordKey($break),
            printedField: $this->printedField($report),
            serial:      $serial,
            serialTemplate: $serialTemplate,
        );
    }

    /**
     * The counter a delivered document increments, from `setprintedfield`.
     *
     * Two of the shipped forms declare one — the Invoice and the Deposit
     * Receipt — and it is the only reason `journal_main.printed` is ever
     * non-zero, which is what the Sales Invoice Manager's "unprinted" filter
     * reads (§13.45).
     *
     * A malformed value is a WARNING rather than a failure: it costs a counter,
     * not a document, and refusing to convert the form over it would be a worse
     * trade than printing it uncounted and saying so.
     */
    private function printedField(object $report): string
    {
        $value = trim((string)($report->setprintedfield ?? ''));
        if ($value === '') return '';

        $probe = new Definition(printedField: $value);
        if ($probe->printedTarget() === null) {
            $this->warnings[] = "The printed-counter field '$value' is not a table.field pair,"
                . ' so this form will print without marking its records printed.';
            return '';
        }
        return $value;
    }

    /**
     * The COLUMN of the record table that ids are values of.
     *
     * Not always the primary key: the Deposit Slip breaks on
     * `purchase_invoice_id`, and handing it journal_main ids selects nothing
     * while erroring about nothing.
     */
    private function recordKey(string $breakField): string
    {
        if ($breakField === '') return 'id';
        $column = str_contains($breakField, '.')
            ? substr($breakField, strrpos($breakField, '.') + 1)
            : $breakField;
        return $column !== '' ? $column : 'id';
    }

    /** @param array<string,mixed> $common */
    private function reportOf(object $report, string $special, array $common): Definition
    {
        $converter = new ReportConverter();

        if (in_array($special, self::STRUCTURAL, true)) {
            // Rows computed in code, so there is no dataset, no filter and no
            // sort to convert — the class decides all three.
            $columns = $converter->convertStructuralColumns($report);
            $this->warnings = $converter->warnings();

            return new Definition(...$common,
                type:     Definition::REPORT,
                provider: $special,
                page:     $converter->convertPageSetup($report),
                columns:  $columns,
                style:    $converter->convertStyle($report),
            );
        }

        if ($special !== '' && !in_array($special, self::DATASET_SERVED, true)) {
            if (in_array($special, self::FORM_PROVIDERS, true)) {
                throw new DefinitionException("This names the form builder '$special' while calling"
                    . ' itself a report; it is printed rather than run as a table.');
            }
            throw new DefinitionException("No class named '$special' is registered,"
                . ' so this definition cannot be converted.');
        }

        $resolver = new DatasetResolver($this->catalog);
        $dataset  = $resolver->resolve($report);
        if ($dataset === null) {
            throw new DefinitionException(implode(' ', $resolver->notes())
                ?: 'No dataset can serve this report.');
        }

        // Order is load-bearing — see the class comment. convertColumns() first,
        // because it builds the field map the rest read and resets the resolved
        // list convertGroup() then adds to.
        $columns = $converter->convertColumns($report, $dataset);
        $group   = $converter->convertGroup($report, $dataset);
        $sorts   = $converter->convertSorts($report, $dataset);
        $filters = $converter->convertFilters($report, $dataset);
        // The date RANGE is resolved at run time — "this quarter" depends on
        // today — so only the field it constrains is converted here.
        $dateField      = $converter->convertDateField($report, $dataset);
        $this->warnings = $converter->warnings();

        return new Definition(...$common,
            type:            Definition::REPORT,
            datasetId:       $dataset->id,
            page:            $converter->convertPageSetup($report),
            dateField:       $dateField,
            filters:         $filters,
            sorts:           $sorts,
            columns:         $columns,
            groupBy:         $group,
            groupFormat:     $converter->convertGroupFormat($report),
            totalsOnly:      $converter->convertTotalsOnly($report),
            resolvedColumns: $converter->resolvedColumns(),
            style:           $converter->convertStyle($report),
        );
    }

    /**
     * Anything the conversion could not map faithfully.
     *
     * Empty is the claim that this definition converted whole. §13.3 condition 1
     * turns that into a migration that blocks rather than one that skips.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
