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

use Override;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Controller\LlmsTxtController;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $response = $this->createController()();

        self::assertInstanceOf(StreamedResponse::class, $response);
        ob_start();
        $response->sendContent();
        self::assertSame("# Generated\n", ob_get_clean());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testServesTheDumpedFile(): void
    {
        (new Filesystem())->dumpFile($this->directory . '/llms.txt', "# Dumped\n");

        $response = $this->createController()();

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame($this->directory . '/llms.txt', $response->getFile()->getPathname());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->has('ETag'));
        self::assertTrue($response->headers->has('Last-Modified'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testFailsBeforeStreamingAnInvalidDocument(): void
    {
        $controller = new LlmsTxtController(new Generator(new EventDispatcher()), new MarkdownRenderer(), $this->directory, 'text/plain', 600);

        $this->expectException(MissingTitleException::class);

        $controller();
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
