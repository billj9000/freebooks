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
//  Path: /modules/forms/classes/definition/serial_form.php
//
/**
 * Forms — the receipt editor's binding, between a SerialTemplate and a form post.
 *
 * TemplateForm does this for the canvas; this does it for the receipt, and is
 * far smaller because a receipt's geometry is three values rather than a box, a
 * style and a visibility rule. See §13.27.
 *
 * The ORDER of the submitted rows is the order of the fields, which is what
 * makes a receipt editable as a list: moving a row moves the field, and
 * dropping a row deletes it. That is not a shortcut — sequence is genuinely
 * where a receipt's layout lives, so the form's row order IS the layout.
 *
 * What it will not do is invent a binding. A row naming an index that no longer
 * exists is refused rather than guessed at, exactly as TemplateForm refuses one
 * — the failure it prevents is a stale browser tab silently rewriting a field
 * into a different kind of field.
 */

namespace FreeBooks\Forms\Definition;

use FreeBooks\Forms\Document\{
    Align, Binding, Field, Format, SerialCell, SerialField, SerialTemplate, StaticText, Table
};

final class SerialForm
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * The edited definition.
     *
     * @param array<string,mixed> $request  $_POST
     * @param array<string,true>  $allowed  binding names this receipt can supply
     * @throws DefinitionException when this is not a receipt at all
     */
    public function apply(Definition $base, array $request, array $allowed): Definition
    {
        $this->warnings = [];

        if (!$base->isSerial() || $base->serialTemplate === null) {
            throw new DefinitionException('This is not a receipt printer form.');
        }

        $original = $base->serialTemplate->fields;
        $fields   = [];

        foreach (array_values((array)($request['field'] ?? [])) as $row) {
            $row   = (array)$row;
            $index = ($row['index'] ?? '') === '' ? null : (int)$row['index'];

            if ($index !== null && !isset($original[$index])) {
                $this->warnings[] = 'A field that is no longer part of this receipt was'
                    . ' submitted, and was ignored. Reopen the receipt before editing it again.';
                continue;
            }

            $field = $index === null
                ? $this->create($row, $allowed)
                : $this->rebuild($original[$index], $row);

            if ($field !== null) $fields[] = $field;
        }

        if ($fields === []) {
            throw new DefinitionException('A receipt with no fields would print nothing.');
        }

        $width = (int)($request['paper'] ?? $base->serialTemplate->width);
        if ($width < 1) {
            $this->warnings[] = 'The paper width must be at least one character; it was left as it was.';
            $width = $base->serialTemplate->width;
        }

        return new Definition(
            id:          $base->id,
            type:        $base->type,
            title:       trim((string)($request['title'] ?? $base->title)) ?: $base->title,
            description: $base->description,
            group:       $base->group,
            filenamePrefix: $base->filenamePrefix,
            emailMessage:   $base->emailMessage,
            provider:    $base->provider,
            datasetId:   $base->datasetId,
            page:        $base->page,
            dateDefault: $base->dateDefault,
            dateField:   $base->dateField,
            period:      $base->period,
            filters:     $base->filters,
            sorts:       $base->sorts,
            columns:     $base->columns,
            groupBy:     $base->groupBy,
            groupFormat: $base->groupFormat,
            totalsOnly:  $base->totalsOnly,
            resolvedColumns: $base->resolvedColumns,
            style:       $base->style,
            template:    $base->template,
            breakField:  $base->breakField,
            recordTable: $base->recordTable,
            recordKey:   $base->recordKey,
            printedField: $base->printedField,
            serial:      true,
            serialTemplate: new SerialTemplate($fields, $width),
        );
    }

    /**
     * An existing field, with whatever the author changed.
     *
     * The ELEMENT is kept as it was — its binding, its format, its separators —
     * and only the cell is rebuilt, because those are the things the receipt
     * editor shows. The one exception is literal text, which is the element and
     * is the thing an author most often wants to change on a receipt.
     */
    private function rebuild(SerialField $field, array $row): ?SerialField
    {
        $element = $field->element;

        if ($element instanceof StaticText) {
            $text = (string)($row['text'] ?? $element->text);
            if ($text !== $element->text) {
                $element = new StaticText($text, $element->box, $element->style,
                                          $element->visible, $element->label);
            }
        }

        // A bound value's FORMAT is editable here and nowhere else in Forms.
        // It has to be: a receipt prints a total in a fixed number of character
        // cells, so an unformatted 12.5 where 12.50 belongs is not a cosmetic
        // difference but a column that no longer lines up. An ABSENT key means
        // the post says nothing about the format and it is carried; an empty
        // one means the author chose "no format", which is a real answer.
        if ($element instanceof Field && array_key_exists('format', $row)) {
            $element = new Field($element->bind, $element->box,
                                 $this->format((string)$row['format'], $element->format),
                                 $element->style, $element->visible, $element->label);
        }

        return new SerialField($element, $this->cell($row, $field->cell, $element instanceof Table));
    }

    /**
     * A format hint, keeping any options the name already carried.
     *
     * Options are not editable and nothing writes them, but a format that had
     * some keeps them when its name is unchanged - dropping them on an edit of
     * a different column would be a save quietly destroying something invisible.
     * The canvas carries them the same way, for the same reason.
     */
    private function format(string $name, ?Format $current): ?Format
    {
        $name = trim($name);
        if ($name === '') return null;
        if ($current !== null && $current->name === $name) return $current;
        return new Format($name);
    }

    /**
     * A new field: either wording typed here, or a value the receipt can supply.
     *
     * The binding is checked against what this receipt CAN produce, never taken
     * on the word of the post. An unknown name would be accepted by the codec,
     * stored, and print nothing - a field that exists, occupies its cells, and
     * is blank on every receipt from then on. Refused and said, the way the
     * canvas refuses one.
     *
     * A bound field is created with no box. A receipt has no coordinates at all
     * (§13.27), so Box(0,0) is not a position - it is the absence of one, and
     * it is what every other field on a receipt already stores.
     */
    private function create(array $row, array $allowed): ?SerialField
    {
        $bind = trim((string)($row['bind'] ?? ''));
        if ($bind !== '') {
            if (!isset($allowed[$bind])) {
                $this->warnings[] = "A new field naming '$bind', which this receipt cannot supply,"
                                  . ' was left off it.';
                return null;
            }
            return new SerialField(
                new Field(new Binding($bind), new \FreeBooks\Forms\Document\Box(0, 0),
                          $this->format((string)($row['format'] ?? ''), null)),
                $this->cell($row, new SerialCell()),
            );
        }

        $text = trim((string)($row['text'] ?? ''));
        if ($text === '') {
            $this->warnings[] = 'A new field with no text was left off the receipt.';
            return null;
        }
        return new SerialField(
            new StaticText($text, new \FreeBooks\Forms\Document\Box(0, 0)),
            $this->cell($row, new SerialCell()),
        );
    }

    /**
     * The cell an author asked for, within what a printer can do.
     *
     * A table always breaks the row whatever the form said — it prints whole
     * lines of its own, and a table that did not break would run the next
     * field onto the end of its last row. The renderer used to force this at
     * print time; refusing it here means the stored definition says what
     * happens.
     */
    private function cell(array $row, SerialCell $current, bool $isTable = false): SerialCell
    {
        $width = (int)($row['width'] ?? $current->width);
        if ($width < 1) {
            $this->warnings[] = 'A cell width below one character was left as it was.';
            $width = $current->width;
        }

        $align = Align::tryFrom(strtoupper((string)($row['align'] ?? ''))) ?? $current->align;

        return new SerialCell(
            width:     $width,
            align:     $align,
            rowBreak:  $isTable ? true : !empty($row['break']),
            wholeLine: $current->wholeLine,
        );
    }

    /**
     * The template as this editor's own field values — the inverse of apply().
     *
     * verify_forms_serial.php holds these two to the property that matters:
     * applying this must return the receipt unchanged, so that opening one and
     * saving it without touching anything rewrites nothing.
     *
     * @return list<array<string,mixed>>
     */
    public static function toRequest(SerialTemplate $template): array
    {
        $rows = [];
        foreach ($template->fields as $index => $field) {
            $rows[] = [
                'index' => (string)$index,
                'width' => (string)$field->cell->width,
                'align' => $field->cell->align->value,
                'break' => $field->cell->rowBreak ? '1' : '',
                'text'  => $field->element instanceof StaticText ? $field->element->text : '',
                // Round-tripped, not decorative: the page posts every row back,
                // so a format missing from here would be an absent key on save
                // for a field whose format the author never touched. rebuild()
                // carries an absent key, but the SELECT would have posted an
                // empty one, and the field would come back unformatted.
                'format' => $field->element instanceof Field
                    ? ($field->element->format?->name ?? '') : '',
            ];
        }
        return $rows;
    }

    /** @return list<string> */
    public function warnings(): array { return $this->warnings; }
}
