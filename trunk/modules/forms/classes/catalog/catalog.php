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
//  Path: /modules/forms/classes/catalog/catalog.php
//
/**
 * Forms — the catalog: datasets, their join graphs, and their fields.
 *
 * This file is where PhreeForm's central vulnerability is designed out. There,
 * CreateTableList() offered every table `SHOW TABLES` returned — including
 * `users`, `configuration` and `data_security` — and the join condition was a
 * free-form text box concatenated straight into the query. Anyone who could
 * print an invoice could therefore author arbitrary SQL (§1.1).
 *
 * The rule that replaces it (§3.3): **an author picks identifiers; they never
 * supply SQL syntax.** Every identifier here is validated on construction
 * against a strict pattern, and the query builder assembles the statement from
 * those validated parts. There is no path by which a stored string becomes
 * syntax.
 *
 * Two tiers, because expressiveness and safety are needed in different places
 * (§3.2):
 *
 *   Shipped — defined in version-controlled PHP by a developer. May reference
 *       any table, including the sensitive set, and may carry raw SQL
 *       expressions for computed fields. Editing one requires filesystem
 *       access, which is the real privilege boundary.
 *
 *   Custom  — created through the admin UI and stored in the database. Plain
 *       columns only, and the sensitive tables are refused. A careless author
 *       can expose a table they should not — auditable and reversible — but
 *       cannot inject SQL, stack queries or UNION, because they never write
 *       syntax.
 *
 * The tier is enforced here rather than in the UI. A Custom dataset carrying an
 * expression or a sensitive table is rejected at construction, so the guarantee
 * holds no matter which code path builds one.
 */

namespace FreeBooks\Forms\Catalog;

use FreeBooks\Forms\Document\ValueType;

/** Who defined a dataset, which decides what it is allowed to contain. */
enum DatasetTier
{
    case Shipped;   // version-controlled PHP; may use expressions and any table
    case Custom;    // admin UI, DB-stored; plain columns, non-sensitive tables
}

enum JoinType: string
{
    case Inner = 'INNER JOIN';
    case Left  = 'LEFT JOIN';
}

/** Thrown when a definition would violate the catalog's safety rules. */
class CatalogException extends \RuntimeException {}

/**
 * Identifier validation — the single chokepoint every table and column name
 * passes through.
 *
 * Deliberately stricter than MySQL allows: no backtick-quoted names, no dots,
 * no spaces. Anything a legitimate FreeBooks schema uses will pass, and
 * anything carrying syntax will not.
 */
final class Identifier
{
    private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public static function check(string $name, string $what): string
    {
        if (!preg_match(self::PATTERN, $name)) {
            throw new CatalogException("Invalid $what '$name': identifiers must match [A-Za-z_][A-Za-z0-9_]*.");
        }
        return $name;
    }
}

/**
 * One equality linking two tables.
 *
 * A list of these expresses a compound join. That is not hypothetical: the
 * shipped library uses only simple `a.b = c.d` (all 11 relationships), but a
 * customised install of ours joins inventory_purchase_details on BOTH sku and
 * vendor_id. A single-pair model would silently fail to express it, and the
 * temptation would then be to re-introduce the free-form condition box that
 * caused the original vulnerability.
 */
final readonly class JoinCondition
{
    public function __construct(
        public string $leftTable,
        public string $leftColumn,
        public string $rightTable,
        public string $rightColumn,
    ) {
        Identifier::check($leftTable,   'table name');
        Identifier::check($leftColumn,  'column name');
        Identifier::check($rightTable,  'table name');
        Identifier::check($rightColumn, 'column name');
    }

    public function toSql(): string
    {
        return "{$this->leftTable}.{$this->leftColumn} = {$this->rightTable}.{$this->rightColumn}";
    }
}

/** A table brought into a dataset, and how it attaches. */
final readonly class Join
{
    /**
     * @param list<JoinCondition> $on
     * @param bool $required Keep this join even when nothing selects, filters
     *   or sorts on it. Default false, so an unreferenced join is pruned —
     *   see QueryBuilder::requiredTables() for why that is the safe default.
     *   Set it only where the join itself carries meaning: an INNER join used
     *   as a restriction ("…and only those with a contact record") is part of
     *   what the dataset IS, and dropping it would widen every report.
     */
    public function __construct(
        public string   $table,
        public array    $on,
        public JoinType $type     = JoinType::Inner,
        public bool     $required = false,
    ) {
        Identifier::check($table, 'table name');
        if (!$on) throw new CatalogException("Join onto '$table' has no conditions.");
    }
}

/**
 * One selectable field.
 *
 * `column` is the ordinary case. `expression` is the escape hatch that keeps
 * PhreeForm's flexibility — the live Purchase Order selects
 * `IF(vendor_sku <> '', vendor_sku, SUBSTRING_INDEX(description_purchase,' ',1))`
 * — and it is exactly what a Custom dataset may not contain, since it IS raw
 * SQL. Dataset enforces that; the constructor only requires that one of the two
 * is present.
 */
final readonly class FieldDefinition
{
    /**
     * @param list<string> $legacyNames PhreeForm field strings this replaces.
     *   A computed field has no table.column for the converter to match on, so
     *   without this the legacy expression it stands in for would be reported as
     *   unconvertible. Naming them here is how a shipped dataset says "this
     *   arithmetic used to be written inline, and now it is this field".
     */
    public function __construct(
        public string    $key,           // stable name used by templates and filters
        public string    $label,
        public ?string   $table  = null,
        public ?string   $column = null,
        public ValueType $type   = ValueType::Text,
        public ?string   $expression = null,   // shipped datasets only
        public bool      $filterable = true,
        public bool      $sortable   = true,
        public array     $legacyNames = [],
    ) {
        Identifier::check($key, 'field key');
        if ($expression === null) {
            if ($table === null || $column === null) {
                throw new CatalogException("Field '$key' needs a table and column, or an expression.");
            }
            Identifier::check($column, 'column name');
        }
        // A computed field may still name a table, and should. The name is not
        // used to build the SQL — the expression already says where the values
        // come from — but it states the field's GRAIN, i.e. whether it varies
        // per line or per record. A consumer folding rows into records has no
        // other way to tell, and getting it wrong silently blanks the column.
        if ($table !== null) Identifier::check($table, 'table name');
    }

    public function isComputed(): bool
    {
        return $this->expression !== null;
    }

    /** The SQL that produces this field's value, aliased to its key. */
    public function toSelect(): string
    {
        $source = $this->expression ?? "{$this->table}.{$this->column}";
        return "$source AS {$this->key}";
    }

    /** The SQL naming this field where an alias is not usable (WHERE, JOIN). */
    public function toReference(): string
    {
        return $this->expression ?? "{$this->table}.{$this->column}";
    }
}

/**
 * A curated view onto the join graph: the unit a report author works inside.
 *
 * Not the same thing as a connected component of the schema. §6.1 found the
 * whole shipped library forms only five components and that no report spans
 * more than one — but the largest component (44 documents) is split into
 * "Transactions" and "General Ledger" here, because a dataset is chosen for
 * usability, not maximality.
 */
final readonly class Dataset
{
    /** Tables the admin UI may never reference (§3.3). */
    public const SENSITIVE_TABLES = [
        'users', 'users_profiles', 'webauthn_creds',
        'data_security', 'configuration', 'audit_log',
    ];

    /** @var array<string,FieldDefinition> keyed by field key */
    public array $fields;

    /**
     * @param list<Join>            $joins
     * @param list<FieldDefinition> $fields
     */
    public function __construct(
        public string      $id,
        public string      $label,
        public string      $baseTable,
        array              $fields,
        public array       $joins = [],
        public DatasetTier $tier  = DatasetTier::Shipped,
        public string      $description = '',
        /**
         * Values for `{{param:name}}` placeholders in computed expressions,
         * BOUND by the builder rather than pasted into the SQL.
         *
         * Some computed fields depend on something no static expression can
         * hold: the aging buckets are today's date less an admin setting, so
         * they change with the clock and with configuration. Writing them into
         * the expression text would put a date literal in the SQL string, which
         * is the shape §3 exists to keep out — even where, as here, the value
         * never came from a user.
         *
         * Shipped datasets only, for the same reason expressions are.
         *
         * @var array<string,scalar>
         */
        public array       $params = [],
    ) {
        Identifier::check($id, 'dataset id');
        Identifier::check($baseTable, 'table name');

        $byKey = [];
        foreach ($fields as $field) {
            if (isset($byKey[$field->key])) {
                throw new CatalogException("Dataset '$id' defines field '{$field->key}' twice.");
            }
            $byKey[$field->key] = $field;
        }
        if (!$byKey) throw new CatalogException("Dataset '$id' has no fields.");
        $this->fields = $byKey;

        if ($tier === DatasetTier::Custom) $this->enforceCustomTierLimits();
    }

    /**
     * The rules a UI-created dataset must obey. Enforced at construction rather
     * than at the form that creates one, so no code path can bypass them.
     */
    private function enforceCustomTierLimits(): void
    {
        foreach ($this->fields as $field) {
            if ($field->isComputed()) {
                throw new CatalogException(
                    "Dataset '{$this->id}' is custom and may not define the computed field "
                    . "'{$field->key}': raw SQL expressions are restricted to shipped datasets.");
            }
        }
        if ($this->params) {
            throw new CatalogException(
                "Dataset '{$this->id}' is custom and may not carry expression parameters: "
                . "they exist only to serve computed expressions, which it may not define.");
        }
        foreach ($this->tables() as $table) {
            if (in_array(strtolower($table), self::SENSITIVE_TABLES, true)) {
                throw new CatalogException(
                    "Dataset '{$this->id}' is custom and may not reference the sensitive table '$table'.");
            }
        }
    }

    /** @return list<string> every table this dataset selects from */
    public function tables(): array
    {
        $tables = [$this->baseTable];
        foreach ($this->joins as $join) $tables[] = $join->table;
        return array_values(array_unique($tables));
    }

    public function field(string $key): ?FieldDefinition
    {
        return $this->fields[$key] ?? null;
    }

    /** @param list<string> $keys @return list<FieldDefinition> */
    public function fieldsFor(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $field = $this->field($key);
            if ($field === null) {
                throw new CatalogException("Dataset '{$this->id}' has no field '$key'.");
            }
            $out[] = $field;
        }
        return $out;
    }
}

/**
 * The set of datasets available to a request.
 *
 * Shipped datasets are registered from PHP; custom ones would be loaded from
 * the database and registered the same way, so everything downstream — the
 * query builder, the provider, the UI — sees one uniform collection.
 */
final class Catalog
{
    /** @var array<string,Dataset> */
    private array $datasets = [];

    public function register(Dataset $dataset): void
    {
        if (isset($this->datasets[$dataset->id])) {
            throw new CatalogException("Dataset '{$dataset->id}' is already registered.");
        }
        $this->datasets[$dataset->id] = $dataset;
    }

    public function has(string $id): bool
    {
        return isset($this->datasets[$id]);
    }

    public function get(string $id): Dataset
    {
        if (!isset($this->datasets[$id])) {
            throw new CatalogException("No dataset '$id'.");
        }
        return $this->datasets[$id];
    }

    /** @return list<Dataset> */
    public function all(): array
    {
        return array_values($this->datasets);
    }
}
