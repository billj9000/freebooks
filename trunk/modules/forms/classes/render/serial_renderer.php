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
//  Path: /modules/forms/classes/render/serial_renderer.php
//
/**
 * Forms — printing a receipt as character cells. See §13.27.
 *
 * The third renderer, beside the layout and table ones, and the smallest: it
 * writes text. A till printer is a fixed grid of columns, so "rendering" is
 * padding each value into its cell and overlaying it onto the line being built.
 *
 * Faithful to PhreeForm's BuildSeq()/formatReceipt() by intent, including where
 * those are WRONG. Two of their behaviours produce visibly broken output on the
 * shipped receipt — a line of double the paper width, and a stray line of
 * spaces (§13.27 records both). They are reproduced here rather than
 * corrected, because a definition prints the same before and after the engine
 * changes under it; an author who wants the fault gone fixes it in the receipt
 * editor, where they can SEE it, and the change is theirs and visible in the
 * definition. A renderer that silently improved on the old one would make the
 * switch to Forms a change of output nobody asked for and nobody could diff.
 *
 * Where it does NOT copy the legacy is data. BuildSeq() pulls each Data field's
 * value with array_shift($FieldValues) — positionally, so inserting a field in
 * the designer silently re-points every field after it. Values here resolve by
 * NAME through the same bindings the page renderers use.
 */

namespace FreeBooks\Forms\Render;

use FreeBooks\Forms\Document\{
    Align, Binding, Column, Document, Element, Field, FieldBlock, FormsRenderer,
    Record, RenderedOutput, RenderOptions, Scope, SerialDocument, SerialField,
    StaticText, Table, TypedValue, UnsupportedDocumentException
};

final class SerialRenderer implements FormsRenderer
{
    private ?SerialDocument $document = null;
    private ?Record         $record   = null;

    public function __construct(
        private readonly Formatter $formatter = new Formatter(),
        /**
         * What follows every receipt, including the last.
         *
         * Four blank lines, which is BuildSeq()'s `$output .= "\n\n\n\n"` and
         * its comment calls a page break. On a till printer it is the feed that
         * pushes the last printed line clear of the cutter, so it is a
         * TERMINATOR rather than a separator between receipts — dropping it
         * from the final one would cut the last line of every receipt in half.
         */
        private readonly string $recordFeed = "\n\n\n\n",
    ) {}

    public function supports(Document $doc): bool
    {
        return $doc instanceof SerialDocument;
    }

    public function render(Document $doc, RenderOptions $options = new RenderOptions()): RenderedOutput
    {
        if (!$doc instanceof SerialDocument) {
            throw new UnsupportedDocumentException('SerialRenderer renders a SerialDocument.');
        }
        $this->document = $doc;

        $text = '';
        foreach ($doc->records as $record) {
            $this->record = $record;
            $text        .= $this->renderRecord() . $this->recordFeed;
        }
        $this->record = null;
        return new RenderedOutput(
            bytes:    $text,
            mimeType: 'text/plain',
            // RenderOptions carries a NULL filename when nobody asked for one,
            // not an empty string — so a truthiness test, or the name is '.txt'.
            filename: ($options->filename ?: 'receipt') . '.txt',
        );
    }

    /** The text of one receipt, exactly as the printer receives it. */
    public function renderToString(SerialDocument $doc): string
    {
        return $this->render($doc)->bytes;
    }

    /**
     * One record.
     *
     * `$line` is BuildSeq()'s $oneline: the line being assembled, which each
     * field overlays rather than appends to. It reaches the output only when a
     * field carrying a row break flushes it — so a trailing group with no break
     * is silently dropped, here as there.
     */
    private function renderRecord(): string
    {
        $out  = '';
        $line = '';

        foreach ($this->document->template->fields as $field) {
            $line = $this->place($field, $line);
            if ($field->cell->rowBreak) {
                $out .= $line . "\n";
                $line = '';
            }
        }
        return $out;
    }

    /** Write one field into the line being built, and hand back the new line. */
    private function place(SerialField $field, string $line): string
    {
        $element = $field->element;
        $cell    = $field->cell;
        $applies = $this->appliesTo($element);

        if ($element instanceof Table) {
            return $applies ? $this->placeTable($element, $line) : $line;
        }

        $text = match (true) {
            $element instanceof StaticText => $element->text,
            $element instanceof Field      => $this->fieldText($element),
            $element instanceof FieldBlock => $this->blockText($element),
            // Every other element kind is page furniture — a rule, a rectangle,
            // an image. A till printer cannot draw one, and the legacy switch
            // had no case for them either: they contribute nothing and must not
            // disturb the line being built.
            default                        => null,
        };
        if ($text === null) return $line;

        // A suppressed field is an EMPTY CELL, not a missing one (§13.51). Its
        // width still has to be laid down, because cells are positioned by
        // accumulation rather than by coordinate — dropping one would slide
        // every field after it along the line, which on a till receipt is
        // every column of the row going crooked to hide one value.
        if (!$applies) $text = '';

        // PhreeForm's TBlk assigned the block straight to $oneline, where every
        // other kind overlaid it. The difference is visible: an overlay onto an
        // empty line pads to the cell width, an assignment does not.
        if ($cell->wholeLine) return $text;

        return self::overlay($text, $cell->width, $cell->align, $line);
    }

    /**
     * A table, which prints whole lines of its own.
     *
     * Reproduces BuildSeq()'s `case 'Tbl'` including two faults, both of which
     * show on the shipped receipt (§13.27):
     *
     *   - it APPENDS to the line under construction rather than flushing it
     *     first, so whatever was accumulated becomes a prefix of the first row
     *     — which is how a 40-column receipt prints an 80-character line;
     *   - it then chops two characters off the end with substr($x, 0, -2),
     *     which eats the final row's last character along with its newline.
     */
    private function placeTable(Table $element, string $line): string
    {
        foreach ($this->record?->table($element->source) ?? [] as $row) {
            $cells = '';
            foreach ($element->columns as $index => $column) {
                $cells .= self::overlay(
                    $this->columnText($column, $row),
                    (int)($column->width ?? 0),
                    $column->align ?? Align::Left,
                    '',
                );
            }
            $line .= $cells . "\n";
        }
        return substr($line, 0, -2);
    }

    /**
     * PhreeForm's formatReceipt(), reproduced.
     *
     * Two modes, chosen by whether there is anything on the line yet. With an
     * empty line the value is padded into a cell of its own. With a line
     * already under construction the value is written OVER it — that is the
     * whole mechanism by which a left-aligned label and a right-aligned amount
     * share one line, and why a receipt cannot be described as boxes.
     *
     * A value containing newlines is placed a line at a time, keeping the
     * breaks: one company-address block field prints as four lines.
     */
    private static function overlay(string $value, int $width, Align $align, string $line): string
    {
        $out   = '';
        $parts = explode("\n", $value);

        foreach ($parts as $index => $part) {
            if ($index > 0) $out .= "\n";
            $length = strlen($part);
            $over   = strlen($line) > 0;

            $out .= match ($align) {
                Align::Left   => $over
                    ? $part . substr($line, $width - $length)
                    : str_pad($part, $width, ' ', STR_PAD_RIGHT),
                Align::Right  => $over
                    ? substr($line, 0, $width - $length) . $part
                    : str_pad($part, $width, ' ', STR_PAD_LEFT),
                Align::Center => $over
                    ? self::centreOver($part, $width, $line)
                    : self::centreIn($part, $width),
            };
        }
        return $out;
    }

    private static function centreOver(string $value, int $width, string $line): string
    {
        $pad = ($width - strlen($value)) / 2;
        return substr($line, 0, (int)floor($pad)) . $value . substr($line, -(int)ceil($pad));
    }

    private static function centreIn(string $value, int $width): string
    {
        $blanks = (($width - strlen($value)) / 2) + strlen($value);
        return str_pad(str_pad($value, (int)$blanks, ' ', STR_PAD_LEFT), $width, ' ', STR_PAD_RIGHT);
    }

    private function fieldText(Field $element): string
    {
        $value = $this->resolve($element->bind);
        return $value === null ? '' : $this->formatter->format($value, $element->format, $this->record?->currency);
    }

    private function blockText(FieldBlock $element): string
    {
        $out = '';
        foreach ($element->parts as $part) {
            $value = $this->resolve($part->bind);
            $text  = $value === null ? '' : $this->formatter->format($value, $part->format, $this->record?->currency);
            $out  .= $this->formatter->separator($text, $part->separator);
        }
        return $out;
    }

    /** @param array<string,TypedValue> $row */
    private function columnText(Column $column, array $row): string
    {
        $value = $row[$column->bind->name] ?? null;
        return $value === null ? '' : $this->formatter->format($value, $column->format, $this->record?->currency);
    }

    /**
     * Whether this field prints for the record being drawn (§13.51).
     *
     * A receipt has no coordinates and no pages, so there is nothing here of
     * the page renderer's two-pass machinery — but the model is shared, a
     * SerialField wrapping the same Element, and a condition that held on one
     * renderer and not the other would be a definition that means two things.
     */
    private function appliesTo(Element $element): bool
    {
        $when = $element->when;
        return $when === null || $when->satisfiedBy($this->resolve($when->bind));
    }

    private function resolve(Binding $binding): ?TypedValue
    {
        return match ($binding->scope) {
            Scope::Record    => $this->record?->value($binding->name),
            Scope::Context   => $this->document?->context->get($binding->name),
            // A receipt's totals come from the provider as named values, so an
            // aggregate resolves like any other. Summing rows is the page
            // renderer's business and no shipped receipt asks for it.
            Scope::Aggregate => $this->record?->value($binding->name),
        };
    }
}
