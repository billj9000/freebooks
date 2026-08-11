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
 * $messageStack, $loaded_modules.
 */

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
    require($fs_admin . 'includes/configure.php');

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

    if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) require_once(DIR_FS_ADMIN . 'vendor/autoload.php');

    require_once(DIR_FS_MODULES . 'phreedom/language/en_us/language.php');
    require_once(DIR_FS_MODULES . 'phreedom/defaults.php');
    require_once(DIR_FS_INCLUDES . 'common_functions.php');
    require_once(DIR_FS_INCLUDES . 'common_classes.php');

    define('DB_DATABASE', $company);
    require_once(DIR_FS_MY_FILES . $company . '/config.php');
    if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
    require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
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
    require_once(DIR_FS_MODULES . 'phreedom/config.php');

    $loaded_modules = array();                       // find_special_class() reads this global
    foreach (scandir(DIR_FS_MODULES) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        if (is_dir(DIR_FS_MODULES . $dir)) gen_pull_language($dir, 'menu');
        if (defined('MODULE_' . strtoupper($dir) . '_STATUS')) {
            $loaded_modules[] = $dir;
            require_once(DIR_FS_MODULES . $dir . '/config.php');
        }
    }
    $currencies = new currencies();

    if ($withPhreeForm) {
        gen_pull_language('phreeform');              // PF_FONT_* etc.
        gen_pull_language('phreebooks');             // special-class strings
        require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
        require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
    }

    return $company;
}
