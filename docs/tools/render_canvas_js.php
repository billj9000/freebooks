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
// +-----------------------------------------------------------------+
//  Path: /docs/tools/render_canvas_js.php
//
/**
 * Render the form canvas's script as the page emits it, and check it holds
 * together (design §13.12, §13.17).
 *
 * The canvas is the one page in Forms whose whole behaviour lives in a script.
 * A mis-nested brace there does not raise an error anybody sees: the module
 * fails to parse, every listener goes unbound, and the editor comes up as a
 * picture of a page that cannot be edited — which looks like a rendering
 * problem rather than a syntax one. This is the cheapest thing that catches it.
 *
 * Two checks, and it is worth being clear about which is which:
 *
 *   1. the file RENDERS — every language constant resolves, and no PHP notice
 *      lands in the middle of the script, which would break it just as surely;
 *   2. the rendered script PARSES.
 *
 * The second is done by `node --check` where node is present, and by the brace
 * scanner further down where it is not. They are not the same check and the
 * output says which one ran. node is a parser: it knows the language, and it
 * knows the canvas script is a `type="module"` — the extension it is handed
 * decides that — so `import` and top-level `await` are read as the module
 * syntax they are rather than reported as faults. The scanner only counts
 * delimiters. It catches the mistake this kind of edit usually makes and
 * nothing else, so where it is all that ran, a pass is weaker than it looks.
 *
 * At the time of writing the application container has PHP but no node, and
 * this tool needs a database, so in practice the scanner is what runs here and
 * node is what runs in verify_page_javascript.php, which renders the same page
 * without a database on a machine that has node. Putting node in the container
 * would collapse that split, and nothing else would have to change.
 *
 * Neither check can tell you the code is correct — only the browser can do that
 * — so both are guards, not proofs.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/render_canvas_js.php [company] [--print]
 *
 * With --print the rendered script is written to stdout after the checks.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
require_once(__DIR__ . '/node_check.php');
$company = fb_bootstrap($argv[1] ?? null);

gen_pull_language('forms');
require(DIR_FS_MODULES . 'forms/defaults.php');
// The canvas page calls forms_document_actions_script() before its own script
// (§13.29). Without this the page dies on an undefined function and every check
// below is skipped in silence — which is what it did, unnoticed, from the day
// that call was added: this tool is not named verify_* so the suite never ran it.
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');

// The script is emitted only for an OPEN definition, and it reads five things
// off the page. None of them changes its shape — the boxes are serialized as
// JSON and the security level picks one flag — so the smallest plausible set
// renders the same code a real form does.
$security_level          = 4;
$forms_canvas_id         = 1;
$forms_canvas_definition = new stdClass();
$forms_canvas_boxes      = [['index' => 0, 'kind' => 'text', 'x' => 10, 'y' => 20,
                             'width' => 30, 'height' => 5, 'label' => 'a box']];
$forms_canvas_values     = [['index' => '0', 'x' => '10', 'y' => '20', 'w' => '30', 'h' => '5']];

ob_start();
require(DIR_FS_MODULES . 'forms/pages/canvas/js_include.php');
$rendered = ob_get_clean();

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if (!$cond) $failures++;
    echo ($cond ? '  ok   ' : '  FAIL ') . $what . ($detail !== '' ? "  — $detail" : '') . "\n";
}

echo "\nThe canvas script, as the page emits it\n";

// The page emits TWO inline scripts: a classic one for the document actions,
// then the module that is the canvas. Taking "everything after the first
// <script>" — which this did — leaves a </script> in the middle of what it then
// calls javascript. The scanner survived that by luck; a parser does not.
$blocks = fb_extract_script_blocks($rendered);
$modules = array_values(array_filter($blocks, fn($b) => $b['module']));
ok('the page emits a module script', count($modules) === 1,
   sprintf('%d inline script(s), %d module(s)', count($blocks), count($modules)));

$js = $modules[0]['js'] ?? '';
ok('it has a body', strlen($js) > 1000, strlen($js) . ' bytes');

// A constant that did not resolve prints its own NAME, and PHP's notice text
// lands in the script where it becomes a syntax error further down.
ok('every language constant resolved', !str_contains($js, 'FORMS_CANVAS_'));
foreach (['Warning:', 'Notice:', 'Fatal error:', 'Deprecated:'] as $noise) {
    ok('no PHP ' . rtrim($noise, ':') . ' in the script', !str_contains($js, $noise));
}

/**
 * Balance {}, () and [], skipping strings, comments and regex literals.
 *
 * The fallback for when node is absent. Kept rather than deleted because the
 * container this tool usually runs in has no node, and half a check there beats
 * none — but see the note at the top: it counts delimiters, it does not know
 * the language, and a pass from it is worth less than a pass from node.
 *
 * The regex heuristic is the standard one: a slash begins a literal only where
 * a VALUE could begin. After a name or a closing bracket it is division. The
 * canvas has two regex literals and no division, so this is not delicate here —
 * but getting it wrong silently swallows half the file, which would make the
 * check pass by reading almost nothing.
 *
 * @return list<string> what is wrong, or none
 */
function unbalanced(string $js): array
{
    $stack = [];
    $bad   = [];
    $line  = 1;
    $prev  = '';
    $n     = strlen($js);
    $pairs = ['}' => '{', ')' => '(', ']' => '['];

    for ($i = 0; $i < $n; $i++) {
        $c    = $js[$i];
        $next = $i + 1 < $n ? $js[$i + 1] : '';
        if ($c === "\n") { $line++; continue; }

        if ($c === '/' && $next === '/') {
            while ($i < $n && $js[$i] !== "\n") $i++;
            $line++;
            continue;
        }
        if ($c === '/' && $next === '*') {
            for ($i += 2; $i < $n && !($js[$i] === '*' && ($js[$i + 1] ?? '') === '/'); $i++) {
                if ($js[$i] === "\n") $line++;
            }
            $i++;
            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            for ($i++; $i < $n && $js[$i] !== $c; $i++) {
                if ($js[$i] === '\\')      $i++;
                elseif ($js[$i] === "\n")  $line++;
            }
            $prev = 'x';
            continue;
        }
        if ($c === '/' && ($prev === '' || strpos('(,=:[!&|?{};+-*%~^<>', $prev) !== false)) {
            for ($i++; $i < $n && $js[$i] !== '/'; $i++) if ($js[$i] === '\\') $i++;
            $prev = 'x';
            continue;
        }

        if (strpos('{([', $c) !== false) {
            $stack[] = [$c, $line];
        } elseif (isset($pairs[$c])) {
            $top = array_pop($stack);
            if ($top === null)                 $bad[] = "line $line: stray $c";
            elseif ($top[0] !== $pairs[$c])    $bad[] = "line $line: $c closes {$top[0]} from line {$top[1]}";
        }
        if (trim($c) !== '') $prev = $c;
    }
    foreach ($stack as $left) $bad[] = "line {$left[1]}: {$left[0]} is never closed";
    return $bad;
}

$node = fb_find_node();
if ($node !== null) {
    // Checked as a module, which is what the <script> tag asked for. Under
    // classic rules its import alone would be reported as an error.
    $parsed = fb_node_check($node, $js, true);
    ok('the script parses, as a module (node ' . fb_node_version($node) . ')',
       $parsed['ok'], $parsed['error']);
} else {
    $bad = unbalanced($js);
    ok('braces, brackets and parentheses balance', $bad === [], implode('; ', array_slice($bad, 0, 4)));
    echo "  [--] node was not found, so the weaker of the two checks ran.\n"
       . "       verify_page_javascript.php parses this same script where node is.\n";
}

// Every name the page hands to the browser as an inline handler, plus the
// helpers the gestures rest on. A module has its own scope, so a function the
// toolbar's onclick names has to be BOTH defined and exported (§13.12).
foreach (['formsCanvasSave', 'formsCanvasZ', 'formsCanvasDelete', 'formsCanvasAdd',
          'formsCanvasUndo', 'formsCanvasRedo'] as $exported) {
    ok("$exported is defined and exported",
       preg_match('/function\s+' . $exported . '\s*\(/', $js) === 1
       && str_contains($js, 'window.' . $exported));
}
foreach (['select', 'describeSelection', 'highlight', 'place', 'origins', 'moveBy',
          'resize', 'boxAt', 'zoneAt', 'sweep', 'dragCursor', 'fine', 'rounded'] as $helper) {
    ok("$helper is defined", preg_match('/function\s+' . $helper . '\s*\(/', $js) === 1);
}

if (in_array('--print', $argv, true)) echo "\n" . $js . "\n";

echo "\n" . ($failures ? "$failures check(s) FAILED\n" : "all checks passed\n");
exit($failures === 0 ? 0 : 1);
