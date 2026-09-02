<?php

declare(strict_types=1);

use PlinCode\SqlDialect\CsvValues;

it('splits a comma separated string', function (): void {
    expect(CsvValues::parse('a,b,c'))->toBe(['a', 'b', 'c']);
});

it('trims whitespace around each value', function (): void {
    expect(CsvValues::parse('a, b ,  c '))->toBe(['a', 'b', 'c']);
});

it('drops blank entries and reindexes the list', function (): void {
    expect(CsvValues::parse('a,,b'))->toBe(['a', 'b']);
});

it('accepts an array as is', function (): void {
    expect(CsvValues::parse(['a', 'b']))->toBe(['a', 'b']);
});

it('wraps a single string without commas', function (): void {
    expect(CsvValues::parse('a'))->toBe(['a']);
});

it('casts scalars to string', function (): void {
    expect(CsvValues::parse([1, 2.5, true]))->toBe(['1', '2.5', '1']);
});

it('discards non scalar entries', function (): void {
    expect(CsvValues::parse(['a', ['nested'], new stdClass, 'b']))->toBe(['a', 'b']);
});

it('returns an empty list for an empty string', function (): void {
    expect(CsvValues::parse(''))->toBe([]);
});

it('returns an empty list for null', function (): void {
    expect(CsvValues::parse(null))->toBe([]);
});

it('preserves the order of the input', function (): void {
    expect(CsvValues::parse('c,a,b'))->toBe(['c', 'a', 'b']);
});
