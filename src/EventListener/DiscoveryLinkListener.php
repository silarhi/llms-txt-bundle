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

use Override;
use Psr\Link\EvolvableLinkProviderInterface;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

/**
 * Advertises the file in the Link header of the pages (discovery.link_header) through WebLink: the link joins the
 * "_links" of the request, which AddLinkHeaderListener serializes into one header with the preload(), link()…
 * of the templates.
 */
final readonly class DiscoveryLinkListener implements EventSubscriberInterface
{
    public function __construct(
        private UrlGenerator $urlGenerator,
        private string $rel,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        // before AddLinkHeaderListener (0), which reads the "_links"
        return [KernelEvents::RESPONSE => ['onKernelResponse', 8]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }

        $request = $event->getRequest();
        $links = $request->attributes->get('_links', new GenericLinkProvider());
        // a provider of the application which cannot take one more link: left as it is
        if (!$links instanceof EvolvableLinkProviderInterface) {
            return;
        }

        $request->attributes->set('_links', $links->withLink(new Link($this->rel, $this->urlGenerator->generate())));
    }

    /**
     * The pages only: not the fragments of a page (sub-requests, Turbo frames, XHR), the redirects, the errors, nor
     * the other formats (JSON, the llms.txt file itself…).
     */
    private function supports(ResponseEvent $event): bool
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest() || !$response->isSuccessful() || $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame')) {
            return false;
        }

        // the Content-Type of a response created without one is only set after this listener, by ResponseListener,
        // from the format of the request
        $contentType = $response->headers->get('Content-Type');
        if (null === $contentType) {
            return 'html' === $request->getRequestFormat();
        }

        return str_starts_with(strtolower($contentType), 'text/html');
    }
}
