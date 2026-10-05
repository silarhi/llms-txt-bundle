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

use RuntimeException;
use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Silarhi\LlmsTxtBundle\Service\GeneratorInterface;
use Silarhi\LlmsTxtBundle\Service\RendererInterface;

use function strlen;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    private const CHUNK_SIZE = 8192;

    public function __construct(
        private GeneratorInterface $generator,
        private RendererInterface $renderer,
        private string $dumpDirectory,
        private string $contentType,
        private int $maxAge,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $path = rtrim($this->dumpDirectory, '/') . '/' . LlmsTxtBundle::FILENAME;

        $response = is_file($path)
            ? new BinaryFileResponse($path, autoEtag: true, autoLastModified: true)
            : $this->render();

        $response->headers->set('Content-Type', $this->contentType);
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
     * The whole file is rendered before the response: a listener failing half way gives an error page, never a
     * truncated 200 left in the caches. php://temp spills to a temporary file past BUFFER_MEMORY: the memory stays
     * flat, and the length and the ETag are known before the first byte.
     */
    private function render(): StreamedResponse
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

        $response = new StreamedResponse(static function () use ($buffer): void {
            rewind($buffer);
            // not fpassthru(): it maps the whole temporary file and writes it to the output buffers in one piece
            while (!feof($buffer)) {
                echo fread($buffer, self::CHUNK_SIZE);
            }
            fclose($buffer);
        });
        $response->headers->set('Content-Length', (string) $length);
        $response->setEtag(hash_final($hash));

        return $response;
    }
}
