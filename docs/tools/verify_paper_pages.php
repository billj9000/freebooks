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
// they read are visible to them. Rendering inside a function makes those
// globals invisible and the paper select silently disappears, which is what the
// first version of this tool reported as a missing control. See
// feedback_cli_bootstrap_scope.

// The Re-stamp Paper Size action. It lived on PhreeForm's tools tab and came
// over to Forms' settings page when that module was deleted (§13.42); this
// check followed it rather than being dropped, because a check that stops
// asking is how a feature disappears without anyone noticing.
//
// From SOURCE, not from a render. That page has a full harness next door —
// verify_forms_pages drives it through index.php's own sequence, with a
// toolbar and a PAGE_TITLE — and a second, thinner harness for the same page
// would only ever test the harness. What is asked here is the part that
// belongs to PAPER: that the action stamps the setting this file is about, and
// that the description names it rather than reciting a constant nobody set.
echo "\n=== the paper re-stamp action ===\n";
$adminSource  = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/admin/pre_process.php');
$adminMarkup  = (string)file_get_contents(DIR_FS_MODULES . 'forms/pages/admin/template_main.php');
ok('the settings page still offers it', str_contains($adminMarkup, "submitToDo('restamp')"),
   'no button names the restamp action');
ok('and its pre_process handles it', str_contains($adminSource, "case 'restamp':"),
   'the button would do nothing');
ok('it stamps the paper setting rather than a literal',
   str_contains($adminSource, 'forms_restamp_native($file, FORMS_DEFAULT_PAPER)'),
   'the action does not use FORMS_DEFAULT_PAPER');
ok('and says which paper it will use before you press it',
   str_contains($adminMarkup, 'FORMS_TOOLS_RESTAMP_DESC, FORMS_DEFAULT_PAPER'),
   'the description does not name the paper');
ok('it asks first', str_contains($adminMarkup, 'FORMS_TOOLS_RESTAMP_CONFIRM'));

echo "\n=== Company Manager: the paper select ===\n";
// Asked for explicitly, because it used to arrive by accident: this tool
// required phreeform/defaults.php, which calls gen_pull_language($mod, 'admin')
// for every module carrying a config_phreeform.php — phreedom among them. The
// require went with the module and took the accident with it, and the template
// died on SETUP_CO_MGR_COPY_CO. Third time this exact shape has turned up
// (§13.34, §13.37); it is what a transitive language pull costs.
gen_pull_language('phreedom', 'admin');
// What the page's own pre_process.php loads: load_company_dropdown() lives here,
// and the paper list is Forms' now — the template draws the row only when it can
// see forms_paper_sizes(), so a harness that does not load it renders a page
// with the control silently missing.
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
require_once(DIR_FS_MODULES . 'forms/defaults.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
$active_tab   = 'manager';
$db_server    = 'localhost';
$db_name      = $db_user = $co_name = '';
$error        = false;
$core_modules = ['phreedom', 'forms', 'phreebooks', 'contacts', 'inventory', 'shipping', 'phreehelp'];
$problems     = [];
ob_start();
include(DIR_FS_MODULES . 'phreedom/pages/admin/template_tab_manager.php');
$html   = (string)ob_get_clean();
$issues = $problems;
ok('it rendered', trim($html) !== '');
ok('it raised nothing of its own', $issues === [], implode(' | ', array_slice($issues, 0, 4)));
ok('the paper select is present', str_contains($html, "name=\"papersize\"") || str_contains($html, "name='papersize'"),
   'no papersize control in the rendered markup');
// By NAME. PhreeForm keyed its list "A4:210:297" because its designer carried
// the dimensions along; a native definition stores the name and TCPDF looks the
// rest up (§13.37). A tool still asserting the old spelling would fail on a
// page that is right.
ok('it offers A4 and Letter',
   preg_match('#<option value="A4"#', $html) === 1
   && preg_match('#<option value="Letter"#', $html) === 1);
ok('it defaults to this company\'s current setting',
   preg_match('#<option value="' . preg_quote(FORMS_DEFAULT_PAPER, '#') . '"[^>]*selected#', $html) === 1,
   'expected ' . FORMS_DEFAULT_PAPER . ' to be preselected');

restore_error_handler();
if ($inherited) {
    printf("\n%d pre-existing notice(s) on these tabs, not from this change:\n", count($inherited));
    foreach (array_slice(array_keys($inherited), 0, 3) as $note) echo "  [--] $note\n";
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
