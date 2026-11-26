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

use Closure;

use function is_iterable;

use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Exception\InvalidLinkProviderException;
use Silarhi\LlmsTxtBundle\Model\Link;

use function sprintf;

/**
 * Adds the links of one link provider (#[AsLlmsTxtLinkProvider] or the "llms_txt.link_provider" tag) to its section.
 * Registered by LinkProviderPass, one per provider, with the provider's priority.
 */
final readonly class LinkProviderListener
{
    /**
     * @param Closure(): mixed $provider returns the links, as an iterable read when the file is rendered
     * @param string           $name     the provider, for the error messages: "service::method"
     */
    public function __construct(
        private Closure $provider,
        public string $section,
        public string $name,
    ) {
    }

    /**
     * @throws InvalidLinkProviderException when the provider does not return an iterable
     */
    public function __invoke(LlmsTxtPopulateEvent $event): void
    {
        $result = ($this->provider)();
        if (!is_iterable($result)) {
            throw new InvalidLinkProviderException(sprintf('The llms.txt link provider "%s" must return an iterable of %s, "%s" returned.', $this->name, Link::class, get_debug_type($result)));
        }

        /** @var iterable<Link> $links the links are read while the file is rendered: no check per link */
        $links = $result;
        $event->getDocument()->addLinks($this->section, $links);
    }
}
