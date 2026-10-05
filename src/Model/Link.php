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

namespace Silarhi\LlmsTxtBundle\Model;

/**
 * One "- [title](url): description" entry of a section.
 */
final readonly class Link
{
    public function __construct(
        public string $url,
        public string $title,
        public ?string $description = null,
    ) {
    }
}
