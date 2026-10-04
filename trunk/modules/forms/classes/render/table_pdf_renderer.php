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
//  Path: /modules/forms/classes/render/table_pdf_renderer.php
//
/**
 * Forms — draws a TableDocument as a PDF (docs/forms-design.md §4.1, §8).
 *
 * The reports counterpart of LayoutPdfRenderer, and the replacement for
 * PhreeForm's `report_generator.php`. It reads a document and nothing else: no
 * `$report`, no `$Heading`, no `$Seq`, no database.
 *
 * ## What it reproduces, and why the odd parts are deliberate
 *
 * PhreeForm's report page is not a plain grid, and three of its behaviours look
 * like mistakes until you see the output they produce:
 *
 * - **Stacked columns.** A column carries a `pageBreak` flag (PhreeForm's
 *   `columnbreak`). Columns accumulate *downward* inside one x-band until a
 *   column with the flag set closes the band and starts the next one to the
 *   right. So four columns can render as two bands of two stacked lines, which
 *   is how the shipped reports fit wide field sets onto a portrait page. A
 *   band's width is the widest column assigned to it.
 * - **The border state machine.** A total row is drawn with `LR` borders, the
 *   row *after* it gets a `T`, and the one after that returns to none. That is
 *   what closes the box around a total without drawing doubled lines where a
 *   group ends and the next begins.
 * - **Full-width fill before text.** Each row paints a background strip from the
 *   current y to the bottom margin *before* the cells are written, then the
 *   cells overwrite it. Painting only the row's own height leaves hairline gaps
 *   between rows at some sizes; this is PhreeForm's fix, kept so converted
 *   reports print as they did.
 *
 * Pagination is manual, exactly as in the legacy engine: TCPDF's auto page break
 * is switched off and the renderer decides when a row will not fit, because the
 * decision depends on the tallest row seen so far, not on the current cell.
 *
 * ## What it deliberately does NOT reproduce
 *
 * Values arrive as TypedValue and are formatted here, at render time, via the
 * shared Formatter. PhreeForm formatted in the data layer, which is why its CSV
 * carried "$ 1,234.50" (§1.2). The same document therefore yields a readable PDF
 * and a machine-readable CSV with no second report definition.
 */

namespace FreeBooks\Forms\Render;

use FreeBooks\Forms\Document\{
    Align, Document, Format, FormsRenderer, PageSetup, RenderedOutput, RenderOptions, ReportColumn,
    ReportStyle, Row, RowKind, Style, TableDocument, TypedValue,
    UnsupportedDocumentException
};

final class TablePdfRenderer implements FormsRenderer
{
    /** Points -> millimetres, as used throughout PhreeForm. */
    private const PT_TO_MM = 0.35;

    /** PhreeForm's PF_DEFAULT_ROWSPACE: padding added to the font size per row. */
    private const ROW_SPACING = 2.0;

    /** Zebra striping on data rows, and the flat grey behind totals. */
    private const ZEBRA_FILL = [224, 235, 255];
    private const TOTAL_FILL = 240;

    private ?\FreeBooks\Forms\Render\PdfCanvas $pdf = null;
    private ?TableDocument $document = null;

    /** x positions of each column band; index 0 is the left margin. */
    private array $bandX = [];

    /** Which band each column belongs to. */
    private array $columnBand = [];

    /** Top of the next row, and the tallest row drawn so far. */
    private float $y = 0.0;
    private float $maxRowHeight = 0.0;

    /** Bottom of the printable area. */
    private float $pageBottom = 0.0;

    /** Right edge of the printable area, on the orientation actually chosen. */
    private float $pageRight = 0.0;

    public function __construct(
        private readonly Formatter $formatter,
        private readonly string    $defaultFont      = 'helvetica',
        private readonly float     $defaultSize      = 10.0,
        private readonly string    $groupTotalLabel  = 'Group Total For:',
        private readonly string    $reportTotalLabel = 'Report Total For:',
        private readonly string    $pageLabel        = 'Page',
        /**
         * Applied to a column that declares no width. The default matches
         * PhreeForm's PF_DEFAULT_COLUMN_WIDTH, which is passed in rather than
         * read here so the renderer stays free of application globals (§2.1).
         */
        private readonly float     $defaultColumnWidth = 25.0,
    ) {}

    public function supports(Document $doc): bool
    {
        return $doc instanceof TableDocument;
    }

    public function render(Document $doc, RenderOptions $options = new RenderOptions()): RenderedOutput
    {
        if (!$doc instanceof TableDocument) {
            throw new UnsupportedDocumentException(
                'TablePdfRenderer draws TableDocument, not ' . $doc::class . '.');
        }

        $this->document = $doc;
        $page  = $doc->pageSetup();
        $style = $doc->style();

        // Orientation is decided before the canvas exists, because it cannot be
        // changed afterwards. A report whose columns do not fit the page it
        // declares is printed landscape rather than clipped: past the right
        // margin TCPDF cuts whatever falls outside, losing the tail of the last
        // column.
        //
        // The designer's WIDTHS are never overridden to achieve this — turning
        // the paper is a change to the paper, not to the report. Only a table
        // too wide even for landscape is scaled, and then only by the little it
        // is over.
        $orientation = $this->orientationFor($doc, $page);
        $pdf = new PdfCanvas($orientation, 'mm', PdfCanvas::paperFormat($page->size), true, 'UTF-8', false);
        $pdf->SetCellPadding(0);
        $pdf->SetMargins($page->marginLeft, $page->marginTop, $page->marginRight);
        // Off deliberately: this renderer decides where rows break, using the
        // tallest row seen so far. TCPDF breaking underneath it would split a
        // row between pages.
        $pdf->SetAutoPageBreak(false, $page->marginBottom);
        $pdf->SetDrawColor(128, 0, 0);
        $pdf->SetLineWidth(1 * self::PT_TO_MM);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->setHeaderMargin(0);
        $pdf->setFooterMargin(0);
        $pdf->SetTitle($doc->title());
        $pdf->SetCreator('FreeBooks');

        $this->pdf        = $pdf;
        $this->pageBottom = $pdf->getPageHeight() - $page->marginBottom;
        $this->pageRight  = $pdf->getPageWidth() - $page->marginRight;
        $this->layOutColumns($doc, $page->marginLeft, $this->pageRight);

        // The furniture repeats on every page, so it lives in the header
        // callback — the one TCPDF hook that fires per page.
        $pdf->setHeaderCallback(fn() => $this->drawFurniture($style));
        $pdf->setFooterCallback(fn() => $this->drawPageNumber());

        $pdf->AddPage();
        $this->drawRows($doc, $style);
        $this->fillToBottom($page->marginLeft, $page->marginRight);

        $filename = $options->filename ?? $this->suggestFilename($doc);
        return new RenderedOutput($pdf->Output($filename, 'S'), 'application/pdf', $filename);
    }

    // ── layout ───────────────────────────────────────────────────────────────

    /**
     * Portrait as declared, or landscape if that is what it takes to fit.
     *
     * Never the other way round: a report that asks for landscape keeps it even
     * if it would fit portrait, because the author chose it and a wide page is
     * not a defect.
     */
    private function orientationFor(TableDocument $doc, PageSetup $page): string
    {
        $declared = strtoupper($page->orientation) === 'L' ? 'L' : 'P';
        if ($declared === 'L') return 'L';

        $needed = $this->naturalWidth($doc);
        foreach (['P', 'L'] as $candidate) {
            $probe     = new PdfCanvas($candidate, 'mm', PdfCanvas::paperFormat($page->size), true, 'UTF-8', false);
            $printable = $probe->getPageWidth() - $page->marginLeft - $page->marginRight;
            if ($needed <= $printable + 0.01) return $candidate;
        }
        // Wider than any orientation of this paper. Take the widest one anyway,
        // so the scaling that follows is the smallest it can be: a table a
        // little past a landscape page would shrink by about a third on
        // portrait.
        return 'L';
    }

    /** The width the columns ask for, before any fitting. */
    private function naturalWidth(TableDocument $doc): float
    {
        $edges = $this->bandEdges($doc, 0.0);
        return (float)end($edges);
    }

    /**
     * Band right-edges, from the columns alone.
     *
     * @return list<float>
     */
    private function bandEdges(TableDocument $doc, float $left): array
    {
        $edges = [$left];
        $band  = 1;
        $open  = false;   // a band has been started and not yet closed by a break
        foreach ($doc->columns as $column) {
            $width = $column->width ?? $this->defaultColumnWidth;
            $edges[$band] = max($edges[$band] ?? 0.0, $edges[$band - 1] + $width);
            $open = !$column->pageBreak;
            if ($column->pageBreak) $band++;
        }
        // A trailing band with no closing column still needs a right edge — but
        // ONLY when one is actually open. When the last column breaks (which is
        // the ordinary side-by-side grid, and most of the library), $band has
        // already moved past the last band in use, and adding an edge here would
        // invent an empty column one default-width wide: the table would measure
        // wider than it is, and layOutColumns() would shrink a report that fits.
        if ($open && !isset($edges[$band])) {
            $edges[$band] = $edges[$band - 1] + $this->defaultColumnWidth;
        }
        return $edges;
    }

    /**
     * Resolve columns into x bands.
     *
     * Columns stack downward within a band; a column flagged `pageBreak` closes
     * its band. A band is as wide as the widest column in it, which is why the
     * running maximum is taken rather than simply summing.
     *
     * Scaling here is the LAST resort, and normally does nothing: a table too
     * wide for its page has already been given a landscape one by
     * orientationFor(). What reaches this is a table too wide for any
     * orientation of its paper, where the choice is between narrowing the bands
     * — which wraps text — and PhreeForm's behaviour of running past the right
     * margin and letting TCPDF cut whatever fell outside.
     */
    private function layOutColumns(TableDocument $doc, float $left, float $rightLimit): void
    {
        $this->bandX      = $this->bandEdges($doc, $left);
        $this->columnBand = [];

        $band = 1;
        foreach ($doc->columns as $index => $column) {
            $this->columnBand[$index] = $band;
            if ($column->pageBreak) $band++;
        }

        $rightEdge = (float)end($this->bandX);
        $available = $rightLimit - $left;
        if ($available > 0 && $rightEdge - $left > $available) {
            $scale = $available / ($rightEdge - $left);
            foreach ($this->bandX as $index => $x) {
                $this->bandX[$index] = $left + (($x - $left) * $scale);
            }
        }
    }

    private function bandWidth(int $band): float
    {
        return ($this->bandX[$band] ?? 0.0) - ($this->bandX[$band - 1] ?? 0.0);
    }

    private function rowHeight(?Style $style): float
    {
        return (($style?->size ?: $this->defaultSize) + self::ROW_SPACING) * self::PT_TO_MM;
    }

    // ── page furniture ───────────────────────────────────────────────────────

    /** Company name, titles, filter description, then the column headings. */
    /**
     * "Page 1 / 2", centred 8 mm up from the foot of every page.
     *
     * Fixed font and size deliberately: PhreeForm hard-codes both here rather
     * than taking them from the report definition, so the page number stays
     * legible whatever the report's own styling does.
     */
    private function drawPageNumber(): void
    {
        $this->pdf->SetY(-8);
        $this->pdf->SetFont($this->defaultFont, '', 8);
        $this->pdf->SetTextColor(0);
        $this->pdf->Cell(0, 10,
            $this->pageLabel . ' ' . $this->pdf->PageNo() . ' / ' . $this->pdf->getAliasNbPages(),
            0, 0, 'C');
    }

    private function drawFurniture(ReportStyle $style): void
    {
        $doc  = $this->document;
        $page = $doc->pageSetup();
        $pdf  = $this->pdf;

        $pdf->SetX($page->marginLeft);
        $pdf->SetY($page->marginTop);
        $pdf->SetFillColor(255);

        $company = $doc->context->get('COMPANY_NAME');
        if ($style->showHeading && $company !== null && (string)$company->raw !== '') {
            $this->applyStyle($style->heading, 12.0, 'B');
            $pdf->Cell(0, $this->rowHeight($style->heading), (string)$company->raw, 0, 1,
                       $this->alignCode($style->heading, Align::Center));
        }
        foreach ([[$style->showTitle1, $style->title1, $style->title1Text],
                  [$style->showTitle2, $style->title2, $style->title2Text]] as [$show, $band, $text]) {
            if (!$show || $text === null || $text === '') continue;
            $this->applyStyle($band, 10.0);
            $pdf->Cell(0, $this->rowHeight($band), $this->substitute($text), 0, 1,
                       $this->alignCode($band, Align::Center));
        }

        // The filter description sits under a rule, separating it from the grid.
        $this->applyStyle($style->filter, 8.0);
        $pdf->MultiCell(0, $this->rowHeight($style->filter), (string)($doc->filterText ?? ''),
                        'B', $this->alignCode($style->filter, Align::Left), false, 1);

        $this->y = $pdf->GetY();
        $this->drawHeadings($style);
    }

    private function drawHeadings(ReportStyle $style): void
    {
        $doc = $this->document;
        $pdf = $this->pdf;

        $this->applyStyle($style->data, $this->defaultSize);
        $pdf->SetDrawColor(128, 0, 0);
        $pdf->SetLineWidth(1 * self::PT_TO_MM);

        $height = $this->rowHeight($style->data);
        $top    = $this->y;
        $maxY   = $top;
        $lastY  = $top;

        foreach ($doc->columns as $index => $column) {
            $band  = $this->columnBand[$index];
            $width = $this->bandWidth($band);
            $text  = $column->heading;
            if ($style->truncate) $text = $this->truncate($text, $width);

            $pdf->SetLeftMargin($this->bandX[$band - 1]);
            $pdf->SetX($this->bandX[$band - 1]);
            $pdf->SetY($lastY);
            $pdf->MultiCell($width, $height, $text, 0,
                            $this->alignCode($style->data, Align::Left), false, 1);

            // A flagged column closes its band: the next heading returns to the
            // top of the row, one band to the right.
            $lastY = $column->pageBreak ? $top : $pdf->GetY();
            if ($pdf->GetY() > $maxY) $maxY = $pdf->GetY();
        }

        // One rule under the whole heading block, however tall it grew.
        $pdf->SetLeftMargin($this->bandX[0]);
        $pdf->SetX($this->bandX[0]);
        $pdf->SetY($top);
        $pdf->Cell(0, $maxY - $top, ' ', 'B');
        $this->y = $maxY + self::PT_TO_MM;
    }

    // ── body ─────────────────────────────────────────────────────────────────

    private function drawRows(TableDocument $doc, ReportStyle $style): void
    {
        $pdf    = $this->pdf;
        $height = $this->rowHeight($style->data);

        $fill    = false;      // zebra toggle
        $needTop = 'no';       // border state machine — see the class comment
        $this->maxRowHeight = 0.0;
        $groupBreakPending  = false;

        foreach ($doc->rows as $row) {
            // Three different questions. The caption band
            // belongs only to a group break, because only a break keeps its
            // caption outside the cells; a subtotal or a section heading is set
            // in the totals style but prints its own first column; and a
            // separator is a deliberate gap, so it neither stripes nor advances
            // the stripe.
            $isBreak   = $row->kind->isBreakTotal();
            $emphasis  = $row->kind->isTotal() || $row->kind === RowKind::SectionHeading;
            $separator = $row->kind === RowKind::Separator;

            if ($isBreak) {
                // A total needs room for its caption and the row beneath it.
                if ($this->y + (2 * $this->maxRowHeight) > $this->pageBottom) {
                    $this->breakPage();
                }
                $this->applyStyle($style->totals, $this->defaultSize);
                $pdf->SetLeftMargin($this->bandX[0]);
                $pdf->SetX($this->bandX[0]);
                $pdf->SetY($this->y);
                $pdf->SetFillColor(self::TOTAL_FILL);
                $pdf->Cell(0, $this->pageBottom - $this->y, '', 0, 0, 'L', true);

                $caption = ($row->kind === RowKind::GroupTotal ? $this->groupTotalLabel
                                                              : $this->reportTotalLabel)
                         . ' ' . (string)($row->label ?? '');
                $pdf->SetX($this->bandX[0]);
                $pdf->SetY($this->y);
                $pdf->Cell(0, $height, $caption, 1, 1, 'C');

                $this->y      = $pdf->GetY() + self::PT_TO_MM;
                $needTop      = 'next';
                $fill         = 'total';
                if ($row->kind === RowKind::GroupTotal && $style->groupPageBreak) {
                    $groupBreakPending = true;
                }
                // falls through: the total's own figures draw like a data row
            }

            $border = 0;
            if ($separator) $fill = false;
            if ($needTop === 'yes') {
                $border  = 'T';
                $fill    = false;   // the row after a total is never striped
                $needTop = 'no';
            } elseif ($needTop === 'next') {
                $border  = 'LR';
                $needTop = 'yes';
            }

            if (($this->y + $this->maxRowHeight) > $this->pageBottom) $this->breakPage();

            $this->applyStyle($emphasis ? $style->totals : $style->data, $this->defaultSize);
            $pdf->SetLeftMargin($this->bandX[0]);
            $pdf->SetX($this->bandX[0]);
            $pdf->SetY($this->y);
            if     ($fill === 'total') $pdf->SetFillColor(self::TOTAL_FILL);
            elseif ($fill === true)    $pdf->SetFillColor(...self::ZEBRA_FILL);
            else                       $pdf->SetFillColor(255);
            // Paint to the bottom margin, then overwrite with cells; see the
            // class comment on why the row's own height is not enough.
            $pdf->Cell(0, $this->pageBottom - $this->y, '', $border, 0, 'L', true);

            $top   = $this->y;
            $maxY  = $top;
            $lastY = $top;

            foreach ($doc->columns as $index => $column) {
                $band  = $this->columnBand[$index];
                $width = $this->bandWidth($band);
                $value = $row->cells[$column->key] ?? null;
                $text  = $value instanceof TypedValue
                    // Reports have no per-record currency the way forms do:
                    // rows are already in the home currency, so no override.
                    ? $this->formatter->format($value, $column->format)
                    : '';
                if ($style->truncate) $text = $this->truncate($text, $width);

                $pdf->SetLeftMargin($this->bandX[$band - 1]);
                $pdf->SetX($this->bandX[$band - 1]);
                $pdf->SetY($lastY);
                $pdf->MultiCell($width, $height, $text, 0,
                                $this->alignCode($column->align !== null ? new Style(align: $column->align)
                                                                        : ($emphasis ? $style->totals : $style->data),
                                                 Align::Left),
                                false, 1);

                $lastY = $column->pageBreak ? $top : $pdf->GetY();
                if ($pdf->GetY() > $maxY) $maxY = $pdf->GetY();
            }
            $pdf->SetLeftMargin($this->bandX[0]);

            $drawn = $maxY - $top;
            if ($drawn > $this->maxRowHeight) $this->maxRowHeight = $drawn;
            $this->y = $maxY;
            $fill    = $separator ? false : ($fill ? false : true);

            if ($groupBreakPending) {
                $this->breakPage();
                $groupBreakPending = false;
            }
        }
    }

    // ── pagination ───────────────────────────────────────────────────────────

    private function breakPage(): void
    {
        $page = $this->document->pageSetup();
        $this->fillToBottom($page->marginLeft, $page->marginRight);
        $this->pdf->AddPage();          // fires the header callback, which resets $this->y
        $this->maxRowHeight = 0.0;
        $this->pdf->SetX($this->bandX[0]);
    }

    /**
     * Close the grid with white to the bottom margin.
     *
     * The margins are nudged out by a quarter-millimetre first: without it the
     * fill occasionally leaves a visible edge against the page border, which is
     * the same workaround the legacy engine used.
     */
    private function fillToBottom(float $left, float $right): void
    {
        $pdf  = $this->pdf;
        $page = $this->document->pageSetup();

        $pdf->SetMargins($left - 0.25, $page->marginTop, $right - 0.25);
        $pdf->SetX($left - 0.25);
        $pdf->SetY($this->y);
        $pdf->SetFillColor(255);
        $pdf->Cell(0, $this->pageBottom - $this->y + 0.25, '', 'T', 0, 'L', true);
        $pdf->SetMargins($left, $page->marginTop, $right);
        $pdf->SetLeftMargin($this->bandX[0]);
        $pdf->SetX($this->bandX[0]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function applyStyle(?Style $style, float $fallbackSize, string $weight = ''): void
    {
        // The band's own weight wins over the caller's default: a caller asks
        // for bold because the company heading is conventionally bold, and an
        // author who says otherwise has said otherwise.
        $this->pdf->SetFont($style?->font ?: $this->defaultFont,
            ($style !== null && $style->fontStyle() !== '') ? $style->fontStyle() : $weight,
            $style?->size ?: $fallbackSize);
        $colour = $style?->colour;
        $this->pdf->SetTextColor($colour?->r ?? 0, $colour?->g ?? 0, $colour?->b ?? 0);
    }

    private function alignCode(?Style $style, Align $fallback): string
    {
        return ($style?->align ?? $fallback)->value;
    }

    /**
     * PhreeForm's TextReplace(): the three substitutions its titles use.
     *
     * `%date%` goes through the formatter rather than date() directly, because
     * PhreeForm renders it in the user's locale format — printing an ISO date
     * where the rest of the report shows dd/mm/yyyy is a visible regression.
     * `%company%` comes from the context, not the COMPANY_NAME constant: the
     * renderer reads no globals (§2.1).
     */
    private function substitute(string $text): string
    {
        $today   = $this->formatter->format(TypedValue::date(date('Y-m-d')), new Format('date'));
        $company = $this->document?->context->get('COMPANY_NAME');
        return str_replace(
            ['%date%', '%reportname%', '%company%'],
            [$today, $this->document?->title() ?? '', (string)($company?->raw ?? '')],
            $text);
    }

    private function truncate(string $text, float $width): string
    {
        if ($text === '' || $width <= 0) return $text;
        $limit = $width * 0.90;                       // allow for proportional spacing
        while ($text !== '' && $this->pdf->GetStringWidth($text) > $limit) {
            $text = mb_substr($text, 0, mb_strlen($text) - 1);
        }
        return $text;
    }

    private function suggestFilename(TableDocument $doc): string
    {
        $base = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc->title()), '_');
        return ($base === '' ? 'report' : $base) . '.pdf';
    }
}
