<?php

declare(strict_types=1);

use PlinCode\SqlDialect\YearExpression;
use Workbench\App\Models\Document;

it('reads the year of a date column as an integer', function (): void {
    Document::create(['title' => 'a', 'issued_on' => '2019-07-14']);

    $expression = YearExpression::numeric(Document::query(), 'issued_on');
    $year = Document::query()->selectRaw("{$expression} as y")->value('y');

    expect((int) $year)->toBe(2019);
})->group('integration');

it('reads the year of a timestamp column as an integer', function (): void {
    Document::create(['title' => 'a', 'recorded_at' => '2019-07-14 10:00:00']);

    $expression = YearExpression::numeric(Document::query(), 'recorded_at');
    $year = Document::query()->selectRaw("{$expression} as y")->value('y');

    expect((int) $year)->toBe(2019);
})->group('integration');

it('reads the year of a date column as text', function (): void {
    Document::create(['title' => 'a', 'issued_on' => '2019-07-14']);

    $expression = YearExpression::text(Document::query(), 'issued_on');
    $year = Document::query()->selectRaw("{$expression} as y")->value('y');

    expect((string) $year)->toBe('2019');
})->group('integration');

it('orders by the numeric year', function (): void {
    Document::create(['title' => 'newer', 'issued_on' => '2021-01-01']);
    Document::create(['title' => 'older', 'issued_on' => '2019-01-01']);

    $expression = YearExpression::numeric(Document::query(), 'issued_on');

    expect(Document::query()->orderByRaw("{$expression} asc")->pluck('title')->all())
        ->toBe(['older', 'newer']);
})->group('integration');

it('wraps a column name that is a reserved word', function (): void {
    Document::create(['title' => 'a', 'from' => '2019-07-14']);

    $expression = YearExpression::numeric(Document::query(), 'from');
    $year = Document::query()->selectRaw("{$expression} as y")->value('y');

    expect((int) $year)->toBe(2019);
})->group('integration');

it('quotes the identifier with the grammar of the connection', function (): void {
    $wrapped = Document::query()->getGrammar()->wrap('from');

    expect(YearExpression::numeric(Document::query(), 'from'))->toContain($wrapped)
        ->and(YearExpression::text(Document::query(), 'from'))->toContain($wrapped);
})->group('integration');
