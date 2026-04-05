<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                      => true,
        'strict_param'                => true,
        'declare_strict_types'        => true,
        'array_syntax'                => ['syntax' => 'short'],
        'ordered_imports'             => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'           => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar'               => true,
        'unary_operator_spaces'       => true,
        'binary_operator_spaces'      => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'single_quote'                => true,
        'no_extra_blank_lines'        => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
