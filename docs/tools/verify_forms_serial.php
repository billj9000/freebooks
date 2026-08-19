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
//
/**
 * Forms — the serial (receipt) path, held to the byte. See §13.27.
 *
 * The receipt is the one document Forms renders as TEXT, and the only check
 * that means anything about it is whether the till prints the same characters
 * it printed before. So this compares SerialRenderer's output against
 * PhreeForm's BuildSeq() for the same record, byte for byte.
 *
 * It is a separate tool rather than a section of verify_forms_native.php
 * because that one's form branch compares a PDF against a PDF and plans through
 * FormRunner::plan(), the legacy-object entry point, which declines a receipt
 * on purpose: a serial form is served from a native Definition. The comparison
 * that matters here is legacy ENGINE against new ENGINE, not one format against
 * the other, so it belongs on its own.
 *
 * Run:
 *       docker exec <c> php /opt/repo/docs/tools/verify_forms_serial.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\FormRunner;
use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionCodec, DefinitionException, DefinitionLoader, SerialForm};
use FreeBooks\Forms\Document\SerialDocument;
use FreeBooks\Forms\Render\{Formatter, SerialRenderer};

$failures = 0;
function ok(string $what, bool $passed, string $detail = ''): void
{
    global $failures;
    if (!$passed) $failures++;
    printf("  [%s] %s%s\n", $passed ? 'ok' : 'FAIL', $what,
        $passed || $detail === '' ? '' : " — $detail");
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);
$loader = new DefinitionLoader($catalog);
$runner = new FormRunner($db, $catalog);

$renderer = new SerialRenderer(new Formatter(
    currencies:      $GLOBALS['currencies'] ?? null,
    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
    lookups:         forms_formatter_lookups(),
));

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the serial definitions on this company ===\n";

$serial = [];
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM . "
    where doc_type <> '0' and doc_ext = 'frm' order by id");
while (!$result->EOF) {
    $id = (int)$result->fields['id'];
    try { $definition = $loader->load($id); } catch (DefinitionException) { $result->MoveNext(); continue; }
    if ($definition->isSerial()) $serial[$id] = [$definition, (string)$result->fields['doc_title']];
    $result->MoveNext();
}

if ($serial === []) {
    // A company without the POS module has no receipt. Not a failure, but it
    // must be SAID: a silent pass here would read as "the receipt is fine".
    echo "  [--] none — this company has no receipt form, so nothing below ran\n";
    echo "\nPASS (nothing to check)\n";
    exit(0);
}
printf("  %d serial definition(s): %s\n", count($serial),
    implode(', ', array_map(fn($e) => $e[1], $serial)));

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the layout converts, and survives the native format ===\n";

foreach ($serial as $id => [$definition, $title]) {
    $template = $definition->serialTemplate;
    ok("pf_$id ($title) has a character-cell layout", $template !== null,
        'the conversion produced none');
    if ($template === null) continue;

    ok("pf_$id keeps every field", count($template->fields) > 0, 'it converted to nothing');
    ok("pf_$id has a paper width", $template->width > 0, "width {$template->width}");

    // Grouping into lines is derived, so it must add up to the flat list.
    $inLines = array_sum(array_map('count', $template->lines()));
    ok("pf_$id groups into lines without losing a field",
        $inLines === count($template->fields),
        "$inLines grouped, " . count($template->fields) . " stored");

    // The round trip that matters: a receipt SAVED by the editor must come
    // back the same, or the first save silently rewrites the layout.
    $codec = new DefinitionCodec();
    $back  = $codec->decode($codec->encode($definition), (string)$id);
    ok("pf_$id round-trips through the native format",
        $back->serialTemplate !== null
        && count($back->serialTemplate->fields) === count($template->fields)
        && $back->serialTemplate->width === $template->width,
        'the layout changed on the way through');
    // The invariant Definition's docblock states: a form carries a page layout
    // or a character-cell one, never both. It was broken in exactly one place
    // — the codec built an empty Template for every form on the way back — and
    // nothing noticed until a receipt had actually been SAVED, because only a
    // native file goes through the decoder.
    ok("pf_$id carries one layout, not two",
        $back->serialTemplate !== null && $back->template === null,
        'the decoded definition has a page template as well as a receipt one');

    ok("pf_$id round-trips its cells unchanged",
        $back->serialTemplate !== null && serialCells($back->serialTemplate) === serialCells($template),
        'a width, alignment or row break moved');
    // The property an editor lives or dies by: opening a receipt and saving it
    // without touching anything must change nothing. toRequest() is what the
    // page renders into its form, apply() is what the save reads back, and if
    // the two disagree the first save silently rewrites the layout.
    $editor    = new SerialForm();
    $untouched = $editor->apply($definition, [
        'title' => $definition->title,
        'paper' => (string)$template->width,
        'field' => SerialForm::toRequest($template),
    ]);
    ok("pf_$id survives being opened and saved unchanged",
        $untouched->serialTemplate !== null
        && serialCells($untouched->serialTemplate) === serialCells($template)
        && $untouched->serialTemplate->width === $template->width,
        'the editor rewrote the layout without being asked to');
    ok("pf_$id keeps its bindings through the editor",
        $untouched->serialTemplate !== null
        && (new DefinitionCodec())->encode($untouched) === (new DefinitionCodec())->encode($definition),
        'a binding, format or separator changed on the way through the editor');
    foreach ($editor->warnings() as $warning) {
        printf("        note: %s\n", $warning);
    }

    ok("pf_$id encodes to the same bytes twice",
        $codec->encode($back) === $codec->encode($definition),
        'the encoding is not stable');
}

/** @return list<string> every cell, flattened, for comparing two layouts. */
function serialCells(\FreeBooks\Forms\Document\SerialTemplate $t): array
{
    return array_map(fn($f) => sprintf('%s:%d:%s:%d:%d',
        (new ReflectionClass($f->element))->getShortName(),
        $f->cell->width, $f->cell->align->value,
        (int)$f->cell->rowBreak, (int)$f->cell->wholeLine), $t->fields);
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== the new engine prints the bytes the old engine printed ===\n";

/**
 * A legacy report object from XML, wherever that XML came from.
 *
 * get_report_details() does exactly this but only ever against `pf_<id>` in the
 * live directory — so once a receipt has been SAVED it is native, that function
 * correctly refuses it, and the comparison it was reading for becomes
 * impossible. The XML has not gone anywhere though: the editor copies it to
 * `pf_<id>.pre-forms` on the first save, precisely so the thing it converted
 * from is never lost.
 *
 * Holding BOTH engines to that same original is what makes this check survive
 * editing. It compares ENGINE against ENGINE rather than stored-file against
 * stored-file, so an author changing their receipt can never turn it red — and
 * it keeps working after the last legacy receipt in the world has been
 * converted, which is when a byte guarantee is hardest to re-establish and
 * most worth still having.
 */
function legacyFromXml(string $xml): ?object
{
    $report = xml_to_object($xml);
    if (!is_object($report)) return null;
    if (isset($report->PhreeformReport) && is_object($report->PhreeformReport)) {
        $report = $report->PhreeformReport;
    }
    foreach (['tables', 'grouplist', 'sortlist', 'filterlist', 'fieldlist'] as $list) {
        if (isset($report->$list) && is_object($report->$list)) $report->$list = [$report->$list];
    }
    if (isset($report->fieldlist) && is_array($report->fieldlist)) {
        foreach ($report->fieldlist as $key => $field) {
            if (isset($field->boxfield) && is_object($field->boxfield)) {
                $report->fieldlist[$key]->boxfield = [$field->boxfield];
            }
        }
    }
    return $report;
}

$compared = 0;
foreach ($serial as $id => [$definition, $title]) {
    $stored = (string)@file_get_contents($loader->path($id));
    $native = DefinitionLoader::isNative($stored);
    $kept   = $loader->path($id) . '.pre-forms';

    // Whichever file still holds the PhreeForm original.
    $xml = !$native ? $stored : (is_file($kept) ? (string)file_get_contents($kept) : '');
    if ($xml === '' || DefinitionLoader::isNative($xml)) {
        echo "  [--] pf_$id ($title): converted, and no PhreeForm original is kept beside it;"
           . " there is nothing left to compare the engines against\n";
        continue;
    }
    printf("  comparing against %s\n", $native ? 'the kept original (this receipt has been saved)' : 'the stored definition');

    if ($native) {
        // Information, not a verdict. A receipt that has been edited SHOULD
        // print differently from the one it was converted from; saying so
        // stops the difference below being read as a fault in the renderer.
        $edited = $definition->serialTemplate?->fields ?? [];
        $origin = $loader->loadContents($xml, (string)$id)->serialTemplate?->fields ?? [];
        if (count($edited) !== count($origin)) {
            printf("  [--] pf_%d has been edited since it was converted (%d fields, was %d)\n",
                $id, count($edited), count($origin));
        }
    }

    $legacyReport = legacyFromXml($xml);
    if ($legacyReport === null) {
        ok("pf_$id ($title) has readable PhreeForm XML to compare against", false,
            'xml_to_object() could not parse it');
        continue;
    }
    if (empty($legacyReport->serialform)) {
        ok("pf_$id ($title) is a serial form on both sides", false,
            'the PhreeForm original does not carry serialform');
        continue;
    }

    // A receipt needs a record of its own kind. journal_id 19 is the POS sale;
    // any other journal row would have the legacy decline before it rendered,
    // and a comparison against a refusal proves nothing.
    $ids  = [];
    $rows = $db->Execute("select id from " . TABLE_JOURNAL_MAIN . "
        where journal_id = 19 order by id desc limit 5");
    while (!$rows->EOF) { $ids[] = (int)$rows->fields['id']; $rows->MoveNext(); }

    if ($ids === []) {
        echo "  [--] pf_$id ($title): no point-of-sale entry on this company to print\n";
        continue;
    }

    foreach ($ids as $recordId) {
        // BOTH engines read the same XML. The legacy one CONSUMES the object it
        // is given — BuildDataArray() and build_criteria() write back onto it —
        // so it is rebuilt for every record rather than reused.
        $report = legacyFromXml($xml);
        $filter = new stdClass();
        $filter->fieldname = 'journal_main.id';
        $filter->default   = 'EQUAL';
        $filter->min_val   = (string)$recordId;
        $filter->type      = '';
        $report->xfilterlist = [$filter];
        $report->datedefault = 'a';
        $GLOBALS['report'] = $report;

        $legacy = BuildForm($report, 'S');
        if (!is_string($legacy) || $legacy === '' || $legacy === '1') {
            // BuildForm() returns true — '1' as a string — when it declines.
            continue;
        }

        $fromXml  = $loader->loadContents($xml, (string)$id);
        $document = $runner->runDefinition($fromXml, [$recordId]);
        if (!$document instanceof SerialDocument) {
            ok("pf_$id runs to a receipt", false, 'the runner returned ' . get_class($document));
            break;
        }
        $forms = $renderer->renderToString($document);

        // And what is STORED — which after a save is the native file, not the
        // XML both engines just read. Unedited, it must print the same bytes:
        // that is the question a save actually raises, and neither the
        // engine comparison above nor the round-trip checks answer it.
        $storedDoc = $runner->runDefinition($definition, [$recordId]);
        $storedOut = $storedDoc instanceof SerialDocument
            ? $renderer->renderToString($storedDoc) : '';
        $unchanged = $definition->serialTemplate !== null
            && serialCells($definition->serialTemplate) === serialCells($fromXml->serialTemplate);
        if ($unchanged) {
            ok("pf_$id ($title) prints record $recordId the same after being saved",
                $storedOut === $legacy,
                'saving the receipt changed what it prints');
        } else {
            ok("pf_$id ($title) still prints something after being edited",
                $storedOut !== '', 'the edited receipt renders nothing');
        }

        $compared++;
        ok("pf_$id ($title) prints record $recordId identically",
            $forms === $legacy,
            sprintf('legacy %d bytes, Forms %d bytes, first difference at %d',
                strlen($legacy), strlen($forms), firstDifference($legacy, $forms)));

        if ($forms !== $legacy) {
            $l = explode("\n", $legacy);
            $f = explode("\n", $forms);
            for ($i = 0; $i < max(count($l), count($f)); $i++) {
                if (($l[$i] ?? null) === ($f[$i] ?? null)) continue;
                printf("        %2d old |%s|\n        %2d new |%s|\n", $i,
                    str_replace(' ', '.', $l[$i] ?? '<none>'), $i,
                    str_replace(' ', '.', $f[$i] ?? '<none>'));
            }
        }
    }
}

function firstDifference(string $a, string $b): int
{
    $n = min(strlen($a), strlen($b));
    for ($i = 0; $i < $n; $i++) if ($a[$i] !== $b[$i]) return $i;
    return $n;
}

if ($compared === 0) {
    echo "  [--] no receipt could be printed on both engines here; nothing was compared\n";
} else {
    printf("  %d record(s) compared\n", $compared);
}

echo "\n" . ($failures === 0 ? "all checks passed\n" : "$failures check(s) FAILED\n");
exit($failures === 0 ? 0 : 1);
