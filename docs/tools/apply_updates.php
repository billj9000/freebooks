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
//  Path: /docs/tools/apply_updates.php
//
/**
 * THIS WRITES TO THE DATABASE. Bring a company up to the version the code is at.
 *
 * The application does this on the next login, in phreedom_admin::initialize():
 * for every installed module whose STATUS differs from its VERSION it calls the
 * module's update(), which applies that module's migrations and stamps the new
 * status. There is no button for it and no way to ask for it, which is fine when
 * somebody is about to log in and awkward when a database has to be brought
 * current on purpose - before deleting the migrations, for instance, which
 * strands anything left behind.
 *
 * So this is the same loop, without the two things initialize() also does that
 * have no business in a migration: fetching currency rates over the network and
 * phoning home for a version check.
 *
 *   docker exec <c> php /opt/repo/docs/tools/apply_updates.php <company> [--go]
 *
 * Without --go it says what it would do and changes nothing.
 */

$company = $argv[1] ?? '';
$go      = in_array('--go', $argv, true);
if ($company === '') { fwrite(STDERR, "usage: apply_updates.php <company> [--go]\n"); exit(2); }

require_once('/opt/repo/docs/tools/bootstrap_freebooks.php');
fb_bootstrap($company, false);
global $db, $loaded_modules;

$pending = array();
foreach ((array)$loaded_modules as $module) {
    if ($module === 'phreedom') continue;
    $status  = 'MODULE_' . strtoupper($module) . '_STATUS';
    $version = 'MODULE_' . strtoupper($module) . '_VERSION';
    if (!defined($status) || !defined($version)) continue;
    if ((string)constant($status) === (string)constant($version)) continue;
    $pending[$module] = array((string)constant($status), (string)constant($version));
}

if (!$pending) { echo "$company is already at the version the code is at.\n"; exit(0); }

printf("%s has %d module(s) behind:\n", $company, count($pending));
foreach ($pending as $m => $v) printf("  %-14s %s -> %s\n", $m, $v[0], $v[1]);

if (!$go) { echo "\nnothing changed. Pass --go to apply.\n"; exit(0); }

echo "\napplying:\n";
foreach ($pending as $module => $v) {
    require_once(DIR_FS_MODULES . $module . '/classes/install.php');
    $class = $module . '_admin';
    $inst  = new $class;
    // update() may need constants the module declares but this company has never
    // had written, which is what initialize() does before calling it.
    if (!empty($inst->keys)) foreach ($inst->keys as $key => $value) {
        if (!defined($key)) write_configure($key, $value);
    }
    try {
        if (method_exists($inst, 'update')) $inst->update($module);
        printf("  %-14s done\n", $module);
    } catch (Throwable $e) {
        printf("  %-14s FAILED: %s\n", $module, $e->getMessage());
    }
}

// Read the stamps back from the database rather than from the constants loaded
// at the start of this process, which are now stale.
echo "\nwhere it ended up:\n";
foreach (array_keys($pending) as $module) {
    $key = 'MODULE_' . strtoupper($module) . '_STATUS';
    $r = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION
                    . " where configuration_key = '" . db_input($key) . "'");
    printf("  %-14s %s\n", $module, $r->EOF ? '(no status row)' : $r->fields['configuration_value']);
}
