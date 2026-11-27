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

namespace Silarhi\LlmsTxtBundle\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Controller\LlmsTxtController;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Silarhi\LlmsTxtBundle\LlmsTxt;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;
use Silarhi\LlmsTxtBundle\Service\Dumper;
use Silarhi\LlmsTxtBundle\Service\FullMarkdownRenderer;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

/**
 * The "Without the bundle" example of the README: neither a kernel nor symfony/dependency-injection, which the
 * "PHPUnit without the bundle dependencies" CI job removes.
 */
final class StandaloneTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/llms-txt-bundle/standalone-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testBuildsDumpsAndServesTheFilesByHand(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouteOptionsListener($this->createRouter(), 'Pages'));
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLinks('Products', (static function () {
                yield new Link('https://example.com/products/1', 'Product 1', content: '# Product 1');
            })());
        });

        $generator = new Generator($dispatcher, title: 'SILARHI', summary: 'Web and mobile applications.');

        $path = (new Dumper($generator, new MarkdownRenderer(), new Filesystem(), $this->directory, LlmsTxt::FILENAME))->dump();
        self::assertSame($this->directory . '/llms.txt', $path);
        $llmsTxt = (string) file_get_contents($path);
        self::assertStringStartsWith("# SILARHI\n\n> Web and mobile applications.\n", $llmsTxt);
        self::assertStringContainsString('- [Contact](https://example.com/contact)', $llmsTxt);
        self::assertStringContainsString('- [Product 1](https://example.com/products/1)', $llmsTxt);

        $fullPath = (new Dumper($generator, new FullMarkdownRenderer(), new Filesystem(), $this->directory, LlmsTxt::FULL_FILENAME))->dump();
        self::assertStringContainsString('# Product 1', (string) file_get_contents($fullPath));

        // nothing dumped there: the controller builds the file on the fly
        $controller = new LlmsTxtController($generator, new MarkdownRenderer(), $this->directory . '/none', 'text/plain; charset=UTF-8', 3600, LlmsTxt::FILENAME, true);
        $response = $controller(Request::create('https://example.com/llms.txt'));

        self::assertSame(200, $response->getStatusCode());
        ob_start();
        $response->sendContent();
        self::assertSame($llmsTxt, ob_get_clean());
    }

    private function createRouter(): Router
    {
        $routes = new RouteCollection();
        $routes->add('contact', new Route('/contact', options: [RouteOptionsListener::OPTION => new LlmsTxtEntry(title: 'Contact')]));

        return new Router(new ClosureLoader(), static fn (): RouteCollection => $routes, [], new RequestContext(host: 'example.com', scheme: 'https'));
    }
}
