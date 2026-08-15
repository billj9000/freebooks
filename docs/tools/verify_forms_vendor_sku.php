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
//  Path: /docs/tools/verify_forms_vendor_sku.php
//
/**
 * The `purchasing` dataset reproduces a customisation it replaces (design §8.2).
 *
 * A customised Purchase Order printed the supplier's part number through a
 * field whose NAME was raw SQL:
 *
 *     IF(inventory_purchase_details.vendor_sku <> '',
 *        inventory_purchase_details.vendor_sku,
 *        SUBSTRING_INDEX(inventory_purchase_details.description_purchase, ' ', 1))
 *
 * That is §1.1 — the report author writing SQL — so Forms declined the whole
 * definition rather than executing it. The `purchasing` dataset names the same
 * arithmetic as a shipped computed field, which converts the customisation
 * instead of running it.
 *
 * "Converts" is a claim about VALUES, and this is what holds it: the dataset's
 * field is compared, line by line, against the legacy SQL written out exactly
 * as the stored `<relationship>` and field name specify. A migration that
 * prints a different part number than the form it replaces is worse than one
 * that refuses to print.
 *
 * Skips cleanly where the definition or the table is absent, so it is quiet on
 * a company that never had the customisation.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_vendor_sku.php [company]
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

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$table  = $prefix . 'inventory_purchase_details';
if (!($db->Execute("show tables like '" . $db->prepare_input($table) . "'"))->RecordCount()) {
    echo "  [--] $table does not exist on this company; nothing to compare.\n";
    exit(0);
}

// The definition that names the extra table — found by what it declares rather
// than by id, because the id is per company.
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$runner = new FormRunner($db, $catalog);

$target = null;
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM . " where doc_ext = 'frm' order by id");
while (!$result->EOF) {
    $definition = get_report_details((int)$result->fields['id']);
    $names = array_map(fn($t) => strtolower((string)($t->tablename ?? '')), (array)($definition->tables ?? []));
    if ($definition && in_array('inventory_purchase_details', $names, true)) {
        $target = [(int)$result->fields['id'], (string)$result->fields['doc_title'], $definition];
        break;
    }
    $result->MoveNext();
}

if ($target === null) {
    echo "  [--] no form on this company joins inventory_purchase_details.\n";
    exit(0);
}
[$id, $title, $definition] = $target;
printf("company '%s', checking pf_%d (%s)\n\n", $company, $id, $title);

echo "=== the customised definition is served, not declined ===\n";
$plan = $runner->plan($definition);
ok("pf_$id resolves to a dataset", $plan->runnable(), implode(' ', $plan->notes));
ok('...and it is the purchasing dataset', $plan->name === 'purchasing', "it chose '{$plan->name}'");
if (!$plan->runnable()) { echo "\nFAIL - $failures check(s) failed.\n"; exit(1); }

// The filters the definition applies to its LINES, so the comparison selects the
// same rows the form does. gl_type is what separates an item line from the tax
// and total lines, and omitting it makes every purchase order look two lines
// short — which is what the first version of this comparison reported.
$lineType = '';
$journalId = '';
foreach ((array)($definition->filterlist ?? []) as $filter) {
    $name = strtolower((string)($filter->fieldname ?? ''));
    $min  = (string)($filter->min_val ?? '');
    if ($min === '') continue;
    if ($name === 'journal_item.gl_type')    $lineType  = $min;
    if ($name === 'journal_main.journal_id') $journalId = $min;
}
if ($journalId === '') {
    echo "  [--] the definition names no journal; cannot choose comparable records.\n";
    exit(0);
}
printf("  journal %s, line type '%s'\n", $journalId, $lineType);

echo "\n=== every line's vendor SKU matches the legacy expression ===\n";
$records = $db->Execute("select id from " . TABLE_JOURNAL_MAIN
    . " where journal_id = '" . $db->prepare_input($journalId) . "' order by id desc limit 40");

$checked = $compared = $mismatch = $countDiff = 0;
$examples = [];
while (!$records->EOF) {
    $recordId = (int)$records->fields['id'];
    $records->MoveNext();

    // PhreeForm's semantics, written out as the stored definition specifies:
    // the same two join conditions and the same expression.
    $where  = "ji.ref_id = " . $recordId;
    if ($lineType !== '') $where .= " and ji.gl_type = '" . $db->prepare_input($lineType) . "'";
    $legacy = [];
    $rows   = $db->Execute("select ji.id lid,
          IF(ipd.vendor_sku <> '', ipd.vendor_sku,
             SUBSTRING_INDEX(ipd.description_purchase, ' ', 1)) v
        from `{$prefix}journal_item` ji
        inner join `{$prefix}journal_main` jm on jm.id = ji.ref_id
        left join `{$prefix}inventory_purchase_details` ipd
           on ji.sku = ipd.sku and jm.bill_acct_id = ipd.vendor_id
        where $where order by ji.id");
    while (!$rows->EOF) { $legacy[(string)$rows->fields['lid']][] = $rows->fields['v']; $rows->MoveNext(); }

    $document = $runner->run($definition, [$recordId], ['dates' => 'a']);
    if (!count($document->records)) continue;
    $checked++;

    $lines = $document->records[0]->table('items');
    if (count($lines) !== count($legacy)) {
        $countDiff++;
        if (count($examples) < 4) {
            $examples[] = sprintf('record %d: Forms %d line(s), legacy %d', $recordId, count($lines), count($legacy));
        }
    }
    foreach ($lines as $line) {
        $lineId = (string)($line['item_id']->raw ?? '');
        if (!isset($legacy[$lineId])) continue;
        $compared++;
        $mine   = (string)($line['vendor_sku']->raw ?? '');
        $theirs = (string)($legacy[$lineId][0] ?? '');
        if ($mine !== $theirs) {
            $mismatch++;
            if (count($examples) < 6) {
                $examples[] = sprintf('record %d line %s: Forms %s, legacy %s',
                    $recordId, $lineId, var_export($mine, true), var_export($theirs, true));
            }
        }
    }
}

printf("  %d record(s), %d line(s) compared\n", $checked, $compared);
foreach ($examples as $example) echo "      $example\n";

ok('records were available to compare', $checked > 0 && $compared > 0,
   "$checked record(s), $compared line(s)");
ok('no line shows a different vendor SKU', $mismatch === 0, "$mismatch of $compared differ");
ok('no record shows a different number of lines', $countDiff === 0, "$countDiff record(s) differ");

// The join is on (sku, vendor_id), and those columns are NOT unique — so a line
// whose part has two purchase records for one vendor multiplies. The provider
// de-duplicates on the line's own id; this is what proves it still does.
echo "\n=== a duplicated purchase record does not duplicate a line ===\n";
$dupes = $db->Execute("select sku, vendor_id, count(*) n from `$table`
    group by sku, vendor_id having n > 1 order by n desc limit 1");
if (!$dupes || $dupes->EOF) {
    echo "  [--] no (sku, vendor_id) pair is duplicated on this company\n";
} else {
    printf("  sku '%s' has %s purchase records for vendor %s\n",
        $dupes->fields['sku'], $dupes->fields['n'], $dupes->fields['vendor_id']);
    ok('the line counts above already account for it', $countDiff === 0,
       'a duplicated purchase record would have shown as a line-count difference');
}

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
