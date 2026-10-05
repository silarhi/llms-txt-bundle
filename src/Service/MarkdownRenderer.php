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
use Silarhi\LlmsTxtBundle\Model\Section;

use function sprintf;
use function strlen;

/**
 * Renders a document in the format of https://llmstxt.org.
 */
final class MarkdownRenderer implements RendererInterface
{
    /**
     * The lines are sent in chunks of about this size: one write and one hash update per chunk, not per link.
     */
    private const CHUNK_SIZE = 64 * 1024;

    /**
     * Checks the document right away, then renders it chunk by chunk: a response or a file can start before the
     * links are read, and never fails half written for a missing title.
     */
    #[Override]
    public function render(Document $document): iterable
    {
        $title = $this->inline($document->getTitle() ?? '');
        if ('' === $title) {
            throw new MissingTitleException();
        }

        return $this->renderChunks($title, $document);
    }

    /**
     * @return Generator<int, string>
     */
    private function renderChunks(string $title, Document $document): Generator
    {
        $chunk = '';
        foreach ($this->renderLines($title, $document) as $line) {
            $chunk .= $line;
            if (strlen($chunk) >= self::CHUNK_SIZE) {
                yield $chunk;
                $chunk = '';
            }
        }

        if ('' !== $chunk) {
            yield $chunk;
        }
    }

    /**
     * @return Generator<int, string>
     */
    private function renderLines(string $title, Document $document): Generator
    {
        yield '# ' . $title . "\n";

        $summary = $this->inline($document->getSummary() ?? '');
        if ('' !== $summary) {
            yield "\n> " . $summary . "\n";
        }

        $details = trim($document->getDetails() ?? '');
        if ('' !== $details) {
            yield "\n" . $details . "\n";
        }

        // the Optional section always comes last, wherever its first link was added
        $sections = $document->getSections();
        $sections = [
            ...array_filter($sections, static fn (Section $section): bool => !$section->isOptional()),
            ...array_filter($sections, static fn (Section $section): bool => $section->isOptional()),
        ];

        foreach ($sections as $section) {
            $empty = true;
            foreach ($section->getLinks() as $link) {
                // the heading waits for the first link: an empty section is left out
                if ($empty) {
                    yield "\n## " . $this->inline($section->getName()) . "\n\n";
                    $empty = false;
                }

                yield $this->renderLink($link) . "\n";
            }
        }
    }

    private function renderLink(Link $link): string
    {
        $line = sprintf('- [%s](%s)', $this->escapeLinkText($link->title), $this->escapeUrl($link->url));

        $description = $this->inline($link->description ?? '');
        if ('' !== $description) {
            $line .= ': ' . $description;
        }

        return $line;
    }

    /**
     * Titles, summary and descriptions are rendered on a single line: a line break would end the heading, the quote
     * or the list item they belong to.
     */
    private function inline(string $text): string
    {
        // most values have nothing to collapse: no ASCII whitespace but single spaces, and none of the lead bytes of
        // the UTF-8 spaces \s matches in Unicode mode (U+0085, U+00A0, U+1680, U+2000 to U+3000)
        if (false === strpbrk($text, "\t\n\v\f\r\xC2\xE1\xE2\xE3") && !str_contains($text, '  ')) {
            return trim($text);
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * A bracket in the text of a link would close it early.
     */
    private function escapeLinkText(string $text): string
    {
        return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $this->inline($text));
    }

    /**
     * A space or a closing parenthesis in the destination of a link would end it early.
     */
    private function escapeUrl(string $url): string
    {
        return str_replace([' ', '(', ')', '<', '>'], ['%20', '%28', '%29', '%3C', '%3E'], trim($url));
    }
}
