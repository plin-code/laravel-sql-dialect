<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Blade;

/**
 * Class names the guideline uses as stand ins for application code.
 * They are neither imported nor part of the package, so the drift checks
 * skip them. Keep this list short and obviously fictional.
 */
const BOOST_EXAMPLE_CLASSES = ['Movie'];

function boostGuideline(): string
{
    return Blade::render((string) file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php'));
}

/**
 * @return array<string, mixed>
 */
function boostComposer(): array
{
    /** @var array<string, mixed> */
    return json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
}

function boostPackageNamespace(): string
{
    /** @var array{autoload: array{psr-4: array<string, string>}} $composer */
    $composer = boostComposer();

    return rtrim((string) array_key_first($composer['autoload']['psr-4']), '\\');
}

/**
 * The bodies of the code-snippet blocks, where calls carry real arguments.
 *
 * @return list<string>
 */
function boostSnippets(string $rendered): array
{
    preg_match_all('/<code-snippet[^>]*>(.*?)<\/code-snippet>/s', $rendered, $matches);

    return $matches[1];
}

/**
 * Maps the short names imported by `use` lines in the snippets to their FQCN.
 *
 * @return array<string, string>
 */
function boostImports(string $rendered): array
{
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $rendered, $matches, PREG_SET_ORDER);

    $imports = [];

    foreach ($matches as $match) {
        $alias = ($match[2] ?? '') !== '' ? $match[2] : class_basename($match[1]);
        $imports[$alias] = $match[1];
    }

    return $imports;
}

/**
 * Resolves a short class name the way a reader would: an import first,
 * then the package namespace. Null for a declared example class.
 */
function boostResolveClass(string $name, string $rendered): ?string
{
    if (in_array($name, BOOST_EXAMPLE_CLASSES, true)) {
        return null;
    }

    return boostImports($rendered)[$name] ?? boostPackageNamespace().'\\'.$name;
}

/**
 * Splits the argument list starting right after an opening parenthesis
 * into top level arguments, honouring nesting and quoted strings.
 *
 * @return list<string>
 */
function boostArguments(string $source, int $offset): array
{
    $arguments = [];
    $current = '';
    $depth = 0;
    $quote = null;
    $length = strlen($source);

    for ($i = $offset; $i < $length; $i++) {
        $char = $source[$i];

        if ($quote !== null) {
            $current .= $char;

            if ($char === '\\') {
                $current .= $source[++$i] ?? '';
            } elseif ($char === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($char === '"' || $char === "'") {
            $quote = $char;
        } elseif (in_array($char, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($char, [')', ']', '}'], true)) {
            if ($depth === 0) {
                break;
            }

            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $arguments[] = trim($current);
            $current = '';

            continue;
        }

        $current .= $char;
    }

    if (trim($current) !== '') {
        $arguments[] = trim($current);
    }

    return $arguments;
}

describe('Boost guidelines', function (): void {
    it('ships a single core guidelines file', function (): void {
        expect(glob(__DIR__.'/../../resources/boost/guidelines/*'))
            ->toBe([__DIR__.'/../../resources/boost/guidelines/core.blade.php']);
    });

    it('renders as blade without leaving directives behind', function (): void {
        expect(boostGuideline())->not->toContain('@verbatim')
            ->not->toContain('@endverbatim')
            ->not->toContain('{{');
    });

    it('wraps its code examples in code-snippet tags', function (): void {
        expect(boostGuideline())->toContain('<code-snippet')
            ->toContain('</code-snippet>');
    });

    it('keeps the guidelines short enough to stay in context', function (): void {
        expect(strlen(boostGuideline()))->toBeLessThan(6000);
    });

    it('suggests laravel boost without requiring it', function (): void {
        $composer = boostComposer();

        expect($composer['suggest']['laravel/boost'] ?? null)->toBeString()->not->toBe('')
            ->and($composer['require']['laravel/boost'] ?? null)->toBeNull()
            ->and($composer['require-dev']['laravel/boost'] ?? null)->toBeNull();
    });
});

describe('Boost guidelines drift', function (): void {
    it('names only classes and namespaces that exist', function (): void {
        preg_match_all('/\b[A-Z]\w*(?:\\\\[A-Z]\w*)+\b/', boostGuideline(), $matches);

        $names = array_values(array_unique($matches[0]));

        expect($names)->not->toBeEmpty();

        foreach ($names as $name) {
            expect(class_exists($name) || $name === boostPackageNamespace())
                ->toBeTrue("The guideline names {$name}, which is neither a class nor the package namespace.");
        }
    });

    it('calls only static methods that exist', function (): void {
        $rendered = boostGuideline();

        preg_match_all('/\b([A-Z]\w*)::(\w+)\(/', $rendered, $matches, PREG_SET_ORDER);

        expect($matches)->not->toBeEmpty();

        foreach ($matches as [, $class, $method]) {
            $fqcn = boostResolveClass($class, $rendered);

            if ($fqcn === null) {
                continue;
            }

            expect(class_exists($fqcn))->toBeTrue("The guideline uses {$class}::{$method}(), but {$fqcn} does not exist.")
                ->and(method_exists($fqcn, $method))->toBeTrue("The guideline uses {$class}::{$method}(), which {$fqcn} does not declare.");

            expect(new ReflectionMethod($fqcn, $method)->isPublic())
                ->toBeTrue("The guideline uses {$class}::{$method}(), which is not public.");
        }
    });

    it('passes arguments that match the signature in every snippet call', function (): void {
        $rendered = boostGuideline();
        $checked = 0;

        foreach (boostSnippets($rendered) as $snippet) {
            preg_match_all('/\b([A-Z]\w*)::(\w+)\(/', $snippet, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                [$call, $offset] = $match[0];
                $fqcn = boostResolveClass($match[1][0], $rendered);

                if ($fqcn === null || ! method_exists($fqcn, $match[2][0])) {
                    continue;
                }

                $reflection = new ReflectionMethod($fqcn, $match[2][0]);
                $parameters = array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $reflection->getParameters());
                $arguments = boostArguments($snippet, $offset + strlen($call));

                expect(count($arguments))->toBeGreaterThanOrEqual($reflection->getNumberOfRequiredParameters(), "{$call}) passes too few arguments.")
                    ->toBeLessThanOrEqual($reflection->getNumberOfParameters(), "{$call}) passes too many arguments.");

                foreach ($arguments as $argument) {
                    if (preg_match('/^(\w+):(?!:)/', $argument, $named) === 1) {
                        expect($parameters)->toContain($named[1]);
                    }
                }

                $checked++;
            }
        }

        expect($checked)->toBeGreaterThan(0);
    });

    it('chains only builder methods that exist', function (): void {
        preg_match_all('/->(\w+)\(/', boostGuideline(), $matches);

        $methods = array_values(array_unique($matches[1]));

        expect($methods)->not->toBeEmpty();

        foreach ($methods as $method) {
            expect(method_exists(EloquentBuilder::class, $method) || method_exists(QueryBuilder::class, $method))
                ->toBeTrue("The guideline chains ->{$method}(), which neither builder declares.");
        }
    });

    it('lists exactly the drivers the package branches on as supported', function (): void {
        $drivers = [];

        foreach (glob(__DIR__.'/../../src/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            preg_match_all('/match\s*\([^{]*?\)\s*\{(.*?)^\s*\};/ms', $source, $blocks);

            foreach ($blocks[1] as $block) {
                preg_match_all("/'(\\w+)'\\s*(?:,|=>)/", $block, $arms);
                $drivers = [...$drivers, ...$arms[1]];
            }

            preg_match_all("/(?:getDriverName\\(\\)|\\\$driver)\\s*===\\s*'(\\w+)'/", $source, $comparisons);
            $drivers = [...$drivers, ...$comparisons[1]];
        }

        $drivers = array_unique($drivers);
        sort($drivers);

        preg_match('/^Supported:([^.]*)\./m', boostGuideline(), $line);
        preg_match_all('/`(\w+)`/', $line[1] ?? '', $listed);
        $listed = array_unique($listed[1]);
        sort($listed);

        expect($drivers)->toContain('pgsql', 'mysql', 'sqlite')
            ->and($listed)->toBe($drivers);
    });
});
