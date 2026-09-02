# Changelog

All notable changes to `laravel-sql-dialect` will be documented in this file.

## v1.0.1 - Metadata only - 2026-09-02

### Metadata only

No code changes. `src/` is byte identical to v1.0.0.

This release exists to correct package metadata that was fixed after v1.0.0 was tagged. Packagist froze its snapshot of v1.0.0 at the earlier commit, correctly refusing the re-tag, so the corrected metadata needs a new version to reach it.

#### What changed

The package description and the Packagist keywords now match the ones shown on GitHub, and the keyword list gained the three drivers the suite actually runs against (`postgresql`, `mysql`, `sqlite`) plus `query-builder`. `CHANGELOG.md` now carries the 1.0.0 entry, which the release automation added after v1.0.0 had already been tagged.

If you are on v1.0.0 there is no reason to upgrade beyond tidiness. The code you have is the code in this release.

## v1.0.0 - Cross driver SQL helpers - 2026-09-02

### 🧩 Cross driver SQL helpers for Laravel

First release. Three static helper classes that let you write `LIKE` queries and year extractions which behave the same on PostgreSQL, MySQL and SQLite, without scattering `match ($driver)` through your services and repositories.

No service provider to register, no config to publish, no facade, no macro.

#### What is in it

**`LikeOperator`** picks `ILIKE` on PostgreSQL and `LIKE` elsewhere, escapes `%`, `_` and `\` in the search term so they match literally, and issues a single `whereRaw()` with the right `ESCAPE` clause for the driver. `applyContainsOnDate()` does the same after casting a date column to text in the correct dialect.

**`YearExpression`** returns the year of a date column as a SQL fragment, either as an integer for ordering and comparisons (`numeric()`) or as text for display (`text()`). It takes the query builder rather than a driver name, so the connection is resolved where the query actually runs. That matters in a multi connection application, where asking the caller to resolve the driver invites resolving the wrong one.

**`CsvValues`** normalises a filter value into a clean `list<string>`: it trims, drops blanks, preserves order and reindexes. It does not solve splitting on commas. It solves what comes after.

#### The MySQL escape bug, proved rather than assumed

The obvious implementation emits `ESCAPE '\'`. Against a real MySQL 8 that fails with `SQLSTATE[42000]`, a syntax error at the escape literal, because MySQL processes backslash escapes inside string literals before the `LIKE` parser sees them. The backslash is therefore doubled in the SQL text on MySQL and MariaDB, and written once on PostgreSQL and SQLite. This release ships the corrected form, with tests that insert a decoy row for each of `%`, `_` and `\` and assert on what actually comes back.

#### Compatibility

| | |
| --- | --- |
| PHP | 8.4, 8.5 |
| Laravel | 12, 13 |
| Drivers proved by the test suite | SQLite, MySQL 8, PostgreSQL 16 |
| Drivers handled but not tested | MariaDB |

Other drivers, `sqlsrv` included, are not supported.

#### When not to use it

If all you need is a case insensitive partial match inside a `spatie/laravel-query-builder` filter, reach for `AllowedFilter::partial()` instead. It has solved exactly that since 2018. `LikeOperator` earns its place for hand written raw queries, where you are already composing SQL and need the operator, the pattern and the escaping to agree with each other across drivers.

#### Installation

```bash
composer require plin-code/laravel-sql-dialect


```