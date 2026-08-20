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
//  Path: /docs/tools/verify_forms_standalone.php
//
/**
 * Everything Forms still borrows from PhreeForm, named and counted.
 *
 *   docker exec <c> php /opt/repo/docs/tools/verify_forms_standalone.php [-v]
 *
 * ## Why this exists
 *
 * Step 6 ends by DELETING PhreeForm, and the sequencing question that answers
 * is: what breaks when it goes? Reading the module for `phreeform` finds the
 * requires and misses everything else — a constant defined in its defaults, a
 * global its defaults assigns, a function its functions file declares. Those
 * are invisible in a Forms file: `PF_DEFAULT_ROWSPACE` looks like any other
 * constant and `value_to_words_en_us()` looks like a helper. The day PhreeForm
 * is removed each becomes a fatal, one page at a time, found by whoever prints
 * something.
 *
 * So the vocabulary is not a list written here. It is READ OUT of PhreeForm —
 * every constant its files define, every function they declare, every global
 * its defaults.php assigns at file scope — and then looked for in Forms. A name
 * added to PhreeForm and used from Forms next year is caught without anyone
 * having to remember this file exists.
 *
 * ## The list is the point
 *
 * BORROWED below is what Forms is currently allowed to take, each with the
 * reason. Both directions fail:
 *
 *   - a borrowing NOT on the list is a new coupling, and step 6 got longer;
 *   - a name on the list that Forms no longer uses is a stale entry, and the
 *     list has to shrink to stay honest.
 *
 * The second half is what makes the list worth reading: it cannot quietly
 * describe last month's module. When BORROWED is empty, Forms stands alone and
 * PhreeForm can be deleted without touching Forms at all.
 *
 * No database and no company: this reads source. It is in the verify_ family
 * because it is a check that fails, not because it needs a bootstrap.
 */

$root  = dirname(__DIR__, 2) . '/trunk';
$forms = $root . '/modules/forms';
$pf    = $root . '/modules/phreeform';

$verbose  = in_array('-v', $argv, true);
$failures = 0;

function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

/**
 * What Forms may still take from PhreeForm, and why.
 *
 * Shrink this as the dependencies move. Do not add to it without a reason that
 * survives step 6 — there is only one entry of that kind, and it is marked.
 */
const BORROWED = [
    // Requires. Each page pulls PhreeForm's bootstrap for the entries below it.
    'phreeform'                        => "gen_pull_language('phreeform'), for the constants defaults.php reads",
    'phreeform/defaults.php'           => 'the paper sizes and the row-space default',
    'phreeform/functions/phreeform.php'=> 'TextReplace(), used by the run pages',

    // Renderer defaults that were PhreeForm admin settings and have no Forms
    // equivalent yet. Each is a document property in the native format, so
    // these are fallbacks for a definition that does not carry one.
    'PF_DEFAULT_COLUMN_WIDTH'          => 'fallback column width, read behind defined()',
    'PF_DEFAULT_ROWSPACE'              => 'fallback row height for a table renderer',
    'PF_DEFAULT_PAPERSIZE'             => 'the paper a seeded document gets',
    'TEXT_CONTINUED'                   => 'the label on a table that runs to a second page',
    'TEXT_GROUP_TOTAL_FOR'             => "a report's group subtotal label",
    'TEXT_REPORT_TOTAL_FOR'            => "a report's grand total label",

    // Tables PhreeForm builds in its defaults.php.
    'PaperSizes'                       => 'the paper sizes the report editor offers',

    // Functions declared in PhreeForm and called from Forms.
    'TextReplace'                      => '%date% / %company% substitution in a title',
    'value_to_words_en_us'             => "the 'n2wrd' format, for cheque amounts",

    // Language constants the seeder reports with.
    'PHREEFORM_REPDUP'                 => 'seeder message: a document of that name exists',
    'PHREEFORM_WRITE_ERROR'            => 'seeder message: the definition could not be written',

    // NOT a step-6 dependency. The catalogue table is shared BY DESIGN — one
    // row per document, read by both engines, which is what made running the
    // two side by side possible at all (§13.3). When PhreeForm goes, the
    // constant moves to forms/config.php and the table keeps its name.
    'TABLE_PHREEFORM'                  => 'the shared document catalogue (by design)',
];

/**
 * What PhreeForm, and only PhreeForm, declares.
 *
 * Three narrowings, each of which the first draft got wrong and each of which
 * decides whether the answer is readable at all:
 *
 *   - **Its own files.** `includes/fpdf/` is vendored FPDF, so scanning it made
 *     `Cell()` and `AddPage()` look like PhreeForm's — and every PDF renderer
 *     in Forms calls those, on a TCPDF object that has nothing to do with
 *     PhreeForm. Its `pages/` are excluded too: a page's own constants go with
 *     the page.
 *   - **Column zero only.** `function` at an indent is a METHOD, and method
 *     names are the commonest words in the codebase — `install`, `output`,
 *     `notes`, `list`. A borrowed FUNCTION is declared at file scope, which is
 *     the only way PhreeForm declares one.
 *   - **Declared nowhere else.** A name that core or another module also
 *     defines is not PhreeForm's to lend, and deleting PhreeForm does not take
 *     it away. `PAGE_TITLE` and the TEXT_* strings go out here.
 *
 * @return array{constants:array<string,string>,functions:array<string,string>,globals:array<string,string>}
 */
function phreeformVocabulary(string $root, string $pf): array
{
    $out = ['constants' => [], 'functions' => [], 'globals' => []];

    foreach (phpFiles($pf) as $path) {
        $where = str_replace($pf . '/', '', $path);
        if (str_starts_with($where, 'includes/fpdf/')) continue;   // vendored FPDF
        if (str_starts_with($where, 'pages/'))         continue;   // a page's own names go with the page
        if (str_starts_with($where, 'custom/'))        continue;
        $src = (string)file_get_contents($path);

        // define('X', ...) — the only form PhreeForm uses.
        if (preg_match_all("/\bdefine\s*\(\s*'([A-Za-z_][A-Za-z0-9_]*)'/", $src, $m)) {
            foreach ($m[1] as $name) $out['constants'][$name] ??= $where;
        }
        // Its runtime settings. These are never define()d in source: install.php
        // seeds pb_configuration and application_top turns each row into a
        // constant, so PF_DEFAULT_ROWSPACE exists at runtime and appears nowhere
        // a source search would look. Deleting the module deletes the setting.
        if ($where === 'classes/install.php'
            && preg_match_all("/'([A-Z][A-Z0-9_]+)'\s*=>/", $src, $m)) {
            foreach ($m[1] as $name) $out['constants'][$name] ??= $where;
        }
        // Functions at file scope. An indented one is a METHOD, and method
        // names are the commonest words in the codebase — install, output,
        // notes, list. PhreeForm declares its own functions at column zero.
        if (preg_match_all('/^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/m', $src, $m)) {
            foreach ($m[1] as $name) $out['functions'][$name] ??= $where;
        }
        // Globals assigned at file scope — and only in the two files that exist
        // to declare them. Every other PhreeForm file assigns working variables
        // at column zero as well, and `$i` is not a table anybody borrows.
        if (($where === 'defaults.php' || $where === 'config_phreeform.php')
            && preg_match_all('/^\$([A-Za-z_][A-Za-z0-9_]*)\s*=/m', $src, $m)) {
            foreach ($m[1] as $name) $out['globals'][$name] ??= $where;
        }
    }

    // Anything the rest of the tree also declares is not PhreeForm's to lend,
    // and deleting PhreeForm does not take it away. PAGE_TITLE and the shared
    // TEXT_* strings go out here.
    $elsewhere = [];
    foreach (phpFiles($root) as $path) {
        if (str_starts_with($path, $pf . '/')) continue;
        if (str_contains($path, '/vendor/'))   continue;
        $src = (string)file_get_contents($path);
        if (preg_match_all("/\bdefine\s*\(\s*'([A-Za-z_][A-Za-z0-9_]*)'/", $src, $m)) {
            foreach ($m[1] as $name) $elsewhere[$name] = true;
        }
        if (preg_match_all('/^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/m', $src, $m)) {
            foreach ($m[1] as $name) $elsewhere[$name] = true;
        }
    }
    foreach (['constants', 'functions'] as $kind) {
        $out[$kind] = array_diff_key($out[$kind], $elsewhere);
    }
    return $out;
}

/** @return list<string> */
function phpFiles(string $dir): array
{
    $files = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($rii as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') $files[] = $file->getPathname();
    }
    sort($files);
    return $files;
}

/**
 * Live references in the Forms module — comments and doc blocks excluded.
 *
 * Through the tokeniser, not a grep, because this module explains itself at
 * length: every name below appears in prose somewhere, and a check that counted
 * prose would be permanently red and therefore permanently ignored.
 *
 * @return array<string,list<string>> name => where
 */
function formsReferences(string $forms, array $vocabulary): array
{
    $found = [];
    foreach (phpFiles($forms) as $path) {
        $where = str_replace($forms . '/', '', $path);
        $src   = (string)file_get_contents($path);

        foreach (token_get_all($src) as $token) {
            if (!is_array($token)) continue;
            [$id, $text, $line] = $token;
            if ($id === T_COMMENT || $id === T_DOC_COMMENT || $id === T_INLINE_HTML) continue;

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                // A require path or a gen_pull_language() argument. Only the
                // whole literal counts: prose ABOUT phreeform is not a use of
                // it, and prose is what a substring search would find.
                $literal = trim($text, "'\"");
                if ($literal === 'phreeform' || str_starts_with($literal, 'phreeform/')) {
                    $found[$literal][] = "$where:$line";
                }
                continue;
            }
            if ($id === T_STRING && (isset($vocabulary['constants'][$text])
                                  || isset($vocabulary['functions'][$text]))) {
                $found[$text][] = "$where:$line";
                continue;
            }
            if ($id === T_VARIABLE && isset($vocabulary['globals'][ltrim($text, '$')])) {
                $found[ltrim($text, '$')][] = "$where:$line";
            }
        }

        // $GLOBALS['Fonts'] reads a global without ever writing $Fonts, so the
        // tokeniser sees only a string subscript. Matched by name, because that
        // spelling is exactly how a borrowed global hides from the check above.
        foreach (array_keys($vocabulary['globals']) as $name) {
            if (!preg_match_all('/\$GLOBALS\s*\[\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*\]/',
                                $src, $m, PREG_OFFSET_CAPTURE)) continue;
            foreach ($m[0] as $hit) {
                $found[$name][] = $where . ':' . (substr_count($src, "\n", 0, $hit[1]) + 1);
            }
        }
    }
    foreach ($found as $name => $where) $found[$name] = array_values(array_unique($where));
    ksort($found);
    return $found;
}

// ─────────────────────────────────────────────────────────────────────────────
$vocabulary = phreeformVocabulary($root, $pf);
printf("PhreeForm declares %d constants, %d functions and %d file-scope globals.\n",
    count($vocabulary['constants']), count($vocabulary['functions']), count($vocabulary['globals']));

$used = formsReferences($forms, $vocabulary);
printf("Forms names %d of them in live code.\n\n", count($used));

echo "=== what Forms still borrows ===\n";
foreach ($used as $name => $where) {
    $why = BORROWED[$name] ?? null;
    printf("  %-34s %s\n", $name, $why ?? '*** NOT ON THE LIST ***');
    if ($verbose || $why === null) foreach ($where as $one) echo "        $one\n";
}
if ($used === []) echo "  (nothing — Forms stands alone)\n";

echo "\n=== the list is honest ===\n";
$new = array_diff(array_keys($used), array_keys(BORROWED));
ok('nothing is borrowed that is not on the list', $new === [],
   'new coupling to PhreeForm: ' . implode(', ', $new));

$stale = array_diff(array_keys(BORROWED), array_keys($used));
ok('nothing on the list has stopped being borrowed', $stale === [],
   'remove from BORROWED, the dependency is gone: ' . implode(', ', $stale));

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
