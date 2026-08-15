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
//  Path: /modules/forms/classes/definition/definition.php
//
/**
 * Forms — a stored definition, in Forms' own terms. See docs/forms-design.md §13.3.
 *
 * This is what a `.form` file holds and what the designer will edit. It is the
 * native format's in-memory shape, and the reason it exists is narrow: Forms
 * currently reads PhreeForm's XML at every run and converts it on the way past,
 * which means the format Forms can express is bounded by the format PhreeForm
 * could store. That is a ceiling on every feature after 1.0.0.
 *
 * The distinction that makes this worth a class rather than an array: a
 * Definition is ALREADY CONVERTED. Its fields, filters, sorts and bindings are
 * dataset field KEYS, not `table.column` strings; its elements are the nine
 * document types, not the legacy fourteen; its route is stated rather than
 * inferred from a table list. Everything DatasetResolver, ReportConverter and
 * TemplateConverter work out at run time is settled here, once, at conversion.
 *
 * Two things deliberately stay OUT, because they are not properties of the
 * definition:
 *
 *   - the resolved date RANGE. `dateDefault` is a preference — "this quarter" —
 *     and what it means depends on today and on the accounting calendar. Only
 *     the field it constrains is stored.
 *   - the Context. A form's company block is read back off its own template at
 *     run time, because a stored copy of COMPANY_NAME would go stale.
 *
 * Above the seam, and free of both the database and HTTP: a Definition can be
 * built, compared and serialised without booting the application, which is what
 * lets docs/tools/verify_forms_native.php hold it to a byte comparison.
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Document\{Format, PageSetup, ReportColumn, ReportStyle, Template};

/** Anything wrong with a stored definition, or with the attempt to build one. */
class DefinitionException extends \RuntimeException {}

final readonly class Definition
{
    public const REPORT = 'rpt';
    public const FORM   = 'frm';

    /**
     * @param string  $id          the catalog id this was loaded from, if any
     * @param string  $type        REPORT or FORM
     * @param ?string $provider    a special class Forms serves in CODE — a
     *   structural report or one of the four form builders. Null for everything
     *   query-driven, INCLUDING the legacy classes that were deleted rather
     *   than ported: their behaviour is baked into the fields below, so a native
     *   definition needs no DATASET_SERVED list to except them.
     * @param ?string $datasetId   the dataset the query runs against, or null
     *   when a provider builds the rows in code
     *
     * @param list<ReportColumn>   $columns         report only, in display order
     * @param array<string,string> $resolvedColumns report only; column key => the
     *   dataset field holding the id it is derived from (§4.3.1)
     *
     * @param list<\FreeBooks\Forms\Catalog\Filter> $filters
     * @param list<\FreeBooks\Forms\Catalog\Sort>   $sorts
     *
     * @param ?string $dateField the dataset field key the date preference
     *   constrains. Null means either that this definition has no date field or
     *   that the one it names cannot be served — the two are indistinguishable
     *   here on purpose, because both mean "do not date-filter".
     * @param string  $breakField form only: the dataset field key one document
     *   ends and the next begins on
     * @param string  $recordTable form only: the table `record_ids` belong to
     * @param string  $recordKey   form only: the COLUMN of that table they are
     *   values of, which is not always its primary key
     */
    public function __construct(
        public string       $id          = '',
        public string       $type        = self::REPORT,
        public string       $title       = '',
        public string       $description = '',
        public string       $group       = '',
        public ?string      $provider    = null,
        public ?string      $datasetId   = null,
        public PageSetup    $page        = new PageSetup(),
        // when it runs
        public string       $dateDefault = '',
        public ?string      $dateField   = null,
        public int          $period      = 0,
        // what it selects
        public array        $filters     = [],
        public array        $sorts       = [],
        // a report
        public array        $columns         = [],
        public ?string      $groupBy         = null,
        public ?Format      $groupFormat     = null,
        public bool         $totalsOnly      = false,
        public array        $resolvedColumns = [],
        public ReportStyle  $style           = new ReportStyle(),
        // a form
        public ?Template    $template    = null,
        public string       $breakField  = 'id',
        public string       $recordTable = '',
        public string       $recordKey   = 'id',
    ) {}

    public function isForm(): bool   { return $this->type === self::FORM; }
    public function isReport(): bool { return $this->type === self::REPORT; }

    /** Served by a class rather than by a dataset query. */
    public function hasProvider(): bool { return $this->provider !== null && $this->provider !== ''; }
}
