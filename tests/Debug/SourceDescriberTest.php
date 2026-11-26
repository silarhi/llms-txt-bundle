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

namespace Silarhi\LlmsTxtBundle\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Debug\Source;
use Silarhi\LlmsTxtBundle\Debug\SourceDescriber;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\LinkProviderListener;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Silarhi\LlmsTxtBundle\Tests\Fixtures\PopulateSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class SourceDescriberTest extends TestCase
{
    public function testDescribesTheListenersInTheOrderTheyRun(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouteOptionsListener(new Router(new ClosureLoader(), static fn (): RouteCollection => new RouteCollection()), 'Pages'));
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, new LinkProviderListener(static fn (): array => [], 'Blog', 'app.blog::posts'), -10);
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (): void {
        }, -5);
        $dispatcher->addSubscriber(new PopulateSubscriber(new UrlGenerator(new RouteCollection(), new RequestContext())));

        self::assertEquals([
            new Source(128, Source::ROUTE_OPTIONS, 'the "llms_txt" option of the routes', null),
            new Source(0, Source::LISTENER, PopulateSubscriber::class . '::populate', null),
            new Source(-5, Source::LISTENER, 'Closure', null),
            new Source(-10, Source::PROVIDER, 'app.blog::posts', 'Blog'),
        ], (new SourceDescriber($dispatcher))->describe());
    }
}
