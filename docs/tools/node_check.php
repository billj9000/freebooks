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
//  Path: /docs/tools/node_check.php
//
/**
 * Ask node whether a piece of javascript parses, and pull the pieces out of a
 * rendered page in the first place.
 *
 * Shared by render_canvas_js.php and verify_page_javascript.php so that the two
 * cannot drift on the part that is easy to get subtly wrong: WHICH FLAVOUR the
 * script is. `<script type="module">` and a plain `<script>` are different
 * languages to a parser. A module may `import` and may `await` at the top
 * level; a classic script may do neither, and node decides which set of rules
 * to apply from the FILE EXTENSION — .mjs or .js. Check a module as a .js and
 * node rejects the import, reporting a syntax error in code that is perfectly
 * good. That false alarm is worse than no check at all, so the flavour is
 * carried from the <script> tag all the way to the temporary file's name.
 *
 * node is optional. Callers are expected to degrade rather than fail when it is
 * absent — it is not, for instance, in the application container — and to say
 * "skipped" rather than "passed" when they do.
 */

/** node's path, or null if this machine has none. */
function fb_find_node(): ?string {
    $out = [];
    exec('command -v node 2>/dev/null', $out, $status);
    if ($status === 0 && !empty($out[0])) return trim($out[0]);
    // Debian installs it as nodejs when the `node` name is taken.
    $out = [];
    exec('command -v nodejs 2>/dev/null', $out, $status);
    return ($status === 0 && !empty($out[0])) ? trim($out[0]) : null;
}

function fb_node_version(string $node): string {
    $out = [];
    exec(escapeshellarg($node) . ' --version 2>&1', $out);
    return trim($out[0] ?? '?');
}

/**
 * Parse $source with node. $is_module picks the rules, via the extension.
 *
 * `node --check` parses and stops: it does not run the code, and it does not
 * resolve an import, so a module that imports a file only a browser can reach
 * is checked here perfectly well.
 *
 * @return array{ok: bool, error: string}
 */
function fb_node_check(string $node, string $source, bool $is_module): array {
    static $seq = 0;
    $path = sprintf('%s/fb_node_check_%d_%d.%s', sys_get_temp_dir(), getmypid(), $seq++,
                    $is_module ? 'mjs' : 'js');
    if (file_put_contents($path, $source) === false) {
        return ['ok' => false, 'error' => 'could not write ' . $path];
    }
    $out = [];
    exec(escapeshellarg($node) . ' --check ' . escapeshellarg($path) . ' 2>&1', $out, $status);
    @unlink($path);
    if ($status === 0) return ['ok' => true, 'error' => ''];

    // node prints the offending line, a caret, then the message. The message is
    // the useful part; the temporary path in it is noise.
    $lines = array_values(array_filter(array_map('trim', $out), fn($l) => $l !== ''));
    $lines = array_map(fn($l) => str_replace($path, '', $l), $lines);
    $said  = '';
    foreach ($lines as $l) {
        if (preg_match('/^(SyntaxError|ReferenceError|TypeError):/', $l)) { $said = $l; break; }
    }
    if ($said === '') $said = implode(' ', array_slice($lines, 0, 3));
    // Where in the script, if node said.
    $where = '';
    foreach ($lines as $l) if (preg_match('/^:(\d+)$/', $l, $m)) { $where = " at line {$m[1]}"; break; }
    return ['ok' => false, 'error' => $said . $where];
}

/**
 * The inline scripts in a rendered page, each with its flavour.
 *
 * A page may emit more than one, and they need not agree: the form canvas emits
 * a classic script for the document actions and then a module for the canvas
 * itself. Joining them into one string — which is what a regex that strips to
 * the FIRST <script> does — leaves a </script> in the middle of the javascript
 * and makes the whole thing unparseable. So they come back separately.
 *
 * A <script src="..."> is skipped: it has a file of its own that node can be
 * pointed at directly.
 *
 * @return list<array{module: bool, js: string}>
 */
function fb_extract_script_blocks(string $html): array {
    $blocks = [];
    if (!preg_match_all('#<script\b([^>]*)>(.*?)</script>#si', $html, $m, PREG_SET_ORDER)) return $blocks;
    foreach ($m as $hit) {
        [$_, $attrs, $body] = $hit;
        if (preg_match('#\bsrc\s*=#i', $attrs)) continue;
        if (trim($body) === '') continue;
        // The old HTML comment wrapper some of these pages still carry.
        $body = preg_replace('#^\s*<!--|-->\s*$#m', '', $body);
        $blocks[] = ['module' => (bool)preg_match('#type\s*=\s*[\'"]module[\'"]#i', $attrs), 'js' => $body];
    }
    return $blocks;
}
