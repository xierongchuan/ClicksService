<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/examples')
    ->name('*.php');

return (new Config())
    ->setRules([
        '@PSR12'                 => true,
        'declare_strict_types'   => true,
        'strict_param'           => true,
        'no_extra_blank_lines'   => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setUsingCache(true);
