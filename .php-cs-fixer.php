<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    'declare_strict_types' => true,
    'final_class' => true,
    'final_public_method_for_abstract_class' => true,
    'native_function_invocation' => ['include' => ['@compiler_optimized']],
    'no_unused_imports' => true,
    'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
    'phpdoc_order' => true,
    'strict_comparison' => true,
    'strict_param' => true,
])
    ->setFinder($finder)
    ->setRiskyAllowed(true);