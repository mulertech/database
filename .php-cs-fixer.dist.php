<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
    ->setRules(
        [
            '@PSR12' => true,
            '@PHP84Migration' => true,
            'array_syntax' => ['syntax' => 'short'],
            'strict_param' => true,
            'declare_strict_types' => true,
        ]
    )
    ->setRiskyAllowed(true)
    ->setFinder($finder);