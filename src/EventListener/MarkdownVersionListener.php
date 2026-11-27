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

use function in_array;
use function is_array;
use function is_string;

use Override;
use Psr\Link\EvolvableLinkProviderInterface;
use Silarhi\LlmsTxtBundle\LlmsTxt;
use Silarhi\LlmsTxtBundle\Routing\MarkdownRoutes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

/**
 * For the routes with a Markdown version (their "_format" requirement lists "md", see MarkdownRoutes): switches the
 * request format to "md" when the Accept header prefers Markdown, makes the responses vary on Accept, and links the
 * HTML page to its Markdown version. Registered with "llms_txt.markdown_versions" only.
 */
final readonly class MarkdownVersionListener implements EventSubscriberInterface
{
    /**
     * The request attribute marking the routes with a Markdown version, for the response.
     */
    private const ATTRIBUTE = '_llms_txt_markdown';

    private const MARKDOWN_TYPES = ['text/markdown', 'text/x-markdown'];

    private const HTML_TYPES = ['text/html', 'application/xhtml+xml'];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private MarkdownRoutes $routes,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            // after RouterListener (32), which sets the "_route"
            KernelEvents::REQUEST => ['onKernelRequest', 16],
            // before AddLinkHeaderListener (0), which reads the "_links"
            KernelEvents::RESPONSE => ['onKernelResponse', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // what "framework.request.formats" would do, unless the application maps "md" itself
        if (null === $request->getMimeType(LlmsTxt::MARKDOWN_FORMAT)) {
            $request->setFormat(LlmsTxt::MARKDOWN_FORMAT, self::MARKDOWN_TYPES);
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route) || !$this->routes->has($route)) {
            return;
        }

        $request->attributes->set(self::ATTRIBUTE, true);

        // an explicit .md path already set it; an explicit .html path is the default format, as a path without suffix
        $format = $request->attributes->get('_format');
        if ((null === $format || 'html' === $format) && $this->prefersMarkdown($request)) {
            $request->attributes->set('_format', LlmsTxt::MARKDOWN_FORMAT);
            $request->setRequestFormat(LlmsTxt::MARKDOWN_FORMAT);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (true !== $request->attributes->get(self::ATTRIBUTE)) {
            return;
        }

        $response = $event->getResponse();
        // the same URL answers HTML or Markdown: a cache must keep both
        $response->setVary('Accept', false);

        if (!$event->isMainRequest() || !$response->isSuccessful() || LlmsTxt::MARKDOWN_FORMAT === $request->getRequestFormat()) {
            return;
        }

        // WebLink is optional: without it, there is no Link header to add the alternate link to
        if (!class_exists(GenericLinkProvider::class)) {
            // symfony/web-link is a dev dependency: no CI job runs without it
            return; // @codeCoverageIgnore
        }

        $links = $request->attributes->get('_links', new GenericLinkProvider());
        if (!$links instanceof EvolvableLinkProviderInterface) {
            return;
        }

        $link = (new Link(Link::REL_ALTERNATE, $this->generateMarkdownUrl($request)))->withAttribute('type', 'text/markdown');
        $request->attributes->set('_links', $links->withLink($link));
    }

    /**
     * The first of the Markdown and HTML types in the order of the Accept header wins; any other type does not
     * count, the wildcards included: a browser gets HTML, an agent asking for text/markdown gets Markdown.
     */
    private function prefersMarkdown(Request $request): bool
    {
        foreach ($request->getAcceptableContentTypes() as $type) {
            $type = strtolower($type);
            if (in_array($type, self::MARKDOWN_TYPES, true)) {
                return true;
            }
            if (in_array($type, self::HTML_TYPES, true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * The .md URL when the path of the route has a {_format}, the URL of the page otherwise (an Accept-only route):
     * the same URL answers Markdown to an Accept header preferring it.
     */
    private function generateMarkdownUrl(Request $request): string
    {
        $route = $request->attributes->get('_route');
        $parameters = $request->attributes->get('_route_params', []);
        if (is_string($route) && is_array($parameters)) {
            try {
                $url = $this->urlGenerator->generate($route, ['_format' => LlmsTxt::MARKDOWN_FORMAT] + $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
                // a _format which is not in the path ends in the query string: it would not switch the format
                $query = parse_url($url, \PHP_URL_QUERY);
                if (!is_string($query) || !str_contains($query, '_format=')) {
                    return $url;
                }
            } catch (RoutingException) {
            }
        }

        return $request->getUri();
    }
}
