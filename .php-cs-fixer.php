<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->files()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
    ])
    ->name('*.php');

return new Config()
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
        // The key rule — adds \ before compiler-optimized functions
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'all',
            'strict'  => true,
        ],
        'native_constant_invocation' => [
            'fix_built_in' => true,
            'scope' => 'all',
        ],
    ])
    ->setFinder($finder);
