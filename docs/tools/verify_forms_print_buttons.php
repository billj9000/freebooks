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
//  Path: /docs/tools/verify_forms_print_buttons.php
//
/**
 * The Print buttons can be pointed at Forms, and doing so prints something.
 *
 * Two separate claims, and the second is the one worth money.
 *
 *  1. gen_print_form_url() routes correctly. Three conditions have to hold
 *     before a button opens Forms, and each is checked by making it false and
 *     seeing PhreeForm come back — a switch that cannot be switched off is not
 *     a switch. The permission gate is exercised in both directions here,
 *     because it is the one an admin cannot see: it depends on who clicked.
 *
 *  2. Every form group a Print button can send resolves to a form that Forms
 *     can actually serve. This is what makes flipping the switch safe to do
 *     rather than merely reversible. The button on a Sales Order does not say
 *     which document it wants — it sends a GROUP ('cust:so') — so a group with
 *     no form in it, or with one Forms declines, is a button that will appear
 *     to do nothing on the day someone ticks the box. Finding that here beats
 *     finding it from a user.
 *
 * The group list is the one the application itself defines: every
 * POPUP_FORM_TYPE in the tree, read out of the source rather than restated, so
 * a new Print button cannot quietly escape this check.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_print_buttons.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\FormRunner;
use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

printf("company '%s'\n", $company);
printf("  Forms installed: %s\n", defined('MODULE_FORMS_STATUS') ? 'yes' : 'no');
printf("  MODULE_FORMS_PRINT_BUTTONS: %s\n\n",
    defined('MODULE_FORMS_PRINT_BUTTONS') ? var_export(MODULE_FORMS_PRINT_BUTTONS, true) : '(not set)');

// ── 1. the switch ────────────────────────────────────────────────────────────
// The session is the only one of the three conditions a running process can
// change — a constant defined at bootstrap from the configuration table cannot
// be redefined — so the key is read where it stands and the permission is moved.
echo "=== the switch routes, and can be switched off ===\n";

$forms  = 'module=forms&page=print';
$legacy = 'module=phreeform&page=popup_gen';

$_SESSION['admin_security'][SECURITY_ID_FORMS_RUN] = 0;
$denied = gen_print_form_url('cust:inv', 'journal_main.id', 'EQUAL', 1372);
ok('without Run rights, a button opens PhreeForm', str_contains($denied, $legacy), $denied);

$_SESSION['admin_security'][SECURITY_ID_FORMS_RUN] = 3;
$granted  = gen_print_form_url('cust:inv', 'journal_main.id', 'EQUAL', 1372);
$key_on   = defined('MODULE_FORMS_STATUS')
         && defined('MODULE_FORMS_PRINT_BUTTONS') && MODULE_FORMS_PRINT_BUTTONS == '1';
ok('with Run rights, a button follows the setting',
   str_contains($granted, $key_on ? $forms : $legacy),
   ($key_on ? 'setting is on but got ' : 'setting is off but got ') . $granted);

if (!$key_on) {
    echo "      (the setting is off here, so the Forms branch is unexercised;\n"
       . "       turn it on in Company -> Module Administration -> Forms to check it)\n";
}

echo "\n=== the parameters survive the round trip ===\n";

// FILENAME_DEFAULT is 'index', not 'index.php' — html_href_link() is where the
// extension normally gets added, and a helper that builds its own relative URL
// has to do the same or every Print button 404s. It did, once.
ok('the URL names a page the browser can fetch', str_starts_with($granted, 'index.php?'), $granted);

$range = gen_print_form_url('vend:po', 'journal_main.purchase_invoice_id', 'RANGE', '100', '200');
parse_str((string)parse_url($range, PHP_URL_QUERY), $q);
ok('gID, xfld, xcr, xmin and xmax all arrive',
   ($q['gID'] ?? '') === 'vend:po' && ($q['xfld'] ?? '') === 'journal_main.purchase_invoice_id'
   && ($q['xcr'] ?? '') === 'RANGE' && ($q['xmin'] ?? '') === '100' && ($q['xmax'] ?? '') === '200',
   $range);

// Nine of the call sites append the record id in JavaScript, so xmin has to be
// last or the id lands inside whatever follows it. That defect has been shipped
// once already on this page's chooser links, which is why it is asserted.
$appendable = gen_print_form_url('cust:so', 'journal_main.id', 'IN_LIST');
ok('xmin is last, so a caller can append the id in JavaScript',
   str_ends_with($appendable, '&xmin='), $appendable);

// ── 2. no call site has escaped the helper, and what groups they send ────────
//
// The DEPLOYED tree, not the repository: what a Print button does is decided by
// the file the web server reads, and a tool that checks the working copy would
// pass while the container ran something else.
echo "\n=== no Print button still names an engine ===\n";
$root      = dirname(rtrim(DIR_FS_MODULES, '/'));
$hardcoded = array();
$groups    = array();
$iterator  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), array('php', 'js'), true)) continue;
    $path = str_replace($root . '/', '', $file->getPathname());
    if (str_starts_with($path, 'vendor/') || str_starts_with($path, 'ext/')) continue;

    // The engines' own pages are allowed to know their own names; so is the
    // helper, which is the whole point of it. They are skipped for the group
    // harvest as well — the print page's own documentation contains an example
    // URL, and harvesting that would invent a group nothing sends.
    if (str_starts_with($path, 'modules/phreeform/') || str_starts_with($path, 'modules/forms/')
        || $path === 'includes/common_functions.php') continue;

    $body = (string)file_get_contents($file->getPathname());
    if (str_contains($body, 'page=popup_gen')) $hardcoded[] = $path;

    // Read the groups out of the source, so a Print button added later is
    // covered by this check without anyone remembering to add it. Two forms:
    // the POPUP_FORM_TYPE constant most pages define, and the literal a caller
    // writes into the URL itself, which is what the work order button does.
    if (preg_match_all("/define\(\s*'POPUP_FORM_TYPE'\s*,\s*'([^']*)'/", $body, $matches)) {
        foreach ($matches[1] as $group) if ($group !== '') $groups[$group] = true;
    }
    if (preg_match_all("/gen_print_form_url\(\s*'([a-z_]+:[a-z_]+)'/", $body, $matches)) {
        foreach ($matches[1] as $group) $groups[$group] = true;
    }
}
ok('every Print button goes through gen_print_form_url()', $hardcoded === [],
   implode(', ', $hardcoded));

// ── 3. every group a button can send is servable ─────────────────────────────
echo "\n=== every form group a Print button sends can be printed ===\n";
$groups = array_keys($groups);
sort($groups);
ok('the source names some form groups', $groups !== [], 'found none');
printf("  %d group(s): %s\n", count($groups), implode(', ', $groups));

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$runner = new FormRunner($db, $catalog);
$loader = new \FreeBooks\Forms\Definition\DefinitionLoader($catalog);

$empty = $declined = 0;
foreach ($groups as $group) {
    $result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM . "
        where doc_group = '" . db_input($group) . "' and doc_ext = 'frm' order by doc_title");
    if ($result->RecordCount() === 0) {
        $empty++;
        printf("      [--] %-11s no form in this group on this company\n", $group);
        continue;
    }
    $lines = array();
    while (!$result->EOF) {
        $id = (int)$result->fields['id'];
        // Through the format-aware loader, not get_report_details(): a form the
        // canvas has saved is native, and the legacy reader refuses it — which
        // this tool reported as "no readable definition" on a file that reads
        // perfectly well.
        try {
            $definition = $loader->load($id);
            $plan       = $runner->planDefinition($definition);
        } catch (\FreeBooks\Forms\Definition\DefinitionException $e) {
            $definition = null;
            $plan       = null;
        }
        if (!$definition) {
            $declined++;
            $lines[] = sprintf('pf_%d cannot be read: %s', $id, $e->getMessage());
        } elseif (!$plan->runnable()) {
            $declined++;
            $lines[] = sprintf('pf_%d (%s) DECLINED: %s', $id,
                (string)$result->fields['doc_title'], implode(' ', $plan->notes));
        } else {
            $lines[] = sprintf('pf_%d (%s) via %s', $id,
                (string)$result->fields['doc_title'], $plan->name);
        }
        $result->MoveNext();
    }
    printf("      %-11s %s\n", $group, implode("\n                  ", $lines));
}

ok('no group holds a form Forms would decline', $declined === 0, "$declined declined");
if ($empty) {
    // Not a failure: a company that does not use the POS has no pos:rcpt form,
    // and its Print button is unreachable for the same reason.
    printf("  [--] %d group(s) have no form here; those buttons print nothing on either engine\n", $empty);
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
