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

    if ($withPhreeForm) {
        gen_pull_language('phreeform');              // PF_FONT_* etc.
        gen_pull_language('phreebooks');             // special-class strings
        fb_require_globals(DIR_FS_MODULES . 'phreeform/defaults.php');
        fb_require_globals(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
    }

    return $company;
}

/**
 * The code-to-label tables the Formatter needs, gathered from the modules that
 * own them. Doing it here keeps the render layer free of module dependencies -
 * see the LOOKUP_HINTS note in modules/forms/classes/render/formatter.php.
 *
 * @return array<string,array<string|int,string>>
 */
function fb_formatter_lookups(): array
{
    require(DIR_FS_MODULES . 'phreebooks/defaults.php');   // $coa_types_list
    $arrLocales = $GLOBALS['arrLocales'] ?? [];            // phreeform/config_phreeform.php

    // An absent label is omitted rather than blanked, so an unknown code renders
    // as itself - matching the legacy hooks' isset() fallbacks.
    $pluck = function (array $rows, string $field): array {
        $out = [];
        foreach ($rows as $key => $row) {
            $value = (string)($row[$field] ?? '');
            if ($value !== '') $out[$key] = $value;
        }
        return $out;
    };

    return [
        'coa_type'  => $pluck($coa_types_list ?? [], 'text'),
        'cvt_iso2'  => $pluck($arrLocales, 'iso2'),
        'cvt_cntry' => $pluck($arrLocales, 'name'),
    ];
}
