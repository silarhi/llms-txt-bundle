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
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Section;

use function strlen;

/**
 * What MarkdownRenderer and FullMarkdownRenderer share: inline text, escaped links, sections in order, chunks.
 *
 * @internal
 */
final class Markdown
{
    /**
     * The lines are sent in chunks of about this size: one write and one hash update per chunk, not per link.
     */
    public const CHUNK_SIZE = 64 * 1024;

    /**
     * Batches the lines into chunks of about CHUNK_SIZE: PHP file streams do not buffer writes.
     *
     * @param iterable<string> $lines
     *
     * @return Generator<int, string>
     */
    public static function chunk(iterable $lines): Generator
    {
        $chunk = '';
        foreach ($lines as $line) {
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
     * The H1, the blockquote summary and the details, each followed by a line break.
     *
     * @return Generator<int, string>
     */
    public static function header(string $title, Document $document): Generator
    {
        yield '# ' . $title . "\n";

        $summary = self::inline($document->getSummary() ?? '');
        if ('' !== $summary) {
            yield "\n> " . $summary . "\n";
        }

        $details = trim($document->getDetails() ?? '');
        if ('' !== $details) {
            yield "\n" . $details . "\n";
        }
    }

    /**
     * The sections in the order of their first use, the Optional one last wherever its first link was added.
     *
     * @return list<Section>
     */
    public static function sections(Document $document): array
    {
        $sections = $document->getSections();

        return [
            ...array_filter($sections, static fn (Section $section): bool => !$section->isOptional()),
            ...array_filter($sections, static fn (Section $section): bool => $section->isOptional()),
        ];
    }

    /**
     * Titles, summary and descriptions are rendered on a single line: a line break would end the heading, the quote
     * or the list item they belong to.
     */
    public static function inline(string $text): string
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
    public static function escapeLinkText(string $text): string
    {
        return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], self::inline($text));
    }

    /**
     * A space or a closing parenthesis in the destination of a link would end it early.
     */
    public static function escapeUrl(string $url): string
    {
        return str_replace([' ', '(', ')', '<', '>'], ['%20', '%28', '%29', '%3C', '%3E'], trim($url));
    }
}
