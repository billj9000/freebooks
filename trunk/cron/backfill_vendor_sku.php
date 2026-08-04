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
//  Path: /cron/backfill_vendor_sku.php
//
/**
 * One-off maintenance script: populates inventory_purchase_details.vendor_sku
 * from the first word of description_purchase, for rows that predate the
 * vendor_sku column and still rely on the old free-text convention.
 *
 * This is purely optional - inv_vendor_sku() (inventory/functions/inventory.php)
 * already falls back to the same first-word rule at read time, so nothing is
 * broken if this is never run. It exists to let vendor_sku become the single
 * source of truth over time.
 *
 * By default description_purchase is left untouched (reversible - vendor_sku
 * is just a copy of what the description already implied). Pass
 * --strip-description to also remove the leading part-number token (and the
 * whitespace after it) from description_purchase once vendor_sku has been
 * set from it - this is NOT reversible, so back up the database first.
 *
 * Dry-run by default: prints what would change and does nothing else.
 * Pass --apply to actually write.
 *
 * Pass --vendor-id=<id> to restrict to a single supplier (contacts.id) instead
 * of scanning every vendor - handy for working through a large catalogue in
 * batches, or trying the tool out on one supplier before running it wide.
 *
 * Usage:
 *   php cron/backfill_vendor_sku.php <company_name> [--apply] [--strip-description] [--vendor-id=<id>]
 */

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

if (file_exists(DIR_FS_ADMIN . 'vendor/autoload.php')) {
    require_once(DIR_FS_ADMIN . 'vendor/autoload.php');
}

if (!defined('PATH_TO_MY_FILES')) define('PATH_TO_MY_FILES', 'my_files/');
define('DIR_FS_INCLUDES', DIR_FS_ADMIN . 'includes/');
define('DIR_FS_MY_FILES', DIR_FS_ADMIN . PATH_TO_MY_FILES);

// ─── Args ──────────────────────────────────────────────────────────────────
$args     = array_slice($argv, 1);
$apply    = in_array('--apply', $args, true);
$strip    = in_array('--strip-description', $args, true);
$vendorId = 0;
$company  = '';
foreach ($args as $arg) {
    if ($arg === '--apply' || $arg === '--strip-description') continue;
    if (strpos($arg, '--vendor-id=') === 0) {
        $vendorId = (int)substr($arg, strlen('--vendor-id='));
        continue;
    }
    $company = $arg;
}
if ($company === '') {
    fwrite(STDERR, "Usage: php cron/backfill_vendor_sku.php <company_name> [--apply] [--strip-description] [--vendor-id=<id>]\n");
    exit(1);
}
if (!file_exists(DIR_FS_MY_FILES . $company . '/config.php')) {
    fwrite(STDERR, "Company '$company' not found.\n");
    exit(1);
}

// ─── Load shared code (db_input() lives here) ─────────────────────────────────
require_once(DIR_FS_INCLUDES . 'common_functions.php');

// ─── Database connection ──────────────────────────────────────────────────────
define('DB_DATABASE', $company);
require_once(DIR_FS_MY_FILES . $company . '/config.php');
if (!defined('DB_SERVER_HOST') && defined('DB_SERVER')) define('DB_SERVER_HOST', DB_SERVER);
require_once(DIR_FS_INCLUDES . 'db/' . DB_TYPE . '/query_factory.php');
$db = new queryFactory();
$db->connect(DB_SERVER_HOST, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);

define('TABLE_INVENTORY_PURCHASE', DB_PREFIX . 'inventory_purchase_details');
define('TABLE_CONTACTS',           DB_PREFIX . 'contacts');

if (!db_field_exists(TABLE_INVENTORY_PURCHASE, 'vendor_sku')) {
    fwrite(STDERR, "vendor_sku column not found on " . TABLE_INVENTORY_PURCHASE . " - run the module upgrade first.\n");
    exit(1);
}

if ($vendorId) {
    $vendor = $db->Execute("SELECT short_name FROM " . TABLE_CONTACTS . " WHERE id = " . $vendorId . " AND type = 'v'");
    if ($vendor->EOF) {
        fwrite(STDERR, "No vendor found with id=$vendorId.\n");
        exit(1);
    }
    echo "Restricting to vendor_id=$vendorId (" . $vendor->fields['short_name'] . ")\n\n";
}

$sql = "SELECT id, sku, vendor_id, description_purchase FROM " . TABLE_INVENTORY_PURCHASE
    . " WHERE (vendor_sku = '' OR vendor_sku IS NULL) AND description_purchase IS NOT NULL AND description_purchase <> ''";
if ($vendorId) $sql .= " AND vendor_id = " . $vendorId;
$sql .= " ORDER BY sku";
$rows = $db->Execute($sql);

$candidates = 0;
$updates    = array();
while (!$rows->EOF) {
    $description = trim((string)$rows->fields['description_purchase']);
    if ($description !== '') {
        $words = preg_split('/\s+/', $description, 2);
        $first = $words[0];
        if ($first !== '') {
            $remainder = isset($words[1]) ? trim($words[1]) : '';
            $candidates++;
            $updates[] = array(
                'id'          => $rows->fields['id'],
                'sku'         => $rows->fields['sku'],
                'vendor_id'   => $rows->fields['vendor_id'],
                'vendor_sku'  => $first,
                'description' => $remainder,
            );
            if ($strip) {
                printf("%s sku=%-24s vendor_id=%-6s vendor_sku=%-20s description=\"%s\"\n", $apply ? 'APPLY' : 'DRY-RUN', $rows->fields['sku'], $rows->fields['vendor_id'], $first, $remainder);
            } else {
                printf("%s sku=%-24s vendor_id=%-6s vendor_sku=%s\n", $apply ? 'APPLY' : 'DRY-RUN', $rows->fields['sku'], $rows->fields['vendor_id'], $first);
            }
        }
    }
    $rows->MoveNext();
}

echo "\n$candidates row(s) " . ($apply ? 'updated.' : 'would be updated. Re-run with --apply to write.') . "\n";
if (!$strip) echo "description_purchase left as-is. Re-run with --strip-description to also remove the leading part number from it.\n";

if ($apply) {
    foreach ($updates as $update) {
        $sql = "UPDATE " . TABLE_INVENTORY_PURCHASE . " SET vendor_sku = '" . db_input($update['vendor_sku']) . "'";
        if ($strip) $sql .= ", description_purchase = '" . db_input($update['description']) . "'";
        $sql .= " WHERE id = " . (int)$update['id'];
        $db->Execute($sql);
    }
    echo "Done." . ($strip ? " description_purchase updated.\n" : " description_purchase was not modified.\n");
}
?>
