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

namespace Silarhi\LlmsTxtBundle\Tests\EventListener;

namespace Silarhi\LlmsTxtBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\DependencyInjection\MarkdownEscapingPass;
use Silarhi\LlmsTxtBundle\Twig\MarkdownEscapingStrategy;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Environment;

final class MarkdownEscapingPassTest extends TestCase
{
    public function testSetsTheStrategyWithTheMarkdownVersions(): void
    {
        $container = $this->createContainer('name', markdownVersions: true);

        (new MarkdownEscapingPass())->process($container);

        self::assertSame([MarkdownEscapingStrategy::class, 'guess'], $this->getAutoescape($container));
    }

    public function testLeavesTwigWithoutTheMarkdownVersions(): void
    {
        $container = $this->createContainer('name', markdownVersions: false);

        (new MarkdownEscapingPass())->process($container);

        self::assertSame('name', $this->getAutoescape($container));
    }

    public function testLeavesTheStrategyOfTheApplication(): void
    {
        $strategy = ['app.escaping_strategy', 'guess'];
        $container = $this->createContainer($strategy, markdownVersions: true);

        (new MarkdownEscapingPass())->process($container);

        self::assertSame($strategy, $this->getAutoescape($container));
    }

    public function testLeavesAContainerWithoutTwig(): void
    {
        $container = new ContainerBuilder();
        $container->register(MarkdownEscapingPass::LISTENER);

        (new MarkdownEscapingPass())->process($container);

        self::assertFalse($container->hasDefinition('twig'));
    }

    /**
     * @param string|list<string> $autoescape
     */
    private function createContainer(string|array $autoescape, bool $markdownVersions): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig', new Definition(Environment::class, [null, ['debug' => false, 'autoescape' => $autoescape]]));
        if ($markdownVersions) {
            $container->register(MarkdownEscapingPass::LISTENER);
        }

        return $container;
    }

    private function getAutoescape(ContainerBuilder $container): mixed
    {
        $options = $container->getDefinition('twig')->getArgument(1);
        self::assertIsArray($options);

        return $options['autoescape'] ?? null;
    }
}
