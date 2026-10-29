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

namespace Silarhi\LlmsTxtBundle\Controller;

use function is_string;

use RuntimeException;
use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Silarhi\LlmsTxtBundle\Routing\DefaultUriContext;
use Silarhi\LlmsTxtBundle\Service\GeneratorInterface;
use Silarhi\LlmsTxtBundle\Service\RendererInterface;

use function strlen;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * Serves the dumped file when there is one, builds it on the fly otherwise. A file dumped into the public directory
 * is served by the web server before reaching this controller: dump it elsewhere (var/, a read-only image…) to have
 * it served from here.
 */
final readonly class LlmsTxtController
{
    /**
     * What php://temp keeps in memory before spilling to a temporary file.
     */
    private const BUFFER_MEMORY = 256 * 1024;

    private const CHUNK_SIZE = 64 * 1024;

    /**
     * Larger files stay out of the cache: a hit holds the whole file in memory. Dump them instead.
     */
    private const CACHE_MAX_SIZE = 1024 * 1024;

    /**
     * @param int $cacheTtl how long the cache keeps the file built on the fly, in seconds: 0 builds it on each request
     */
    public function __construct(
        private GeneratorInterface $generator,
        private RendererInterface $renderer,
        private string $dumpDirectory,
        private string $contentType,
        private int $maxAge,
        private ?DefaultUriContext $defaultUriContext = null,
        private ?CacheInterface $cache = null,
        private int $cacheTtl = 0,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $path = rtrim($this->dumpDirectory, '/') . '/' . LlmsTxtBundle::FILENAME;

        $response = is_file($path)
            ? new BinaryFileResponse($path, autoEtag: true, autoLastModified: true)
            : $this->build($request);

        $response->headers->set('Content-Type', $this->contentType);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic();
        $response->setMaxAge($this->maxAge);

        // a StreamedResponse sends the output of its callback whatever its status: a 304 has no body
        if ($response->isNotModified($request) && $response instanceof StreamedResponse) {
            $response->setCallback(static function (): void {
            });
        }

        return $response;
    }

    /**
     * Builds the file once per cacheTtl and base URL: without the cache, every request (a 304, a ?random query string
     * past the HTTP caches) would run all the listeners.
     */
    private function build(Request $request): Response
    {
        if (!$this->cache instanceof CacheInterface || $this->cacheTtl <= 0) {
            return $this->stream(...$this->render());
        }

        $rendered = null;
        $content = $this->cache->get($this->getCacheKey($request), function (ItemInterface $item, bool &$save) use (&$rendered): ?string {
            $item->expiresAfter($this->cacheTtl);

            $rendered = $this->render();
            if ($rendered[1] > self::CACHE_MAX_SIZE) {
                $save = false;

                return null;
            }

            [$buffer] = $rendered;
            $rendered = null;
            try {
                return (string) stream_get_contents($buffer, offset: 0);
            } finally {
                fclose($buffer);
            }
        });

        if (!is_string($content)) {
            return $this->stream(...$rendered ?? $this->render());
        }

        $response = new Response($content);
        $response->headers->set('Content-Length', (string) strlen($content));
        $response->setEtag(hash('xxh128', $content));

        return $response;
    }

    /**
     * The URLs of the file depend on the base URL: one entry per host of the requests, unless "default_uri" sets it.
     */
    private function getCacheKey(Request $request): string
    {
        $baseUrl = $this->defaultUriContext?->getBaseUrl() ?? $request->getSchemeAndHttpHost() . $request->getBaseUrl();

        return 'llms_txt.' . hash('xxh128', $baseUrl);
    }

    /**
     * The whole file is rendered before the response: a listener failing half way gives an error page, never a
     * truncated 200 left in the caches. php://temp spills to a temporary file past BUFFER_MEMORY: the memory stays
     * flat, and the length and the ETag are known before the first byte. The URLs come from "default_uri" when it is
     * set, those of the listeners too: the links are generated while the chunks are read.
     *
     * @return array{resource, int, string} the buffer, its length and its ETag
     */
    private function render(): array
    {
        return $this->defaultUriContext instanceof DefaultUriContext ? $this->defaultUriContext->apply($this->doRender(...)) : $this->doRender();
    }

    /**
     * @return array{resource, int, string}
     */
    private function doRender(): array
    {
        $chunks = $this->renderer->render($this->generator->generate());

        $buffer = fopen('php://temp/maxmemory:' . self::BUFFER_MEMORY, 'w+');
        if (false === $buffer) {
            throw new RuntimeException('Failed to open a php://temp buffer.');
        }

        $hash = hash_init('xxh128');
        $length = 0;
        try {
            foreach ($chunks as $chunk) {
                if (false === fwrite($buffer, $chunk)) {
                    throw new RuntimeException('Failed to write the php://temp buffer.');
                }

                hash_update($hash, $chunk);
                $length += strlen($chunk);
            }
        } catch (Throwable $exception) {
            fclose($buffer);

            throw $exception;
        }

        return [$buffer, $length, hash_final($hash)];
    }

    /**
     * @param resource $buffer
     */
    private function stream($buffer, int $length, string $etag): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($buffer): void {
            rewind($buffer);
            // not fpassthru(): it maps the whole temporary file and writes it to the output buffers in one piece
            while (!feof($buffer)) {
                echo fread($buffer, self::CHUNK_SIZE);
            }
            fclose($buffer);
        });
        $response->headers->set('Content-Length', (string) $length);
        $response->setEtag($etag);

        return $response;
    }
}
