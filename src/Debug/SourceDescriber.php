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

namespace Silarhi\LlmsTxtBundle\Debug;

use Closure;

use function is_array;
use function is_callable;
use function is_object;
use function is_string;

use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\LinkProviderListener;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The listeners of LlmsTxtPopulateEvent, in the order they run: link providers, route options, other listeners.
 *
 * @internal
 */
final readonly class SourceDescriber
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return list<Source>
     */
    public function describe(): array
    {
        $sources = [];
        foreach ($this->dispatcher->getListeners(LlmsTxtPopulateEvent::class) as $listener) {
            // with an event name, a list of listeners, not of lists
            if (!is_callable($listener)) { // @phpstan-ignore function.alreadyNarrowedType (Symfony 8.1 types them as callables, the dispatcher takes any listener)
                continue;
            }

            $priority = $this->dispatcher->getListenerPriority(LlmsTxtPopulateEvent::class, $listener) ?? 0;
            // the TraceableEventDispatcher of the debug mode wraps each listener
            if ($listener instanceof WrappedListener) {
                $listener = $listener->getWrappedListener();
            }

            $target = is_array($listener) ? $listener[0] : $listener;
            $method = is_array($listener) && is_string($listener[1] ?? null) ? $listener[1] : '__invoke';

            $sources[] = match (true) {
                $target instanceof LinkProviderListener => new Source($priority, Source::PROVIDER, $target->name, $target->section),
                $target instanceof RouteOptionsListener => new Source($priority, Source::ROUTE_OPTIONS, 'the "llms_txt" option of the routes', null),
                $target instanceof Closure => new Source($priority, Source::LISTENER, 'Closure', null),
                is_object($target) => new Source($priority, Source::LISTENER, $target::class . '::' . $method, null),
                // a function or "Class::method" string names itself
                is_string($listener) => new Source($priority, Source::LISTENER, $listener, null),
                default => new Source($priority, Source::LISTENER, (is_string($target) ? $target : get_debug_type($target)) . '::' . $method, null),
            };
        }

        return $sources;
    }
}
