<?php

declare(strict_types=1);

namespace PlinCode\SqlDialect;

use Illuminate\Support\Arr;

final class CsvValues
{
    /**
     * Normalize a filter value into a clean list of strings.
     *
     * Accepts arrays, comma separated strings, or scalars.
     * Trims whitespace, drops blanks, preserves order, reindexes.
     *
     * Non scalar entries are silently discarded. This is deliberate and
     * relied upon by callers that receive raw request input.
     *
     * @return list<string>
     */
    public static function parse(mixed $value): array
    {
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        $items = array_map(
            static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '',
            Arr::wrap($value),
        );

        return array_values(array_filter($items, static fn (string $v): bool => $v !== ''));
    }
}
