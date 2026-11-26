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

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Silarhi\LlmsTxtBundle\Exception\InvalidRouteOptionException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class RouteOptionsListenerTest extends TestCase
{
    public function testAddsTheRoutesCarryingTheOption(): void
    {
        $document = $this->populate([
            'home' => new Route('/'),
            'contact' => new Route('/contact', options: ['llms_txt' => new LlmsTxtEntry(title: 'Contact', description: 'Reach us')]),
            'about' => new Route('/about', options: ['llms_txt' => ['title' => 'About']]),
            'legal' => new Route('/legal', options: ['llms_txt' => new LlmsTxtEntry(title: 'Legal', section: Section::OPTIONAL)]),
            'hidden' => new Route('/hidden', options: ['llms_txt' => false]),
        ]);

        self::assertCount(2, $document->getSections());
        self::assertEquals([new Link('https://example.com/contact', 'Contact', 'Reach us'), new Link('https://example.com/about', 'About')], iterator_to_array($document->section('Pages')->getLinks(), false));
        self::assertEquals([new Link('https://example.com/legal', 'Legal')], iterator_to_array($document->section('Optional')->getLinks(), false));
    }

    public function testPointsToTheMarkdownVersion(): void
    {
        $document = $this->populate([
            'guide' => new Route('/guide.{_format}', ['_format' => 'html'], options: ['llms_txt' => new LlmsTxtEntry(title: 'Guide', markdown: true)]),
        ]);

        self::assertEquals([new Link('https://example.com/guide.md', 'Guide')], iterator_to_array($document->section('Pages')->getLinks(), false));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, bool|null, bool, string}>
     */
    public static function provideMarkdownDetections(): iterable
    {
        $formats = ['_format' => 'html|md'];
        yield 'a Markdown URL' => ['/guide.{_format}', $formats, null, true, 'https://example.com/guide.md'];
        yield 'a non-capturing group' => ['/guide.{_format}', ['_format' => '(?:md|html)'], null, true, 'https://example.com/guide.md'];
        yield 'markdown_versions disabled' => ['/guide.{_format}', $formats, null, false, 'https://example.com/guide'];
        yield 'an Accept-only route' => ['/guide', $formats, null, true, 'https://example.com/guide'];
        yield 'no "md" in the requirement' => ['/guide.{_format}', ['_format' => 'html|json'], null, true, 'https://example.com/guide'];
        yield 'a pattern merely matching "md"' => ['/guide.{_format}', ['_format' => '[a-z]+'], null, true, 'https://example.com/guide'];
        yield 'markdown: false' => ['/guide.{_format}', $formats, false, true, 'https://example.com/guide'];
        yield 'markdown: true without the requirement' => ['/guide.{_format}', [], true, false, 'https://example.com/guide.md'];
    }

    /**
     * @param array<string, string> $requirements
     */
    #[DataProvider('provideMarkdownDetections')]
    public function testDetectsTheMarkdownVersion(string $path, array $requirements, ?bool $markdown, bool $markdownVersions, string $url): void
    {
        $document = $this->populate([
            'guide' => new Route($path, ['_format' => 'html'], $requirements, ['llms_txt' => new LlmsTxtEntry(title: 'Guide', markdown: $markdown)]),
        ], markdownVersions: $markdownVersions);

        self::assertEquals([new Link($url, 'Guide')], iterator_to_array($document->section('Pages')->getLinks(), false));
    }

    public function testNeedsAFormatInThePathForTheMarkdownVersion(): void
    {
        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage('The "llms_txt" option of the route "guide" points to the Markdown version of the page, but its path has no {_format}: add ".{_format}" to "/guide".');

        $this->populate(['guide' => new Route('/guide', options: ['llms_txt' => ['title' => 'Guide', 'markdown' => true]])]);
    }

    public function testReadsTheCachesWithoutTheMarkdownFlag(): void
    {
        $cacheDir = $this->getCacheDir();
        // the cache 1.4 wrote, still fresh after an upgrade without cache:clear
        (new Filesystem())->dumpFile($cacheDir . '/llms_txt_routes.php', "<?php return [['contact', 'Contact', null, null]];\n");

        $document = $this->populate(['contact' => new Route('/contact')], $cacheDir);

        self::assertEquals([new Link('https://example.com/contact', 'Contact')], iterator_to_array($document->section('Pages')->getLinks(), false));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideInvalidOptions(): iterable
    {
        yield 'neither an entry nor an array' => [true, 'must be a Silarhi\\LlmsTxtBundle\\Routing\\LlmsTxtEntry or an array, "bool" given'];
        yield 'no title' => [['description' => 'Reach us'], 'needs a non-empty "title"'];
        yield 'empty title' => [['title' => ' '], 'needs a non-empty "title"'];
        yield 'unknown key' => [['title' => 'Contact', 'priority' => 1], 'unknown keys "priority"'];
        yield 'invalid description' => [['title' => 'Contact', 'description' => 1], '"description"'];
        yield 'invalid section' => [['title' => 'Contact', 'section' => ''], '"section"'];
    }

    #[DataProvider('provideInvalidOptions')]
    public function testRejectsInvalidOptions(mixed $options, string $message): void
    {
        try {
            $this->populate(['contact' => new Route('/contact', options: ['llms_txt' => $options])]);
            self::fail('The option should have been rejected');
        } catch (InvalidRouteOptionException $exception) {
            self::assertStringStartsWith('The "llms_txt" option of the route "contact"', $exception->getMessage());
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    public function testRejectsRoutesNeedingParameters(): void
    {
        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage('needs parameters');

        $this->populate(['offer' => new Route('/offers/{slug}', options: ['llms_txt' => new LlmsTxtEntry('Offer')])]);
    }

    public function testCachesTheRouteOptions(): void
    {
        $routes = [
            'home' => new Route('/'),
            'contact' => new Route('/contact', options: ['llms_txt' => new LlmsTxtEntry(title: 'Contact', description: 'Reach us', section: Section::OPTIONAL)]),
        ];

        // Each request gets a new router, as in production: only the cache spares it loading the routes
        $first = $this->populate($routes, $this->getCacheDir());
        $second = $this->populate($routes, $this->getCacheDir());

        self::assertSame(1, $this->routeLoads);
        self::assertFileExists($this->getCacheDir() . '/llms_txt_routes.php');
        self::assertEquals($first, $second);
        self::assertEquals([new Link('https://example.com/contact', 'Contact', 'Reach us')], iterator_to_array($second->section('Optional')->getLinks(), false));
    }

    public function testWarmUpWritesTheCacheInTheBuildDirectory(): void
    {
        $routes = ['contact' => new Route('/contact', options: ['llms_txt' => ['title' => 'Contact']])];

        $router = $this->createRouter($routes, $this->getCacheDir() . '/router');
        $listener = new RouteOptionsListener($router, 'Pages', new ConfigCacheFactory(false), $this->getCacheDir());
        self::assertTrue($listener->isOptional());
        self::assertSame([], $listener->warmUp($this->getCacheDir() . '/cache', $this->getCacheDir()));
        // cache:warmup also dumps the router's compiled routes
        $router->getGenerator();
        self::assertFileExists($this->getCacheDir() . '/llms_txt_routes.php');

        $document = $this->populate($routes, $this->getCacheDir());

        self::assertSame(1, $this->routeLoads);
        self::assertEquals([new Link('https://example.com/contact', 'Contact')], iterator_to_array($document->section('Pages')->getLinks(), false));
    }

    public function testWarmUpLeavesInvalidOptionsToTheRequests(): void
    {
        $routes = ['contact' => new Route('/contact', options: ['llms_txt' => ['description' => 'Reach us']])];

        $listener = new RouteOptionsListener($this->createRouter($routes), 'Pages', new ConfigCacheFactory(false), $this->getCacheDir());
        self::assertSame([], $listener->warmUp($this->getCacheDir()));
        self::assertFileDoesNotExist($this->getCacheDir() . '/llms_txt_routes.php');

        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage('needs a non-empty "title"');

        $this->populate($routes, $this->getCacheDir());
    }

    public function testFallsBackToTheRoutesWhenTheCacheCannotBeWritten(): void
    {
        $file = $this->getCacheDir() . '/not-a-directory';
        (new Filesystem())->dumpFile($file, '');

        $document = $this->populate(['contact' => new Route('/contact', options: ['llms_txt' => ['title' => 'Contact']])], $file);

        self::assertEquals([new Link('https://example.com/contact', 'Contact')], iterator_to_array($document->section('Pages')->getLinks(), false));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->cacheDir) {
            (new Filesystem())->remove($this->cacheDir);
        }
    }

    private int $routeLoads = 0;

    private ?string $cacheDir = null;

    private function getCacheDir(): string
    {
        return $this->cacheDir ??= sys_get_temp_dir() . '/llms-txt-bundle/route-options-' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<string, Route> $routes
     * @param string|null          $cacheDir the router's own cache, which spares its URL generator the route loading in production
     */
    private function createRouter(array $routes, ?string $cacheDir = null): Router
    {
        $loader = function () use ($routes): RouteCollection {
            ++$this->routeLoads;
            $collection = new RouteCollection();
            foreach ($routes as $name => $route) {
                $collection->add($name, $route);
            }

            return $collection;
        };

        return new Router(new ClosureLoader(), $loader, ['cache_dir' => $cacheDir], new RequestContext(host: 'example.com', scheme: 'https'));
    }

    /**
     * @param array<string, Route> $routes
     */
    private function populate(array $routes, ?string $cacheDir = null, bool $markdownVersions = true): Document
    {
        $router = $this->createRouter($routes, null === $cacheDir ? null : $this->getCacheDir() . '/router');
        $listener = null === $cacheDir
            ? new RouteOptionsListener($router, 'Pages', markdownVersions: $markdownVersions)
            : new RouteOptionsListener($router, 'Pages', new ConfigCacheFactory(false), $cacheDir, $markdownVersions);

        $document = new Document('Example');
        $listener->populate(new LlmsTxtPopulateEvent($document));

        return $document;
    }
}
