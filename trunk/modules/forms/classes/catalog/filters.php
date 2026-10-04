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
 * A filter cannot carry syntax, because the operator is an enum and the value
 * never reaches the statement. Concatenating stored values into the WHERE
 * clause — numeric-looking ones unquoted — lets a crafted value become syntax.
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

/**
 * Arithmetic over dataset FIELDS, for comparing a column to other columns.
 *
 * The shipped library uses this: the Re-order Worksheet asks for items where
 * `quantity_on_hand` is less than `(minimum_stock_level +
 * quantity_on_sales_order - quantity_on_order)`. That cannot be a bound
 * parameter — it is not a value — and binding it anyway is not a safe
 * failure: MySQL coerces the string to 0, so the report silently becomes
 * "items with negative stock".
 *
 * It is also the obvious place to reintroduce injection, so nothing here is
 * free text. An expression is a list of tokens that are either a dataset field
 * KEY or one of six fixed symbols; the key is resolved through the dataset at
 * render time, exactly as a selected field is. Anything the parser cannot
 * account for is rejected rather than passed through.
 */
final readonly class FieldExpression
{
    public const OPERATORS = ['+', '-', '*', '/', '(', ')'];

    /** @param list<array{kind: 'field'|'op', text: string}> $tokens */
    public function __construct(public array $tokens)
    {
        if (!$tokens) throw new CatalogException('A field expression cannot be empty.');
        $hasField = false;
        foreach ($tokens as $token) {
            if ($token['kind'] === 'op') {
                if (!in_array($token['text'], self::OPERATORS, true)) {
                    throw new CatalogException("'{$token['text']}' is not an allowed expression operator.");
                }
            } elseif ($token['kind'] === 'field') {
                $hasField = true;
                Identifier::check($token['text'], 'field key');
            } else {
                throw new CatalogException("Unknown expression token kind '{$token['kind']}'.");
            }
        }
        // An expression of pure arithmetic is a literal in disguise; it should
        // have been bound as a parameter instead of routed through here.
        if (!$hasField) throw new CatalogException('A field expression must reference at least one field.');
    }

    /** @return list<string> */
    public function fieldKeys(): array
    {
        $keys = [];
        foreach ($this->tokens as $token) if ($token['kind'] === 'field') $keys[] = $token['text'];
        return array_values(array_unique($keys));
    }
}

/** One condition: a dataset field, a comparison, and its values. */
final readonly class Filter
{
    /**
     * @param list<scalar|null> $values
     * @param ?FieldExpression  $expression compare against other FIELDS rather
     *   than against values; when set, $values must be empty.
     */
    public function __construct(
        public string           $fieldKey,
        public FilterOperator   $operator,
        public array            $values = [],
        public ?FieldExpression $expression = null,
    ) {
        if ($expression !== null) {
            if ($values) {
                throw new CatalogException("Filter on '$fieldKey' cannot take both values and a field expression.");
            }
            // Only the scalar comparisons make sense against an expression.
            if (!in_array($operator, [FilterOperator::Equal, FilterOperator::NotEqual,
                    FilterOperator::GreaterThan, FilterOperator::GreaterEqual,
                    FilterOperator::LessThan, FilterOperator::LessEqual], true)) {
                throw new CatalogException(
                    "Operator '{$operator->value}' cannot compare '$fieldKey' to a field expression.");
            }
            return;
        }
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

    public static function compareFields(string $fieldKey, FilterOperator $operator, FieldExpression $expression): self
    {
        return new self($fieldKey, $operator, [], $expression);
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
