<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/config',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withSkip([
        // Conflicts with Pint's increment_style (post-increment); Pint is the canonical formatter.
        PostIncDecToPreIncDecRector::class,
    ]);
