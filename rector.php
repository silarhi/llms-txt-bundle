<?php

declare(strict_types=1);

/*
 * This file is part of the LLMs.txt Bundle package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

return RectorConfig::configure()
    ->withCache(__DIR__ . '/var/tools/rector')
    ->withPaths([
        'src',
        'tests',
    ])
    ->withPhpSets(php82: true)
    ->withComposerBased(
        twig: true,
        symfony: true,
    )
    ->withSkip([
        // Symfony 8.1 moves the bundle classes to DependencyInjection\Kernel: the bundle still supports 6.4 and 7.x
        RenameClassRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
