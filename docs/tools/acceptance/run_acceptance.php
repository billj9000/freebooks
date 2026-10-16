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
//  Path: /docs/tools/acceptance/run_acceptance.php
//
/**
 * THIS WRITES TO THE DATABASE. Post a ledger scenario step by step through the
 * application's own classes and check the books after every step (issue #94).
 *
 *   docker exec <c> php -d display_errors=1 /opt/repo/docs/tools/acceptance/run_acceptance.php \
 *       <company> --scenario=a1 [--mode=stock|mysql8|both] [--go]
 *
 * Without --go it lists the steps and changes nothing. With --go it refuses any
 * company whose database is not on the throwaway server (make one with
 * docs/tools/test_company.sh), because it begins by EMPTYING THE COMPANY'S
 * LEDGER - the same tables Purge Journal empties - so that every figure it
 * checks comes from the scenario alone.
 *
 * WHAT A STEP DOES. Purchases and sales go through orders::post_ordr(), edits
 * through post_ordr() on an existing id, deletes through orders::delete_ordr(),
 * and the general journal entry through journal::Post() - the calls the order
 * and journal pages make, fed the fields those pages would send.
 *
 * WHAT IS CHECKED after every step: the scenario's expected figures, and every
 * check in invariants.php. A step whose post fails stops the run, since the
 * steps after it assume it happened; a figure that is wrong does not.
 *
 * ONE PROCESS PER STEP, as in journal_driver.php: queryFactory answers a failed
 * statement with die(), so a refusal would otherwise end the run silently and
 * exit 0. It also gives each step a fresh JOURNAL_ID, which the order and
 * journal classes read as a constant, as each page defines it. Step outcomes
 * pass between processes in a small state file.
 *
 * BOTH SQL PROFILES. --mode=both (the default) runs the whole scenario twice:
 * under the server's own sql_mode, and with the zero-date and GROUP BY rules
 * MySQL 8 enforces added.
 */

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) $args[$m[1]] = $m[2] ?? true;
    else $args['company'] = $a;
}
$company  = $args['company'] ?? '';
$webroot  = getenv('FREEBOOKS_WEBROOT') ?: '/var/www/html';
$TEST_DB_HOST = getenv('FREEBOOKS_TEST_DB_HOST') ?: 'fb-testdb';
$MODES = array(
    'stock'  => null,
    'mysql8' => 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,'
              . 'NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY',
);

if ($company === '' || empty($args['scenario'])) {
    fwrite(STDERR, "usage: run_acceptance.php <company> --scenario=<name> [--mode=stock|mysql8|both] [--go]\n");
    exit(2);
}
$found = glob(__DIR__ . '/scenarios/' . basename((string)$args['scenario']) . '*.php');
if (count($found) !== 1) { fwrite(STDERR, "no single scenario matches '{$args['scenario']}'\n"); exit(2); }
$scenario_file = $found[0];
$scenario = require $scenario_file;

$cfg = $webroot . '/my_files/' . $company . '/config.php';
if (!file_exists($cfg)) { fwrite(STDERR, "no such company: $company\n"); exit(2); }
preg_match("/define\('DB_SERVER_HOST','([^']*)'/", file_get_contents($cfg), $h);
$on_test_server = (($h[1] ?? '') === $TEST_DB_HOST);

if (isset($args['child'])) {
    if (!$on_test_server) { echo "REFUSED: not on the throwaway server\n"; exit(2); }
    exit(acc_child((string)$args['child'], $company, $scenario, (string)$args['state'], $MODES[$args['mode']] ?? null));
}

/** What a step does, in words. */
function acc_describe(array $s): string
{
    switch ($s['do']) {
        case 'gl':     return 'general journal';
        case 'buy':    return sprintf('buy %s @ %s', $s['qty'], $s['price']);
        case 'sell':   return sprintf('sell %s @ %s', $s['qty'], $s['price']);
        case 'edit':   return sprintf('edit %s to %s @ %s', $s['target'], $s['qty'], $s['price']);
        case 'delete': return 'delete ' . $s['target'];
    }
    return $s['do'];
}

// ---------------------------------------------------------------- parent
printf("%s - %s\n", basename($scenario_file), $scenario['title']);
if (empty($args['go'])) {
    foreach ($scenario['steps'] as $s) {
        printf("  step %-2d day %-2d %-6s %-30s %s\n", $s['seq'], $s['day'], $s['label'] ?? '', acc_describe($s), $s['note'] ?? '');
    }
    echo "\nnothing changed. Pass --go to run it on a throwaway company.\n";
    exit(0);
}
if (!$on_test_server) {
    fwrite(STDERR, "REFUSING to write: '$company' is on '" . ($h[1] ?? '?') . "', not the throwaway server '$TEST_DB_HOST'.\n"
                 . "The run empties the company's ledger. Make a copy with docs/tools/test_company.sh.\n");
    exit(2);
}

$php = escapeshellarg(PHP_BINARY) . ' -d display_errors=1';
exec("$php " . escapeshellarg(dirname(__DIR__) . '/canary.php') . ' ' . escapeshellarg($company) . ' 2>&1', $canary);
if (!in_array('CANARY PASS', array_map('trim', $canary), true)) {
    echo implode("\n", $canary), "\nthe detectors are not working, so a run would prove nothing.\n";
    exit(1);
}
echo "canary: the error detectors are alive\n";

$sql_log = $webroot . '/my_files/sql_errors.log';
$want_modes = ($args['mode'] ?? 'both') === 'both' ? array_keys($MODES) : array((string)$args['mode']);
$failed = 0;
foreach ($want_modes as $mode) {
    if (!array_key_exists($mode, $MODES)) { fwrite(STDERR, "unknown mode: $mode\n"); exit(2); }
    echo "\n== sql mode: $mode\n";
    $state = tempnam(sys_get_temp_dir(), 'acc');
    $run = function (string $child) use ($php, $company, $scenario_file, $state, $mode) {
        $cmd = sprintf('%s %s %s --scenario=%s --mode=%s --state=%s --child=%s 2>&1', $php, escapeshellarg(__FILE__),
                       escapeshellarg($company), escapeshellarg(basename($scenario_file, '.php')),
                       escapeshellarg($mode), escapeshellarg($state), escapeshellarg($child));
        $out = array(); $rc = 0;
        exec($cmd, $out, $rc);
        $out = array_values(array_filter($out, function ($l) { return strpos($l, 'company: ') !== 0 && trim($l) !== ''; }));
        return array($rc, $out);
    };
    list($rc, $out) = $run('reset');
    if ($rc !== 0 || end($out) !== 'RESET') {
        echo "  reset FAILED\n";
        foreach ($out as $l) echo "      $l\n";
        $failed++; @unlink($state); continue;
    }
    foreach (array_slice($out, 0, -1) as $l) echo "  $l\n";
    foreach ($scenario['steps'] as $i => $s) {
        printf("  step %-2d day %-2d %-30s ", $s['seq'], $s['day'], acc_describe($s));
        clearstatcache();
        $log_size = file_exists($sql_log) ? filesize($sql_log) : 0;
        list($rc, $out) = $run("post:$i");
        if ($rc !== 0 || !$out || strpos(end($out), 'POSTED') !== 0) {
            echo "POST FAILED\n";
            foreach ($out as $l) echo "      $l\n";
            if (!$out) {
                // A die() inside queryFactory prints nothing; the statement it refused is in the log.
                printf("      died with no output (exit %d); sql_errors.log says:\n", $rc);
                clearstatcache();
                $new = file_exists($sql_log) && filesize($sql_log) > $log_size ? file_get_contents($sql_log, false, null, $log_size) : '';
                foreach (explode("\n", trim($new)) as $l) if (trim($l) !== '') echo "        " . substr(trim($l), 0, 300) . "\n";
            }
            echo "      the steps after this one assume it happened; stopping this mode\n";
            $failed++;
            break;
        }
        list($rc, $out) = $run("check:$i");
        $verdict = $out ? end($out) : '';
        echo $verdict === 'CHECK-PASS' ? "ok\n" : "FAIL\n";
        foreach (array_slice($out, 0, -1) as $l) echo "      $l\n";
        if ($verdict !== 'CHECK-PASS') {
            if (!$out) printf("      the check died with no output (exit %d)\n", $rc);
            $failed++;
        }
    }
    @unlink($state);
}
echo $failed ? "\n$failed failure(s)\n" : "\nall steps passed\n";
exit($failed ? 1 : 0);

// ---------------------------------------------------------------- child

function acc_child(string $what, string $company, array $scenario, string $state_file, ?string $sql_mode): int
{
    require_once(dirname(__DIR__) . '/bootstrap_freebooks.php');
    require_once(__DIR__ . '/invariants.php');
    fb_bootstrap($company, false);
    global $db;
    if ($sql_mode !== null) $db->Execute("SET SESSION sql_mode = '" . $sql_mode . "'");
    // Language first: the defaults files build their arrays from its constants.
    foreach (array('phreebooks', 'contacts', 'inventory', 'shipping') as $m) gen_pull_language($m);
    gen_pull_language('phreebooks', 'admin');   // the account type names phreebooks/defaults.php reads
    fb_require_globals(DIR_FS_MODULES . 'phreebooks/defaults.php');
    fb_require_globals(DIR_FS_MODULES . 'inventory/defaults.php');
    require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
    require_once(DIR_FS_MODULES . 'phreebooks/classes/gen_ledger.php');
    require_once(DIR_FS_MODULES . 'phreebooks/classes/orders.php');
    $_SESSION['messageToStack'] = array();

    $state = is_file($state_file) && filesize($state_file) ? json_decode(file_get_contents($state_file), true) : array();
    list($kind, $n) = array_pad(explode(':', $what, 2), 2, null);
    if ($kind === 'reset') {
        $state = acc_reset($scenario);
        file_put_contents($state_file, json_encode($state));
        echo "RESET\n";
        return 0;
    }
    $step = $scenario['steps'][(int)$n] ?? null;
    if (!$step) { echo "no step $n\n"; return 2; }
    if ($kind === 'post')  return acc_post($step, $state, $state_file);
    if ($kind === 'check') return acc_check($step, $scenario, $state);
    echo "unknown child task $what\n";
    return 2;
}

/**
 * Empty the ledger, pin the scenario's settings and create its items and
 * contacts. The tables emptied are the ones Purge Journal empties
 * (phreebooks/pages/admin_tools, purge_db).
 */
function acc_reset(array $scenario): array
{
    global $db;
    foreach (array(TABLE_JOURNAL_MAIN, TABLE_JOURNAL_ITEM, TABLE_ACCOUNTS_HISTORY, TABLE_INVENTORY_HISTORY,
                   TABLE_INVENTORY_COGS_OWED, TABLE_INVENTORY_COGS_USAGE, TABLE_RECONCILIATION) as $t) {
        $db->Execute("TRUNCATE TABLE $t");
    }
    if (defined('TABLE_SHIPPING_LOG')) $db->Execute("TRUNCATE TABLE " . TABLE_SHIPPING_LOG);
    $db->Execute("UPDATE " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " SET beginning_balance = 0, debit_amount = 0, credit_amount = 0");
    // History for an account no longer in the chart is zero after the purge, but
    // I3 would still report it, and the run is about the scenario, not the copy.
    $db->Execute("DELETE FROM " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " WHERE account_id NOT IN (SELECT id FROM " . TABLE_CHART_OF_ACCOUNTS . ")");
    $db->Execute("UPDATE " . TABLE_INVENTORY . " SET quantity_on_hand = 0, quantity_on_order = 0, quantity_on_sales_order = 0");

    foreach ($scenario['settings'] as $key => $value) {
        $r = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . db_input($key) . "'");
        if ($r->EOF) db_perform(TABLE_CONFIGURATION, array('configuration_key' => $key, 'configuration_value' => $value), 'insert');
        else         db_perform(TABLE_CONFIGURATION, array('configuration_value' => $value), 'update', "configuration_key = '" . db_input($key) . "'");
    }

    $roles = array(
        'ap'        => AP_DEFAULT_PURCHASE_ACCOUNT,
        'ar'        => AR_DEFAULT_GL_ACCT,
        'inventory' => INV_STOCK_DEFAULT_INVENTORY,
        'cogs'      => INV_STOCK_DEFAULT_COS,
        'sales'     => INV_STOCK_DEFAULT_SALES,
    );
    $first = function (string $types) {
        $r = acc_rows("SELECT id FROM " . TABLE_CHART_OF_ACCOUNTS . " WHERE account_type IN ($types) ORDER BY account_type, id LIMIT 1");
        return $r ? (string)$r[0]['id'] : '';
    };
    $roles['cash']   = $first('0');
    $roles['equity'] = $first('40, 42');
    foreach ($roles as $role => $acct) {
        if ($acct === '' || !acc_rows("SELECT id FROM " . TABLE_CHART_OF_ACCOUNTS . " WHERE id = '" . db_input($acct) . "'")) {
            echo "the chart has no account for '$role' ($acct)\n";
            exit(1);
        }
    }

    foreach ($scenario['items'] as $it) {
        $sku = db_input($it['sku']);
        $db->Execute("DELETE FROM " . TABLE_INVENTORY . " WHERE sku = '$sku'");
        $db->Execute("DELETE FROM " . TABLE_INVENTORY_PURCHASE . " WHERE sku = '$sku'");
        db_perform(TABLE_INVENTORY, array(
            'sku'                    => $it['sku'],
            'inventory_type'         => 'si',
            'description_short'      => $it['description'],
            'description_purchase'   => $it['description'],
            'description_sales'      => $it['description'],
            'account_sales_income'   => $roles['sales'],
            'account_inventory_wage' => $roles['inventory'],
            'account_cost_of_sales'  => $roles['cogs'],
            'cost_method'            => $it['cost_method'],
            'item_cost'              => $it['item_cost'],
            'creation_date'          => date('Y-m-d H:i:s'),
        ), 'insert');
    }

    $contacts = array();
    foreach ($scenario['contacts'] as $c) {
        $old = acc_rows("SELECT id FROM " . TABLE_CONTACTS . " WHERE short_name = '" . db_input($c['short_name']) . "' AND type = '" . db_input($c['type']) . "'");
        foreach ($old as $o) {
            $db->Execute("DELETE FROM " . TABLE_ADDRESS_BOOK . " WHERE ref_id = " . (int)$o['id']);
            $db->Execute("DELETE FROM " . TABLE_CONTACTS . " WHERE id = " . (int)$o['id']);
        }
        db_perform(TABLE_CONTACTS, array(
            'type'            => $c['type'],
            'short_name'      => $c['short_name'],
            'gl_type_account' => $c['type'] === 'v' ? $roles['inventory'] : $roles['sales'],
            'first_date'      => date('Y-m-d'),
        ), 'insert');
        $id = (int)db_insert_id();
        db_perform(TABLE_ADDRESS_BOOK, array('ref_id' => $id, 'type' => $c['type'] . 'm', 'primary_name' => $c['name'],
                                             'country_code' => COMPANY_COUNTRY), 'insert');
        $contacts[$c['type']] = array('id' => $id, 'address_id' => (int)db_insert_id(), 'name' => $c['name']);
    }

    // Day 1 is the first day of the current fiscal year's first period.
    $fy = acc_rows("SELECT fiscal_year FROM " . TABLE_ACCOUNTING_PERIODS . " WHERE period = " . (int)CURRENT_ACCOUNTING_PERIOD);
    $p  = acc_rows("SELECT period, start_date, end_date FROM " . TABLE_ACCOUNTING_PERIODS . "
                    WHERE fiscal_year = " . (int)$fy[0]['fiscal_year'] . " ORDER BY period LIMIT 1");
    $max_day = max(array_column($scenario['steps'], 'day'));
    if (strtotime($p[0]['start_date'] . " +" . ($max_day - 1) . " days") > strtotime($p[0]['end_date'])) {
        echo "the scenario runs to day $max_day, past the end of period {$p[0]['period']}\n";
        exit(1);
    }
    printf("ledger emptied; period %d from %s; accounts: %s\n", $p[0]['period'], $p[0]['start_date'],
           implode(', ', array_map(function ($k, $v) { return "$k $v"; }, array_keys($roles), $roles)));
    return array('roles' => $roles, 'contacts' => $contacts, 'period' => (int)$p[0]['period'],
                 'start' => $p[0]['start_date'], 'ids' => array());
}

function acc_date(array $state, int $day): string
{
    return date('Y-m-d', strtotime($state['start'] . ' +' . ($day - 1) . ' days'));
}

/** The constants the order page defines for its journal (phreebooks/pages/orders/pre_process.php). */
function acc_define_journal(int $jid, array $state): void
{
    define('JOURNAL_ID', $jid);
    if ($jid === 6) {
        define('GL_TYPE', 'por');
        define('DEF_INV_GL_ACCT', AP_DEFAULT_INVENTORY_ACCOUNT);
        define('DEF_GL_ACCT', $state['roles']['ap']);
    } elseif ($jid === 12) {
        define('GL_TYPE', 'sos');
        define('DEF_INV_GL_ACCT', AR_DEF_GL_SALES_ACCT);
        define('DEF_GL_ACCT', $state['roles']['ar']);
    }
}

/** Errors the ledger raised, read off the stack the application uses. */
function acc_stack_errors(): array
{
    $out = array();
    foreach ($_SESSION['messageToStack'] ?? array() as $m) {
        if (($m['type'] ?? '') !== 'error') continue;
        $out[] = trim(preg_replace('/\s+/', ' ', strip_tags($m['text'])));
    }
    return $out;
}

function acc_post(array $step, array $state, string $state_file): int
{
    $sql_log = DIR_FS_MY_FILES . 'sql_errors.log';
    clearstatcache();
    $log_size = file_exists($sql_log) ? filesize($sql_log) : 0;
    $why = array(); $id = 0;
    try {
        switch ($step['do']) {
            case 'gl':
                $id = acc_post_gl($step, $state);
                break;
            case 'buy':
            case 'sell':
                $id = acc_post_order($step, $state, $step['do'] === 'buy' ? 6 : 12, 0);
                break;
            case 'edit':
                $id = acc_post_order($step, $state, acc_journal_of($state, $step['target']), (int)$state['ids'][$step['target']]);
                break;
            case 'delete':
                $target = (int)$state['ids'][$step['target']];
                acc_define_journal(acc_journal_of($state, $step['target']), $state);
                $order = new orders();
                $order->journal($target);
                $id = $order->delete_ordr() ? $target : 0;
                break;
            default:
                $why[] = 'unknown action ' . $step['do'];
        }
    } catch (Throwable $e) {
        $why[] = get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
    }
    foreach (acc_stack_errors() as $m) $why[] = 'ledger: ' . $m;
    clearstatcache();
    if (file_exists($sql_log) && filesize($sql_log) > $log_size) {
        foreach (explode("\n", trim(file_get_contents($sql_log, false, null, $log_size))) as $l) $why[] = 'REFUSED ' . substr(trim($l), 0, 200);
    }
    if (!$id && !$why) $why[] = 'the post answered false and said nothing';
    if ($why) { foreach ($why as $w) echo "$w\n"; return 1; }
    if (isset($step['label'])) $state['ids'][$step['label']] = $id;
    file_put_contents($state_file, json_encode($state));
    echo "POSTED $id\n";
    return 0;
}

function acc_journal_of(array $state, string $label): int
{
    $r = acc_rows("SELECT journal_id FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)($state['ids'][$label] ?? 0));
    if (!$r) throw new RuntimeException("step $label has no entry to act on");
    return (int)$r[0]['journal_id'];
}

/** A general journal entry, as phreebooks/pages/journal/pre_process.php builds one. */
function acc_post_gl(array $step, array $state): int
{
    global $db;
    define('JOURNAL_ID', 2);
    $entry = new journal();
    $entry->id                  = '';
    $entry->journal_id          = 2;
    $entry->post_date           = acc_date($state, (int)$step['day']);
    $entry->period              = gen_calculate_period($entry->post_date, true);
    $entry->purchase_invoice_id = 'ACC-' . $step['label'];
    $entry->admin_id            = 1;
    $entry->recur_id            = 0;
    $entry->store_id            = 0;
    $entry->journal_rows        = array();
    $total = 0;
    foreach ($step['rows'] as list($role, $debit, $credit)) {
        $entry->journal_rows[] = array('id' => '', 'qty' => '1', 'gl_account' => $state['roles'][$role],
            'description' => $step['note'] ?? 'acceptance', 'debit_amount' => $debit, 'credit_amount' => $credit,
            'post_date' => $entry->post_date);
        $total += $debit;
    }
    $entry->journal_main_array = array(
        'id' => '', 'period' => $entry->period, 'journal_id' => 2, 'post_date' => $entry->post_date,
        'total_amount' => $total, 'description' => GL_ENTRY_TITLE, 'purchase_invoice_id' => $entry->purchase_invoice_id,
        'currencies_code' => DEFAULT_CURRENCY, 'currencies_value' => 1, 'admin_id' => 1,
        'bill_primary_name' => $step['note'] ?? 'acceptance', 'recur_id' => 0, 'store_id' => 0,
    );
    $db->transStart();
    if (!$entry->validate_purchase_invoice_id() || !$entry->Post('insert')) { $db->transRollback(); return 0; }
    $db->transCommit();
    return (int)$entry->id;
}

/**
 * A purchase or sale of one stock line, new or edited, as the order page sends
 * it (phreebooks/pages/orders/pre_process.php). An edit keeps the entry's id,
 * its document number and its item line's id, as the page does.
 */
function acc_post_order(array $step, array $state, int $jid, int $edit_id): int
{
    acc_define_journal($jid, $state);
    $buy  = ($jid === 6);
    $who  = $state['contacts'][$buy ? 'v' : 'c'];
    $item = acc_rows("SELECT * FROM " . TABLE_INVENTORY . " WHERE sku = '" . db_input($step['sku'] ?? acc_sku_of($edit_id)) . "'")[0];

    $order = new orders();
    $order->id                  = $edit_id ?: '';
    $order->journal_id          = $jid;
    $order->post_date           = acc_date($state, (int)$step['day']);
    $order->period              = gen_calculate_period($order->post_date, true);
    $order->account_type        = $buy ? 'v' : 'c';
    $order->bill_add_update     = 0;
    $order->bill_acct_id        = $who['id'];
    $order->bill_address_id     = $who['address_id'];
    $order->bill_primary_name   = $who['name'];
    $order->ship_acct_id        = $who['id'];
    $order->ship_address_id     = $who['address_id'];
    $order->gl_acct_id          = DEF_GL_ACCT;
    $order->purchase_invoice_id = $edit_id ? acc_rows("SELECT purchase_invoice_id FROM " . TABLE_JOURNAL_MAIN . " WHERE id = $edit_id")[0]['purchase_invoice_id']
                                           : ($buy ? 'ACC-' . $step['label'] : '');
    $order->purch_order_id      = '';
    $order->so_po_ref_id        = '';
    $order->store_id            = 0;
    $order->recur_id            = 0;
    $order->recur_frequency     = 0;
    $order->admin_id            = 1;
    $order->rep_id              = 0;
    $order->terms               = '';
    $order->waiting             = 0;
    $order->closed              = 0;
    $order->terminal_date       = '';
    $order->freight             = 0;
    $order->discount            = 0;
    $order->disc_percent        = 0;
    $order->sales_tax           = 0;
    $order->notes               = '';
    $order->description         = sprintf(TEXT_JID_ENTRY, constant('ORD_TEXT_' . $jid . '_WINDOW_TITLE'));
    $line_id = $edit_id ? (acc_rows("SELECT id FROM " . TABLE_JOURNAL_ITEM . " WHERE ref_id = $edit_id AND gl_type = '" . GL_TYPE . "' LIMIT 1")[0]['id'] ?? '') : '';
    $order->item_rows = array(array(
        'id'                     => $line_id,
        'so_po_item_ref_id'      => '',
        'item_cnt'               => 1,
        'gl_type'                => GL_TYPE,
        'qty'                    => $step['qty'],
        'pstd'                   => $step['qty'],
        'sku'                    => $item['sku'],
        'desc'                   => $item['description_short'],
        'proj'                   => '',
        'purch_package_quantity' => '',
        'date_1'                 => '',
        'price'                  => $step['price'],
        'full'                   => $buy ? 0 : $step['price'],
        'acct'                   => $buy ? $item['account_inventory_wage'] : $item['account_sales_income'],
        'tax'                    => 0,
        'restock'                => '1',
        'total'                  => $step['qty'] * $step['price'],
        'serial'                 => '',
    ));
    return $order->post_ordr($edit_id ? 'edit' : 'insert') ? (int)$order->id : 0;
}

function acc_sku_of(int $id): string
{
    return (string)(acc_rows("SELECT sku FROM " . TABLE_JOURNAL_ITEM . " WHERE ref_id = $id AND sku <> '' LIMIT 1")[0]['sku'] ?? '');
}

/** Net income for a period: what income, cost of goods and expense accounts net to, credit positive. */
function acc_net_income(int $period): float
{
    $r = acc_rows("SELECT SUM(h.beginning_balance + h.debit_amount - h.credit_amount) AS n
        FROM " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " h JOIN " . TABLE_CHART_OF_ACCOUNTS . " a ON a.id = h.account_id
        WHERE a.account_type IN (30, 32, 34) AND h.period = " . (int)$period);
    return -(float)($r[0]['n'] ?? 0);
}

function acc_check(array $step, array $scenario, array $state): int
{
    $tol = acc_tolerance();
    $roles = $state['roles'];
    $sku = $scenario['items'][0]['sku'];
    $qoh = (float)(acc_rows("SELECT quantity_on_hand FROM " . TABLE_INVENTORY . " WHERE sku = '" . db_input($sku) . "'")[0]['quantity_on_hand'] ?? 0);
    $got = array(
        'qty'        => $qoh,
        'inventory'  => acc_account_balance($roles['inventory']),
        'net_income' => acc_net_income($state['period']),
        'cash'       => acc_account_balance($roles['cash']),
    );
    $ok = true; $parts = array();
    foreach ($step['expect'] as $k => $want) {
        $limit = $k === 'qty' ? ACC_QTY_TOLERANCE : $tol;
        $good = abs($got[$k] - $want) <= $limit;
        $ok = $ok && $good;
        $fmt = $k === 'qty' ? '%s' : '%.4f';
        $parts[] = sprintf("%s $fmt%s", str_replace('_', ' ', $k), $k === 'qty' ? $got[$k] + 0 : $got[$k],
                           $good ? '' : sprintf(" WANTED $fmt", $k === 'qty' ? $want + 0 : $want));
    }
    echo implode('  ', $parts), "\n";
    if (!empty($scenario['conservation'])) {
        $ap = -acc_account_balance($roles['ap']);
        $held = $got['inventory'] + acc_account_balance($roles['cogs']);
        if (abs($ap - $held) > $tol) {
            $ok = false;
            printf("payables %.4f, but inventory + cost of goods = %.4f\n", $ap, $held);
        }
    }
    $owed = acc_rows("SELECT SUM(qty) AS q FROM " . TABLE_INVENTORY_COGS_OWED . " WHERE sku = '" . db_input($sku) . "'")[0]['q'] ?? 0;
    printf("(owed %s, inventory per unit %s)\n", $owed + 0, $qoh != 0 ? sprintf('%.4f', $got['inventory'] / $qoh) : '-');
    ob_start();
    $inv_ok = acc_report_invariants(acc_invariants(), '');
    $report = ob_get_clean();
    // Only the failing checks are worth a line once the step passes.
    foreach (explode("\n", rtrim($report)) as $l) if (!$inv_ok && $l !== '' && !preg_match('/ ok \(/', $l)) echo "$l\n";
    echo ($ok && $inv_ok) ? "CHECK-PASS\n" : "CHECK-FAIL\n";
    return ($ok && $inv_ok) ? 0 : 1;
}
