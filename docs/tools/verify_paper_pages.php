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
//  Path: /docs/tools/verify_paper_pages.php
//
/**
 * The two tabs that ask for a paper size actually render.
 *
 * Choosing the paper added a select to the company manager and a re-stamp
 * utility to the Reports and Forms tools tab, and with them six new language
 * strings across four locales. An undefined constant in any one of those
 * locales is a FATAL page, not a missing label — and it would only show up for
 * whoever runs that locale, which is nobody testing it.
 *
 * So this renders both templates the way their pages do, once per locale, and
 * treats any notice or warning as a failure. It is the cheap version of the
 * lesson that cost four rounds of "undefined constant" on the Forms pages: a
 * template that is never executed is a template that has never been checked.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_paper_pages.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
fb_bootstrap($argv[1] ?? null);

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/** Constants each template reaches for, and the module whose admin.php defines them. */
const REQUIRED = [
    'phreeform' => [
        'PHREEFORM_TOOLS_RESTAMP_TITLE', 'PHREEFORM_TOOLS_RESTAMP_DESC',
        'PHREEFORM_TOOLS_RESTAMP_SUBMIT', 'PHREEFORM_TOOLS_RESTAMP_CONFIRM',
        'PHREEFORM_RESTAMP_DONE', 'PHREEFORM_RESTAMP_SKIPPED',
    ],
    'phreedom' => ['SETUP_CO_MGR_PAPER_SIZE'],
];

// ── every locale defines every new string ────────────────────────────────────
// Read as text rather than by including them: a locale's admin.php cannot be
// loaded twice in one process, and the whole point is to check each separately.
echo "=== every locale defines the strings the templates use ===\n";
foreach (REQUIRED as $module => $constants) {
    $dir = DIR_FS_MODULES . $module . '/language/';
    foreach (glob($dir . '*', GLOB_ONLYDIR) ?: [] as $localeDir) {
        $file = $localeDir . '/admin.php';
        if (!file_exists($file)) continue;
        $locale  = basename($localeDir);
        $source  = (string)file_get_contents($file);
        $missing = [];
        foreach ($constants as $constant) {
            if (!preg_match("#define\s*\(\s*'" . preg_quote($constant, '#') . "'#", $source)) {
                $missing[] = $constant;
            }
        }
        ok("$module/$locale defines all " . count($constants) . ' string(s)',
           $missing === [], implode(', ', $missing));
    }
}

// ── the installer's own strings ──────────────────────────────────────────────
// The install form cannot be driven from here — it runs before there is a
// configure.php, a database or a company — so this is a static check of the one
// thing that broke the module pages repeatedly: a constant defined in one
// locale and not the others. install_pull_language() has NO fallback (it loads
// the requested locale or en_us, never both), so a locale missing a string is a
// fatal install form for whoever picks that language.
echo "\n=== the install form's strings exist in every locale it offers ===\n";
foreach (glob(DIR_FS_ADMIN . 'install/language/*', GLOB_ONLYDIR) ?: [] as $localeDir) {
    $file = $localeDir . '/language.php';
    if (!file_exists($file)) continue;
    $source  = (string)file_get_contents($file);
    $missing = [];
    foreach (['TEXT_PRINTING_INFO', 'TEXT_PAPER_SIZE_INFO'] as $constant) {
        if (!str_contains($source, "define('$constant'")) $missing[] = $constant;
    }
    ok('install/' . basename($localeDir) . ' defines both string(s)', $missing === [], implode(', ', $missing));
}

// ── the templates execute ────────────────────────────────────────────────────
// Warnings are failures: an undefined constant is a fatal on PHP 8, and an
// undefined variable is how a select silently renders empty.
// Pre-existing on the company manager, and not this change's business. Both of
// these fire on the real page exactly as they do here:
//
//   - the tab reads $_POST[$mod.'_action'] for every module without a null
//     coalesce, so a first render with no POST raises two notices per module
//   - load_company_dropdown() includes every company's my_files config.php, and
//     each one re-defines DB_SERVER_* that application_top.php already defined
//
// They are worth fixing, but fixing them here would hide whether the paper
// select itself is clean. Reported at the end rather than silently dropped.
const INHERITED = '#^(Undefined array key "[a-z0-9_]+_action"|Constant DB_SERVER_\w+ already defined)#';

$problems = $inherited = [];
set_error_handler(function (int $no, string $message, string $file, int $line)
        use (&$problems, &$inherited): bool {
    $where = sprintf('%s in %s:%d', $message, basename($file), $line);
    if (preg_match(INHERITED, $message)) $inherited[$where] = true; else $problems[] = $where;
    return true;
});

// Rendered at FILE scope, deliberately, and not from inside a helper function.
// template_index.php requires a page's template at global scope, and the tab
// templates are required at the file scope of template_main.php, so the globals
// that phreeform/defaults.php defines — $PaperSizes among them — are visible to
// them. Rendering inside a function makes those globals invisible and the paper
// select silently disappears, which is what the first version of this tool
// reported as a missing control. See feedback_cli_bootstrap_scope.
$active_tab = 'tools';
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');

echo "\n=== Reports and Forms: the tools tab ===\n";
$problems = [];
ob_start();
include(DIR_FS_MODULES . 'phreeform/pages/admin/template_tab_tools.php');
$html   = (string)ob_get_clean();
$issues = $problems;
ok('it rendered', trim($html) !== '');
ok('it raised nothing', $issues === [], implode(' | ', array_slice($issues, 0, 4)));
ok('the re-stamp button is wired to the restamp action', str_contains($html, "submitToDo('restamp')"), $html);
ok('it names the paper that will be stamped',
   defined('PF_DEFAULT_PAPERSIZE') && str_contains($html, PF_DEFAULT_PAPERSIZE),
   'expected ' . (defined('PF_DEFAULT_PAPERSIZE') ? PF_DEFAULT_PAPERSIZE : '?'));
ok('it asks before rewriting anything', str_contains($html, 'confirm('));

echo "\n=== Company Manager: the paper select ===\n";
gen_pull_language('phreedom', 'admin');
// What the page's own pre_process.php loads: load_company_dropdown() lives here.
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
$active_tab   = 'manager';
$db_server    = 'localhost';
$db_name      = $db_user = $co_name = '';
$error        = false;
$core_modules = ['phreedom', 'phreeform', 'phreebooks', 'contacts', 'inventory', 'shipping', 'phreehelp'];
$problems     = [];
ob_start();
include(DIR_FS_MODULES . 'phreedom/pages/admin/template_tab_manager.php');
$html   = (string)ob_get_clean();
$issues = $problems;
ok('it rendered', trim($html) !== '');
ok('it raised nothing of its own', $issues === [], implode(' | ', array_slice($issues, 0, 4)));
ok('the paper select is present', str_contains($html, "name=\"papersize\"") || str_contains($html, "name='papersize'"),
   'no papersize control in the rendered markup');
ok('it offers A4 and Letter',
   str_contains($html, 'A4:210:297') && str_contains($html, 'Letter:216:282'));
ok('it defaults to this company\'s current setting',
   !defined('PF_DEFAULT_PAPERSIZE')
   || preg_match('#<option value="' . preg_quote(PF_DEFAULT_PAPERSIZE, '#') . '" selected#', $html) === 1,
   'expected ' . (defined('PF_DEFAULT_PAPERSIZE') ? PF_DEFAULT_PAPERSIZE : '?') . ' to be preselected');

restore_error_handler();
if ($inherited) {
    printf("\n%d pre-existing notice(s) on these tabs, not from this change:\n", count($inherited));
    foreach (array_slice(array_keys($inherited), 0, 3) as $note) echo "  [--] $note\n";
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
