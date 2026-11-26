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

namespace Silarhi\LlmsTxtBundle\Tests\DependencyInjection;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\DependencyInjection\LinkProviderPass;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\LinkProviderListener;
use Silarhi\LlmsTxtBundle\Exception\InvalidLinkProviderException;
use Silarhi\LlmsTxtBundle\Tests\Fixtures\CatalogLinks;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class LinkProviderPassTest extends TestCase
{
    public function testRegistersAListenerPerTag(): void
    {
        $container = new ContainerBuilder();
        $container->register('catalog', CatalogLinks::class)
            ->addTag(LinkProviderPass::TAG, ['section' => 'Offers', 'method' => 'offers', 'priority' => -10])
            ->addTag(LinkProviderPass::TAG, ['section' => 'Blog']);

        (new LinkProviderPass())->process($container);

        $offers = $container->getDefinition('.llms_txt.link_provider.catalog.0');
        self::assertSame(LinkProviderListener::class, $offers->getClass());
        self::assertSame([['event' => LlmsTxtPopulateEvent::class, 'method' => '__invoke', 'priority' => -10]], $offers->getTag('kernel.event_listener'));
        self::assertSame('Offers', $offers->getArgument(1));
        self::assertSame('catalog::offers', $offers->getArgument(2));

        $closure = $offers->getArgument(0);
        self::assertInstanceOf(Definition::class, $closure);
        self::assertSame(Closure::class, $closure->getClass());
        self::assertEquals([[new Reference('catalog'), 'offers']], $closure->getArguments());

        $blog = $container->getDefinition('.llms_txt.link_provider.catalog.1');
        self::assertSame([['event' => LlmsTxtPopulateEvent::class, 'method' => '__invoke', 'priority' => 0]], $blog->getTag('kernel.event_listener'));
        self::assertSame('catalog::__invoke', $blog->getArgument(2));
    }

    public function testCallsAStaticMethodWithoutTheService(): void
    {
        $container = new ContainerBuilder();
        $container->register('catalog', CatalogLinks::class)
            ->addTag(LinkProviderPass::TAG, ['section' => 'Blog', 'method' => 'staticLinks']);

        (new LinkProviderPass())->process($container);

        $closure = $container->getDefinition('.llms_txt.link_provider.catalog.0')->getArgument(0);
        self::assertInstanceOf(Definition::class, $closure);
        // the class name, not a Reference: the service is not built
        self::assertIsArray($callable = $closure->getArgument(0));
        self::assertSame(CatalogLinks::class, $callable[0]);
        self::assertSame('staticLinks', $callable[1]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidTags(): iterable
    {
        yield 'no section' => [[], 'needs a non-empty "section"'];
        yield 'empty section' => [['section' => ' '], 'needs a non-empty "section"'];
        yield 'unknown method' => [['section' => 'Blog', 'method' => 'nope'], 'needs a public method "nope"'];
        yield 'not a string priority' => [['section' => 'Blog', 'priority' => '10'], 'must be an integer, "string" given'];
    }

    /**
     * @param array<string, mixed> $tag
     */
    #[DataProvider('invalidTags')]
    public function testRejectsAnInvalidTag(array $tag, string $message): void
    {
        $container = new ContainerBuilder();
        $container->register('catalog', CatalogLinks::class)->addTag(LinkProviderPass::TAG, $tag);

        $this->expectException(InvalidLinkProviderException::class);
        $this->expectExceptionMessage($message);

        (new LinkProviderPass())->process($container);
    }

    public function testRejectsAServiceWithoutClass(): void
    {
        $container = new ContainerBuilder();
        $container->register('catalog', 'App\Missing')->addTag(LinkProviderPass::TAG, ['section' => 'Blog']);

        $this->expectException(InvalidLinkProviderException::class);
        $this->expectExceptionMessage('has no class');

        (new LinkProviderPass())->process($container);
    }
}
