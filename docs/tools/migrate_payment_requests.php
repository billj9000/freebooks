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
//  Path: /docs/tools/migrate_payment_requests.php
//
/**
 * WITH --go THIS WRITES TO THE DATABASE. Move a company from pb_paypal_orders
 * to payment_requests.
 *
 * A company upgraded from PhreeBooks, or installed fresh, gets payment_requests
 * from the payment module. This is for a company that was already on FreeBooks
 * 1.0.0 before that table replaced pb_paypal_orders: the 1.0.0 migration does
 * not run again for it.
 *
 * For each company it creates payment_requests from the installer's
 * declaration, copies every pb_paypal_orders row across, checks that each one
 * arrived, and only then drops pb_paypal_orders. It is safe to run again: a
 * company that already has payment_requests and no pb_paypal_orders is left as
 * it is, and a row already copied is not copied twice.
 *
 * What a pb_paypal_orders row did not hold is filled in where FreeBooks knows
 * it and left empty where it does not: the amount and currency are the
 * invoice's; a payment link expires three hours after it was made, as PayPal
 * allows; the receipt is the J18 carrying the capture's id. When the request
 * was paid is not known, so completed_at stays empty.
 *
 *   docker exec <c> php docs/tools/migrate_payment_requests.php <company>|--all [--go]
 *
 * Without --go it reports what it would do and changes nothing.
 */

$webroot = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$args    = array_slice($argv, 1);
$all     = in_array('--all', $args, true);
$go      = in_array('--go', $args, true);
$company = '';
foreach ($args as $a) if ($a[0] !== '-') $company = $a;
if (!$all && $company === '') {
    fwrite(STDERR, "usage: migrate_payment_requests.php <company>|--all [--go]\n");
    exit(2);
}

$companies = array();
if ($all) {
    foreach (scandir($webroot . '/my_files') as $d) {
        if ($d[0] === '.') continue;
        if (file_exists($webroot . '/my_files/' . $d . '/config.php')) $companies[] = $d;
    }
} else {
    $companies[] = $company;
}

if (count($companies) > 1) {
    // One process per company: the constants a company's config defines cannot
    // be redefined for the next one.
    $failed = 0;
    foreach ($companies as $co) {
        $cmd = sprintf('%s -d display_errors=1 %s %s%s', escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__),
                       escapeshellarg($co), $go ? ' --go' : '');
        passthru($cmd, $rc);
        if ($rc !== 0) $failed++;
    }
    exit($failed ? 1 : 0);
}

require_once(__DIR__ . '/bootstrap_freebooks.php');
fb_bootstrap($company, false);
global $db;
if (!defined('TABLE_PAYMENT_REQUESTS')) define('TABLE_PAYMENT_REQUESTS', DB_PREFIX . 'payment_requests');
$old = 'pb_paypal_orders';

$has_new = db_table_exists(TABLE_PAYMENT_REQUESTS);
$has_old = db_table_exists($old);
$n_old   = 0;
if ($has_old) {
    $r = $db->Execute("SELECT COUNT(*) AS n FROM " . $old);
    $n_old = (int)$r->fields['n'];
}
printf("  %-20s payment_requests %s, pb_paypal_orders %s\n", $company,
       $has_new ? 'present' : 'absent', $has_old ? "present ($n_old rows)" : 'absent');

if ($has_new && !$has_old) {
    echo "    nothing to do\n";
    exit(0);
}
if (!$go) {
    if (!$has_new) echo "    would create payment_requests\n";
    if ($has_old)  echo "    would copy $n_old row(s), then drop pb_paypal_orders\n";
    exit(0);
}

if (!$has_new) {
    require_once(DIR_FS_MODULES . 'payment/classes/install.php');
    $install = new payment_admin();
    $db->Execute($install->tables[TABLE_PAYMENT_REQUESTS]);
    echo "    created payment_requests\n";
}
if (!$has_old) exit(0);

$db->Execute("INSERT IGNORE INTO " . TABLE_PAYMENT_REQUESTS
    . " (method, request_type, external_id, ref_id, amount, currency_code, status,"
    . "  receipt_id, capture_id, admin_id, created_at, expires_at)"
    . " SELECT 'paypal',"
    . "  IF(o.source_type = 'invoice', 'invoice', 'link'),"
    . "  o.paypal_order_id,"
    . "  o.fb_invoice_id,"
    . "  COALESCE(ROUND(m.total_amount * m.currencies_value, 2), 0),"
    . "  COALESCE(m.currencies_code, ''),"
    . "  IF(o.status = 'COMPLETED', 'paid', 'requested'),"
    . "  (SELECT r.id FROM " . TABLE_JOURNAL_MAIN . " r WHERE r.journal_id = 18"
    . "     AND o.capture_id IS NOT NULL AND r.purch_order_id = o.capture_id ORDER BY r.id LIMIT 1),"
    . "  o.capture_id,"
    . "  0,"
    . "  COALESCE(o.created_at, NOW()),"
    . "  IF(o.source_type = 'invoice', NULL, DATE_ADD(COALESCE(o.created_at, NOW()), INTERVAL 3 HOUR))"
    . " FROM " . $old . " o LEFT JOIN " . TABLE_JOURNAL_MAIN . " m ON m.id = o.fb_invoice_id");

$r = $db->Execute("SELECT COUNT(*) AS n FROM " . $old . " o JOIN " . TABLE_PAYMENT_REQUESTS . " p"
    . " ON p.method = 'paypal' AND p.external_id = o.paypal_order_id");
$copied = (int)$r->fields['n'];
if ($copied !== $n_old) {
    echo "    STOPPED: $copied of $n_old row(s) found in payment_requests; pb_paypal_orders kept\n";
    exit(1);
}
$db->Execute("DROP TABLE " . $old);
echo "    copied $n_old row(s), dropped pb_paypal_orders\n";
exit(0);
