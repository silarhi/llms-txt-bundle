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

namespace Silarhi\LlmsTxtBundle\DependencyInjection;

use Closure;

use function is_int;
use function is_string;

use Override;
use ReflectionClass;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\LinkProviderListener;
use Silarhi\LlmsTxtBundle\Exception\InvalidLinkProviderException;

use function sprintf;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Turns each "llms_txt.link_provider" tag ({section, method: "__invoke", priority: 0}) into a LlmsTxtPopulateEvent
 * listener with its priority: providers and hand-written listeners share one order.
 */
final class LinkProviderPass implements CompilerPassInterface
{
    public const TAG = 'llms_txt.link_provider';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(self::TAG, true) as $id => $tags) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            if (!is_string($class) || !($reflection = $container->getReflectionClass($class, false)) instanceof ReflectionClass) {
                throw new InvalidLinkProviderException(sprintf('The llms.txt link provider "%s" has no class.', $id));
            }

            /** @var array<string, mixed> $tag */
            foreach ($tags as $index => $tag) {
                $section = $tag['section'] ?? null;
                $method = $tag['method'] ?? '__invoke';
                $priority = $tag['priority'] ?? 0;
                $name = $id . '::' . (is_string($method) ? $method : '?');

                if (!is_string($section) || '' === trim($section)) {
                    throw new InvalidLinkProviderException(sprintf('The llms.txt link provider "%s" needs a non-empty "section".', $name));
                }
                if (!is_string($method) || !$reflection->hasMethod($method) || !$reflection->getMethod($method)->isPublic()) {
                    throw new InvalidLinkProviderException(sprintf('The llms.txt link provider "%s" needs a public method "%s" on "%s".', $id, is_string($method) ? $method : get_debug_type($method), $class));
                }
                if (!is_int($priority)) {
                    throw new InvalidLinkProviderException(sprintf('The "priority" of the llms.txt link provider "%s" must be an integer, "%s" given.', $name, get_debug_type($priority)));
                }

                // a static method needs no instance: the service is not even built
                $callable = $reflection->getMethod($method)->isStatic() ? [$class, $method] : [new Reference($id), $method];

                $container->register(sprintf('.llms_txt.link_provider.%s.%d', $id, $index), LinkProviderListener::class)
                    ->setArguments([
                        (new Definition(Closure::class))->setFactory([Closure::class, 'fromCallable'])->setArguments([$callable]),
                        $section,
                        $name,
                    ])
                    ->addTag('kernel.event_listener', ['event' => LlmsTxtPopulateEvent::class, 'method' => '__invoke', 'priority' => $priority]);
            }
        }
    }
}
