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
//  Path: /docs/tools/compare_schema.php
//
/**
 * Does a fresh install produce the schema an upgraded database actually has?
 *
 * Today it does not, and that is the release blocker: columns exist in every one
 * of our databases that a new installation never creates, because only the update
 * chain ever added them and the base CREATE TABLE was never kept in step. A fresh
 * install stamps MODULE_X_STATUS straight to MODULE_X_VERSION, so the per-login
 * cascade that would apply those migrations sees nothing to do, and never will.
 * The columns simply never arrive.
 *
 * This is the acceptance test for putting that right. It builds a schema the way
 * a new installation does - every module's $this->tables, then its install() -
 * into a scratch database, and compares it column by column against a reference
 * company. When the two agree the baseline is correct and the update chain can be
 * deleted. Until then this prints the work remaining.
 *
 * Names and types are compared, not rows: demo data is not the question here.
 *
 *   docker exec <c> php /opt/repo/docs/tools/compare_schema.php [reference] [--v]
 *
 * Needs fb-testdb running. The scratch database is created and dropped here.
 */

$argv      = $argv ?? array();
$verbose   = in_array('--v', $argv, true);
$reference = 'demo';
foreach (array_slice($argv, 1) as $a) if ($a !== '--v') { $reference = $a; break; }
$webroot   = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$scratch   = 'fb_baseline_probe';

// ---- the scratch server ---------------------------------------------------
$probe = new PDO('mysql:host=fb-testdb', 'root', 'testroot');
$probe->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$probe->exec("DROP DATABASE IF EXISTS `$scratch`");
$probe->exec("CREATE DATABASE `$scratch` DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$probe->exec("USE `$scratch`");

// ---- a database object the install classes will accept --------------------
// They expect queryFactory's shape, not PDO's. Failures are collected rather
// than fatal: one bad statement must not hide the twenty behind it.
class probeResult {
    public $fields = array(); public $EOF = true;
    private $rows = array(); private $i = 0;
    function __construct($rows = array()) {
        $this->rows = $rows; $this->EOF = !count($rows);
        $this->fields = $this->EOF ? array() : $rows[0];
    }
    function MoveNext() { $this->i++;
        if ($this->i >= count($this->rows)) { $this->EOF = true; $this->fields = array(); }
        else $this->fields = $this->rows[$this->i]; }
    function RecordCount() { return count($this->rows); }
}
class probeDb {
    public $failures = array(); public $ok = 0; public $db_connected = true; public $pdo;
    function __construct($pdo) { $this->pdo = $pdo; }
    function Execute($sql, $a = false, $b = false, $c = 0) {
        try {
            $st = $this->pdo->query($sql); $this->ok++;
            $rows = ($st && $st->columnCount() > 0) ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
            return new probeResult($rows);
        } catch (Throwable $e) {
            $this->failures[] = array('sql' => preg_replace('/\s+/', ' ', substr(trim($sql), 0, 120)),
                                      'err' => $e->getMessage());
            return new probeResult();
        }
    }
    function insert_ID()  { return $this->pdo->lastInsertId(); }
    function show_error() { return ''; }
    function connect()    { return true; }
}

require_once('/opt/repo/docs/tools/bootstrap_freebooks.php');
$company = fb_bootstrap($reference, false);
$real    = $GLOBALS['db'];                    // the reference company's connection
$fake    = new probeDb($probe);

// ---- build the schema a new installation would get ------------------------
// Order matters and alphabetical is wrong. Optional modules alter tables the core
// ones own - several add their own counter to current_status - so running amazon
// before phreedom asks to alter a table that does not exist yet. The installer
// does the core first; so does this.
$core = array('phreedom', 'phreebooks', 'contacts', 'inventory', 'shipping',
              'payment', 'forms', 'labels', 'api', 'phreepos', 'phreehelp');
$extra = array();
foreach (scandir(DIR_FS_MODULES) as $e) {
    if ($e[0] === '.' || !is_dir(DIR_FS_MODULES . $e)) continue;
    if (!file_exists(DIR_FS_MODULES . $e . '/classes/install.php')) continue;
    if (!in_array($e, $core, true)) $extra[] = $e;
}
sort($extra);
$modules = array();
foreach (array_merge($core, $extra) as $m) {
    if (file_exists(DIR_FS_MODULES . $m . '/classes/install.php')) $modules[] = $m;
}

$skipped = array();
$GLOBALS['db'] = $db = $fake;                 // everything below writes to the scratch
$built = 0;
foreach ($modules as $mod) {
    // install() writes user-facing strings - log entries, tab titles - so the
    // module's language file has to be loaded first, exactly as the installer
    // does it. Without this, four installers died on an undefined constant and
    // took their whole contribution with them.
    if (function_exists('gen_pull_language')) { @gen_pull_language($mod); @gen_pull_language($mod, 'admin'); }
    if (file_exists(DIR_FS_MODULES . $mod . '/defaults.php')) @include_once DIR_FS_MODULES . $mod . '/defaults.php';
    try { require_once DIR_FS_MODULES . $mod . '/classes/install.php'; } catch (Throwable $e) { continue; }
    $class = $mod . '_admin';
    if (!class_exists($class)) { $skipped[] = $mod; continue; }
    // A module the reference company has not installed never had its config.php
    // loaded, so its TABLE_ constants do not exist and its installer cannot be
    // built. Its tables are then absent from the fresh install for a reason that
    // has nothing to do with the baseline, and saying so saves the next person
    // chasing five tables that were never missing.
    try { $inst = new $class; } catch (Throwable $e) { $skipped[] = $mod; continue; }
    if (is_array($inst->tables ?? null)) foreach ($inst->tables as $sql) { $fake->Execute($sql); $built++; }
    // install() is where the guarded ALTERs live - the per-module counters on
    // current_status, and the columns one module adds to another's table. A
    // comparison that runs only $this->tables would report those as missing and
    // send someone to fix what is not broken.
    // Swallowing what install() throws is how a module's whole contribution
    // disappears without trace: zencart adds six columns to inventory and all
    // six were reported missing because its installer had died on line one.
    if (method_exists($inst, 'install')) {
        try { $inst->install($mod); }
        catch (Throwable $e) {
            $fake->failures[] = array('sql' => $mod . '_admin::install()',
                                      'err' => get_class($e) . ': ' . $e->getMessage());
        }
    }
}
$GLOBALS['db'] = $db = $real;                 // back to the reference for reading

// ---- compare --------------------------------------------------------------
/**
 * Each column as "type/collation". The collation has to be in there: charset is
 * not part of column_type, so a table built utf8mb3 and one built utf8mb4 look
 * identical to a comparison that reads the type alone - and the charset is half
 * of what 1.0.0 is changing. A column with no collation (a number, a date)
 * compares as its type only.
 */
function columns(PDO $pdo, $schema) {
    $out = array();
    // Nullability and the default belong here too. column_type is "date" whether
    // the column accepts NULL or not, so a comparison reading the type alone
    // cannot see the change from NOT NULL DEFAULT '0000-00-00' to NULL - which
    // is the other half of what 1.0.0 does to this schema. It compared charset
    // for the same reason, after the same oversight.
    $st = $pdo->prepare("select table_name, column_name, column_type, collation_name,
                                is_nullable, column_default
                         from information_schema.columns where table_schema = ?");
    $st->execute(array($schema));
    foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) {
        $out[strtolower($r[0])][strtolower($r[1])] =
            $r[2] . ($r[3] ? ' / ' . $r[3] : '')
                  . ($r[4] === 'YES' ? ' NULL' : ' NOT NULL')
                  . ' default ' . ($r[5] === null ? 'NULL' : $r[5]);
    }
    return $out;
}
$fresh_cols = columns($probe, $scratch);

// Columns someone added through the UI are not schema. The extra-fields
// mechanism writes a real column and registers it in xtra_fields against a tab
// it created, and that column exists on THAT installation and no other - by
// design. A fresh install not having it is correct, and counting it as drift
// would send someone to write another installation's custom field into the
// baseline. (Which happened: custom_contact_field went in and had to come out.)
//
// Only fields on a created tab count. xtra_fields also lists base columns
// against tab 0, for display, and those are schema like any other. A field a
// module's installer creates never reaches this test anyway, because the fresh
// install has it.
$custom = array();

require $webroot . '/my_files/' . $company . '/config.php';
$refpdo = new PDO('mysql:host=' . DB_SERVER_HOST . ';dbname=' . $company,
                  DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
$ref_cols = columns($refpdo, $company);
try {
    foreach ($refpdo->query("select field_name from xtra_fields where tab_id > 0")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $custom[strtolower($f)] = true;
    }
} catch (Throwable $e) { /* no xtra_fields: nothing to exclude */ }

printf("fresh install: %d statements, %d tables, %d failures\n", $built, count($fresh_cols), count($fake->failures));
printf("reference '%s': %d tables\n\n", $company, count($ref_cols));

$only_ref = array_diff_key($ref_cols, $fresh_cols);
$only_new = array_diff_key($fresh_cols, $ref_cols);
if ($only_ref) {
    printf("tables the reference has and a fresh install does not (%d):\n  %s\n",
           count($only_ref), implode(', ', array_keys($only_ref)));
    if ($skipped) printf("  (this company has not installed: %s - their tables are absent for that reason)\n",
                         implode(', ', array_unique($skipped)));
    echo "\n";
}
if ($only_new) printf("tables a fresh install creates and the reference does not (%d):\n  %s\n\n",
                      count($only_new), implode(', ', array_keys($only_new)));

$missing = $extra = $differ = 0; $custom_seen = array();
foreach (array_intersect_key($ref_cols, $fresh_cols) as $t => $cols) {
    $m = array_diff_key($cols, $fresh_cols[$t]);
    foreach (array_keys($m) as $c) if (isset($custom[$c])) { $custom_seen[$t][] = $c; unset($m[$c]); }
    $x = array_diff_key($fresh_cols[$t], $cols);
    $d = array();
    foreach (array_intersect_key($cols, $fresh_cols[$t]) as $c => $type) {
        if (strtolower($type) !== strtolower($fresh_cols[$t][$c])) $d[$c] = "$type vs {$fresh_cols[$t][$c]}";
    }
    if (!$m && !$x && !$d) continue;
    $missing += count($m); $extra += count($x); $differ += count($d);
    printf("  %s\n", $t);
    if ($m) printf("      a fresh install never creates: %s\n", implode(', ', array_keys($m)));
    if ($x) printf("      only a fresh install has:      %s\n", implode(', ', array_keys($x)));
    if ($d && $verbose) foreach ($d as $c => $why) printf("      %-22s %s\n", $c, $why);
    elseif ($d) printf("      %d column(s) of a different type (--v to list)\n", count($d));
}

if ($custom_seen) {
    $n = 0; foreach ($custom_seen as $cs) $n += count($cs);
    printf("\n%d column(s) are custom fields added through the UI, not schema - correctly absent from a fresh install:\n", $n);
    foreach ($custom_seen as $t => $cs) printf("  %-22s %s\n", $t, implode(', ', $cs));
}
printf("\n%d columns missing from a fresh install, %d only in one, %d typed differently.\n",
       $missing, $extra, $differ);
// The verdict must count the tables too. It did not, and said the two agreed
// while three tables were missing outright - a false pass of exactly the kind
// this tool exists to catch.
$agree = !$missing && !$extra && !$differ && !$only_ref && !$only_new;
if (!$agree && ($only_ref || $only_new)) {
    printf("%d table(s) exist on one side only.\n", count($only_ref) + count($only_new));
}
echo $agree
   ? "A fresh install and an upgraded database agree.\n"
   : "The baseline does not yet reproduce a working database.\n";
exit($agree ? 0 : 1);

if ($fake->failures && $verbose) {
    echo "\nstatements the fresh install could not run:\n";
    foreach (array_slice($fake->failures, 0, 15) as $f) printf("  %s\n      %s\n", $f['err'], $f['sql']);
}
$probe->exec("DROP DATABASE IF EXISTS `$scratch`");
