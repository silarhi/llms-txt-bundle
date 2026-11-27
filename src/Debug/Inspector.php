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

namespace Silarhi\LlmsTxtBundle\Debug;

use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\LlmsTxt;
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Service\GeneratorInterface;
use Silarhi\LlmsTxtBundle\Service\RendererInterface;

use function sprintf;
use function strlen;

use Symfony\Component\Console\Helper\Helper;

/**
 * Builds the report of debug:llms-txt. The document is built up to three times, like requests of the files would:
 * once for the checks, once per file measured, since the links given as generators can be read once.
 *
 * @internal
 */
final readonly class Inspector
{
    /**
     * @param RendererInterface|null $fullRenderer null while llms-full.txt is disabled
     */
    public function __construct(
        private GeneratorInterface $generator,
        private RendererInterface $renderer,
        private ?RendererInterface $fullRenderer,
        private SourceDescriber $sourceDescriber,
        private LinkChecker $linkChecker,
        private string $dumpDirectory,
    ) {
    }

    /**
     * @param int $maxSize the size llms.txt should stay under, in bytes
     */
    public function inspect(int $maxSize): Report
    {
        $sources = $this->sourceDescriber->describe();

        $warnings = [];
        $document = $this->generator->generate();
        if ('' === trim($document->getTitle() ?? '')) {
            $warnings[] = 'The document has no title: set "llms_txt.title", or call Document::setTitle() from a listener.';
        }

        $check = $this->linkChecker->check($document);
        $warnings = [...$warnings, ...$check->warnings];

        $size = $this->measure($this->renderer);
        if (null !== $size && $size > $maxSize) {
            $warnings[] = sprintf('llms.txt weighs %s, over the %s of --max-size: move links to the "%s" section, or their pages to llms-full.txt.', Helper::formatMemory($size), Helper::formatMemory($maxSize), Section::OPTIONAL);
        }

        $fullSize = null;
        if ($this->fullRenderer instanceof RendererInterface) {
            $fullSize = $this->measure($this->fullRenderer);
        } elseif ($check->pages > 0) {
            $warnings[] = sprintf('%d links have a content, but llms-full.txt is disabled: set "llms_txt.full.enabled".', $check->pages);
        }

        return new Report($sources, $check->sections, $check->links, $check->pages, $size, $fullSize, $this->describeFiles(), $warnings);
    }

    /**
     * Renders a new document: the links given as generators of the first one were read by the checks.
     */
    private function measure(RendererInterface $renderer): ?int
    {
        try {
            $size = 0;
            foreach ($renderer->render($this->generator->generate()) as $chunk) {
                $size += strlen($chunk);
            }

            return $size;
        } catch (MissingTitleException) {
            return null;
        }
    }

    /**
     * @return list<DumpedFile>
     */
    private function describeFiles(): array
    {
        $filenames = $this->fullRenderer instanceof RendererInterface ? [LlmsTxt::FILENAME, LlmsTxt::FULL_FILENAME] : [LlmsTxt::FILENAME];

        $files = [];
        foreach ($filenames as $filename) {
            $path = rtrim($this->dumpDirectory, '/') . '/' . $filename;
            $files[] = is_file($path)
                ? new DumpedFile($path, (int) filesize($path), date(\DATE_ATOM, (int) filemtime($path)))
                : new DumpedFile($path, null, null);
        }

        return $files;
    }
}
