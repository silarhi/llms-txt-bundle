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

use Closure;
use Generator;

use function is_string;

use Override;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;

/**
 * Renders the llms-full.txt file: the header of llms.txt, then the Markdown of each page whose link has a content,
 * under its "# title" and its "Source: url". The links without a content are left out.
 */
final class FullMarkdownRenderer implements RendererInterface
{
    #[Override]
    public function render(Document $document): iterable
    {
        $title = Markdown::inline($document->getTitle() ?? '');
        if ('' === $title) {
            throw new MissingTitleException();
        }

        return Markdown::chunk($this->renderLines($title, $document));
    }

    /**
     * @return Generator<int, string>
     */
    private function renderLines(string $title, Document $document): Generator
    {
        yield from Markdown::header($title, $document);

        foreach (Markdown::sections($document) as $section) {
            foreach ($section->getLinks() as $link) {
                yield from $this->renderPage($link);
            }
        }
    }

    /**
     * The heading of the page waits for its first non-empty piece: a page without content is left out. The pages keep
     * their own headings: an H1 in a page reads as the start of a page, as in the llms-full.txt files in the wild.
     *
     * @return Generator<int, string>
     */
    private function renderPage(Link $link): Generator
    {
        if (null === $link->content) {
            return;
        }

        $last = '';
        foreach ($this->pieces($link->content) as $piece) {
            if ('' === $piece) {
                continue;
            }

            if ('' === $last) {
                yield "\n# " . Markdown::inline($link->title) . "\n\nSource: " . Markdown::escapeUrl($link->url) . "\n\n";
            }

            yield $piece;
            $last = $piece;
        }

        if ('' !== $last && !str_ends_with($last, "\n")) {
            yield "\n";
        }
    }

    /**
     * @param string|(Closure(): (string|iterable<string>)) $content
     *
     * @return iterable<string>
     */
    private function pieces(string|Closure $content): iterable
    {
        if ($content instanceof Closure) {
            $content = $content();
        }

        // a string is trimmed, a page given piece by piece is left as it is: its ends are not known in advance
        return is_string($content) ? [trim($content)] : $content;
    }
}
