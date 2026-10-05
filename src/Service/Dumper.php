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

use Override;
use Silarhi\LlmsTxtBundle\LlmsTxtBundle;

use function sprintf;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class Dumper implements DumperInterface
{
    public function __construct(
        private GeneratorInterface $generator,
        private RendererInterface $renderer,
        private Filesystem $filesystem,
        private string $directory,
    ) {
    }

    #[Override]
    public function dump(?string $directory = null): string
    {
        $directory = rtrim($directory ?? $this->directory, '/');
        $path = $directory . '/' . LlmsTxtBundle::FILENAME;

        $chunks = $this->renderer->render($this->generator->generate());

        // the chunks go to a temporary file renamed once complete: a web server never serves a half written file,
        // and a failing listener leaves the previous one in place. Next to the file: a rename is atomic only within
        // a filesystem.
        $this->filesystem->mkdir($directory);
        $temporary = $this->filesystem->tempnam($directory, '.' . LlmsTxtBundle::FILENAME . '.');
        try {
            $this->write($temporary, $chunks);

            // tempnam() makes the file readable by its owner only: keep the permissions of the previous file
            $this->filesystem->chmod($temporary, is_file($path) ? fileperms($path) & 0o7777 : 0o666 & ~umask());
            $this->filesystem->rename($temporary, $path, true);
        } finally {
            $this->filesystem->remove($temporary);
        }

        return $path;
    }

    /**
     * @param iterable<string> $chunks
     */
    private function write(string $path, iterable $chunks): void
    {
        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new IOException(sprintf('Failed to open "%s".', $path), path: $path);
        }

        try {
            foreach ($chunks as $chunk) {
                if (false === fwrite($handle, $chunk)) {
                    throw new IOException(sprintf('Failed to write "%s".', $path), path: $path);
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
