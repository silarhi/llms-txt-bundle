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

use Silarhi\LlmsTxtBundle\Model\Link;

final class NotALinkProvider
{
    /**
     * @return list<Link>
     */
    public function links(): array
    {
        return [];
    }
}
