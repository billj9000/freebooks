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
//  Path: /docs/tools/verify_configure_writes.php
//
/**
 * A runtime setting is written by NAME, never by the constant itself.
 *
 * `write_configure($constant, $value)` takes the configuration KEY as a string.
 * Handing it the constant instead hands it the constant's VALUE, and the two
 * failures that follow are both silent:
 *
 *   - a falsy value ('0', '', 0) hits the function's own `if (!$constant)`
 *     guard, so it returns false and writes NOTHING — and that is exactly the
 *     case such a line usually exists for, turning a flag ON when it is off;
 *   - any other value creates or updates a configuration row keyed by that
 *     value, so the settings table quietly grows a row called '1'.
 *
 * Found in phreehelp and phreedom, both writing PHREEHELP_FORCE_RELOAD — the
 * flag that makes the Help manual reindex. Neither ever fired, which is why a
 * new or edited manual topic stayed invisible until somebody set the flag by
 * hand, and why the documentation's claim that "module updates set the flag
 * automatically" had never been true.
 *
 * Source only: no database, no application bootstrap. The whole point is that
 * the fault is invisible at runtime, so there is nothing to observe — the
 * shape of the call is the only evidence there is.
 *
 *     php docs/tools/verify_configure_writes.php
 */

$repo  = dirname(__DIR__, 2);
$roots = [$repo . '/trunk', $repo . '/extensions'];

$failures = 0;
function ok(string $what, bool $passed, string $detail = ''): void
{
    global $failures;
    if (!$passed) $failures++;
    printf("  [%s] %s%s\n", $passed ? 'ok' : 'FAIL', $what,
        $passed || $detail === '' ? '' : " — $detail");
}

/**
 * A first argument that is a bare UPPERCASE identifier.
 *
 * Deliberately narrow. `write_configure($key, $value)` with a variable is
 * correct and common — the caller holds the name. So is a built one, like
 * `write_configure('MODULE_' . strtoupper($module) . '_STATUS', ...)`. Only an
 * unquoted constant-shaped token is the mistake, and matching just that keeps
 * this from becoming a check people learn to ignore.
 */
$pattern = '/write_configure\s*\(\s*([A-Z][A-Z0-9_]*)\s*,/';

$offenders = [];
$scanned   = 0;
foreach ($roots as $root) {
    if (!is_dir($root)) continue;
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($walk as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $path = $file->getPathname();
        // Vendored code is not ours to hold to this.
        if (str_contains($path, '/vendor/') || str_contains($path, '/jodit/')) continue;
        $scanned++;
        $body = (string)file_get_contents($path);
        if (!str_contains($body, 'write_configure')) continue;
        foreach (explode("\n", $body) as $n => $line) {
            if (preg_match($pattern, $line, $found)) {
                $offenders[] = sprintf('%s:%d passes %s',
                    str_replace($repo . '/', '', $path), $n + 1, $found[1]);
            }
        }
    }
}

printf("  [--] %d php file(s) scanned\n", $scanned);
ok('every write_configure() names its setting as a string', $offenders === [],
   implode('; ', array_slice($offenders, 0, 6))
   . ' — a bare constant passes its VALUE, and a falsy one writes nothing at all');

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
