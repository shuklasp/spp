<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/spp/core')
    ->in(__DIR__ . '/spp/modules')
    ->exclude('oldsrc');

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
