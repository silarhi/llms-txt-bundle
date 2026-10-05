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

use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Silarhi\LlmsTxtBundle\Service\GeneratorInterface;
use Silarhi\LlmsTxtBundle\Service\RendererInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the dumped file when there is one, builds it on the fly otherwise. A file dumped into the public directory
 * is served by the web server before reaching this controller: dump it elsewhere (var/, a read-only image…) to have
 * it served from here.
 */
final readonly class LlmsTxtController
{
    public function __construct(
        private GeneratorInterface $generator,
        private RendererInterface $renderer,
        private string $dumpDirectory,
        private string $contentType,
        private int $maxAge,
    ) {
    }

    public function __invoke(): Response
    {
        $path = rtrim($this->dumpDirectory, '/') . '/' . LlmsTxtBundle::FILENAME;

        $response = is_file($path)
            ? new BinaryFileResponse($path, autoEtag: true, autoLastModified: true)
            : $this->stream($this->renderer->render($this->generator->generate()));

        $response->headers->set('Content-Type', $this->contentType);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);

        return $response;
    }

    /**
     * Each chunk is sent as soon as it is rendered, never the whole file at once. The chunks are created here, before
     * the response: an invalid document fails with an error page, not with a truncated 200.
     *
     * @param iterable<string> $chunks
     */
    private function stream(iterable $chunks): StreamedResponse
    {
        return new StreamedResponse(static function () use ($chunks): void {
            foreach ($chunks as $chunk) {
                echo $chunk;
            }
        });
    }
}
