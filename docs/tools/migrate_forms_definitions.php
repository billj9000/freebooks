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
//  Path: /docs/tools/migrate_forms_definitions.php
//
/**
 * Convert a company's definitions from PhreeForm XML to the native format.
 *
 * §13.7 step 6, and decisions I, J and K: convert everything in BULK at
 * upgrade, delete PhreeForm in the same release, and block on anything that
 * cannot be converted rather than skipping it. A permanent dual-format world
 * buys a distinction no user can perceive; a partial migration buys a company
 * documents that nothing can read.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/migrate_forms_definitions.php <company> [--write]
 *
 * **It reports and changes nothing unless you pass `--write`.** Rewriting every
 * definition a company owns is not something to discover you have done.
 *
 * ## What blocks
 *
 * Decision J, refined by what the survey found. Three outcomes, not two:
 *
 *   - **Convertible** — converted, or would be.
 *   - **Blocked** — the file is there and cannot be converted, or converts into
 *     something that will not survive being written and read back. This is
 *     decision J exactly: something readable today that PhreeForm's removal
 *     would make unreadable for ever. Nothing is written for the whole company
 *     while any of these stand.
 *   - **Already lost** — the catalogue names a file that is not on disk. One of
 *     the companies surveyed had 56 such rows and no `pf_` files whatever.
 *
 * That third case is why J needed refining. A missing file is unreadable by
 * BOTH engines already, so deleting PhreeForm loses nothing that is not already
 * lost — and blocking on it would make such a company permanently
 * un-upgradeable for a fault the migration neither caused nor can repair. It is
 * reported, loudly and by id, and it does not block.
 *
 * ## The bar this tool holds, and the one it does not
 *
 * Every definition is converted, encoded, decoded and encoded again, and the
 * two encodings must be byte-identical. That is a TOTAL check — it needs no
 * data, so it covers every definition on every company, including the ones a
 * render comparison must skip — and what it proves is the thing this tool is
 * responsible for: **the file written back holds exactly what the conversion
 * produced.**
 *
 * It does NOT prove the converted definition prints the same document. That
 * needs records to render, so it can only ever cover the definitions a company
 * happens to have data for, and it already has a home: `verify_forms_native.php`
 * renders both paths and compares the bytes. Run it first. This tool says so
 * rather than quietly implying a guarantee it has not checked.
 *
 * ## Going back
 *
 * Every conversion keeps the original beside the new file as `pf_N.pre-forms`,
 * which is the same convention the canvas has used since §13.3.
 *
 * `--rollback` restores **only what this tool converted**, read from the
 * manifest a `--write` run leaves behind. That scoping is the whole of its
 * safety, and it was learnt the expensive way: the first version restored every
 * `.pre-forms` it could find, which is not the same set. A definition saved in
 * the Forms editor months ago ALSO has one — its PhreeForm original — and
 * restoring that does not undo a migration, it discards the author's work. Run
 * against a company where five documents had been edited that way, it silently
 * reverted all five.
 *
 * So a rollback with no manifest refuses rather than guessing. The originals are
 * still there to be restored by hand if that is genuinely wanted, and a refusal
 * that can be overridden deliberately beats an action that cannot be undone.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);

require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\{DefinitionCodec, DefinitionException, DefinitionLoader};

$flags    = array_slice($argv, 2);
$write    = in_array('--write', $flags, true);
$rollback = in_array('--rollback', $flags, true);

// A company can have PhreeForm definitions and no Forms module: an archive
// created before Forms existed is exactly that, and one of the four surveyed
// was. Its custom-dataset table is not there to read, so registering from it
// would be a fatal on the tool that is supposed to be telling the operator what
// state the company is in.
//
// The conversion itself does not need the module installed. It needs the
// SHIPPED datasets, which are code, and the loader, which is code. So the tool
// runs, says what it found, and converts what it can — an archive with no
// definitions has nothing to convert, and one with definitions should not be
// left behind because a table is missing.
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$forms_installed = defined('MODULE_FORMS_STATUS') && defined('TABLE_FORMS_DATASETS');
if ($forms_installed) {
    (new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))
        ->registerAll($catalog);
} else {
    echo "  [--] the Forms module is not installed on this company;"
       . " only the shipped datasets are known\n";
}
$loader = new DefinitionLoader($catalog);
$codec  = new DefinitionCodec();

// What a --write run leaves behind so that --rollback knows what it did, and
// just as importantly what it did NOT do.
$manifestPath = (defined('FORMS_DIR_DEFINITIONS') ? FORMS_DIR_DEFINITIONS : '')
              . 'forms-migration.json';

/** Native is decided by the first non-whitespace byte — the loader's own rule. */
function forms_is_native(string $path): bool
{
    $head = ltrim((string)file_get_contents($path, false, null, 0, 64));
    return $head !== '' && $head[0] === '{';
}

$rows = [];
$result = $db->Execute("select id, doc_title, doc_type from " . TABLE_PHREEFORM
                     . " where doc_type <> '0' order by id");
while (!$result->EOF) {
    $rows[(int)$result->fields['id']] = (string)$result->fields['doc_title'];
    $result->MoveNext();
}

// ── going back ───────────────────────────────────────────────────────────────
if ($rollback) {
    echo "=== rollback: undoing what this tool converted ===\n";

    if (!is_file($manifestPath)) {
        echo "  There is no migration manifest in this company's report folder, so\n"
           . "  there is no record of what this tool converted here.\n\n"
           . "  Restoring every pf_N.pre-forms instead would NOT be a rollback. A\n"
           . "  definition saved in the Forms editor also has one — its PhreeForm\n"
           . "  original — and putting that back discards the author's work rather\n"
           . "  than undoing a migration.\n\n"
           . "  Nothing was changed. Restore by hand if that is really what you want.\n";
        exit(1);
    }

    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    $ids = is_array($manifest['converted'] ?? null) ? $manifest['converted'] : null;
    if ($ids === null) {
        echo "  The manifest could not be read. Nothing was changed.\n";
        exit(1);
    }
    printf("  the manifest records %d definition(s) converted on %s\n",
        count($ids), (string)($manifest['when'] ?? 'an unrecorded date'));

    $restored = $absent = 0;
    foreach ($ids as $id) {
        $path = $loader->path((int)$id);
        $kept = $path . '.pre-forms';
        if (!is_file($kept)) { $absent++; continue; }
        if (!$write) { $restored++; continue; }
        // The original is COPIED back, not moved: a rollback that consumed the
        // only copy could not be repeated, and the second attempt is exactly
        // when somebody needs it.
        if (@copy($kept, $path)) $restored++;
        else echo "  could not restore pf_$id\n";
    }
    printf("  %s %d definition(s); %d had no original kept\n",
        $write ? 'restored' : 'would restore', $restored, $absent);

    // The manifest goes only once its work is undone, so a half-finished
    // rollback can be run again.
    if ($write && $restored > 0 && $absent === 0) @unlink($manifestPath);
    if (!$write) echo "\n  Nothing was changed. Add --write to do it.\n";
    exit(0);
}

// ── survey, then convert ─────────────────────────────────────────────────────
echo "=== " . count($rows) . " document(s) in the catalogue ===\n";

$native = $lost = 0;
$todo = $blocked = $warned = [];

foreach ($rows as $id => $title) {
    $path = $loader->path($id);

    if (!is_file($path)) {
        $lost++;
        // Named individually rather than counted. A row pointing at nothing is
        // a fault somebody has to decide about, and a total invites nobody to.
        $blocked['__lost'][] = "pf_$id ($title)";
        continue;
    }
    if (forms_is_native($path)) { $native++; continue; }

    try {
        $definition = $loader->load($id);
    } catch (DefinitionException $e) {
        $blocked['unreadable'][] = "pf_$id ($title): " . $e->getMessage();
        continue;
    }
    $warnings = $loader->warnings();

    // The total check: what we would write must read back as what we converted.
    try {
        $encoded = $codec->encode($definition);
        $again   = $codec->encode($codec->decode($encoded, (string)$id));
    } catch (\Throwable $e) {
        $blocked['unwritable'][] = "pf_$id ($title): " . $e->getMessage();
        continue;
    }
    if ($encoded !== $again) {
        $blocked['unstable'][] = "pf_$id ($title): the native file does not read back as itself";
        continue;
    }

    $todo[$id] = ['title' => $title, 'encoded' => $encoded];
    if ($warnings) $warned["pf_$id ($title)"] = $warnings;
}

printf("  already native : %d\n", $native);
printf("  to convert     : %d\n", count($todo));
printf("  already lost   : %d\n", $lost);

$hard = array_diff_key($blocked, ['__lost' => true]);
$hardCount = 0;
foreach ($hard as $list) $hardCount += count($list);
printf("  BLOCKED        : %d\n", $hardCount);

if ($lost) {
    echo "\n--- the catalogue names these, and there is no file ---\n";
    echo "  Unreadable by PhreeForm too, so removing it loses nothing that is not\n";
    echo "  already gone. They do not block, and they are not repaired here.\n";
    foreach (array_slice($blocked['__lost'], 0, 12) as $line) echo "    $line\n";
    if ($lost > 12) echo "    ... and " . ($lost - 12) . " more\n";
}

if ($warned) {
    echo "\n--- converted, with something to report ---\n";
    foreach ($warned as $what => $list) {
        echo "  $what\n";
        foreach ($list as $warning) echo "      $warning\n";
    }
    echo "  These convert. Read them before writing: a warning is the converter\n";
    echo "  saying it did something you may not have wanted, not that it failed.\n";
}

if ($hardCount) {
    echo "\n--- BLOCKED, and nothing will be written while they stand ---\n";
    foreach ($hard as $kind => $list) {
        foreach ($list as $line) echo "  [$kind] $line\n";
    }
    echo "\n  Decision J: the migration blocks rather than skips, because after\n";
    echo "  PhreeForm is deleted nothing can read what was skipped.\n";
    exit(1);
}

if (!$todo) {
    echo "\n  Nothing to convert. This company is already native.\n";
    exit(0);
}

if (!$write) {
    echo "\n  Nothing was changed.\n";
    echo "  Run verify_forms_native.php on this company first — it renders both\n";
    echo "  paths and compares the bytes, which is the one thing this tool does\n";
    echo "  not check. Then add --write.\n";
    exit(0);
}

echo "\n=== writing ===\n";
$done = 0;
foreach ($todo as $id => $entry) {
    $path = $loader->path($id);
    $kept = $path . '.pre-forms';

    // Only the FIRST conversion keeps an original. A second would overwrite the
    // PhreeForm file with a native one and lose the very thing it preserves —
    // the same rule, and the same reason, as the canvas's own keep (§13.3).
    if (!is_file($kept) && !@copy($path, $kept)) {
        echo "  could not keep the original of pf_$id; stopping before writing it\n";
        exit(1);
    }

    try {
        $loader->save(
            $codec->decode($entry['encoded'], (string)$id), $id);
        $done++;
    } catch (\Throwable $e) {
        echo "  pf_$id ({$entry['title']}) failed to write: " . $e->getMessage() . "\n";
        echo "  Stopping. Everything written so far is intact, and --rollback undoes it.\n";
        exit(1);
    }
}

file_put_contents($manifestPath, (string)json_encode([
    'when'      => date('Y-m-d H:i:s'),
    'converted' => array_map('intval', array_keys($todo)),
], JSON_PRETTY_PRINT));

printf("  converted %d definition(s); each original kept as pf_N.pre-forms\n", $done);
echo "\n  Re-run without --write to confirm, and verify_forms_native.php to prove\n";
echo "  the documents still render. --rollback --write puts the originals back.\n";
exit(0);
