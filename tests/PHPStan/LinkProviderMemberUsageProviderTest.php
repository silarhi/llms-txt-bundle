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

namespace Silarhi\LlmsTxtBundle\Tests\PHPStan;

use Override;
use PhpParser\Node;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ShipMonk\PHPStan\DeadCode\Rule\DeadCodeRule;

/**
 * @extends RuleTestCase<DeadCodeRule>
 */
final class LinkProviderMemberUsageProviderTest extends RuleTestCase
{
    public function testKeepsTheLinkProviders(): void
    {
        $this->analyse([
            __DIR__ . '/Data/MethodLinkProvider.php',
            __DIR__ . '/Data/InvokableLinkProvider.php',
            __DIR__ . '/Data/NamedMethodLinkProvider.php',
            __DIR__ . '/Data/NotALinkProvider.php',
        ], [
            ['Unused Silarhi\LlmsTxtBundle\Tests\PHPStan\Data\MethodLinkProvider::forgotten', 43],
            ['Unused Silarhi\LlmsTxtBundle\Tests\PHPStan\Data\InvokableLinkProvider::forgotten', 33],
            ['Unused Silarhi\LlmsTxtBundle\Tests\PHPStan\Data\NamedMethodLinkProvider::forgotten', 44],
            ['Unused Silarhi\LlmsTxtBundle\Tests\PHPStan\Data\NotALinkProvider::links', 23],
        ]);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../vendor/shipmonk/dead-code-detector/rules.neon',
            __DIR__ . '/../../shipmonk.neon',
        ];
    }

    #[Override]
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(DeadCodeRule::class);
    }

    /**
     * @return list<Collector<Node, mixed>>
     */
    #[Override]
    protected function getCollectors(): array
    {
        /** @var list<Collector<Node, mixed>> $collectors */
        $collectors = self::getContainer()->getServicesByTag('phpstan.collector');

        return array_values(array_filter($collectors, static fn (Collector $collector): bool => str_starts_with($collector::class, 'ShipMonk\\')));
    }
}
