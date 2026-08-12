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
//  Path: /docs/tools/verify_forms_catalog.php
//
/**
 * Assert the Forms catalog's safety guarantees (docs/forms-design.md §3).
 *
 * The catalog exists to make PhreeForm's central vulnerability unreachable: any
 * report author could author arbitrary SQL and read any table, including `users`
 * and `configuration` (§1.1). That claim is only worth what its tests are, so
 * this tool attacks the catalog rather than demonstrating it:
 *
 *   - identifiers carrying SQL syntax must be refused, not escaped;
 *   - a custom (UI-created) dataset must refuse expressions and sensitive tables;
 *   - filter values must always leave as bound parameters, never as text;
 *   - a filter or sort naming an unknown field must be refused.
 *
 * Runs standalone on the host: no database, no application bootstrap, because
 * the guarantees are structural rather than environmental.
 *
 *   php docs/tools/verify_forms_catalog.php
 *
 * Exit 0 = every guarantee holds.
 */

$root = dirname(__DIR__, 2) . '/trunk/modules/forms/classes';
require_once($root . '/document/load.php');
require_once($root . '/catalog/load.php');
require_once($root . '/render/formatter.php');
require_once($root . '/legacy/template_converter.php');

use FreeBooks\Forms\Catalog\{
    Catalog, CatalogException, Dataset, DatasetTier, FieldDefinition, FieldExpression, Filter,
    FilterOperator, Join, JoinCondition, JoinType, QueryBuilder, ShippedDatasets,
    Sort, SortDirection
};
use FreeBooks\Forms\Document\ValueType;

$pass = 0; $fail = 0;

function ok(string $what, bool $condition, string $detail = ''): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "  [ok] $what\n"; }
    else            { $fail++; echo "  [FAIL] $what" . ($detail ? " — $detail" : '') . "\n"; }
}

/** Assert that building something throws — i.e. the rule is enforced. */
function refuses(string $what, callable $fn): void
{
    global $pass, $fail;
    try {
        $fn();
        $fail++;
        echo "  [FAIL] $what — was ALLOWED\n";
    } catch (CatalogException $e) {
        $pass++;
        echo "  [ok] $what\n";
    } catch (\Throwable $e) {
        global $fail;
        $fail++;
        echo "  [FAIL] $what — wrong exception: " . $e::class . ' ' . $e->getMessage() . "\n";
    }
}

echo "=== identifiers may not carry syntax ===\n";
$injections = [
    'users; DROP TABLE journal_main',
    'journal_main WHERE 1=1 UNION SELECT * FROM users',
    'id, (SELECT password FROM users)',
    '`users`',
    'journal_main.id',            // dots belong in the model, not in a name
    'a b',
    '',
    "id' OR '1'='1",
];
foreach ($injections as $bad) {
    refuses('table name ' . var_export($bad, true) . ' refused',
        fn() => new Dataset('d', 'D', $bad, [new FieldDefinition('f', 'F', 't', 'c')]));
    refuses('column name ' . var_export($bad, true) . ' refused',
        fn() => new FieldDefinition('f', 'F', 'journal_main', $bad));
}
refuses('join condition column carrying syntax refused',
    fn() => new JoinCondition('journal_main', 'id', 'journal_item', 'ref_id = 1 OR 1=1'));

echo "\n=== the custom tier is bounded (§3.2) ===\n";
refuses('custom dataset may not carry a raw SQL expression',
    fn() => new Dataset(
        id: 'c1', label: 'C', baseTable: 'journal_main', tier: DatasetTier::Custom,
        fields: [new FieldDefinition('x', 'X', type: ValueType::Money, expression: '(SELECT password FROM users LIMIT 1)')]));

foreach (Dataset::SENSITIVE_TABLES as $sensitive) {
    refuses("custom dataset may not reference '$sensitive'",
        fn() => new Dataset(
            id: 'c2', label: 'C', baseTable: $sensitive, tier: DatasetTier::Custom,
            fields: [new FieldDefinition('x', 'X', $sensitive, 'id')]));
    refuses("custom dataset may not JOIN '$sensitive'",
        fn() => new Dataset(
            id: 'c3', label: 'C', baseTable: 'journal_main', tier: DatasetTier::Custom,
            fields: [new FieldDefinition('x', 'X', 'journal_main', 'id')],
            joins:  [new Join($sensitive, [new JoinCondition('journal_main', 'admin_id', $sensitive, 'admin_id')])]));
}

// The same definitions must be ALLOWED for the shipped tier — otherwise the
// stock audit report could not exist, and the tier split would be pointless.
$shippedSensitive = new Dataset(
    id: 's1', label: 'S', baseTable: 'audit_log', tier: DatasetTier::Shipped,
    fields: [new FieldDefinition('x', 'X', 'users', 'admin_name'),
             new FieldDefinition('y', 'Y', type: ValueType::Money, expression: 'SUM(audit_log.id)')],
    joins:  [new Join('users', [new JoinCondition('audit_log', 'admin_id', 'users', 'admin_id')])]);
ok('shipped dataset MAY use sensitive tables and expressions', count($shippedSensitive->fields) === 2);

echo "\n=== filter values never become SQL ===\n";
$catalog = new Catalog();
ShippedDatasets::registerAll($catalog);
$transactions = $catalog->get('transactions');
$builder      = new QueryBuilder();

$attack = "1' OR '1'='1";
$query  = $builder->build($transactions, ['id', 'post_date'], [Filter::equals('purchase_invoice_id', $attack)]);
ok('the attacking value is absent from the SQL', !str_contains($query->sql, $attack), $query->sql);
ok('the attacking value is bound as a parameter', $query->params === [$attack]);
ok('the SQL uses a placeholder', str_contains($query->sql, '= ?'));

$query = $builder->build($transactions, ['id'], [Filter::in('journal_id', [4, 6, "7 OR 1=1"])]);
ok('IN emits one placeholder per value', substr_count($query->sql, '?') === 3, $query->sql);
ok('IN binds every value', $query->params === [4, 6, '7 OR 1=1']);

$query = $builder->build($transactions, ['id'], [Filter::between('post_date', '2026-01-01', '2026-12-31')]);
ok('BETWEEN emits two placeholders', str_contains($query->sql, 'BETWEEN ? AND ?'));
ok('BETWEEN binds both bounds', $query->params === ['2026-01-01', '2026-12-31']);

$query = $builder->build($transactions, ['id'], [new Filter('description', FilterOperator::Contains, ['100%_real'])]);
ok('LIKE metacharacters in a value are escaped', $query->params === ['%100\%\_real%'], var_export($query->params, true));

echo "\n=== unknown or disallowed references are refused ===\n";
refuses('filtering an unknown field',  fn() => $builder->build($transactions, ['id'], [Filter::equals('no_such_field', 1)]));
refuses('sorting by an unknown field', fn() => $builder->build($transactions, ['id'], [], [new Sort('no_such_field')]));
refuses('selecting an unknown field',  fn() => $builder->build($transactions, ['no_such_field']));
refuses('a negative LIMIT',            fn() => $builder->build($transactions, ['id'], [], [], -1));
refuses('duplicate field keys',        fn() => new Dataset('d', 'D', 't', [
    new FieldDefinition('same', 'A', 't', 'a'), new FieldDefinition('same', 'B', 't', 'b')]));
refuses('a dataset with no fields',    fn() => new Dataset('d', 'D', 't', []));
refuses('a join with no conditions',   fn() => new Join('journal_item', []));

echo "\n=== the shipped catalog is well-formed ===\n";
$expected = ['transactions', 'general_ledger', 'inventory', 'audit_trail'];
foreach ($expected as $id) ok("dataset '$id' is registered", $catalog->has($id));
refuses('registering the same dataset twice', fn() => $catalog->register(ShippedDatasets::transactions()));

foreach ($catalog->all() as $dataset) {
    $query = $builder->build($dataset);            // every field, no filters
    ok("dataset '{$dataset->id}' builds a parameterless SELECT", $query->params === []);
    ok("dataset '{$dataset->id}' selects from its base table",
        str_contains($query->sql, 'FROM ' . $dataset->baseTable));
    // Every join must be reachable from the tables already in play, or the SQL
    // is invalid however well-formed each part is.
    $known = [$dataset->baseTable];
    $wellOrdered = true;
    foreach ($dataset->joins as $join) {
        foreach ($join->on as $c) {
            $sides = [$c->leftTable, $c->rightTable];
            if (!in_array($join->table, $sides, true)) $wellOrdered = false;
            $other = $c->leftTable === $join->table ? $c->rightTable : $c->leftTable;
            if (!in_array($other, $known, true)) $wellOrdered = false;
        }
        $known[] = $join->table;
    }
    ok("dataset '{$dataset->id}' joins are ordered and connected", $wellOrdered);
}

$audit = $catalog->get('audit_trail');
ok('audit_trail reaches users (shipped tier privilege)', in_array('users', $audit->tables(), true));
ok('audit_trail exposes only named user columns',
    $audit->field('user_name')?->column === 'admin_name' && $audit->field('password') === null);

echo "\n=== a table prefix reaches introductions only ===\n";
// DB_PREFIX is empty on every database here, so nothing else would notice this
// breaking. verify_forms_prefix.php proves it against a real prefixed schema;
// these are the structural claims, which need no database.
$txn       = $catalog->get('transactions');
$prefixed  = (new QueryBuilder('pb_'))->build($txn, ['id', 'item_sku']);
ok('the base table is introduced prefixed and aliased bare',
    str_contains($prefixed->sql, 'FROM pb_journal_main AS journal_main'), $prefixed->sql);
ok('a join is introduced prefixed and aliased bare',
    str_contains($prefixed->sql, 'JOIN pb_journal_item AS journal_item'), $prefixed->sql);
ok('no alias carries the prefix',   !preg_match('/AS pb_/', $prefixed->sql));
ok('no column reference carries the prefix', !preg_match('/\bpb_[A-Za-z0-9_]*\./', $prefixed->sql));

// An expression naming a FRESH instance of a table cannot use the alias.
$invPrefixed = (new QueryBuilder('pb_'))->build($catalog->get('inventory'), ['history_sku_avg_cost']);
ok('a subquery in an expression opens the prefixed table',
    str_contains($invPrefixed->sql, 'FROM pb_inventory_history h_avg'), $invPrefixed->sql);
ok('the correlated reference beside it stays on the alias',
    str_contains($invPrefixed->sql, 'h_avg.sku = inventory_history.sku'), $invPrefixed->sql);
ok('no placeholder survives into the statement', !str_contains($invPrefixed->sql, '{{'));

$unprefixed = (new QueryBuilder(''))->build($txn, ['id']);
ok('an empty prefix still aliases, so one code path serves both',
    str_contains($unprefixed->sql, 'FROM journal_main AS journal_main'), $unprefixed->sql);
refuses('a prefix that is not a bare identifier prefix',
    fn() => (new QueryBuilder('pb`; DROP TABLE users; --'))->build($txn, ['id']));
refuses('a prefix carrying a schema qualifier',
    fn() => (new QueryBuilder('other_db.'))->build($txn, ['id']));

echo "\n=== a field expression cannot carry SQL ===\n";
// FieldExpression exists so a filter can compare a column to other columns
// (§8.5). It is the one place a caller-shaped string comes near the statement,
// so it is attacked here rather than trusted.
refuses('an expression with a non-arithmetic operator',
    fn() => new FieldExpression([['kind' => 'op', 'text' => ';']]));
refuses('an expression whose operator is a SQL keyword',
    fn() => new FieldExpression([['kind' => 'field', 'text' => 'qty_stock'], ['kind' => 'op', 'text' => 'UNION']]));
refuses('an expression whose field key carries syntax',
    fn() => new FieldExpression([['kind' => 'field', 'text' => 'qty_stock) OR 1=1 --']]));
refuses('an expression with an unknown token kind',
    fn() => new FieldExpression([['kind' => 'raw', 'text' => 'DROP TABLE users']]));
refuses('an empty expression', fn() => new FieldExpression([]));
refuses('an expression referencing no field at all',
    fn() => new FieldExpression([['kind' => 'op', 'text' => '('], ['kind' => 'op', 'text' => ')']]));

$inv  = $catalog->get('inventory');
$expr = new FieldExpression([
    ['kind' => 'field', 'text' => 'min_stock_level'],
    ['kind' => 'op',    'text' => '+'],
    ['kind' => 'field', 'text' => 'qty_on_sales'],
]);
$q = (new QueryBuilder())->build($inv, ['sku'],
    [Filter::compareFields('qty_stock', FilterOperator::LessThan, $expr)]);
ok('a field comparison binds no parameters', $q->params === []);
ok('a field comparison emits only resolved column references',
    str_contains($q->sql, 'inventory.quantity_on_hand < (inventory.minimum_stock_level + inventory.quantity_on_sales_order)'),
    $q->sql);
refuses('a field comparison against a field the dataset lacks',
    fn() => (new QueryBuilder())->build($inv, ['sku'], [Filter::compareFields('qty_stock',
        FilterOperator::LessThan, new FieldExpression([['kind' => 'field', 'text' => 'no_such_field']]))]));
refuses('a field comparison with a non-scalar operator',
    fn() => Filter::compareFields('qty_stock', FilterOperator::In, $expr));
refuses('a filter carrying both values and an expression',
    fn() => new Filter('qty_stock', FilterOperator::LessThan, ['5'], $expr));

echo "\n=== the two halves of provider-side resolution agree ===\n";
// The renderer refuses these hints; the converter re-points their bindings at
// the field a provider resolves them into. Two lists in two layers, which drift
// silently: a hint the renderer refuses but the converter does not re-point
// prints a raw id on a customer-facing document.
$refused   = \FreeBooks\Forms\Render\Formatter::RESOLUTION_HINTS;
$repointed = array_keys(\FreeBooks\Forms\Legacy\TemplateConverter::PROVIDER_RESOLVED);
sort($refused); sort($repointed);
ok('Formatter::RESOLUTION_HINTS matches TemplateConverter::PROVIDER_RESOLVED',
    $refused === $repointed,
    'renderer refuses [' . implode(', ', $refused) . '], converter re-points [' . implode(', ', $repointed) . ']');

echo "\n" . ($fail === 0
    ? "PASS - $pass guarantees hold.\n"
    : "FAIL - $fail of " . ($pass + $fail) . " checks failed.\n");
exit($fail === 0 ? 0 : 1);
