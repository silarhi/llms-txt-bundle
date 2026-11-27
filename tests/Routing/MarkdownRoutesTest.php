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

namespace Silarhi\LlmsTxtBundle\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Routing\MarkdownRoutes;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class MarkdownRoutesTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, bool}>
     */
    public static function provideRequirements(): iterable
    {
        yield 'html|md' => [['_format' => 'html|md'], true];
        yield 'md alone' => [['_format' => 'md'], true];
        yield 'a group' => [['_format' => '(md|html)'], true];
        yield 'a non-capturing group' => [['_format' => '(?:html|md)'], true];
        yield 'no requirement' => [[], false];
        yield 'no "md"' => [['_format' => 'html|json'], false];
        yield 'a pattern merely matching "md"' => [['_format' => '[a-z]+'], false];
        yield 'a longer name' => [['_format' => 'html|mdx'], false];
    }

    /**
     * @param array<string, string> $requirements
     */
    #[DataProvider('provideRequirements')]
    public function testAcceptsTheRoutesListingMd(array $requirements, bool $accepts): void
    {
        self::assertSame($accepts, MarkdownRoutes::accepts(new Route('/guide.{_format}', requirements: $requirements)));
    }

    public function testCachesTheNamesOfTheRoutes(): void
    {
        $cacheDir = sys_get_temp_dir() . '/llms-txt-bundle/markdown-routes-' . bin2hex(random_bytes(4));
        $loads = 0;
        $router = new Router(new ClosureLoader(), static function () use (&$loads): RouteCollection {
            ++$loads;
            $routes = new RouteCollection();
            $routes->add('guide', new Route('/guide.{_format}', requirements: ['_format' => 'html|md']));
            $routes->add('faq', new Route('/faq', requirements: ['_format' => 'html|md']));
            $routes->add('contact', new Route('/contact'));

            return $routes;
        });

        try {
            (new MarkdownRoutes($router, new ConfigCacheFactory(false), $cacheDir))->warmUp($cacheDir);
            self::assertSame(['guide', 'faq'], require $cacheDir . '/llms_txt_markdown_routes.php');

            $routes = new MarkdownRoutes($router, new ConfigCacheFactory(false), $cacheDir);
            self::assertTrue($routes->has('guide'));
            self::assertTrue($routes->has('faq'));
            self::assertFalse($routes->has('contact'));
            self::assertSame(1, $loads);
        } finally {
            (new Filesystem())->remove($cacheDir);
        }
    }

    public function testReadsTheRoutesWhenTheCacheCannotBeWritten(): void
    {
        $cacheDir = sys_get_temp_dir() . '/llms-txt-bundle/markdown-routes-' . bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($cacheDir . '/not-a-directory', '');
        $router = new Router(new ClosureLoader(), static function (): RouteCollection {
            $routes = new RouteCollection();
            $routes->add('guide', new Route('/guide.{_format}', requirements: ['_format' => 'html|md']));
            $routes->add('contact', new Route('/contact'));

            return $routes;
        });

        try {
            $routes = new MarkdownRoutes($router, new ConfigCacheFactory(false), $cacheDir . '/not-a-directory');
            self::assertTrue($routes->has('guide'));
            self::assertFalse($routes->has('contact'));
        } finally {
            (new Filesystem())->remove($cacheDir);
        }
    }
}
