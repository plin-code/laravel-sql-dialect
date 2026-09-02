<?php

declare(strict_types=1);

namespace PlinCode\SqlDialect;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyContains(Builder $query, string $column, string $term): void
    {
        $pattern = self::containsPattern($term);
        $wrappedColumn = $query->getGrammar()->wrap($column);
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $operator = self::for($query);
        $escape = self::escapeClause($connection->getDriverName());

        $query->whereRaw("{$wrappedColumn} {$operator} ? {$escape}", [$pattern]);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyContainsOnDate(Builder $query, string $column, string $term): void
    {
        $pattern = self::containsPattern($term);
        $wrappedColumn = $query->getGrammar()->wrap($column);
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();
        $operator = self::for($query);
        $escape = self::escapeClause($driver);

        $expression = match ($driver) {
            'pgsql' => "{$wrappedColumn}::text",
            'mysql', 'mariadb' => "CAST({$wrappedColumn} AS CHAR)",
            default => "CAST({$wrappedColumn} AS TEXT)",
        };

        $query->whereRaw("{$expression} {$operator} ? {$escape}", [$pattern]);
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
