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
use Silarhi\LlmsTxtBundle\Routing\DefaultUriContext;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;
use Symfony\Component\HttpFoundation\Request;
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

    public function testUsesTheDefaultUriWhateverTheHostOfTheRequest(): void
    {
        $routes = new RouteCollection();
        $routes->add('llms_txt', new Route('/llms.txt'));

        self::assertSame('https://example.com/llms.txt', $this->createDefaultUriUrlGenerator($routes, 'https://example.com')->generate());
        self::assertSame('https://example.com/llms.txt', $this->createDefaultUriUrlGenerator(new RouteCollection(), 'https://example.com')->generate());
    }

    public function testUsesTheHostOfTheRequestWithoutDefaultUri(): void
    {
        $routes = new RouteCollection();
        $routes->add('llms_txt', new Route('/llms.txt'));

        self::assertSame('http://forged.example.net/llms.txt', $this->createDefaultUriUrlGenerator($routes, '')->generate());
        self::assertSame('http://forged.example.net/llms.txt', $this->createDefaultUriUrlGenerator(new RouteCollection(), '')->generate());
    }

    private function createDefaultUriUrlGenerator(RouteCollection $routes, string $defaultUri): UrlGenerator
    {
        $request = Request::create('http://forged.example.net/contact');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $router = new RoutingUrlGenerator($routes, (new RequestContext())->fromRequest($request));

        return new UrlGenerator($router, new UrlHelper($requestStack), new DefaultUriContext($router, $requestStack, $defaultUri));
    }

    private function createUrlGenerator(RouteCollection $routes): UrlGenerator
    {
        $context = new RequestContext(baseUrl: '/app', host: 'example.com', scheme: 'https');

        return new UrlGenerator(new RoutingUrlGenerator($routes, $context), new UrlHelper(new RequestStack(), $context));
    }
}
