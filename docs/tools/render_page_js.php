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
//  Path: /docs/tools/render_page_js.php
//
/**
 * Get the javascript out of a page's js_include.php, without a database.
 *
 * A js_include.php is not javascript. It is PHP that PRINTS javascript, and it
 * prints a different program depending on the action, on whether the last post
 * failed, and on what is in the query string. `node --check` cannot be pointed
 * at the file, and reading it does not tell you what any one of those branches
 * emits. This renders one branch so that something else can check it.
 *
 * It works without bootstrapping the application, which is the point: these
 * pages reach half of FreeBooks, and a check that needed a company and a
 * database would be a check nobody ran. Instead every constant the file names
 * becomes a placeholder string and every function it calls becomes a stub that
 * returns nothing, both discovered from the file's own tokens. Undefined
 * variables and array keys are left to evaluate to null, as PHP 8 does anyway.
 *
 * **What comes out is not what the application would emit.** Language constants
 * are their own names, calendars and address widgets render as nothing. What it
 * DOES preserve is the shape: the same statements, in the same branches, with
 * the same quoting and the same delimiters. That is what a syntax check asks
 * about, and it is all this claims.
 *
 * A page may emit more than one inline script, and they need not be the same
 * language: the form canvas emits a classic script and then a `type="module"`.
 * They come back separately for that reason — see fb_extract_script_blocks().
 *
 *   php docs/tools/render_page_js.php <page> [action] [k=v&k=v] [outstem]
 *
 *   php docs/tools/render_page_js.php orders edit print=1
 *
 * <page> is a key from fb_page_js_pages() below, or a path relative to the
 * repository root. With no outstem the javascript goes to stdout; with one,
 * each block is written to <outstem>.N.mjs or <outstem>.N.js, the extension
 * saying which language it is, and the names are listed on stderr.
 *
 * Runs anywhere PHP does — the development machine or the container — since it
 * opens no database and includes no application bootstrap.
 */

require_once(__DIR__ . '/node_check.php');

/**
 * The pages this knows how to render, and the variables each one reads that are
 * not constants. Seeds are deliberately minimal and plainly fictional: they
 * exist to get the file through to its javascript, not to model real data.
 *
 * 'variants' lists the branches worth checking — the ones that emit different
 * javascript. verify_page_javascript.php walks them.
 */
function fb_page_js_pages(): array {
    $address = ['primary_name', 'address1', 'city_town', 'country_code'];
    $company = ['Example Co', '1 Example Street', 'Exampleton', 'GB'];
    $labels  = ['Name', 'Address', 'Town', 'Country'];

    return [
        'orders' => [
            'path' => 'trunk/modules/phreebooks/pages/orders/js_include.php',
            'seed' => function (bool $error) use ($address, $company, $labels) {
                $order = new stdClass();
                $order->ship_short_name = '';
                $order->id              = '';
                return [
                    'js_arrays'         => ['fields' => $address, 'company' => $company, 'text' => $labels],
                    'order'             => $order,
                    'error'             => $error,
                    'oID'               => 1234,
                    'req_date'          => '2026-01-31',
                    'cal_order'         => ['name' => 'dateOrdered'],
                    'cal_terminal'      => ['name' => 'dateRequired'],
                    'template_options'  => ['terminal_date' => true],
                    'js_gl_array'       => "var js_gl_array = new Array(0);\n",
                    'js_tax_rates'      => "var tax_rates = new Array(0);\n",
                    'js_proj_list'      => "var proj_list = new Array(0);\n",
                    'shipping_methods'  => "var freightLevels = new Array();\n",
                    'account_type'      => 'c',
                    'security_level'    => 4,
                    'item_col_1_enable' => true,
                    'item_col_2_enable' => false,
                    // Only build_js_currency_arrays() is called on this path.
                    'currencies'        => new class {
                        function build_js_currency_arrays() { return "var currencies = [];\n"; }
                    },
                ];
            },
            // A document opened over AJAX must NOT snapshot itself at init;
            // fillOrder() does that when the response lands. A failed post must
            // not snapshot at all. Both are branches here, so both are checked.
            'variants' => [
                ['action' => '',       'get' => '',        'error' => false],
                ['action' => 'edit',   'get' => '',        'error' => false],
                ['action' => 'edit',   'get' => 'print=1', 'error' => false],
                ['action' => 'prc_so', 'get' => '',        'error' => false],
                ['action' => 'email',  'get' => '',        'error' => false],
                ['action' => 'save',   'get' => '',        'error' => true ],
            ],
        ],

        'bills' => [
            'path' => 'trunk/modules/phreebooks/pages/bills/js_include.php',
            'seed' => function (bool $error) use ($address, $company, $labels) {
                $order = new stdClass();
                $order->id = '';
                return [
                    'js_arrays'       => ['fields' => $address, 'company' => $company, 'text' => $labels],
                    'order'           => $order,
                    'error'           => $error,
                    'oID'             => 4242,
                    'cal_bills'       => ['name' => 'dateOrdered'],
                    'payment_modules' => [],
                    'account_type'    => 'c',
                    'security_level'  => 4,
                    'post_success'    => false,
                ];
            },
            'variants' => [
                ['action' => '',     'get' => '',        'error' => false],
                ['action' => 'edit', 'get' => '',        'error' => false],
                ['action' => 'edit', 'get' => 'print=1', 'error' => false],
                ['action' => 'pmt',  'get' => '',        'error' => false],
                ['action' => 'save', 'get' => '',        'error' => true ],
            ],
        ],

        'work_orders' => [
            'path' => 'extensions/Modules/work_orders/pages/main/js_include.php',
            'seed' => function (bool $error) {
                return [
                    'error'          => $error,
                    'id'             => 77,
                    'cal_date'       => ['name' => 'dateReference'],
                    'security_level' => 4,
                    'hide_save'      => false,
                ];
            },
            'variants' => [
                ['action' => '',      'get' => '',        'error' => false],
                ['action' => 'new',   'get' => '',        'error' => false],
                ['action' => 'edit',  'get' => '',        'error' => false],
                ['action' => 'edit',  'get' => 'print=1', 'error' => false],
                ['action' => 'build', 'get' => '',        'error' => false],
                ['action' => 'save',  'get' => '',        'error' => true ],
            ],
        ],
        // The form canvas is the page most exposed to this kind of mistake: its
        // whole behaviour is one module script, and a module that fails to parse
        // binds no listener at all, so the editor comes up as a picture of a page
        // that cannot be edited. render_canvas_js.php checks it against the real
        // constants inside the container; this checks that it PARSES, here, where
        // node is.
        'forms_canvas' => [
            'path' => 'trunk/modules/forms/pages/canvas/js_include.php',
            'seed' => function (bool $error) {
                return [
                    'security_level'          => 4,
                    'forms_canvas_id'         => 1,
                    'forms_canvas_definition' => new stdClass(),
                    'forms_canvas_boxes'      => [['index' => 0, 'kind' => 'text', 'x' => 10, 'y' => 20,
                                                   'width' => 30, 'height' => 5, 'label' => 'a box']],
                    'forms_canvas_values'     => [['index' => '0', 'x' => '10', 'y' => '20',
                                                   'w' => '30', 'h' => '5']],
                    'error'                   => $error,
                ];
            },
            // The canvas page has no actions of its own; the script is emitted
            // whenever a definition is open, which the seed above makes true.
            'variants' => [
                ['action' => '', 'get' => '', 'error' => false],
            ],
        ],
    ];
}

/** The repository root, from this file's own position. */
function fb_repo_root(): string { return dirname(__DIR__, 2); }

/**
 * Every bare identifier in the file, sorted into the ones that are called and
 * the ones that are not. Read from PHP's own tokens rather than by pattern, so
 * that `$obj->method(` and `Class::CONST` are not mistaken for either.
 */
function fb_scan_identifiers(string $src): array {
    $tokens = token_get_all($src);
    $funcs = $consts = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING) continue;
        $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
        $j = $i - 1;
        while ($j >= 0 && is_array($tokens[$j]) && in_array($tokens[$j][0], $skip)) $j--;
        if ($j >= 0 && is_array($tokens[$j])
            && in_array($tokens[$j][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CLASS])) continue;
        $k = $i + 1;
        while ($k < $count && is_array($tokens[$k]) && in_array($tokens[$k][0], $skip)) $k++;
        if ($k < $count && $tokens[$k] === '(') $funcs[$tokens[$i][1]] = true;
        else                                    $consts[$tokens[$i][1]] = true;
    }
    return [array_keys($funcs), array_keys($consts)];
}

/**
 * Render one branch of one page. Returns its inline scripts, each with the
 * flavour its <script> tag gave it.
 *
 * The seed hands back its variables rather than setting them, because the
 * include below happens inside this function and therefore in THIS scope: a
 * seed that wrote to $GLOBALS would set variables the page cannot see, and the
 * page would render against nulls without saying so. extract() is what puts
 * them where the page will look. Every local here is prefixed to keep it out of
 * the way of what is extracted over it.
 */
function fb_render_page_js(string $fb_path, string $fb_action, string $fb_query, bool $fb_error, callable $fb_seed): array {
    $fb_full = fb_repo_root() . '/' . $fb_path;
    if (!is_file($fb_full)) throw new RuntimeException("no such page file: $fb_path");

    [$fb_funcs, $fb_consts] = fb_scan_identifiers(file_get_contents($fb_full));
    foreach ($fb_funcs as $fb_f) {
        if (function_exists($fb_f)) continue;
        // Return nothing: a stub that printed a word would put that word in the
        // middle of the javascript, where it is a statement nobody wrote.
        eval("function $fb_f() { return ''; }");
    }
    $fb_reserved = ['true', 'false', 'null', 'array', 'string', 'int', 'float', 'bool',
                    'void', 'mixed', 'object', 'callable', 'iterable', 'self', 'static', 'parent'];
    foreach ($fb_consts as $fb_c) {
        if (defined($fb_c) || in_array(strtolower($fb_c), $fb_reserved, true)) continue;
        define($fb_c, $fb_c . '_VALUE');
    }

    $_REQUEST = ['action' => $fb_action];
    if ($fb_query !== '') { parse_str($fb_query, $fb_extra); $_REQUEST += $fb_extra; }
    $_GET  = $_REQUEST;
    $_POST = [];
    extract($fb_seed($fb_error));

    // Undefined variables and array keys are warnings in PHP 8 and evaluate to
    // null. That is the behaviour wanted here, so let them pass quietly rather
    // than printing a notice into the middle of the script. Set after extract()
    // so that a seed's own mistakes are still reported.
    set_error_handler(function () { return true; });
    ob_start();
    include $fb_full;
    $fb_html = ob_get_clean();
    restore_error_handler();

    return fb_extract_script_blocks($fb_html);
}

// ---------------------------------------------------------------- CLI -------
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    $pages  = fb_page_js_pages();
    $which  = $argv[1] ?? '';
    $action = $argv[2] ?? '';
    $query  = $argv[3] ?? '';
    $out    = $argv[4] ?? '';
    if ($which === '' || (!isset($pages[$which]) && !is_file(fb_repo_root() . '/' . $which))) {
        fwrite(STDERR, "usage: php " . basename(__FILE__) . " <page> [action] [k=v&k=v] [outfile]\n");
        fwrite(STDERR, "pages: " . implode(', ', array_keys($pages)) . " (or a path from the repo root)\n");
        exit(2);
    }
    $error = getenv('FB_POST_FAILED') === '1';
    if (isset($pages[$which])) {
        $blocks = fb_render_page_js($pages[$which]['path'], $action, $query, $error, $pages[$which]['seed']);
    } else {
        $blocks = fb_render_page_js($which, $action, $query, $error, function () {});
    }
    if ($out === '') {
        foreach ($blocks as $i => $b) {
            printf("// ---- block %d (%s) ----\n%s\n", $i, $b['module'] ? 'module' : 'classic', $b['js']);
        }
        exit(0);
    }
    // One file per block, the extension carrying the flavour so that whoever
    // parses it applies the right rules. The names go to stderr, not stdout,
    // so a caller can still pipe the javascript if it wants to.
    foreach ($blocks as $i => $b) {
        $path = sprintf('%s.%d.%s', $out, $i, $b['module'] ? 'mjs' : 'js');
        file_put_contents($path, $b['js']);
        fwrite(STDERR, sprintf("%s\t%d bytes\n", $path, strlen($b['js'])));
    }
    if (!$blocks) fwrite(STDERR, "no inline script in this branch\n");
}
