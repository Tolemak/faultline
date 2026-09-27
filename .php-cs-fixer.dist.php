<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'assets', 'public/bundles', 'public/assets'])
    ->notPath(['config/bundles.php', 'config/reference.php'])
    ->append([__DIR__.'/bin/console', __DIR__.'/bin/check-coverage.php']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
