<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'braces_position' => [
            'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],
        'single_line_empty_body' => true,
        'indentation_type' => true,
        'array_indentation' => true,
        'method_chaining_indentation' => true,
    ])
    ->setFinder($finder);
