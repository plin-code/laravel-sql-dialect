<?php

declare(strict_types=1);

use PlinCode\SqlDialect\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The driver the current run is pointed at. Tests that assert dialect
 * specific behaviour branch on this instead of hard coding a driver.
 */
function driver(): string
{
    return (string) config('database.default');
}
