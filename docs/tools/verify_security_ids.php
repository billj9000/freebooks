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
//  Path: /docs/tools/verify_security_ids.php
//
/**
 * Every permission the application demands can be granted. See §13.38.
 *
 *   php docs/tools/verify_security_ids.php
 *
 * ## Why
 *
 * Company -> Roles is built from `$mainmenu`. A security id that no menu entry
 * carries therefore has **no row on that page**, and an id with no row can be
 * seeded by an upgrade and then never changed by anyone — not granted, not
 * revoked, not seen.
 *
 * That is not hypothetical. Three dashboards used SECURITY_ID_PHREEFORM as
 * their key, and moving them to one of their own (§13.38) would have created
 * exactly that hole: the migration copies each role's level across, and after
 * that nobody could ever have altered it, because dashboards are not menu
 * entries and the roles page had nothing to draw.
 *
 * ## The rule
 *
 * Every id the code DEMANDS — `validate_user(SECURITY_ID_X)`, a dashboard's
 * `$security_id` — must be one the roles page can OFFER. Source-only: this is
 * about what ships, not about what one company's roles happen to hold.
 *
 * Menu entries hidden with `show_in_users_settings => false` are excluded from
 * the offered set on purpose. That flag means "do not draw a row for this", and
 * an id that only ever appears behind it is unreachable in exactly the way this
 * tool is looking for.
 */

$root = dirname(__DIR__, 2) . '/trunk';
$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

/** @return list<string> */
function phpFiles(string $dir): array {
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') $out[] = $f->getPathname();
    }
    sort($out);
    return $out;
}

// ── what the roles page can offer ───────────────────────────────────────────
//
// A menu entry's id, unless the entry is explicitly hidden from the settings
// page; plus whatever the roles page adds for permissions that belong to no
// menu entry, which is the mechanism this check exists to keep honest.
$offered = [];
foreach (phpFiles($root . '/modules') as $path) {
    if (!str_ends_with($path, '/config.php')) continue;
    $src = (string)file_get_contents($path);
    // Each entry is an array literal; a hidden one carries the flag inside the
    // same literal, so the two are read together rather than line by line.
    foreach (preg_split('/\barray\s*\(/', $src) as $chunk) {
        if (!preg_match("/'security_id'\s*=>\s*(SECURITY_ID_[A-Z_0-9]+)/", $chunk, $m)) continue;
        if (preg_match("/'show_in_users_settings'\s*=>\s*(false|FALSE)/", $chunk)) continue;
        $offered[$m[1]] = true;
    }
}
$extra = (string)@file_get_contents($root . '/modules/phreedom/pages/roles/pre_process.php');
if (preg_match_all("/'security_id'\s*=>\s*(SECURITY_ID_[A-Z_0-9]+)/", $extra, $m)) {
    foreach ($m[1] as $name) $offered[$name] = true;
}

// ── what the code demands ───────────────────────────────────────────────────
$demanded = [];
foreach (phpFiles($root . '/modules') as $path) {
    $where = str_replace($root . '/modules/', '', $path);
    $src   = (string)file_get_contents($path);
    foreach ([
        '/\bvalidate_user\s*\(\s*(SECURITY_ID_[A-Z_0-9]+)/'          => 'a page',
        '/\bvalidate_ajax_user\s*\(\s*(SECURITY_ID_[A-Z_0-9]+)/'     => 'an ajax op',
        '/public\s+\$security_id\s*=\s*(SECURITY_ID_[A-Z_0-9]+)/'    => 'a dashboard',
    ] as $pattern => $kind) {
        if (!preg_match_all($pattern, $src, $m)) continue;
        foreach ($m[1] as $name) $demanded[$name][] = "$kind ($where)";
    }
}

printf("%d permission(s) demanded, %d offered on the roles page\n\n",
    count($demanded), count($offered));

echo "=== every permission the code demands can be granted ===\n";
$unreachable = [];
foreach ($demanded as $name => $where) {
    if (isset($offered[$name])) continue;
    $unreachable[] = $name . ' — ' . $where[0];
}
ok('no permission is demanded that the roles page cannot offer',
   $unreachable === [], implode("\n         ", $unreachable));

if ($unreachable === []) {
    printf("  %d demanded, all reachable\n", count($demanded));
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
