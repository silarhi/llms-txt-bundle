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

namespace Silarhi\LlmsTxtBundle\EventListener;

use function is_array;

use Override;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Exception\InvalidRouteOptionException;
use Silarhi\LlmsTxtBundle\Exception\LlmsTxtExceptionInterface;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;

use function sprintf;

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adds the routes carrying an "llms_txt" option, the way PrestaSitemapBundle adds the routes carrying a "sitemap" one:
 *
 *     #[Route('/contact', name: 'contact', options: ['llms_txt' => new LlmsTxtEntry(title: 'Contact', description: '…')])]
 *
 * The option can also be an array, {title: …, description: …, section: …}, for the routes YAML or XML declare.
 */
final readonly class RouteOptionsListener implements EventSubscriberInterface, CacheWarmerInterface
{
    public const OPTION = 'llms_txt';

    public function __construct(
        private RouterInterface $router,
        private string $defaultSection,
        private ?ConfigCacheFactoryInterface $configCacheFactory = null,
        private ?string $cacheDir = null,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        // before the listeners of the application: the static pages come first in their section
        return [LlmsTxtPopulateEvent::class => ['populate', 128]];
    }

    #[Override]
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * Writes the cache of the route options with the compiled routes: a read-only build directory works too.
     *
     * @return list<string>
     */
    #[Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        try {
            $this->getEntries($buildDir ?? $cacheDir);
        } catch (LlmsTxtExceptionInterface) {
            // an invalid option fails the requests of the file, as without the cache, not the deployment
        }

        return [];
    }

    public function populate(LlmsTxtPopulateEvent $event): void
    {
        $document = $event->getDocument();
        foreach ($this->getEntries($this->cacheDir) as [$name, $title, $description, $section]) {
            $document->section($section ?? $this->defaultSection)->addLink(new Link($this->generateUrl($name), $title, $description));
        }
    }

    /**
     * The routes carrying the option, cached next to the compiled routes like the router does: loading the route
     * collection (every attribute, YAML or XML file of the application) costs far more than the file itself.
     *
     * @return list<array{string, string, string|null, string|null}> name, title, description, section
     */
    private function getEntries(?string $cacheDir): array
    {
        if (!$this->configCacheFactory instanceof ConfigCacheFactoryInterface || null === $cacheDir) {
            return $this->collectEntries();
        }

        try {
            $cache = $this->configCacheFactory->cache($cacheDir . '/llms_txt_routes.php', function (ConfigCacheInterface $cache): void {
                $collection = $this->router->getRouteCollection();
                $cache->write('<?php return ' . var_export($this->collectEntries(), true) . ';' . "\n", $collection->getResources());
            });
        } catch (IOExceptionInterface) {
            return $this->collectEntries();
        }

        /** @var list<array{string, string, string|null, string|null}> $entries */
        $entries = require $cache->getPath();

        return $entries;
    }

    /**
     * @return list<array{string, string, string|null, string|null}>
     */
    private function collectEntries(): array
    {
        $entries = [];
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $option = $route->getOption(self::OPTION);
            if (null === $option || false === $option) {
                continue;
            }

            $entry = $this->createEntry($name, $option);
            $entries[] = [$name, $entry->title, $entry->description, $entry->section];
        }

        return $entries;
    }

    private function createEntry(string $route, mixed $option): LlmsTxtEntry
    {
        if ($option instanceof LlmsTxtEntry) {
            return $option;
        }

        if (!is_array($option)) {
            throw new InvalidRouteOptionException(sprintf('The "%s" option of the route "%s" must be a %s or an array, "%s" given.', self::OPTION, $route, LlmsTxtEntry::class, get_debug_type($option)));
        }

        try {
            return LlmsTxtEntry::fromArray($option);
        } catch (InvalidRouteOptionException $exception) {
            throw new InvalidRouteOptionException(sprintf('The "%s" option of the route "%s" is invalid: %s', self::OPTION, $route, $exception->getMessage()), $exception->getCode(), $exception);
        }
    }

    private function generateUrl(string $route): string
    {
        try {
            return $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (MissingMandatoryParametersException $exception) {
            throw new InvalidRouteOptionException(sprintf('The route "%s" has the "%s" option but needs parameters: add its URLs from a LlmsTxtPopulateEvent listener instead.', $route, self::OPTION), $exception->getCode(), previous: $exception);
        }
    }
}
