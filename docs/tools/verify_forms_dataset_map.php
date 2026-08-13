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
//  Path: /docs/tools/verify_forms_dataset_map.php
//
/**
 * Resolve every shipped report to a dataset, and check the answers.
 *
 * Until DatasetResolver existed, the report-to-dataset mapping lived in whoever
 * was typing the verify command — and was wrong twice on the shipped set
 * (`pf_84` is the Audit Log, not a general_ledger report; `pf_62` is the General
 * Ledger report but reads `transactions`). Both mistakes produced a plausible
 * failure rather than an error, which is why the mapping is now code and this
 * tool asserts it.
 *
 * EXPECTED below is the ground truth: every entry is a mapping confirmed by a
 * passing row-set comparison in verify_forms_report.php, not by inspection. A
 * report absent from EXPECTED is reported as unverified rather than assumed.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_dataset_map.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/legacy/dataset_resolver.php');

use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Legacy\DatasetResolver;

/** Mappings proven by a passing row-set comparison. */
const EXPECTED = [
    34 => 'contacts',        // Customer List
    35 => 'contacts',        // Employee List
    36 => 'contacts',        // Vendor List
    37 => 'inventory',       // Inventory Aging - Average Cost
    38 => 'inventory',       // Inventory Aging
    39 => 'inventory',       // Inventory List
    40 => 'inventory',       // Inventory Re-order Worksheet
    41 => 'transactions',    // Inventory Transfer Report
    42 => 'inventory',       // Inventory Valuation - Average
    43 => 'inventory',       // Inventory Valuation - Branch
    44 => 'inventory',       // Inventory Valuation
    46 => 'transactions',    // Aged Payables
    47 => 'transactions',    // Aged Payables Summary
    48 => 'transactions',    // Aged Receivables
    49 => 'transactions',    // Aged Receivables Summary
    52 => 'general_ledger',  // Chart of Accounts
    54 => 'transactions',    // Customer Invoice History
    55 => 'transactions',    // Customer Ledger
    56 => 'transactions',    // Customer Payments
    62 => 'transactions',    // General Ledger      <- reads journal_main, not the GL dataset
    63 => 'general_ledger',  // General Ledger Trial Balance
    70 => 'transactions',    // Open Purchase Orders
    71 => 'transactions',    // Open Sales Orders
    75 => 'transactions',    // Sales By Rep
    76 => 'transactions',    // Sales By SKU
    78 => 'transactions',    // Sales Order Report
    79 => 'transactions',    // Sales Report
    81 => 'transactions',    // Vendor Ledger
    82 => 'transactions',    // Vendor Payments
    84 => 'audit_trail',     // Audit Log           <- not general_ledger
    86 => 'assets',          // Asset List
    89 => 'work_orders',     // Work Order report
    90 => 'work_orders',     // Work Order tasks
];

/**
 * Served by a provider, so they have no dataset at all (§5, §11), plus
 * `income_statement_au`, which names a class that exists nowhere and has
 * therefore never run (§12.3.1).
 *
 * `aged_receivables` and `aged_summary` are deliberately NOT here. They are the
 * two special classes that were deleted rather than ported: their buckets became
 * computed fields on the transactions dataset, so those four reports go through
 * the ordinary query-driven path and their mapping must be checked like any
 * other (§11.11).
 */
const PROVIDER_SERVED = [
    'acct_recon', 'balance_sheet', 'is_budget', 'income_statement_au',
    'entry_builder', 'statement_builder', 'pos_builder', 'wo_builder',
];

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$resolver = new DatasetResolver($catalog);

$failures = $checked = $unverified = $structural = 0;

$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_type in ('rpt', 's') order by id");

while (!$result->EOF) {
    $id     = (int)$result->fields['id'];
    $title  = (string)$result->fields['doc_title'];
    $report = get_report_details($id);
    $result->MoveNext();
    if (!$report) continue;

    $special = (string)($report->special_class ?? '');
    if (in_array($special, PROVIDER_SERVED, true)) {
        $structural++;
        printf("  [--] pf_%-3d %-34s provider: %s\n", $id, substr($title, 0, 34), $special);
        continue;
    }

    $dataset = $resolver->resolve($report);
    $got     = $dataset?->id;
    $notes   = $resolver->notes();

    if (!isset(EXPECTED[$id])) {
        $unverified++;
        printf("  [??] pf_%-3d %-34s resolved to %-16s (not yet proven by a row-set run)\n",
            $id, substr($title, 0, 34), $got ?? 'nothing');
        foreach ($notes as $note) echo "         $note\n";
        continue;
    }

    $checked++;
    $want = EXPECTED[$id];
    if ($got === $want) {
        printf("  [ok] pf_%-3d %-34s %s\n", $id, substr($title, 0, 34), $got);
    } else {
        $failures++;
        printf("  [FAIL] pf_%-3d %-32s expected %s, resolved %s\n",
            $id, substr($title, 0, 32), $want, $got ?? 'nothing');
        foreach ($notes as $note) echo "         $note\n";
    }
}

printf("\n%d proven mapping(s) checked, %d failed; %d structural, %d not yet proven.\n",
    $checked, $failures, $structural, $unverified);
echo $failures === 0 ? "PASS\n" : "FAIL\n";
exit($failures === 0 ? 0 : 1);
