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
//  Path: /modules/forms/classes/document/documents.php
//
/**
 * Forms — the document model. This file IS the seam. See docs/forms-design.md §4.
 *
 * A Document is everything a renderer needs and nothing more: no database
 * handle, no HTTP, no globals. That is what makes the render layer replaceable
 * (design decision D) and testable without booting the application.
 *
 * Two shapes, because reports and forms genuinely differ:
 *   TableDocument  — columns, rows, group breaks, totals   (reports)
 *   LayoutDocument — positioned elements, one render per record (forms)
 */

namespace FreeBooks\Forms\Document;

/**
 * Marker for anything renderable.
 *
 * Kept deliberately thin: a renderer declares which concrete shapes it supports
 * (CSV handles TableDocument but not LayoutDocument), so behaviour belongs on
 * the renderer, not here.
 */
interface Document
{
    public function title(): string;
    public function pageSetup(): PageSetup;
    /** @return list<AfterRenderHook> */
    public function afterRender(): array;
}

// ─────────────────────────────────────────────────────────────────────────────
// LayoutDocument — forms
// ─────────────────────────────────────────────────────────────────────────────

/** The positioned elements. Immutable, and shared across every record. */
final readonly class Template
{
    /** @param list<Element> $elements */
    public function __construct(public array $elements = []) {}

    /** @return list<Element> */
    public function ofType(string $class): array
    {
        return array_values(array_filter($this->elements, fn($e) => $e instanceof $class));
    }
}

/**
 * The data for one rendered form.
 *
 * `values` is a single flat namespace holding header fields *and* totals
 * together — confirmed against Invoice.xml, where invoice_subtotal, sales_tax,
 * total_paid and balance_due sit alongside post_date and rep_id as ordinary
 * Data bindings. That is why totals need no separate concept.
 *
 * `currency` is per-record, not per-document: PhreeForm's posted_cur formatting
 * depends on the currency the individual record was posted in.
 */
final readonly class Record
{
    /**
     * @param array<string,TypedValue>                       $values
     * @param array<string,list<array<string,TypedValue>>>   $tables  source => rows
     */
    public function __construct(
        public array     $values   = [],
        public array     $tables   = [],
        public ?Currency $currency = null,
        public ?string   $key      = null,   // originating record id, for hooks/tracing
    ) {}

    public function value(string $name): ?TypedValue
    {
        return $this->values[$name] ?? null;
    }

    /** @return list<array<string,TypedValue>> */
    public function table(string $source): array
    {
        return $this->tables[$source] ?? [];
    }
}

/**
 * A form: one template, rendered once per record.
 *
 * Page-agnostic by design. The document describes a *logical* layout; the
 * renderer paginates. PhreeForm delegated pagination to TCPDF, and keeping that
 * boundary means a future renderer is not bound to TCPDF's model.
 */
final readonly class LayoutDocument implements Document
{
    /**
     * @param list<Record>          $records
     * @param list<AfterRenderHook> $afterRender
     */
    public function __construct(
        public string    $title,
        public Template  $template,
        public array     $records,
        public Context   $context     = new Context(),
        public PageSetup $page        = new PageSetup(),
        public array     $afterRender = [],
    ) {}

    public function title(): string        { return $this->title; }
    public function pageSetup(): PageSetup { return $this->page; }
    public function afterRender(): array   { return $this->afterRender; }
}

// ─────────────────────────────────────────────────────────────────────────────
// TableDocument — reports
// ─────────────────────────────────────────────────────────────────────────────

/**
 * What a row means.
 *
 * Replaces PhreeForm's magic string prefixes, where $Data[$row][0] held 'd',
 * 'g:<label>' or 'r:<title>' and every consumer re-parsed them.
 */
enum RowKind
{
    case Data;
    case GroupTotal;
    case ReportTotal;
}

/** One column of a report. */
final readonly class ReportColumn
{
    public function __construct(
        public string  $key,
        public string  $heading,
        public ?float  $width  = null,
        public ?Align  $align  = null,
        public ?Format $format = null,
        public bool    $total  = false,
        public bool    $pageBreak = false,   // PhreeForm's columnbreak
    ) {}
}

/** One row: data, a group subtotal, or the report total. */
final readonly class Row
{
    /** @param array<string,TypedValue> $cells keyed by ReportColumn::$key */
    public function __construct(
        public RowKind $kind,
        public array   $cells,
        public ?string $label = null,   // group value / report title on total rows
    ) {}
}

/**
 * A report: columns and rows, with group and report totals as first-class row
 * kinds rather than tagged array entries.
 *
 * PhreeForm passed this as a 2-D array plus three globals ($Heading, $Seq and
 * $report); the structure here carries all of it explicitly.
 */
final readonly class TableDocument implements Document
{
    /**
     * @param list<ReportColumn>    $columns
     * @param list<Row>             $rows
     * @param list<AfterRenderHook> $afterRender
     */
    public function __construct(
        public string    $title,
        public array     $columns,
        public array     $rows,
        public Context   $context     = new Context(),
        public PageSetup $page        = new PageSetup(),
        public ?string   $filterText  = null,   // human-readable description of the filters applied
        public array     $afterRender = [],
    ) {}

    public function title(): string        { return $this->title; }
    public function pageSetup(): PageSetup { return $this->page; }
    public function afterRender(): array   { return $this->afterRender; }
}
