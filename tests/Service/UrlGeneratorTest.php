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

namespace Silarhi\LlmsTxtBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGenerator as RoutingUrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class UrlGeneratorTest extends TestCase
{
    public function testUsesTheRouteOfTheBundle(): void
    {
        $routes = new RouteCollection();
        $routes->add('llms_txt', new Route('/llms.txt'));

        self::assertSame('https://example.com/app/llms.txt', $this->createUrlGenerator($routes)->generate());
    }

    public function testFallsBackToTheRootOfTheSite(): void
    {
        self::assertSame('https://example.com/llms.txt', $this->createUrlGenerator(new RouteCollection())->generate());
    }

    private function createUrlGenerator(RouteCollection $routes): UrlGenerator
    {
        $context = new RequestContext(baseUrl: '/app', host: 'example.com', scheme: 'https');

        return new UrlGenerator(new RoutingUrlGenerator($routes, $context), new UrlHelper(new RequestStack(), $context));
    }
}
