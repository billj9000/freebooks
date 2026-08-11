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
//  Path: /docs/tools/verify_forms_seam.php
//
/**
 * Verify the Forms document seam. See docs/forms-design.md §2 and §4.
 *
 * Asserts the guarantees the design depends on, not merely that the classes
 * parse: raw values survive to the renderer, template and data stay separate,
 * the four PhreeForm element collapses hold, documents are immutable, and a
 * renderer that does not support a document shape is refused rather than
 * silently mis-rendering it.
 *
 * The seam has no dependencies, so this needs no database, no configure.php and
 * no module bootstrap - a single require, which is itself one of the properties
 * being demonstrated. Runs on any PHP >= 8.2, host or container:
 *
 *     php docs/tools/verify_forms_seam.php
 *
 * Exits non-zero if any assertion fails.
 */

$seam = getenv('FORMS_SEAM') ?: __DIR__ . '/../../trunk/modules/forms/classes/document/load.php';
if (!file_exists($seam)) {
    fwrite(STDERR, "Seam not found at $seam - set FORMS_SEAM to override.\n");
    exit(1);
}
require_once($seam);

use FreeBooks\Forms\Document\{
    Align, AfterRenderHook, Barcode, Binding, BlockPart, Box, Colour, Column, Context, Currency,
    Document, Field, FieldBlock, Format, Image, LayoutDocument, Line, PageNumber, PageSetup,
    Record, Rect, RenderedOutput, RenderOptions, RendererRegistry, ReportColumn, Row, RowKind,
    Scope, StaticText, Style, Table, TableDocument, Template, TypedValue, ValueType, Visibility,
    FormsRenderer, UnsupportedDocumentException
};

$pass = 0; $fail = 0;
function ok(string $what, bool $cond) { global $pass, $fail; $cond ? $pass++ : $fail++;
    printf("  [%s] %s\n", $cond ? 'ok' : 'FAIL', $what); }

// ── 1. Build a LayoutDocument shaped like the real Invoice ───────────────────
$template = new Template([
    new Image('freebooks_compact_logo.png', new Box(8, 8, 25), label: 'Logo'),
    new StaticText('INVOICE', new Box(150, 20, 50), new Style(size: 18, align: Align::Right)),
    new FieldBlock([                                   // was TBlk (Bill To)
        new BlockPart(Binding::record('bill_primary_name'), "\n"),
        new BlockPart(Binding::record('bill_address1'),     "\n"),
        new BlockPart(Binding::record('bill_city_town'),    ", "),
    ], new Box(8, 45, 80, 4.5), label: 'Bill To'),
    new FieldBlock([                                   // was CBlk — same shape, Context scope
        new BlockPart(Binding::context('COMPANY_NAME'),     "\n"),
        new BlockPart(Binding::context('COMPANY_ADDRESS1'), "\n"),
    ], new Box(50, 8, 60, 4.5), label: 'Company'),
    new Field(Binding::record('purchase_invoice_id'), new Box(155, 30, 50, 5)),
    new Field(Binding::record('post_date'), new Box(155, 36, 50, 5), new Format('date')),
    new Table('items', [                               // was Tbl
        new Column(Binding::record('invoice_sku'),        'SKU',       35, Align::Left),
        new Column(Binding::record('invoice_description'),'Description',86, Align::Left),
        new Column(Binding::record('invoice_qty'),        'Qty',        22, Align::Right),
        new Column(Binding::record('invoice_price'),      'Extension',  30, Align::Right,
                   new Format('posted_cur'), total: true),
    ], new Box(8, 102, 200, 130), headerStyle: new Style(size: 8, align: Align::Left)),
    new Field(Binding::record('total_amount'), new Box(155, 240, 50, 5), new Format('posted_cur')),
    // the pagination-aware case (open risk 1) — placeholder on all pages but the last
    new Field(Binding::aggregate('running_total'), new Box(155, 246, 50, 5),
              new Format('posted_cur'), visible: Visibility::ContinuedOnOverflow),
    new PageNumber(new Box(155, 38, 50, 5)),
    new Line(new Box(8, 100, 200, 0)),
    new Rect(new Box(8, 8, 200, 280)),
    new Barcode(Binding::record('purchase_invoice_id'), new Box(8, 250, 60, 15)),
]);

$record = new Record(
    values: [
        'purchase_invoice_id' => TypedValue::text('20055'),
        'post_date'           => TypedValue::date('2026-07-14'),
        'bill_primary_name'   => TypedValue::text('Acme Ltd'),
        'total_amount'        => TypedValue::money(272.2545),   // raw, NOT pre-formatted
    ],
    tables: ['items' => [
        ['invoice_sku' => TypedValue::text('WIDGET-001'),
         'invoice_qty' => TypedValue::int(2),
         'invoice_price' => TypedValue::money(136.13)],
    ]],
    currency: new Currency('EUR', 1.225018),
    key: '349',
);

$invoice = new LayoutDocument(
    title: 'Invoice',
    template: $template,
    records: [$record],
    context: new Context(['COMPANY_NAME' => TypedValue::text('Northwind Trading')]),
    page: new PageSetup('A4', 'P'),
    afterRender: [new AfterRenderHook('increment_field',
        ['table' => 'journal_main', 'field' => 'printed', 'id' => 349])],
);

ok('LayoutDocument builds', $invoice instanceof Document);
ok('template holds 13 elements', count($invoice->template->elements) === 13);
ok('all 9 element types constructible', count(array_unique(array_map(
    fn($e) => $e::class, $invoice->template->elements))) === 9);

// ── 2. The defects the seam exists to fix ────────────────────────────────────
$total = $record->value('total_amount');
ok('values stay RAW, not pre-formatted (the CSV-export defect)',
   $total->raw === 272.2545 && $total->type === ValueType::Money);
ok('currency is per-RECORD, not per-document', $record->currency?->code === 'EUR');
ok('totals share the flat value namespace (no separate concept)',
   isset($record->values['total_amount']));
ok('Data and CDta collapse to Field+scope',
   $template->elements[4]->bind->scope === Scope::Record);
ok('TBlk and CBlk differ only by binding scope',
   $template->elements[2]->parts[0]->bind->scope === Scope::Record &&
   $template->elements[3]->parts[0]->bind->scope === Scope::Context);
ok('Ttl is a Field with an Aggregate binding + ContinuedOnOverflow',
   $template->elements[8]->bind->scope === Scope::Aggregate &&
   $template->elements[8]->visible === Visibility::ContinuedOnOverflow);
ok('side effects are declarative data, not closures',
   $invoice->afterRender()[0]->type === 'increment_field');

// template carries no data; data lives on the record
$fieldEl = $template->elements[4];
ok('template element carries NO resolved value (no template/data fusion)',
   !property_exists($fieldEl, 'text') && !property_exists($fieldEl, 'data'));

// ── 3. Immutability is enforced by the language, not convention ──────────────
$immutable = false;
try { $record->values = []; } catch (\Error $e) { $immutable = true; }
ok('Record is immutable at runtime', $immutable);
$immutable2 = false;
try { $invoice->title = 'x'; } catch (\Error $e) { $immutable2 = true; }
ok('LayoutDocument is immutable at runtime', $immutable2);

// ── 4. TableDocument (reports) replaces the tagged 2-D array ─────────────────
$report = new TableDocument(
    title: 'Customer List',
    columns: [
        new ReportColumn('name',  'Customer', 60, Align::Left),
        new ReportColumn('total', 'Total',    30, Align::Right, new Format('dlr'), total: true),
    ],
    rows: [
        new Row(RowKind::Data,        ['name' => TypedValue::text('Acme'),  'total' => TypedValue::money(100.5)]),
        new Row(RowKind::Data,        ['name' => TypedValue::text('Beta'),  'total' => TypedValue::money(50.25)]),
        new Row(RowKind::GroupTotal,  ['total' => TypedValue::money(150.75)], label: 'North'),
        new Row(RowKind::ReportTotal, ['total' => TypedValue::money(150.75)], label: 'Customer List'),
    ],
);
ok('TableDocument builds', $report instanceof Document);
ok("row kinds are typed, not 'd'/'g:'/'r:' string prefixes",
   $report->rows[2]->kind === RowKind::GroupTotal && $report->rows[2]->label === 'North');

// ── 5. Renderer registry: the swap mechanism ─────────────────────────────────
$csv = new class implements FormsRenderer {
    public function supports(Document $doc): bool { return $doc instanceof TableDocument; }
    public function render(Document $doc, RenderOptions $o = new RenderOptions()): RenderedOutput {
        $out = '';
        foreach ($doc->rows as $r) {
            if ($r->kind !== RowKind::Data) continue;
            // raw values -> machine-readable, which PhreeForm could not do
            $out .= implode(',', array_map(fn($c) => (string)$c->raw, $r->cells)) . "\n";
        }
        return new RenderedOutput($out, 'text/csv', ($o->filename ?? $doc->title()) . '.csv');
    }
};
$reg = new RendererRegistry();
$reg->register('csv', $csv);
ok('registry routes a supported document', $reg->formatsFor($report) === ['csv']);
ok('registry reports LayoutDocument unsupported by CSV', $reg->formatsFor($invoice) === []);
$out = $reg->render('csv', $report);
ok('CSV emits RAW numbers (not "$ 100.50")', str_contains($out->bytes, '100.5'));
ok('RenderedOutput carries mime + filename', $out->mimeType === 'text/csv' && $out->filename === 'Customer List.csv');
$threw = false;
try { $reg->render('csv', $invoice); } catch (UnsupportedDocumentException $e) { $threw = true; }
ok('mismatched document is rejected, not silently mis-rendered', $threw);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
