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

namespace Silarhi\LlmsTxtBundle\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Silarhi\LlmsTxtBundle\Routing\DefaultUriContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class DefaultUriContextTest extends TestCase
{
    public function testUsesTheDefaultUriWhateverTheHostOfTheRequest(): void
    {
        [$router, $requestStack] = $this->createRouter(Request::create('http://forged.example.net/contact'));
        $context = new DefaultUriContext($router, $requestStack, 'https://example.com:8443/app');

        self::assertSame('https://example.com:8443/app/contact', $context->apply(static fn (): string => $router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL)));
        // the rest of the request keeps the context of the request
        self::assertSame('http://forged.example.net/contact', $router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testRestoresTheContextWhenTheCallbackFails(): void
    {
        [$router, $requestStack] = $this->createRouter(Request::create('http://forged.example.net/contact'));
        $context = new DefaultUriContext($router, $requestStack, 'https://example.com');

        try {
            $context->apply(static function (): string {
                throw new RuntimeException('Failed');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Failed', $exception->getMessage());
        }

        self::assertSame('forged.example.net', $router->getContext()->getHost());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsetDefaultUris(): iterable
    {
        yield 'not set' => [''];
        yield 'the placeholder of the Symfony recipe' => ['http://localhost'];
        yield 'no host' => ['/app'];
    }

    #[DataProvider('provideUnsetDefaultUris')]
    public function testKeepsTheHostOfTheRequestWithoutDefaultUri(string $defaultUri): void
    {
        [$router, $requestStack] = $this->createRouter(Request::create('https://www.example.org/contact'));
        $context = new DefaultUriContext($router, $requestStack, $defaultUri);

        self::assertSame('https://www.example.org/contact', $context->apply(static fn (): string => $router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL)));
        self::assertNull($context->getBaseUrl());
    }

    public function testKeepsTheContextOfTheRouterWithoutRequest(): void
    {
        // llms-txt:dump --base-url sets the context of the router: "default_uri" must not override it
        [$router] = $this->createRouter(Request::create('https://www.example.org/'));
        $context = new DefaultUriContext($router, new RequestStack(), 'https://example.com');

        self::assertSame('https://www.example.org/contact', $context->apply(static fn (): string => $router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL)));
    }

    public function testGivesTheBaseUrlOfTheDefaultUri(): void
    {
        self::assertSame('https://example.com', (new DefaultUriContext($this->createRouter(new Request())[0], new RequestStack(), 'https://example.com/'))->getBaseUrl());
        self::assertSame('http://example.com:8080/app', (new DefaultUriContext($this->createRouter(new Request())[0], new RequestStack(), 'http://example.com:8080/app'))->getBaseUrl());
    }

    /**
     * The router as RouterListener leaves it during a request: its context filled from the request.
     *
     * @return array{UrlGenerator, RequestStack}
     */
    private function createRouter(Request $request): array
    {
        $routes = new RouteCollection();
        $routes->add('contact', new Route('/contact'));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return [new UrlGenerator($routes, (new RequestContext())->fromRequest($request)), $requestStack];
    }
}
