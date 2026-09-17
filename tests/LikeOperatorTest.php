<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
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

it('adds a contains clause with or instead of and', function (): void {
    Document::create(['title' => 'backend developer']);
    Document::create(['title' => 'data_engineer']);
    Document::create(['title' => 'data engineer']);
    Document::create(['title' => 'designer']);

    $query = Document::query()->where(function (Builder $query): void {
        LikeOperator::orApplyContains($query, 'title', 'backend');
        LikeOperator::orApplyContains($query, 'title', 'data_engineer');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['backend developer', 'data_engineer']);
})->group('integration');

it('keeps an or contains clause inside its group', function (): void {
    Document::create(['title' => 'backend developer', 'summary' => 'keep']);
    Document::create(['title' => 'backend developer', 'summary' => 'drop']);
    Document::create(['title' => 'frontend developer', 'summary' => 'keep']);

    $query = Document::query()
        ->where('summary', 'keep')
        ->where(function (Builder $query): void {
            LikeOperator::orApplyContains($query, 'title', 'backend');
            LikeOperator::orApplyContains($query, 'title', 'nothing matches');
        });

    expect($query->pluck('title')->all())->toBe(['backend developer']);
})->group('integration');

it('treats a percent sign as a literal in an or contains clause', function (): void {
    Document::create(['title' => '100% remote']);
    Document::create(['title' => '100 remote']);

    $query = Document::query()->where(function (Builder $query): void {
        LikeOperator::orApplyContains($query, 'title', '100% remote');
    });

    expect($query->pluck('title')->all())->toBe(['100% remote']);
})->group('integration');

it('excludes rows containing the term', function (): void {
    Document::create(['title' => 'senior engineer']);
    Document::create(['title' => 'junior engineer']);

    $query = Document::query();
    LikeOperator::applyNotContains($query, 'title', 'senior');

    expect($query->pluck('title')->all())->toBe(['junior engineer']);
})->group('integration');

it('treats a percent sign as a literal in a not contains clause', function (): void {
    Document::create(['title' => '100% remote']);
    Document::create(['title' => '100 remote']);

    $query = Document::query();
    LikeOperator::applyNotContains($query, 'title', '100% remote');

    expect($query->pluck('title')->all())->toBe(['100 remote']);
})->group('integration');

it('treats an underscore as a literal in a not contains clause', function (): void {
    Document::create(['title' => 'data_engineer']);
    Document::create(['title' => 'data engineer']);

    $query = Document::query();
    LikeOperator::applyNotContains($query, 'title', 'data_engineer');

    expect($query->pluck('title')->all())->toBe(['data engineer']);
})->group('integration');

it('treats a backslash as a literal in a not contains clause', function (): void {
    Document::create(['title' => 'path\\to']);
    Document::create(['title' => 'pathto']);

    $query = Document::query();
    LikeOperator::applyNotContains($query, 'title', 'path\\to');

    expect($query->pluck('title')->all())->toBe(['pathto']);
})->group('integration');

it('ands consecutive not contains clauses', function (): void {
    Document::create(['title' => 'senior engineer']);
    Document::create(['title' => 'lead engineer']);
    Document::create(['title' => 'engineer']);

    $query = Document::query();
    LikeOperator::applyNotContains($query, 'title', 'senior');
    LikeOperator::applyNotContains($query, 'title', 'lead');

    expect($query->pluck('title')->all())->toBe(['engineer']);
})->group('integration');

it('excludes a null column from a not contains clause', function (): void {
    Document::create(['title' => 'a', 'summary' => null]);
    Document::create(['title' => 'b', 'summary' => 'remote']);
    Document::create(['title' => 'c', 'summary' => 'office']);

    $query = Document::query();
    LikeOperator::applyNotContains($query, 'summary', 'remote');

    expect($query->pluck('title')->all())->toBe(['c']);
})->group('integration');

it('keeps a null column when grouped with or where null', function (): void {
    Document::create(['title' => 'a', 'summary' => null]);
    Document::create(['title' => 'b', 'summary' => 'remote']);
    Document::create(['title' => 'c', 'summary' => 'office']);

    $query = Document::query()->where(function (Builder $query): void {
        $query->whereNull('summary');
        LikeOperator::orApplyNotContains($query, 'summary', 'remote');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['a', 'c']);
})->group('integration');

it('adds a not contains clause with or instead of and', function (): void {
    Document::create(['title' => 'senior engineer', 'summary' => 'keep']);
    Document::create(['title' => 'senior engineer', 'summary' => 'drop']);
    Document::create(['title' => 'junior engineer', 'summary' => 'drop']);

    $query = Document::query()->where(function (Builder $query): void {
        $query->where('summary', 'keep');
        LikeOperator::orApplyNotContains($query, 'title', 'senior');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['senior engineer', 'junior engineer'])
        ->and($query->pluck('summary')->sort()->values()->all())->toBe(['drop', 'keep']);
})->group('integration');

it('accepts the boolean and the negation as arguments', function (): void {
    Document::create(['title' => 'senior engineer']);
    Document::create(['title' => 'junior engineer']);
    Document::create(['title' => 'designer']);

    $query = Document::query()->where(function (Builder $query): void {
        LikeOperator::applyContains($query, 'title', 'engineer', 'or', true);
        LikeOperator::applyContains($query, 'title', 'junior', 'or');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['junior engineer', 'designer']);
})->group('integration');

it('excludes a substring of a date column', function (): void {
    Document::create(['title' => 'a', 'issued_on' => '2019-07-14']);
    Document::create(['title' => 'b', 'issued_on' => '2020-07-14']);

    $query = Document::query();
    LikeOperator::applyContainsOnDate($query, 'issued_on', '2019', 'and', true);

    expect($query->pluck('title')->all())->toBe(['b']);
})->group('integration');

it('matches a substring of a date column with or', function (): void {
    Document::create(['title' => 'a', 'issued_on' => '2019-07-14']);
    Document::create(['title' => 'b', 'issued_on' => '2020-07-14']);
    Document::create(['title' => 'c', 'issued_on' => '2021-07-14']);

    $query = Document::query()->where(function (Builder $query): void {
        LikeOperator::applyContainsOnDate($query, 'issued_on', '2019', 'or');
        LikeOperator::applyContainsOnDate($query, 'issued_on', '2021', 'or');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['a', 'c']);
})->group('integration');

it('rejects a boolean other than and or or', function (): void {
    LikeOperator::applyContains(Document::query(), 'title', 'x', 'xor');
})->throws(InvalidArgumentException::class, "The boolean must be 'and' or 'or', got 'xor'.");
