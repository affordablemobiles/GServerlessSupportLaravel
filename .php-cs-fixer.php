<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->ignoreDotFiles(false)
    ->ignoreVCSIgnored(true)
    ->in(__DIR__)
;

$config = new Config();
$config
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP83Migration'        => true,
        '@PHP80Migration:risky'  => true,
        'heredoc_indentation'    => false,
        '@PhpCsFixer'            => true,
        '@PhpCsFixer:risky'      => true,
        'binary_operator_spaces' => [
            'default' => 'align',
        ],
    ])
    ->setFinder($finder)
;

return $config;
