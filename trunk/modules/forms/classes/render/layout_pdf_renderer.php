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
//  Path: /modules/forms/classes/render/layout_pdf_renderer.php
//
/**
 * Forms — renders a LayoutDocument to PDF via TCPDF.
 *
 * One record renders as one page group: a group starts at page 1 and paginates
 * as its line items overflow, so "2 of 3" counts within an invoice rather than
 * across a 40-invoice batch.
 *
 * WHAT THIS RENDERER MAY NOT DO, and why it matters (docs/forms-design.md §2.1):
 * it never queries, never emits an HTTP header, never calls exit(), and never
 * mutates business data. Everything it draws arrives in the Document. The
 * legacy engine broke all four — most consequentially the last, since
 * setprintedfield fired an UPDATE from inside the PDF builder, so merely
 * previewing an invoice incremented its printed counter. Forms declares that as
 * an AfterRenderHook which the CALLER runs on delivery.
 *
 * Pagination lives here rather than in the document. The document describes a
 * logical layout; how it breaks across sheets is this layer's business, which
 * is what keeps the model free of TCPDF's assumptions.
 *
 * ── The two-pass total ──────────────────────────────────────────────────────
 * A total belongs on the last page, but its value is only known once every row
 * has been placed — and by then earlier pages are already committed. PhreeForm
 * solved this by pre-seeding every Ttl with the literal "Continued" and relying
 * on TCPDF calling Footer() late enough on the final page to pick up the real
 * figure. That is a side effect of call ordering, and it silently prints
 * "Continued" as a total if the ordering ever shifts.
 *
 * Here the same output is produced deliberately: the renderer tracks which page
 * ends each group, and a ContinuedOnOverflow element draws its placeholder on
 * every page of the group except the last, where it draws the value. The
 * decision is explicit and testable rather than emergent.
 */

namespace FreeBooks\Forms\Render;

require_once(__DIR__ . '/formatter.php');
require_once(__DIR__ . '/pdf_canvas.php');

use FreeBooks\Forms\Document\{
    Align, Barcode, Binding, Column, Document, Element, Field, FieldBlock, Image,
    LayoutDocument, Line, PageNumber, Rect, Record, RenderedOutput, RenderOptions,
    FormsRenderer, Scope, StaticText, Style, Table, TypedValue,
    UnsupportedDocumentException, ValueType, Visibility
};

final class LayoutPdfRenderer implements FormsRenderer
{
    /** Points -> millimetres. PhreeForm's pervasive 0.35 magic number. */
    private const PT_TO_MM = 0.35;

    private ?PdfCanvas $pdf = null;

    /** The record being drawn, and the template it is drawn with. */
    private ?Record       $record   = null;
    private ?LayoutDocument $document = null;

    /** Page number on which the current group started, and where it ends. */
    private int  $groupFirstPage = 1;
    private ?int $groupLastPage  = null;

    /** Set only while the deferred pass is drawing; true on the final page. */
    private ?bool $deferredIsLastPage = null;

    public function __construct(
        private readonly Formatter $formatter,
        private readonly string    $defaultFont    = 'helvetica',
        private readonly float     $rowSpacing     = 2.0,
        private readonly string    $continuedLabel = 'Continued',
        private readonly string    $pageOfLabel    = 'of',
        private readonly ?string   $imageDirectory = null,
    ) {}

    public function supports(Document $doc): bool
    {
        return $doc instanceof LayoutDocument;
    }

    public function render(Document $doc, RenderOptions $options = new RenderOptions()): RenderedOutput
    {
        if (!$doc instanceof LayoutDocument) {
            throw new UnsupportedDocumentException(
                'LayoutPdfRenderer draws LayoutDocument, not ' . $doc::class . '.');
        }

        $this->document = $doc;
        $page           = $doc->pageSetup();
        $pdf            = new PdfCanvas($page->orientation, 'mm', $page->size, true, 'UTF-8', false);

        $pdf->SetCellPadding(0);
        $pdf->SetMargins($page->marginLeft, $page->marginTop, $page->marginRight);
        // Auto page breaks off: this renderer decides where tables break, so
        // TCPDF must not insert pages underneath it.
        $pdf->SetAutoPageBreak(false, $page->marginBottom);
        $pdf->SetFont($this->defaultFont);
        $pdf->SetLineWidth(1 * self::PT_TO_MM);
        // Header() must stay enabled — it is TCPDF's only callback for content
        // that repeats on each page, and it is where the furniture is drawn.
        // Footer() stays OFF: TCPDF fires it on every page close, including at
        // Close(), which would re-draw the totals over themselves on the final
        // page. Last-page content is drawn explicitly instead, once the extent
        // of the record is known.
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        $pdf->setHeaderMargin(0);
        $pdf->SetTitle($doc->title());
        $pdf->SetCreator('FreeBooks');

        $this->pdf = $pdf;
        $pdf->setHeaderCallback(fn() => $this->drawRepeatingElements());

        foreach ($doc->records as $record) {
            $this->renderRecord($record);
        }

        // Nothing was rendered: emit a valid empty page rather than a corrupt file.
        if ($pdf->getNumPages() === 0) $pdf->AddPage();

        $filename = $options->filename ?? $this->suggestFilename($doc);
        $bytes    = $pdf->Output($filename, 'S');

        $this->pdf = $this->record = $this->document = null;

        return new RenderedOutput($bytes, 'application/pdf', $filename);
    }

    // ── one record ───────────────────────────────────────────────────────────

    private function renderRecord(Record $record): void
    {
        $this->record = $record;

        // Pass 1 places the content and discovers how many pages the record
        // takes. TCPDF fires Header() on AddPage(), which draws the repeating
        // elements; totals are held back until the page count is known.
        $this->groupLastPage = null;
        $this->pdf->startPageGroup();     // TCPDF numbers pages within this record
        $this->pdf->AddPage();
        $this->groupFirstPage = $this->pdf->getPage();

        foreach ($this->document->template->elements as $element) {
            if ($element instanceof Table) $this->drawTable($element, $record);
        }

        // Pass 2: the extent is known, so deferred content can be placed. Each
        // page of the group is revisited, because a total needs its placeholder
        // on the pages before the last and its value on the last.
        $this->groupLastPage = $this->pdf->getPage();
        for ($page = $this->groupFirstPage; $page <= $this->groupLastPage; $page++) {
            $this->pdf->setPage($page);
            $this->drawDeferredElements($page === $this->groupLastPage);
        }
        $this->pdf->setPage($this->groupLastPage);
    }

    /**
     * Elements that repeat on every page of a record. Called by TCPDF's Header()
     * on each new page, which is the only point at which page furniture can be
     * placed before body content.
     */
    private function drawRepeatingElements(): void
    {
        if ($this->record === null || $this->document === null) return;

        foreach ($this->document->template->elements as $element) {
            if ($element instanceof Table) continue;              // body, not furniture
            if (!$this->visibleOnCurrentPage($element)) continue;
            $this->drawElement($element);
        }
    }

    /**
     * Draw the content that could not be placed until the record's extent was
     * known: totals, and values held back for the last page.
     *
     * @param bool $isLastPage true when drawing the final page of the group
     */
    private function drawDeferredElements(bool $isLastPage): void
    {
        if ($this->record === null || $this->document === null) return;

        $this->deferredIsLastPage = $isLastPage;
        try {
            foreach ($this->document->template->elements as $element) {
                if ($element instanceof Table) continue;
                $visible = $element->visible;
                if ($visible === Visibility::ContinuedOnOverflow) {
                    $this->drawElement($element);          // placeholder or value
                } elseif ($visible === Visibility::LastPageOnly && $isLastPage) {
                    $this->drawElement($element);          // box was already drawn empty
                }
            }
        } finally {
            $this->deferredIsLastPage = null;
        }
    }

    /**
     * Whether an element appears on the page currently being drawn.
     *
     * LastPageOnly still returns true. The element is drawn on every page — but
     * EMPTY until the final one, so its border and fill appear throughout while
     * its value does not. That is PhreeForm's behaviour and it is load-bearing:
     * pf_91 boxes each of its six totals, and skipping the element outright
     * leaves those boxes missing from every page but the last.
     *
     * ContinuedOnOverflow likewise appears on every page, showing the
     * placeholder until the last, where the real value is drawn over it.
     */
    private function visibleOnCurrentPage(Element $element): bool
    {
        return match ($element->visible) {
            Visibility::AllPages            => true,
            Visibility::FirstPageOnly       => $this->pdf->getPage() === $this->groupFirstPage,
            Visibility::LastPageOnly        => true,    // drawn blank; value added later
            // Deferred entirely. Drawing a placeholder here would leave
            // "Continued" underneath the real total on the final page, since
            // text is painted, not replaced.
            Visibility::ContinuedOnOverflow => false,
        };
    }

    /**
     * True while the extent of the record is still unknown, i.e. during the
     * placing pass, when last-page content cannot yet be resolved.
     */
    private function awaitingLastPage(): bool
    {
        return $this->groupLastPage === null;
    }

    // ── elements ─────────────────────────────────────────────────────────────

    private function drawElement(Element $element): void
    {
        match (true) {
            $element instanceof StaticText => $this->drawText($element, $element->text),
            $element instanceof Field      => $this->drawText($element, $this->fieldText($element)),
            $element instanceof FieldBlock => $this->drawText($element, $this->blockText($element)),
            $element instanceof PageNumber => $this->drawText($element, $this->pageNumberText($element)),
            $element instanceof Line       => $this->drawLine($element),
            $element instanceof Rect       => $this->drawRect($element),
            $element instanceof Image      => $this->drawImage($element),
            $element instanceof Barcode    => $this->drawBarcode($element),
            default                        => null,     // unknown element: skip, never fatal
        };
    }

    /** A bound value, or its placeholder while the last page is still unknown. */
    private function fieldText(Field $element): string
    {
        // A total shows the placeholder on every page but the last.
        if ($element->visible === Visibility::ContinuedOnOverflow
            && $this->deferredIsLastPage === false) {
            return $this->continuedLabel;
        }
        if ($this->awaitingLastPage()) {
            // A deferred value stays blank, leaving its box drawn but empty.
            if ($element->visible === Visibility::ContinuedOnOverflow) return $this->continuedLabel;
            if ($element->visible === Visibility::LastPageOnly)        return '';
        }
        $value = $this->resolve($element->bind);
        return $value === null ? '' : $this->formatter->format($value, $element->format, $this->record->currency);
    }

    private function blockText(FieldBlock $element): string
    {
        if ($this->awaitingLastPage() && $element->visible === Visibility::LastPageOnly) return '';

        $out = '';
        foreach ($element->parts as $part) {
            $value  = $this->resolve($part->bind);
            $text   = $value === null ? '' : $this->formatter->format($value, $part->format, $this->record->currency);
            $out   .= $this->formatter->separator($text, $part->separator);
        }
        return $out;
    }

    private function pageNumberText(PageNumber $element): string
    {
        // getPageGroupAlias() returns a placeholder that TCPDF itself replaces
        // with the group's final page count at output time.
        return strtr($element->pattern, [
            '{page}'  => (string)$this->pdf->getGroupPageNo(),
            '{pages}' => $this->pdf->getPageGroupAlias(),
            '{of}'    => $this->pageOfLabel,
        ]);
    }

    private function drawText(Element $element, string $text): void
    {
        $box   = $element->box;
        $style = $element->style;

        $this->applyFont($style);
        $this->applyTextColour($style);

        $border = '0';
        if ($style?->borderShow) {
            $border = '1';
            $colour = $style->borderColour ?? $style->colour;
            if ($colour) $this->pdf->SetDrawColor($colour->r, $colour->g, $colour->b);
            $this->pdf->SetLineWidth(($style->borderSize ?? 1) * self::PT_TO_MM);
        }

        if ($style?->truncate && $box->width) $text = $this->truncate($text, $box->width);

        $this->pdf->SetXY($box->x, $box->y);
        $this->pdf->MultiCell(
            $box->width  ?? 0,
            $box->height ?? 0,
            $text,
            $border,
            $style?->align?->value ?? 'L',
            false
        );
    }

    private function drawLine(Line $element): void
    {
        // The box carries the line as start point plus offset; see the legacy
        // converter, where linetype/length are resolved into that offset.
        $box    = $element->box;
        $colour = $element->style?->borderColour ?? $element->style?->colour;
        if ($colour) $this->pdf->SetDrawColor($colour->r, $colour->g, $colour->b);
        $this->pdf->SetLineWidth(($element->style?->borderSize ?? 0) * self::PT_TO_MM);
        $this->pdf->Line($box->x, $box->y, $box->x + ($box->width ?? 0), $box->y + ($box->height ?? 0));
    }

    private function drawRect(Rect $element): void
    {
        $box    = $element->box;
        $colour = $element->style?->borderColour ?? $element->style?->colour;
        if ($colour) $this->pdf->SetDrawColor($colour->r, $colour->g, $colour->b);
        $this->pdf->SetLineWidth(($element->style?->borderSize ?? 1) * self::PT_TO_MM);
        $this->pdf->Rect($box->x, $box->y, $box->width ?? 0, $box->height ?? 0);
    }

    private function drawImage(Image $element): void
    {
        $src = $element->src;
        if ($src instanceof Binding) {
            $value = $this->resolve($src);
            $src   = $value === null ? '' : (string)$value->raw;
        }
        if ($src === '') return;

        $path = $this->imageDirectory !== null ? rtrim($this->imageDirectory, '/') . '/' . $src : $src;
        if (!is_file($path)) return;      // a missing logo must not abort the document

        $box = $element->box;
        $this->pdf->Image($path, $box->x, $box->y, $box->width ?? 0, $box->height ?? 0);
    }

    private function drawBarcode(Barcode $element): void
    {
        $value = $this->resolve($element->bind);
        $text  = $value === null ? '' : (string)$value->raw;
        if ($text === '') return;

        $box    = $element->box;
        $colour = $element->style?->colour;
        $this->pdf->write1DBarcode($text, $element->symbology, $box->x, $box->y,
            $box->width ?? 0, $box->height ?? 0, 0.4, [
                'position'    => 'L',
                'border'      => false,
                'padding'     => 0,
                'fgcolor'     => $colour ? [$colour->r, $colour->g, $colour->b] : [0, 0, 0],
                'bgcolor'     => false,
                'text'        => false,
            ]);
    }

    // ── tables ───────────────────────────────────────────────────────────────

    /**
     * Draw a table's rows, paginating when they overflow its box.
     *
     * The box is fixed by the template, so overflow means a new page rather
     * than a taller box; the heading is redrawn at the top of each page.
     */
    private function drawTable(Table $element, Record $record): void
    {
        $rows = $record->table($element->source);
        $box  = $element->box;
        $maxY = $box->y + ($box->height ?? 0);

        [$y, $headingHeight] = $this->drawTableHeading($element, $box->y);

        // The break test uses the height of the row just drawn, NOT the height
        // of the row about to be drawn. That is what PhreeForm does
        // (`if (($this->y0 + $MaxRowHt) > $MaxBoxY)`, where $MaxRowHt is the
        // previous row's height), and it decides which page each row lands on.
        // Estimating the upcoming row instead is more obviously correct but
        // moves rows across page boundaries relative to every form printed so
        // far, so the legacy rule is reproduced deliberately.
        $previousRowHeight = 0.0;

        foreach ($rows as $row) {
            if ($y + $previousRowHeight > $maxY) {
                $this->drawTableBorders($element, $headingHeight);
                $this->pdf->AddPage();               // Header() redraws the furniture
                [$y, $headingHeight] = $this->drawTableHeading($element, $box->y);
                $previousRowHeight   = $headingHeight;
            }
            $rowTop            = $y;
            $y                 = $this->drawTableRow($element, $this->rowCells($element, $row), $y, false);
            $previousRowHeight = $y - $rowTop;
        }
        $this->drawTableBorders($element, $headingHeight);
    }

    /**
     * Draw the heading row.
     *
     * Returns [y where data starts, height of the heading BOX]. Those two differ
     * and must not be conflated: a bordered heading is underlined at the bottom
     * of its text and then followed by an extra gap of the heading font size in
     * points (PhreeForm's `$this->y0 += $Params->hsize * 0.35`).
     *
     * The box stops at the text — the same edge as the rule — while the data
     * starts after the gap. Sizing the box to include the gap draws its bottom
     * edge just below the rule, which shows up as a doubled line under every
     * table heading. The gap is not cosmetic either: it consumes table height
     * and so shifts where rows break across pages.
     *
     * @return array{0: float, 1: float}
     */
    private function drawTableHeading(Table $element, float $top): array
    {
        $textEnd = $this->drawTableRow($element, $this->headingCells($element), $top, true);
        $dataTop = $textEnd;

        $header = $element->headerStyle ?? $element->style;
        if ($header?->borderShow) {
            $colour = $header->borderColour;
            if ($colour) $this->pdf->SetDrawColor($colour->r, $colour->g, $colour->b);
            $this->pdf->SetLineWidth(($header->borderSize ?? 1) * self::PT_TO_MM);
            $this->pdf->Line($element->box->x, $textEnd, $element->box->x + ($element->box->width ?? 0), $textEnd);
            $dataTop += ($header->size ?? 0) * self::PT_TO_MM;
        }
        return [$dataTop, $textEnd - $top];
    }

    /**
     * Outline the table and rule between its columns.
     *
     * Drawn per page rather than once, because each page carries its own copy
     * of the box. The heading is boxed separately from the body so the two can
     * differ in colour and weight, as the legacy definitions expect.
     */
    private function drawTableBorders(Table $element, float $headingHeight): void
    {
        $box    = $element->box;
        $width  = $box->width  ?? 0;
        $height = $box->height ?? 0;
        $header = $element->headerStyle ?? $element->style;

        if ($header?->borderShow && $headingHeight > 0) {
            $colour = $header->borderColour;
            if ($colour) $this->pdf->SetDrawColor($colour->r, $colour->g, $colour->b);
            $this->pdf->SetLineWidth(($header->borderSize ?? 1) * self::PT_TO_MM);
            $this->pdf->Rect($box->x, $box->y, $width, $headingHeight);
            $this->drawColumnRules($element, $box->y, $headingHeight);
        }

        if ($element->style?->borderShow) {
            $colour = $element->style->borderColour;
            if ($colour) $this->pdf->SetDrawColor($colour->r, $colour->g, $colour->b);
            $this->pdf->SetLineWidth(($element->style->borderSize ?? 1) * self::PT_TO_MM);
            $this->pdf->Rect($box->x, $box->y + $headingHeight, $width, $height - $headingHeight);
            $this->drawColumnRules($element, $box->y + $headingHeight, $height - $headingHeight);
        }
    }

    /** Vertical rules at each column boundary. */
    private function drawColumnRules(Table $element, float $top, float $height): void
    {
        $x = $element->box->x;
        foreach ($element->columns as $column) {
            $this->pdf->Line($x, $top, $x, $top + $height);
            $x += (float)($column->width ?? 0);
        }
    }

    /** @return list<array{text:string,width:float,align:string}> */
    private function headingCells(Table $element): array
    {
        $cells = [];
        foreach ($element->columns as $column) {
            $cells[] = [
                'text'  => $column->heading,
                'width' => (float)($column->width ?? 0),
                // The heading style wins over the column's own alignment when it
                // sets one; PhreeForm's 'A' (auto) meant "use the column's".
                'align' => $element->headerStyle?->align?->value ?? $column->align?->value ?? 'L',
                'style' => null,
            ];
        }
        return $cells;
    }

    /**
     * @param array<string,TypedValue> $row
     * @return list<array{text:string,width:float,align:string}>
     */
    private function rowCells(Table $element, array $row): array
    {
        $cells = [];
        foreach ($element->columns as $column) {
            $value  = $row[$column->bind->name] ?? null;
            $text   = $value === null ? '' : $this->formatter->format($value, $column->format, $this->record->currency);
            $width  = (float)($column->width ?? 0);
            if ($element->style?->truncate && $width > 0) $text = $this->truncate($text, $width);
            $cells[] = [
                'text'  => $text,
                'width' => $width,
                'align' => $column->align?->value ?? 'L',
                'style' => $column->style,          // null falls back to the table's
            ];
        }
        return $cells;
    }

    /**
     * Draw one row of cells left to right, returning the y position after it.
     * A row is as tall as its tallest wrapped cell.
     */
    private function drawTableRow(Table $element, array $cells, float $y, bool $isHeading): float
    {
        $rowStyle = $isHeading ? ($element->headerStyle ?? $element->style) : $element->style;

        $lineHeight = (($rowStyle?->size ?? 10) + $this->rowSpacing) * self::PT_TO_MM;
        $x          = $element->box->x;
        $maxY       = $y;

        foreach ($cells as $cell) {
            // Each column may carry its own font/size/colour.
            $cellStyle = $cell['style'] ?? $rowStyle;
            $this->applyFont($cellStyle);
            $this->applyTextColour($cellStyle);

            $this->pdf->SetLeftMargin($x);
            $this->pdf->SetXY($x, $y);
            $this->pdf->MultiCell($cell['width'], $lineHeight, $cell['text'], 0, $cell['align'], false);
            if ($this->pdf->GetY() > $maxY) $maxY = $this->pdf->GetY();
            $x += $cell['width'];
        }
        $this->pdf->SetLeftMargin($this->document->pageSetup()->marginLeft);

        return $maxY > $y ? $maxY : $y + $lineHeight;
    }

    // ── values ───────────────────────────────────────────────────────────────

    /** Resolve a binding against the record, the document context, or aggregates. */
    private function resolve(Binding $binding): ?TypedValue
    {
        return match ($binding->scope) {
            Scope::Record    => $this->record?->value($binding->name),
            Scope::Context   => $this->document?->context->get($binding->name),
            Scope::Aggregate => $this->aggregate($binding),
        };
    }

    /**
     * Add up the fields an aggregate names.
     *
     * Each field is looked for among the record's own values first and summed
     * across the line items only if it is not one — because PhreeForm's Ttl
     * means both things depending on who supplies the data. A provider-backed
     * form (entry_builder) sums HEADER values: load_total_results() does
     * `$total += $this->$field` over the builder's own properties. A
     * query-driven form sums ROWS: `select sum(a + b) ...`. One rule covers
     * both, and it never queries.
     */
    private function aggregate(Binding $binding): TypedValue
    {
        $sum = 0.0;
        foreach ($binding->aggregateFields() as $field) {
            $value = $this->record?->value($field);
            if ($value !== null) {
                if (is_numeric($value->raw)) $sum += (float)$value->raw;
                continue;
            }
            foreach ($this->record?->tables ?? [] as $rows) {
                foreach ($rows as $row) {
                    $raw = $row[$field]->raw ?? null;
                    if (is_numeric($raw)) $sum += (float)$raw;
                }
            }
        }
        return new TypedValue($sum, ValueType::Money);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function applyFont(?Style $style): void
    {
        $this->pdf->SetFont($style?->font ?: $this->defaultFont, '', $style?->size ?: 10);
    }

    private function applyTextColour(?Style $style): void
    {
        $colour = $style?->colour;
        $this->pdf->SetTextColor($colour?->r ?? 0, $colour?->g ?? 0, $colour?->b ?? 0);
    }

    /** Shorten text until it fits the column width. */
    private function truncate(string $text, float $width): string
    {
        if ($text === '' || $width <= 0) return $text;
        $limit = $width * 0.90;                       // allow for proportional spacing
        while ($text !== '' && $this->pdf->GetStringWidth($text) > $limit) {
            $text = mb_substr($text, 0, mb_strlen($text) - 1);
        }
        return $text;
    }

    private function suggestFilename(LayoutDocument $doc): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc->title());
        return trim($base, '_') . '.pdf' ?: 'document.pdf';
    }
}
