<?php

declare(strict_types=1);

arch('the package does not depend on spatie')
    ->expect('PlinCode\SqlDialect')
    ->not->toUse('Spatie');

arch('the package does not depend on the host application')
    ->expect('PlinCode\SqlDialect')
    ->not->toUse('App');

arch('every class is final')
    ->expect('PlinCode\SqlDialect')
    ->toBeFinal();

arch('nothing is left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('strict types everywhere')
    ->expect('PlinCode\SqlDialect')
    ->toUseStrictTypes();
