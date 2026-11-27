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

namespace Silarhi\LlmsTxtBundle\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\LlmsTxt;
use Silarhi\LlmsTxtBundle\LlmsTxtBundle;

/**
 * Needs symfony/dependency-injection: the "PHPUnit without the bundle dependencies" CI job removes it and excludes this
 * group.
 */
#[Group('bundle')]
final class LlmsTxtBundleTest extends TestCase
{
    public function testKeepsTheConstantsMovedToLlmsTxt(): void
    {
        self::assertSame(LlmsTxt::FILENAME, LlmsTxtBundle::FILENAME);
        self::assertSame(LlmsTxt::ROUTE, LlmsTxtBundle::ROUTE);
        self::assertSame(LlmsTxt::FULL_FILENAME, LlmsTxtBundle::FULL_FILENAME);
        self::assertSame(LlmsTxt::FULL_ROUTE, LlmsTxtBundle::FULL_ROUTE);
        self::assertSame(LlmsTxt::MARKDOWN_FORMAT, LlmsTxtBundle::MARKDOWN_FORMAT);
    }
}
