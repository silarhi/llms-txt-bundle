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
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
}
