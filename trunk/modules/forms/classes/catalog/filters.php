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
//  Path: /modules/forms/classes/catalog/filters.php
//
/**
 * Forms — filters, as field + operator + value rather than as SQL.
 *
 * PhreeForm's build_criteria() concatenated the stored min/max values into the
 * WHERE clause. Numeric-looking criteria were interpolated unquoted, so a
 * crafted value became syntax — the same class of bug fixed at three separate
 * call sites in gen_db_date(), gen_build_sql_date() and popup_gen during the
 * 2026-08 audit. Fixing the shape rather than the call sites is the point of
 * this file: a filter cannot carry syntax, because the operator is an enum and
 * the value never reaches the statement.
 *
 * Every value becomes a bound parameter. The only text the builder emits is the
 * operator's own keyword and a placeholder.
 */

namespace FreeBooks\Forms\Catalog;

/**
 * The comparisons a filter may make.
 *
 * A closed set, so an author picks one; there is no path for a stored string to
 * become an operator. `arity` states how many values the operator consumes,
 * which is what lets the builder emit the right number of placeholders.
 */
enum FilterOperator: string
{
    case Equal        = 'eq';
    case NotEqual     = 'ne';
    case GreaterThan  = 'gt';
    case GreaterEqual = 'ge';
    case LessThan     = 'lt';
    case LessEqual    = 'le';
    case Between      = 'between';
    case In           = 'in';
    case Contains     = 'contains';
    case StartsWith   = 'starts';
    case IsEmpty      = 'empty';
    case IsNotEmpty   = 'notempty';

    /** -1 means "any number of values" (IN). */
    public function arity(): int
    {
        return match ($this) {
            self::Between                    => 2,
            self::In                         => -1,
            self::IsEmpty, self::IsNotEmpty  => 0,
            default                          => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Equal        => 'equals',
            self::NotEqual     => 'does not equal',
            self::GreaterThan  => 'is greater than',
            self::GreaterEqual => 'is at least',
            self::LessThan     => 'is less than',
            self::LessEqual    => 'is at most',
            self::Between      => 'is between',
            self::In           => 'is one of',
            self::Contains     => 'contains',
            self::StartsWith   => 'starts with',
            self::IsEmpty      => 'is empty',
            self::IsNotEmpty   => 'is not empty',
        };
    }
}

/** One condition: a dataset field, a comparison, and its values. */
final readonly class Filter
{
    /** @param list<scalar|null> $values */
    public function __construct(
        public string         $fieldKey,
        public FilterOperator $operator,
        public array          $values = [],
    ) {
        $arity = $operator->arity();
        if ($arity >= 0 && count($values) !== $arity) {
            throw new CatalogException(sprintf(
                "Filter on '%s' with operator '%s' expects %d value(s), got %d.",
                $fieldKey, $operator->value, $arity, count($values)));
        }
        if ($arity === -1 && !$values) {
            throw new CatalogException("Filter on '$fieldKey' with operator 'in' needs at least one value.");
        }
    }

    public static function equals(string $fieldKey, mixed $value): self
    {
        return new self($fieldKey, FilterOperator::Equal, [$value]);
    }

    public static function between(string $fieldKey, mixed $low, mixed $high): self
    {
        return new self($fieldKey, FilterOperator::Between, [$low, $high]);
    }

    /** @param list<scalar|null> $values */
    public static function in(string $fieldKey, array $values): self
    {
        return new self($fieldKey, FilterOperator::In, array_values($values));
    }
}

/**
 * How rows are ordered. Direction is an enum for the same reason operators are:
 * "ASC"/"DESC" is the only text that reaches the statement.
 */
enum SortDirection: string
{
    case Ascending  = 'ASC';
    case Descending = 'DESC';
}

final readonly class Sort
{
    public function __construct(
        public string        $fieldKey,
        public SortDirection $direction = SortDirection::Ascending,
    ) {}
}
