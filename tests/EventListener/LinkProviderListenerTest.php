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

namespace Silarhi\LlmsTxtBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\LinkProviderListener;
use Silarhi\LlmsTxtBundle\Exception\InvalidLinkProviderException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;

final class LinkProviderListenerTest extends TestCase
{
    public function testAddsTheLinksWithoutReadingThem(): void
    {
        $read = false;
        $links = static function () use (&$read): iterable {
            $read = true;

            yield new Link('https://example.com/blog', 'Blog');
        };

        $document = new Document();
        (new LinkProviderListener($links(...), 'Blog', 'blog::__invoke'))(new LlmsTxtPopulateEvent($document));

        self::assertFalse($read);
        self::assertSame('Blog', $document->getSections()[0]->getName());
        self::assertEquals([new Link('https://example.com/blog', 'Blog')], iterator_to_array($document->getSections()[0]->getLinks(), false));
    }

    public function testNeedsAnIterable(): void
    {
        $listener = new LinkProviderListener(static fn (): string => 'nope', 'Blog', 'blog::__invoke');

        $this->expectException(InvalidLinkProviderException::class);
        $this->expectExceptionMessage('The llms.txt link provider "blog::__invoke" must return an iterable of Silarhi\LlmsTxtBundle\Model\Link, "string" returned.');

        $listener(new LlmsTxtPopulateEvent(new Document()));
    }
}
