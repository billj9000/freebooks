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
//  Path: /docs/tools/verify_bills_currency.php
//
/**
 * A receipt or payment settles the currency the document was raised in.
 *
 * Issue #63: a customer payment raised against a USD credit memo came out in
 * GBP with no way to change it. The data model was never the problem —
 * journal_main has carried currencies_code and currencies_value all along, and
 * the ZenCart cron has been writing both onto its receipts — it was this one
 * page, which stamped DEFAULT_CURRENCY at 1.0 on everything it posted and
 * showed every open document as its home-currency equivalent with nothing to
 * say so.
 *
 * What that means for the checks below, and it is the whole shape of them:
 * every journal amount is STORED in the home currency. So there is no figure
 * anywhere that is "wrong" and can be caught by looking at it. What is checked
 * instead is the pair of conversions either side — the rate applied on the way
 * to the screen, and divided out again on the way to the ledger — and the code
 * recorded beside the result.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_bills_currency.php [company] [mode]
 *
 * With no mode it runs the model checks and then re-runs itself once per page
 * mode as a child process, because the bills page defines JOURNAL_ID and a
 * constant cannot be defined twice with different values.
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
$MODE    = $argv[2] ?? '';

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}
function skip(string $what, string $why): void { echo "  [--] $what — $why\n"; }

// In the order a page does it. phreebooks/defaults.php reads COA_*_DESC out of
// the language file, so pulling the strings is not a nicety before it - it is
// the difference between a bootstrapped tool and an undefined-constant fatal
// on line 28. index.php does the same through DIR_FS_WORKING; a CLI tool has
// no equivalent and has to say it.
gen_pull_language('phreebooks');
gen_pull_language('phreebooks', 'admin');   // COA_*_DESC, which defaults.php reads on line 28
fb_require_globals(DIR_FS_MODULES . 'phreebooks/defaults.php');
fb_require_globals(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');

// ─────────────────────────────────────────────────────────────────────────────
if ($MODE === '') {

echo "=== every locale defines the strings the page now uses ===\n";
// The lesson verify_paper_pages was written for, and it applies exactly here:
// gen_pull_language() loads ONE locale file and falls back to en_us only when
// the whole file is missing. A constant added to en_us alone is not a missing
// label in nl_nl, it is a fatal page — for whoever runs that locale, which is
// nobody testing it.
$strings = ['BNK_CURRENCY', 'BNK_CURRENCY_NOTE', 'BNK_JS_ONE_CURRENCY',
            'BNK_ERROR_MIXED_CURRENCY', 'BNK_WARN_LEGACY_CURRENCY',
            'BNK_ERROR_PAYMENT_CURRENCY'];
foreach (glob(DIR_FS_MODULES . 'phreebooks/language/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $file = $dir . '/language.php';
    if (!file_exists($file)) continue;
    $source  = (string)file_get_contents($file);
    $missing = [];
    foreach ($strings as $name) {
        if (!preg_match("#define\s*\(\s*'" . preg_quote($name, '#') . "'#", $source)) $missing[] = $name;
    }
    ok(basename($dir) . ' defines all ' . count($strings) . ' string(s)', $missing === [],
       implode(', ', $missing));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== a payment method says whether it can take a foreign currency ===\n";
fb_require_globals(DIR_FS_MODULES . 'payment/defaults.php');
gen_pull_language('payment');
require_once(DIR_FS_MODULES . 'payment/classes/payment.php');   // the base every method extends
// Conservative by default, exactly as refund() is. A method that transmits an
// amount and names DEFAULT_CURRENCY beside it charges the right number in the
// wrong currency, and nothing anybody can see says so.
$base = new ReflectionClass('payment');
$declared = $base->getDefaultProperties();
ok('the base class defaults to refusing', array_key_exists('multi_currency', $declared)
   && $declared['multi_currency'] === false,
   var_export($declared['multi_currency'] ?? '(absent)', true));

// Transmits no amount, or is genuinely told which currency to take.
$safe   = ['cod', 'directdebit', 'freecharger', 'moneyorder', 'paypal', 'square_terminal'];
// Sends an amount and assumes the company's currency beside it. Left that way on
// purpose: each needs its own change and none of them can be tested here, so the
// page refuses rather than charges. paypal_nvp is the older of the two PayPal
// methods and is NOT the one that was taught - they are separate modules.
$unsafe = ['authorizenet', 'firstdata', 'linkpoint_api', 'nova_xml', 'paypal_nvp'];
foreach (array_merge($safe, $unsafe) as $code) {
    $file = DIR_FS_MODULES . 'payment/methods/' . $code . '/' . $code . '.php';
    if (!file_exists($file)) { skip($code, 'not installed'); continue; }
    // A method names its own strings in its property defaults, so the class
    // cannot even be DEFINED until its language file is in - which is why this
    // is load_method_language() and not another gen_pull_language().
    load_method_language(DIR_FS_MODULES . 'payment/methods/', $code);
    require_once($file);
    if (!class_exists($code)) { skip($code, 'class did not load'); continue; }
    $props = (new ReflectionClass($code))->getDefaultProperties();
    $says  = !empty($props['multi_currency']);
    $want  = in_array($code, $safe, true);
    ok(sprintf('%s %s a foreign-currency entry', $code, $want ? 'accepts' : 'refuses'),
       $says === $want, 'it says ' . var_export($says, true));
}

// An installation carries more methods than the repository ships - the optional
// ones are copied in from extensions/ - so the two lists above cannot be the
// whole truth about a given machine. What must hold on EVERY machine is the
// direction of the default: anything not examined refuses. A method that claims
// otherwise without being on the safe list is one nobody has checked, claiming
// it can take money in a currency nobody has watched it send.
$unnamed = [];
foreach (glob(DIR_FS_MODULES . 'payment/methods/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $code = basename($dir);
    if (in_array($code, $safe, true) || in_array($code, $unsafe, true)) continue;
    if (!file_exists($dir . '/' . $code . '.php')) continue;
    $unnamed[] = $code;
}
$claiming = [];
foreach ($unnamed as $code) {
    load_method_language(DIR_FS_MODULES . 'payment/methods/', $code);
    require_once(DIR_FS_MODULES . 'payment/methods/' . $code . '/' . $code . '.php');
    if (!class_exists($code)) continue;
    $props = (new ReflectionClass($code))->getDefaultProperties();
    if (!empty($props['multi_currency'])) $claiming[] = $code;
}
ok('no method this list has not examined claims it can take a foreign currency',
   $claiming === [], implode(', ', $claiming));

// paypal is the one that was taught, so what it is declared to be is checked
// against what its code actually does. A property saying "safe" over a payload
// that still names DEFAULT_CURRENCY is worse than no property at all.
$ppSource = (string)@file_get_contents(DIR_FS_MODULES . 'payment/methods/paypal/paypal.php');
// Precisely: it must ACCEPT one, and reach for the company's only when told
// nothing. An earlier version of this looked for the words DEFAULT_CURRENCY and
// failed on the fallback - which is the part that has to be there, so that a
// caller with nothing to say about currency keeps behaving as it did.
ok('paypal invoicing accepts a currency from its caller',
   $ppSource !== '' && str_contains($ppSource, '$discount = 0.0, $currency = null'),
   'create_paypal_invoice() still takes no currency argument');
ok('and reaches for the company\'s only when given none',
   $ppSource !== '' && str_contains($ppSource, "if (\$currency === null) \$currency = defined('DEFAULT_CURRENCY')"),
   'the fallback is not guarded, so a currency passed in would be overwritten');
// A request for payment asks in the invoice's own currency, read from
// journal_main and never from the post, so a forged request cannot name one the
// invoice was never in.
$rqSource = (string)@file_get_contents(DIR_FS_MODULES . 'payment/ajax/request_payment.php');
ok('Request payment reads the currency off the invoice',
   $rqSource !== '' && str_contains($rqSource, 'currencies_code, currencies_value FROM " . TABLE_JOURNAL_MAIN'),
   'it does not read journal_main.currencies_code');
ok('and asks in that currency, not a posted one',
   $rqSource !== '' && str_contains($rqSource, "create_request(\$type, \$inv['id'], \$amount, \$inv['currencies_code']"),
   'create_request() is not given the invoice\'s own currency');
// And what the invoice would actually SAY, line by line, against a real
// document. Not a shape check: paypal::build_invoice_items() is called for real
// and every line is compared with the journal row it came from, times the rate.
//
// This exists because a shape check is what was here first, and it passed while
// the freight line was wrong. Freight has no `full_price`, so it comes through
// the OTHER branch of the unit-price choice - convert one and not the other and
// every product line is right, which is exactly what it looked like.
if (class_exists('paypal')) {
    $inv = $db->Execute("select id, currencies_code, currencies_value, total_amount
        from " . TABLE_JOURNAL_MAIN . " m
        where m.journal_id = 12 and m.currencies_code <> '" . DEFAULT_CURRENCY . "'
          and m.currencies_code <> '' and m.currencies_value <> 1
          and exists (select 1 from " . TABLE_JOURNAL_ITEM . " i
                      where i.ref_id = m.id and i.gl_type = 'frt' and i.credit_amount <> 0)
        order by m.id desc");
    if ($inv->EOF) {
        skip('a foreign invoice carrying freight', 'this company has none');
    } else {
        $f     = $inv->fields;
        $rate  = (float)$f['currencies_value'];
        $pp    = new paypal();
        $built = $pp->build_invoice_items($f['id'], $f['currencies_code'], $rate,
                     (float)$f['total_amount'] * $rate);

        // Every journal row, as the customer's invoice states it.
        $wanted = [];
        $rows = $db->Execute("select gl_type, sku, description, qty, credit_amount, full_price
            from " . TABLE_JOURNAL_ITEM . " where ref_id = " . (int)$f['id']
            . " and gl_type in ('sos','frt','tax') and credit_amount <> 0 order by item_cnt");
        while (!$rows->EOF) {
            $r    = $rows->fields;
            $home = (float)$r['full_price'] != 0 ? (float)$r['full_price'] : (float)$r['credit_amount'];
            $wanted[$r['gl_type']][] = number_format($home * $rate, 2, '.', '');
            $rows->MoveNext();
        }

        $got = [];
        foreach ($built['items'] as $line) {
            if ($line['name'] === 'Rounding adjustment') continue;
            $got[] = $line['unit_amount']['value'];
        }
        $flatWanted = array_merge($wanted['sos'] ?? [], $wanted['frt'] ?? [], $wanted['tax'] ?? []);
        ok('every PayPal line is its journal row at the document\'s rate',
           $got === $flatWanted,
           'got ' . implode(', ', $got) . ' — wanted ' . implode(', ', $flatWanted));

        // Named on its own, because this is the one that was wrong and the one
        // the other lines cannot speak for.
        $frtGot  = null;
        foreach ($built['items'] as $line) if ($line['name'] === 'Shipping') $frtGot = $line['unit_amount']['value'];
        $frtWant = $wanted['frt'][0] ?? null;
        ok('and the freight line in particular, which has no unit price of its own',
           $frtGot !== null && $frtGot === $frtWant,
           'got ' . var_export($frtGot, true) . ', wanted ' . var_export($frtWant, true));
        ok('and it is NOT the home-currency figure',
           $frtGot !== null && $frtGot !== number_format((float)$frtWant / $rate, 2, '.', ''),
           'freight came through unconverted');
        ok('every line names the document\'s currency',
           array_reduce($built['items'], fn($c, $l) =>
               $c && $l['unit_amount']['currency_code'] === $f['currencies_code'], true),
           'a line named some other currency');
    }
}
if ($unnamed !== []) {
    printf("  [--] %d installed method(s) not in either list, so refusing by default: %s\n",
           count($unnamed), implode(', ', $unnamed));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== an open document is priced in the currency it was raised in ===\n";
// Against a real document rather than a fixture, because the claim is about
// what this company's data does. A company with nothing foreign in it reports
// [--] rather than passing: a check with no foreign document in it would pass
// with the whole feature reverted.
define('JOURNAL_ID', 18);
// OPEN, and then the FIRST one that really turns up in its customer's list -
// fill_paid_invoice_array() has its own view of what is settleable, and a
// document that agrees with the `closed` column and not with the function would
// otherwise skip the whole section rather than checking anything.
$foreign = $db->Execute("select id, journal_id, bill_acct_id, total_amount, currencies_code, currencies_value
    from " . TABLE_JOURNAL_MAIN . "
    where journal_id in (12, 13) and currencies_code <> '" . DEFAULT_CURRENCY . "'
      and currencies_code <> '' and bill_acct_id > 0 and closed = '0' order by id desc");
$doc = $seen = null;
while (!$foreign->EOF) {
    $candidate = $foreign->fields;
    $rows = fill_paid_invoice_array(0, $candidate['bill_acct_id'], 'c');
    foreach ($rows['invoices'] as $row) if ($row['id'] == $candidate['id']) {
        $doc = $candidate; $seen = $row; break 2;
    }
    $foreign->MoveNext();
}
if ($seen === null) {
    skip('a foreign customer document that is still open', 'this company has none');
} else {
    {
        ok('the row carries the document\'s own currency',
           $seen['currencies_code'] === $doc['currencies_code'],
           $seen['currencies_code'] . ' vs ' . $doc['currencies_code']);
        ok('and prices the amount in it, not in ' . DEFAULT_CURRENCY,
           $seen['display_code'] === $doc['currencies_code'],
           'displayed in ' . $seen['display_code']);
        // The number, not just the label - and computed from the DATABASE rather
        // than from the figure being checked.
        //
        // The first version of this took the displayed amount, divided by the
        // rate and multiplied by it again, which is arithmetic that agrees with
        // itself whatever the page did: both checks passed with the conversion
        // reverted. What makes them mean something is that the expected value
        // comes from journal_main, through the same partial-payment subtraction
        // and the same credit-memo sign the function itself applies.
        $outstanding = (float)$doc['total_amount'] - fetch_partially_paid($doc['id']);
        if (in_array((int)$doc['journal_id'], [7, 13], true)) $outstanding = -$outstanding;
        ok('and the figure is the stored one at the document\'s own rate',
           $seen['total_amount'] === $currencies->format($outstanding, true,
               $doc['currencies_code'], $doc['currencies_value']),
           $seen['total_amount'] . ' vs ' . $currencies->format($outstanding, true,
               $doc['currencies_code'], $doc['currencies_value']));
        ok('and it is NOT the home-currency figure it used to show',
           $seen['total_amount'] !== $currencies->format($outstanding),
           'the rate made no difference, which it must on a foreign document');

        // The other mode: a STORED record prices every row in the record's own
        // currency, so the column adds up. Asked for the home currency here,
        // which is what a legacy receipt carries.
        $flat = fill_paid_invoice_array(0, $doc['bill_acct_id'], 'c', DEFAULT_CURRENCY, 1);
        $seenFlat = null;
        foreach ($flat['invoices'] as $row) if ($row['id'] == $doc['id']) { $seenFlat = $row; break; }
        ok('asked for one currency, every row comes back in it',
           $seenFlat !== null && $seenFlat['display_code'] === DEFAULT_CURRENCY,
           $seenFlat === null ? 'row vanished' : $seenFlat['display_code']);
        ok('and the document still says what it was really raised in',
           $seenFlat !== null && $seenFlat['currencies_code'] === $doc['currencies_code'],
           $seenFlat === null ? 'row vanished' : $seenFlat['currencies_code']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== raising a credit memo from a foreign invoice ===\n";
// The path the user takes before any of the above: Credit Memo on a USD sales
// invoice, which is orders?jID=13&oID=<invoice>&action=prc_so and loads over
// AJAX through load_order.php.
//
// That response feeds fillOrder(), which writes any OrderData tag into the form
// field of the same name - so journal_main.freight, a HOME-currency column,
// landed in a box that recalculateCurrencies() treats as being in the display
// currency. On the ordinary paths an frt line item follows and orders.js
// overwrites the box with the item's converted total; on THIS one the source
// document's items are read by the loop that keeps only stock lines, so nothing
// does. Driven as a child process because load_order.php defines JOURNAL_ID.
$cmCmd = sprintf('%s -d display_errors=1 %s %s cm 2>&1',
    escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__), escapeshellarg($company));
$cmOut = []; $cmStatus = 0;
exec($cmCmd, $cmOut, $cmStatus);
echo implode("\n", $cmOut) . "\n";
if ($cmStatus !== 0) $failures++;

echo "\n=== the page, once per mode ===\n";
$modes = ['j20c', 'j18c'];
$anyChild = 0;
foreach ($modes as $mode) {
    $cmd = sprintf('%s -d display_errors=1 %s %s %s 2>&1',
        escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__),
        escapeshellarg($company), escapeshellarg($mode));
    $out = []; $status = 0;
    exec($cmd, $out, $status);
    echo implode("\n", $out) . "\n";
    if ($status !== 0) $anyChild++;
}
$failures += $anyChild;

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
}

// ─────────────────────────────────────────────────────────────────────────────
if ($MODE === 'cm') {

echo "\n--- orders jID=13 action=prc_so (credit memo from an invoice) ---\n";
$inv = $db->Execute("select m.id, m.purchase_invoice_id, m.bill_acct_id, m.freight,
        m.currencies_code, m.currencies_value
    from " . TABLE_JOURNAL_MAIN . " m
    where m.journal_id = 12 and m.currencies_code <> '" . DEFAULT_CURRENCY . "'
      and m.currencies_code <> '' and m.currencies_value <> 1 and m.freight <> 0
    order by m.id desc");
if ($inv->EOF) { skip('a foreign invoice carrying freight', 'this company has none'); exit(0); }
$doc = $inv->fields;

$_SESSION['admin_security'] = array_fill(0, 1000, 4);
$_GET = $_REQUEST = ['module' => 'phreebooks', 'page' => 'ajax', 'op' => 'load_order',
                     'oID' => (string)$doc['id'], 'cID' => (string)$doc['bill_acct_id'],
                     'jID' => '13', 'so_po' => '', 'ship_only' => ''];
$_POST = [];

// The file ends in die(), which would take this process with it - so it runs in
// a child of its own and the XML is read back off its stdout. Nothing else can
// both let it finish and see what it wrote.
$runner = tempnam(sys_get_temp_dir(), 'fb_lo_') . '.php';
file_put_contents($runner, "<?php\n"
  . "require_once(" . var_export(__DIR__ . '/bootstrap_freebooks.php', true) . ");\n"
  . "fb_bootstrap(" . var_export($company, true) . ");\n"
  // The same order a page uses; load_order.php requires phreebooks/defaults.php,
  // which reads COA_*_DESC out of the admin strings on its line 28.
  . "gen_pull_language('phreebooks');\n"
  . "gen_pull_language('phreebooks', 'admin');\n"
  . "gen_pull_language('inventory');\n"
  // application_top.php defines this for every request; the bootstrap does not,
  // and load_order.php explodes it on line 35.
  . "if (!defined('COG_ITEM_TYPES')) define('COG_ITEM_TYPES', 'si,sr,ms,mi,ma,sa');\n"
  . "\$_SESSION['admin_security'] = array_fill(0, 1000, 4);\n"
  . "\$_GET = \$_REQUEST = " . var_export($_GET, true) . ";\n"
  . "ob_start();\n"
  . "require(DIR_FS_MODULES . 'phreebooks/ajax/load_order.php');\n");
$lines = []; $st = 0;
exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . escapeshellarg($runner) . ' 2>&1', $lines, $st);
@unlink($runner);
$xmlOut = implode("\n", $lines);

ok('the response is XML', str_contains($xmlOut, '<OrderData>'), substr($xmlOut, 0, 120));
$got = null;
// xmlEntry() wraps every value in CDATA, so the tag body is not the value.
if (preg_match('#<freight>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</freight>#s', $xmlOut, $m)) $got = trim($m[1]);
$want = $currencies->format((float)$doc['freight'], true, $doc['currencies_code'], $doc['currencies_value']);
ok('the freight it hands the form is in the invoice\'s currency',
   $got !== null && $got === $want,
   'got ' . var_export($got, true) . ', wanted ' . $want
   . ' (stored ' . $doc['freight'] . ' ' . DEFAULT_CURRENCY . ' at ' . $doc['currencies_value'] . ')');
// Numerically, not as a string. The stored value has four decimal places and
// format() gives two, so comparing the two spellings let 22.4988 pass as "not
// the home-currency figure" when that is exactly what it was.
ok('and not the home-currency figure it used to hand over',
   $got !== null && abs((float)$currencies->clean_value($got, $doc['currencies_code'])
                        - (float)$doc['freight']) > 0.005,
   'freight came through unconverted - a memo raised from invoice '
   . $doc['purchase_invoice_id'] . ' would understate the shipping');

echo ($failures === 0 ? "  credit memo: ok\n" : "  credit memo: FAILED $failures check(s)\n");
exit($failures === 0 ? 0 : 1);
}

// ─────────────────────────────────────────────────────────────────────────────
// One page mode per process: pages/bills/pre_process.php defines JOURNAL_ID and
// GL_TYPE, and a constant cannot be defined twice with different values.
// ─────────────────────────────────────────────────────────────────────────────
[$jID, $type] = $MODE === 'j18c' ? [18, 'c'] : [20, 'c'];
echo "\n--- bills jID=$jID type=$type ---\n";

// A foreign document to raise the entry from. Without one there is nothing to
// check, and saying so beats passing.
// OPEN, because a closed one has nothing outstanding and every amount below
// would be 0.00 - which agrees with itself whatever the rate does, and is the
// shape of vacuous check this suite keeps finding in itself.
$src = $db->Execute("select id, currencies_code, currencies_value, total_amount
    from " . TABLE_JOURNAL_MAIN . "
    where journal_id in (12, 13) and currencies_code <> '" . DEFAULT_CURRENCY . "'
      and currencies_code <> '' and bill_acct_id > 0 and closed = '0' order by id desc");
if ($src->EOF) { skip('raising an entry from an OPEN foreign document', 'this company has none'); exit(0); }
$doc = $src->fields;

define('DIR_FS_WORKING', DIR_FS_MODULES . 'phreebooks/');
// validate_user() wants nothing else, and without it the page redirects and
// exits INSIDE the output buffer - no output, status 0, and a run that reads as
// a page with nothing to say rather than as one that never rendered.
$_SESSION['admin_security'] = array_fill(0, 1000, 4);
// The theme supplies js_calendar_init(); index.php loads a theme and a CLI tool
// does not, so the page's script would die on a function that is not missing so
// much as un-loaded.
fb_require_globals(DIR_FS_ADMIN . 'themes/' . (defined('DEFAULT_THEME') ? DEFAULT_THEME : 'default') . '/config.php');
// application_top.php makes this one for every request; a template that adds an
// icon to it gets "modify property on null" without it, which reads as a broken
// template rather than as a missing bootstrap.
$toolbar = new toolbar;
$_SESSION['admin_prefs'] = $_SESSION['admin_prefs'] ?? ['def_cash_acct' => '', 'def_store_id' => 0];
$_GET = $_REQUEST = ['module' => 'phreebooks', 'page' => 'bills', 'jID' => (string)$jID,
                     'type' => $type, 'action' => 'pmt', 'oID' => (string)$doc['id']];
$_POST = [];

$problems = [];
set_error_handler(function ($no, $str, $file, $line) use (&$problems) {
    if (str_contains($file, '/modules/phreebooks/')) $problems[] = basename($file) . ":$line $str";
    return true;
});

$fatal = null; $output = '';
try {
    ob_start();
    require(DIR_FS_WORKING . 'pages/bills/pre_process.php');
    ob_start();
    require(DIR_FS_WORKING . 'pages/bills/js_include.php');
    $script = (string)ob_get_clean();
    ob_start();
    require(DIR_FS_WORKING . 'pages/bills/template_main.php');
    $output = $script . (string)ob_get_clean();
    ob_get_clean();
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    $fatal = get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}
restore_error_handler();

ok('the page renders without throwing', $fatal === null, (string)$fatal);

// NOT "the page is silent". It is not, and it was not before this change: the
// bills template reads a dozen properties a fresh banking object never defines,
// which is an undefined-property notice apiece on every real page load. Cleaning
// those up is worth doing and is not this. What IS claimed is the narrower thing
// this change is answerable for - that none of the new fields are among them,
// which is what a mis-spelled property name would look like.
$mine = array_values(array_filter($problems, fn($p) =>
    stripos($p, 'currenc') !== false || stripos($p, 'curr_') !== false));
ok('none of the currency fields raise a notice', $mine === [], implode(' | ', $mine));
printf("  [--] %d pre-existing notice(s) on this page, none of them from the currency work\n",
       count($problems) - count($mine));

// The entry took the document's currency, not the company's. This is the issue
// as reported: "the customer payment is in GBP only".
ok('the entry took the document\'s currency',
   isset($order->currencies_code) && $order->currencies_code === $doc['currencies_code'],
   ($order->currencies_code ?? '(none)') . ' vs ' . $doc['currencies_code']);
// At the document's OWN rate rather than today's, so settling it closes it to
// exactly zero instead of leaving a few pence nobody owes.
ok('at the rate that document was raised at, not today\'s',
   isset($order->currencies_value) && abs((float)$order->currencies_value - (float)$doc['currencies_value']) < 1e-9,
   ($order->currencies_value ?? '(none)') . ' vs ' . $doc['currencies_value']);
ok('and today\'s rate is a different number, so that mattered',
   abs((float)$doc['currencies_value'] - (float)$currencies->currencies[$doc['currencies_code']]['value']) > 1e-9,
   'the two rates agree, so this check proved nothing');

// The amount offered is the one the customer would recognise.
$outstanding = (float)$doc['total_amount'] - fetch_partially_paid($doc['id']);
$want = $currencies->format($outstanding, true, $doc['currencies_code'], $doc['currencies_value']);
ok('the amount offered is the document\'s own figure', ($order->amt_1 ?? '') === $want,
   ($order->amt_1 ?? '(none)') . ' vs ' . $want);
// And it is NOT the home-currency one, or the rate did nothing and the check
// would pass with the whole conversion removed.
ok('and not the home-currency equivalent it used to offer',
   ($order->amt_1 ?? '') !== $currencies->format($outstanding),
   'the two agree, so this proved nothing');

// The page carries both, or the save has nothing to read.
ok('the form posts the currency back', str_contains($output, 'name="currencies_code"'));
ok('and the rate with it',              str_contains($output, 'name="currencies_value"'));
ok('the row says which currency it is in', str_contains($output, 'name="curr_1"'));
ok('and the heading names the entry\'s currency rather than the company\'s',
   str_contains($output, 'id="currency_label"'));

echo ($failures === 0 ? "  jID=$jID: ok\n" : "  jID=$jID: FAILED $failures check(s)\n");
exit($failures === 0 ? 0 : 1);
