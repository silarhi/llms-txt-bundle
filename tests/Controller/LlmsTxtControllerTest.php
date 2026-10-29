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

namespace Silarhi\LlmsTxtBundle\Tests\Controller;

use Generator as PhpGenerator;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Silarhi\LlmsTxtBundle\Controller\LlmsTxtController;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Routing\DefaultUriContext;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;

use function strlen;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LlmsTxtControllerTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/llms-txt-bundle/controller-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testBuildsTheFileWhenItIsNotDumped(): void
    {
        $response = $this->createController()(new Request());

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame("# Generated\n", $this->send($response));
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('12', $response->headers->get('Content-Length'));
        self::assertSame('"' . hash('xxh128', "# Generated\n") . '"', $response->getEtag());
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testAnswersNotModifiedWithoutABody(): void
    {
        $etag = $this->createController()(new Request())->getEtag();
        self::assertIsString($etag);

        $response = $this->createController()(new Request(server: ['HTTP_IF_NONE_MATCH' => $etag]));

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        self::assertSame('', $this->send($response));
    }

    public function testServesTheDumpedFile(): void
    {
        (new Filesystem())->dumpFile($this->directory . '/llms.txt', "# Dumped\n");

        $response = $this->createController()(new Request());

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame($this->directory . '/llms.txt', $response->getFile()->getPathname());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->has('ETag'));
        self::assertTrue($response->headers->has('Last-Modified'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));

        $notModified = $this->createController()(new Request(server: ['HTTP_IF_NONE_MATCH' => (string) $response->getEtag()]));
        self::assertSame(Response::HTTP_NOT_MODIFIED, $notModified->getStatusCode());
    }

    public function testFailsBeforeRespondingToAnInvalidDocument(): void
    {
        $controller = new LlmsTxtController(new Generator(new EventDispatcher()), new MarkdownRenderer(), $this->directory, 'text/plain', 600);

        $this->expectException(MissingTitleException::class);

        $controller(new Request());
    }

    public function testFailsBeforeRespondingWhenALinkFailsHalfWay(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLinks('Offers', (static function (): PhpGenerator {
                yield new Link('https://example.com/offers/1', 'Offer 1');

                throw new RuntimeException('The second offer has no URL');
            })());
        });
        $controller = new LlmsTxtController(new Generator($dispatcher, 'Generated'), new MarkdownRenderer(), $this->directory, 'text/plain', 600);

        // no response at all, so no truncated 200: the kernel turns the exception into an error page
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The second offer has no URL');

        $controller(new Request());
    }

    public function testServesTheFileFromTheCache(): void
    {
        $calls = 0;
        $cache = new ArrayAdapter();
        $request = Request::create('https://example.com/llms.txt');

        $first = $this->createCachedController($calls, $cache, $request)($request);
        $second = $this->createCachedController($calls, $cache, $request)(Request::create('https://example.com/llms.txt?x=random'));

        self::assertSame(1, $calls);
        self::assertSame("# Cached\n\n## Pages\n\n- [Contact](https://example.com/contact)\n", $this->send($second));
        self::assertSame($first->getEtag(), $second->getEtag());
        self::assertSame((string) strlen($this->send($second)), $second->headers->get('Content-Length'));
        self::assertSame('nosniff', $second->headers->get('X-Content-Type-Options'));
        self::assertSame('600', $second->headers->getCacheControlDirective('max-age'));
    }

    public function testAnswersNotModifiedFromTheCache(): void
    {
        $calls = 0;
        $cache = new ArrayAdapter();
        $request = Request::create('https://example.com/llms.txt');
        $etag = $this->createCachedController($calls, $cache, $request)($request)->getEtag();
        self::assertIsString($etag);

        $response = $this->createCachedController($calls, $cache, $request)(Request::create('https://example.com/llms.txt', server: ['HTTP_IF_NONE_MATCH' => $etag]));

        self::assertSame(1, $calls);
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        self::assertSame('', $this->send($response));
    }

    public function testCachesTheFileOfEachHostWithoutDefaultUri(): void
    {
        $calls = 0;
        $cache = new ArrayAdapter();
        $first = Request::create('https://example.com/llms.txt');
        $second = Request::create('https://www.example.org/llms.txt');

        $this->createCachedController($calls, $cache, $first)($first);
        $response = $this->createCachedController($calls, $cache, $second)($second);

        self::assertSame(2, $calls);
        self::assertStringContainsString('(https://www.example.org/contact)', $this->send($response));
    }

    public function testUsesTheDefaultUriWhateverTheHostOfTheRequest(): void
    {
        $calls = 0;
        $cache = new ArrayAdapter();
        $forged = Request::create('https://forged.example.net/llms.txt');
        $other = Request::create('https://other.example.net/llms.txt');

        $response = $this->createCachedController($calls, $cache, $forged, 'https://example.com')($forged);
        $this->createCachedController($calls, $cache, $other, 'https://example.com')($other);

        // the URLs of the listeners come from "default_uri" too, and all the hosts share the entry of the cache
        self::assertStringContainsString('(https://example.com/contact)', $this->send($response));
        self::assertSame(1, $calls);
    }

    public function testDoesNotCacheWithoutTtl(): void
    {
        $calls = 0;
        $cache = new ArrayAdapter();
        $request = Request::create('https://example.com/llms.txt');

        $this->createCachedController($calls, $cache, $request, cacheTtl: 0)($request);
        $response = $this->createCachedController($calls, $cache, $request, cacheTtl: 0)($request);

        self::assertSame(2, $calls);
        self::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testStreamsALargeFileWithoutCachingIt(): void
    {
        $calls = 0;
        $cache = new ArrayAdapter();
        $request = Request::create('https://example.com/llms.txt');

        $response = $this->createCachedController($calls, $cache, $request, links: 20_000)($request);
        $this->createCachedController($calls, $cache, $request, links: 20_000)($request);

        self::assertSame(2, $calls);
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertGreaterThan(1024 * 1024, strlen($this->send($response)));
        // ArrayAdapter keeps its misses as nulls
        self::assertSame([], array_filter($cache->getValues(), static fn (mixed $value): bool => null !== $value));
    }

    private function send(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function createController(): LlmsTxtController
    {
        return new LlmsTxtController(
            new Generator(new EventDispatcher(), 'Generated'),
            new MarkdownRenderer(),
            $this->directory,
            'text/plain; charset=UTF-8',
            600,
        );
    }

    /**
     * A controller whose listener generates its URL from the router, as RouterListener leaves it during the request.
     */
    private function createCachedController(int &$calls, ArrayAdapter $cache, Request $request, string $defaultUri = '', int $cacheTtl = 300, int $links = 0): LlmsTxtController
    {
        $routes = new RouteCollection();
        $routes->add('contact', new Route('/contact'));
        $router = new UrlGenerator($routes, (new RequestContext())->fromRequest($request));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event) use (&$calls, $router, $links): void {
            ++$calls;
            $event->getDocument()->addLink('Pages', $router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL), 'Contact');
            for ($i = 0; $i < $links; ++$i) {
                $event->getDocument()->addLink('Offers', 'https://example.com/offers/' . $i, 'Offer ' . $i, 'The description of the offer ' . $i);
            }
        });

        return new LlmsTxtController(
            new Generator($dispatcher, 'Cached'),
            new MarkdownRenderer(),
            $this->directory,
            'text/plain; charset=UTF-8',
            600,
            new DefaultUriContext($router, $requestStack, $defaultUri),
            $cache,
            $cacheTtl,
        );
    }
}
