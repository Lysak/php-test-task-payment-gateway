<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/examples',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withImportNames(removeUnusedImports: true) // auto-use statements
    ->withTypeCoverageLevel(3)
    ->withDeadCodeLevel(3)
    ->withCodeQualityLevel(3);
