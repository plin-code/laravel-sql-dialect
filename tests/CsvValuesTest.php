<?php

declare(strict_types=1);

use PlinCode\SqlDialect\CsvValues;

it('splits a comma separated string', function () {
    expect(CsvValues::parse('a,b,c'))->toBe(['a', 'b', 'c']);
});

it('trims whitespace around each value', function () {
    expect(CsvValues::parse('a, b ,  c '))->toBe(['a', 'b', 'c']);
});

it('drops blank entries and reindexes the list', function () {
    expect(CsvValues::parse('a,,b'))->toBe(['a', 'b']);
});

it('accepts an array as is', function () {
    expect(CsvValues::parse(['a', 'b']))->toBe(['a', 'b']);
});

it('wraps a single string without commas', function () {
    expect(CsvValues::parse('a'))->toBe(['a']);
});

it('casts scalars to string', function () {
    expect(CsvValues::parse([1, 2.5, true]))->toBe(['1', '2.5', '1']);
});

it('discards non scalar entries', function () {
    expect(CsvValues::parse(['a', ['nested'], new stdClass, 'b']))->toBe(['a', 'b']);
});

it('returns an empty list for an empty string', function () {
    expect(CsvValues::parse(''))->toBe([]);
});

it('returns an empty list for null', function () {
    expect(CsvValues::parse(null))->toBe([]);
});

it('preserves the order of the input', function () {
    expect(CsvValues::parse('c,a,b'))->toBe(['c', 'a', 'b']);
});
