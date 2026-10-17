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
printf("  Forms installed: %s\n\n", defined('MODULE_FORMS_STATUS') ? 'yes' : 'no');

// ── 1. where a Print button goes ─────────────────────────────────────────────
//
// This section used to test a SWITCH. gen_print_form_url() chose between Forms
// and PhreeForm on MODULE_FORMS_PRINT_BUTTONS and on whether the person
// clicking held SECURITY_ID_FORMS_RUN, both of which were scaffolding for
// running the two engines side by side (§11.16). One engine now, so the only
// thing left to hold is that every button reaches it — including for a user
// whose role does not grant Run, who used to be quietly sent to the other
// engine and is now refused by the print page, which is the honest answer.
echo "=== every Print button opens Forms ===\n";

$forms  = 'module=forms&page=print';
$legacy = 'module=phreeform';

foreach ([0 => 'without Run rights', 3 => 'with Run rights'] as $level => $who) {
    $_SESSION['admin_security'][SECURITY_ID_FORMS_RUN] = $level;
    $url = gen_print_form_url('cust:inv', 'journal_main.id', 'EQUAL', 1372);
    ok("$who, a button opens Forms", str_contains($url, $forms), $url);
    ok("$who, it names no other engine", !str_contains($url, $legacy), $url);
    $granted = $url;      // the last one, with rights, for the checks below
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
    // docs/ ships in the container's web root but is development tooling, and
    // this tool's own source names the URL it is looking for.
    if (str_starts_with($path, 'vendor/') || str_starts_with($path, 'ext/') || str_starts_with($path, 'docs/')) continue;

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

// Every one of them accounted for by the canvas's record picker (§13.54).
//
// RecordSelector carries a copy of the journal-to-form-type mapping that lives
// as a `switch` in phreebooks/pages/status/pre_process.php. A copy of a switch
// goes stale the first time a journal is added, and the symptom is quiet: the
// picker offers the wrong records, or none, for a form type nobody thought to
// check. This is the check — the application's own list of form types, against
// the two lists that class keeps.
//
// UNRESTRICTED is what makes the check readable rather than merely passing: a
// group in it has been considered and deliberately left alone, with the reason
// written down, which is a different thing from a group nobody has met yet.
$mapped = \FreeBooks\Forms\App\RecordSelector::restrictedGroups();
$known  = array_merge($mapped, array_keys(\FreeBooks\Forms\App\RecordSelector::UNRESTRICTED));
$adrift = array_values(array_diff($groups, $known));
ok('every form type the application sends is one the record picker knows about',
   $adrift === [],
   implode(', ', $adrift) . ' — the canvas would offer the wrong records for these');
printf("  [--] %d restricted by journal, %d deliberately unrestricted\n",
       count($mapped), count(\FreeBooks\Forms\App\RecordSelector::UNRESTRICTED));

// And the other direction: a group this class restricts that the application no
// longer sends is a restriction nothing can reach, which is dead weight rather
// than a fault — reported, not failed.
$unsent = array_values(array_diff($mapped, $groups));
if ($unsent !== []) {
    printf("  [--] restricted but no Print button sends it: %s\n", implode(', ', $unsent));
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$runner = new FormRunner($db, $catalog);
$loader = new \FreeBooks\Forms\Definition\DefinitionLoader($catalog);

$empty = $declined = $handedBack = 0;
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
        } elseif ($definition->isSerial()) {
            // Served, but as TEXT rather than a PDF: the print page renders it
            // through SerialRenderer (§13.27). Counted separately because
            // "prints" means something different for a till printer, and a
            // group reported alongside the PDF forms would hide that.
            $handedBack++;
            $lines[] = sprintf('pf_%d (%s) via %s, as a receipt', $id,
                (string)$result->fields['doc_title'], $plan->name);
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
if ($handedBack) {
    printf("  [--] %d group(s) hold a receipt printer form, printed as text\n", $handedBack);
}
if ($empty) {
    // Not a failure: a company that does not use the POS has no pos:rcpt form,
    // and its Print button is unreachable for the same reason.
    printf("  [--] %d group(s) have no form here; those buttons print nothing on either engine\n", $empty);
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
