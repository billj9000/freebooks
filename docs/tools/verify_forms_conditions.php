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
//  Path: /docs/tools/verify_forms_conditions.php
//
/**
 * Forms — an element that prints for some records and not others. See §13.51.
 *
 * This is the stage-0 proof: the model, the format and both renderers, with no
 * editor in front of any of it. What it has to establish, in the order that a
 * failure would matter:
 *
 *   1. **Comparison means what an author will assume.** The whole feature is
 *      one predicate, and the cases it gets wrong are invisible — an element
 *      that fails to print says nothing at all, which is the opposite of a
 *      binding typed wrong (an empty box you can see). So the table below is
 *      long on purpose.
 *   2. **A definition with no condition is byte-identical to one written
 *      before conditions existed.** All 57 shipped definitions are in that
 *      state, and a format change that rewrote every one of them to say
 *      nothing would be a diff nobody could review.
 *   3. **A condition survives a save.** The canvas has no control for one yet,
 *      so the danger is not that it cannot be edited but that it is silently
 *      DISCARDED by a save of anything else on the form.
 *   4. **The renderers agree**, and in particular that the page renderer's
 *      deferred pass — the one that draws totals, and the one that does not go
 *      through visibleOnCurrentPage() — honours a condition like every other.
 *
 * Nothing here writes: it builds documents in memory and reads the PDFs back.
 * Safe against a live company, and it needs a company only for TCPDF's fonts
 * and the formatter's currency table.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_conditions.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Definition\{Definition, DefinitionCodec, DefinitionException, TemplateForm};
use FreeBooks\Forms\Document\{
    Align, Binding, Box, Column, Condition, ConditionOp, Context, Currency, Field, LayoutDocument,
    Line, PageSetup, Record, Rect, RenderOptions, SerialCell, SerialDocument, SerialField,
    SerialTemplate, StaticText, Style, Table, Template, TypedValue, ValueType, Visibility
};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer, SerialRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/**
 * The text a PDF draws, as one string.
 *
 * TCPDF compresses its page streams where zlib is available, so reading the
 * bytes as text finds nothing — and every assertion below would pass on an
 * empty string. verify_forms_canvas.php learned this about drawing operators;
 * it is the same trap for text, and the marker count is asserted rather than
 * assumed so that "found nothing" fails instead of agreeing.
 */
function drawnText(string $pdf): string
{
    $out = '';
    if (!preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams)) return $out;
    foreach ($streams[1] as $raw) {
        $plain = @gzuncompress($raw);
        if ($plain === false) $plain = @gzinflate($raw);
        if ($plain === false) $plain = $raw;
        $out .= $plain . "\n";
    }
    return $out;
}

/** How many times a marker is drawn. */
function marks(string $pdf, string $marker): int
{
    return substr_count(drawnText($pdf), $marker);
}

$renderer = new LayoutPdfRenderer(
    formatter:      new Formatter(
                      currencies:      $currencies,
                      defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                      lookups:         forms_formatter_lookups()),
    // helvetica, and NOT the company's FORMS_DEFAULT_FONT, which is freeserif.
    // A core face writes its text into the content stream as ASCII; a TrueType
    // one writes subset GLYPH INDICES, and a marker searched for in those is
    // never found. Measured rather than assumed: the same marker drawn twice
    // came back 1 in helvetica and 0 in freeserif, which is how this tool first
    // reported that nothing at all had been drawn.
    //
    // This is a choice about reading the output, not about how forms print. The
    // conditions under test do not know what face they are drawn in, and the
    // checks that count drawing OPERATORS rather than text — the rectangle and
    // the rule below — pass in either.
    defaultFont:    'helvetica',
    continuedLabel: 'Continued',
    pageOfLabel:    'of',
    imageDirectory: FORMS_DIR_IMAGES,
);
$codec = new DefinitionCodec();


// ─────────────────────────────────────────────────────────────────────────────
echo "=== what an operator means ===\n";

// value, its type, operator, the author's text, and what must come back.
//
// The awkward ones are deliberate and each is here because getting it wrong is
// plausible: "0.00" against 0 (a money column arrives from the database as a
// string), a currency code in the wrong case, a trailing comma in a list, and
// a boolean false — which PHP casts to the empty string, so a careless
// implementation makes every `false` indistinguishable from a missing value.
$cases = [
    // the worked example from §13.51: three blocks covering every record
    ['EUR',     ValueType::Text,    ConditionOp::In,    'GBP',          false],
    ['EUR',     ValueType::Text,    ConditionOp::In,    'EUR',          true],
    ['CHF',     ValueType::Text,    ConditionOp::NotIn, 'GBP,EUR',      true],
    ['EUR',     ValueType::Text,    ConditionOp::NotIn, 'GBP,EUR',      false],
    ['GBP',     ValueType::Text,    ConditionOp::In,    'GBP,EUR,USD',  true],

    // case, and whitespace either side of a list entry
    ['eur',     ValueType::Text,    ConditionOp::Equals, 'EUR',         true],
    ['EUR',     ValueType::Text,    ConditionOp::Equals, 'eur',         true],
    ['EUR',     ValueType::Text,    ConditionOp::In,    'GBP, EUR',     true],
    ['EUR',     ValueType::Text,    ConditionOp::In,    'GBP,EUR,',     true],

    // money as the database hands it over
    ['0.00',    ValueType::Money,   ConditionOp::Equals,  '0',          true],
    [0.0,       ValueType::Money,   ConditionOp::Equals,  '0.00',       true],
    ['1250.00', ValueType::Money,   ConditionOp::Greater, '0',          true],
    ['0.00',    ValueType::Money,   ConditionOp::Greater, '0',          false],
    [-12.5,     ValueType::Money,   ConditionOp::Less,    '0',          true],
    [1250.0,    ValueType::Money,   ConditionOp::Greater, '999',        true],

    // a number compared as a number, not as a string: "9" > "10" by strcmp
    [9,         ValueType::Integer, ConditionOp::Less,    '10',         true],

    // dates need no rule of their own — Y-m-d sorts correctly as text
    ['2025-12-31', ValueType::Date, ConditionOp::Less,    '2026-01-01', true],
    ['2026-02-01', ValueType::Date, ConditionOp::Less,    '2026-01-01', false],

    // present, absent, and the boolean that is neither
    ['',        ValueType::Text,    ConditionOp::IsEmpty,    '',        true],
    ['ABC',     ValueType::Text,    ConditionOp::IsEmpty,    '',        false],
    ['ABC',     ValueType::Text,    ConditionOp::IsNotEmpty, '',        true],
    [false,     ValueType::Boolean, ConditionOp::IsEmpty,    '',        false],
    [false,     ValueType::Boolean, ConditionOp::Equals,     '0',       true],
    [true,      ValueType::Boolean, ConditionOp::Equals,     '1',       true],

    ['ABC',     ValueType::Text,    ConditionOp::NotEquals,  'XYZ',     true],
];
foreach ($cases as [$raw, $type, $op, $text, $expected]) {
    $when = new Condition(Binding::record('x'), $op, $text);
    $got  = $when->satisfiedBy(new TypedValue($raw, $type));
    ok(sprintf("%-10s %-8s %-10s => %s", var_export($raw, true), $op->value,
               $text === '' ? "''" : $text, $expected ? 'prints' : 'does not'),
       $got === $expected, 'got ' . ($got ? 'prints' : 'does not'));
}

// A binding that resolves to nothing at all. This is the case an author reaches
// by mistyping a field name, and the answer has to be predictable: absent is
// empty, and equals-something is false rather than an error.
$missing = null;
ok('a value the record does not supply is empty',
   (new Condition(Binding::record('nope'), ConditionOp::IsEmpty))->satisfiedBy($missing));
ok('a value the record does not supply equals nothing',
   !(new Condition(Binding::record('nope'), ConditionOp::Equals, 'EUR'))->satisfiedBy($missing));

// An entirely blank list matches nothing, so `in ''` is an element that never
// prints — a loud authoring error rather than one that quietly matches blanks.
ok("an empty `in` list matches nothing, including an empty value",
   !(new Condition(Binding::record('x'), ConditionOp::In, ''))->satisfiedBy(TypedValue::text('')));


// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the format ===\n";

$conditioned = new Template([
    new StaticText('MARKERGBP', new Box(20, 40, 80, 6), null, Visibility::AllPages, 'GBP bank',
        new Condition(Binding::record('currencies_code'), ConditionOp::In, 'GBP')),
    new StaticText('MARKEREUR', new Box(20, 40, 80, 6), null, Visibility::AllPages, 'EUR bank',
        new Condition(Binding::record('currencies_code'), ConditionOp::In, 'EUR')),
    new StaticText('MARKERELSE', new Box(20, 40, 80, 6), null, Visibility::AllPages, 'other bank',
        new Condition(Binding::record('currencies_code'), ConditionOp::NotIn, 'GBP,EUR')),
]);
$withCondition = new Definition(id: 'zzz', type: Definition::FORM, title: 'Conditions',
                                template: $conditioned, page: new PageSetup('A4', 'P'));

$json = $codec->encode($withCondition);
ok('a condition is written to the file', str_contains($json, '"when"'), substr($json, 0, 200));
ok('encode -> decode -> encode is byte-stable',
   $codec->encode($codec->decode($json, 'zzz')) === $json);

$back  = $codec->decode($json, 'zzz')->template->elements;
$first = $back[0]->when;
ok('the operator survives the round trip', $first?->op === ConditionOp::In, $first?->op->value ?? 'null');
ok('the binding survives the round trip',  $first?->bind->name === 'currencies_code');
ok('the value survives the round trip',    $first?->value === 'GBP');
ok('the "otherwise" element survives as not-in',
   $back[2]->when?->op === ConditionOp::NotIn && $back[2]->when?->value === 'GBP,EUR');

// The guarantee the shipping set depends on: an element with no condition
// writes NO key, so every definition that predates this feature re-encodes
// exactly as it was.
$plain = new Definition(id: 'zzz', type: Definition::FORM, title: 'Plain',
    template: new Template([new StaticText('PLAIN', new Box(20, 40, 80, 6))]),
    page: new PageSetup('A4', 'P'));
ok('an element with no condition writes no `when` key at all',
   !str_contains($codec->encode($plain), 'when'));

// Strict decoding (codec rule 3). An operator this cannot read must not be
// treated as "no condition": that prints an element on every record its author
// meant to restrict, and a form printing MORE than it should is the failure
// nobody reports.
$refused = function (string $body) use ($codec): bool {
    $doc = ['format' => 'freebooks/forms-definition', 'version' => 1, 'type' => 'frm',
            'title' => 't', 'form' => ['elements' => [json_decode($body, true)]]];
    try { $codec->decode(json_encode($doc), 'zzz'); return false; }
    catch (DefinitionException $e) { return true; }
};
ok('an unreadable operator is refused, not ignored',
   $refused('{"type":"text","text":"x","box":{"x":1,"y":1},'
          . '"when":{"bind":{"name":"c"},"op":"matches"}}'));
ok('a condition that is not an object is refused',
   $refused('{"type":"text","text":"x","box":{"x":1,"y":1},"when":"currency=EUR"}'));
ok('an unreadable binding scope inside a condition is refused',
   $refused('{"type":"text","text":"x","box":{"x":1,"y":1},'
          . '"when":{"bind":{"name":"c","scope":"sql"},"op":"eq"}}'));


// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a save does not throw it away ===\n";

// The canvas has no control for a condition yet, so every save posts rows that
// say nothing about one. TemplateForm must carry it from the original — and
// without that, editing anything else on the form strips every condition in it
// and the page that prints afterwards looks perfectly reasonable.
$form    = new TemplateForm();
$request = ['element' => TemplateForm::toRequest($conditioned)];
$saved   = $form->apply($withCondition, $request, ['currencies_code' => true]);
$kept    = $saved->template->elements;

ok('a save that knows nothing about conditions keeps all three',
   count($kept) === 3, count($kept) . ' element(s)');
ok('the condition itself is carried through a save',
   $kept[0]->when?->op === ConditionOp::In && $kept[0]->when?->value === 'GBP');
ok('the saved definition is byte-identical to the one loaded',
   $codec->encode($saved) === $json);


// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the page renderer ===\n";

/** One record, in the currency named. */
$record = function (string $code, float $balance = 0.0): Record {
    return new Record(
        values: [
            'currencies_code' => TypedValue::text($code),
            'balance_due'     => TypedValue::money($balance),
        ],
        tables: ['items' => array_map(fn(int $n) => [
            'sku' => TypedValue::text('SKU-' . $n),
            'amt' => TypedValue::money(10.0),
        ], range(1, 3))],
        currency: new Currency($code, 1.0),
        key: 'r1',
    );
};
$render = function (Template $template, Record $record) use ($renderer): string {
    return $renderer->render(new LayoutDocument(
        title: 'conditions', template: $template, records: [$record],
        context: new Context(), page: new PageSetup('A4', 'P'),
    ), new RenderOptions(filename: 'conditions.pdf'))->bytes;
};

foreach ([['GBP', 'MARKERGBP'], ['EUR', 'MARKEREUR'], ['CHF', 'MARKERELSE']] as [$code, $wanted]) {
    $pdf   = $render($conditioned, $record($code));
    $drawn = [];
    foreach (['MARKERGBP', 'MARKEREUR', 'MARKERELSE'] as $marker) {
        if (marks($pdf, $marker) > 0) $drawn[] = $marker;
    }
    ok("a $code record prints $wanted and nothing else in that box",
       $drawn === [$wanted], $drawn === [] ? 'nothing was drawn' : implode(' + ', $drawn));
}

// Every kind, not only text. A condition on Element rather than inside a text
// element is the whole reason the model was shaped this way, and a rule or a
// box that ignored its own condition would make the claim false.
$drawings = new Template([
    new Rect(new Box(20, 60, 40, 20), new Style(borderSize: 2),
             Visibility::AllPages, 'boxed', new Condition(Binding::record('currencies_code'),
                                                          ConditionOp::Equals, 'GBP')),
    new Line(new Box(20, 90, 40, 0), 'solid', new Style(borderSize: 2),
             Visibility::AllPages, 'ruled', new Condition(Binding::record('currencies_code'),
                                                          ConditionOp::Equals, 'GBP')),
]);
$gbpDrawn = substr_count(drawnText($render($drawings, $record('GBP'))), ' re');
$eurDrawn = substr_count(drawnText($render($drawings, $record('EUR'))), ' re');
ok('a rectangle and a rule are drawn when their condition holds', $gbpDrawn > 0, "$gbpDrawn");
ok('neither is drawn when it does not', $eurDrawn < $gbpDrawn, "$eurDrawn vs $gbpDrawn");

// A table, which is placed by renderRecord() before drawElement() runs and so
// is tested at its own call site. A condition it ignored would print every line
// item of a suppressed table.
$tabled = new Template([
    new Table('items', [
        new Column(Binding::record('sku'), 'MARKERHEAD', 40, Align::Left),
        new Column(Binding::record('amt'), 'Amount',     30, Align::Right),
    ], new Box(20, 120, 80, 40), null, null, Visibility::AllPages, 'lines',
       new Condition(Binding::record('currencies_code'), ConditionOp::Equals, 'GBP')),
]);
ok('a conditional table prints its rows when the condition holds',
   marks($render($tabled, $record('GBP')), 'SKU-1') === 1);
ok('a conditional table prints neither heading nor rows when it does not',
   marks($render($tabled, $record('EUR')), 'SKU-1') === 0
   && marks($render($tabled, $record('EUR')), 'MARKERHEAD') === 0);

// The branch the implementation comment warns about. A LastPageOnly element is
// drawn by drawDeferredElements(), which reads ->visible itself and never calls
// visibleOnCurrentPage() — so a condition tested in the obvious-looking place
// would work on everything EXCEPT totals, and say nothing about it.
$deferred = new Template([
    new StaticText('MARKERDUE', new Box(20, 200, 60, 6), null,
                   Visibility::LastPageOnly, 'balance owing',
                   new Condition(Binding::record('balance_due'), ConditionOp::Greater, '0')),
    new StaticText('MARKERPAID', new Box(20, 210, 60, 6), null,
                   Visibility::LastPageOnly, 'paid stamp',
                   new Condition(Binding::record('balance_due'), ConditionOp::Equals, '0')),
]);
// The absent half of each pair is the check that bites: if the deferred pass
// ignored conditions, the suppressed marker would still be drawn once by it.
$owing = $render($deferred, $record('GBP', 250.0));
$clear = $render($deferred, $record('GBP', 0.0));
ok('a conditional LAST-PAGE element prints when there is a balance',
   marks($owing, 'MARKERDUE') > 0 && marks($owing, 'MARKERPAID') === 0,
   sprintf('due %d, paid %d', marks($owing, 'MARKERDUE'), marks($owing, 'MARKERPAID')));
ok('and gives way to the stamp when there is not',
   marks($clear, 'MARKERPAID') > 0 && marks($clear, 'MARKERDUE') === 0,
   sprintf('due %d, paid %d', marks($clear, 'MARKERDUE'), marks($clear, 'MARKERPAID')));

// A bound field, which is where an author will put a condition second.
$fielded = new Template([
    new Field(Binding::record('currencies_code'), new Box(20, 40, 60, 6), null, null,
              Visibility::AllPages, 'code',
              new Condition(Binding::record('balance_due'), ConditionOp::Greater, '0')),
]);
ok('a conditional bound field resolves only when the condition holds',
   marks($render($fielded, $record('GBP', 250.0)), 'GBP') > 0
   && marks($render($fielded, $record('GBP', 0.0)), 'GBP') === 0);


// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the receipt renderer ===\n";

// The model is shared — a SerialField wraps the same Element — so a condition
// that held on one renderer and not the other would be a definition meaning two
// different things depending on which printer it reached.
$receipt = new SerialTemplate([
    new SerialField(new StaticText('LEFT', new Box(0, 0)), new SerialCell(width: 10)),
    new SerialField(new StaticText('MID', new Box(0, 0)), new SerialCell(width: 10),
                    ),
    new SerialField(new StaticText('RIGHT', new Box(0, 0)),
                    new SerialCell(width: 10, rowBreak: true)),
], 30);
$conditionedReceipt = new SerialTemplate([
    $receipt->fields[0],
    new SerialField(new StaticText('MID', new Box(0, 0, null, null), null, Visibility::AllPages,
                                  null, new Condition(Binding::record('currencies_code'),
                                                      ConditionOp::Equals, 'GBP')),
                    new SerialCell(width: 10)),
    $receipt->fields[2],
], 30);

$till = new SerialRenderer();
$line = function (SerialTemplate $t, string $code) use ($till, $record): string {
    return explode("\n", $till->renderToString(
        new SerialDocument(title: 'r', template: $t, records: [$record($code)])))[0];
};

$plainLine = $line($receipt, 'GBP');
$keptLine  = $line($conditionedReceipt, 'GBP');
$goneLine  = $line($conditionedReceipt, 'EUR');

ok('a receipt field prints when its condition holds', $keptLine === $plainLine,
   var_export($keptLine, true));
ok('and is blanked when it does not', !str_contains($goneLine, 'MID'),
   var_export($goneLine, true));
// The cells after it must not slide along the line to fill the gap: on a till
// receipt that is every column of the row going crooked to hide one value.
ok('a suppressed field leaves its cell empty rather than removing it',
   strpos($goneLine, 'RIGHT') === strpos($plainLine, 'RIGHT'),
   var_export($goneLine, true));


echo "\n" . ($failures === 0 ? "all checks passed\n" : "$failures check(s) FAILED\n");
exit($failures === 0 ? 0 : 1);
