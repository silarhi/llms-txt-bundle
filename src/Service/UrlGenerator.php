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

namespace Silarhi\LlmsTxtBundle\Service;

use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Silarhi\LlmsTxtBundle\Routing\DefaultUriContext;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The absolute URL of the llms.txt file, advertised by the discovery link and header. From "framework.router.default_uri"
 * when it is set, like the URLs of the file itself: the pages carrying it can be publicly cached too.
 */
final readonly class UrlGenerator
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private UrlHelper $urlHelper,
        private ?DefaultUriContext $defaultUriContext = null,
    ) {
    }

    public function generate(): string
    {
        $generate = function (): string {
            try {
                return $this->router->generate(LlmsTxtBundle::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (RouteNotFoundException) {
                // the routes of the bundle are not imported: the dumped file is served by the web server at the root
                $baseUrl = $this->defaultUriContext?->getBaseUrl();

                return null !== $baseUrl ? $baseUrl . '/' . LlmsTxtBundle::FILENAME : $this->urlHelper->getAbsoluteUrl('/' . LlmsTxtBundle::FILENAME);
            }
        };

        return $this->defaultUriContext instanceof DefaultUriContext ? $this->defaultUriContext->apply($generate) : $generate();
    }
}
