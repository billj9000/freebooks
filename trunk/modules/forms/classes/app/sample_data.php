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
//  Path: /modules/forms/classes/app/sample_data.php
//
/**
 * Forms — invented rows, so an empty report can still be laid out (§13.2).
 *
 * A preview of a report that returns nothing is a page of furniture, and column
 * widths, alignment and totals cannot be judged against it. That is not an edge
 * case: a fresh install has no invoices at all, and a report whose stored date
 * preference is "this quarter" shows nothing on a company whose data stopped
 * two years ago. The design doc already accepts this for the form canvas — bind
 * to a real record, fall back to generated values, mark them visibly — and a
 * report wants exactly the same thing.
 *
 * Two rules make the fallback safe rather than misleading:
 *
 * 1. **The document is the real one.** Columns, widths, alignment, grouping,
 *    styling, paper and margins all come from the document that returned no
 *    rows; only the CELLS are invented. Building a plausible-looking document
 *    from scratch would preview a layout the author is not editing.
 * 2. **It says so on the page.** The note goes in the filter band, under the
 *    titles, where a report already explains what it was filtered by — so it
 *    cannot be mistaken for data, and cannot be printed by accident either,
 *    since nothing but the preview ever calls this.
 *
 * Values are derived from the row index rather than randomised: two previews of
 * the same report agree, which matters when the author is nudging a column
 * width and comparing.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Catalog\Dataset;
use FreeBooks\Forms\Document\{
    Barcode, Binding, Field, FieldBlock, Record, ReportColumn, Row, RowKind, Scope, Table,
    TableDocument, Template, TypedValue, ValueType
};

final class SampleData
{
    /** Rows per group when the report groups, or in total when it does not. */
    private const PER_GROUP = 4;
    private const GROUPS    = 3;
    private const FLAT      = 12;

    /**
     * The same document, with invented rows.
     *
     * @param ?string $groupBy the field key the report breaks on, so the sample
     *   shows what a group break looks like rather than a flat list — grouping
     *   is most of what an author is judging, and it is invisible without one.
     */
    public static function fill(
        TableDocument $document,
        ?Dataset      $dataset,
        string        $note,
        ?string       $groupBy = null,
    ): TableDocument {
        if (!$document->columns) return $document;

        $types = self::typesFor($document->columns, $dataset);
        $rows  = [];

        if ($groupBy !== null && $groupBy !== '') {
            for ($group = 0; $group < self::GROUPS; $group++) {
                for ($line = 0; $line < self::PER_GROUP; $line++) {
                    $rows[] = new Row(RowKind::Data,
                        self::cells($document->columns, $types, $group * self::PER_GROUP + $line, $group));
                }
                $rows[] = new Row(RowKind::GroupTotal,
                    self::totals($document->columns, $types, self::PER_GROUP, $group),
                    self::groupLabel($group));
            }
        } else {
            for ($line = 0; $line < self::FLAT; $line++) {
                $rows[] = new Row(RowKind::Data, self::cells($document->columns, $types, $line, null));
            }
        }

        // A report total only if some column asks to be totalled — inventing one
        // where the report has none would preview a band that will not print.
        if (self::hasTotals($document->columns)) {
            $count = $groupBy !== null && $groupBy !== '' ? self::GROUPS * self::PER_GROUP : self::FLAT;
            $rows[] = new Row(RowKind::ReportTotal,
                self::totals($document->columns, $types, $count, null), $document->title);
        }

        return new TableDocument(
            title:       $document->title,
            columns:     $document->columns,
            rows:        $rows,
            context:     $document->context,
            page:        $document->page,
            // Appended rather than replacing: what the report was filtered by is
            // still worth reading, and is usually why it came back empty.
            filterText:  trim($note . ($document->filterText !== null && $document->filterText !== ''
                             ? ' — ' . $document->filterText : '')),
            afterRender: [],   // never fire a hook for data that does not exist
            style:       $document->style,
        );
    }

    /**
     * One invented record, for a form whose own table has nothing to print.
     *
     * The canvas is only worth looking at if there is TEXT under the element
     * outlines: a form rendered against an empty record draws its static labels
     * and its rules and nothing else, and an author cannot judge whether a box
     * sits over its value when there is no value. §13.2 requires this — a fresh
     * install has no invoices and must still be able to lay an invoice out.
     *
     * Every binding the template names is filled, including the line-item
     * table, so what the author checks is the geometry of a full page rather
     * than of a page with the middle missing.
     *
     * @param int $lines rows for each of the template's tables
     */
    public static function record(Template $template, ?Dataset $dataset, int $lines = 6): Record
    {
        $values = [];
        $tables = [];

        foreach ($template->elements as $element) {
            if ($element instanceof Field) {
                // A Context binding is a real company constant and is resolved
                // for real; inventing one would put "Sample COMPANY_NAME" where
                // the company's own name belongs.
                if ($element->bind->scope === Scope::Context) continue;
                $values[$element->bind->name] = self::forBinding($element->bind, $dataset, 0);
                foreach ($element->bind->aggregateFields() as $summed) {
                    $values[$summed] ??= self::forBinding(Binding::record($summed), $dataset, 0);
                }
                continue;
            }
            if ($element instanceof FieldBlock) {
                foreach ($element->parts as $index => $part) {
                    if ($part->bind->scope === Scope::Context) continue;
                    $values[$part->bind->name] = self::forBinding($part->bind, $dataset, $index);
                }
                continue;
            }
            if ($element instanceof Barcode) {
                $values[$element->bind->name] = TypedValue::text('SAMPLE-0001');
                continue;
            }
            if ($element instanceof Table) {
                $rows = [];
                for ($line = 0; $line < $lines; $line++) {
                    $row = [];
                    foreach ($element->columns as $column) {
                        $row[$column->bind->name] = self::forBinding($column->bind, $dataset, $line);
                    }
                    $rows[] = $row;
                }
                $tables[$element->source] = $rows;
            }
        }

        return new Record($values, $tables, null, 'sample');
    }

    /** A value of the right TYPE for one binding, named so it can be recognised. */
    private static function forBinding(Binding $binding, ?Dataset $dataset, int $line): TypedValue
    {
        $field = $dataset?->field($binding->name);
        $type  = $field?->type ?? self::guessType($binding->name);
        $label = $field !== null && trim($field->label) !== '' ? trim($field->label) : $binding->name;

        return match ($type) {
            ValueType::Money, ValueType::Decimal => new TypedValue(self::money($line, null), $type),
            ValueType::Integer => TypedValue::int($line + 1),
            ValueType::Boolean => TypedValue::bool($line % 2 === 0),
            ValueType::Date    => TypedValue::date(date('Y-m-d', strtotime('-' . ($line * 3) . ' days'))),
            default            => TypedValue::text($label . ' ' . ($line + 1)),
        };
    }

    /**
     * What a field is, from its NAME, when no dataset declares it.
     *
     * Every form served by a provider is in that position: its values come from
     * the provider's own contract, not from a dataset the canvas can consult.
     * A guess is right often enough to be useful and is never load-bearing —
     * this data is invented either way, and the page says so.
     */
    private static function guessType(string $name): ValueType
    {
        $name = strtolower($name);
        return match (true) {
            str_contains($name, 'date')                              => ValueType::Date,
            str_contains($name, 'qty'), str_contains($name, 'count') => ValueType::Integer,
            str_contains($name, 'amount'), str_contains($name, 'total'),
            str_contains($name, 'price'),  str_contains($name, 'cost'),
            str_contains($name, 'debit'),  str_contains($name, 'credit') => ValueType::Money,
            default                                                  => ValueType::Text,
        };
    }

    /**
     * What each column holds, so a money column reads as money.
     *
     * The dataset knows; where it does not — a resolved column has no field of
     * its own — the format hint is the next best evidence, and Text is a safe
     * last resort because every type renders as text somewhere.
     *
     * @param list<ReportColumn> $columns
     * @return list<ValueType>
     */
    private static function typesFor(array $columns, ?Dataset $dataset): array
    {
        $types = [];
        foreach ($columns as $column) {
            $field = $dataset?->field($column->key);
            if ($field !== null) { $types[] = $field->type; continue; }

            $hint = strtolower($column->format?->name ?? '');
            $types[] = match (true) {
                str_contains($hint, 'cur')  => ValueType::Money,
                str_contains($hint, 'date') => ValueType::Date,
                default                     => ValueType::Text,
            };
        }
        return $types;
    }

    /** @param list<ReportColumn> $columns */
    private static function hasTotals(array $columns): bool
    {
        foreach ($columns as $column) if ($column->total) return true;
        return false;
    }

    /**
     * One row of invented cells.
     *
     * @param list<ReportColumn> $columns
     * @param list<ValueType>    $types
     * @return array<string,TypedValue>
     */
    private static function cells(array $columns, array $types, int $line, ?int $group): array
    {
        $cells = [];
        foreach ($columns as $index => $column) {
            $cells[$column->key] = self::value($types[$index], $column, $line, $group);
        }
        return $cells;
    }

    /**
     * A total row: the summed columns carry a figure, the rest are blank.
     *
     * Blank rather than repeated, because a total row that restates the date and
     * the description of its last line is not what a report prints.
     *
     * @param list<ReportColumn> $columns
     * @param list<ValueType>    $types
     * @return array<string,TypedValue>
     */
    private static function totals(array $columns, array $types, int $count, ?int $group): array
    {
        $cells = [];
        foreach ($columns as $index => $column) {
            if (!$column->total) { $cells[$column->key] = TypedValue::text(''); continue; }
            $sum = 0.0;
            for ($line = 0; $line < $count; $line++) $sum += self::money($line, $group);
            $cells[$column->key] = new TypedValue($sum, $types[$index]);
        }
        return $cells;
    }

    private static function money(int $line, ?int $group): float
    {
        // Varied enough to fill a column and see it align, stable across runs.
        return round(125.5 + ($line * 87.25) + (($group ?? 0) * 40), 2);
    }

    private static function groupLabel(int $group): string
    {
        return 'Sample group ' . ($group + 1);
    }

    private static function value(ValueType $type, ReportColumn $column, int $line, ?int $group): TypedValue
    {
        return match ($type) {
            ValueType::Money, ValueType::Decimal => new TypedValue(self::money($line, $group), $type),
            ValueType::Integer => TypedValue::int($line + 1),
            ValueType::Boolean => TypedValue::bool($line % 2 === 0),
            // Counting BACK from today, so the newest sample row is today and the
            // dates look like a report sorted newest first.
            ValueType::Date    => TypedValue::date(date('Y-m-d', strtotime('-' . ($line * 3) . ' days'))),
            default            => TypedValue::text(self::text($column, $line, $group)),
        };
    }

    /**
     * Text that names the column it is in.
     *
     * "Sample Customer 3" under a Customer heading tells the author which column
     * they are looking at while they resize it; a row of "lorem ipsum" does not.
     */
    private static function text(ReportColumn $column, int $line, ?int $group): string
    {
        $label = trim($column->heading) !== '' ? trim($column->heading) : $column->key;
        if ($group !== null) return $label . ' ' . ($group + 1) . '.' . ($line % self::PER_GROUP + 1);
        return $label . ' ' . ($line + 1);
    }
}
