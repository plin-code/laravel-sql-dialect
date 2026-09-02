<p align="center">
  <img src="https://raw.githubusercontent.com/plin-code/laravel-sql-dialect/main/art/banner.png" alt="Laravel SQL Dialect">
</p>

<div align="center">
    <h1>Laravel SQL Dialect</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/plin-code/laravel-sql-dialect"><img src="https://img.shields.io/packagist/v/plin-code/laravel-sql-dialect.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-sql-dialect"><img src="https://img.shields.io/packagist/php-v/plin-code/laravel-sql-dialect.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-sql-dialect"><img src="https://badge.laravel.cloud/badge/plin-code/laravel-sql-dialect?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/plin-code/laravel-sql-dialect/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/plin-code/laravel-sql-dialect/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/plin-code/laravel-sql-dialect"><img src="https://img.shields.io/packagist/dt/plin-code/laravel-sql-dialect.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Cross driver SQL helpers for Laravel: LIKE/ILIKE with wildcard escaping, year extraction from date columns, multi value normalisation.

## What it solves

Writing a `LIKE` filter or a "group by year" query that behaves the same on PostgreSQL, MySQL and SQLite usually means scattering `match ($driver)` (or `if/else` on `DB::connection()->getDriverName()`) through your services and repositories. This package moves that branching into three small, static, dependency free classes: `LikeOperator`, `YearExpression` and `CsvValues`. Each one takes the query builder it needs to inspect the connection from, and returns the right operator or SQL fragment for that connection's driver.

## Installation

You can install the package via Composer:

```bash
composer require plin-code/laravel-sql-dialect
```

There is nothing else to do. No service provider to register, no config to publish, no facade, no macro. The three classes are ready to call as soon as the package is autoloaded.

## `LikeOperator`

`LikeOperator::applyContains()` adds a case matched, wildcard safe `LIKE` (or `ILIKE` on PostgreSQL) clause to a query. It works on any `Builder`, including the one handed to a closure for a subquery:

```php
use PlinCode\SqlDialect\LikeOperator;

Movie::query()
    ->where(function (Builder $query) use ($term) {
        LikeOperator::applyContains($query, 'movies.title', $term);
    })
    ->orWhereIn('movies.id', function ($subquery) use ($term) {
        $subquery->select('movie_id')
            ->from('credits')
            ->where(function ($sub) use ($term) {
                LikeOperator::applyContains($sub, 'credits.person_name', $term);
            });
    })
    ->get();
```

`applyContains()` wraps the column through the query's grammar, picks the operator with `LikeOperator::for()`, builds the pattern with `LikeOperator::containsPattern()` and issues one `whereRaw()` call with the correct `ESCAPE` clause for the driver. `applyContainsOnDate()` does the same thing but first casts the date column to text in the right dialect (`::text` on PostgreSQL, `CAST(... AS CHAR)` on MySQL and MariaDB, `CAST(... AS TEXT)` elsewhere), for matching a partial date, month or year that is displayed rather than compared.

`containsPattern()` (and the `escapeWildcards()` it calls) neutralise `%`, `_` and `\` in the search term with `addcslashes()`, so a term containing those characters is matched literally instead of being interpreted as a wildcard. That is why `applyContains()` always appends an `ESCAPE` clause: it tells the driver which character in the pattern is the escape character it just used.

### Per driver behaviour

| Driver | Operator (`LikeOperator::for()`) | `ESCAPE` clause written |
| --- | --- | --- |
| PostgreSQL (`pgsql`) | `ILIKE` | `ESCAPE '\'` |
| MySQL (`mysql`) | `LIKE` | `ESCAPE '\\'` |
| MariaDB (`mariadb`) | `LIKE` | `ESCAPE '\\'` |
| SQLite (`sqlite`) | `LIKE` | `ESCAPE '\'` |

`ILIKE` on PostgreSQL is case insensitive; plain `LIKE` on MySQL depends on the collation of the column (case insensitive by default with the common `*_ci` collations); `LIKE` on SQLite is case sensitive for anything outside ASCII and case insensitive for ASCII letters only. The `ESCAPE` clause is doubled to `'\\'` on MySQL and MariaDB because those drivers process backslash escapes inside string literals before the `LIKE` parser sees them, so a single backslash never reaches it. This was proved against a real MySQL 8 instance: the single backslash form fails with `SQLSTATE[42000]`, a syntax error at the escape literal. PostgreSQL and SQLite take the single backslash as is.

## When not to use it

If all you need is a case insensitive partial match inside a [`spatie/laravel-query-builder`](https://github.com/spatie/laravel-query-builder) filter, reach for `AllowedFilter::partial()` instead. It has solved exactly that since 2018, with a `LOWER()` wrapper that already works the same on every driver Spatie's package supports:

```php
AllowedFilter::partial('title');
```

`LikeOperator` earns its place for hand written raw queries: concatenation with other `whereRaw()` calls, subqueries, joins, anything where you are already composing SQL by hand and need the operator, the pattern and the escaping to agree with each other across drivers. It is not a replacement for `AllowedFilter::partial()` in the common case.

## `YearExpression`

`YearExpression::numeric()` returns a SQL fragment that evaluates to the year as an integer, for ordering and comparisons. `YearExpression::text()` returns the year as text, for concatenating into a displayed value. Both wrap the column through the query's grammar and both take the `Builder` so the connection is resolved where the query actually runs.

```php
use PlinCode\SqlDialect\YearExpression;
use Illuminate\Support\Facades\DB;

$query = Movie::query();

$query->orderByRaw(YearExpression::numeric($query, 'release_date').' desc');

$query->select([
    'movies.*',
    DB::raw(YearExpression::text($query, 'release_date').' as release_year'),
]);
```

### Expressions generated per driver

| Driver | `numeric()` | `text()` |
| --- | --- | --- |
| PostgreSQL (`pgsql`) | `EXTRACT(YEAR FROM <column>)` | `EXTRACT(YEAR FROM <column>)::text` |
| MySQL (`mysql`) | `EXTRACT(YEAR FROM <column>)` | `YEAR(<column>)` |
| MariaDB (`mariadb`) | `EXTRACT(YEAR FROM <column>)` | `YEAR(<column>)` |
| SQLite (`sqlite`) | `CAST(strftime('%Y', <column>) AS INTEGER)` | `strftime('%Y', <column>)` |

## `CsvValues`

`CsvValues::parse()` normalises a filter value, whether it arrives as a comma separated string, an array, or a single scalar, into a clean `list<string>`: whitespace trimmed, blanks dropped, order preserved, keys reindexed.

Note that [`spatie/laravel-query-builder`](https://github.com/spatie/laravel-query-builder) **already** splits a filter value on commas before your filter class sees it, so by the time `$value` reaches a custom filter it is typically an array already. `CsvValues` does not solve splitting; it solves what comes after it, cleaning up the values before your application logic runs:

```php
use PlinCode\SqlDialect\CsvValues;
use Spatie\QueryBuilder\Filters\Filter;

final class GenresFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $genres = CsvValues::parse($value);

        if ($genres === []) {
            return;
        }

        $query->whereIn($property, $genres);
    }
}
```

Non scalar entries (a nested array or an object slipped into the value by mistake) are silently discarded: each one is turned into an empty string and then filtered out with the rest of the blanks, with no error or signal that it happened. This is deliberate and existing consumers rely on it, so it stays as documented behaviour rather than being changed.

## Compatibility

| | Supported |
| --- | --- |
| PHP | 8.3, 8.4, 8.5 |
| Laravel | 12, 13 (`illuminate/database` and `illuminate/support` `^12.0 \|\| ^13.0`) |
| Drivers proved by the test suite | SQLite, MySQL 8, PostgreSQL 16 |
| Drivers handled but not tested | MariaDB (the `mariadb` branches exist in the code but the test matrix does not run against it) |

### Running the tests against the three drivers

The full suite (34 tests) runs against an in memory SQLite database by default:

```bash
vendor/bin/pest
```

Tests marked `->group('integration')` are the dialect dependent ones. They can be pointed at a real MySQL or PostgreSQL instance with the same environment variables the CI workflow uses:

```bash
# against MySQL 8
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=testing DB_USERNAME=root DB_PASSWORD=root \
    vendor/bin/pest --group=integration

# against PostgreSQL 16
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=testing DB_USERNAME=postgres DB_PASSWORD=postgres \
    vendor/bin/pest --group=integration
```

CI runs the same two commands against `mysql:8` and `postgres:16` service containers; see `.github/workflows/integration-tests.yml`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel SQL Dialect! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Daniele Barbaro](https://github.com/plin-code)
- [All Contributors](../../contributors)

## License

Laravel SQL Dialect is open sourced software licensed under the [MIT license](LICENSE.md).
