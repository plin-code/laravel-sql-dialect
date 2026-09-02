<?php

declare(strict_types=1);

namespace PlinCode\SqlDialect;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pulls the year out of a date column, in the dialect of the connection.
 *
 * Takes the builder rather than a driver name so the connection is resolved
 * where the query actually runs. In a multi connection application, asking
 * the caller to resolve the driver invites resolving the wrong one.
 */
final class YearExpression
{
    /**
     * The year as an integer, for ordering and comparisons.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function numeric(Builder $query, string $column): string
    {
        $wrapped = $query->getGrammar()->wrap($column);

        return match (self::driver($query)) {
            'sqlite' => "CAST(strftime('%Y', {$wrapped}) AS INTEGER)",
            default => "EXTRACT(YEAR FROM {$wrapped})",
        };
    }

    /**
     * The year as text, for concatenating into a displayed value.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function text(Builder $query, string $column): string
    {
        $wrapped = $query->getGrammar()->wrap($column);

        return match (self::driver($query)) {
            'pgsql' => "EXTRACT(YEAR FROM {$wrapped})::text",
            'mysql', 'mariadb' => "YEAR({$wrapped})",
            default => "strftime('%Y', {$wrapped})",
        };
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private static function driver(Builder $query): string
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();

        return $connection->getDriverName();
    }
}
