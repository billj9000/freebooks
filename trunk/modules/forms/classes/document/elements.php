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
//  Path: /modules/forms/classes/document/elements.php
//
/**
 * Forms — the positioned element vocabulary for LayoutDocument.
 * See docs/forms-design.md §4.2 and §4.3.
 *
 * Nine element types, collapsed from PhreeForm's fourteen. Four of its pairs
 * were the same shape differing only in where the data came from, which is now
 * a Binding scope rather than a distinct element:
 *
 *   Text                -> StaticText
 *   Data + CDta         -> Field       (scope: Record | Context)
 *   TBlk + CBlk         -> FieldBlock  (scope: Record | Context)
 *   Tbl                 -> Table
 *   TDup                -> (eliminated: a second Table on the same source)
 *   Ttl                 -> Field with an Aggregate binding
 *   Img + ImgLink       -> Image       (literal filename or a Binding)
 *   Line/Rect/PgNum/BarCode -> Line/Rect/PageNumber/Barcode
 *
 * Elements are TEMPLATE only. They carry no data. PhreeForm wrote resolved
 * values back into its definition ($report->fieldlist[$key]->text = ...), which
 * fused template and data into one mutated object; here data lives in Record.
 */

namespace FreeBooks\Forms\Document;

/** Base for every positioned element. */
abstract readonly class Element
{
    public function __construct(
        public Box        $box,
        public ?Style     $style   = null,
        public Visibility $visible = Visibility::AllPages,
        public ?string    $label   = null,   // designer-facing description only
    ) {}
}

/** Literal text that never varies. */
final readonly class StaticText extends Element
{
    public function __construct(
        public string $text,
        Box $box,
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/** A single bound value. Replaces PhreeForm's Data, CDta and Ttl. */
final readonly class Field extends Element
{
    public function __construct(
        public Binding $bind,
        Box $box,
        public ?Format $format = null,
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/**
 * One part of a FieldBlock: a bound value plus the separator that joins it to
 * the next part. PhreeForm's AddSep() vocabulary — 'nl', 'del-nl' (drop the
 * line entirely when empty), 'com-sp', '2sp', 'semi-sp'.
 */
final readonly class BlockPart
{
    public function __construct(
        public Binding $bind,
        public string  $separator = '',
        public ?Format $format    = null,
    ) {}
}

/**
 * Several bound values concatenated into one box — an address block, a company
 * block. Replaces PhreeForm's TBlk and CBlk.
 */
final readonly class FieldBlock extends Element
{
    /** @param list<BlockPart> $parts */
    public function __construct(
        public array $parts,
        Box $box,
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/**
 * One column of a Table.
 *
 * Carries its own optional Style because PhreeForm styles table columns
 * individually — each boxfield holds its own font, size and colour, and real
 * definitions do vary alignment per column. A column with no style inherits the
 * table's.
 */
final readonly class Column
{
    public function __construct(
        public Binding $bind,
        public string  $heading,
        public ?float  $width  = null,
        public ?Align  $align  = null,
        public ?Format $format = null,
        public bool    $total  = false,   // contributes to an Aggregate binding
        public ?Style  $style  = null,
    ) {}
}

/**
 * A repeating table of line items drawn from one of the record's named tables.
 *
 * The renderer owns pagination: overflow, page breaks and heading repetition
 * are its business, not the document's. This is what keeps the model free of
 * TCPDF's pagination assumptions and lets a future renderer differ.
 */
final readonly class Table extends Element
{
    /** @param list<Column> $columns */
    public function __construct(
        public string $source,          // key into Record::$tables
        public array  $columns,
        Box $box,
        public ?Style $headerStyle = null,
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/** A drawn rule. */
final readonly class Line extends Element
{
    public function __construct(
        Box $box,
        public string $lineType = 'solid',
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/** A drawn rectangle. */
final readonly class Rect extends Element
{
    public function __construct(
        Box $box,
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/**
 * An image. `src` is either a literal filename (PhreeForm's Img) or a Binding
 * resolving to one (its ImgLink) — one element type, two sources.
 *
 * The two are looked for in DIFFERENT places, which is why `pathPrefix` exists.
 * A literal filename is a form's own furniture and lives with the report
 * images. A bound one is a path to business data — the Work Order shows the
 * product photo of the SKU being built — and the legacy stored the folder it
 * lives in on the element, as `inventory/images/`, with the record supplying
 * only the filename. Dropping that prefix makes the renderer look for a product
 * photo among the logos, find nothing, and draw nothing: the element is
 * present, the binding resolves, and the picture is simply absent.
 */
final readonly class Image extends Element
{
    public function __construct(
        public string|Binding $src,
        Box $box,
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
        /** Folder, relative to the company's files, for a BOUND src only. */
        public string $pathPrefix = '',
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/**
 * Page numbering. The pattern is filled by the renderer, which is the only
 * layer that knows the page count.
 */
final readonly class PageNumber extends Element
{
    public function __construct(
        Box $box,
        public string $pattern = '{page} / {pages}',
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}

/** A barcode rendered from a bound value. */
final readonly class Barcode extends Element
{
    public function __construct(
        public Binding $bind,
        Box $box,
        public string $symbology = 'C128',
        ?Style $style = null,
        Visibility $visible = Visibility::AllPages,
        ?string $label = null,
    ) {
        parent::__construct($box, $style, $visible, $label);
    }
}
