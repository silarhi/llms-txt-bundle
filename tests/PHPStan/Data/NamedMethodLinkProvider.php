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

namespace Silarhi\LlmsTxtBundle\Tests\PHPStan\Data;

use Silarhi\LlmsTxtBundle\Attribute\AsLlmsTxtLinkProvider;
use Silarhi\LlmsTxtBundle\Model\Link;

#[AsLlmsTxtLinkProvider(section: 'Offers', method: 'offers')]
#[AsLlmsTxtLinkProvider('Deals', 'DEALS')]
final class NamedMethodLinkProvider
{
    /**
     * @return list<Link>
     */
    public function offers(): array
    {
        return [];
    }

    /**
     * Method names are case-insensitive: the attribute names it "DEALS".
     *
     * @return list<Link>
     */
    public function deals(): array
    {
        return [];
    }

    /**
     * @return list<Link>
     */
    public function forgotten(): array
    {
        return [];
    }
}
