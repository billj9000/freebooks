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
// +-----------------------------------------------------------------+
//  Path: /modules/forms/classes/render/csv_renderer.php
//
/**
 * Forms — renders a TableDocument as CSV, for machines.
 *
 * This renderer is the payoff for typing values instead of pre-formatting them,
 * and it is worth being concrete about what it fixes.
 *
 * PhreeForm formatted in the DATA layer: ProcessData() ran during
 * BuildDataArray(), so by the time any output format saw a value it was already
 * a display string. A money column marked `dlr` reached the CSV writer as
 * "$ 1,234.50" and a date as "08/10/2026" — so a report built to be readable as
 * a PDF exported as text no importer could parse, and the only workaround was to
 * maintain a second copy of the report with the formatting stripped.
 *
 * Here the document carries typed values and each renderer decides. The PDF
 * renderer formats for a human; this one does not format at all:
 *
 *     money   -> 1234.50      (no symbol, no thousands separator)
 *     date    -> 2026-08-10   (ISO, not the UI locale)
 *     boolean -> 1 / 0
 *
 * One report, one definition, and both audiences served — which is what makes a
 * report like "export invoices with payment details to another accounting
 * system" a normal thing to build rather than a special case.
 *
 * Group and report total rows are emitted by default with their kind in the
 * first column, so a consumer can skip them; `dataRowsOnly` drops them entirely,
 * which is usually what an importer wants.
 */

namespace FreeBooks\Forms\Render;

use FreeBooks\Forms\Document\{
    Document, FormsRenderer, RenderedOutput, RenderOptions, Row, RowKind,
    TableDocument, TypedValue, UnsupportedDocumentException, ValueType
};

final class CsvRenderer implements FormsRenderer
{
    public function __construct(
        private readonly string $delimiter    = ',',
        private readonly bool   $includeHeader = true,
        private readonly bool   $dataRowsOnly  = false,
        /** Prefix a row-kind column so totals are distinguishable. */
        private readonly bool   $labelRowKinds = true,
    ) {}

    public function supports(Document $doc): bool
    {
        // A LayoutDocument is positioned graphics; there is no meaningful CSV of
        // one, and pretending otherwise would emit a plausible but wrong file.
        return $doc instanceof TableDocument;
    }

    public function render(Document $doc, RenderOptions $options = new RenderOptions()): RenderedOutput
    {
        if (!$doc instanceof TableDocument) {
            throw new UnsupportedDocumentException(
                'CsvRenderer renders TableDocument, not ' . $doc::class . '.');
        }

        $lines = [];

        if ($this->includeHeader) {
            $heading = [];
            if ($this->labelRowKinds && !$this->dataRowsOnly) $heading[] = 'row_type';
            foreach ($doc->columns as $column) $heading[] = $column->heading;
            $lines[] = $this->line($heading);
        }

        foreach ($doc->rows as $row) {
            if ($this->dataRowsOnly && $row->kind !== RowKind::Data) continue;

            $cells = [];
            if ($this->labelRowKinds && !$this->dataRowsOnly) $cells[] = $this->kindLabel($row);

            foreach ($doc->columns as $column) {
                $value   = $row->cells[$column->key] ?? null;
                $cells[] = $value === null ? '' : $this->raw($value);
            }
            $lines[] = $this->line($cells);
        }

        $filename = $options->filename ?? ($this->safeName($doc->title()) . '.csv');
        // A BOM so spreadsheets open UTF-8 correctly; importers tolerate it.
        return new RenderedOutput("\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n", 'text/csv', $filename);
    }

    /** The machine-readable form of a value — never the display form. */
    private function raw(TypedValue $value): string
    {
        if ($value->raw === null) return '';

        return match ($value->type) {
            ValueType::Money, ValueType::Decimal =>
                is_numeric($value->raw) ? sprintf('%.2f', (float)$value->raw) : (string)$value->raw,
            ValueType::Integer =>
                is_numeric($value->raw) ? (string)(int)$value->raw : (string)$value->raw,
            ValueType::Boolean => $value->raw ? '1' : '0',
            ValueType::Date    => $this->isoDate((string)$value->raw),
            default            => (string)$value->raw,
        };
    }

    /** Dates leave as ISO, not in the viewer's locale. */
    private function isoDate(string $value): string
    {
        if ($value === '' || $value === '0000-00-00') return '';
        $time = strtotime($value);
        return $time === false ? $value : date('Y-m-d', $time);
    }

    private function kindLabel(Row $row): string
    {
        return match ($row->kind) {
            RowKind::Data        => 'data',
            RowKind::GroupTotal  => 'group_total:' . ($row->label ?? ''),
            RowKind::ReportTotal => 'report_total',
        };
    }

    /** @param list<string> $cells */
    private function line(array $cells): string
    {
        return implode($this->delimiter, array_map($this->quote(...), $cells));
    }

    /** RFC 4180 quoting: only when needed, and doubling embedded quotes. */
    private function quote(string $value): string
    {
        $needs = str_contains($value, $this->delimiter)
              || str_contains($value, '"')
              || str_contains($value, "\n")
              || str_contains($value, "\r");
        return $needs ? '"' . str_replace('"', '""', $value) . '"' : $value;
    }

    private function safeName(string $title): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $title);
        return trim((string)$name, '_') ?: 'report';
    }
}
