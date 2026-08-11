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
//  Path: /docs/tools/render_forms_document.php
//
/**
 * Render a form through the FORMS engine, for comparison with the PhreeForm
 * baseline captured by capture_phreeform_baseline.php (docs/forms-design.md §8).
 *
 * Converts the stored PhreeForm definition to a Forms Template, builds the
 * document with EntryDocumentProvider, and renders it with LayoutPdfRenderer —
 * so the comparison exercises the real geometry rather than a template written
 * by hand to suit the renderer.
 *
 * The output is deliberately a PDF per record, matching the baseline's layout,
 * so the two can be diffed with pdftotext/pdfinfo on the host. PDF bytes never
 * match (TCPDF regenerates file_id and the creation date per run), which is why
 * the comparison is on page count and extracted text.
 *
 * NOT part of the application. A dev aid, run inside the container.
 *
 * Usage (from the host):
 *   docker cp docs/tools/ <container>:/tmp/fbtools/
 *   docker exec <container> php -d display_errors=1 \
 *       /tmp/fbtools/render_forms_document.php <report_id> <ids> [out_dir] [company]
 *
 * Renders nothing to the database: the printed-counter side effect arrives as
 * an AfterRenderHook which this tool deliberately does not execute.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');

$REPORT_ID = isset($argv[1]) ? (int)$argv[1] : 0;
$RECORDS   = isset($argv[2]) ? array_values(array_filter(array_map('intval', explode(',', $argv[2])))) : [];
$OUT       = $argv[3] ?? '/tmp/forms_out';
if (!$REPORT_ID || !$RECORDS) {
    fwrite(STDERR, "Usage: php render_forms_document.php <report_id> <id,id,...> [out_dir] [company]\n");
    exit(1);
}

$company = fb_bootstrap($argv[4] ?? null);
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/render/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/template_converter.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/entry_provider.php');

use FreeBooks\Forms\Document\{Context, RenderOptions, TypedValue};
use FreeBooks\Forms\Legacy\TemplateConverter;
use FreeBooks\Forms\Provider\EntryDocumentProvider;
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer};

if (!is_dir($OUT) && !@mkdir($OUT, 0777, true)) {
    fwrite(STDERR, "Cannot create $OUT\n");
    exit(1);
}

$report = get_report_details($REPORT_ID);
if (!$report) { fwrite(STDERR, "Report $REPORT_ID not found.\n"); exit(1); }

$converter = new TemplateConverter();
$template  = $converter->convert($report);
$page      = $converter->convertPageSetup($report);

echo "report:   pf_$REPORT_ID (" . ($report->title ?? '?') . ")\n";
echo "elements: " . count($template->elements) . " converted\n";
foreach ($converter->warnings() as $w) echo "  WARN $w\n";

// The company block. PhreeForm resolved CDta/CBlk by calling constant() on the
// stored field name, so the set is whatever a template happens to reference —
// not a fixed list. Deriving it from the template is what the app layer does,
// and it is the only way the Context is guaranteed to cover the form.
$contextValues = [];
$missing       = [];
$collect = function (string $name) use (&$contextValues, &$missing) {
    if ($name === '' || isset($contextValues[$name])) return;
    if (defined($name)) $contextValues[$name] = TypedValue::text((string)constant($name));
    else                $missing[] = $name;
};
foreach ($template->elements as $element) {
    if ($element instanceof \FreeBooks\Forms\Document\Field
        && $element->bind->scope === \FreeBooks\Forms\Document\Scope::Context) {
        $collect($element->bind->name);
    }
    if ($element instanceof \FreeBooks\Forms\Document\FieldBlock) {
        foreach ($element->parts as $part) {
            if ($part->bind->scope === \FreeBooks\Forms\Document\Scope::Context) $collect($part->bind->name);
        }
    }
}
printf("context:  %d constants resolved%s\n", count($contextValues),
    $missing ? ', MISSING: ' . implode(', ', $missing) : '');

$context = new Context($contextValues, $_SESSION['language'] ?? 'en_us');

$provider = new EntryDocumentProvider(
    db:       $db,
    template: $template,
    context:  $context,
    title:    (string)($report->title ?? 'Document'),
    page:     $page,
);

$formatter = new Formatter($currencies, DEFAULT_CURRENCY, fb_formatter_lookups());
$renderer  = new LayoutPdfRenderer(
    formatter:      $formatter,
    defaultFont:    defined('PDF_DEFAULT_FONT') ? PDF_DEFAULT_FONT : 'helvetica',
    rowSpacing:     defined('PF_DEFAULT_ROWSPACE') ? (float)PF_DEFAULT_ROWSPACE : 2.0,
    continuedLabel: defined('TEXT_CONTINUED') ? TEXT_CONTINUED : 'Continued',
    pageOfLabel:    defined('TEXT_OF') ? TEXT_OF : 'of',
    imageDirectory: PF_DIR_MY_REPORTS . 'images',
);

$exit = 0;
foreach ($RECORDS as $recId) {
    $doc = $provider->build(['record_ids' => [$recId]]);
    if (!count($doc->records)) {
        printf("  pf_%-4d rec %-6d NO RECORD\n", $REPORT_ID, $recId);
        $exit = 1;
        continue;
    }

    $out  = $renderer->render($doc, new RenderOptions(filename: "forms_pf{$REPORT_ID}_rec{$recId}.pdf"));
    $path = rtrim($OUT, '/') . "/forms_pf{$REPORT_ID}_rec{$recId}.pdf";
    file_put_contents($path, $out->bytes);

    $record = $doc->records[0];
    printf("  pf_%-4d rec %-6d %-4s lines=%-4d pdf=%dB  hooks=%d (not run)\n",
        $REPORT_ID, $recId,
        $record->currency?->code ?? '?',
        count($record->table('items')),
        $out->size(),
        count($doc->afterRender()));
}

echo "rendered to $OUT\n";
exit($exit);
