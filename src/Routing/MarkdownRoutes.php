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

namespace Silarhi\LlmsTxtBundle\Routing;

use function in_array;

use Override;
use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The routes with a Markdown version: their "_format" requirement lists "md".
 *
 *     #[Route('/blog/{slug}.{_format}', format: 'html', requirements: ['_format' => 'html|md'])] // /blog/foo.md too
 *     #[Route('/faq', format: 'html', requirements: ['_format' => 'html|md'])]                  // Accept header only
 *
 * The names are cached next to the compiled routes, like the route options: a request checks its route against them
 * without loading the route collection.
 *
 * @internal
 */
final class MarkdownRoutes implements CacheWarmerInterface
{
    /**
     * @var array<string, true>|null
     */
    private ?array $names = null;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ConfigCacheFactoryInterface $configCacheFactory,
        private readonly string $cacheDir,
    ) {
    }

    /**
     * The alternatives the requirement names, "html|md" or "(md|html)": a pattern that merely matches "md", such as
     * "[a-z]+", does not opt in, its action would get the Markdown requests without a Markdown template.
     */
    public static function accepts(Route $route): bool
    {
        $requirement = $route->getRequirement('_format');
        if (null === $requirement) {
            return false;
        }

        $alternatives = explode('|', trim(str_replace('?:', '', $requirement), '()'));

        return in_array(LlmsTxtBundle::MARKDOWN_FORMAT, $alternatives, true);
    }

    public function has(string $route): bool
    {
        $this->names ??= array_fill_keys($this->load($this->cacheDir), true);

        return isset($this->names[$route]);
    }

    #[Override]
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->load($buildDir ?? $cacheDir);

        return [];
    }

    /**
     * @return list<string>
     */
    private function load(string $cacheDir): array
    {
        try {
            $cache = $this->configCacheFactory->cache($cacheDir . '/llms_txt_markdown_routes.php', function (ConfigCacheInterface $cache): void {
                $collection = $this->router->getRouteCollection();
                $cache->write('<?php return ' . var_export($this->collect(), true) . ';' . "\n", $collection->getResources());
            });
        } catch (IOExceptionInterface) {
            return $this->collect();
        }

        /** @var list<string> $names */
        $names = require $cache->getPath();

        return $names;
    }

    /**
     * @return list<string>
     */
    private function collect(): array
    {
        $names = [];
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            if (self::accepts($route)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
