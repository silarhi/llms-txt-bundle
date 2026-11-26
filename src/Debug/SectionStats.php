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

use JsonSerializable;
use Override;

/**
 * A section of the document and its number of links.
 *
 * @internal
 */
final readonly class SectionStats implements JsonSerializable
{
    public function __construct(
        public string $name,
        public int $links,
    ) {
    }

    /**
     * @return array{name: string, links: int}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'links' => $this->links];
    }
}
