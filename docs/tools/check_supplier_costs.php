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
//  Path: /docs/tools/check_supplier_costs.php
//
/**
 * Report code that reads or writes a supplier's cost without item_costs (issue #130).
 *
 * A supplier row's item_cost is in the row's own currency, so SQL that names the
 * column directly - to price with it, compare it, or write a journal's figure into
 * it - skips the conversion that trunk/modules/inventory/classes/item_costs.php does.
 *
 *   php docs/tools/check_supplier_costs.php
 *
 * It looks at every PHP file of ours in trunk/ and extensions/ and reports each
 * statement that names the supplier table (TABLE_INVENTORY_PURCHASE or
 * inventory_purchase_details) together with item_cost, or the column through the
 * usual alias, p.item_cost. Exit status 1 when anything is reported.
 *
 * ALLOWED lists the files that may, each with its reason. A statement that only
 * displays or copies the figure with its currency may be allowed; one that prices
 * or compares with it may not.
 */

$root = dirname(__DIR__, 2);

const ALLOWED = [
    'trunk/modules/inventory/classes/item_costs.php'   => 'the conversion itself',
    'trunk/modules/inventory/classes/install.php'      => 'table definition and demo data',
    'trunk/modules/inventory/updates/'                 => 'migrations',
];

const SKIP = ['trunk/vendor/', 'trunk/ext/', 'trunk/jodit/', 'extensions/Modules/zencart/ZenCartFiles/',
              'extensions/Modules/doc_ctl/includes/jstree/'];

function cs_allowed(string $rel): ?string {
    foreach (ALLOWED as $prefix => $why) if (strpos($rel, $prefix) === 0) return $why;
    return null;
}

$found = 0;
$allowed_hits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if ($file->getExtension() !== 'php' || !$file->isFile()) continue;
    $rel = substr($file->getPathname(), strlen($root) + 1);
    if (strpos($rel, 'trunk/') !== 0 && strpos($rel, 'extensions/') !== 0) continue;
    foreach (SKIP as $s) if (strpos($rel, $s) === 0) continue 2;
    $src = file_get_contents($file->getPathname());
    if (strpos($src, 'item_cost') === false) continue;
    // Strip comments so prose about the column is not reported.
    $code = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $code .= str_repeat("\n", substr_count($t[1], "\n"));
        } else {
            $code .= is_array($t) ? $t[1] : $t;
        }
    }
    $offset = 0;
    foreach (explode(';', $code) as $stmt) {
        $line = substr_count($code, "\n", 0, $offset) + 1 + (strlen($stmt) - strlen(ltrim($stmt)) ? substr_count(substr($stmt, 0, strlen($stmt) - strlen(ltrim($stmt))), "\n") : 0);
        $offset += strlen($stmt) + 1;
        $names_table = preg_match('/TABLE_INVENTORY_PURCHASE|inventory_purchase_details/', $stmt);
        $names_cost  = preg_match('/\bitem_cost\b/', $stmt);
        if (!(($names_table && $names_cost) || preg_match('/\bp\.item_cost\b/', $stmt))) continue;
        $why = cs_allowed($rel);
        if ($why !== null) { $allowed_hits[$rel] = $why; continue; }
        printf("%s:%d  %s\n", $rel, $line, preg_replace('/\s+/', ' ', trim(substr(trim($stmt), 0, 160))));
        $found++;
    }
}

echo "\n";
foreach ($allowed_hits as $rel => $why) printf("allowed  %s  (%s)\n", $rel, $why);
printf("\n%d statement(s) read or write a supplier cost outside item_costs\n", $found);
exit($found ? 1 : 0);
