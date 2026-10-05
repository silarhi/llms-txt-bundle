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
use Silarhi\LlmsTxtBundle\EventListener\DiscoveryLinkListener;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGenerator as RoutingUrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

/**
 * Run with the listener of WebLink, which writes the header.
 */
final class DiscoveryLinkListenerTest extends TestCase
{
    private const HEADER = '<https://example.com/llms.txt>; rel="llms-txt"';

    public function testAdvertisesTheFileOnAnHtmlPage(): void
    {
        $response = $this->dispatch(Request::create('/'), new Response('<html></html>', headers: ['Content-Type' => 'text/html; charset=UTF-8']));

        self::assertSame([self::HEADER], $response->headers->all('link'));
    }

    public function testAdvertisesTheFileOnAnHtmlPageWithoutContentTypeYet(): void
    {
        $response = $this->dispatch(Request::create('/'), new Response('<html></html>'));

        self::assertSame([self::HEADER], $response->headers->all('link'));
    }

    public function testJoinsTheLinksOfTheRequest(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_links', (new GenericLinkProvider())->withLink((new Link(Link::REL_PRELOAD, '/app.css'))->withAttribute('as', 'style')));

        $response = $this->dispatch($request, new Response('<html></html>', headers: ['Content-Type' => 'text/html']));

        self::assertSame(['</app.css>; rel="preload"; as="style",' . self::HEADER], $response->headers->all('link'));
    }

    /**
     * @return iterable<string, array{Request, Response}>
     */
    public static function provideIgnoredResponses(): iterable
    {
        $html = ['Content-Type' => 'text/html; charset=UTF-8'];
        $json = Request::create('/');
        $json->setRequestFormat('json');

        yield 'JSON' => [Request::create('/'), new JsonResponse([])];
        yield 'plain text, the llms.txt file itself' => [Request::create('/llms.txt'), new Response('# Example', headers: ['Content-Type' => 'text/plain; charset=UTF-8'])];
        yield 'JSON format without Content-Type yet' => [$json, new Response('{}')];
        yield 'Turbo Stream' => [Request::create('/'), new Response('<turbo-stream></turbo-stream>', headers: ['Content-Type' => 'text/vnd.turbo-stream.html'])];
        yield 'redirect' => [Request::create('/'), new RedirectResponse('/elsewhere')];
        yield 'error' => [Request::create('/'), new Response('<html></html>', 404, $html)];
        yield 'Turbo Frame' => [Request::create('/', server: ['HTTP_TURBO_FRAME' => 'offers']), new Response('<turbo-frame id="offers"></turbo-frame>', headers: $html)];
        yield 'XHR' => [Request::create('/', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']), new Response('<p></p>', headers: $html)];
    }

    #[DataProvider('provideIgnoredResponses')]
    public function testIgnores(Request $request, Response $response): void
    {
        self::assertFalse($this->dispatch($request, $response)->headers->has('Link'));
    }

    public function testIgnoresSubRequests(): void
    {
        $response = $this->dispatch(Request::create('/'), new Response('<p></p>', headers: ['Content-Type' => 'text/html']), HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('Link'));
    }

    private function dispatch(Request $request, Response $response, int $requestType = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $routes = new RouteCollection();
        $routes->add('llms_txt', new Route('/llms.txt'));
        $context = new RequestContext(host: 'example.com', scheme: 'https');
        $urlGenerator = new UrlGenerator(new RoutingUrlGenerator($routes, $context), new UrlHelper(new RequestStack(), $context));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new AddLinkHeaderListener());
        $dispatcher->addSubscriber(new DiscoveryLinkListener($urlGenerator, 'llms-txt'));

        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $requestType, $response);
        $dispatcher->dispatch($event, KernelEvents::RESPONSE);

        return $event->getResponse();
    }
}
