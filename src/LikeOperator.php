<?php

declare(strict_types=1);

namespace PlinCode\SqlDialect;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class LikeOperator
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function for(Builder $query): string
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();

        return $connection->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    public static function escapeWildcards(string $term): string
    {
        return addcslashes($term, '%_\\');
    }

    public static function containsPattern(string $term): string
    {
        return '%'.self::escapeWildcards($term).'%';
    }

    /**
     * Adds `column LIKE '%term%'` (ILIKE on PostgreSQL) to the query.
     *
     * `$boolean` and `$not` mirror Laravel's `whereLike()`: `'or'` joins the
     * clause with OR instead of AND, and `$not` negates it into NOT LIKE or
     * NOT ILIKE. A NULL column never matches, negated or not.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  'and'|'or'  $boolean
     */
    public static function applyContains(Builder $query, string $column, string $term, string $boolean = 'and', bool $not = false): void
    {
        $wrappedColumn = $query->getGrammar()->wrap($column);

        self::applyPattern($query, $wrappedColumn, $term, $boolean, $not);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function orApplyContains(Builder $query, string $column, string $term): void
    {
        self::applyContains($query, $column, $term, 'or');
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyNotContains(Builder $query, string $column, string $term): void
    {
        self::applyContains($query, $column, $term, 'and', true);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function orApplyNotContains(Builder $query, string $column, string $term): void
    {
        self::applyContains($query, $column, $term, 'or', true);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  'and'|'or'  $boolean
     */
    public static function applyContainsOnDate(Builder $query, string $column, string $term, string $boolean = 'and', bool $not = false): void
    {
        $wrappedColumn = $query->getGrammar()->wrap($column);
        /** @var Connection $connection */
        $connection = $query->getConnection();

        $expression = match ($connection->getDriverName()) {
            'pgsql' => "{$wrappedColumn}::text",
            'mysql', 'mariadb' => "CAST({$wrappedColumn} AS CHAR)",
            default => "CAST({$wrappedColumn} AS TEXT)",
        };

        self::applyPattern($query, $expression, $term, $boolean, $not);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyPattern(Builder $query, string $expression, string $term, string $boolean, bool $not): void
    {
        $boolean = strtolower($boolean);

        if ($boolean !== 'and' && $boolean !== 'or') {
            throw new InvalidArgumentException("The boolean must be 'and' or 'or', got '{$boolean}'.");
        }

        /** @var Connection $connection */
        $connection = $query->getConnection();
        $operator = ($not ? 'NOT ' : '').self::for($query);
        $escape = self::escapeClause($connection->getDriverName());

        $query->whereRaw("{$expression} {$operator} ? {$escape}", [self::containsPattern($term)], $boolean);
    }

    /**
     * MySQL processes backslash escapes inside string literals, so the
     * escape character has to be written doubled in the SQL text to reach
     * the parser as a single backslash. PostgreSQL and SQLite take it as is.
     */
    private static function escapeClause(string $driver): string
    {
        return $driver === 'mysql' || $driver === 'mariadb'
            ? "ESCAPE '\\\\'"
            : "ESCAPE '\\'";
    }
}
