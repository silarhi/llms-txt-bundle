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

namespace Silarhi\LlmsTxtBundle\Tests\Debug;

use Generator as PhpGenerator;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Debug\LinkChecker;
use Silarhi\LlmsTxtBundle\Debug\SectionStats;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Model\Section;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class LinkCheckerTest extends TestCase
{
    public function testCountsTheLinksOfEachSectionInTheOrderOfTheFile(): void
    {
        $document = (new Document('Example'))
            ->addLink(Section::OPTIONAL, 'https://example.com/legal', 'Legal')
            ->addLinks('Blog', (static function (): PhpGenerator {
                yield new Link('https://example.com/blog/1', 'First', content: 'Post');
                yield new Link('https://example.com/blog/2', 'Second');
            })());

        $check = $this->createChecker('example.com')->check($document);

        self::assertEquals([new SectionStats('Blog', 2), new SectionStats(Section::OPTIONAL, 1)], $check->sections);
        self::assertSame(3, $check->links);
        self::assertSame(1, $check->pages);
        self::assertSame([], $check->warnings);
    }

    public function testFindsTheProblems(): void
    {
        $document = (new Document('Example'))
            ->addLink('Pages', 'https://example.com/a', 'A')
            ->addLink('Pages', 'https://example.com/a', 'A again')
            ->addLink('Pages', '/relative', 'Relative')
            ->addLink('Pages', 'ftp://example.com/file', 'FTP')
            ->addLink('Pages', 'https://other.example/b', ' ')
            ->addLinks('Empty', []);

        $check = $this->createChecker('example.com')->check($document);

        self::assertSame([
            'The section "Empty" has no links: it is left out of the file.',
            '1 links repeat the URL of another one, the first: "https://example.com/a".',
            '2 links do not have an absolute http(s) URL, the first: "/relative".',
            '1 links are on another host than "example.com", the first: "https://other.example/b". Check "framework.router.default_uri", or the --base-url of llms-txt:dump.',
            '1 links have no title, the first: "https://other.example/b".',
        ], $check->warnings);
    }

    public function testSkipsTheHostWithoutOne(): void
    {
        $check = $this->createChecker('')->check((new Document('Example'))->addLink('Pages', 'https://other.example/b', 'B'));

        self::assertSame([], $check->warnings);
    }

    private function createChecker(string $host): LinkChecker
    {
        return new LinkChecker(new UrlGenerator(new RouteCollection(), new RequestContext(host: $host, scheme: 'https')));
    }
}
