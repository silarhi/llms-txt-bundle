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

use function assert;
use function is_array;
use function is_string;

use Override;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Exception\InvalidRouteOptionException;
use Silarhi\LlmsTxtBundle\Model\Link;

use function sprintf;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adds the routes carrying an "llms_txt" option, the way PrestaSitemapBundle adds the routes carrying a "sitemap" one:
 *
 *     #[Route('/contact', name: 'contact', options: ['llms_txt' => ['title' => 'Contact', 'description' => '…']])]
 */
final readonly class RouteOptionsListener implements EventSubscriberInterface
{
    public const OPTION = 'llms_txt';

    private const ALLOWED_KEYS = ['title', 'description', 'section'];

    public function __construct(
        private RouterInterface $router,
        private string $defaultSection,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        // before the listeners of the application: the static pages come first in their section
        return [LlmsTxtPopulateEvent::class => ['populate', 128]];
    }

    public function populate(LlmsTxtPopulateEvent $event): void
    {
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $options = $route->getOption(self::OPTION);
            if (null === $options || false === $options) {
                continue;
            }

            $link = $this->createLink($name, $options);
            $section = $options['section'] ?? $this->defaultSection;

            $event->getDocument()->section($section)->addLink($link);
        }
    }

    /**
     * @phpstan-assert array{title: string, description?: string|null, section?: string} $options
     */
    private function createLink(string $route, mixed $options): Link
    {
        if (!is_array($options)) {
            throw new InvalidRouteOptionException(sprintf('The "%s" option of the route "%s" must be an array with at least a "title", "%s" given.', self::OPTION, $route, get_debug_type($options)));
        }

        $unknownKeys = array_diff(array_keys($options), self::ALLOWED_KEYS);
        if ([] !== $unknownKeys) {
            throw new InvalidRouteOptionException(sprintf('The "%s" option of the route "%s" has unknown keys "%s", allowed: "%s".', self::OPTION, $route, implode('", "', $unknownKeys), implode('", "', self::ALLOWED_KEYS)));
        }

        $title = $options['title'] ?? null;
        $description = $options['description'] ?? null;
        $section = $options['section'] ?? null;
        if (!is_string($title) || '' === trim($title)) {
            throw new InvalidRouteOptionException(sprintf('The "%s" option of the route "%s" needs a non-empty "title".', self::OPTION, $route));
        }
        if (null !== $description && !is_string($description)) {
            throw new InvalidRouteOptionException(sprintf('The "description" of the "%s" option of the route "%s" must be a string.', self::OPTION, $route));
        }
        if (null !== $section && (!is_string($section) || '' === trim($section))) {
            throw new InvalidRouteOptionException(sprintf('The "section" of the "%s" option of the route "%s" must be a non-empty string.', self::OPTION, $route));
        }

        try {
            $url = $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (MissingMandatoryParametersException $exception) {
            throw new InvalidRouteOptionException(sprintf('The route "%s" has the "%s" option but needs parameters: add its URLs from a LlmsTxtPopulateEvent listener instead.', $route, self::OPTION), $exception->getCode(), previous: $exception);
        }

        return new Link($url, $title, $description);
    }
}
