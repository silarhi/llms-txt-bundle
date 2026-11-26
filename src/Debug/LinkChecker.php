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

use function in_array;

use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Service\Markdown;

use function sprintf;

use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * Counts the links of each section and finds the duplicate or relative URLs, the URLs on another host, the empty
 * titles and sections. Reads every link once: the links given as generators can be read once.
 *
 * @internal
 */
final readonly class LinkChecker
{
    private const MESSAGES = [
        'duplicate' => '%d links repeat the URL of another one, the first: "%s".',
        'relative' => '%d links do not have an absolute http(s) URL, the first: "%s".',
        'host' => '%d links are on another host than "{host}", the first: "%s". Check "framework.router.default_uri", or the --base-url of llms-txt:dump.',
        'title' => '%d links have no title, the first: "%s".',
    ];

    public function __construct(
        private RequestContextAwareInterface $router,
    ) {
    }

    public function check(Document $document): LinkCheck
    {
        // the host the URLs are generated with, "" without "framework.router.default_uri" in a command
        $host = $this->router->getContext()->getHost();

        // the URLs, not the links: they are released once read
        $seen = [];
        /** @var array<string, array{int, string}> $problems kind => [count, first URL] */
        $problems = [];
        $sections = [];
        $total = 0;
        $pages = 0;
        $warnings = [];

        foreach (Markdown::sections($document) as $section) {
            $count = 0;
            foreach ($section->getLinks() as $link) {
                ++$count;
                if (null !== $link->content) {
                    ++$pages;
                }

                $url = trim($link->url);
                foreach ($this->findProblems($url, $link->title, $host, isset($seen[$url])) as $kind) {
                    $problems[$kind] = [($problems[$kind][0] ?? 0) + 1, $problems[$kind][1] ?? $url];
                }
                $seen[$url] = true;
            }

            $total += $count;
            $sections[] = new SectionStats($section->getName(), $count);
            if (0 === $count) {
                $warnings[] = sprintf('The section "%s" has no links: it is left out of the file.', $section->getName());
            }
        }

        foreach (self::MESSAGES as $kind => $message) {
            if (isset($problems[$kind])) {
                // replaced after sprintf(): a "%" in the host would be read as a placeholder
                $warnings[] = str_replace('{host}', $host, sprintf($message, ...$problems[$kind]));
            }
        }

        return new LinkCheck($sections, $total, $pages, $warnings);
    }

    /**
     * @return list<key-of<self::MESSAGES>>
     */
    private function findProblems(string $url, string $title, string $host, bool $seen): array
    {
        $kinds = $seen ? ['duplicate'] : [];

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $kinds[] = 'relative';
        } elseif ('' !== $host && strtolower($parts['host']) !== strtolower($host)) {
            $kinds[] = 'host';
        }

        if ('' === trim($title)) {
            $kinds[] = 'title';
        }

        return $kinds;
    }
}
