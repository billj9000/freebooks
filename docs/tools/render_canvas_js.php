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
 * There is no JavaScript engine on the development machine or in the container,
 * and the canvas is the one page in Forms whose whole behaviour lives in a
 * script. A mis-nested brace there does not raise an error anybody sees: the
 * module fails to parse, every listener goes unbound, and the editor comes up
 * as a picture of a page that cannot be edited — which looks like a rendering
 * problem rather than a syntax one. This is the cheapest thing that catches it.
 *
 * Two checks, and it is worth being clear about which is which:
 *
 *   1. the file RENDERS — every language constant resolves, and no PHP notice
 *      lands in the middle of the script, which would break it just as surely;
 *   2. the rendered script's delimiters BALANCE.
 *
 * The second is a scanner, not a parser. It knows about both comment forms,
 * three kinds of string and regex literals, which is enough for the mistake
 * this kind of edit actually makes. It cannot tell you the code is correct —
 * only the browser can do that — so it is a guard, not a proof.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/render_canvas_js.php [company] [--print]
 *
 * With --print the rendered script is written to stdout after the checks.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);

gen_pull_language('forms');
require(DIR_FS_MODULES . 'forms/defaults.php');

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

ok('the page emits a module script',
   str_contains($rendered, '<script type="module">') && str_contains($rendered, '</script>'));

$js = preg_replace('#^.*?<script[^>]*>#s', '', $rendered);
$js = preg_replace('#</script>\s*$#s', '', $js);
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

$bad = unbalanced($js);
ok('braces, brackets and parentheses balance', $bad === [], implode('; ', array_slice($bad, 0, 4)));

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
          'resize', 'zoneAt', 'sweep', 'dragCursor', 'fine', 'rounded'] as $helper) {
    ok("$helper is defined", preg_match('/function\s+' . $helper . '\s*\(/', $js) === 1);
}

if (in_array('--print', $argv, true)) echo "\n" . $js . "\n";

echo "\n" . ($failures ? "$failures check(s) FAILED\n" : "all checks passed\n");
exit($failures === 0 ? 0 : 1);
