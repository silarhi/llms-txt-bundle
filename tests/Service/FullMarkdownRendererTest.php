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
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Service\FullMarkdownRenderer;

final class FullMarkdownRendererTest extends TestCase
{
    public function testRendersThePagesWithAContent(): void
    {
        $document = (new Document('Example', 'An example site.'))
            ->addLink(Section::OPTIONAL, 'https://example.com/legal', 'Legal', 'Not read')
            ->addLink('Pages', 'https://example.com/about', 'About')
            ->addLinks('Pages', [
                new Link('https://example.com/contact', 'Contact', 'Not rendered', "\n\nWrite to us.\n\n"),
                new Link('https://example.com/blank', 'Blank', content: '  '),
                new Link('https://example.com/guide (2)', "The\nguide", content: static fn (): string => "## Install\n\nRun it."),
                new Link('https://example.com/blog', 'Blog', content: static function (): Generator {
                    yield '';
                    yield "First post,\n";
                    yield 'second post';
                }),
            ])
            ->addLinks(Section::OPTIONAL, [new Link('https://example.com/terms', 'Terms', content: 'The terms.')]);

        self::assertSame(<<<'MD'
            # Example

            > An example site.

            # Contact

            Source: https://example.com/contact

            Write to us.

            # The guide

            Source: https://example.com/guide%20%282%29

            ## Install

            Run it.

            # Blog

            Source: https://example.com/blog

            First post,
            second post

            # Terms

            Source: https://example.com/terms

            The terms.

            MD, implode('', [...(new FullMarkdownRenderer())->render($document)]));
    }

    public function testRequiresATitle(): void
    {
        $this->expectException(MissingTitleException::class);

        (new FullMarkdownRenderer())->render(new Document());
    }
}
