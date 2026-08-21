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
//  Path: /docs/tools/verify_forms_migration.php
//
/**
 * The bulk migration, and above all what its rollback may touch. See §13.32.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_migration.php [company]
 *
 * ## Why this exists
 *
 * `--rollback` restored **every** `pf_N.pre-forms` in its first version, and
 * that is not the set the migration wrote. A definition saved in the Forms
 * editor also has one — its PhreeForm original — so restoring it does not undo
 * a migration, it discards the author's work. Run against a company where five
 * documents had been edited that way, it reverted all five, silently, and the
 * native files it overwrote were gone.
 *
 * The scoping that replaced it is a manifest written by `--write` and read by
 * `--rollback`. That is one variable standing between a safety net and a
 * destructive operation, and it fails the way the original did: quietly, on a
 * company whose history nobody is looking at. So it is pinned here.
 *
 * ## How it can test a destructive tool safely
 *
 * The whole report directory is copied aside before anything runs and copied
 * back afterwards — from a shutdown function, so it happens on a fatal and on a
 * refusal as well as on success. The tool under test is then run for real,
 * against a real company, which is the only way to test the thing that actually
 * ships rather than a re-implementation of it that could agree with the code
 * while both are wrong.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/defaults.php');

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; return; }
    $failures++;
    echo "  [FAIL] $what" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$dir  = rtrim(FORMS_DIR_DEFINITIONS, '/\\');
$tool = __DIR__ . '/migrate_forms_definitions.php';

if (!is_dir($dir)) { echo "  [--] no report directory on this company\n"; exit(0); }

// ── the safety net, installed before anything can change ────────────────────
//
// A shutdown function rather than a try/finally: the tool under test is run in
// a child process, but this script can still die on an assertion that throws,
// and a verifier that leaves a company's definitions in a half-migrated state
// would be worse than no verifier.
/** Every pf_ file and its bytes, which is what "unchanged" has to mean. */
function snapshot(string $dir): array {
    $out = [];
    foreach (glob("$dir/pf_*") ?: [] as $path) $out[basename($path)] = (string)md5_file($path);
    ksort($out);
    return $out;
}

// Through the shared snapshot, which restores OWNERSHIP as well as bytes. This
// tool ran as root under `docker exec` and put every definition back owned by
// root, which the web server cannot then write — a verifier proving the
// definitions were untouched, by making them unwritable. See fb_snapshot_tree().
$snapshot = fb_snapshot_tree($dir);
register_shutdown_function(function () use ($snapshot) {
    fb_restore_tree($snapshot);
    echo "\n  [--] the report directory was restored exactly as it was found\n";
});

/** Run the tool and hand back its output. */
function run(string $tool, string $company, array $flags = []): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tool)
             . ' ' . escapeshellarg($company);
    foreach ($flags as $flag) $command .= ' ' . escapeshellarg($flag);
    $lines = []; $status = 0;
    exec($command . ' 2>&1', $lines, $status);
    return [implode("\n", $lines), $status];
}

$manifest = $dir . '/forms-migration.json';

// ── put the company into the state worth testing ────────────────────────────
//
// A company that is already migrated has nothing to convert, so the tool exits
// before it writes a manifest and every check below is vacuous. The fixture is
// therefore built rather than hoped for: some definitions are put BACK to their
// PhreeForm original, and at least one native one with an original beside it is
// left alone — because that combination, a definition saved in the Forms
// editor, is exactly what the first rollback destroyed and exactly what must
// survive this one.
//
// Safe to do because the whole directory was copied aside above and is restored
// by the shutdown function whatever happens next.
$madeLegacy = [];
$leftNative = [];
foreach (glob("$dir/pf_*.pre-forms") ?: [] as $kept) {
    $id   = (int)substr(basename($kept), 3);
    $path = "$dir/pf_$id";
    if (!is_file($path)) continue;

    $orig = ltrim((string)file_get_contents($kept, false, null, 0, 32));
    if ($orig === '' || $orig[0] === '{') continue;   // no real PhreeForm original

    // Keep two aside as the "saved in the editor" case; make the rest legacy.
    if (count($leftNative) < 2) { $leftNative[] = $id; continue; }
    if (count($madeLegacy) >= 6) continue;
    if (@copy($kept, $path)) $madeLegacy[] = $id;
}

$before = snapshot($dir);
echo "company '$company', " . count($before) . " definition file(s)\n";
printf("  fixture: %d put back to PhreeForm XML, %d left native with an original beside them\n\n",
    count($madeLegacy), count($leftNative));

if ($madeLegacy === []) {
    echo "  [--] nothing on this company could be put back to legacy, so there is\n"
       . "       nothing for a migration to convert and nothing to test.\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
echo "=== a dry run changes nothing ===\n";
@unlink($manifest);
[$out] = run($tool, $company);
ok('the dry run says so', str_contains($out, 'Nothing was changed'), $out);
ok('and no file moved', snapshot($dir) === $before);

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a rollback with no manifest refuses, and touches nothing ===\n";
//
// THE check. Without a manifest there is no record of what this tool converted,
// and the alternative — restoring every original it can find — is not a
// rollback but a revert of everybody's editor saves.
[$out, $status] = run($tool, $company, ['--rollback', '--write']);
ok('it refuses', $status !== 0, "exit status $status");
ok('and says why, in terms of what would be lost',
   str_contains($out, 'discards the author') || str_contains($out, 'no migration manifest'), $out);
ok('and no file moved', snapshot($dir) === $before,
   'a refusal that still writes is the worst of both');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a migration records exactly what it converted ===\n";

// What was ALREADY native going in. These are the ones the first rollback ate:
// a definition saved in the editor is native with its PhreeForm original beside
// it, which looks exactly like a migrated one and must not be rolled back.
$nativeBefore = [];
foreach (array_keys($before) as $name) {
    if (str_ends_with($name, '.pre-forms')) continue;
    $head = ltrim((string)file_get_contents("$dir/$name", false, null, 0, 32));
    if ($head !== '' && $head[0] === '{') $nativeBefore[] = (int)substr($name, 3);
}

[$out, $status] = run($tool, $company, ['--write']);
ok('the migration runs', $status === 0, $out);

if (!is_file($manifest)) {
    ok('it leaves a manifest', false, 'no forms-migration.json was written');
} else {
    ok('it leaves a manifest', true);
    $recorded = json_decode((string)file_get_contents($manifest), true);
    $ids = array_map('intval', (array)($recorded['converted'] ?? []));

    ok('the manifest names at least one definition', $ids !== []);
    // The invariant the incident turned on.
    $wrongly = array_intersect($ids, $nativeBefore);
    ok('and names nothing that was already native before it ran', $wrongly === [],
       'would roll back an editor save: pf_' . implode(', pf_', $wrongly));

    // Everything it claims must actually be native now, or the manifest is a
    // record of something that did not happen.
    $notNative = [];
    foreach ($ids as $id) {
        $head = ltrim((string)@file_get_contents("$dir/pf_$id", false, null, 0, 32));
        if ($head === '' || $head[0] !== '{') $notNative[] = $id;
    }
    ok('and everything it names is native on disk', $notNative === [],
       'pf_' . implode(', pf_', $notNative));

    // And an original was kept for each, or the rollback has nothing to use.
    $unkept = [];
    foreach ($ids as $id) if (!is_file("$dir/pf_$id.pre-forms")) $unkept[] = $id;
    ok('and each has its original kept beside it', $unkept === [],
       'pf_' . implode(', pf_', $unkept));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a rollback restores what it converted, and only that ===\n";

$afterMigration = snapshot($dir);
$untouched = [];
foreach ($nativeBefore as $id) {
    if (isset($before["pf_$id"], $afterMigration["pf_$id"])) {
        $untouched["pf_$id"] = $before["pf_$id"];
    }
}
ok('a definition that was already native was left alone by the migration',
   array_intersect_key($afterMigration, $untouched) == $untouched,
   'the migration rewrote something it should have skipped');

[$out, $status] = run($tool, $company, ['--rollback', '--write']);
ok('the rollback runs', $status === 0, $out);

$afterRollback = snapshot($dir);

// The one that was native before must STILL be native and byte-identical — it
// was never this migration's to undo. This is the check that would have caught
// the incident.
$harmed = [];
foreach ($untouched as $name => $hash) {
    if (($afterRollback[$name] ?? null) !== $hash) $harmed[] = $name;
}
ok('and left every definition it did not convert exactly as it was',
   $harmed === [], 'reverted an editor save: ' . implode(', ', $harmed));

// The round trip itself: everything is back where it started.
ok('the company is byte-for-byte as it was before the migration',
   $afterRollback === $before,
   count(array_diff_assoc($afterRollback, $before)) . ' file(s) differ');

ok('and the manifest is gone, so a second rollback has nothing to repeat',
   !is_file($manifest));

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
