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
//  Path: /docs/tools/bootstrap_freebooks.php
//
/**
 * Boot enough of FreeBooks for a CLI dev tool, without a web request.
 *
 * NOT part of the application. Shared by the Forms migration tools so the
 * bootstrap exists once: it has to mirror application_top.php's exact ordering,
 * and every deviation shows up as an undefined SECURITY_ID_* / MENU_HEADING_* /
 * PF_FONT_* constant partway through a run.
 *
 * Usage:
 *   $company = fb_bootstrap($argv[4] ?? null);
 *
 * Company resolution: the argument, else FREEBOOKS_COMPANY, else the first
 * company under my_files/. Which one you land on is load-bearing - a baseline
 * taken from the wrong database still renders plausible-looking output - so the
 * resolved company is always echoed, and an auto-detected one warns.
 *
 * Webroot: FREEBOOKS_WEBROOT, else /var/www/html.
 *
 * Note: display_errors is Off in the container image, so CLI fatals are
 * invisible. Always invoke tools with -d display_errors=1.
 *
 * Sets up the globals the legacy code reaches for: $db, $currencies,
 * $messageStack, $loaded_modules, and everything the module defaults.php files
 * define — see fb_require_globals() for why that last part needs saying.
 */

/**
 * require() a legacy file so the variables it defines end up GLOBAL.
 *
 * PHP evaluates an include in the variable scope of whatever ran it, so a
 * `require` of phreeform/defaults.php from INSIDE this function makes every
 * `$var` in that file a local of fb_bootstrap() that vanishes on return.
 * application_top.php has no such problem because it runs at file scope.
 *
 * This is not a tidiness point. The legacy engines read those arrays back with
 * `global`, and an absent global is not an error — it is a silently DIFFERENT
 * ANSWER. build_criteria() looks up `$CritChoices[$filter->type]` to find the
 * default comparison mode; with $CritChoices unset it falls through to its
 * `default:` case and emits no criteria at all, so Sales By SKU came back with
 * every journal in the table (4,707 rows) instead of the two its own IN_LIST
 * filter names (647). A tool comparing engines then reports a parity gap that
 * exists only in the harness.
 *
 * Restoring file-scope behaviour takes BOTH halves, and the read half matters
 * as much as the write half: phreeform/defaults.php guards its hook-loading
 * loop with `isset($loaded_modules)`, so a file that can only write globals but
 * not see them quietly skips every module's pf_process_* hooks. Hence the
 * extract() in, and the promotion out.
 *
 * Note this must be the ONLY route by which these files are loaded:
 * `require_once` on an already-loaded file returns without defining anything,
 * so a stray direct require elsewhere would re-open the same hole.
 */
function fb_require_globals(string $fb_include_path): void
{
    // Copied by name rather than by extract(), which would try to rebind the
    // $GLOBALS superglobal this function still needs on the way out.
    foreach ($GLOBALS as $fb_key => $fb_value) {
        if ($fb_key === 'GLOBALS' || $fb_key === 'fb_include_path') continue;
        $$fb_key = $fb_value;
    }
    unset($fb_key, $fb_value);
    require_once($fb_include_path);
    foreach (get_defined_vars() as $fb_name => $fb_value) {
        if ($fb_name === 'fb_include_path') continue;
        $GLOBALS[$fb_name] = $fb_value;
    }
}

/**
 * @param  string|null $company preferred company; null to auto-detect
 * @param  bool        $withPhreeForm load the phreeform engine too
 * @return string      the company that was booted
 */
function fb_bootstrap(?string $company = null, bool $withPhreeForm = true): string
{
    global $db, $currencies, $messageStack, $loaded_modules;

    if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
    if (!defined('PAGE_EXECUTION_START_TIME')) define('PAGE_EXECUTION_START_TIME', microtime(true));
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

    $fs_admin = rtrim(getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html', '/') . '/';
    if (!file_exists($fs_admin . 'includes/configure.php')) {
        fwrite(STDERR, "No configure.php under $fs_admin - set FREEBOOKS_WEBROOT.\n");
        exit(1);
    }
    fb_require_globals($fs_admin . 'includes/configure.php');

    if (!defined('DIR_FS_ADMIN'))     define('DIR_FS_ADMIN', $fs_admin);
    if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');
    define('DIR_WS_MODULES',  'modules/');
    define('DIR_WS_MY_FILES', PATH_TO_MY_FILES);
    define('DIR_FS_INCLUDES', DIR_FS_ADMIN . 'includes/');
    define('DIR_FS_MODULES',  DIR_FS_ADMIN . 'modules/');
    define('DIR_FS_MY_FILES', DIR_FS_ADMIN . PATH_TO_MY_FILES);
    define('DIR_FS_THEMES',   DIR_FS_ADMIN . 'themes/');
    define('FILENAME_DEFAULT','index');
    define('DIR_WS_ICONS',    '');
    // application_top.php defines this at file scope and the ledger reads it on
    // every inventory posting. Absent, gen_ledger dies on `strpos(COG_ITEM_TYPES,
    // $type)` partway through a post - which looks like a posting fault rather
    // than a missing harness piece.
    if (!defined('COG_ITEM_TYPES')) define('COG_ITEM_TYPES', 'si,sr,ms,mi,ma,sa');

    $autoDetected = false;
    $company = (string)($company ?? (getenv('FREEBOOKS_COMPANY') ?: ''));
    if ($company === '' && is_dir(DIR_FS_MY_FILES)) {
        $autoDetected = true;
        foreach (scandir(DIR_FS_MY_FILES) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir(DIR_FS_MY_FILES . $entry) && file_exists(DIR_FS_MY_FILES . $entry . '/config.php')) {
                $company = $entry;
                break;
            }
        }
    }
    if ($company === '' || !file_exists(DIR_FS_MY_FILES . $company . '/config.php')) {
        fwrite(STDERR, "Company '$company' not found under " . DIR_FS_MY_FILES . "\n");
        exit(1);
    }
    if ($autoDetected) {
        fwrite(STDERR, "WARNING: company auto-detected as '$company'. Pass it explicitly "
                     . "if that is not the one you meant.\n");
    }
    echo "company: $company\n";

    $_SESSION['company']  = $company;
    $_SESSION['language'] = 'en_us';
    $_SESSION['admin_id'] = 1;

    if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) fb_require_globals(DIR_FS_ADMIN . 'vendor/autoload.php');

    fb_require_globals(DIR_FS_MODULES . 'phreedom/language/en_us/language.php');
    fb_require_globals(DIR_FS_MODULES . 'phreedom/defaults.php');
    fb_require_globals(DIR_FS_INCLUDES . 'common_functions.php');
    fb_require_globals(DIR_FS_INCLUDES . 'common_classes.php');

    define('DB_DATABASE', $company);
    fb_require_globals(DIR_FS_MY_FILES . $company . '/config.php');
    if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
    fb_require_globals(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
    $db = new queryFactory();
    $db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

    $cfg = $db->Execute("SELECT configuration_key, configuration_value FROM " . DB_PREFIX . "configuration");
    while (!$cfg->EOF) {
        if (!defined($cfg->fields['configuration_key']))
            define($cfg->fields['configuration_key'], $cfg->fields['configuration_value']);
        $cfg->MoveNext();
    }
    if (!defined('DATE_FORMAT_CALENDAR')) define('DATE_FORMAT_CALENDAR', 'mm-dd-yy');

    // web-path constants html_href_link() needs (normally derived from the request)
    if (!defined('DIR_WS_ADMIN'))     define('DIR_WS_ADMIN', '/');
    if (!defined('HTTP_SERVER'))      define('HTTP_SERVER', 'http://localhost');
    if (!defined('HTTPS_SERVER'))     define('HTTPS_SERVER', 'http://localhost');
    if (!defined('DIR_WS_FULL_PATH')) define('DIR_WS_FULL_PATH', HTTP_SERVER . DIR_WS_ADMIN);
    if (!defined('DIR_WS_THEMES'))    define('DIR_WS_THEMES', 'themes/default/');
    if (!defined('DIR_WS_IMAGES'))    define('DIR_WS_IMAGES', 'themes/default/images/');

    $messageStack = new messageStack();
    gen_pull_language('phreedom',   'menu');
    gen_pull_language('phreebooks', 'menu');
    fb_require_globals(DIR_FS_MODULES . 'phreedom/config.php');

    $loaded_modules = array();                       // find_special_class() reads this global
    foreach (scandir(DIR_FS_MODULES) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        if (is_dir(DIR_FS_MODULES . $dir)) gen_pull_language($dir, 'menu');
        if (defined('MODULE_' . strtoupper($dir) . '_STATUS')) {
            $loaded_modules[] = $dir;
            fb_require_globals(DIR_FS_MODULES . $dir . '/config.php');
        }
    }
    $currencies = new currencies();

    // Everything a PAGE does for itself before it can run a document. A module's
    // defaults.php is not loaded for you — index.php requires it per page
    // through DIR_FS_WORKING, and a CLI tool has no equivalent — so a tool has
    // to do the same. FORMS_DIR_DEFINITIONS is in there, and it is what every
    // definition is read from.
    //
    // Behind the flag rather than unconditional, and that is the point of the
    // flag: verify_forms_pages.php boots with it false so that a page failing to
    // load its own bootstrap has nothing to fall back on. Supplying a constant
    // the page is supposed to supply is what let an empty chooser pass once.
    if ($withPhreeForm) {
        gen_pull_language('phreebooks');             // special-class strings
        fb_require_globals(DIR_FS_MODULES . 'forms/defaults.php');

        // PhreeForm only IF IT IS STILL THERE. Step 6 deletes it, and every
        // tool in this directory boots through here — so an unconditional
        // require turns the deletion into a bootstrap fatal for all of them at
        // once, including the ones written to keep working without it. Found by
        // hiding the module and running the suite, which is the only way to
        // find it before the day it matters.
        if (is_file(DIR_FS_MODULES . 'phreeform/defaults.php')) {
            gen_pull_language('phreeform');          // PF_FONT_* etc.
            fb_require_globals(DIR_FS_MODULES . 'phreeform/defaults.php');
            fb_require_globals(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
        }
    }

    // Whatever this tool goes on to write under my_files/<company>/ ends up
    // owned by whoever runs it — root, under `docker exec` — and the web server
    // then cannot write it. Repaired at shutdown, once, for every tool.
    fb_guard_file_ownership($company);

    return $company;
}

/**
 * The code-to-label tables the Formatter needs.
 *
 * A delegate now, not a second copy. It WAS a copy, and the copy is how
 * `cvt_iso2` and `cvt_cntry` stayed empty in both places at once for the whole
 * project: two identical mistakes look like agreement. The application function
 * is the one that ships, so it is the one a tool must measure.
 *
 * Kept as a name because three tools call it and because it is the place to
 * require what it needs — a tool has no page bootstrap to load it for them.
 *
 * @return array<string,array<string|int,string>>
 */
function fb_formatter_lookups(): array
{
    require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
    return forms_formatter_lookups();
}

/**
 * Put back any ownership a tool changed under `my_files/<company>/`.
 *
 * Registered by fb_bootstrap() for every tool, because this is not a mistake
 * one tool made. A tool run with `docker exec` is root; anything it writes —
 * a converted definition, a restored backup, a kept original, a temporary file
 * renamed into place — belongs to root afterwards, and Apache runs as www-data
 * and cannot write it again. The tool passes, because from where it stands
 * nothing is wrong. The failure surfaces later, somewhere else, as
 *
 *     Report not saved: The definition 'pf_91' could not be written.
 *
 * Every individual tool could remember to chown. None of them did, and the two
 * that were fixed by hand would not have covered the migration tool, the
 * designer verifier, the template maker or the next one written. So it is done
 * once, here, for whatever the tool turns out to touch.
 *
 * The model is `my_files/<company>/` itself, which no tool creates and which
 * therefore still carries the ownership the installation intends. Nothing
 * happens unless this process is root and the owner actually differs.
 */
function fb_guard_file_ownership(string $company): void
{
    if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) return;
    if (!defined('DIR_FS_MY_FILES')) return;

    $model = rtrim(DIR_FS_MY_FILES, '/\\') . '/' . $company;
    if (!is_dir($model)) return;
    $uid = (int)@fileowner($model);
    $gid = (int)@filegroup($model);

    register_shutdown_function(function () use ($model, $uid, $gid) {
        $fix = function (string $path) use (&$fix, $uid, $gid): void {
            if ((int)@fileowner($path) !== $uid || (int)@filegroup($path) !== $gid) {
                @chgrp($path, $gid);
                @chown($path, $uid);
            }
            if (!is_dir($path)) return;
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $fix("$path/$entry");
            }
        };
        $fix($model);
    });
}

/**
 * Copy a directory aside so a destructive tool can put it back exactly.
 *
 * ## Why this is not two lines of copy()
 *
 * It restores OWNERSHIP, and that is the whole reason it exists. A tool run
 * with `docker exec` runs as root; deleting a company's `phreeform/` directory
 * and copying it back therefore recreates every file — and the directory — owned
 * by root. Everything the tool checks then passes, because the tool is root and
 * can read anything. The next person to save a report gets
 *
 *     Report not saved: The definition 'pf_91' could not be written.
 *
 * from Apache, which runs as www-data and no longer can. The verifier that
 * proved the definitions were untouched is what made them unwritable, and it
 * said nothing, because from where it stood nothing was wrong. Measured on
 * three companies at once.
 *
 * So the mode and the owner of every path are recorded going in and reapplied
 * coming out, and if reapplying fails — a tool run as somebody who cannot
 * chown — it says so loudly rather than leaving a company that looks fine and
 * cannot be edited.
 *
 * @return array{dir:string,backup:string,owners:array<string,array{0:int,1:int,2:int}>}
 */
function fb_snapshot_tree(string $dir): array
{
    $dir    = rtrim($dir, '/\\');
    $backup = $dir . '.fb-snapshot-' . getmypid();
    $owners = [];

    $walk = function (string $from, string $rel) use (&$walk, &$owners): void {
        $owners[$rel] = [(int)@fileowner($from), (int)@filegroup($from), (int)@fileperms($from) & 0777];
        if (!is_dir($from)) return;
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $walk("$from/$entry", $rel === '' ? $entry : "$rel/$entry");
        }
    };
    $walk($dir, '');

    fb_copy_tree($dir, $backup);
    return ['dir' => $dir, 'backup' => $backup, 'owners' => $owners];
}

/** Put back what fb_snapshot_tree() copied aside, ownership and all. */
function fb_restore_tree(array $snapshot): void
{
    ['dir' => $dir, 'backup' => $backup, 'owners' => $owners] = $snapshot;
    if (!is_dir($backup)) return;

    fb_remove_tree($dir);
    fb_copy_tree($backup, $dir);
    fb_remove_tree($backup);

    // The directory's own owner is the fallback for anything that was not there
    // before — a tool may legitimately leave a file behind.
    [$uid, $gid, $mode] = $owners[''] ?? [0, 0, 0755];
    $apply = function (string $path, string $rel) use (&$apply, $owners, $uid, $gid): void {
        [$u, $g, $m] = $owners[$rel] ?? [$uid, $gid, is_dir($path) ? 0755 : 0644];
        @chmod($path, $m);
        @chgrp($path, $g);
        @chown($path, $u);
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $apply("$path/$entry", $rel === '' ? $entry : "$rel/$entry");
        }
    };
    $apply($dir, '');

    // Say so rather than leave a company that looks fine and cannot be saved to.
    if ((int)@fileowner($dir) !== $uid) {
        fwrite(STDERR, "\n  *** THE DIRECTORY WAS RESTORED BUT ITS OWNER WAS NOT ***\n"
            . "      $dir is now owned by uid " . (int)@fileowner($dir) . ", was $uid.\n"
            . "      The web server may no longer be able to save a definition. Run:\n"
            . "        chown -R $uid:$gid " . escapeshellarg($dir) . "\n\n");
    }
}

function fb_copy_tree(string $from, string $to): void
{
    @mkdir($to, 0777, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $a = "$from/$entry"; $b = "$to/$entry";
        if (is_dir($a)) fb_copy_tree($a, $b); else @copy($a, $b);
    }
}

function fb_remove_tree(string $path): void
{
    if (!is_dir($path)) { @unlink($path); return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $p = "$path/$entry";
        if (is_dir($p)) fb_remove_tree($p); else @unlink($p);
    }
    @rmdir($path);
}

/**
 * The PhreeForm original for a definition, whether or not it has been migrated.
 *
 * `get_report_details()` reads the STORED file and refuses a native one by
 * design (§13.9). That was fine while every company held XML; it makes a tool
 * built on it vacuous the moment a company is migrated, which is the state
 * step 6 puts every company into — 0 of 0 compared, reported as a pass.
 *
 * The original does not go away, though. Every conversion keeps it beside the
 * new file as `pf_N.pre-forms`, so the comparison survives migration by reading
 * THAT. It changes what a tool proves in a way worth being explicit about: it
 * is a comparison of the two ENGINES over one definition, not of two files.
 *
 * Now a thin wrapper over LegacyReader, which is where that reading lives.
 * It stays as a function because it is what the tools call and because it is
 * the place to say why — and because the loader is what knows the path.
 *
 * Returns null when there is no original left to compare against — a company
 * seeded native has never had one, and nothing is wrong with that.
 */
function legacyOriginal(object $loader, string|int $id): ?object
{
    return \FreeBooks\Forms\Legacy\LegacyReader::original($loader->path($id));
}
