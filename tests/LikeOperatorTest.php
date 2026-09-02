<?php

declare(strict_types=1);

use PlinCode\SqlDialect\LikeOperator;
use Workbench\App\Models\Document;

it('escapes like wildcards', function (): void {
    expect(LikeOperator::escapeWildcards('50% off_now'))->toBe('50\\% off\\_now');
});

it('escapes a literal backslash', function (): void {
    expect(LikeOperator::escapeWildcards('a\\b'))->toBe('a\\\\b');
});

it('leaves a plain term untouched', function (): void {
    expect(LikeOperator::escapeWildcards('plain term'))->toBe('plain term');
});

it('wraps an escaped term in a contains pattern', function (): void {
    expect(LikeOperator::containsPattern('50%'))->toBe('%50\\%%');
});

it('picks the operator for the driver', function (): void {
    $expected = driver() === 'pgsql' ? 'ILIKE' : 'LIKE';

    expect(LikeOperator::for(Document::query()))->toBe($expected);
})->group('integration');

it('finds rows containing a plain substring', function (): void {
    Document::create(['title' => 'quotation 2019']);
    Document::create(['title' => 'invoice 2020']);

    $query = Document::query();
    LikeOperator::applyContains($query, 'title', 'quotation');

    expect($query->pluck('title')->all())->toBe(['quotation 2019']);
})->group('integration');

it('treats a percent sign in the term as a literal', function (): void {
    Document::create(['title' => '50% off']);
    Document::create(['title' => '50 off']);

    $query = Document::query();
    LikeOperator::applyContains($query, 'title', '50%');

    expect($query->pluck('title')->all())->toBe(['50% off']);
})->group('integration');

it('treats an underscore in the term as a literal', function (): void {
    Document::create(['title' => 'a_b']);
    Document::create(['title' => 'axb']);

    $query = Document::query();
    LikeOperator::applyContains($query, 'title', 'a_b');

    expect($query->pluck('title')->all())->toBe(['a_b']);
})->group('integration');

it('treats a backslash in the term as a literal', function (): void {
    Document::create(['title' => 'path\\to']);
    Document::create(['title' => 'pathto']);

    $query = Document::query();
    LikeOperator::applyContains($query, 'title', 'path\\to');

    expect($query->pluck('title')->all())->toBe(['path\\to']);
})->group('integration');

it('matches a substring of a date column', function (): void {
    Document::create(['title' => 'a', 'issued_on' => '2019-07-14']);
    Document::create(['title' => 'b', 'issued_on' => '2020-07-14']);

    $query = Document::query();
    LikeOperator::applyContainsOnDate($query, 'issued_on', '2019');

    expect($query->pluck('title')->all())->toBe(['a']);
})->group('integration');

it('matches a substring of a timestamp column', function (): void {
    Document::create(['title' => 'a', 'recorded_at' => '2019-07-14 10:00:00']);
    Document::create(['title' => 'b', 'recorded_at' => '2020-07-14 10:00:00']);

    $query = Document::query();
    LikeOperator::applyContainsOnDate($query, 'recorded_at', '2019');

    expect($query->pluck('title')->all())->toBe(['a']);
})->group('integration');

it('wraps a column name that is a reserved word', function (): void {
    Document::create(['title' => 'a', 'from' => '2019-07-14']);

    $query = Document::query();
    LikeOperator::applyContainsOnDate($query, 'from', '2019');

    expect($query->pluck('title')->all())->toBe(['a']);
})->group('integration');
