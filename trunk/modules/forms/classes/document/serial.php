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
//  Path: /modules/forms/classes/document/serial.php
//
/**
 * Forms — a receipt: a document with no page and no coordinates. See §13.27.
 *
 * A serial form is what PhreeForm's `serialform` flag marks and BuildSeq()
 * prints: character cells for a till printer, not a page. Its geometry is not
 * "coordinates Forms reads badly" — there are none: a receipt's `abscissa` and
 * `ordinate` are empty, and BuildSeq() never reads them. Three other things
 * place a value instead:
 *
 *   - SEQUENCE. Fields are laid down in order.
 *   - WIDTH and ALIGN. Each field is padded into a cell that many characters
 *     wide, left, right or centred.
 *   - ROW BREAK. A field carrying one ends the line and starts the next.
 *
 * The consequence that makes this a separate model rather than a Template with
 * odd numbers in it: successive fields OVERLAY the same line rather than
 * sitting beside each other. "Subtotal" left-aligned and its amount
 * right-aligned are two fields written into the SAME 40 cells, the second
 * printing over the spaces of the first. There is no arrangement of boxes that
 * says that, which is why a canvas cannot edit one and this exists.
 *
 * What it deliberately does NOT duplicate is the element model. A receipt's
 * four field types are exactly Forms' StaticText, Field, FieldBlock and Table,
 * with the same bindings and the same formats, so SerialField COMPOSES an
 * Element with the cell geometry rather than restating it. Every converter,
 * binding rule and format that already works for a page works here untouched.
 */

namespace FreeBooks\Forms\Document;

/**
 * Where one field lands on the line.
 *
 * @param int  $width    the cell, in CHARACTERS — not millimetres, and not a
 *   proportion. A till printer has a fixed column count and the definition
 *   states the cell against it.
 * @param bool $rowBreak this field ends the line. Always true for a Table,
 *   which emits whole lines of its own; the legacy renderer forced that at
 *   print time, and it is recorded here instead so the model says what happens.
 */
final readonly class SerialCell
{
    public function __construct(
        public int   $width    = 40,
        public Align $align    = Align::Left,
        public bool  $rowBreak = false,
        /**
         * This field writes the whole line rather than overlaying the one being
         * built. PhreeForm's TBlk assigned its text straight to $oneline where
         * every other kind overlaid, and the difference shows: an overlay onto
         * an empty line pads the value into its cell, an assignment leaves it
         * exactly as long as it is. Both shapes are in definitions in use.
         */
        public bool  $wholeLine = false,
    ) {}
}

/** One element, together with the cell it is written into. */
final readonly class SerialField
{
    public function __construct(
        public Element    $element,
        public SerialCell $cell = new SerialCell(),
    ) {}
}

/**
 * A receipt's layout: fields in order, and how wide the paper is.
 *
 * $width is the printer's column count, and is the width a NEW field defaults
 * to. It is not a limit — the stored definitions set a width per field, and a
 * field wider than the paper is a fault the editor should show rather than one
 * the model should forbid, because it is already true of definitions in use.
 */
final readonly class SerialTemplate
{
    /** @param list<SerialField> $fields */
    public function __construct(
        public array $fields = [],
        public int   $width  = 40,
    ) {}

    /**
     * The elements alone, in order.
     *
     * For the callers that care what a field IS and not where it lands — the
     * company-block collector, chiefly, which walks a page template's elements
     * and needs to walk these the same way.
     *
     * @return list<Element>
     */
    public function elements(): array
    {
        return array_map(static fn (SerialField $f): Element => $f->element, $this->fields);
    }

    /**
     * The fields grouped into the lines they print as.
     *
     * The editor shows a receipt as rows of cells, which is how someone reads
     * one; the stored form is the flat list, which is how it prints. This is
     * the one conversion between them, and it is derived rather than stored so
     * the two can never disagree.
     *
     * A trailing group with no row break is still a line: BuildSeq() leaves it
     * in $oneline and it reaches the output only if something later breaks.
     * Showing it as a line is what lets an author SEE that it never prints.
     *
     * @return list<list<SerialField>>
     */
    public function lines(): array
    {
        $lines = [];
        $line  = [];
        foreach ($this->fields as $field) {
            $line[] = $field;
            if ($field->cell->rowBreak) { $lines[] = $line; $line = []; }
        }
        if ($line !== []) $lines[] = $line;
        return $lines;
    }
}

/**
 * A receipt, rendered once per record.
 *
 * Page-agnostic in the same way LayoutDocument is, and for a stronger reason:
 * there is no page at all. pageSetup() answers with whatever the definition
 * carried so a caller that asks is not surprised by a null, but nothing in the
 * serial renderer reads it.
 */
final readonly class SerialDocument implements Document
{
    /**
     * @param list<Record>         $records
     * @param list<AfterRenderHook> $hooks
     */
    public function __construct(
        public string         $title,
        public SerialTemplate $template,
        public array          $records = [],
        public Context        $context = new Context(),
        public PageSetup      $page    = new PageSetup(),
        public array          $hooks   = [],
    ) {}

    public function title(): string { return $this->title; }
    public function pageSetup(): PageSetup { return $this->page; }
    /** @return list<AfterRenderHook> */
    public function afterRender(): array { return $this->hooks; }
}
