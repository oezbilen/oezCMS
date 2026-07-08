<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/bin',
        __DIR__ . '/plugins',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests'
    ])
    ->exclude([
        'database',
        'docs',
        'vendor',
        'storage',
        'templates',
        'cache',
    ])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => [
            'syntax' => 'short'
        ],
        'strict_param' => true,
        'declare_strict_types' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha'
        ],
        'no_unused_imports' => true,
        'single_quote' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'trailing_comma_in_multiline' => [
            'elements' => [
                'arrays',
                'arguments',
                'parameters',
                'match'
            ]
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline'
        ],
        'phpdoc_align' => true,
        'modifier_keywords' => [
            'elements' => [
                'property',
                'method'
            ]
        ],
    ])
    ->setFinder($finder);
