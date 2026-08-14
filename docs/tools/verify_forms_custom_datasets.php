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
//  Path: /docs/tools/verify_forms_custom_datasets.php
//
/**
 * Verify the admin-defined dataset tier (design §3.2, §3.3).
 *
 * This is the feature that reintroduces the capability PhreeForm was replaced
 * for having, so the assertions are almost entirely adversarial. The claim being
 * tested is precise:
 *
 *   A dataset author picks identifiers; they never supply SQL syntax. The worst
 *   a careless author can do is expose a table they should not have. They cannot
 *   inject SQL, stack a query, UNION, or reach the sensitive tables.
 *
 * So the checks come in three kinds. That an ordinary dataset works end to end
 * and actually queries. That every hostile input is refused — by NAME, at the
 * boundary, rather than escaped somewhere downstream. And that a definition
 * already in the database is re-validated on the way out, because a row is
 * reachable by anything with database access and by a restore of an older
 * backup.
 *
 * It creates and drops its own datasets, all named with a `zzcheck_` prefix, and
 * touches nothing else. Safe against a live company.
 *
 *   docker exec <c> php -d display_errors=1 \
 *       /opt/repo/docs/tools/verify_forms_custom_datasets.php [company]
 */

require_once(__DIR__ . '/bootstrap_freebooks.php');
fb_bootstrap($argv[1] ?? null);
require_once(DIR_FS_MODULES . 'forms/classes/document/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/catalog/load.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/query_provider.php');
require_once(DIR_FS_MODULES . 'forms/classes/providers/report_provider.php');

use FreeBooks\Forms\Catalog\{Catalog, CatalogException, CustomDatasetStore, Dataset,
    DatasetTier, QueryBuilder, SchemaInspector, ShippedDatasets};
use FreeBooks\Forms\Document\{ReportColumn, TableDocument};
use FreeBooks\Forms\Provider\ReportDocumentProvider;

$failures = 0;
function ok(string $what, bool $cond, string $detail = ''): void {
    global $failures;
    if ($cond) { echo "  [ok] $what\n"; }
    else { $failures++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/** Assert that saving a definition is refused, and refused for the right reason. */
function refuses(CustomDatasetStore $store, string $what, array $input, string $expect = ''): void {
    try {
        $store->save($input, true);
        ok($what, false, 'it was ACCEPTED');
        $store->delete((string)($input['id'] ?? ''));
    } catch (\Throwable $e) {
        $matched = $expect === '' || stripos($e->getMessage(), $expect) !== false;
        ok($what, $matched, 'refused, but for a different reason: ' . $e->getMessage());
    }
}

// Forms is installed per company, so its table constant is not defined on one
// that has not enabled it. Skipping says so; the fatal this replaces said
// "Undefined constant TABLE_FORMS_DATASETS" and read like a broken tool.
if (!defined('TABLE_FORMS_DATASETS')) {
    echo "  [--] Forms is not installed on this company; there are no custom datasets to check.\n";
    exit(0);
}

$schema = new SchemaInspector($db);
$store  = new CustomDatasetStore($db, $schema, TABLE_FORMS_DATASETS);

// Clean up anything a previous interrupted run left behind.
foreach (['zzcheck_ds', 'zzcheck_roundtrip', 'zzcheck_shadow', 'contacts'] as $leftover) {
    $db->ExecutePrepared("delete from " . TABLE_FORMS_DATASETS . " where id = ?", ['zzcheck_' . ltrim($leftover, 'zzcheck_')]);
}
$db->ExecutePrepared("delete from " . TABLE_FORMS_DATASETS . " where id like 'zzcheck%'", []);

echo "\n=== the schema inspector offers only what may be used ===\n";
$tables = $schema->tables();
ok(sprintf('it lists the installation\'s tables (%d)', count($tables)), count($tables) > 20);
ok('every sensitive table is absent from the listing',
   array_intersect(array_map('strtolower', $tables), Dataset::SENSITIVE_TABLES) === [],
   implode(', ', array_intersect(array_map('strtolower', $tables), Dataset::SENSITIVE_TABLES)));
ok('names are unprefixed, so a prefixed install offers the same list',
   in_array('journal_main', $tables, true), 'journal_main was not listed');
ok('columns come back for a real table', in_array('post_date', $schema->columns('journal_main'), true));
ok('a table that does not exist has no columns', $schema->columns('no_such_table') === []);
ok('a sensitive table is rejected as sensitive, not as missing',
   str_contains((string)$schema->reject('users'), 'reserved'), (string)$schema->reject('users'));
ok('a missing table is rejected as missing',
   str_contains((string)$schema->reject('no_such_table'), 'no table'), (string)$schema->reject('no_such_table'));
ok('a real, permitted table is not rejected', $schema->reject('journal_main') === null);
ok('a money column is typed as a number, not as text',
   $schema->valueTypeOf('journal_main', 'total_amount')->name !== 'Text',
   $schema->valueTypeOf('journal_main', 'total_amount')->name);
ok('a date column is typed as a date',
   $schema->valueTypeOf('journal_main', 'post_date')->name === 'Date',
   $schema->valueTypeOf('journal_main', 'post_date')->name);

echo "\n=== an ordinary dataset works end to end ===\n";
$definition = [
    'id'        => 'zzcheck_ds',
    'label'     => 'Check dataset',
    'baseTable' => 'journal_main',
    'joins'     => [[
        'table' => 'contacts',
        'type'  => 'LEFT JOIN',
        'on'    => [['leftTable' => 'journal_main', 'leftColumn' => 'bill_acct_id',
                     'rightTable' => 'contacts',    'rightColumn' => 'id']],
    ]],
    'fields'    => [
        ['table' => 'journal_main', 'column' => 'id',           'key' => 'journal_id', 'label' => 'Journal'],
        ['table' => 'journal_main', 'column' => 'post_date',    'key' => 'post_date',  'label' => 'Date'],
        ['table' => 'journal_main', 'column' => 'total_amount', 'key' => 'total',      'label' => 'Total'],
        ['table' => 'contacts',     'column' => 'short_name',  'key' => 'contact',    'label' => 'Contact'],
    ],
];
$saved = $store->save($definition, true);
ok('a valid dataset saves', $saved === 'zzcheck_ds');

$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$store->registerAll($catalog);
ok('...and comes back out of the database', $catalog->has('zzcheck_ds'));
ok('...with no problems reported', $store->problems() === [], implode(' | ', $store->problems()));

$dataset = $catalog->get('zzcheck_ds');
ok('...at the custom tier', $dataset->tier === DatasetTier::Custom);
ok('...carrying its four fields', count($dataset->fields) === 4, (string)count($dataset->fields));
ok('...and both tables', $dataset->tables() === ['journal_main', 'contacts'],
   implode(', ', $dataset->tables()));
ok('a column\'s type was inferred rather than left as text',
   $dataset->fields['post_date']->type->name === 'Date', $dataset->fields['post_date']->type->name);

$query = (new QueryBuilder())->build($dataset, array_keys($dataset->fields));
ok('it builds a SELECT with no free text in it',
   str_starts_with(strtolower(trim($query->sql)), 'select') && !str_contains($query->sql, ';'),
   substr($query->sql, 0, 160));
ok('...and binds no parameters, having no expressions to bind', $query->params === []);

$provider = new ReportDocumentProvider(
    db: $db, catalog: $catalog, datasetId: 'zzcheck_ds',
    columns: [
        new ReportColumn('journal_id', 'Journal'),
        new ReportColumn('post_date',  'Date'),
        new ReportColumn('total',      'Total'),
        new ReportColumn('contact',    'Contact'),
    ],
    title: 'Check');
$document = $provider->build([]);
ok('a report built on it actually runs and returns rows',
   $document instanceof TableDocument && count($document->rows) > 0,
   'it returned ' . ($document instanceof TableDocument ? count($document->rows) : '?') . ' rows');

echo "\n=== hostile input is refused by name, at the boundary ===\n";
$base = $definition;
$base['id'] = 'zzcheck_evil';

// The exact capability §1.1 describes: PhreeForm's free-form relationship box.
refuses($store, 'a join condition carrying SQL is refused',
    array_replace_recursive($base, ['joins' => [['on' => [['leftColumn' => "id' OR '1'='1"]]]]]),
    'no column');
refuses($store, 'a table name carrying SQL is refused',
    array_replace($base, ['baseTable' => 'journal_main; drop table contacts']),
    'no table');
refuses($store, 'a UNION smuggled into a column is refused',
    array_replace_recursive($base, ['fields' => [['column' => 'id union select password from users']]]),
    'no column');
refuses($store, 'a qualified column name is refused, since it is syntax not an identifier',
    array_replace_recursive($base, ['fields' => [['column' => 'journal_main.id']]]),
    'no column');
refuses($store, 'a comment sequence in a table name is refused',
    array_replace($base, ['baseTable' => 'journal_main-- ']), 'no table');

// The privilege boundary itself.
refuses($store, 'the users table cannot be the base of a custom dataset',
    array_replace($base, ['baseTable' => 'users', 'joins' => [], 'fields' => [
        ['table' => 'users', 'column' => 'admin_id', 'key' => 'uid']]]),
    'reserved');
refuses($store, 'the users table cannot be joined in either',
    array_replace_recursive($base, ['joins' => [['table' => 'users']]]), 'reserved');
refuses($store, 'the configuration table is out of reach',
    array_replace($base, ['baseTable' => 'configuration', 'joins' => [], 'fields' => [
        ['table' => 'configuration', 'column' => 'configuration_key', 'key' => 'k']]]),
    'reserved');

// Structural rules, which stop a dataset that is valid SQL but not a dataset.
refuses($store, 'a join whose left side is not yet in the dataset is refused',
    array_replace_recursive($base, ['joins' => [['on' => [['leftTable' => 'inventory']]]]]),
    'not part of this dataset');
// Built explicitly rather than with array_replace_recursive(), which MERGES an
// empty array instead of emptying one and so left the original conditions in
// place — the check passed against an input it was not testing.
refuses($store, 'a join with no conditions is refused rather than becoming a cross join',
    array_replace($base, ['joins' => [['table' => 'contacts', 'type' => 'LEFT JOIN', 'on' => []]]]),
    'no conditions');
refuses($store, 'a dataset with no fields is refused',
    array_replace($base, ['fields' => []]), 'at least one field');
refuses($store, 'two fields sharing a name are refused',
    array_replace($base, ['fields' => [
        ['table' => 'journal_main', 'column' => 'id',        'key' => 'same'],
        ['table' => 'journal_main', 'column' => 'post_date', 'key' => 'same']]]),
    'share the same name');
refuses($store, 'a dataset name that is not an identifier is refused',
    array_replace($base, ['id' => 'evil-name; drop table x']), 'Invalid dataset name');
refuses($store, 'a field on a table the dataset does not include is refused',
    array_replace($base, ['fields' => [['table' => 'inventory', 'column' => 'sku', 'key' => 'sku']]]),
    'does not include');

ok('nothing hostile was left behind in the table',
   !$db->ExecutePrepared("select id from " . TABLE_FORMS_DATASETS . " where id = ?", ['zzcheck_evil'])->fields);

echo "\n=== a stored row is not trusted on the way out ===\n";
// Forge definitions straight into the table, as a database-level attacker or a
// restored older backup would, and confirm the load path refuses them.
$forge = function (string $id, array $definition) use ($db): void {
    $db->ExecutePrepared("delete from " . TABLE_FORMS_DATASETS . " where id = ?", [$id]);
    $db->ExecutePrepared("insert into " . TABLE_FORMS_DATASETS
        . " (id, label, description, definition, create_date, last_update) values (?,?,?,?,?,?)",
        [$id, $id, '', json_encode($definition), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
};

$forge('zzcheck_forged_table', [
    'baseTable' => 'users',
    'joins'     => [],
    'fields'    => [['key' => 'uid', 'label' => 'uid', 'table' => 'users', 'column' => 'admin_id']],
]);
$forge('zzcheck_forged_sql', [
    'baseTable' => 'journal_main',
    'joins'     => [],
    'fields'    => [['key' => 'x', 'label' => 'x', 'table' => 'journal_main',
                     'column' => "id from journal_main union select 1 -- "]],
]);
$forge('zzcheck_forged_json', []);

$catalog2 = new Catalog();
ShippedDatasets::registerAll($catalog2);
$store->registerAll($catalog2);
$problems = implode(' | ', $store->problems());

ok('a forged sensitive-table dataset does not load', !$catalog2->has('zzcheck_forged_table'));
ok('a forged SQL-carrying column does not load',   !$catalog2->has('zzcheck_forged_sql'));
ok('an unreadable definition does not load',       !$catalog2->has('zzcheck_forged_json'));
$named = 0;
foreach (['zzcheck_forged_table', 'zzcheck_forged_sql', 'zzcheck_forged_json'] as $forged) {
    if (str_contains($problems, $forged)) $named++;
}
ok('...and each is reported rather than swallowed', $named === 3, $problems);
ok('the valid dataset alongside them still loads', $catalog2->has('zzcheck_ds'),
   'one bad row took out the whole catalog');

// A stored row must not be able to redefine a dataset that ships in code, which
// would be a way to grant a custom dataset the powers of a shipped one.
$forge('contacts', [
    'baseTable' => 'journal_main',
    'joins'     => [],
    'fields'    => [['key' => 'id', 'label' => 'id', 'table' => 'journal_main', 'column' => 'id']],
]);
$catalog3 = new Catalog();
ShippedDatasets::registerAll($catalog3);
$store->registerAll($catalog3);
ok('a stored row cannot shadow a shipped dataset',
   $catalog3->get('contacts')->tier === DatasetTier::Shipped);
ok('...and says so', str_contains(implode(' ', $store->problems()), 'already uses that name'),
   implode(' | ', $store->problems()));

echo "\n=== the tier's limits hold wherever a Dataset is built ===\n";
// Not through the store: directly, because the guarantee has to be the
// constructor's rather than any one caller's.
$threw = false;
try {
    new Dataset(id: 'zzcheck_direct', label: 'x', baseTable: 'users',
        fields: [new \FreeBooks\Forms\Catalog\FieldDefinition('uid', 'uid', 'users', 'admin_id')],
        tier: DatasetTier::Custom);
} catch (CatalogException $e) { $threw = true; }
ok('constructing a custom Dataset on a sensitive table throws', $threw);

$threw = false;
try {
    new Dataset(id: 'zzcheck_direct', label: 'x', baseTable: 'journal_main',
        fields: [new \FreeBooks\Forms\Catalog\FieldDefinition(
            key: 'x', label: 'x', expression: '(select 1)')],
        tier: DatasetTier::Custom);
} catch (CatalogException $e) { $threw = true; }
ok('a custom Dataset may not carry a SQL expression', $threw);

$db->ExecutePrepared("delete from " . TABLE_FORMS_DATASETS . " where id like 'zzcheck%'", []);
$db->ExecutePrepared("delete from " . TABLE_FORMS_DATASETS . " where id = ?", ['contacts']);
$remaining = $db->ExecutePrepared("select count(*) as n from " . TABLE_FORMS_DATASETS
    . " where id like 'zzcheck%' or id = 'contacts'", [])->fields['n'] ?? 0;
ok('the check cleaned up after itself', (int)$remaining === 0, "$remaining row(s) left");

echo "\n" . ($failures === 0 ? "PASS\n" : "FAIL - $failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
