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

namespace Silarhi\LlmsTxtBundle\Tests\Fixtures;

use Silarhi\LlmsTxtBundle\Attribute\AsLlmsTxtLinkProvider;
use Silarhi\LlmsTxtBundle\Model\Link;

/**
 * What an application link provider looks like: one service, several sections, a generator per section.
 */
#[AsLlmsTxtLinkProvider(section: 'Blog', priority: -20)]
final readonly class CatalogLinks
{
    /**
     * After PopulateSubscriber (priority 0): the second offer follows the first one.
     *
     * @return iterable<Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Offers', priority: -10)]
    public function offers(): iterable
    {
        yield new Link('https://example.com/offers/second', 'Second offer', content: static fn (): iterable => ["Half price,\n", 'this week.']);
    }

    /**
     * @return list<Link>
     */
    public function __invoke(): array
    {
        return [new Link('https://example.com/blog', 'Blog', 'The latest posts', '## Posts')];
    }

    /**
     * Registered by hand in LinkProviderPassTest: a static provider needs no instance.
     *
     * @return list<Link>
     */
    public static function staticLinks(): array
    {
        return [];
    }
}
