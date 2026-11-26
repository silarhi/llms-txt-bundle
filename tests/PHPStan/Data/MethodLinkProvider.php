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

final class MethodLinkProvider
{
    /**
     * @return list<Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Products')]
    public function products(): array
    {
        return [];
    }

    /**
     * @return list<Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Posts')]
    #[AsLlmsTxtLinkProvider(section: 'Archives')]
    public static function posts(): array
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
