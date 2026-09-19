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
//  Path: /docs/tools/verify_forms_forms.php
//
/**
 * Every shipped FORM is routed, selected and rendered (design §11.16).
 *
 * verify_forms_app.php does this for reports and stops at forms, because until
 * now Forms declined them: printing one is driven from the document it belongs
 * to, and nothing drove it. FormRunner and RecordSelector are that missing
 * half, and this is what holds them to the same standard.
 *
 * The claim is narrow and worth stating: not that the figures are right —
 * diff_forms_provider.php proves those against a PhreeForm baseline, per
 * provider — but that a stored form definition plus the four URL parameters a
 * Print button sends produce a PDF, for every form in the catalog at once.
 *
 * Records are found rather than hard-coded, because a fixture id that no longer
 * exists is a tool that reports a green nothing. Each form's record table is
 * asked for its most recent rows, filtered the way the definition filters — and
 * SEVERAL are tried, because some filters live on a joined table the fixture
 * query does not reach (the Credit Memo wants a journal_item whose gl_type is
 * 'sos') and only the provider knows about those.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_forms.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
$company = fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');

use FreeBooks\Forms\App\{FormPlan, FormRunner, RecordSelector};
use FreeBooks\Forms\Catalog\{Catalog, ShippedDatasets};
use FreeBooks\Forms\Definition\DefinitionLoader;
use FreeBooks\Forms\Document\{LayoutDocument, RenderOptions};
use FreeBooks\Forms\Render\{Formatter, LayoutPdfRenderer};

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);

// This tool exercises the LEGACY route — plan()/run() over a get_report_details()
// object — which is the route step 6 deletes. Until it is repointed at
// planDefinition()/runDefinition(), it reads the PhreeForm original wherever one
// survives, so a migrated company is still covered rather than silently
// reporting "0 forms ran". See §13.32.
$forms_loader = new DefinitionLoader($catalog);
$runner   = new FormRunner($db, $catalog);
$selector = new RecordSelector($db);

$renderer = new LayoutPdfRenderer(
    formatter:      new Formatter($currencies, DEFAULT_CURRENCY, forms_formatter_lookups()),
    defaultFont:    defined('FORMS_DEFAULT_FONT') ? FORMS_DEFAULT_FONT : 'freeserif',
    rowSpacing:     (float)FORMS_DEFAULT_ROWSPACE,
    continuedLabel: defined('TEXT_CONTINUED') ? TEXT_CONTINUED : 'Continued',
    pageOfLabel:    defined('TEXT_OF') ? TEXT_OF : 'of',
    imageDirectory: FORMS_DIR_IMAGES,
  linkedImageDirectory: DIR_FS_MY_FILES . $_SESSION['company'] . '/',
);

/**
 * A record this form could actually print, found from the definition itself.
 *
 * The definition's own filterlist is what makes a Purchase Order a purchase
 * order rather than any journal row, so the same filters pick the fixture. A
 * form whose table is empty on this company yields nothing, which is data
 * coverage rather than a defect and is counted separately.
 */
function findRecords(\queryFactory $db, object $report, string $table, string $key = 'id', int $limit = 12): array
{
    if ($table === '') return [];
    $qualified = (defined('DB_PREFIX') ? DB_PREFIX : '') . $table;

    // Every column the record table actually has. Needed because a filter name
    // may be UNQUALIFIED and belong to another table entirely — pf_58 is a
    // Customer Statement whose record table is contacts, and it filters on a
    // bare `journal_id`, which contacts does not have. Without this check the
    // query is a SQL error, and queryFactory::set_error() answers a SQL error
    // with die() — so the whole tool exited silently, with status 0, having
    // printed only its header. A dev tool must not be able to fail that way.
    $columns = [];
    $meta = $db->Execute("select column_name from information_schema.columns
        where table_schema = database() and table_name = '" . $db->prepare_input($qualified) . "'");
    while ($meta && !$meta->EOF) {
        $columns[strtolower((string)$meta->fields['column_name'])] = true;
        $meta->MoveNext();
    }
    if (!isset($columns[strtolower($key)])) return [];

    $where = [];
    foreach ((array)($report->filterlist ?? []) as $filter) {
        $name = strtolower((string)($filter->fieldname ?? ''));
        $min  = (string)($filter->min_val ?? '');
        if ($name === '' || $min === '') continue;
        $column = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
        // Only filters on the record table itself; a filter on a joined table
        // (the address-book type, say) does not narrow which record to print.
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $column)) continue;
        if (str_contains($name, '.') && !str_starts_with($name, $table . '.')) continue;
        if (!isset($columns[$column])) continue;
        // A legacy filter value may be a LIST — the Deposit Slip's journal
        // filter holds "2, 18, 19". Comparing the column to that string makes
        // MySQL cast it to 2, so the fixture came from the wrong journal and
        // its break value was empty. The runner splits these; so must this.
        if (str_contains($min, ',')) {
            $values = array_filter(array_map('trim', explode(',', $min)), fn($v) => $v !== '');
            if ($values === []) continue;
            $quoted = array_map(fn($v) => "'" . $db->prepare_input($v) . "'", $values);
            $where[] = "`$column` in (" . implode(',', $quoted) . ")";
            continue;
        }
        $where[] = "`$column` = '" . $db->prepare_input($min) . "'";
    }
    $sql = "select distinct `$key` from `$qualified`"
         . ($where ? ' where ' . implode(' and ', $where) : '')
         . " order by `$key` desc limit " . (int)$limit;
    $ids    = [];
    $result = $db->Execute($sql);
    while ($result && !$result->EOF) {
        $value = (string)$result->fields[$key];
        if ($value !== '') $ids[] = $value;
        $result->MoveNext();
    }
    return $ids;
}

// Every form in the catalog, in id order.
$forms  = [];
$result = $db->Execute("select id, doc_title from " . TABLE_PHREEFORM
    . " where doc_ext = 'frm' order by id");
while (!$result->EOF) {
    $forms[(int)$result->fields['id']] = (string)$result->fields['doc_title'];
    $result->MoveNext();
}
printf("company '%s', %d form definition(s)\n\n", $company, count($forms));

echo "=== every form is routed, and every route renders ===\n";
$counts   = ['provider' => 0, 'dataset' => 0, 'unsupported' => 0];
$rendered = 0;
$declined = $noRecord = [];

foreach ($forms as $id => $title) {
    $report = legacyOriginal($forms_loader, $id);
    if (!$report) continue;

    $plan = $runner->plan($report);
    $counts[$plan->kind]++;
    if (!$plan->runnable()) { $declined[] = "pf_$id ($title): " . implode(' ', $plan->notes); continue; }

    // On the key the form BREAKS on, not blindly on `id` — see FormPlan::$recordKey.
    $candidates = findRecords($db, $report, $plan->recordTable, $plan->recordKey);
    if ($candidates === []) {
        $noRecord[] = "pf_$id ($title): no row in {$plan->recordTable}.{$plan->recordKey}";
        continue;
    }

    // The definition must survive the run untouched — the legacy engine
    // consumes one as it reads it, so a shared object would come back gutted.
    $before   = serialize($report);
    $doc      = null;
    $recordId = null;
    $threw    = null;

    foreach ($candidates as $candidate) {
        try {
            $built = $runner->run($report, [$candidate], ['dates' => (string)($report->datedefault ?? 'a')]);
        } catch (\Throwable $e) {
            $threw = $e;
            break;
        }
        if ($built instanceof LayoutDocument && count($built->records) > 0) {
            $doc      = $built;
            $recordId = $candidate;
            break;
        }
        $doc = $built;      // keep the last, so the type check below still runs
    }

    if ($threw !== null) {
        ok("pf_$id ($title) runs", false, get_class($threw) . ': ' . $threw->getMessage());
        continue;
    }

    if (serialize($report) !== $before) {
        ok("pf_$id ($title) leaves its definition untouched", false, 'the runner mutated it');
        continue;
    }

    if (!$doc instanceof LayoutDocument) {
        ok("pf_$id ($title) produces a LayoutDocument", false, get_class($doc));
        continue;
    }
    if ($recordId === null) {
        $noRecord[] = sprintf('pf_%d (%s): none of %d candidate %s.%s value(s) built a record',
            $id, $title, count($candidates), $plan->recordTable, $plan->recordKey);
        continue;
    }

    $out = $renderer->render($doc, new RenderOptions(filename: "pf{$id}.pdf"));
    $sane = str_starts_with($out->bytes, '%PDF')
         && $out->size() > 500
         && $out->mimeType === 'application/pdf';
    if (!$sane) {
        ok("pf_$id ($title) renders", false,
            sprintf('%d bytes, starts %s, %s', $out->size(), substr($out->bytes, 0, 4), $out->mimeType));
        continue;
    }
    $rendered++;

    // A group holding SEVERAL forms, so the page check can drive the chooser
    // branch — the one a caller reaches by clicking Print on an invoice, and
    // the one whose links were broken while every rID-driven check passed.
    // Named for the company. The record ids in these files mean nothing outside
    // the database they came from, and an unqualified name let one company's
    // invoice be printed against another on the next run: the page selected no
    // records, and the suite reported the print path broken. A fixture that
    // crosses companies can fail for the wrong reason, and can as easily pass
    // for one.
    if (!file_exists(sys_get_temp_dir() . '/forms-print-group-' . $company)) {
        $group = $db->Execute("select doc_group, count(*) n from " . TABLE_PHREEFORM . "
            where doc_ext = 'frm' group by doc_group having n > 1 order by n desc limit 1");
        if ($group && !$group->EOF) {
            file_put_contents(sys_get_temp_dir() . '/forms-print-group-' . $company,
                http_build_query([
                    'gID'  => (string)$group->fields['doc_group'],
                    'xfld' => $plan->recordTable . '.' . $plan->recordKey,
                    'xcr'  => 'EQUAL',
                    'xmin' => $recordId,
                    'date' => 'a',
                ]));
        }
    }

    // Publish one proven (form, record) pair for verify_forms_pages.php to drive
    // the print page with. Passed through a file for the same reason the main
    // page's run URL is: the page check runs one page per process, and a pair
    // discovered here is one that is known to produce a document.
    if (!file_exists(sys_get_temp_dir() . '/forms-print-target-' . $company)) {
        file_put_contents(sys_get_temp_dir() . '/forms-print-target-' . $company,
            http_build_query([
                'rID'  => $id,
                'xfld' => $plan->recordTable . '.' . $plan->recordKey,
                'xcr'  => 'EQUAL',
                'xmin' => $recordId,
                'date' => (string)($report->datedefault ?? 'a'),
            ]));
    }
}

// A company seeded NATIVE has no PhreeForm original for any form, so this tool
// — which tests the legacy route — has nothing to route. That is the ordinary
// state of every fresh install and, after step 6, of every company; it is
// reported rather than failed, because a suite that goes red on a correct
// installation is a suite people learn to ignore.
//
// It is also the clearest possible statement of why this tool must be
// repointed at planDefinition()/runDefinition() when PhreeForm goes (§13.32):
// what it covers shrinks to nothing on its own.
$routed = $counts['provider'] + $counts['dataset'] + $counts['unsupported'];
if ($routed === 0) {
    echo "  [--] no form on this company has a PhreeForm original to route.\n"
       . "       This tool exercises the legacy engine, which such a company has\n"
       . "       never used. Nothing was tested here, and nothing is wrong.\n";
} else {
    ok(sprintf('%d form(s) ran and rendered as PDF', $rendered), $rendered > 0);
}
printf("  routing: %d by provider, %d by dataset, %d declined; %d had no record on this company\n",
    $counts['provider'], $counts['dataset'], $counts['unsupported'], count($noRecord));
foreach ($declined as $note) echo "  [--] $note\n";
// Named rather than counted: "3 had no record" hides the difference between a
// company that lacks the data and a provider that silently built nothing.
foreach ($noRecord as $note) echo "  [--] $note\n";

// A company where every form found nothing proves nothing at all — the vacuous
// pass this suite has been caught by before.
if ($routed > 0) {
    ok('the run produced documents, not a set of empty ones', $rendered > count($noRecord),
       $rendered . ' rendered, ' . count($noRecord) . ' empty');
}

echo "\n=== the Print button's parameters select the right records ===\n";
// EQUAL on the record key, which is what the orders and bills pages send.
$sample = null;
foreach ($forms as $id => $title) {
    $report = legacyOriginal($forms_loader, $id);
    if (!$report) continue;
    $plan = $runner->plan($report);
    if (!$plan->runnable() || $plan->recordTable !== 'journal_main') continue;
    $found = findRecords($db, $report, 'journal_main', 'id', 1);
    if ($found !== []) { $sample = [$id, $title, $report, $found[0]]; break; }
}

if ($sample === null) {
    echo "  [--] no journal-backed form with a record on this company\n";
} else {
    [$id, $title, $report, $recordId] = $sample;
    $equal = $selector->resolve('journal_main', 'journal_main.id', 'EQUAL', $recordId);
    ok("EQUAL picks exactly the record asked for", $equal->ids === [$recordId],
       'got ' . json_encode($equal->ids));

    $list = $selector->resolve('journal_main', 'journal_main.id', 'IN_LIST', $recordId . ',' . $recordId);
    ok('IN_LIST accepts a comma list', count($list->ids) >= 1, json_encode($list->ids));

    // RANGE on a column that is NOT the break field is the bulk-bills case, and
    // the one that has to go to the database.
    $range = $selector->resolve('journal_main', 'journal_main.purchase_invoice_id', 'RANGE', '0', 'zzzz');
    ok('RANGE on another column resolves to keys, not to itself',
       !$range->isEmpty(), implode(' ', $range->notes));

    echo "\n=== a URL cannot choose its own table or column ===\n";
    // xfld reaches a FROM and a WHERE. These are the shapes that would matter.
    foreach ([
        'a quoted column'      => "id' or '1'='1",
        'a stacked statement'  => 'id; drop table contacts',
        'a comment'            => 'id -- ',
        'a subselect'          => '(select 1)',
    ] as $what => $probe) {
        $bad = $selector->resolve('journal_main', $probe, 'EQUAL', $recordId);
        ok("$what is refused rather than escaped", $bad->isEmpty(), 'it returned ' . count($bad->ids) . ' id(s)');
    }
    // The table qualifier is discarded, so naming another table cannot redirect
    // the lookup — the record table comes from the form's plan, not the URL.
    $other = $selector->resolve('journal_main', 'users.admin_id', 'EQUAL', $recordId);
    ok('a foreign table qualifier does not redirect the lookup',
       $other->isEmpty() || $other->ids === [$recordId],
       'got ' . json_encode($other->ids));
}

echo "\n=== a form for an uninstalled module is declined, not fataled ===\n";
$woForm = null;
foreach ($forms as $id => $title) {
    $report = legacyOriginal($forms_loader, $id);
    if ($report && ($report->special_class ?? '') === 'wo_builder') { $woForm = [$id, $report]; break; }
}
if ($woForm === null) {
    echo "  [--] no work-order form on this company\n";
} elseif (defined('TABLE_WO_JOURNAL_MAIN')) {
    echo "  [--] the work_orders module IS installed here, so the guard cannot fire\n";
} else {
    [$id, $report] = $woForm;
    $plan = $runner->plan($report);
    ok("pf_$id is declined because its module is absent", !$plan->runnable(), implode(' ', $plan->notes));
    $threw = false;
    try { $runner->run($report, [1]); } catch (\RuntimeException $e) { $threw = true; }
    ok('...and running it anyway throws rather than fatals', $threw);
}


// ── offering an author a record to draw against (§13.53) ─────────────────────
//
// The canvas's record picker asks this class three questions the print page
// never did: what records are there, what is one called, and which one does a
// person's own reference name. All three reach a query, two of them carrying a
// value somebody typed, so they are checked here for what they return AND for
// what they refuse.
echo "\n=== the records an author may draw a form against ===\n";

$recent = $selector->recent('journal_main', 'id', 5);
ok('recent() offers records from the record table', count($recent) > 0 && count($recent) <= 5,
   count($recent) . ' offered');
ok('and names every one of them', $recent !== []
   && count(array_filter($recent, fn($label) => trim((string)$label) !== '')) === count($recent));

// The no-regression property, and the reason recent() does not simply drop what
// it cannot name: this list is also the list the canvas TRIES, so a record table
// with no reference columns must still yield candidates or the canvas silently
// stops showing real data for those forms.
$unnamed = $selector->recent('inventory', 'id', 3);
// Compared as strings on both sides, because PHP turns a numeric-string array
// key into an integer — so the keys come back as ints and the labels as the
// strings they were built from. Worth knowing rather than working around: a
// caller doing a strict in_array() against these keys matches nothing.
ok('a table it cannot name still yields records, labelled by their own key',
   $unnamed !== [] && array_map('strval', array_keys($unnamed)) === array_values($unnamed),
   count($unnamed) . ' offered');

// Restricted to the kind of transaction a form actually prints (§13.54).
// journal_main holds every transaction there is, so an unrestricted list offered
// receipts and sales orders while an author laid out an invoice.
$journalOf = function (array $records) use ($db): array {
    if ($records === []) return [];
    $ids = implode(',', array_map('intval', array_keys($records)));
    $out = [];
    $r = $db->Execute("select distinct journal_id from " . TABLE_JOURNAL_MAIN . " where id in ($ids)");
    while ($r && !$r->EOF) { $out[] = (int)$r->fields['journal_id']; $r->MoveNext(); }
    sort($out);
    return $out;
};
foreach (['cust:inv' => 12, 'cust:so' => 10, 'vend:po' => 4] as $group => $journal) {
    $only = $selector->recent('journal_main', 'id', 20, $group);
    if ($only === []) {
        echo "  [--] this company has no $group records, so the restriction was not exercised\n";
        continue;
    }
    ok("a $group picker offers only journal $journal",
       $journalOf($only) === [$journal], implode(',', $journalOf($only)));
}

// Unrestricted stays unrestricted: the print page passes no group and must keep
// seeing what it always saw. Only meaningful where the company holds more than
// one kind of transaction, which is why it is asserted rather than assumed.
$mixed = $journalOf($selector->recent('journal_main', 'id', 20));
ok('with no group, every kind of transaction is still offered', count($mixed) > 1,
   implode(',', $mixed) . ' — the print page would have lost records');

// A group nobody has mapped must not restrict to nothing, which is the failure
// a wrong guess would produce: a picker gone empty with no reason on screen.
ok('a group the map does not know restricts nothing',
   $journalOf($selector->recent('journal_main', 'id', 20, 'no:such-group')) === $mixed);

$oneKey = (string)array_key_first($recent);
ok('label() names a record that exists',
   trim((string)$selector->label('journal_main', 'id', $oneKey)) !== '');
ok('and answers null for one that does not',
   $selector->label('journal_main', 'id', '999999999') === null);
ok('and null for a blank id, rather than the first record it finds',
   $selector->label('journal_main', 'id', '') === null);

// A reference is what a person calls a record — an invoice number — and it is
// typed, so it is the value most likely to arrive malformed.
$reference = trim((string)($db->Execute("select purchase_invoice_id from " . TABLE_JOURNAL_MAIN
    . " where purchase_invoice_id <> '' order by id desc limit 1")->fields['purchase_invoice_id'] ?? ''));
if ($reference === '') {
    echo "  [--] no record on this company carries a reference, so the search was not exercised\n";
} else {
    $found = $selector->findByReference('journal_main', 'id', $reference, 5);
    ok('findByReference() finds a record by what a person calls it', $found !== [],
       'searched for a reference this company holds and found nothing');
    ok('and the record it found carries that reference',
       $found !== [] && str_contains((string)reset($found), $reference),
       $found === [] ? 'nothing found' : (string)reset($found));
}
ok('a reference nothing matches finds nothing, rather than everything',
   $selector->findByReference('journal_main', 'id', 'zzz-no-such-reference-zzz') === []);

// The identifier rule, in the two places these methods could have broken it.
// A column name is never quoted into a query, it is refused — so a plan naming
// a column the table has lost answers empty rather than erroring, and a value
// carrying quotes is escaped rather than closing the string.
ok('a key column the table does not have is refused, not quoted',
   $selector->recent('journal_main', 'no_such_column_at_all', 3) === []);
ok('a key column that is not an identifier at all is refused',
   $selector->recent('journal_main', 'id`; drop table x; --', 3) === []);
$quoted = "' or '1'='1";
ok('a reference carrying quotes matches nothing rather than everything',
   $selector->findByReference('journal_main', 'id', $quoted) === []);
ok('and so does an id carrying them',
   $selector->label('journal_main', 'id', $quoted) === null);

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
