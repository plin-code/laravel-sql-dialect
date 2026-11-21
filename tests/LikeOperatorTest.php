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

it('wraps an escaped term in a starts with pattern', function (): void {
    expect(LikeOperator::startsWithPattern('50%'))->toBe('50\\%%')
        ->and(LikeOperator::startsWithPattern('a_b\\c'))->toBe('a\\_b\\\\c%')
        ->and(LikeOperator::startsWithPattern(''))->toBe('%');
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

it('finds rows starting with the term', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Rovigo']);
    Document::create(['title' => 'Verona']);
    Document::create(['title' => 'Bergamo']);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', 'Ro');

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['Roma', 'Rovigo']);
})->group('integration');

it('does not match the term in the middle or at the end', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Monterotondo']);
    Document::create(['title' => 'Bro']);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', 'ro');

    expect($query->pluck('title')->all())->toBe(['Roma']);
})->group('integration');

it('ignores the case of ascii letters in a starts with clause', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Verona']);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', 'rOM');

    expect($query->pluck('title')->all())->toBe(['Roma']);
})->group('integration');

it('treats a percent sign in the term as a literal in a starts with clause', function (): void {
    Document::create(['title' => '100% remote']);
    Document::create(['title' => '1000 remote']);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', '100%');

    expect($query->pluck('title')->all())->toBe(['100% remote']);
})->group('integration');

it('treats an underscore in the term as a literal in a starts with clause', function (): void {
    Document::create(['title' => 'a_b c']);
    Document::create(['title' => 'axb c']);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', 'a_b');

    expect($query->pluck('title')->all())->toBe(['a_b c']);
})->group('integration');

it('treats a backslash in the term as a literal in a starts with clause', function (): void {
    Document::create(['title' => 'path\\to']);
    Document::create(['title' => 'pathto']);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', 'path\\');

    expect($query->pluck('title')->all())->toBe(['path\\to']);
})->group('integration');

it('matches every non null row for an empty term in a starts with clause', function (): void {
    Document::create(['title' => 'a', 'summary' => 'x']);
    Document::create(['title' => 'b', 'summary' => '']);
    Document::create(['title' => 'c', 'summary' => null]);

    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'summary', '');

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['a', 'b']);
})->group('integration');

it('adds a starts with clause with or instead of and', function (): void {
    Document::create(['title' => 'Roma', 'summary' => 'keep']);
    Document::create(['title' => 'Verona', 'summary' => 'keep']);
    Document::create(['title' => 'Padova', 'summary' => 'keep']);
    Document::create(['title' => 'Roma', 'summary' => 'drop']);

    $query = Document::query()
        ->where('summary', 'keep')
        ->where(function (Builder $query): void {
            LikeOperator::orApplyStartsWith($query, 'title', 'Ro');
            LikeOperator::orApplyStartsWith($query, 'title', 'Ver');
        });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['Roma', 'Verona']);
})->group('integration');

it('excludes rows starting with the term', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Rovigo']);
    Document::create(['title' => 'Verona']);

    $query = Document::query();
    LikeOperator::applyNotStartsWith($query, 'title', 'Ro');

    expect($query->pluck('title')->all())->toBe(['Verona']);
})->group('integration');

it('keeps a row containing but not starting with the term in a not starts with clause', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Monterotondo']);

    $query = Document::query();
    LikeOperator::applyNotStartsWith($query, 'title', 'ro');

    expect($query->pluck('title')->all())->toBe(['Monterotondo']);
})->group('integration');

it('treats a percent sign as a literal in a not starts with clause', function (): void {
    Document::create(['title' => '100% remote']);
    Document::create(['title' => '1000 remote']);

    $query = Document::query();
    LikeOperator::applyNotStartsWith($query, 'title', '100%');

    expect($query->pluck('title')->all())->toBe(['1000 remote']);
})->group('integration');

it('ands consecutive not starts with clauses', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Verona']);
    Document::create(['title' => 'Padova']);

    $query = Document::query();
    LikeOperator::applyNotStartsWith($query, 'title', 'Ro');
    LikeOperator::applyNotStartsWith($query, 'title', 'Ver');

    expect($query->pluck('title')->all())->toBe(['Padova']);
})->group('integration');

it('excludes a null column from a not starts with clause', function (): void {
    Document::create(['title' => 'a', 'summary' => null]);
    Document::create(['title' => 'b', 'summary' => 'remote']);
    Document::create(['title' => 'c', 'summary' => 'office']);

    $query = Document::query();
    LikeOperator::applyNotStartsWith($query, 'summary', 'rem');

    expect($query->pluck('title')->all())->toBe(['c']);
})->group('integration');

it('adds a not starts with clause with or instead of and', function (): void {
    Document::create(['title' => 'Roma', 'summary' => 'keep']);
    Document::create(['title' => 'Roma', 'summary' => 'drop']);
    Document::create(['title' => 'Verona', 'summary' => 'drop']);

    $query = Document::query()->where(function (Builder $query): void {
        $query->where('summary', 'keep');
        LikeOperator::orApplyNotStartsWith($query, 'title', 'Ro');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['Roma', 'Verona'])
        ->and($query->pluck('summary')->sort()->values()->all())->toBe(['drop', 'keep']);
})->group('integration');

it('accepts the boolean and the negation as arguments of starts with', function (): void {
    Document::create(['title' => 'Roma']);
    Document::create(['title' => 'Rovigo']);
    Document::create(['title' => 'Verona']);

    $query = Document::query()->where(function (Builder $query): void {
        LikeOperator::applyStartsWith($query, 'title', 'Ro', 'or', true);
        LikeOperator::applyStartsWith($query, 'title', 'Rov', 'or');
    });

    expect($query->orderBy('id')->pluck('title')->all())->toBe(['Rovigo', 'Verona']);
})->group('integration');

it('binds the term and writes the operator and escape clause for the driver', function (): void {
    $query = Document::query();
    LikeOperator::applyStartsWith($query, 'title', 'Ro%');

    $operator = driver() === 'pgsql' ? 'ILIKE' : 'LIKE';
    $escape = in_array(driver(), ['mysql', 'mariadb'], true) ? "ESCAPE '\\\\'" : "ESCAPE '\\'";

    expect($query->toRawSql())->toContain($operator)->toContain($escape)
        ->and($query->getBindings())->toBe(['Ro\\%%']);
})->group('integration');

it('rejects a boolean other than and or or in a starts with clause', function (): void {
    LikeOperator::applyStartsWith(Document::query(), 'title', 'x', 'xor');
})->throws(InvalidArgumentException::class, "The boolean must be 'and' or 'or', got 'xor'.");
