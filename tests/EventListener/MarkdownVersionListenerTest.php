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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Link\LinkProviderInterface;
use Silarhi\LlmsTxtBundle\EventListener\MarkdownVersionListener;
use Silarhi\LlmsTxtBundle\Routing\MarkdownRoutes;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class MarkdownVersionListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAcceptHeaders(): iterable
    {
        yield 'a browser' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'html'];
        yield 'an agent' => ['text/markdown', 'md'];
        yield 'an agent falling back to HTML' => ['text/markdown, text/html;q=0.9', 'md'];
        yield 'HTML first' => ['text/html, text/markdown;q=0.9', 'html'];
        yield 'the legacy type' => ['text/x-markdown', 'md'];
        yield 'anything' => ['*/*', 'html'];
        yield 'plain text' => ['text/plain, */*', 'html'];
    }

    #[DataProvider('provideAcceptHeaders')]
    public function testNegotiatesTheFormat(string $accept, string $format): void
    {
        $request = $this->createRequest('/faq', 'faq', ['HTTP_ACCEPT' => $accept]);

        $this->createListener()->onKernelRequest($this->createRequestEvent($request));

        self::assertSame($format, $request->getRequestFormat());
    }

    public function testRegistersTheMarkdownFormat(): void
    {
        $request = $this->createRequest('/contact', 'contact');

        $this->createListener()->onKernelRequest($this->createRequestEvent($request));

        self::assertSame('text/markdown', $request->getMimeType('md'));
        self::assertSame('md', $request->getFormat('text/x-markdown'));
    }

    public function testKeepsAnExplicitFormat(): void
    {
        $request = $this->createRequest('/guide.json', 'guide', ['HTTP_ACCEPT' => 'text/markdown']);
        $request->attributes->set('_format', 'json');

        $this->createListener()->onKernelRequest($this->createRequestEvent($request));

        self::assertSame('json', $request->getRequestFormat());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideOtherRoutes(): iterable
    {
        yield 'a route without "md" in its requirement' => ['contact'];
        yield 'no route' => [null];
    }

    #[DataProvider('provideOtherRoutes')]
    public function testLeavesTheOtherRoutes(?string $route): void
    {
        $request = $this->createRequest('/contact', $route, ['HTTP_ACCEPT' => 'text/markdown']);
        $listener = $this->createListener();

        $listener->onKernelRequest($this->createRequestEvent($request));
        $response = new Response();
        $listener->onKernelResponse($this->createResponseEvent($request, $response));

        self::assertSame('html', $request->getRequestFormat());
        self::assertFalse($response->headers->has('Vary'));
        self::assertFalse($request->attributes->has('_links'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideAlternateLinks(): iterable
    {
        yield 'a Markdown URL' => ['/guide', 'guide', 'https://example.com/guide.md'];
        yield 'an Accept-only route' => ['/faq', 'faq', 'https://example.com/faq'];
    }

    #[DataProvider('provideAlternateLinks')]
    public function testLinksTheHtmlPageToItsMarkdownVersion(string $path, string $route, string $href): void
    {
        $request = $this->createRequest($path, $route);
        $listener = $this->createListener();

        $listener->onKernelRequest($this->createRequestEvent($request));
        $response = new Response();
        $listener->onKernelResponse($this->createResponseEvent($request, $response));

        self::assertSame(['Accept'], $response->getVary());
        $links = $request->attributes->get('_links');
        self::assertInstanceOf(LinkProviderInterface::class, $links);
        self::assertSame([$href], array_map(static fn (\Psr\Link\LinkInterface $link): string => $link->getHref(), [...$links->getLinksByRel('alternate')]));
    }

    public function testDoesNotLinkTheMarkdownVersionToItself(): void
    {
        $request = $this->createRequest('/guide.md', 'guide');
        $request->attributes->set('_format', 'md');
        $listener = $this->createListener();

        $listener->onKernelRequest($this->createRequestEvent($request));
        $response = new Response();
        $listener->onKernelResponse($this->createResponseEvent($request, $response));

        self::assertSame(['Accept'], $response->getVary());
        self::assertFalse($request->attributes->has('_links'));
    }

    public function testDoesNotLinkTheFragments(): void
    {
        $request = $this->createRequest('/faq', 'faq');
        $listener = $this->createListener();

        $listener->onKernelRequest($this->createRequestEvent($request));
        $response = new Response();
        $listener->onKernelResponse($this->createResponseEvent($request, $response, HttpKernelInterface::SUB_REQUEST));

        self::assertSame(['Accept'], $response->getVary());
        self::assertFalse($request->attributes->has('_links'));
    }

    private function createListener(): MarkdownVersionListener
    {
        $router = new Router(new ClosureLoader(), static function (): RouteCollection {
            $routes = new RouteCollection();
            $routes->add('guide', new Route('/guide.{_format}', ['_format' => 'html'], ['_format' => 'html|md']));
            $routes->add('faq', new Route('/faq', ['_format' => 'html'], ['_format' => 'html|md']));
            $routes->add('contact', new Route('/contact'));

            return $routes;
        }, [], new RequestContext(host: 'example.com', scheme: 'https'));

        $cacheDir = sys_get_temp_dir() . '/llms-txt-bundle/markdown-listener-' . bin2hex(random_bytes(4));

        return new MarkdownVersionListener($router, new MarkdownRoutes($router, new ConfigCacheFactory(false), $cacheDir));
    }

    /**
     * @param array<string, string> $server
     */
    private function createRequest(string $path, ?string $route, array $server = []): Request
    {
        $request = Request::create('https://example.com' . $path, server: $server);
        if (null !== $route) {
            $request->attributes->add(['_route' => $route, '_route_params' => []]);
        }

        return $request;
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createResponseEvent(Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, $response);
    }
}
