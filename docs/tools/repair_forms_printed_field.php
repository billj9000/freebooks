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
//  Path: /docs/tools/repair_forms_printed_field.php
//
/**
 * Put the printed counter back on a company that was migrated without it.
 *
 * The converter dropped `setprintedfield` until §13.45, so every company
 * migrated before that has invoices whose definition no longer asks for the
 * counter — which is why `journal_main.printed` stopped moving and the Sales
 * Invoice Manager's "unprinted" filter started listing everything. The code fix
 * makes new conversions carry it; this fixes the ones already done.
 *
 * It does NOT re-convert. The kept original — `pf_N.pre-forms`, saved by the
 * migration precisely so a question like this can be answered years later — is
 * read for the one tag, and the tag alone is written into the native file. A
 * re-conversion would discard whatever has been done on the canvas since.
 *
 *     docker exec <c> php -d display_errors=1 \
 *         /opt/repo/docs/tools/repair_forms_printed_field.php [company] [--write]
 *
 * Without --write nothing is touched and every intended change is listed.
 *
 * ## A definition whose original never declared one
 *
 * Some companies carry a customised invoice that was copied from the shipped
 * one BEFORE PhreeBooks added the tag, so there is nothing in its original to
 * recover: it has simply never marked anything printed, under either engine.
 * `--set N` stamps such a definition, and refuses unless the form's own record
 * table is the one the counter lives on, so a statement or a purchase order
 * cannot be stamped with a sales invoice's counter by a slip of the finger.
 *
 * ## What it will not do
 *
 * Write anything if the file would change in more than that one key. The native
 * files round-trip byte-identically through the codec, and this tool insists on
 * that per file before writing: a definition that would come back different is
 * reported and left alone, because a repair that quietly rewrites a form is a
 * worse outcome than a counter that stays broken for another day.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$args    = array_slice($argv, 1);
$write   = in_array('--write', $args, true);
$forced  = [];
foreach ($args as $index => $arg) {
    if ($arg === '--set' && isset($args[$index + 1])) $forced[] = (int)$args[$index + 1];
}
$company = fb_bootstrap(array_values(array_filter($args, static fn($a) =>
    $a[0] !== '-' && !ctype_digit($a)))[0] ?? null);

require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{Definition, DefinitionCodec, DefinitionLoader};
use FreeBooks\Forms\Legacy\LegacyReader;

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);

$codec  = new DefinitionCodec();
$loader = new DefinitionLoader($catalog);
$dir    = rtrim(FORMS_DIR_DEFINITIONS, '/\\');

if (!is_dir($dir)) { echo "  no definitions directory on this company\n"; exit(0); }

/** The counter a definition's kept original asked for, or ''. */
function declared_counter(string $path): string
{
    if (!is_file($path)) return '';
    $original = LegacyReader::fromFile($path);
    return $original === null ? '' : trim((string)($original->setprintedfield ?? ''));
}

/** The same definition with a counter added, or null if it cannot be added safely. */
function with_counter(DefinitionCodec $codec, Definition $d, string $counter, string $raw): ?string
{
    $rebuilt = new Definition(
        id: $d->id, type: $d->type, title: $d->title, description: $d->description,
        group: $d->group, filenamePrefix: $d->filenamePrefix, emailMessage: $d->emailMessage,
        provider: $d->provider, datasetId: $d->datasetId, page: $d->page,
        dateDefault: $d->dateDefault, dateField: $d->dateField, period: $d->period,
        filters: $d->filters, sorts: $d->sorts, columns: $d->columns,
        groupBy: $d->groupBy, groupFormat: $d->groupFormat, totalsOnly: $d->totalsOnly,
        resolvedColumns: $d->resolvedColumns, style: $d->style, template: $d->template,
        breakField: $d->breakField, recordTable: $d->recordTable, recordKey: $d->recordKey,
        printedField: $counter, serial: $d->serial, serialTemplate: $d->serialTemplate,
    );
    $encoded = $codec->encode($rebuilt);

    // The one-key rule: everything the file already said must still be there,
    // character for character, with a single line added.
    $wasLines = explode("\n", $raw);
    $nowLines = explode("\n", $encoded);
    $added    = array_values(array_diff($nowLines, $wasLines));
    $removed  = array_values(array_diff($wasLines, $nowLines));
    $onlyKey  = count($added) <= 2 && count($removed) <= 1
        && (bool)preg_grep('/"printedField"/', $added);

    return $onlyKey ? $encoded : null;
}

echo "\n=== " . ($write ? 'repairing' : 'what would be repaired') . " on $company ===\n";
$patched = $already = $none = $skipped = 0;

foreach (glob("$dir/pf_*") ?: [] as $path) {
    if (str_contains($path, '.pre-forms')) continue;
    $id  = (int)substr(basename($path), 3);
    $raw = (string)file_get_contents($path);
    if (!DefinitionLoader::isNative($raw)) { continue; }   // still legacy; conversion will carry it

    try { $definition = $codec->decode($raw, (string)$id); }
    catch (\Throwable $e) { echo "  pf_$id could not be read: " . $e->getMessage() . "\n"; $skipped++; continue; }

    if (!$definition->isForm()) continue;

    $counter = declared_counter($path . '.pre-forms');
    if (in_array($id, $forced, true) && $counter === '') $counter = 'journal_main.printed';

    if ($counter === '') { $none++; continue; }
    if ($definition->printedField === $counter) { $already++; continue; }

    // A counter belongs to the table the form's own records come from. Without
    // this a forced --set could point an invoice's counter at a form that
    // breaks on contacts, and the UPDATE would silently match nothing.
    $target = (new Definition(printedField: $counter))->printedTarget();
    if ($target === null) {
        echo "  pf_$id ({$definition->title}): its original asks for '$counter', which is not"
           . " a table.field pair — left alone\n";
        $skipped++; continue;
    }
    if ($definition->recordTable !== '' && $definition->recordTable !== $target['table']) {
        echo "  pf_$id ({$definition->title}): counts {$target['table']} but its records are"
           . " {$definition->recordTable} — left alone\n";
        $skipped++; continue;
    }

    $encoded = with_counter($codec, $definition, $counter, $raw);
    if ($encoded === null) {
        echo "  pf_$id ({$definition->title}): adding the counter would rewrite more of the"
           . " file than the one key — left alone\n";
        $skipped++; continue;
    }

    printf("  pf_%-4d %-34s + %s%s\n", $id, $definition->title, $counter,
           in_array($id, $forced, true) ? '   (--set)' : '');
    if ($write) {
        file_put_contents($path, $encoded);
        @chmod($path, 0664);
    }
    $patched++;
}

printf("\n  %d to patch, %d already right, %d declare no counter, %d left alone\n",
       $patched, $already, $none, $skipped);
echo $write
    ? "  written. Print an invoice and check the Printed column on the sales journal list.\n"
    : "  dry run — add --write to apply.\n";
exit(0);
