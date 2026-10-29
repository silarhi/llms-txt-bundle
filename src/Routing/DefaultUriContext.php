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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * The absolute URLs of the file from "framework.router.default_uri", not from the request. During a request, the
 * RouterListener fills the request context of the router from the Host header (or X-Forwarded-Host behind a trusted
 * proxy): a forged one would end up in a publicly cached file.
 *
 * Without "default_uri", or with the "http://localhost" placeholder of the Symfony recipe, the URLs keep the host of
 * the request: "framework.trusted_hosts" should be set then.
 */
final readonly class DefaultUriContext
{
    /**
     * @param string $defaultUri "%router.request_context.base_url%", where FrameworkBundle puts "default_uri"
     */
    public function __construct(
        private RequestContextAwareInterface $router,
        private RequestStack $requestStack,
        private string $defaultUri = '',
    ) {
    }

    /**
     * Runs the callback with the scheme, host, ports and base URL of "default_uri" in the request context of the
     * router, restored afterwards: the rest of the request keeps the ones of the request.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function apply(callable $callback): mixed
    {
        $default = $this->getDefaultContext();
        // no request (a command, a worker): the context already comes from "default_uri", or from --base-url
        if (!$default instanceof RequestContext || !$this->requestStack->getMainRequest() instanceof Request) {
            return $callback();
        }

        $context = $this->router->getContext();
        $previous = clone $context;
        $this->copy($default, $context);
        try {
            return $callback();
        } finally {
            $this->copy($previous, $context);
        }
    }

    /**
     * The base URL of "default_uri", e.g. "https://example.com/app", null when it is not set.
     */
    public function getBaseUrl(): ?string
    {
        $context = $this->getDefaultContext();
        if (!$context instanceof RequestContext) {
            return null;
        }

        $scheme = $context->getScheme();
        $port = match ($scheme) {
            'http' => 80 === $context->getHttpPort() ? '' : ':' . $context->getHttpPort(),
            'https' => 443 === $context->getHttpsPort() ? '' : ':' . $context->getHttpsPort(),
            default => '',
        };

        return $scheme . '://' . $context->getHost() . $port . $context->getBaseUrl();
    }

    private function getDefaultContext(): ?RequestContext
    {
        // FrameworkBundle falls back to "localhost" without "default_uri": an empty fallback tells them apart
        $context = RequestContext::fromUri($this->defaultUri, '');
        $host = $context->getHost();

        return '' === $host || 'localhost' === $host ? null : $context;
    }

    private function copy(RequestContext $from, RequestContext $to): void
    {
        $to->setScheme($from->getScheme())
            ->setHost($from->getHost())
            ->setHttpPort($from->getHttpPort())
            ->setHttpsPort($from->getHttpsPort())
            ->setBaseUrl($from->getBaseUrl());
    }
}
