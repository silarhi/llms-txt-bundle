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

namespace Silarhi\LlmsTxtBundle\Tests\Fixtures;

use Override;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What an application listener looks like: links built from its own data.
 */
final readonly class PopulateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [LlmsTxtPopulateEvent::class => 'populate'];
    }

    public function populate(LlmsTxtPopulateEvent $event): void
    {
        $event->getDocument()
            ->addLink('Offers', $this->urlGenerator->generate('contact', ['offer' => 'first'], UrlGeneratorInterface::ABSOLUTE_URL), 'First offer', 'The first one')
            ->addLink('Pages', 'https://example.com/about', 'About');
    }
}
