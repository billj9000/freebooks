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
//  Path: /docs/tools/adopt_forms_layouts.php
//
/**
 * Adopt layouts tidied in a company back into the shipping set (design §13.18).
 *
 * The shipped layouts were inherited from PhreeBooks and several of them are
 * genuinely untidy — overlapping blocks, headings colliding with table rules.
 * They are XML in origin, and nobody is going to fix a layout by editing
 * coordinates in XML. The canvas is where that work belongs, so the tidying is
 * done in a company and travels back here: this is the return leg.
 *
 * ## Why it is a file copy, and why that is not obvious
 *
 * A definition saved by the editor and a definition shipped in the repository are
 * written by the SAME encoder with the same flags, and a native definition holds
 * no company-specific anything — no id, no paths, no keys. Measured on a fresh
 * install of 54 documents: run each through the codec and every one is
 * byte-identical to its committed master. So adoption is a copy with one field
 * put back, and this tool exists to make that safe rather than to make it work.
 *
 * Two things it puts back or refuses:
 *
 *   - THE PAPER. A company is stamped with the installer's Letter/A4 answer, and
 *     the shipped master keeps its own. Adopting the company's `page.size` would
 *     re-paper the whole shipping set for every future install, silently, from
 *     whichever company happened to be used. So the master's size is restored.
 *
 *     It does NOT follow that the paper can be ignored while tidying. Letter is
 *     6 mm wider than A4 and 18 mm SHORTER, and elements are positioned from the
 *     top, so a layout fitted to the foot of an A4 page falls off the foot of a
 *     Letter one. Tidy on the paper the masters ship.
 *
 *   - EVERYTHING THAT IS NOT GEOMETRY, unless asked twice. Moving and resizing is
 *     what this is for. A change to a text, an image, or a binding is a change to
 *     what the document SAYS, and the likeliest reason for one is that the
 *     definition came from a company that had customised it — which is how a real
 *     installation's branding, headings and field choices would get committed to a
 *     public repository. `--allow-content` is the second ask, and every such
 *     change is listed before it is made.
 *
 * ## What it changes about the shipping set's contract
 *
 * Until now every shipped `.json` was a GENERATED artefact:
 * `generate_forms_shipping_set.php` wrote it from the `.xml` and
 * `verify_forms_shipping_set.php` proved, byte for byte, that it still matched.
 * A hand-tidied layout cannot satisfy that and must not be reverted by the next
 * generator run, so an adopted file is recorded in `docs/forms-adopted.json`:
 *
 *   - the generator leaves an adopted file alone (it says so, and `--force`
 *     overrides);
 *   - the verifier drops the byte check for an adopted file and applies the
 *     checks that survive editing instead — it still decodes, it is still
 *     canonical, and it is still the same document as the XML.
 *
 * The XML stays. It is what Import reads, what a company without Forms seeds
 * from, and the record of where these definitions came from.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/adopt_forms_layouts.php [company] \
 *       [--write] [--allow-content] [--only=Title]
 *
 * Without --write it reports what would change and touches nothing. The files it
 * writes are in the DEPLOYED tree, so copy them back into the repository
 * afterwards — the paths it prints are relative to `trunk/`. Nothing needs
 * copying the other way: the build context holds `docs/` and this module only, so
 * there are no report directories in it to copy into. The full procedure is in
 * design §13.18.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\Catalog\{Catalog, CustomDatasetStore, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Definition\DefinitionCodec;

$write   = in_array('--write', $argv, true);
$content = in_array('--allow-content', $argv, true);
$only    = '';
foreach ($argv as $arg) if (str_starts_with($arg, '--only=')) $only = substr($arg, 7);

const ADOPTED_MANIFEST = __DIR__ . '/../forms-adopted.json';

// Both shapes, because both are edited and both can be adopted: a FORM keeps its
// layout in `form.elements[]`, each with a box; a REPORT keeps its in
// `report.columns[]`, each with a width. Only the first is drawn on the canvas,
// but a column width is the same kind of change and travels the same way.

/** The paths that describe WHERE something is, rather than what it says. */
function fb_is_geometry(string $path): bool
{
    return (bool)preg_match('/^form\.elements\.\d+\.box\./', $path)
        || (bool)preg_match('/^(form\.elements\.\d+|report)\.columns\.\d+\.width$/', $path)
        || (bool)preg_match('/^page\.margins\./', $path);
}

/**
 * The paths that carry what a document SAYS, or which data it says it about.
 *
 * Deliberately wide. A false positive here costs one extra flag on the command
 * line; a false negative commits a real installation's wording to a public
 * repository, which is the failure this classification exists to prevent. So a
 * column TOTAL counts as content — it adds a number to the page — while a column
 * BREAK does not, being only where the rest of the columns go.
 */
function fb_is_content(string $path): bool
{
    // `label` is in here because it is FREE TEXT that names an element, and the
    // name someone gives a logo box is their company's. Measured on a company
    // cloned from a real installation: the labels were the give-away, and they
    // would have been adopted without a second ask if this line were not here.
    return (bool)preg_match('/^form\.elements\.\d+\.(text|label|src|source|format|type|visible)$/', $path)
        || (bool)preg_match('/^form\.elements\.\d+\.(bind|parts)\./', $path)
        || (bool)preg_match('/^form\.elements\.\d+\.columns\.\d+\.(key|field|bind|heading|total|format)$/', $path)
        || (bool)preg_match('/^report\.columns\.\d+\.(key|field|bind|heading|total|format)$/', $path)
        || (bool)preg_match('/^report\.(groupBy|resolved)/', $path)
        // A title line is free text with substitutions in it, so it is what the
        // document says; and an orientation turns the whole page, which is a
        // shipping decision like the paper rather than a tidy. Both found by
        // running this over a company cloned from a real installation.
        || (bool)preg_match('/Text$/', $path)
        || $path === 'page.orientation'
        || (bool)preg_match('/^(provider|dataset|type|title|group|description|filenamePrefix|dates|filters)/', $path)
        || (bool)preg_match('/^form\.(source|filters|sorting|grouping)/', $path);
}

/** Every leaf of a decoded definition, keyed by its dotted path. */
function fb_leaves(mixed $value, string $prefix = ''): array
{
    if (!is_array($value)) return [$prefix => var_export($value, true)];
    $out = [];
    foreach ($value as $key => $inner) {
        $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
        $out += fb_leaves($inner, $path);
    }
    // An empty array is a leaf of its own, or a cleared list looks like no change.
    return $value === [] ? [$prefix => '[]'] : $out;
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
(new CustomDatasetStore($db, new SchemaInspector($db), TABLE_FORMS_DATASETS))->registerAll($catalog);
$codec = new DefinitionCodec();

// The shipped masters, by the title a definition carries. Title is what the
// seeder matches on and what the catalogue row stores, so it is the only join
// between a company's `pf_37` and a file called `Invoice.json`.
$masters = [];
$ambiguous = [];
foreach (glob(DIR_FS_MODULES . '*/language/*/reports/*.json') ?: [] as $path) {
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded) || !isset($decoded['title'])) continue;
    $title = (string)$decoded['title'];
    if (isset($masters[$title])) { $ambiguous[$title] = true; continue; }
    $masters[$title] = $path;
}
if ($ambiguous) {
    echo "  [note] " . count($ambiguous) . " title(s) ship more than once; skipped: "
       . implode(', ', array_keys($ambiguous)) . "\n";
}

$adopted = [];
if (is_file(ADOPTED_MANIFEST)) {
    $decoded = json_decode((string)file_get_contents(ADOPTED_MANIFEST), true);
    $adopted = is_array($decoded['adopted'] ?? null) ? $decoded['adopted'] : [];
}

$dir = defined('PF_DIR_MY_REPORTS') ? PF_DIR_MY_REPORTS : DIR_FS_MY_FILES . $company . '/phreeform/';
echo "company: $company\nreading: $dir\n\n";

$same = $wouldWrite = $written = $blocked = $skipped = 0;
$notes = [];

foreach (scandir($dir) ?: [] as $file) {
    if (substr($file, 0, 3) !== 'pf_') continue;
    $raw = (string)file_get_contents($dir . $file);

    // A company that has not been through the migration still has XML here, and
    // an XML definition has no tidied layout to adopt — it has never been on the
    // canvas. Reported rather than converted: converting is the generator's job
    // and it starts from the shipped xml, not from a company's copy of it.
    if (!str_starts_with(ltrim($raw), '{')) { $skipped++; $notes[] = "$file is not native"; continue; }

    try {
        $definition = $codec->decode($raw);
        $canonical  = $codec->encode($definition);
    } catch (\Throwable $e) {
        $skipped++;
        $notes[] = "$file does not decode: " . $e->getMessage();
        continue;
    }

    $decoded = json_decode($canonical, true);
    $title   = (string)($decoded['title'] ?? '');
    if ($only !== '' && $title !== $only) continue;

    if (!isset($masters[$title])) {
        $skipped++;
        // An optional module from extensions/ ships xml only, so its native file
        // exists in the deployed tree and not in the repository. Adopting one
        // means deciding that extension modules ship native at all, which is a
        // larger question than a layout.
        $notes[] = "no shipped master for '" . $title . "' ($file)";
        continue;
    }
    $masterPath = $masters[$title];
    $shown      = str_replace(DIR_FS_MODULES, 'modules/', $masterPath);
    $masterRaw  = (string)file_get_contents($masterPath);
    $masterData = json_decode($masterRaw, true);

    // The paper goes back to the master's, always. See the note at the top.
    $paper = $masterData['page']['size'] ?? null;
    if ($paper !== null) {
        // Objects, so an empty `{}` in the file does not come back as `[]`; and
        // the trailing newline, because that is how DefinitionCodec::encode()
        // ends and this has to stay comparable with what it writes. Both were
        // missing from the seeder and the re-stamp until this tool needed them.
        $object = json_decode($canonical);
        $object->page->size = $paper;
        $canonical = json_encode($object,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $decoded['page']['size'] = $paper;
    }

    if ($canonical === $masterRaw) { $same++; continue; }

    // What actually differs, split by what kind of difference it is.
    $was = fb_leaves($masterData);
    $now = fb_leaves($decoded);
    $geometry = $appearance = $contentChanges = [];
    foreach ($now as $path => $value) {
        if (array_key_exists($path, $was) && $was[$path] === $value) continue;
        $line = $path . ': ' . ($was[$path] ?? '(absent)') . ' -> ' . $value;
        if (fb_is_geometry($path))      $geometry[] = $line;
        elseif (fb_is_content($path))   $contentChanges[] = $line;
        else                            $appearance[] = $line;
    }
    foreach ($was as $path => $value) {
        if (array_key_exists($path, $now)) continue;
        $line = $path . ': ' . $value . ' -> (absent)';
        if (fb_is_geometry($path))      $geometry[] = $line;
        elseif (fb_is_content($path))   $contentChanges[] = $line;
        else                            $appearance[] = $line;
    }
    $countWas = count($masterData['form']['elements'] ?? []);
    $countNow = count($decoded['form']['elements'] ?? []);
    if ($countWas !== $countNow) {
        $contentChanges[] = "form.elements: $countWas element(s) -> $countNow";
    }

    printf("  %s\n      geometry %d, appearance %d, content %d\n",
        $shown, count($geometry), count($appearance), count($contentChanges));
    foreach (array_slice($appearance, 0, 6) as $line) echo "        [appearance] $line\n";
    if (count($appearance) > 6) echo "        [appearance] … " . (count($appearance) - 6) . " more\n";
    foreach ($contentChanges as $line)                echo "        [CONTENT]    $line\n";

    if ($contentChanges && !$content) {
        $blocked++;
        echo "      REFUSED: this changes what the document says, not only where it sits.\n"
           . "      If that is deliberate, re-run with --allow-content. If this company is a\n"
           . "      real installation, those lines are its own customisation and must not be\n"
           . "      committed.\n";
        continue;
    }

    if (!$write) { $wouldWrite++; echo "      would write $shown\n"; continue; }

    if (file_put_contents($masterPath, $canonical) === false) {
        echo "      FAILED to write $shown\n";
        $blocked++;
        continue;
    }
    $written++;
    echo "      written: " . str_replace(DIR_FS_MODULES, 'trunk/modules/', $masterPath) . "\n";
    $relative = str_replace(DIR_FS_MODULES, '', $masterPath);
    if (!in_array($relative, $adopted, true)) $adopted[] = $relative;
}

sort($adopted);
if ($write && $adopted) {
    $manifest = json_encode(
        ['comment' => 'Shipped definitions whose layout was tidied by hand and is no longer'
                    . ' generated from the xml. See docs/tools/adopt_forms_layouts.php.',
         'adopted' => array_values($adopted)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents(ADOPTED_MANIFEST, $manifest) === false) {
        echo "\n  FAILED to write " . ADOPTED_MANIFEST . " — the generator will revert these.\n";
    } else {
        echo "\n  manifest: " . count($adopted) . " adopted definition(s) recorded in docs/forms-adopted.json\n";
    }
}

echo "\n  unchanged $same, "
   . ($write ? "written $written" : "would write $wouldWrite")
   . ", refused $blocked, skipped $skipped\n";

if ($notes) {
    echo "\n  " . count($notes) . " note(s):\n";
    foreach ($notes as $note) echo "    $note\n";
}

if ($written) {
    echo "\n  These were written into the DEPLOYED tree. Copy them into the repository,\n"
       . "  along with docs/forms-adopted.json, then re-run verify_forms_shipping_set.php.\n";
}

exit($blocked ? 1 : 0);
