<?php

declare(strict_types=1);

$finderClass = 'PhpCsFixer\\Finder';
$configClass = 'PhpCsFixer\\Config';

$finder = $finderClass::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/public',
        __DIR__ . '/views',
    ])
    ->exclude(['assets/vendor'])
    ->name('*.php');

return (new $configClass())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
