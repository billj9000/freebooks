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
    Align, SerialCell, SerialField, SerialTemplate, StaticText, Table
};

final class SerialForm
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * The edited definition.
     *
     * @param array<string,mixed> $request  $_POST
     * @throws DefinitionException when this is not a receipt at all
     */
    public function apply(Definition $base, array $request): Definition
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
                ? $this->create($row)
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

        return new SerialField($element, $this->cell($row, $field->cell, $element instanceof Table));
    }

    /**
     * A new field.
     *
     * Literal text only. Everything else on a receipt is a BOUND value, and
     * choosing a binding needs the dataset machinery the canvas has and this
     * page does not — so the editor offers what it can honestly complete, and
     * a new bound field is added where bindings are already chosen.
     */
    private function create(array $row): ?SerialField
    {
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
            ];
        }
        return $rows;
    }

    /** @return list<string> */
    public function warnings(): array { return $this->warnings; }
}
