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

namespace Silarhi\LlmsTxtBundle\Tests\Service;

use Generator;
use IteratorAggregate;
use LogicException;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;

final class MarkdownRendererTest extends TestCase
{
    public function testRendersTheSpecFormat(): void
    {
        $document = (new Document('Example', 'An example site.', "Some details.\n\n- a list"))
            ->addLink(Section::OPTIONAL, 'https://example.com/legal', 'Legal notice')
            ->addLink('Offers', 'https://example.com/offers/first', 'First offer', 'The first one')
            ->addLink('Offers', 'https://example.com/offers/second', 'Second offer');
        $document->section('Empty');

        self::assertSame(<<<'MD'
            # Example

            > An example site.

            Some details.

            - a list

            ## Offers

            - [First offer](https://example.com/offers/first): The first one
            - [Second offer](https://example.com/offers/second)

            ## Optional

            - [Legal notice](https://example.com/legal)

            MD, $this->render($document));
    }

    public function testRendersTheTitleAlone(): void
    {
        self::assertSame("# Example\n", $this->render(new Document('Example', '  ', '')));
    }

    public function testKeepsEachValueOnItsLine(): void
    {
        $document = (new Document("Multi\nline", "A\n  summary"))
            ->addLink("Sec\ntion", 'https://example.com', "A\ntitle", "A\r\ndescription");

        self::assertSame(<<<'MD'
            # Multi line

            > A summary

            ## Sec tion

            - [A title](https://example.com): A description

            MD, $this->render($document));
    }

    public function testEscapesLinks(): void
    {
        $document = (new Document('Example'))
            ->addLink('Pages', 'https://example.com/a page (1)', 'The [best] \\ page');

        self::assertStringContainsString(
            '- [The \\[best\\] \\\\ page](https://example.com/a%20page%20%281%29)',
            $this->render($document),
        );
    }

    public function testReadsTheLazyLinksInOrder(): void
    {
        $offers = static function (): Generator {
            yield 'a' => new Link('https://example.com/offers/1', 'Offer 1');
            yield 'a' => new Link('https://example.com/offers/2', 'Offer 2');
        };

        $document = (new Document('Example'))
            ->addLink('Offers', 'https://example.com/offers', 'All offers')
            ->addLinks('Offers', $offers())
            ->addLinks('Empty', (static fn (): Generator => yield from [])())
            ->addLinks('Offers', [new Link('https://example.com/offers/3', 'Offer 3')]);

        self::assertSame(<<<'MD'
            # Example

            ## Offers

            - [All offers](https://example.com/offers)
            - [Offer 1](https://example.com/offers/1)
            - [Offer 2](https://example.com/offers/2)
            - [Offer 3](https://example.com/offers/3)

            MD, $this->render($document));
    }

    public function testRequiresATitleBeforeAnyChunk(): void
    {
        // reading the links would throw a LogicException instead
        $links = new class implements IteratorAggregate {
            public function getIterator(): Generator
            {
                throw new LogicException('The links should not be read');
            }
        };
        $document = (new Document(" \n"))->addLinks('Offers', $links);

        $this->expectException(MissingTitleException::class);

        (new MarkdownRenderer())->render($document);
    }

    private function render(Document $document): string
    {
        $content = '';
        foreach ((new MarkdownRenderer())->render($document) as $chunk) {
            $content .= $chunk;
        }

        return $content;
    }
}
