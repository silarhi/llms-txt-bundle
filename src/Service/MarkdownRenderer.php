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

use Generator;
use Override;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;

use function sprintf;

/**
 * Renders a document in the format of https://llmstxt.org.
 */
final class MarkdownRenderer implements RendererInterface
{
    /**
     * Checks the document right away, then renders it chunk by chunk: a response or a file can start before the
     * links are read, and never fails half written for a missing title.
     */
    #[Override]
    public function render(Document $document): iterable
    {
        $title = Markdown::inline($document->getTitle() ?? '');
        if ('' === $title) {
            throw new MissingTitleException();
        }

        return Markdown::chunk($this->renderLines($title, $document));
    }

    private function renderLink(Link $link): string
    {
        $line = sprintf('- [%s](%s)', Markdown::escapeLinkText($link->title), Markdown::escapeUrl($link->url));

        $description = Markdown::inline($link->description ?? '');
        if ('' !== $description) {
            $line .= ': ' . $description;
        }

        return $line;
    }

    /**
     * @return Generator<int, string>
     */
    private function renderLines(string $title, Document $document): Generator
    {
        yield from Markdown::header($title, $document);

        foreach (Markdown::sections($document) as $section) {
            $empty = true;
            foreach ($section->getLinks() as $link) {
                // the heading waits for the first link: an empty section is left out
                if ($empty) {
                    yield "\n## " . Markdown::inline($section->getName()) . "\n\n";
                    $empty = false;
                }

                yield $this->renderLink($link) . "\n";
            }
        }
    }
}
