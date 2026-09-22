<?php
/**
 * Update FreeBooks currency exchange rates from the ECB daily feed.
 *
 * Usage:
 *   php /var/www/html/cron/update_currency_rates.php [company_name]
 *
 * company_name may also be supplied via the FREEBOOKS_COMPANY environment
 * variable.  When omitted the script auto-detects the first company directory
 * found under my_files/.
 */

define('PAGE_EXECUTION_START_TIME', microtime(true));

// ─── Resolve the trunk root ───────────────────────────────────────────────────
$trunk = realpath(__DIR__ . '/..');
if ($trunk === false) {
    fwrite(STDERR, "Cannot resolve trunk path from " . __DIR__ . "\n");
    exit(1);
}
$fs_admin = $trunk . '/';

if (!file_exists($fs_admin . 'includes/configure.php')) {
    fwrite(STDERR, "configure.php not found at " . $fs_admin . "includes/configure.php\n");
    exit(1);
}
require($fs_admin . 'includes/configure.php');
if (!defined('DIR_FS_ADMIN')) define('DIR_FS_ADMIN', $fs_admin);

// ─── Path constants ───────────────────────────────────────────────────────────
if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');
define('DIR_WS_MODULES',  'modules/');
define('DIR_WS_MY_FILES', PATH_TO_MY_FILES);
define('DIR_FS_INCLUDES', DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MODULES',  DIR_FS_ADMIN . 'modules/');
define('DIR_FS_MY_FILES', DIR_FS_ADMIN . PATH_TO_MY_FILES);
define('DIR_FS_THEMES',   DIR_FS_ADMIN . 'themes/');
define('FILENAME_DEFAULT', 'index');
define('DIR_WS_ICONS',    '');

// ─── Fake HTTP + session context for CLI ─────────────────────────────────────
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['PHP_SELF']    = '/cron/update_currency_rates.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_REQUEST = $_GET = $_POST = [];
$_SESSION = ['language' => 'en_us', 'admin_id' => 1, 'admin_security' => []];

// ─── Resolve company ─────────────────────────────────────────────────────────
$company = (string)($argv[1] ?? getenv('FREEBOOKS_COMPANY') ?? '');
if ($company === '') {
    $mf = DIR_FS_MY_FILES;
    if (is_dir($mf)) {
        foreach (scandir($mf) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($mf . $entry) && file_exists($mf . $entry . '/config.php')) {
                $company = $entry;
                break;
            }
        }
    }
}
if ($company === '' || !file_exists(DIR_FS_MY_FILES . $company . '/config.php')) {
    fwrite(STDERR, "Company '$company' not found. Usage: php update_currency_rates.php <company_name>\n");
    exit(1);
}
$_SESSION['company'] = $company;

// ─── Load shared code ────────────────────────────────────────────────────────
// Composer first: common_functions.php below declares a class implementing a
// PHPMailer interface.
if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) {
    require_once(DIR_FS_ADMIN . 'vendor/autoload.php');
}
$_prev_er = error_reporting(E_ALL & ~E_WARNING);
require_once(DIR_FS_MODULES . 'phreedom/language/en_us/language.php');
// The currency class titles itself from the admin language file.
require_once(DIR_FS_MODULES . 'phreedom/language/en_us/admin.php');
require_once(DIR_FS_MODULES . 'phreedom/defaults.php');
require_once(DIR_FS_INCLUDES . 'common_functions.php');
require_once(DIR_FS_INCLUDES . 'common_classes.php');
error_reporting($_prev_er); unset($_prev_er);

// ─── Database connection ──────────────────────────────────────────────────────
define('DB_DATABASE', $company);
require_once(DIR_FS_MY_FILES . $company . '/config.php');
if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
$db = new queryFactory();
$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

// Load configuration constants
$_cfg = $db->Execute("SELECT configuration_key, configuration_value FROM " . DB_PREFIX . "configuration");
while (!$_cfg->EOF) {
    if (!defined($_cfg->fields['configuration_key'])) {
        define($_cfg->fields['configuration_key'], $_cfg->fields['configuration_value']);
    }
    $_cfg->MoveNext();
}

// The currency class reads its own permission out of the session. A cron has no
// login, so it is given the rights this job needs and no others. Declared here
// rather than by loading phreedom/config.php, which is a menu builder and wants
// web UI language constants.
define('SECURITY_ID_CONFIGURATION', 11);
$_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] = 4;
define('TABLE_CURRENCIES',    DB_PREFIX . 'currencies');
define('TABLE_AUDIT_LOG',     DB_PREFIX . 'audit_log');
define('TABLE_JOURNAL_MAIN',  DB_PREFIX . 'journal_main');

// ─── Run the update ───────────────────────────────────────────────────────────
$messageStack = new messageStack();
require_once(DIR_FS_MODULES . 'phreedom/classes/currency.php');

$cur = new currency();
$cur->btn_update();

// Report results, and take the exit code from the same place. messageStack keeps
// its messages in $_SESSION['messageToStack'].
$had_error = false;
foreach ($_SESSION['messageToStack'] ?? array() as $msg) {
    $type   = $msg['type'] ?? '';
    $prefix = $type === 'error' ? 'ERROR' : ($type === 'caution' || $type === 'warning' ? 'WARN' : 'OK');
    if ($type === 'error') $had_error = true;
    echo date('[Y-m-d H:i:s]') . " $prefix: " . trim(html_entity_decode(strip_tags($msg['text'] ?? ''), ENT_QUOTES, 'UTF-8')) . "\n";
}
exit($had_error ? 1 : 0);
