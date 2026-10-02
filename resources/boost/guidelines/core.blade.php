## Laravel SQL Dialect

Static helpers in `PlinCode\SqlDialect` for SQL that must behave the same on PostgreSQL, MySQL and SQLite. There is no service provider, config, facade or macro: import the class and call it. Use them instead of writing the driver specific SQL by hand.

### Partial text matches

Do not write `->where('title', 'like', "%{$term}%")` with a user supplied term: `%`, `_` and `\` in the term act as wildcards, and case sensitivity differs per driver. `LikeOperator::applyContains()` escapes the term, binds it, adds the right `ESCAPE` clause, and uses `ILIKE` on PostgreSQL and `LIKE` elsewhere.

@verbatim
<code-snippet name="Wildcard safe contains filter" lang="php">
use Illuminate\Database\Eloquent\Builder;
use PlinCode\SqlDialect\LikeOperator;

Movie::query()
    ->where(function (Builder $query) use ($term, $excluded) {
        LikeOperator::applyContains($query, 'title', $term);
        LikeOperator::applyNotContains($query, 'title', $excluded);
    })
    ->get();
</code-snippet>
@endverbatim

- Variants: `LikeOperator::orApplyContains()`, `LikeOperator::applyNotContains()`, `LikeOperator::orApplyNotContains()`. Wrap OR clauses in a closure so they do not leak into the rest of the query. A `NULL` column matches neither the positive nor the negated form.
- `LikeOperator::applyContainsOnDate()` matches a partial date by casting the column to text first, instead of a hand written `CAST(... AS CHAR)` or `::text`.
- These methods take `Illuminate\Database\Eloquent\Builder` only, not `Illuminate\Database\Query\Builder` (so not inside `whereExists()` or `orWhereIn()` closures).
- `$column` is interpolated into raw SQL: pass a column name from your code, never request input. The term is always bound.
- Building SQL by hand: `LikeOperator::for()` returns the operator, `LikeOperator::containsPattern()` the escaped `%term%` pattern.

### Year of a date column

Do not write `YEAR(...)`, `EXTRACT(YEAR FROM ...)` or `strftime('%Y', ...)` in `whereRaw()` or `orderByRaw()`. Use `YearExpression::numeric()` (integer, for ordering and comparisons) or `YearExpression::text()` (text, for display).

@verbatim
<code-snippet name="Year expressions" lang="php">
use PlinCode\SqlDialect\YearExpression;

$query->whereRaw(YearExpression::numeric($query, 'release_date').' = ?', [$year]);
$query->orderByRaw(YearExpression::numeric($query, 'release_date').' desc');
</code-snippet>
@endverbatim

### Multi value filters

Instead of `explode(',', ...)` plus `trim()` and `array_filter()`, call `CsvValues::parse($value)`. It accepts an array, a comma separated string or a scalar and returns a `list<string>`: trimmed, blanks dropped, order kept. Non scalar entries are dropped silently.

### Drivers

Supported: `pgsql`, `mysql`, `mariadb` (handled but not covered by the test suite), `sqlite`. Any other driver, `sqlsrv` included, is unsupported and raises no error up front: `YearExpression::numeric()`, `YearExpression::text()` and `LikeOperator::applyContainsOnDate()` fall back to syntax that fails at the database at query time.
