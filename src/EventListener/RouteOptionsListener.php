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
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;

use function sprintf;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
final readonly class RouteOptionsListener implements EventSubscriberInterface
{
    public const OPTION = 'llms_txt';

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
            $option = $route->getOption(self::OPTION);
            if (null === $option || false === $option) {
                continue;
            }

            $entry = $this->createEntry($name, $option);
            $link = new Link($this->generateUrl($name), $entry->title, $entry->description);

            $event->getDocument()->section($entry->section ?? $this->defaultSection)->addLink($link);
        }
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
